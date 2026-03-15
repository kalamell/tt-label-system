#!/usr/bin/env python3
"""
Render PDF pages to PNG using PyMuPDF (fitz)
ไม่ต้องการ Ghostscript — PyMuPDF มี renderer ในตัวเอง

Called by LabelGeneratorService::renderWithPython()

Usage: python3 render_pdf.py <job_json_file>

Job JSON format:
{
    "pdf": "/absolute/path/to/file.pdf",
    "output_dir": "/absolute/path/to/output/dir",
    "dpi": 200,
    "pages": {
        "1": "790000000001",
        "2": "790000000002",
        ...
    }
}

Output: {output_dir}/{tracking_number}.png สำหรับแต่ละหน้า
Exit codes: 0 = success, 1 = no PyMuPDF, 2 = bad args, 3 = bad job file
"""

import sys
import os
import json

def main():
    if len(sys.argv) < 2:
        print("Usage: render_pdf.py <job_json_file>", file=sys.stderr)
        sys.exit(2)

    job_file = sys.argv[1]

    # อ่าน job JSON
    try:
        with open(job_file) as f:
            job = json.load(f)
    except Exception as e:
        print(f"Cannot read job file: {e}", file=sys.stderr)
        sys.exit(3)
    finally:
        # ล้าง job file หลังอ่าน
        try:
            os.unlink(job_file)
        except Exception:
            pass

    pdf_path   = job.get('pdf', '')
    output_dir = job.get('output_dir', '')
    pages      = {int(k): str(v) for k, v in job.get('pages', {}).items()}
    dpi        = int(job.get('dpi', 200))

    if not pdf_path or not output_dir or not pages:
        print("Missing required fields: pdf, output_dir, pages", file=sys.stderr)
        sys.exit(3)

    # Import PyMuPDF
    try:
        import fitz  # PyMuPDF
    except ImportError:
        print("PyMuPDF not installed. Run: pip3 install pymupdf", file=sys.stderr)
        sys.exit(1)

    os.makedirs(output_dir, exist_ok=True)

    # เปิด PDF
    try:
        doc = fitz.open(pdf_path)
    except Exception as e:
        print(f"Cannot open PDF: {e}", file=sys.stderr)
        sys.exit(3)

    # Matrix สำหรับ render ที่ DPI ที่กำหนด (PDF default = 72 DPI)
    mat = fitz.Matrix(dpi / 72.0, dpi / 72.0)

    rendered = 0
    skipped  = 0
    errors   = 0

    for page_num, tracking in pages.items():
        out_path = os.path.join(output_dir, f'{tracking}.png')

        # ข้ามถ้ามีอยู่แล้ว
        if os.path.exists(out_path):
            skipped += 1
            continue

        page_idx = page_num - 1  # PyMuPDF ใช้ 0-based index
        if page_idx < 0 or page_idx >= len(doc):
            print(f"Page {page_num} out of range (total: {len(doc)})", file=sys.stderr)
            errors += 1
            continue

        try:
            page = doc[page_idx]
            # render เป็น RGB PNG
            pix = page.get_pixmap(matrix=mat, colorspace=fitz.csRGB, alpha=False)
            pix.save(out_path)
            rendered += 1
        except Exception as e:
            print(f"Error rendering page {page_num}: {e}", file=sys.stderr)
            errors += 1

    doc.close()

    print(f"Done: rendered={rendered}, skipped={skipped}, errors={errors}")
    sys.exit(0 if errors == 0 else 3)


if __name__ == '__main__':
    main()
