<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Rekapitulasi Monitoring & Validasi TTE - DKIS Kota Cirebon</title>
    <style>
        @page {
            margin: 1.5cm 1.2cm 1.5cm 1.2cm;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 8pt;
            color: #1a1a1a;
            line-height: 1.35;
        }

        /* ── KOP SURAT RESMI PEMERINTAH KOTA CIREBON ── */
        .kop-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }

        .kop-logo {
            width: 65px;
            vertical-align: middle;
            text-align: center;
        }

        .kop-text {
            text-align: center;
            vertical-align: middle;
        }

        .kop-instansi-1 {
            font-size: 11pt;
            font-weight: bold;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin: 0;
            color: #111;
        }

        .kop-instansi-2 {
            font-size: 14pt;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin: 2px 0 0 0;
            color: #000;
        }

        .kop-alamat {
            font-size: 7.5pt;
            color: #444;
            margin: 3px 0 0 0;
        }

        .kop-divider-thick {
            border-top: 3px solid #000;
            margin-top: 6px;
        }

        .kop-divider-thin {
            border-top: 1px solid #000;
            margin-top: 2px;
            margin-bottom: 12px;
        }

        /* ── JUDUL DOKUMEN ── */
        .doc-title {
            text-align: center;
            font-size: 10pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 4px 0 2px 0;
        }

        .doc-subtitle {
            text-align: center;
            font-size: 8pt;
            color: #444;
            margin-bottom: 12px;
        }

        /* ── METADATA & FILTER ── */
        .meta-box {
            width: 100%;
            margin-bottom: 10px;
            background: #fbfbfb;
            border: 1px solid #d0d0d0;
            padding: 6px 10px;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5pt;
        }

        .meta-table td {
            padding: 2px 4px;
            vertical-align: top;
        }

        .meta-label {
            width: 15%;
            color: #555;
            font-weight: bold;
        }

        .meta-val {
            width: 35%;
            color: #111;
        }

        /* ── RINGKASAN STATISTIK (KPI BOXES) ── */
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .summary-table td {
            border: 1px solid #ccc;
            padding: 5px;
            text-align: center;
            background: #fdfdfd;
        }

        .summary-num {
            font-size: 11pt;
            font-weight: bold;
            color: #0f62fe;
            display: block;
        }

        .summary-label {
            font-size: 6.5pt;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #555;
            margin-top: 2px;
        }

        /* ── TABEL DATA UTAMA ── */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .data-table th {
            background-color: #262626;
            color: #ffffff;
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            padding: 6px 4px;
            border: 1px solid #333333;
            text-align: left;
        }

        .data-table td {
            border: 1px solid #d0d0d0;
            padding: 4px 4px;
            font-size: 7pt;
            vertical-align: middle;
        }

        .data-table tr:nth-child(even) {
            background-color: #f7f7f7;
        }

        .badge-status {
            display: inline-block;
            padding: 2px 4px;
            font-size: 6.5pt;
            font-weight: bold;
            border-radius: 2px;
            text-align: center;
        }

        .badge-asn { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
        .badge-non-asn { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
        .badge-aktif { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .badge-menunggu { background: #fef9c3; color: #a16207; border: 1px solid #fef08a; }
        .badge-ditolak { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

        /* ── TANDA TANGAN / PENGESAHAN ── */
        .ttd-container {
            width: 100%;
            margin-top: 15px;
            page-break-inside: avoid;
        }

        .ttd-table {
            width: 100%;
            border-collapse: collapse;
        }

        .ttd-box {
            width: 40%;
            text-align: center;
            vertical-align: top;
            font-size: 7.5pt;
        }

        .ttd-space {
            height: 48px;
        }

        .ttd-nama {
            font-weight: bold;
            text-decoration: underline;
            font-size: 8pt;
        }

        /* ── FOOTER HALAMAN ── */
        .footer-note {
            font-size: 6.5pt;
            color: #777;
            border-top: 1px solid #e0e0e0;
            padding-top: 4px;
            margin-top: 10px;
        }
    </style>
</head>
<body>

    <!-- ── KOP SURAT RESMI DINAS ── -->
    <table class="kop-table">
        <tr>
            <td class="kop-logo">
                <svg width="48" height="54" viewBox="0 0 100 115" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <polygon points="50,5 92,28 92,85 50,110 8,85 8,28" stroke="#111" stroke-width="4" fill="#f4f4f4"/>
                    <polygon points="50,15 84,34 84,80 50,100 16,80 16,34" fill="#0f62fe"/>
                    <circle cx="50" cy="55" r="16" fill="#ffffff"/>
                    <text x="50" y="60" font-size="14" font-family="sans-serif" font-weight="bold" text-anchor="middle" fill="#0f62fe">TTE</text>
                </svg>
            </td>
            <td class="kop-text">
                <div class="kop-instansi-1">Pemerintah Daerah Kota Cirebon</div>
                <div class="kop-instansi-2">Dinas Komunikasi dan Informatika</div>
                <div class="kop-alamat">
                    Jl. Sudarsono No. 40 Telp. (0231) 208750 Faksimile (0231) 208750 Cirebon 45134<br>
                    Laman: <u>https://cirebonkota.go.id</u> &bull; Pos-el: <u>diskominfo@cirebonkota.go.id</u>
                </div>
            </td>
        </tr>
    </table>

    <div class="kop-divider-thick"></div>
    <div class="kop-divider-thin"></div>

    <!-- ── JUDUL LAPORAN ── -->
    <div class="doc-title">
        Laporan Rekapitulasi Monitoring & Validasi Tanda Tangan Elektronik (TTE)
    </div>
    <div class="doc-subtitle">
        Sistem Informasi Monitoring TTE (SISMON TTE) &bull; Kota Cirebon
    </div>

    <!-- ── PARAMETER & METADATA DOKUMEN ── -->
    <div class="meta-box">
        <table class="meta-table">
            <tr>
                <td class="meta-label">Unit Kerja (SKPD):</td>
                <td class="meta-val"><strong>{{ $selectedSkpd ? $selectedSkpd->nama_skpd : 'SELURUH SATUAN KERJA PERANGKAT DAERAH' }}</strong></td>
                <td class="meta-label">Waktu Cetak:</td>
                <td class="meta-val">{{ $printedAt }}</td>
            </tr>
            <tr>
                <td class="meta-label">Periode Waktu:</td>
                <td class="meta-val">
                    @if($startDate && $endDate)
                        {{ $startDate }} s/d {{ $endDate }}
                    @elseif($startDate)
                        Mulai {{ $startDate }}
                    @elseif($endDate)
                        Sampai {{ $endDate }}
                    @else
                        Semua Periode Transaksi
                    @endif
                </td>
                <td class="meta-label">Dicetak Oleh:</td>
                <td class="meta-val">{{ $printedBy }}</td>
            </tr>
            <tr>
                <td class="meta-label">Filter Status:</td>
                <td class="meta-val">
                    Pendaftaran: <strong>{{ $filterStatus ?: 'Semua' }}</strong> &bull;
                    Sertifikat: <strong>{{ $filterSertifikat ?: 'Semua' }}</strong> &bull;
                    Jalur: <strong>{{ $filterTipe ?: 'ASN & Non-ASN' }}</strong>
                </td>
                <td class="meta-label">Klasifikasi:</td>
                <td class="meta-val">DOKUMEN DINAS RESMI (TERBATAS)</td>
            </tr>
        </table>
    </div>

    <!-- ── RINGKASAN STATISTIK DOKUMEN CETAK ── -->
    <table class="summary-table">
        <tr>
            <td>
                <span class="summary-num">{{ number_format($summary['total']) }}</span>
                <span class="summary-label">Total Permohonan</span>
            </td>
            <td>
                <span class="summary-num">{{ number_format($summary['asn']) }}</span>
                <span class="summary-label">Pegawai ASN</span>
            </td>
            <td>
                <span class="summary-num">{{ number_format($summary['non_asn']) }}</span>
                <span class="summary-label">Non-ASN (THL)</span>
            </td>
            <td>
                <span class="summary-num" style="color: #15803d;">{{ number_format($summary['aktif']) }}</span>
                <span class="summary-label">Sertifikat Aktif</span>
            </td>
            <td>
                <span class="summary-num" style="color: #a16207;">{{ number_format($summary['menunggu']) }}</span>
                <span class="summary-label">Menunggu Audit</span>
            </td>
            <td>
                <span class="summary-num" style="color: #b91c1c;">{{ number_format($summary['ditolak']) }}</span>
                <span class="summary-label">Ditolak</span>
            </td>
        </tr>
    </table>

    @if($isCapped ?? false)
        <div style="background: #fff8e1; border: 1px solid #ffe082; color: #856404; padding: 6px 10px; margin-bottom: 10px; font-size: 7pt; line-height: 1.3;">
            <strong>Catatan Batasan Memori Cetak:</strong> Laporan format PDF ini dibatasi maksimal 500 data terbaru demi menjaga alokasi stabilitas RAM render. Untuk mendapatkan seluruh data historis tanpa batasan, silakan gunakan menu <strong>Ekspor CSV (Streaming)</strong> pada dashboard admin.
        </div>
    @endif

    <!-- ── TABEL REKAPITULASI DATA ── -->
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 20px; text-align: center;">No</th>
                <th style="width: 90px;">No. Referensi</th>
                <th style="width: 130px;">Nama Lengkap</th>
                <th style="width: 45px; text-align: center;">Tipe</th>
                <th style="width: 100px;">NIP / NIK (UU PDP)</th>
                <th>Unit Kerja (SKPD)</th>
                <th style="width: 120px;">Email Dinas</th>
                <th style="width: 75px; text-align: center;">Validasi</th>
                <th style="width: 65px; text-align: center;">Sertifikat</th>
                <th style="width: 65px; text-align: center;">Kadaluarsa</th>
            </tr>
        </thead>
        <tbody>
            @forelse($records as $index => $row)
                <tr>
                    <td style="text-align: center;">{{ $index + 1 }}</td>
                    <td><strong style="font-family: monospace;">{{ $row->nomor_referensi }}</strong></td>
                    <td>{{ $row->nama_lengkap }}</td>
                    <td style="text-align: center;">
                        @if($row->tipe_pegawai === 'NON_ASN')
                            <span class="badge-status badge-non-asn">Non-ASN</span>
                        @else
                            <span class="badge-status badge-asn">ASN</span>
                        @endif
                    </td>
                    <td>
                        <span style="font-family: monospace;">{{ $row->masked_nip !== '-' ? $row->masked_nip : $row->masked_nik }}</span>
                    </td>
                    <td>{{ $row->nama_skpd }}</td>
                    <td>{{ $row->email_dinas }}</td>
                    <td style="text-align: center;">
                        @if($row->status_pendaftaran === 'DISETUJUI')
                            <span class="badge-status badge-aktif">DISETUJUI</span>
                        @elseif($row->status_pendaftaran === 'DITOLAK')
                            <span class="badge-status badge-ditolak">DITOLAK</span>
                        @else
                            <span class="badge-status badge-menunggu">MENUNGGU</span>
                        @endif
                    </td>
                    <td style="text-align: center;">
                        @if($row->status_sertifikat === 'AKTIF')
                            <span class="badge-status badge-aktif">AKTIF</span>
                        @elseif($row->status_sertifikat === 'REVOKED')
                            <span class="badge-status badge-ditolak">DICABUT</span>
                        @elseif($row->status_sertifikat === 'EXPIRED')
                            <span class="badge-status badge-ditolak">KADALUARSA</span>
                        @else
                            <span style="color: #888;">-</span>
                        @endif
                    </td>
                    <td style="text-align: center; font-family: monospace;">{{ $row->tanggal_kadaluarsa }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" style="text-align: center; padding: 20px; color: #888;">
                        Tidak ada data pendaftaran atau sertifikat TTE yang memenuhi kriteria filter terpilih.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- ── BLOK PENGESAHAN DOKUMEN DINAS ── -->
    <div class="ttd-container">
        <table class="ttd-table">
            <tr>
                <td style="width: 60%; vertical-align: top; font-size: 7pt; color: #666;">
                    <strong>Catatan Dokumen:</strong><br>
                    1. Dokumen ini dicetak secara otomatis dari Sistem Monitoring Tanda Tangan Elektronik (SISMON TTE) Versi 6.0.<br>
                    2. Data nomor identitas kependudukan (NIK/NIP) telah disamarkan demi mematuhi Undang-Undang Perlindungan Data Pribadi (UU PDP).<br>
                    3. Keabsahan data dapat diverifikasi langsung melalui Pusat Data Diskominfo Kota Cirebon.
                </td>
                <td class="ttd-box">
                    Cirebon, {{ \Carbon\Carbon::now()->isoFormat('D MMMM Y') }}<br>
                    <strong>Kepala Dinas Komunikasi dan Informatika<br>Kota Cirebon</strong>
                    <div class="ttd-space"></div>
                    <div class="ttd-nama">Drs. H. KUSWANTO, M.Si.</div>
                    <div>Pembina Utama Muda</div>
                    <div>NIP. 19680512 199303 1 008</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- ── FOOTER DOKUMEN CETAK ── -->
    <div class="footer-note">
        Dicetak melalui Portal SISMON TTE DKIS Kota Cirebon &bull; Id Dokumen: {{ strtoupper(substr(md5($printedAt . ($selectedSkpd?->id ?? 'ALL')), 0, 16)) }}
    </div>

    <script type="text/php">
        if (isset($pdf)) {
            $text = "Halaman {PAGE_NUM} dari {PAGE_COUNT}";
            $size = 7;
            $font = $fontMetrics->getFont("Helvetica");
            $width = $fontMetrics->get_text_width($text, $font, $size);
            $x = $pdf->get_width() - $width - 34;
            $y = $pdf->get_height() - 25;
            $pdf->page_text($x, $y, $text, $font, $size, [0.4, 0.4, 0.4]);
        }
    </script>
</body>
</html>
