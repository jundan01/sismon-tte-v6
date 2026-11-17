"""
build_jip_docx.py
Automated script to inject the finalized research article into
template JIP (Jurnal Informatika Polinema).docx while preserving
the original geometry, 2-column layout, styles, and headers/footers.
"""

import os
import docx
from docx.shared import Pt, Inches, Mm, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.table import WD_TABLE_ALIGNMENT
from lxml import etree

TEMPLATE_PATH = r"DOKUMEN LAPORAN & ARTIKEL/template JIP (Jurnal Informatika Polinema).docx"
OUTPUT_PATH = r"DOKUMEN LAPORAN & ARTIKEL/ARTIKEL_JIP_FINAL.docx"

def set_cell_margins(cell, top=20, bottom=20, left=15, right=15):
    """Set inner margins (padding) for a table cell in dxa (1 pt = 20 dxa)."""
    tcPr = cell._tc.get_or_add_tcPr()
    tcMar = etree.SubElement(tcPr, '{http://schemas.openxmlformats.org/wordprocessingml/2006/main}tcMar')
    for m, val in [('top', top), ('bottom', bottom), ('left', left), ('right', right)]:
        node = etree.SubElement(tcMar, f'{{http://schemas.openxmlformats.org/wordprocessingml/2006/main}}{m}')
        node.set('{http://schemas.openxmlformats.org/wordprocessingml/2006/main}w', str(val))
        node.set('{http://schemas.openxmlformats.org/wordprocessingml/2006/main}type', 'dxa')

def set_table_borders(table):
    """Apply formal academic booktabs borders to table (top/bottom rules, no vertical rules)."""
    tblPr = table._tbl.tblPr
    tblBorders = tblPr.find('{http://schemas.openxmlformats.org/wordprocessingml/2006/main}tblBorders')
    if tblBorders is None:
        tblBorders = etree.SubElement(tblPr, '{http://schemas.openxmlformats.org/wordprocessingml/2006/main}tblBorders')
    
    for b_name in ['top', 'bottom']:
        node = tblBorders.find(f'{{http://schemas.openxmlformats.org/wordprocessingml/2006/main}}{b_name}')
        if node is None:
            node = etree.SubElement(tblBorders, f'{{http://schemas.openxmlformats.org/wordprocessingml/2006/main}}{b_name}')
        node.set('{http://schemas.openxmlformats.org/wordprocessingml/2006/main}val', 'single')
        node.set('{http://schemas.openxmlformats.org/wordprocessingml/2006/main}sz', '4') # 0.5 pt
        node.set('{http://schemas.openxmlformats.org/wordprocessingml/2006/main}space', '0')
        node.set('{http://schemas.openxmlformats.org/wordprocessingml/2006/main}color', '000000')

    for b_name in ['left', 'right', 'insideH', 'insideV']:
        node = tblBorders.find(f'{{http://schemas.openxmlformats.org/wordprocessingml/2006/main}}{b_name}')
        if node is None:
            node = etree.SubElement(tblBorders, f'{{http://schemas.openxmlformats.org/wordprocessingml/2006/main}}{b_name}')
        node.set('{http://schemas.openxmlformats.org/wordprocessingml/2006/main}val', 'nil')

def set_header_row_border(row):
    """Apply bottom border to header row cells."""
    for cell in row.cells:
        tcPr = cell._tc.get_or_add_tcPr()
        tcBorders = tcPr.find('{http://schemas.openxmlformats.org/wordprocessingml/2006/main}tcBorders')
        if tcBorders is None:
            tcBorders = etree.SubElement(tcPr, '{http://schemas.openxmlformats.org/wordprocessingml/2006/main}tcBorders')
        bot = tcBorders.find('{http://schemas.openxmlformats.org/wordprocessingml/2006/main}bottom')
        if bot is None:
            bot = etree.SubElement(tcBorders, '{http://schemas.openxmlformats.org/wordprocessingml/2006/main}bottom')
        bot.set('{http://schemas.openxmlformats.org/wordprocessingml/2006/main}val', 'single')
        bot.set('{http://schemas.openxmlformats.org/wordprocessingml/2006/main}sz', '4')
        bot.set('{http://schemas.openxmlformats.org/wordprocessingml/2006/main}space', '0')
        bot.set('{http://schemas.openxmlformats.org/wordprocessingml/2006/main}color', '000000')

def add_heading_1(doc, text):
    """Major section heading: e.g. 1. PENDAHULUAN"""
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.LEFT
    p.paragraph_format.space_before = Pt(10)
    p.paragraph_format.space_after = Pt(4)
    p.paragraph_format.keep_with_next = True
    p.paragraph_format.first_line_indent = Pt(0)
    run = p.add_run(text)
    run.font.name = "Times New Roman"
    run.font.size = Pt(10)
    run.font.bold = True
    return p

def add_heading_2(doc, text):
    """Subheading: e.g. 2.1 Arsitektur Pemisahan Jalur Kueri"""
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.LEFT
    p.paragraph_format.space_before = Pt(7)
    p.paragraph_format.space_after = Pt(2)
    p.paragraph_format.keep_with_next = True
    p.paragraph_format.first_line_indent = Pt(0)
    run = p.add_run(text)
    run.font.name = "Times New Roman"
    run.font.size = Pt(10)
    run.font.bold = True
    return p

def add_body_p(doc, text, space_after=4, indent=True, bold_prefix=None):
    """Add a justified body text paragraph with optional indent and bold prefix."""
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
    p.paragraph_format.line_spacing = 1.0
    p.paragraph_format.space_after = Pt(space_after)
    p.paragraph_format.first_line_indent = Pt(14) if indent else Pt(0)
    
    if bold_prefix:
        r_pre = p.add_run(bold_prefix)
        r_pre.font.name = "Times New Roman"
        r_pre.font.size = Pt(10)
        r_pre.font.bold = True
        
    run = p.add_run(text)
    run.font.name = "Times New Roman"
    run.font.size = Pt(10)
    return p

def add_equation_box(doc, formula_lines, eq_num_str):
    """Add centered mathematical equation with right-aligned numbering using a borderless 2-column table."""
    table = doc.add_table(rows=1, cols=2)
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    table.autofit = False
    tblPr = table._tbl.tblPr
    tblW = tblPr.find('{http://schemas.openxmlformats.org/wordprocessingml/2006/main}tblW')
    if tblW is not None:
        tblW.set('{http://schemas.openxmlformats.org/wordprocessingml/2006/main}w', '4252')
        tblW.set('{http://schemas.openxmlformats.org/wordprocessingml/2006/main}type', 'dxa')
        
    tblBorders = etree.SubElement(tblPr, '{http://schemas.openxmlformats.org/wordprocessingml/2006/main}tblBorders')
    for b in ['top', 'left', 'bottom', 'right', 'insideH', 'insideV']:
        node = etree.SubElement(tblBorders, f'{{http://schemas.openxmlformats.org/wordprocessingml/2006/main}}{b}')
        node.set('{http://schemas.openxmlformats.org/wordprocessingml/2006/main}val', 'nil')

    row = table.rows[0]
    cell_eq = row.cells[0]
    cell_num = row.cells[1]
    
    cell_eq.width = Mm(65)
    cell_num.width = Mm(10)
    set_cell_margins(cell_eq, top=20, bottom=20, left=0, right=0)
    set_cell_margins(cell_num, top=20, bottom=20, left=0, right=0)

    p_eq = cell_eq.paragraphs[0]
    p_eq.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p_eq.paragraph_format.line_spacing = 1.0
    p_eq.paragraph_format.space_after = Pt(2)
    p_eq.paragraph_format.space_before = Pt(2)

    for i, line in enumerate(formula_lines):
        if i > 0:
            p_eq = cell_eq.add_paragraph()
            p_eq.alignment = WD_ALIGN_PARAGRAPH.CENTER
            p_eq.paragraph_format.line_spacing = 1.0
            p_eq.paragraph_format.space_after = Pt(2)
            p_eq.paragraph_format.space_before = Pt(0)
        r = p_eq.add_run(line)
        r.font.name = "Times New Roman"
        r.font.size = Pt(9.5)
        r.font.italic = True

    p_num = cell_num.paragraphs[0]
    p_num.alignment = WD_ALIGN_PARAGRAPH.RIGHT
    p_num.paragraph_format.line_spacing = 1.0
    p_num.paragraph_format.space_after = Pt(2)
    p_num.paragraph_format.space_before = Pt(2)
    r_num = p_num.add_run(eq_num_str)
    r_num.font.name = "Times New Roman"
    r_num.font.size = Pt(9.5)

    p_sp = doc.add_paragraph()
    p_sp.paragraph_format.space_before = Pt(0)
    p_sp.paragraph_format.space_after = Pt(2)

