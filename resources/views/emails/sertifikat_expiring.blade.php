<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pemberitahuan Kedaluwarsa Sertifikat TTE</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f4; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #161616;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f4f4f4; padding: 30px 15px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" style="max-width: 600px; background-color: #ffffff; border: 1px solid #e0e0e0; border-top: 4px solid #0f62fe;">
                    <!-- Header Instansi -->
                    <tr>
                        <td style="padding: 24px 30px; border-bottom: 1px solid #f0f0f0;">
                            <table role="presentation" width="100%">
                                <tr>
                                    <td>
                                        <div style="font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.05em; color: #525252;">Pemerintah Daerah Kota Cirebon</div>
                                        <div style="font-size: 16px; font-weight: 800; color: #161616; margin-top: 2px;">Dinas Komunikasi dan Informatika</div>
                                        <div style="font-size: 11px; color: #6f6f6f; margin-top: 2px;">Bidang Persandian dan Keamanan Informasi</div>
                                    </td>
                                    <td align="right" style="vertical-align: top;">
                                        <span style="display: inline-block; padding: 4px 8px; background: #fff1f1; border: 1px solid #da1e28; color: #da1e28; font-size: 10px; font-weight: bold; letter-spacing: 0.05em; text-transform: uppercase;">
                                            PERINGATAN H-{{ $sisaHari }}
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Isi Surat -->
                    <tr>
                        <td style="padding: 30px;">
                            <p style="margin: 0 0 16px 0; font-size: 14px; line-height: 1.5;">
                                Yth. <strong>{{ $namaPemohon }}</strong>,
                            </p>
                            <p style="margin: 0 0 20px 0; font-size: 13px; line-height: 1.6; color: #393939;">
                                Berdasarkan pencatatan pada <strong>Sistem Informasi Monitoring Tanda Tangan Elektronik (SISMON TTE)</strong> Kota Cirebon, kami memberitahukan bahwa Sertifikat Elektronik Balai Sertifikasi Elektronik (BSrE BSSN) yang terdaftar atas nama Saudara akan segera berakhir masa berlakunya.
                            </p>

                            <!-- Box Detail Sertifikat -->
                            <table role="presentation" width="100%" style="background-color: #f8f9fa; border: 1px solid #e0e0e0; margin-bottom: 24px; font-size: 12px; border-collapse: collapse;">
                                <tr>
                                    <td style="padding: 10px 14px; border-bottom: 1px solid #e9ecef; width: 35%; color: #525252; font-weight: 600;">Nama Pemilik</td>
                                    <td style="padding: 10px 14px; border-bottom: 1px solid #e9ecef; font-weight: bold; color: #161616;">{{ $namaPemohon }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 14px; border-bottom: 1px solid #e9ecef; color: #525252; font-weight: 600;">NIP / NIK</td>
                                    <td style="padding: 10px 14px; border-bottom: 1px solid #e9ecef; font-family: monospace; color: #161616;">{{ $maskedNip !== '-' ? $maskedNip : $maskedNik }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 14px; border-bottom: 1px solid #e9ecef; color: #525252; font-weight: 600;">Unit Kerja (SKPD)</td>
                                    <td style="padding: 10px 14px; border-bottom: 1px solid #e9ecef; color: #161616;">{{ $namaSkpd }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 14px; border-bottom: 1px solid #e9ecef; color: #525252; font-weight: 600;">Email Dinas</td>
                                    <td style="padding: 10px 14px; border-bottom: 1px solid #e9ecef; color: #0f62fe;">{{ $sertifikat->email_dinas }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 14px; border-bottom: 1px solid #e9ecef; color: #525252; font-weight: 600;">Tanggal Kedaluwarsa</td>
                                    <td style="padding: 10px 14px; border-bottom: 1px solid #e9ecef; color: #da1e28; font-weight: bold;">{{ $tanggalKadaluarsa }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 14px; color: #525252; font-weight: 600;">Sisa Masa Berlaku</td>
                                    <td style="padding: 10px 14px; color: #da1e28; font-weight: bold;">{{ $sisaHari }} Hari Lagi</td>
                                </tr>
                            </table>

                            <!-- Petunjuk Tindakan Perpanjangan -->
                            <div style="background-color: #edf5ff; border-left: 3px solid #0f62fe; padding: 14px 16px; margin-bottom: 24px;">
                                <div style="font-size: 12px; font-weight: bold; color: #0043ce; margin-bottom: 6px;">
                                    Langkah Rekomendasi Perpanjangan Sertifikat:
                                </div>
                                <ol style="margin: 0; padding-left: 18px; font-size: 12px; line-height: 1.5; color: #393939;">
                                    <li>Segera ajukan permohonan pembaruan/perpanjangan sertifikat TTE sebelum tanggal jatuh tempo kedaluwarsa.</li>
                                    <li>Pengajuan dapat dikoordinasikan melalui Subbag Kepegawaian SKPD masing-masing atau langsung menghubungi Helpdesk Layanan TTE DKIS.</li>
                                    <li>Apabila sertifikat telah melewati tanggal kedaluwarsa, seluruh penandatanganan dokumen elektronik akan terhenti otomatis.</li>
                                </ol>
                            </div>

                            <p style="margin: 0 0 4px 0; font-size: 12px; color: #6f6f6f;">
                                Untuk informasi dan konsultasi teknis lebih lanjut:
                            </p>
                            <p style="margin: 0; font-size: 12px; line-height: 1.5; color: #393939;">
                                <strong>Layanan Helpdesk TTE Diskominfo Kota Cirebon</strong><br>
                                Gedung Diskominfo Jl. Sudarsono No. 40, Kesambi, Kota Cirebon<br>
                                WhatsApp Bantuan: <strong>0811-2000-000</strong> &bull; Pos-el: <strong>tte@cirebonkota.go.id</strong>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer Email -->
                    <tr>
                        <td style="padding: 18px 30px; background-color: #f4f4f4; border-top: 1px solid #e0e0e0; font-size: 11px; color: #6f6f6f; text-align: center;">
                            Surat elektronik ini dikirimkan secara otomatis oleh Sistem Monitoring Tanda Tangan Elektronik (SISMON TTE) Pemerintah Daerah Kota Cirebon.<br>
                            &copy; {{ date('Y') }} Dinas Komunikasi dan Informatika Kota Cirebon. Seluruh hak cipta dilindungi undang-undang.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
