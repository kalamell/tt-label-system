#!/usr/bin/env python3
"""
Extract product info (Product Name, SKU, Seller SKU, Qty) from TikTok Shop PDF
โดยใช้ x-position ของ text แต่ละ span เพื่อแยก column ได้ถูกต้อง

สาเหตุที่ต้องใช้ PyMuPDF:
  smalot/pdfparser concatenate text ซ้าย-ขวาโดยไม่รู้ column boundary
  ทำให้ SKU + Seller SKU ปนเข้าไปใน product_name

Column x-ranges (% of page width) จาก original TikTok PDF:
  Product Name : 0  – 42%
  SKU          : 43 – 57%
  Seller SKU   : 58 – 88%
  Qty          : 89 – 100%

Note: PDF blocks ไม่ได้เรียงตาม y-coordinate เสมอไป (พบใน continuation pages)
      → collect spans ทั้งหมดแล้ว sort ตาม y ก่อน process
      → group rows โดยใช้ y-position ของ SKU column เป็น anchor
      → รายการสินค้าหลายรายการใน 1 หน้า จะคั่นด้วย " | "

Usage: python3 extract_product_info.py <pdf_path>
Output (stdout): JSON object keyed by 1-based page number
  {"1": {"product_name": "...", "product_sku": "...", "seller_sku": "...", "quantity": 1}, ...}
Exit: 0=success, 1=no PyMuPDF, 2=bad args, 3=error
"""

import sys
import json
import re

try:
    import fitz
except ImportError:
    fitz = None

# Thai PUA → Unicode mapping
THAI_PUA_MAP = {
    '\uf700': '\u0e48',  # ่ mai ek
    '\uf701': '\u0e49',  # ้ mai tho
    '\uf702': '\u0e4a',  # ๊ mai tri
    '\uf703': '\u0e4b',  # ๋ mai jattawa
    '\uf704': '\u0e4c',  # ์ thantakhat
    '\uf705': '\u0e4d',  # ํ nikhahit
    '\uf706': '\u0e31',  # ั sara a (above)
    '\uf707': '\u0e34',  # ิ sara i
    '\uf708': '\u0e35',  # ี sara ii
    '\uf709': '\u0e36',  # ึ sara ue
    '\uf70a': '\u0e48',  # ่ mai ek  (confirmed)
    '\uf70b': '\u0e49',  # ้ mai tho (confirmed)
    '\uf70c': '\u0e4a',  # ๊ mai tri
    '\uf70d': '\u0e4b',  # ๋ mai jattawa
    '\uf70e': '\u0e4c',  # ์ thantakhat (confirmed)
    '\uf70f': '\u0e4d',  # ํ nikhahit
}

def normalize_thai(text: str) -> str:
    for pua, thai in THAI_PUA_MAP.items():
        text = text.replace(pua, thai)
    return text


def main():
    if len(sys.argv) < 2:
        print('Usage: extract_product_info.py <pdf_path>', file=sys.stderr)
        sys.exit(2)

    if fitz is None:
        print('PyMuPDF not installed. Run: pip3 install pymupdf', file=sys.stderr)
        sys.exit(1)

    pdf_path = sys.argv[1]
    try:
        doc = fitz.open(pdf_path)
    except Exception as e:
        print(f'Cannot open PDF: {e}', file=sys.stderr)
        sys.exit(3)

    results = {}
    for page_idx, page in enumerate(doc):
        info = extract_product_info(page)
        addr = extract_address_block(page)
        if info or addr:
            entry = info or {}
            if addr:
                entry['recipient_address'] = addr
            results[str(page_idx + 1)] = entry

    print(json.dumps(results, ensure_ascii=False))
    sys.exit(0)


def extract_address_block(page) -> str:
    """
    ดึงที่อยู่ผู้รับ: อยู่ระหว่าง phone number และ "Shipping Date" / Vietnamese line
    PyMuPDF decode Thai font ได้ถูกต้อง ต่างจาก smalot/pdfparser ที่ตัด tone mark หาย

    โครงสร้างหน้า PDF:
      ... (ชื่อผู้รับ)
      (+66)xx*****xx   ← phone
      [ที่อยู่บรรทัด 1]
      [ที่อยู่บรรทัด 2]
      ...
      người mua không... / DROP-OFF / Shipping Date:  ← end marker
    """
    w = page.rect.width

    # Collect all spans
    all_spans = []
    for b in page.get_text('dict')['blocks']:
        for line in b.get('lines', []):
            for s in line.get('spans', []):
                text = normalize_thai(s['text']).strip()
                if text:
                    all_spans.append((s['origin'][1], s['origin'][0], text))

    all_spans.sort(key=lambda s: (round(s[0]), s[1]))

    # หา y ของ phone number
    phone_y = None
    for y, x, text in all_spans:
        if re.match(r'^\(\+66\)', text):
            phone_y = y
            break

    # หา y ของ end marker
    end_y = None
    end_markers = ('người mua', 'DROP-OFF', 'PICKUP', 'Shipping Date:', 'Order ID:')
    for y, x, text in all_spans:
        if phone_y and y <= phone_y:
            continue
        if any(m in text for m in end_markers):
            end_y = y
            break

    if phone_y is None or end_y is None:
        return ''

    # เก็บ spans ระหว่าง phone และ end (เฉพาะซีกซ้าย x < 60% ของหน้า)
    addr_spans = [
        (y, x, t) for y, x, t in all_spans
        if y > phone_y and y < end_y and x < w * 0.60
    ]

    # กรองออก: tracking number (79xxxxxxxxxx), ตัวเลข/ตัวอักษร 1-2 ตัวลอยๆ
    addr_parts = []
    for _, _, text in addr_spans:
        if re.match(r'^79\d{10}$', text):
            continue
        addr_parts.append(text)

    address = ' '.join(addr_parts).strip()
    address = re.sub(r'\s+', ' ', address)
    return address


