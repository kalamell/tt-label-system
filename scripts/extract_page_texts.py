#!/usr/bin/env python3
"""
Extract plain text from each page of a PDF using PyMuPDF (fitz).
ใช้ dict mode + PUA normalization เพื่อให้ภาษาไทยถูกต้อง

ใช้เป็น fallback เมื่อ smalot/pdfparser ไม่สามารถอ่าน PDF ได้ (เช่น Shopee PDF)

Usage:  python3 extract_page_texts.py <pdf_path>
Output: JSON { "1": "text", "2": "text", ... }  keyed by 1-based page number
Exit:   0=ok, 1=no PyMuPDF, 2=bad args, 3=error
"""

import sys
import json

try:
    import fitz
except ImportError:
    print('PyMuPDF not installed. Run: pip3 install pymupdf', file=sys.stderr)
    sys.exit(1)

# Thai PUA → Unicode mapping (same as extract_product_info.py)
THAI_PUA_MAP = {
    '\uf700': '\u0e48',  # ่ mai ek
    '\uf701': '\u0e49',  # ้ mai tho
    '\uf702': '\u0e4a',  # ๊ mai tri
    '\uf703': '\u0e4b',  # ๋ mai jattawa
    '\uf704': '\u0e4c',  # ์ thantakhat
    '\uf705': '\u0e4d',  # ํ nikhahit
    '\uf706': '\u0e31',  # ั sara a
    '\uf707': '\u0e34',  # ิ sara i
    '\uf708': '\u0e35',  # ี sara ii
    '\uf709': '\u0e36',  # ึ sara ue
    '\uf70a': '\u0e48',  # ่ mai ek  (alt)
    '\uf70b': '\u0e49',  # ้ mai tho (alt)
    '\uf70c': '\u0e4a',  # ๊ mai tri (alt)
    '\uf70d': '\u0e4b',  # ๋ mai jattawa (alt)
    '\uf70e': '\u0e4c',  # ์ thantakhat (alt)
    '\uf70f': '\u0e4d',  # ํ nikhahit (alt)
}


def normalize_thai(text: str) -> str:
    for pua, thai in THAI_PUA_MAP.items():
        text = text.replace(pua, thai)
    return fix_thai_order(text)


# Thai combining character sets
_TONE_MARKS   = set('\u0e48\u0e49\u0e4a\u0e4b\u0e4c\u0e4d')  # ่ ้ ๊ ๋ ์ ํ
_ABOVE_VOWELS = set('\u0e31\u0e34\u0e35\u0e36\u0e37\u0e38\u0e39\u0e3a')  # ั ิ ี ึ ื ุ ู ฺ


def fix_thai_order(text: str) -> str:
    """
    PDF มักเก็บ tone mark ก่อน sara uu/above vowel (ผิด Unicode order)
    เช่น ผ + ้ + ู แทนที่จะเป็น ผ + ู + ้
    ฟังก์ชันนี้ swap ให้ถูกต้องเพื่อให้ PHP regex match ได้
    """
    result = []
    i = 0
    while i < len(text):
        c = text[i]
        if c in _TONE_MARKS and i + 1 < len(text) and text[i + 1] in _ABOVE_VOWELS:
            result.append(text[i + 1])  # vowel ก่อน
            result.append(c)             # แล้วค่อย tone mark
            i += 2
        else:
            result.append(c)
            i += 1
    return ''.join(result)


def page_to_text(page) -> str:
    """
    Extract page text in reading order (top→bottom, left→right).
    Spans on the same y-level are joined with space.
    Different y-levels are joined with newline.
    """
    lines: dict[int, list[tuple[float, str]]] = {}

    for block in page.get_text('dict')['blocks']:
        for line in block.get('lines', []):
            for span in line.get('spans', []):
                text = normalize_thai(span['text']).strip()
                if not text:
                    continue
                y = round(span['origin'][1])
                x = span['origin'][0]
                lines.setdefault(y, []).append((x, text))

    rows = []
    for y in sorted(lines.keys()):
        row = ' '.join(t for _, t in sorted(lines[y]))
        rows.append(row)

    return '\n'.join(rows)


def main():
    if len(sys.argv) < 2:
        print('Usage: extract_page_texts.py <pdf_path>', file=sys.stderr)
        sys.exit(2)

    pdf_path = sys.argv[1]
    try:
        doc = fitz.open(pdf_path)
    except Exception as e:
        print(f'Cannot open PDF: {e}', file=sys.stderr)
        sys.exit(3)

    results = {}
    for i, page in enumerate(doc):
        text = page_to_text(page)
        if text.strip():
            results[str(i + 1)] = text

    print(json.dumps(results, ensure_ascii=False))
    sys.exit(0)


if __name__ == '__main__':
    main()
