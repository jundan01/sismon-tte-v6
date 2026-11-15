# Script sinkronisasi file docx
$source = "f:\sismon-tte-testing-V5\DOKUMEN LAPORAN & ARTIKEL\ARTIKEL_ILMIAH_SISMON_TTE_REVISI_REAL.docx"
$target = "f:\sismon-tte-testing-V5\DOKUMEN LAPORAN & ARTIKEL\ARTIKEL_ILMIAH_SISMON_TTE.docx"

if (Test-Path $source) {
    try {
        Copy-Item -Path $source -Destination $target -Force
        Write-Host "BERHASIL: $target telah diperbarui dengan versi revisi real!" -ForegroundColor Green
    } catch {
        Write-Warning "GAGAL: $target masih terkunci oleh Microsoft Word. Silakan tutup jendela Word terlebih dahulu, lalu jalankan kembali skrip ini."
    }
}
