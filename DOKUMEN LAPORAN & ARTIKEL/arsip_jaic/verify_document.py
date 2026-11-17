import docx
import os
from docx.oxml.ns import qn

target = 'DOKUMEN LAPORAN & ARTIKEL/ARTIKEL_ILMIAH_JAIC_SINTA3_FINAL.docx'
template = 'JAIC Template 2027.docx'

print(f"Target file exists: {os.path.exists(target)}, size: {os.path.getsize(target):,} bytes")
print(f"Template file exists: {os.path.exists(template)}, size: {os.path.getsize(template):,} bytes")

doc = docx.Document(target)
print("\n=== SECTIONS ===")
print("Sections count:", len(doc.sections))
for i, s in enumerate(doc.sections):
    sectPr = s._sectPr
    cols = sectPr.xpath('./w:cols')
    col_str = "None"
    if cols:
        col_str = f"num={cols[0].get(qn('w:num'))}, space={cols[0].get(qn('w:space'))}"
    print(f"Section {i}: start_type={s.start_type}, cols={col_str}")

print("\n=== PARAGRAPHS & HEADINGS ===")
headings = []
for p in doc.paragraphs:
    if p.style.name.startswith('Heading') or p.text.startswith(('I.', 'II.', 'III.', 'IV.', 'ACKNOWLEDGMENT', 'FUNDING', 'AUTHOR', 'CONFLICT', 'INFORMED', 'ETHICAL', 'DATA', 'REFERENCES')):
        headings.append(f"[{p.style.name}] {p.text}")
for h in headings:
    print("  ", h)

print("\n=== TABLES ===")
print("Tables count:", len(doc.tables))
for i, t in enumerate(doc.tables):
    rows = len(t.rows)
    cols = len(t.columns)
    first_cell = t.rows[0].cells[0].text.replace('\n', ' ')[:30]
    print(f"Table {i}: {rows} rows x {cols} cols | First cell: {repr(first_cell)}")

print("\n=== IMAGES / INLINE SHAPES ===")
images_found = 0
for p in doc.paragraphs:
    runs_with_drawings = [r for r in p.runs if 'drawing' in r._r.xml]
    if runs_with_drawings:
        images_found += len(runs_with_drawings)
print(f"Images embedded: {images_found}")

print("\n=== REFERENCES CHECK ===")
ref_count = 0
for p in doc.paragraphs:
    if p.text.strip().startswith('[') and ']' in p.text:
        ref_count += 1
        num = p.text.split(']')[0] + ']'
        print(f"  {num}: {p.text[:70]}...")
print(f"Total references detected: {ref_count}")
