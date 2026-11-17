"""
generate_figure1.py
Generate publication-quality Figure 1 at 300 DPI for JIP template:
Diagram alur pemisahan jalur kueri dan pemrosesan kriptografi DAE v1 pada PostgreSQL.
"""

import matplotlib.pyplot as plt
import matplotlib.patches as patches

def generate_figure():
    fig, ax = plt.subplots(figsize=(7.5, 9.2), dpi=300)
    ax.set_xlim(0, 100)
    ax.set_ylim(0, 100)
    ax.axis('off')

    # Palette
    bg_box_app = '#F0F4F8'
    bg_box_crypto = '#EBF5FB'
    bg_box_db = '#EAF2F8'
    bg_box_verify = '#FEF9E7'
    border_app = '#2980B9'
    border_crypto = '#1F618D'
    border_db = '#1B4F72'
    border_verify = '#B7950B'
    box_white = '#FFFFFF'
    text_dark = '#1A252F'

    # Font properties
    title_font = {'fontsize': 8.5, 'fontweight': 'bold', 'color': text_dark, 'family': 'sans-serif'}
    subtitle_font = {'fontsize': 7.5, 'fontweight': 'bold', 'color': '#2C3E50', 'family': 'sans-serif'}
    body_font = {'fontsize': 7, 'color': text_dark, 'family': 'sans-serif'}
    code_font = {'fontsize': 6.5, 'family': 'monospace', 'color': '#117864', 'fontweight': 'bold'}
    small_font = {'fontsize': 6.2, 'color': '#566573', 'family': 'sans-serif'}

    # 1. Block: Input Pengguna
    box_input = patches.FancyBboxPatch((20, 93), 60, 5, boxstyle="round,pad=0.5",
                                      fc='#EAEDED', ec='#7F8C8D', lw=1.2)
    ax.add_patch(box_input)
    ax.text(50, 95.5, "Input Bilah Pencarian Tunggal (Single Search Input)", 
            ha='center', va='center', **title_font)
    ax.text(50, 93.8, "Menerima String NIK (16 digit), NIP (18 digit), atau Nama Lengkap", 
            ha='center', va='center', **small_font)

    # Arrow 1 down
    ax.annotate('', xy=(50, 89.5), xytext=(50, 92.5),
                arrowprops=dict(facecolor='#2C3E50', edgecolor='#2C3E50', width=1, headwidth=4, headlength=5))

    # 2. Block: Lapisan Aplikasi & Deteksi Pola Input
    box_app = patches.FancyBboxPatch((8, 73), 84, 16.5, boxstyle="round,pad=0.6",
                                    fc=bg_box_app, ec=border_app, lw=1.2)
    ax.add_patch(box_app)
    ax.text(10, 88, "1. Lapisan Layanan Aplikasi (Application Service Layer) - Deteksi Pola Regex", 
            ha='left', va='center', **subtitle_font)

    # 3 sub-branches
    # Left: NIK
    b_nik = patches.FancyBboxPatch((10, 74.5), 24, 11, boxstyle="round,pad=0.3",
                                  fc=box_white, ec='#3498DB', lw=1)
    ax.add_patch(b_nik)
    ax.text(22, 83.5, "Pola: /^[0-9]{16}$/", ha='center', va='center', **code_font)
    ax.text(22, 80.5, "Jalur Kueri Eksak NIK", ha='center', va='center', **title_font)
    ax.text(22, 77, "label = 'nik'\nKueri: WHERE nik = ?", ha='center', va='center', **small_font)

    # Center: NIP
    b_nip = patches.FancyBboxPatch((38, 74.5), 24, 11, boxstyle="round,pad=0.3",
                                  fc=box_white, ec='#3498DB', lw=1)
    ax.add_patch(b_nip)
    ax.text(50, 83.5, "Pola: /^[0-9]{18}$/", ha='center', va='center', **code_font)
    ax.text(50, 80.5, "Jalur Kueri Eksak NIP", ha='center', va='center', **title_font)
    ax.text(50, 77, "label = 'nip'\nKueri: WHERE nip = ?", ha='center', va='center', **small_font)

    # Right: Nama (Non-kripto)
    b_nama = patches.FancyBboxPatch((66, 74.5), 24, 11, boxstyle="round,pad=0.3",
                                   fc='#FCF3CF', ec='#F39C12', lw=1)
    ax.add_patch(b_nama)
    ax.text(78, 83.5, "Pola Teks Bebas", ha='center', va='center', **subtitle_font)
    ax.text(78, 80.5, "Jalur Pencarian Nama", ha='center', va='center', **title_font)
    ax.text(78, 77, "Pencarian Wildcard\nWHERE nama ILIKE ?", ha='center', va='center', **small_font)

    # Arrow from NIK/NIP to Crypto
    ax.annotate('', xy=(36, 68), xytext=(36, 73),
                arrowprops=dict(facecolor='#1F618D', edgecolor='#1F618D', width=1, headwidth=4, headlength=5))
    ax.text(38, 70.5, "Parameter Identitas P", ha='left', va='center', **code_font)

    # Arrow from Nama straight to DB
    ax.plot([78, 78, 85, 85], [74.5, 71, 71, 41.5], color='#D68910', lw=1.2, ls='--')
    ax.annotate('', xy=(85, 41), xytext=(85, 41.5),
                arrowprops=dict(facecolor='#D68910', edgecolor='#D68910', width=1, headwidth=4, headlength=5))
    ax.text(86, 56, "Teks Pola\nBypass DAE", ha='left', va='center', **small_font)

    # 3. Block: Modul Kriptografi DAE v1
    box_crypto = patches.FancyBboxPatch((8, 47), 74, 21, boxstyle="round,pad=0.6",
                                       fc=bg_box_crypto, ec=border_crypto, lw=1.2)
    ax.add_patch(box_crypto)
    ax.text(10, 66.5, "2. Modul Kriptografi DAE v1 (Paradigma SIV-like CTR Mode)", 
            ha='left', va='center', **subtitle_font)

    # Step 1: HKDF
    b_hkdf = patches.FancyBboxPatch((10, 58), 34, 7, boxstyle="round,pad=0.3",
                                   fc=box_white, ec='#2980B9', lw=1)
    ax.add_patch(b_hkdf)
    ax.text(12, 63.2, "K_master (256-bit)", **code_font)
    ax.text(12, 60.8, "HKDF-SHA256 (salt=∅, info) ->", **small_font)
    ax.text(12, 58.8, "Sub-kunci K_enc & K_mac (Cached)", **code_font)

    # Step 2: Context & Synthetic IV
    b_siv = patches.FancyBboxPatch((46, 58), 34, 7, boxstyle="round,pad=0.3",
                                  fc=box_white, ec='#2980B9', lw=1)
    ax.add_patch(b_siv)
    ax.text(48, 63.2, "Associated Data: Context = label || 0x00", **code_font)
    ax.text(48, 60.8, "Synthetic IV (16 byte):", **small_font)
    ax.text(48, 58.8, "V = Trunc_16B(HMAC(K_mac, Ctx||P))", **code_font)

    # Step 3: Stream Cipher AES-CTR
    b_aes = patches.FancyBboxPatch((10, 48.5), 34, 7.5, boxstyle="round,pad=0.3",
                                  fc=box_white, ec='#2980B9', lw=1)
    ax.add_patch(b_aes)
    ax.text(12, 54.2, "Stream Cipher (Tanpa Padding PKCS#7):", **small_font)
    ax.text(12, 52.0, "C_stream = AES-256-CTR(K_enc, IV=V, P)", **code_font)
    ax.text(12, 49.5, "|C_stream| = |P| (NIK 16B, NIP 18B)", **small_font)

    # Step 4: Ciphertext Assembly
    b_raw = patches.FancyBboxPatch((46, 48.5), 34, 7.5, boxstyle="round,pad=0.3",
                                  fc=box_white, ec='#2980B9', lw=1)
    ax.add_patch(b_raw)
    ax.text(48, 54.2, "Perakitan Ciphertext & Pengkodean:", **small_font)
    ax.text(48, 52.0, "C_raw = 0x01 || V || C_stream", **code_font)
    ax.text(48, 49.5, "Ciphertext = base64_encode(C_raw)", **code_font)

    # Internal arrows in Crypto
    ax.annotate('', xy=(45, 61.5), xytext=(44, 61.5),
                arrowprops=dict(facecolor='#1F618D', edgecolor='#1F618D', width=0.8, headwidth=3, headlength=4))
    ax.annotate('', xy=(27, 56), xytext=(27, 58),
                arrowprops=dict(facecolor='#1F618D', edgecolor='#1F618D', width=0.8, headwidth=3, headlength=4))
    ax.annotate('', xy=(63, 56), xytext=(63, 58),
                arrowprops=dict(facecolor='#1F618D', edgecolor='#1F618D', width=0.8, headwidth=3, headlength=4))

    # Arrow from Crypto to DB
    ax.annotate('', xy=(45, 42.5), xytext=(45, 47),
                arrowprops=dict(facecolor='#1B4F72', edgecolor='#1B4F72', width=1.2, headwidth=5, headlength=6))
    ax.text(46, 44.8, "Kueri SQL: WHERE nik = 'base64(...)'", ha='left', va='center', **code_font)

    # 4. Block: Lapisan Basis Data PostgreSQL
    box_db = patches.FancyBboxPatch((8, 25), 84, 17.5, boxstyle="round,pad=0.6",
                                   fc=bg_box_db, ec=border_db, lw=1.2)
    ax.add_patch(box_db)
    ax.text(10, 41, "3. Lapisan Basis Data (PostgreSQL 16 Engine) - Eksekusi Kueri dan Indeks", 
            ha='left', va='center', **subtitle_font)

    # Left DB: Partial Unique Index
    b_idx = patches.FancyBboxPatch((10, 26.5), 44, 13, boxstyle="round,pad=0.3",
                                  fc=box_white, ec='#1B4F72', lw=1)
    ax.add_patch(b_idx)
    ax.text(12, 37.5, "Indeks Parsial Unik B-Tree PostgreSQL:", ha='left', va='center', **title_font)
    ax.text(12, 35.0, "CREATE UNIQUE INDEX idx_nik_active", ha='left', va='center', **code_font)
    ax.text(12, 33.0, "ON pendaftaran_tte (nik)", ha='left', va='center', **code_font)
    ax.text(12, 31.0, "WHERE status <> 'DITOLAK';", ha='left', va='center', **code_font)
    ax.text(12, 28.5, "• Menjamin integritas pencegahan duplikasi aktif\n• Mengizinkan pendaftaran ulang pasca penolakan", 
            ha='left', va='center', **small_font)

    # Right DB: Metrics
    b_perf = patches.FancyBboxPatch((56, 26.5), 34, 13, boxstyle="round,pad=0.3",
                                   fc=box_white, ec='#27AE60', lw=1)
    ax.add_patch(b_perf)
    ax.text(73, 37.5, "Hasil Audit Buffer & Latensi:", ha='center', va='center', **title_font)
    ax.text(58, 34.5, "• Index Scan: O(log N)", ha='left', va='center', **body_font)
    ax.text(58, 32.0, "• Shared Hit: Tepat 4 Blok Buffer", ha='left', va='center', **body_font)
    ax.text(58, 29.5, "• Shared Read: 0 Blok (Zero Disk Thrash)", ha='left', va='center', **body_font)
    ax.text(58, 27.2, "• Latensi Engine: 0,075 ms (N = 100k)", ha='left', va='center', **code_font)

    # Arrow from DB to Verification
    ax.annotate('', xy=(50, 20.5), xytext=(50, 25),
                arrowprops=dict(facecolor='#B7950B', edgecolor='#B7950B', width=1.2, headwidth=5, headlength=6))
    ax.text(51, 22.8, "Hasil Baris Ciphertext Terambil", ha='left', va='center', **code_font)

    # 5. Block: Verifikasi Autentikasi & Dekripsi
    box_verify = patches.FancyBboxPatch((8, 2), 84, 18.5, boxstyle="round,pad=0.6",
                                       fc=bg_box_verify, ec=border_verify, lw=1.2)
    ax.add_patch(box_verify)
    ax.text(10, 19, "4. Pipeline Pembacaan: Dekripsi & Verifikasi Integritas Autentikasi", 
            ha='left', va='center', **subtitle_font)

    # Steps of verification
    b_dec = patches.FancyBboxPatch((10, 3.5), 24, 14, boxstyle="round,pad=0.3",
                                  fc=box_white, ec='#B7950B', lw=1)
    ax.add_patch(b_dec)
    ax.text(22, 15.5, "1. Ekstraksi & Dekripsi", ha='center', va='center', **title_font)
    ax.text(12, 13.0, "• Baca C_raw (33/35B)", ha='left', va='center', **small_font)
    ax.text(12, 11.0, "• Ekstrak V (16B) & C_stream", ha='left', va='center', **small_font)
    ax.text(12, 8.8, "• Dekripsi Kandidat P':", ha='left', va='center', **small_font)
    ax.text(12, 6.5, "  AES-CTR(K_enc, IV=V)", ha='left', va='center', **code_font)

    b_check = patches.FancyBboxPatch((36, 3.5), 26, 14, boxstyle="round,pad=0.3",
                                    fc=box_white, ec='#B7950B', lw=1)
    ax.add_patch(b_check)
    ax.text(49, 15.5, "2. Hitung Ulang Tag", ha='center', va='center', **title_font)
    ax.text(38, 13.0, "• Evaluasi V' kandidat:", ha='left', va='center', **small_font)
    ax.text(38, 11.0, "  V' = Trunc_16B(", ha='left', va='center', **code_font)
    ax.text(38, 9.2, "    HMAC(K_mac, Ctx||P'))", ha='left', va='center', **code_font)
    ax.text(38, 6.8, "• Uji hash_equals(V, V')", ha='left', va='center', **code_font)

    b_result = patches.FancyBboxPatch((64, 3.5), 26, 14, boxstyle="round,pad=0.3",
                                     fc=box_white, ec='#B7950B', lw=1)
    ax.add_patch(b_result)
    ax.text(77, 15.5, "3. Validasi Autentikasi", ha='center', va='center', **title_font)
    ax.text(66, 13.0, "• Jika Cocok (Match):", ha='left', va='center', **small_font)
    ax.text(66, 11.0, "  -> Return Plaintext P'", ha='left', va='center', **code_font)
    ax.text(66, 8.5, "• Jika Modifikasi / Tamper:", ha='left', va='center', **small_font)
    ax.text(66, 6.5, "  -> Tolak Kueri (Null)", ha='left', va='center', **code_font)
    ax.text(66, 4.5, "  (Mencegah Bit-Flipping)", ha='left', va='center', **small_font)

    # Arrows in verification
    ax.annotate('', xy=(35, 10.5), xytext=(34, 10.5),
                arrowprops=dict(facecolor='#B7950B', edgecolor='#B7950B', width=0.8, headwidth=3, headlength=4))
    ax.annotate('', xy=(63, 10.5), xytext=(62, 10.5),
                arrowprops=dict(facecolor='#B7950B', edgecolor='#B7950B', width=0.8, headwidth=3, headlength=4))

    out_path = r"DOKUMEN LAPORAN & ARTIKEL/assets/gambar1_alur_penelitian_dae.png"
    plt.tight_layout()
    plt.savefig(out_path, dpi=300, bbox_inches='tight')
    plt.close()
    print(f"Figure 1 successfully generated at {out_path} with 300 DPI.")

if __name__ == "__main__":
    generate_figure()