def add_table_data(doc, caption, headers, rows, col_widths_mm, alignments=None, note=None):
    """Add a professional academic table fitting the 75mm column width with autofit and 8.5pt font."""
    # Caption
    p_cap = doc.add_paragraph()
    p_cap.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p_cap.paragraph_format.space_before = Pt(6)
    p_cap.paragraph_format.space_after = Pt(3)
    p_cap.paragraph_format.keep_with_next = True
    r_cap = p_cap.add_run(caption)
    r_cap.font.name = "Times New Roman"
    r_cap.font.size = Pt(8.5)
    r_cap.font.bold = True

    # Table
    table = doc.add_table(rows=len(rows) + 1, cols=len(headers))
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    table.autofit = True
    tblPr = table._tbl.tblPr
    tblW = tblPr.find('{http://schemas.openxmlformats.org/wordprocessingml/2006/main}tblW')
    if tblW is not None:
        tblW.set('{http://schemas.openxmlformats.org/wordprocessingml/2006/main}w', '4252')
        tblW.set('{http://schemas.openxmlformats.org/wordprocessingml/2006/main}type', 'dxa')
    set_table_borders(table)

    # Format Header Row
    hdr_row = table.rows[0]
    hdr_row._tr.get_or_add_trPr().append(etree.Element('{http://schemas.openxmlformats.org/wordprocessingml/2006/main}tblHeader'))
    hdr_row._tr.get_or_add_trPr().append(etree.Element('{http://schemas.openxmlformats.org/wordprocessingml/2006/main}cantSplit'))
    set_header_row_border(hdr_row)

    for i, h_text in enumerate(headers):
        cell = hdr_row.cells[i]
        cell.width = Mm(col_widths_mm[i])
        set_cell_margins(cell, top=25, bottom=25, left=15, right=15)
        p = cell.paragraphs[0]
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        p.paragraph_format.line_spacing = 1.0
        p.paragraph_format.space_before = Pt(1)
        p.paragraph_format.space_after = Pt(1)
        r = p.add_run(h_text)
        r.font.name = "Times New Roman"
        r.font.size = Pt(8.5)
        r.font.bold = True

    # Format Data Rows
    for r_idx, row_data in enumerate(rows):
        row = table.rows[r_idx + 1]
        row._tr.get_or_add_trPr().append(etree.Element('{http://schemas.openxmlformats.org/wordprocessingml/2006/main}cantSplit'))
        for c_idx, val in enumerate(row_data):
            cell = row.cells[c_idx]
            cell.width = Mm(col_widths_mm[c_idx])
            set_cell_margins(cell, top=20, bottom=20, left=15, right=15)
            p = cell.paragraphs[0]
            if alignments and c_idx < len(alignments):
                p.alignment = alignments[c_idx]
            else:
                p.alignment = WD_ALIGN_PARAGRAPH.CENTER
            p.paragraph_format.line_spacing = 1.0
            p.paragraph_format.space_before = Pt(1)
            p.paragraph_format.space_after = Pt(1)
            r = p.add_run(val)
            r.font.name = "Times New Roman"
            r.font.size = Pt(8.5)
            # Bold if it's the proposed DAE v1 or key finding
            if "DAE v1" in val or ("5.000" in val and c_idx == 3):
                r.font.bold = True

    # Note if present
    if note:
        p_note = doc.add_paragraph()
        p_note.alignment = WD_ALIGN_PARAGRAPH.LEFT
        p_note.paragraph_format.space_before = Pt(2)
        p_note.paragraph_format.space_after = Pt(4)
        r_note = p_note.add_run(note)
        r_note.font.name = "Times New Roman"
        r_note.font.size = Pt(8)
        r_note.font.italic = True
    else:
        p_sp = doc.add_paragraph()
        p_sp.paragraph_format.space_before = Pt(0)
        p_sp.paragraph_format.space_after = Pt(3)

def add_reference(doc, text):
    """Add APA 7th reference paragraph with hanging indent."""
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
    p.paragraph_format.line_spacing = 1.0
    p.paragraph_format.space_after = Pt(4)
    p.paragraph_format.left_indent = Pt(14)
    p.paragraph_format.first_line_indent = Pt(-14)
    
    parts = text.split('*')
    for i, part in enumerate(parts):
        if not part:
            continue
        run = p.add_run(part)
        run.font.name = "Times New Roman"
        run.font.size = Pt(9)
        if i % 2 == 1:
            run.font.italic = True