def assign_to_row(y, row_anchors):
    """
    หา row anchor ที่ y นี้ควรอยู่
    Rule: y อยู่ใน row i ถ้า anchors[i] <= round(y) < anchors[i+1]
    """
    yk = round(y)
    for i, anchor in enumerate(row_anchors):
        next_a = row_anchors[i + 1] if i + 1 < len(row_anchors) else float('inf')
        if yk < next_a:
            return anchor
    return row_anchors[-1]


def extract_product_info(page):
    """
    อ่าน product table จาก 1 หน้า
    คืน dict ที่มี product_name/product_sku/seller_sku เป็น " | " คั่นระหว่างรายการ
    """
    w = page.rect.width

    SKU_X_START        = w * 0.40
    SELLER_SKU_X_START = w * 0.55
    QTY_X_START        = w * 0.85

    # Collect ALL spans จากทุก block แล้ว sort ตาม (y, x)
    all_spans = []
    for b in page.get_text('dict')['blocks']:
        for line in b.get('lines', []):
            for s in line.get('spans', []):
                text = normalize_thai(s['text']).strip()
                if text:
                    all_spans.append((s['origin'][1], s['origin'][0], text))

    all_spans.sort(key=lambda s: (round(s[0]), s[1]))

    in_header     = False
    in_data       = False
    product_spans = []  # (y, x, text)
    sku_spans     = []  # (y, x, text)
    seller_spans  = []  # (y, x, text)
    qty_spans     = []  # (y, x, text)

    for y, x, text in all_spans:
        if text == 'Product Name':
            in_header = True
            in_data   = True
            continue

        if in_header and text in ('SKU', 'Seller SKU', 'Qty'):
            continue

        if 'Qty Total' in text:
            in_data = False
            break

        if not in_data:
            continue

        # กรอง Order ID line ที่ตกอยู่ใน Seller SKU column
        if re.match(r'Order\s*ID:', text):
            continue

        if x >= QTY_X_START:
            qty_spans.append((y, x, text))
        elif x >= SELLER_SKU_X_START:
            seller_spans.append((y, x, text))
        elif x >= SKU_X_START:
            sku_spans.append((y, x, text))
        else:
            product_spans.append((y, x, text))

    if not product_spans and not sku_spans:
        return None

    # ----------------------------------------------------------------
    # Group spans into product rows using SKU y-positions as anchors
    # ----------------------------------------------------------------
    sku_row_ys = sorted(set(round(y) for y, x, t in sku_spans))

    if not sku_row_ys:
        # Fallback: single row (no SKU column found)
        product_name = ' '.join(t for _, _, t in product_spans).strip()
        seller_sku   = ' '.join(t for _, _, t in seller_spans).strip()
        quantity = 1
        for _, _, t in qty_spans:
            try: quantity = int(t); break
            except ValueError: pass
        return {
            'product_name': product_name or None,
            'product_sku':  None,
            'seller_sku':   seller_sku or None,
            'quantity':     quantity,
        }

    # Initialize rows
    rows = {a: {'product': [], 'sku': [], 'seller': [], 'qty': []} for a in sku_row_ys}

    for y, x, text in product_spans:
        rows[assign_to_row(y, sku_row_ys)]['product'].append((y, x, text))
    for y, x, text in sku_spans:
        rows[assign_to_row(y, sku_row_ys)]['sku'].append((y, x, text))
    for y, x, text in seller_spans:
        rows[assign_to_row(y, sku_row_ys)]['seller'].append((y, x, text))
    for y, x, text in qty_spans:
        rows[assign_to_row(y, sku_row_ys)]['qty'].append((y, x, text))

    # Build per-row results
    product_parts = []
    sku_parts     = []
    seller_parts  = []
    quantities    = []
    total_qty     = 0

    for anchor in sku_row_ys:
        rd = rows[anchor]

        pn  = ' '.join(t for _, _, t in sorted(rd['product'], key=lambda s: (round(s[0]), s[1]))).strip()
        sku = ' '.join(t for _, _, t in sorted(rd['sku'],     key=lambda s: (round(s[0]), s[1]))).strip()
        sel = ' '.join(t for _, _, t in sorted(rd['seller'],  key=lambda s: (round(s[0]), s[1]))).strip()

        row_qty = 0
        for _, _, t in rd['qty']:
            try: row_qty = int(t); break
            except ValueError: pass

        product_parts.append(pn)
        sku_parts.append(sku)
        seller_parts.append(sel)
        quantities.append(row_qty)
        total_qty += row_qty

    return {
        'product_name':    ' | '.join(filter(None, product_parts)) or None,
        'product_sku':     ' | '.join(filter(None, sku_parts))     or None,
        'seller_sku':      ' | '.join(filter(None, seller_parts))  or None,
        'quantity':        total_qty or 1,
        'item_quantities': ' | '.join(str(q) for q in quantities),  # เช่น "1 | 2 | 1"
    }


if __name__ == '__main__':
    main()