def build_paper():
    print(f"Loading template from {TEMPLATE_PATH}...")
    doc = docx.Document(TEMPLATE_PATH)
    body = doc._body._body

    # 1. Update Title (P2)
    p_title = doc.paragraphs[2]
    p_title.text = ""
    p_title.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p_title.paragraph_format.space_before = Pt(6)
    p_title.paragraph_format.space_after = Pt(6)
    r_title = p_title.add_run("Pencarian Eksak Kolom Identitas Terenkripsi Deterministik Terautentikasi pada PostgreSQL")
    r_title.font.name = "Times New Roman"
    r_title.font.size = Pt(14)
    r_title.font.bold = True

    # Clear template instruction in P3
    doc.paragraphs[3].text = ""

    # 2. Update Authors (P5)
    p_auth = doc.paragraphs[5]
    p_auth.text = ""
    p_auth.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p_auth.paragraph_format.space_before = Pt(4)
    p_auth.paragraph_format.space_after = Pt(2)
    
    r1 = p_auth.add_run("Ajria Jundan Jahid Idris")
    r1.font.name = "Times New Roman"
    r1.font.size = Pt(10)
    r1.font.bold = True
    
    r1_sup = p_auth.add_run("1*")
    r1_sup.font.name = "Times New Roman"
    r1_sup.font.size = Pt(10)
    r1_sup.font.bold = True
    r1_sup.font.superscript = True

    r_sep = p_auth.add_run(", ")
    r_sep.font.name = "Times New Roman"
    r_sep.font.size = Pt(10)
    r_sep.font.bold = True

    r2 = p_auth.add_run("Bani Nurhakim")
    r2.font.name = "Times New Roman"
    r2.font.size = Pt(10)
    r2.font.bold = True

    r2_sup = p_auth.add_run("2")
    r2_sup.font.name = "Times New Roman"
    r2_sup.font.size = Pt(10)
    r2_sup.font.bold = True
    r2_sup.font.superscript = True

    # Clear P6
    doc.paragraphs[6].text = ""

    # 3. Update Affiliation (P8) with explicit line break between institution and street address
    p_aff = doc.paragraphs[8]
    p_aff.text = ""
    p_aff.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p_aff.paragraph_format.space_before = Pt(2)
    p_aff.paragraph_format.space_after = Pt(2)

    r_aff_sup = p_aff.add_run("1,2")
    r_aff_sup.font.name = "Times New Roman"
    r_aff_sup.font.size = Pt(10)
    r_aff_sup.font.superscript = True

    r_aff_inst = p_aff.add_run("Program Studi Manajemen Informatika, STMIK IKMI Cirebon")
    r_aff_inst.font.name = "Times New Roman"
    r_aff_inst.font.size = Pt(10)

    # Line break
    p_aff.add_run().add_break()

    r_aff_addr = p_aff.add_run("Jl. Perjuangan No. 10B, Karyamulya, Kec. Kesambi, Kota Cirebon, Jawa Barat 45131, Indonesia")
    r_aff_addr.font.name = "Times New Roman"
    r_aff_addr.font.size = Pt(10)

    # 4. Update Email (P9) with explicit line break between email and corresponding author
    p_email = doc.paragraphs[9]
    p_email.text = ""
    p_email.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p_email.paragraph_format.space_before = Pt(1)
    p_email.paragraph_format.space_after = Pt(6)

    r_em_sup = p_email.add_run("*")
    r_em_sup.font.name = "Times New Roman"
    r_em_sup.font.size = Pt(10)
    r_em_sup.font.superscript = True

    r_em_txt = p_email.add_run("ajriajundan123@gmail.com, baninurhakim@ikmi.ac.id")
    r_em_txt.font.name = "Times New Roman"
    r_em_txt.font.size = Pt(10)

    # Line break
    p_email.add_run().add_break()

    r_em_corr = p_email.add_run("*Penulis Korespondensi")
    r_em_corr.font.name = "Times New Roman"
    r_em_corr.font.size = Pt(10)

    # Clear P10, P11, P12
    doc.paragraphs[10].text = ""
    doc.paragraphs[11].text = ""
    doc.paragraphs[12].text = ""

    # 5. Abstrak Title (P13)
    p_abs_title = doc.paragraphs[13]
    p_abs_title.text = ""
    p_abs_title.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p_abs_title.paragraph_format.space_before = Pt(6)
    p_abs_title.paragraph_format.space_after = Pt(4)
    r_at = p_abs_title.add_run("Abstrak")
    r_at.font.name = "Times New Roman"
    r_at.font.size = Pt(10)
    r_at.font.bold = True

    # Clear P14
    doc.paragraphs[14].text = ""

    # 6. Abstrak Text (P15)
    p_abs_text = doc.paragraphs[15]
    p_abs_text.text = ""
    p_abs_text.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
    p_abs_text.paragraph_format.line_spacing = 1.0
    p_abs_text.paragraph_format.space_after = Pt(4)
    p_abs_text.paragraph_format.first_line_indent = Pt(0)
    
    abstract_content = (
        "Penyelenggaraan Sistem Pemerintahan Berbasis Elektronik (SPBE) mewajibkan kepatuhan terhadap "
        "Undang-Undang Pelindungan Data Pribadi (UU PDP) No. 27 Tahun 2022, khususnya pengamanan kolom identitas "
        "kependudukan seperti Nomor Induk Kependudukan (NIK) dan Nomor Induk Pegawai (NIP). Namun, penerapan enkripsi "
        "probabilistik standar seperti AES-GCM merusak keterurutan indeks pohon B-Tree relasional, sehingga memaksa pembacaan "
        "pemindaian berurutan (Sequential Scan) di memori aplikasi dengan kompleksitas waktu linear O(N). Sebaliknya, "
        "enkripsi deterministik konvensional berbasis AES-CBC dengan vektor inisialisasi (Initialization Vector/IV) statis "
        "rentan terhadap kebocoran kesamaan prefiks (prefix leakage) dan manipulasi bit. Penelitian ini mengusulkan skema "
        "Deterministic Authenticated Encryption (DAE v1) berbasis paradigma Synthetic Initialization Vector (SIV-like CTR mode) "
        "yang memadukan derivasi kunci independen HKDF-SHA256, komputasi IV sintesis HMAC-SHA256 berpemisah domain kolom, "
        "dan enkripsi stream AES-256-CTR tanpa padding, terintegrasi dengan pemisahan jalur kueri indeks parsial unik B-Tree "
        "PostgreSQL (WHERE status <> 'DITOLAK'). Evaluasi empiris pada klaster kontainer PostgreSQL 16 dengan 100.000 rekaman "
        "membuktikan bahwa DAE v1 mengeliminasi 100% kebocoran blok pertama 16-byte NIP (dari 4.900.500 pasangan baris bocor "
        "pada AES-CBC menjadi 0 pasangan). Pada uji pencarian multi-skala (N = 1.000 hingga 100.000), DAE v1 mempertahankan "
        "kompleksitas logaritmik O(log N) dengan median latensi 1,101 ms pada N = 100.000 dan puncak memori proses CLI sebesar "
        "10 MB, setara secara statistik dengan kueri teks polos (0,879 ms), sementara metode dekripsi memori AES-GCM terdegradasi "
        "parah hingga 230,820 ms. Selain itu, DAE v1 menghemat 32,8% total ruang penyimpanan basis data dan 28,9% memori indeks "
        "dibandingkan skema blind index, dengan latensi penulisan INSERT yang sebanding (~2,1 ms)."
    )
    r_abs = p_abs_text.add_run(abstract_content)
    r_abs.font.name = "Times New Roman"
    r_abs.font.size = Pt(10)

    # Clear P16
    doc.paragraphs[16].text = ""

    # 7. Kata Kunci (P17)
    p_kw = doc.paragraphs[17]
    p_kw.text = ""
    p_kw.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
    p_kw.paragraph_format.line_spacing = 1.0
    p_kw.paragraph_format.space_after = Pt(8)
    p_kw.paragraph_format.first_line_indent = Pt(0)
    
    r_kw_lbl = p_kw.add_run("Kata kunci: ")
    r_kw_lbl.font.name = "Times New Roman"
    r_kw_lbl.font.size = Pt(10)
    r_kw_lbl.font.bold = True

    r_kw_val = p_kw.add_run("Enkripsi Deterministik Terautentikasi, Synthetic Initialization Vector, Indeks B-Tree, PostgreSQL, UU Pelindungan Data Pribadi.")
    r_kw_val.font.name = "Times New Roman"
    r_kw_val.font.size = Pt(10)

    # Clear P18, P19
    doc.paragraphs[18].text = ""
    doc.paragraphs[19].text = ""
    # P20 has the sectPr for Section 0, KEEP IT UNTOUCHED

    # 8. Remove old template body paragraphs and tables (from index 21 up to child before final sectPr)
    to_remove = len(body) - 1 - 21
    print(f"Removing {to_remove} dummy template elements after header block...")
    for _ in range(to_remove):
        body.remove(body[21])

    print("Populating 2-column article body into Section 1...")

    # ==================== 1. PENDAHULUAN ====================
    add_heading_1(doc, "1. PENDAHULUAN")
    add_body_p(doc, 
        "Transformasi digital pelayanan publik dalam kerangka Sistem Pemerintahan Berbasis Elektronik (SPBE) "
        "menuntut integrasi data yang cepat dan aman. Pada instansi pemerintah daerah, seperti Dinas Komunikasi, "
        "Informatika, dan Statistik (DKIS) Kota Cirebon melalui aplikasi Sistem Monitoring Penerbitan Tanda Tangan Elektronik "
        "(Sismon-TTE), data identitas kependudukan aparatur sipil negara (ASN) berupa Nomor Induk Kependudukan (NIK) dan "
        "Nomor Induk Pegawai (NIP) diproses secara intensif untuk verifikasi sertifikat elektronik ke Balai Sertifikasi "
        "Elektronik (BSrE) Badan Siber dan Sandi Negara (BSSN) guna mendukung efisiensi pelayanan publik dan tata kelola "
        "keamanan aplikasi web pemerintahan (Arizal et al., 2026; Noor et al., 2021). Berlakunya Undang-Undang Republik "
        "Indonesia Nomor 27 Tahun 2022 tentang Pelindungan Data Pribadi (UU PDP), khususnya Pasal 35 dan Pasal 39, mewajibkan "
        "pengendali data pribadi untuk menerapkan langkah teknis dan operasional keamanan guna melindungi data pribadi, "
        "di mana enkripsi merupakan kontrol teknis utama dari ancaman akses tidak sah, kebocoran, serta manipulasi data.", indent=False)

    add_body_p(doc,
        "Meskipun kewajiban hukum menuntut enkripsi data saat tersimpan (data-at-rest), implementasi enkripsi pada sistem basis "
        "data relasional (Relational Database Management System/RDBMS) seperti PostgreSQL menghadapi dilema fundamental "
        "arsitektural antara keamanan kriptografi dan kinerja pencarian kueri (Carvalho et al., 2025; Trabucco et al., 2026). "
        "Standar enkripsi simetris modern terautentikasi (Authenticated Encryption with Associated Data/AEAD) seperti AES-256-GCM "
        "bersifat probabilistik, di mana setiap operasi enkripsi menghasilkan ciphertext yang berbeda secara acak berkat penggunaan "
        "IV/nonce yang unik (Pina et al., 2024). Sifat probabilistik ini secara inheren menghancurkan relasi kesetaraan (equality mapping), "
        "sehingga klausul pencarian eksak SQL (WHERE nik = ?) tidak dapat memanfaatkan struktur indeks pohon B-Tree (Gui et al., 2024). "
        "Akibatnya, RDBMS terpaksa melakukan pemindaian tabel menyeluruh (Sequential Scan) atau aplikasi harus menarik seluruh baris "
        "ke memori untuk didekripsi satu per satu (In-Memory Full Decryption Scan), yang memicu degradasi komputasi linear O(N) dan "
        "ledakan penggunaan memori (heap exhaustion) pada volume data besar (Carvalho et al., 2025).", indent=True)

    add_body_p(doc,
        "Untuk mempertahankan pemanfaatan indeks B-Tree berkecepatan O(log N), sejumlah sistem mengadopsi enkripsi deterministik "
        "konvensional seperti AES-256-CBC dengan IV statis (Bellare et al., 2007). Namun, penggunaan IV statis pada mode CBC membuka "
        "celah keamanan serius berupa kebocoran pola (pattern leakage). Karena ukuran blok AES adalah 16 byte (128 bit), dua rekaman "
        "plaintext yang berbagi 16 karakter pertama yang sama akan menghasilkan blok ciphertext pertama yang identik (Chen et al., 2024; "
        "Dou et al., 2024). Pada format NIP di Indonesia yang terdiri atas 18 digit terstruktur (8 digit tanggal lahir + 6 digit TMT "
        "pengangkatan CPNS + 1 digit jenis kelamin + 3 digit nomor urut), ASN yang berada pada kohort formasi yang sama akan memiliki "
        "14 hingga 16 digit awal yang identik, sehingga penyerang yang memiliki akses ke media penyimpanan dapat menyimpulkan kesamaan "
        "tanggal lahir dan instansi pegawai dengan menganalisis kesamaan blok ciphertext (Dou et al., 2024). Selain itu, skema deterministik "
        "tanpa tag autentikasi rentan terhadap serangan manipulasi bit (bit-flipping attack) dan serangan lintas-kolom (cross-column replay attack), "
        "di mana ciphertext NIK dapat disuntikkan ke kolom NIP tanpa terdeteksi (Bellare et al., 2007).", indent=True)

    add_body_p(doc,
        "Pendekatan alternatif lain yang sering diusulkan adalah blind indexing berbasis HMAC (Chen et al., 2024; Khan et al., 2025). "
        "Pada pendekatan ini, data identitas dienkripsi secara probabilistik menggunakan AES-GCM, sementara kolom tambahan disediakan "
        "untuk menyimpan digest HMAC sebagai indeks pencarian deterministik terpisah. Walaupun aman, arsitektur blind index menimbulkan "
        "konsekuensi penggandaan ruang penyimpanan (storage amplification), memicu penalti latensi ganda pada siklus penulisan (write "
        "overhead), serta memerlukan dekripsi kandidat tambahan pada setiap pencarian untuk memverifikasi potensi tabrakan hash "
        "(Chen et al., 2024; Khan et al., 2025).", indent=True)

    add_body_p(doc,
        "Menjawab tantangan tersebut, penelitian ini merancang, mengimplementasikan, dan mengevaluasi skema Deterministic Authenticated "
        "Encryption (DAE v1) yang diadaptasi dari paradigma Synthetic Initialization Vector (SIV-like) Rogaway & Shrimpton (2006). "
        "Penelitian ini memberikan tiga kontribusi utama:", indent=True)

    add_body_p(doc,
        "1. Rancang Bangun Kriptografi DAE v1 Bebas Ekstensi Eksternal: Mengembangkan skema enkripsi deterministik terautentikasi "
        "murni berbasis pustaka C bawaan OpenSSL di PHP 8.3 yang menggabungkan derivasi sub-kunci independen HKDF-SHA256, "
        "pembangkitan IV sintesis HMAC-SHA256 dengan pemisahan domain kolom (Associated Data), dan enkripsi stream AES-256-CTR "
        "tanpa kebutuhan padding PKCS#7.", indent=False)

    add_body_p(doc,
        "2. Arsitektur Pemisahan Jalur Kueri & Indeks Parsial B-Tree: Merancang arsitektur basis data relasional PostgreSQL yang "
        "memisahkan jalur pencarian eksak terenkripsi dan pencarian teks nama bebas, serta mengimplementasikan indeks parsial unik "
        "(WHERE status <> 'DITOLAK') yang mendukung kepatuhan aturan bisnis pendaftaran ulang pasca penolakan berkas.", indent=False)

    add_body_p(doc,
        "3. Audit Tolok Ukur Empiris & Evaluasi Kebocoran Riil: Melakukan pengujian terisolasi bebas bias memori per proses CLI pada "
        "100.000 rekaman data sintetis realistis DKIS Kota Cirebon (Seed: 20261116), yang membuktikan eliminasi total kebocoran blok "
        "pertama NIP (0,0000%), kesetaraan latensi sub-milidetik terhadap teks polos (O(log N)), efisiensi penyimpanan fisik 32,8% "
        "lebih hemat dibanding blind index, dan ketahanan mutlak terhadap manipulasi bit.", indent=False)

    # ==================== 2. METODE PENELITIAN ====================
    add_heading_1(doc, "2. METODE PENELITIAN")
    
    add_heading_2(doc, "2.1 Arsitektur Pemisahan Jalur Kueri dan Indeks Parsial Unik")
    add_body_p(doc,
        "Pada sistem pemantauan penerbitan sertifikat elektronik, pengguna melakukan pencarian melalui satu bilah input (single "
        "search input) yang dapat menerima nomor identitas numerik (NIK 16 digit atau NIP 18 digit) maupun nama lengkap pegawai. "
        "Praktik buruk yang jamak ditemukan adalah menggabungkan seluruh kondisi ke dalam satu klausa SQL disjungtif (WHERE nik = ? "
        "OR nip = ? OR nama_lengkap ILIKE ?). Dalam PostgreSQL, klausul OR yang mencampuradukkan parameter kesetaraan terenkripsi "
        "dan pencarian pola teks memaksa perencana kueri (query planner) melakukan BitmapOr atau bahkan beralih ke Sequential Scan "
        "paralel yang lambat.", indent=False)

    add_body_p(doc,
        "Untuk mengatasi inefisiensi tersebut, arsitektur sistem memisahkan jalur kueri di tingkat Application Service Layer "
        "sebelum kueri dieksekusi ke PostgreSQL:", indent=True)

    add_body_p(doc,
        "1. Deteksi Pola Input: Input masukan divalidasi menggunakan ekspresi reguler. Jika input merupakan string numerik tepat "
        "16 digit, input dialihkan ke jalur kueri eksak NIK. Jika tepat 18 digit, input dialihkan ke jalur kueri eksak NIP. Di luar "
        "pola numerik tersebut, input dialihkan ke jalur pencarian teks nama dengan wildcard escaping (ILIKE ?).", indent=False)

    add_body_p(doc,
        "2. Indeks Parsial Unik (Partial Unique Index): Berdasarkan aturan bisnis tata kelola sertifikat TTE, seorang pegawai tidak "
        "boleh memiliki dua pendaftaran aktif bersamaan (MENUNGGU_VERIFIKASI atau DISETUJUI). Namun, jika pendaftaran sebelumnya "
        "berstatus DITOLAK (misalnya karena ketidaksesuaian dokumen surat tugas), pegawai bersangkutan berhak mendaftar kembali "
        "dengan NIK/NIP yang sama. Konstrain unik standar (UNIQUE(nik)) akan menolak pendaftaran ulang tersebut. Oleh karena itu, "
        "PostgreSQL dikonfigurasi menggunakan indeks parsial unik B-Tree:", indent=False)

    # Code / SQL formula box
    add_equation_box(doc, [
        "CREATE UNIQUE INDEX idx_pendaftaran_nik_active",
        "ON pendaftaran_tte (nik) WHERE status <> 'DITOLAK';"
    ], "")

    add_body_p(doc,
        "Indeks ini secara otomatis mengecualikan baris berstatus DITOLAK dari pohon B-Tree aktif, sehingga menjamin integritas "
        "pencegahan duplikasi aktif sekaligus mengizinkan riwayat penolakan tersimpan berdampingan secara legal di tabel basis data.", indent=True)

    add_heading_2(doc, "2.2 Formulasi Kriptografi Skema DAE v1")
    add_body_p(doc,
        "Konstruksi DAE v1 dirancang mengikuti paradigma PRF-then-CTR (Rogaway & Shrimpton, 2006; Bellare et al., 2007) dengan "
        "jaminan keamanan Deterministic Authenticated Encryption. Alur pembentukan ciphertext dijabarkan sebagai berikut:", indent=False)

    add_body_p(doc,
        "1. Derivasi Sub-Kunci Independen (HKDF-SHA256): Dari kunci utama aplikasi K_master (256-bit), diturunkan dua sub-kunci "
        "independen berukuran masing-masing 32 byte menggunakan fungsi derivasi kunci berbasis HMAC (HKDF):", indent=False)

    add_equation_box(doc, [
        "K_enc = HKDF-SHA256(K_master, salt=∅, info='sismon-tte:enc:v1', L=32)",
        "K_mac = HKDF-SHA256(K_master, salt=∅, info='sismon-tte:mac:v1', L=32)"
    ], "(1)")

    add_body_p(doc,
        "Pemisahan sub-kunci ini menjamin bahwa kebocoran kunci enkripsi stream tidak mengekspos kunci integritas autentikasi MAC. "
        "Kunci di-cache pada memori proses worker untuk meniadakan overhead komputasi berulang.", indent=True)

    add_body_p(doc,
        "2. Pengikatan Konteks Kolom (Domain Separation) & Vektor Inisialisasi Sintesis (V): Untuk mencegah serangan injeksi lintas-kolom, "
        "label nama kolom basis data (label ∈ {'nik', 'nip'}) diikatkan sebagai Associated Authenticated Data (AAD) dengan pemisah byte nol:", indent=False)

    add_equation_box(doc, [
        "Context = label || 0x00"
    ], "(2)")

    add_body_p(doc,
        "Synthetic IV V berukuran 16 byte (128 bit) dihitung dengan mengevaluasi HMAC-SHA256 terhadap penggabungan konteks kolom "
        "dan keseluruhan plaintext P:", indent=True)

    add_equation_box(doc, [
        "V = Truncate_16B(HMAC-SHA256(K_mac, Context || P))"
    ], "(3)")

    add_body_p(doc,
        "Karena komputasi V melibatkan seluruh karakter plaintext, perubahan sekecil 1 bit pada karakter terakhir NIK/NIP akan "
        "mengacak seluruh 16 byte nilai V berdasarkan sifat Strict Avalanche Criterion (SAC) dari SHA-256.", indent=True)

    add_body_p(doc,
        "3. Enkripsi Stream AES-256-CTR & Format Ciphertext: Plaintext dienkripsi menggunakan algoritma AES-256 mode Counter (CTR) "
        "dengan parameter IV = V:", indent=False)

    add_equation_box(doc, [
        "C_stream = AES-256-CTR(K_enc, IV=V, P)"
    ], "(4)")

    add_body_p(doc,
        "Mode CTR mengubah cipher blok menjadi stream cipher, sehingga panjang ciphertext tepat sama dengan panjang plaintext "
        "tanpa memerlukan alokasi byte padding PKCS#7. Ciphertext mentah disusun dengan membubuhkan penanda versi 1-byte (0x01):", indent=True)

    add_equation_box(doc, [
        "C_raw = 0x01 || V || C_stream",
        "Ciphertext = base64_encode(C_raw)"
    ], "(5, 6)")

    add_body_p(doc,
        "4. Verifikasi Autentikasi dan Dekripsi: Proses dekripsi membaca C_raw dan mengekstrak versi, V, serta C_stream. "
        "Plaintext kandidat P' didekripsi menggunakan AES-256-CTR dengan IV = V. Selanjutnya, sistem menghitung ulang tag autentikasi:", indent=False)

    add_equation_box(doc, [
        "V' = Truncate_16B(HMAC-SHA256(K_mac, Context || P'))"
    ], "(7)")

    add_body_p(doc,
        "Kecocokan tag diuji secara konstan-waktu menggunakan fungsi hash_equals(V, V'). Jika terdapat ketidakcocokan (akibat "
        "manipulasi bit atau penukaran label kolom), kueri seketika menolak data dengan mengembalikan nilai null.", indent=True)

    add_heading_2(doc, "2.3 Desain Lingkungan Eksperimen dan Dataset")
    add_body_p(doc,
        "Pengujian dilakukan pada infrastruktur terisolasi berbasis kontainer Docker untuk menjamin kemurnian pengukuran (reproducibility) "
        "tanpa mengganggu basis data operasional. Parameter lingkungan pengujian dicatat secara programmatic pada berkas metadata konfigurasi:", indent=False)

    add_body_p(doc,
        "• Spesifikasi Perangkat Keras Host: Intel(R) Core(TM) i5-7300U CPU @ 2.60GHz (2 core fisik, 4 logical thread), RAM fisik "
        "15,88 GB, Sistem Operasi Windows 10/11 x64, Docker Engine 29.9.3 (WSL2 Kernel 6.18.58.1-1).", indent=False)

    add_body_p(doc,
        "• Mesin Basis Data PostgreSQL: PostgreSQL 16.15 on x86_64-pc-linux-musl (Alpine Linux) dengan konfigurasi shared_buffers = 128MB, "
        "work_mem = 4MB, effective_cache_size = 4GB, dan random_page_cost = 4.", indent=False)

    add_body_p(doc,
        "• Dataset Sintetis Realistis: Sebanyak 100.000 baris pendaftaran disintesis menggunakan bilangan acak deterministik (Seed: 20261116). "
        "Format NIK menggunakan kode 5 kecamatan Kota Cirebon (327401–327405), tanggal lahir realistis (dengan offset +40 hari untuk wanita), "
        "dan nomor urut unik. Format NIP dibangun dari 500 kohort padat (tanggal lahir 1970–1999 dan TMT CPNS 1992–2026). Komposisi status "
        "pendaftaran disebarkan merata (interleaved) menggunakan algoritma pengacakan Fisher-Yates dengan proporsi: 60% MENUNGGU_VERIFIKASI "
        "(60.000 baris), 20% DISETUJUI (20.000 baris), dan 20% DITOLAK (20.000 baris).", indent=False)

    # ==================== 3. HASIL DAN PEMBAHASAN ====================
    add_heading_1(doc, "3. HASIL DAN PEMBAHASAN")

    add_heading_2(doc, "3.1 Evaluasi Rencana Eksekusi Kueri dan Audit Buffer PostgreSQL")
    add_body_p(doc,
        "Pengujian Fase 1 mengevaluasi rencana eksekusi fisik perencana kueri PostgreSQL menggunakan perintah EXPLAIN (ANALYZE, "
        "BUFFERS, FORMAT JSON) terhadap 30 target pencarian acak independen. Metrik penggunaan buffer I/O diekstraksi murni dari node "
        "akar (Root Plan Node) tanpa akumulasi rekursif untuk mencegah bias penggandaan multi-worker.", indent=False)

    add_body_p(doc,
        "Tabel 1 merangkum perbandingan performa rencana eksekusi kueri pada tabel berukuran 100.000 baris. Kueri terpisah (Query C, D, dan G) "
        "yang memanfaatkan indeks B-Tree menghasilkan waktu eksekusi sub-milidetik (0,075–0,082 ms). Yang paling mendasar, node akar "
        "kueri indeks B-Tree secara konsisten hanya membutuhkan tepat 4 Shared Hit Blocks (traversal node indeks B-Tree dan pengambilan halaman heap tabel) "
        "dengan 0 Shared Read Blocks, yang membuktikan bahwa seluruh data terlayani sempurna dari memori buffer pool tanpa ada aktivitas pembacaan "
        "fisik ke disk (zero disk thrashing).", indent=True)

    add_body_p(doc,
        "Sebaliknya, pada pengujian Kontrol K1 di mana indeks sengaja dinonaktifkan, PostgreSQL beralih ke Parallel Sequential Scan yang "
        "melibatkan 2 worker latar belakang. Waktu eksekusi melonjak menjadi 13,921 ms (174 kali lebih lambat) dengan konsumsi I/O mencapai "
        "2.367 blok buffer. Temuan ini menegaskan bahwa keberadaan indeks B-Tree pada kolom identitas merupakan prasyarat mutlak bagi efisiensi "
        "sistem basis data relasional.", indent=True)

    # Tabel 1
    t1_headers = ["Kueri", "Struktur SQL", "Rencana", "Indeks Digunakan", "Hit", "Mean", "p50"]
    t1_rows = [
        ["Query C", "NIK Exact + Single", "Index Scan", "idx_nik_active", "4", "0,075 ms", "0,059 ms"],
        ["Query D", "NIK + Status IN", "Index Scan", "idx_nik_active", "4", "0,082 ms", "0,068 ms"],
        ["Query G", "NIK + DITOLAK", "Index Scan", "idx_nik_all", "4", "0,078 ms", "0,066 ms"],
        ["Kontrol K1", "NIK (No Index)", "Seq Scan", "None", "2.367", "13,921 ms", "13,850 ms"],
    ]
    t1_widths = [10, 16, 11, 14, 6, 9, 9] # Sum = 75 mm
    add_table_data(doc, "Tabel 1. Evaluasi Rencana Eksekusi Kueri dan Konsumsi Buffer (N = 100.000)", 
                   t1_headers, t1_rows, t1_widths, note="Catatan: Seluruh kueri indeks menghasilkan 0 Shared Read Blocks (zero disk thrashing).")

    add_heading_2(doc, "3.2 Evaluasi Kebocoran Blok Pertama 16-Byte NIP dan Biaya Komputasi Kriptografi")
    add_body_p(doc,
        "Salah satu kelemahan paling berbahaya dari enkripsi deterministik berbasis cipher blok konvensional (AES-256-CBC) adalah "
        "kebocoran prefiks identitas. Pengujian Fase 2 mengevaluasi fenomena ini secara empiris dengan membandingkan skema CBC lama melawan "
        "skema DAE v1 baru pada dua mode generator NIP (Realistis dan Worst-Case 500 Kohort) pada skala N = 5.000 dan N = 100.000.", indent=False)

    # Tabel 2
    t2_headers = ["Mode NIP", "N", "Skema", "Blok Unik", "Tabrak", "Pasangan", "Kebocoran"]
    t2_rows = [
        ["Realistis", "5k", "Legacy CBC", "4.991", "9", "9", "0,36 %"],
        ["Realistis", "5k", "DAE v1", "5.000", "0", "0", "0,00 %"],
        ["Realistis", "100k", "Legacy CBC", "95.329", "4.465", "4.883", "9,14 %"],
        ["Realistis", "100k", "DAE v1", "100.000", "0", "0", "0,00 %"],
        ["Worst-Case", "5k", "Legacy CBC", "500", "500", "22.500", "100,00 %"],
        ["Worst-Case", "5k", "DAE v1", "5.000", "0", "0", "0,00 %"],
        ["Worst-Case", "100k", "Legacy CBC", "1.500", "1.000", "4.900.500", "99,50 %"],
        ["Worst-Case", "100k", "DAE v1", "100.000", "0", "0", "0,00 %"],
    ]
    t2_widths = [13, 7, 13, 11, 9, 11, 11] # Sum = 75 mm
    add_table_data(doc, "Tabel 2. Evaluasi Empiris Kebocoran Blok Pertama 16-Byte NIP",
                   t2_headers, t2_rows, t2_widths, note="Catatan: Pasangan berbagi dihitung dari sum C(count, 2). DAE v1 mencapai 0% kebocoran.")

    add_body_p(doc,
        "Data pada Tabel 2 membuktikan bahwa pada skema CBC dengan IV statis, sebanyak 4.900.500 pasangan baris mengalami kebocoran blok "
        "pertama pada kondisi worst-case N = 100.000 (tingkat kebocoran mencapai 99,50%). Penyerang pasif dapat mengelompokkan pegawai "
        "ke dalam kohort tanggal lahir dan TMT yang sama hanya dengan melihat kesamaan 16 byte awal ciphertext. Sebaliknya, skema DAE v1 "
        "mencatatkan 0 blok tabrakan, 0 pasangan berbagi, dan 0,0000% kebocoran di seluruh pengujian. Komputasi HMAC-SHA256 menyerap seluruh "
        "18 digit NIP, sehingga memicu efek avalanche penuh yang mengacak total nilai IV sintesis V.", indent=True)

    # Tabel 3
    t3_headers = ["Operasi", "Skema", "Min", "Mean", "p50", "p95", "SD"]
    t3_rows = [
        ["Enkripsi", "Legacy CBC", "27,5 µs", "49,2 µs", "41,5 µs", "74,1 µs", "44,3 µs"],
        ["Enkripsi", "DAE v1", "14,7 µs", "21,2 µs", "17,7 µs", "31,6 µs", "75,3 µs"],
        ["Dekripsi", "Legacy CBC", "6,2 µs", "9,9 µs", "7,9 µs", "16,9 µs", "12,6 µs"],
        ["Dekripsi", "DAE v1", "12,2 µs", "16,6 µs", "13,7 µs", "26,6 µs", "17,2 µs"],
    ]
    t3_widths = [11, 14, 10, 10, 10, 10, 10] # Sum = 75 mm
    add_table_data(doc, "Tabel 3. Tolok Ukur Waktu Komputasi Kriptografi (100.000 Operasi)",
                   t3_headers, t3_rows, t3_widths, note="Catatan: Enkripsi DAE v1 56,8% lebih cepat dibanding CBC karena tanpa alokasi buffer padding PKCS#7.")

    add_body_p(doc,
        "Tabel 3 menyajikan hasil pengukuran 100.000 operasi enkripsi dan dekripsi murni. Enkripsi DAE v1 justru tercatat 56,8% lebih cepat "
        "(rata-rata 21,244 µs vs 49,183 µs) dibanding skema lama karena kunci derivasi di-cache secara statis dan mode CTR meniadakan alokasi "
        "buffer padding PKCS#7. Pada operasi dekripsi, DAE v1 menambahkan sedikit overhead sebesar +6,73 µs (hanya 0,0067 ms) untuk "
        "memverifikasi tag HMAC-SHA256 via hash_equals(). Overhead sub-mikrodetik ini sepenuhnya terabaikan dalam pipeline kueri basis data.", indent=True)

    add_heading_2(doc, "3.3 Tolok Ukur Latensi Pencarian Multi-Skala Skenario A–D")
    add_body_p(doc,
        "Pengujian Fase 3 membandingkan secara komprehensif empat skenario pencarian exact-match NIK pada tiga skala volume data "
        "(N = 1.000, 10.000, dan 100.000) menggunakan proses CLI terisolasi untuk meniadakan distorsi akumulasi memori:", indent=False)

    add_body_p(doc, "• Skenario A: Pencarian Teks Polos (Plaintext) berindeks B-Tree.", indent=False)
    add_body_p(doc, "• Skenario B: Pencarian Deterministic Authenticated Encryption (DAE v1 CTR-SIV) berindeks B-Tree.", indent=False)
    add_body_p(doc, "• Skenario C: Pencarian Probabilistik AES-256-GCM dengan pemindaian dekripsi memori penuh (In-Memory Decryption Scan).", indent=False)
    add_body_p(doc, "• Skenario D: Pencarian Probabilistik AES-256-GCM berbantuan HMAC-SHA256 Blind Index.", indent=False)

    # Tabel 4
    t4_headers = ["N", "Skenario", "p50", "Mean", "p95", "SD", "CPU", "RAM"]
    t4_rows = [
        ["1k", "A: Plaintext", "0,756 ms", "0,887 ms", "1,463 ms", "0,338 ms", "0,413 ms", "10 MB"],
        ["1k", "B: DAE v1", "0,825 ms", "0,917 ms", "1,392 ms", "0,350 ms", "0,445 ms", "10 MB"],
        ["1k", "C: In-Memory", "2,931 ms", "2,991 ms", "5,226 ms", "1,065 ms", "2,384 ms", "10 MB"],
        ["1k", "D: Blind Index", "0,979 ms", "1,045 ms", "1,583 ms", "0,338 ms", "0,520 ms", "10 MB"],
        ["10k", "A: Plaintext", "0,812 ms", "0,865 ms", "1,248 ms", "0,209 ms", "0,372 ms", "10 MB"],
        ["10k", "B: DAE v1", "0,846 ms", "0,937 ms", "1,555 ms", "0,271 ms", "0,429 ms", "10 MB"],
        ["10k", "C: In-Memory", "27,341 ms", "26,798 ms", "38,729 ms", "9,440 ms", "23,764 ms", "10 MB"],
        ["10k", "D: Blind Index", "1,697 ms", "1,896 ms", "4,496 ms", "0,832 ms", "0,876 ms", "10 MB"],
        ["100k", "A: Plaintext", "0,879 ms", "1,021 ms", "2,070 ms", "0,406 ms", "0,430 ms", "10 MB"],
        ["100k", "B: DAE v1", "1,101 ms", "1,256 ms", "2,081 ms", "0,388 ms", "0,555 ms", "10 MB"],
        ["100k", "C: In-Memory", "230,820 ms", "234,812 ms", "390,749 ms", "112,406 ms", "194,578 ms", "10 MB"],
        ["100k", "D: Blind Index", "1,175 ms", "1,501 ms", "3,239 ms", "0,772 ms", "0,682 ms", "10 MB"],
    ]
    t4_widths = [6, 17, 9, 9, 9, 8, 9, 8] # Sum = 75 mm
    add_table_data(doc, "Tabel 4. Rekapitulasi Statistik Latensi Pencarian Multi-Skala",
                   t4_headers, t4_rows, t4_widths, note="Catatan: n = 30 sampel acak per skenario, kecuali Skenario C pada N = 100.000 menggunakan n = 10.")

    add_body_p(doc,
        "Berdasarkan Tabel 4, Skenario A dan Skenario B menunjukkan karakteristik performa logaritmik O(log N) yang sangat konsisten. "
        "Pada N = 100.000, median latensi Skenario B adalah 1,101 ms, hanya berselisih tipis +0,222 ms dibandingkan teks polos (0,879 ms). "
        "Selisih 0,22 ms ini merupakan cerminan biaya komputasi enkripsi parameter lokal di sisi PHP, bukan perlambatan di sisi indeks basis data PostgreSQL.", indent=True)

    add_body_p(doc,
        "Sebaliknya, Skenario C memperlihatkan ketidaklayakan skema dekripsi memori pada RDBMS: latensi meningkat linear O(N) dari 2,99 ms "
        "pada N = 1.000 menjadi 234,81 ms pada N = 100.000, dengan rata-rata konsumsi waktu CPU PHP mencapai 194,58 ms. Pada volume data jutaan rekaman, "
        "metode pemindaian in-memory diproyeksikan membutuhkan waktu puluhan detik per transaksi kueri. Skenario D (Blind Index) mencatatkan "
        "latensi median 1,175 ms pada N = 100.000, sedikit lebih lambat dibanding Skenario B akibat perlunya dua tahapan kriptografi terpisah.", indent=True)

    add_heading_2(doc, "3.4 Biaya Penulisan (Write Overhead) dan Ukuran Penyimpanan Fisik Storage")
    add_body_p(doc,
        "Pengujian penulisan mengukur waktu eksekusi individual dari 1.000 transaksi INSERT rekaman baru ke dalam tabel berukuran N = 100.000 "
        "baris yang telah memiliki indeks parsial aktif. Pengukuran ukuran fisik disk dilakukan menggunakan fungsi sistem PostgreSQL "
        "SELECT pg_relation_size(...) dan SELECT pg_total_relation_size(...).", indent=False)

    # Tabel 5
    t5_headers = ["Skema", "Ops", "Min", "Mean", "p50", "p95", "p99", "SD"]
    t5_rows = [
        ["Plaintext", "1.000", "1,595 ms", "2,630 ms", "2,088 ms", "4,717 ms", "9,113 ms", "3,939 ms"],
        ["DAE v1", "1.000", "1,597 ms", "2,301 ms", "2,104 ms", "3,231 ms", "5,083 ms", "1,044 ms"],
        ["Blind Index", "1.000", "1,697 ms", "2,304 ms", "2,131 ms", "3,050 ms", "4,753 ms", "1,146 ms"],
    ]
    t5_widths = [14, 7, 9, 9, 9, 9, 9, 9] # Sum = 75 mm
    add_table_data(doc, "Tabel 5. Pengukuran Biaya Penulisan 1.000 INSERT (N = 100.000)",
                   t5_headers, t5_rows, t5_widths, note="Catatan: Median waktu penulisan ketiga skema setara (~2,1 ms), didominasi latensi penulisan WAL.")

    add_body_p(doc,
        "Hasil pada Tabel 5 memperlihatkan bahwa median waktu penulisan ketiga skema praktis berada pada level yang sama: 2,088 ms (Plaintext), "
        "2,104 ms (DAE v1), dan 2,131 ms (Blind Index). Waktu transaksi INSERT pada RDBMS relasional didominasi oleh latensi I/O penulisan "
        "transaksi ke Write-Ahead Log (WAL) dan pemeliharaan page split pada struktur pohon B-Tree di disk. Biaya komputasi enkripsi CPU lokal "
        "(~0,021 ms pada DAE v1) hanya berkontribusi kurang dari 1,0% dari total durasi transaksi.", indent=True)

    # Tabel 6
    t6_headers = ["Skema", "Tabel Heap", "Ukuran Heap", "Indeks B-Tree", "Ukuran Indeks", "Total Relasi"]
    t6_rows = [
        ["Plaintext", "bench_plain", "10,61 MB", "idx_plain_nik", "3,15 MB", "15,97 MB"],
        ["DAE v1", "bench_dae", "13,33 MB", "idx_dae_nik", "5,20 MB", "20,73 MB"],
        ["Blind Index", "bench_bidx", "21,33 MB", "idx_bidx_nik", "7,31 MB", "30,85 MB"],
    ]
    t6_widths = [13, 11, 13, 13, 12, 13] # Sum = 75 mm
    add_table_data(doc, "Tabel 6. Ukuran Fisik Penyimpanan Tabel dan Indeks (N = 100.000)",
                   t6_headers, t6_rows, t6_widths, 
                   note="Catatan: Total ukuran relasi mencakup heap tabel, indeks aktif, primary key, dan overhead metadata PostgreSQL. DAE v1 menghemat 32,8% total ruang penyimpanan dan 28,9% memori indeks dibandingkan blind index.")

    add_body_p(doc,
        "Dari Tabel 6, terungkap keunggulan efisiensi arsitektural skema DAE v1 dibandingkan blind indexing: Pertama, penghematan total "
        "penyimpanan mencapai 32,8% (20,73 MB vs 30,85 MB). Skema blind index membutuhkan ukuran heap tabel ganda (21,33 MB, +101% dibanding "
        "plaintext) karena tabel harus menyimpan dua kolom panjang secara bersamaan: nik_gcm (ciphertext probabilistik 60 karakter) dan "
        "nik_bidx (hash HMAC 64 karakter hex). Kedua, efisiensi memori indeks B-Tree DAE v1 (5,20 MB) menghemat 28,9% memori indeks "
        "dibandingkan blind index (7,31 MB). Penghematan memori indeks sangat krusial dalam RDBMS produksi berskala besar agar indeks dapat "
        "tetap berada seutuhnya di dalam memori kerja buffer pool (shared_buffers).", indent=True)

    add_heading_2(doc, "3.5 Analisis Keterbatasan Keamanan dan Lingkungan Nyata")
    add_body_p(doc,
        "Penelitian ini secara jujur mengidentifikasi batasan teoretis dan arsitektural dari skema yang diusulkan:", indent=False)

    add_body_p(doc,
        "1. Kebocoran Pola Kesamaan (Equality Pattern Leakage): Meskipun DAE v1 mengeliminasi 100% kebocoran prefiks 16-byte NIP, sifat "
        "deterministik secara matematis tetap membocorkan relasi kesetaraan (frequency equality): dua rekaman dengan plaintext yang sama "
        "persis akan selalu menghasilkan ciphertext yang identik. Pada konteks kolom identitas kependudukan di instansi pemerintah, "
        "kerentanan ini berhasil dimitigasi secara substansial oleh konstrain unik aktif (idx_pendaftaran_nik_active), di mana satu NIK/NIP "
        "aktif hanya boleh muncul satu kali dalam basis data. Namun, jika skema ini diterapkan pada atribut non-unik dengan entropi rendah "
        "(seperti kolom agama, gender, atau status perkawinan), penyerang dapat melakukan serangan inferensi frekuensi (frequency analysis attack).", indent=False)

    add_body_p(doc,
        "2. Latensi Jaringan Fisik (Network Round-Trip Time): Pengukuran latensi kueri pada penelitian ini (0,07–1,10 ms) dievaluasi pada lingkungan "
        "lokal terkontainerisasi. Pada arsitektur fisik terdistribusi nyata di mana server web aplikasi terhubung ke klaster basis data "
        "melalui jaringan lokal (LAN), latensi jaringan fisik (RTT berkisar 0,3–1,0 ms) akan mendominasi waktu respons end-to-end, sehingga "
        "selisih performa antara plaintext dan DAE v1 pada level pengguna akhir akan semakin tidak terbedakan secara statistik.", indent=False)

    # ==================== 4. KESIMPULAN ====================
    add_heading_1(doc, "4. KESIMPULAN")
    add_body_p(doc,
        "Penelitian ini berhasil membuktikan bahwa skema Deterministic Authenticated Encryption (DAE v1) berbasis paradigma Synthetic IV "
        "(SIV-like CTR mode) yang dipadukan dengan pemisahan jalur kueri indeks parsial B-Tree PostgreSQL (WHERE status <> 'DITOLAK') mampu "
        "menyelesaikan dilema mendasar antara keamanan kriptografi dan efisiensi pencarian basis data relasional. Pada dataset 100.000 rekaman, "
        "skema DAE v1 mengeliminasi 100% kebocoran blok pertama 16-byte NIP (dari 4.900.500 pasangan baris bocor pada AES-CBC menjadi 0 pasangan), "
        "menolak manipulasi bit secara konstan waktu, dan mempertahankan performa pencarian logaritmik O(log N) dengan median latensi 1,101 ms "
        "dan konsumsi memori CLI murni 10 MB, setara secara statistik dengan kueri teks polos (0,879 ms). Dibandingkan arsitektur blind index, "
        "DAE v1 menghemat 32,8% total ruang penyimpanan basis data dan 28,9% memori indeks B-Tree dengan waktu penulisan INSERT yang sebanding (~2,1 ms).", indent=False)

    add_body_p(doc,
        "Pengembangan selanjutnya dapat diarahkan pada integrasi modul Key Management Service (KMS) terpusat berbasis perangkat keras "
        "(Hardware Security Module/HSM) dengan protokol rotasi kunci otomatis tanpa waktu henti (zero-downtime key rotation), serta "
        "eksplorasi skema Order-Preserving Encryption (OPE) untuk mendukung kueri rentang (range queries) terenkripsi pada atribut non-identitas.", indent=True)

    # ==================== UCAPAN TERIMA KASIH ====================
    add_heading_1(doc, "UCAPAN TERIMA KASIH")
    add_body_p(doc,
        "Penulis menyampaikan apresiasi dan terima kasih kepada Dinas Komunikasi, Informatika, dan Statistik (DKIS) Kota Cirebon atas penyediaan "
        "konteks studi kasus layanan Sistem Monitoring Penerbitan Tanda Tangan Elektronik (Sismon-TTE), serta civitas akademika STMIK IKMI Cirebon "
        "yang telah memfasilitasi sarana infrastruktur komputasi dalam pelaksanaan penelitian ini.", indent=False)

    # ==================== DAFTAR PUSTAKA ====================
    add_heading_1(doc, "DAFTAR PUSTAKA")
    
    references = [
        "Arizal, Hilal, M., & Priambodo, D. F. (2026). Security analysis of Indonesian region government web applications based on NIST SP 800-115 and WSTG v4.2. *Journal of Information Systems and Informatics*, 8(2), 1776–1799. https://doi.org/10.63158/journalisi.v8i2.1558",
        "Bellare, M., Boldyreva, A., & O'Neill, A. (2007). Deterministic and efficiently searchable encryption. In A. Menezes (Ed.), *Advances in Cryptology – CRYPTO 2007* (LNCS Vol. 4622, pp. 535–552). Springer. https://doi.org/10.1007/978-3-540-74143-5_30",
        "Carvalho, M., Sá, F., & Bernardino, J. (2025). Evaluation of the impact of AES encryption on query read performance across Oracle, MySQL, and SQL Server databases. *Cryptography*, 9(4), Article 77. https://doi.org/10.3390/cryptography9040077",
        "Chen, H., Yang, Y., & Lv, S. (2024). Revisiting frequency-smoothing encryption: new security definitions and efficient construction. *Cybersecurity*, 7(1). https://doi.org/10.1186/s42400-024-00208-w",
        "Dou, H., Dan, Z., Xu, P., Wang, W., Xu, S., Chen, T., & Jin, H. (2024). Dynamic searchable symmetric encryption with strong security and robustness. *IEEE Transactions on Information Forensics and Security*, 19, 2370–2384. https://doi.org/10.1109/TIFS.2024.3350330",
        "Gui, Z., Paterson, K. G., Patranabis, S., & Warinschi, B. (2024). SWiSSSE: System-wide security for searchable symmetric encryption. *Proceedings on Privacy Enhancing Technologies*, 2024(1), 549–581. https://doi.org/10.56553/popets-2024-0032",
        "Khan, A. N., Naveed, A., Mehmood, A., Arora, D., Khan, A. U. R., & Ali, J. (2025). BloomSec: Scalable and privacy-preserving searchable encryption for cloud environments. *PLoS ONE*, 20(12), Article e0336944. https://doi.org/10.1371/journal.pone.0336944",
        "Noor, A., Izzah, E., & Sugandha, W. (2021). Penggunaan tanda tangan elektronik dalam penyelenggaraan e-government guna mewujudkan pelayanan publik yang efisien. *Journal of Law, Society, and Islamic Civilization*, 9(1), 1–9. https://doi.org/10.20961/jolsic.v9i1.52836",
        "Pina, E., Ramos, J., Jorge, H., Váz, P., & Silva, J. (2024). Data privacy and ethical considerations in database management. *Journal of Cybersecurity and Privacy*, 4(3), 494–517. https://doi.org/10.3390/jcp4030024 [TODO-PENULIS: lengkapi 3 nama penulis terakhir dari DOI]",
        "Republik Indonesia. (2018). *Peraturan Presiden Republik Indonesia Nomor 95 Tahun 2018 tentang Sistem Pemerintahan Berbasis Elektronik*. Lembaran Negara Republik Indonesia Tahun 2018 Nomor 182.",
        "Republik Indonesia. (2022). *Undang-Undang Republik Indonesia Nomor 27 Tahun 2022 tentang Pelindungan Data Pribadi*. Lembaran Negara Republik Indonesia Tahun 2022 Nomor 196.",
        "Rogaway, P., & Shrimpton, T. (2006). A deterministic approach to authenticated encryption. In B. Roy (Ed.), *Advances in Cryptology – ASIACRYPT 2006* (LNCS Vol. 4284, pp. 514–529). Springer. https://doi.org/10.1007/11935230_34",
        "Trabucco, M., Andreolini, M., & Ferretti, L. (2026). Practical and secure history-independent indexing for queryable-encrypted databases. *Journal of Information Security and Applications*, 99, Article 104439. https://doi.org/10.1016/j.jisa.2026.104439",
    ]

    for ref in references:
        add_reference(doc, ref)

    print(f"Saving final document to {OUTPUT_PATH}...")
    doc.save(OUTPUT_PATH)
    print("Document successfully created and saved!")

if __name__ == "__main__":
    build_paper()
