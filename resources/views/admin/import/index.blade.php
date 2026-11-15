@extends('layouts.admin')

@section('title', 'Impor Data Historis Pegawai & Sertifikat')
@section('breadcrumb', 'Impor Data Historis')

@section('content')
<div class="panel-header">
    <div>
        <h1 class="panel-title">Impor Data Historis Pegawai & Sertifikat TTE</h1>
        <p style="font-size: 0.8125rem; color: var(--ink-muted); margin-top: 2px;">
            Fasilitas batch import untuk memuat data pendaftaran dan sertifikat TTE eksisting Kota Cirebon secara masal.
        </p>
    </div>

    <div style="display: flex; gap: 0.5rem;">
        <a href="{{ route('admin.import.template') }}" class="btn btn-outline btn-sm" id="btn-download-template">
            📥 Unduh Template CSV Standar
        </a>
    </div>
</div>

<!-- Alert Notifikasi Hasil Impor -->
@if(session('import_errors') && count(session('import_errors')) > 0)
    <div class="alert-flat alert-danger" style="margin-bottom: 1.25rem;">
        <strong>Daftar Galat Format CSV (Maks 15 Ditampilkan):</strong>
        <ul style="margin: 0.5rem 0 0 1.25rem; padding: 0; font-size: 0.75rem;">
            @foreach(session('import_errors') as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if(session('import_skipped') && count(session('import_skipped')) > 0)
    <div class="alert-flat alert-warning" style="margin-bottom: 1.25rem;">
        <strong>Data Dilewati Karena Telah Terdaftar (Maks 15 Ditampilkan):</strong>
        <ul style="margin: 0.5rem 0 0 1.25rem; padding: 0; font-size: 0.75rem;">
            @foreach(session('import_skipped') as $skip)
                <li>{{ $skip }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div style="display: grid; grid-template-columns: 1fr 340px; gap: 1.5rem; align-items: start; margin-bottom: 1.5rem;">
    <!-- Form Unggah Berkas CSV -->
    <div class="card-flat">
        <div class="card-header">
            <h3>Unggah Berkas CSV Data Historis</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.import.store') }}" method="POST" enctype="multipart/form-data" id="form-import-historical">
                @csrf

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label class="form-label">Pilih Berkas CSV / TXT <span style="color: var(--danger);">*</span></label>
                    <input type="file" name="file_csv" id="file_csv" class="form-control" accept=".csv,text/csv,text/plain" required>
                    <div style="font-size: 0.6875rem; color: var(--ink-muted); margin-top: 4px;">
                        Maksimal ukuran file 10MB. Delimiter koma (<code>,</code>) atau titik koma (<code>;</code>) dideteksi otomatis.
                    </div>
                </div>

                <div style="margin-bottom: 1.5rem; padding: 0.75rem 1rem; background: var(--bg-surface); border: 1px solid var(--border);">
                    <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8125rem; font-weight: 500; cursor: pointer;">
                        <input type="checkbox" name="dry_run" value="1" id="dry_run" style="width: 16px; height: 16px;">
                        <span>Uji Coba Validasi Saja (<em>Dry Run</em> &ndash; Tanpa Menyimpan ke Basis Data)</span>
                    </label>
                    <div style="font-size: 0.6875rem; color: var(--ink-muted); margin-left: 24px; margin-top: 2px;">
                        Sistem hanya akan memvalidasi integritas baris dan mendeteksi duplikasi tanpa melakukan perubahan data.
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="submit" class="btn btn-primary" id="btn-submit-import">
                        🚀 Mulai Proses Impor CSV
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Panduan Format Kolom CSV -->
    <div class="card-flat">
        <div class="card-header">
            <h3>Spesifikasi Kolom CSV</h3>
        </div>
        <div class="card-body" style="font-size: 0.75rem; line-height: 1.5;">
            <p style="margin-top: 0; color: var(--ink-secondary);">
                Pastikan berkas CSV menggunakan baris header berikut:
            </p>
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 1rem; font-size: 0.6875rem;">
                <tr style="border-bottom: 1px solid var(--border);">
                    <td style="padding: 4px 0; font-weight: bold; font-family: monospace;">nama_lengkap</td>
                    <td style="padding: 4px 0; color: var(--danger);">Wajib</td>
                </tr>
                <tr style="border-bottom: 1px solid var(--border);">
                    <td style="padding: 4px 0; font-weight: bold; font-family: monospace;">nik</td>
                    <td style="padding: 4px 0; color: var(--danger);">Wajib (16 Digit)</td>
                </tr>
                <tr style="border-bottom: 1px solid var(--border);">
                    <td style="padding: 4px 0; font-weight: bold; font-family: monospace;">nip</td>
                    <td style="padding: 4px 0; color: var(--ink-muted);">Opsional Non-ASN</td>
                </tr>
                <tr style="border-bottom: 1px solid var(--border);">
                    <td style="padding: 4px 0; font-weight: bold; font-family: monospace;">tipe_pegawai</td>
                    <td style="padding: 4px 0; color: var(--ink-muted);">ASN / NON_ASN</td>
                </tr>
                <tr style="border-bottom: 1px solid var(--border);">
                    <td style="padding: 4px 0; font-weight: bold; font-family: monospace;">skpd</td>
                    <td style="padding: 4px 0; color: var(--ink-muted);">Nama / Kode SKPD</td>
                </tr>
                <tr style="border-bottom: 1px solid var(--border);">
                    <td style="padding: 4px 0; font-weight: bold; font-family: monospace;">email_dinas</td>
                    <td style="padding: 4px 0; color: var(--ink-muted);">Email Pemohon</td>
                </tr>
                <tr style="border-bottom: 1px solid var(--border);">
                    <td style="padding: 4px 0; font-weight: bold; font-family: monospace;">tanggal_penerbitan</td>
                    <td style="padding: 4px 0; color: var(--ink-muted);">YYYY-MM-DD</td>
                </tr>
                <tr>
                    <td style="padding: 4px 0; font-weight: bold; font-family: monospace;">status_sertifikat</td>
                    <td style="padding: 4px 0; color: var(--ink-muted);">AKTIF / EXPIRED</td>
                </tr>
            </table>

            <div style="background: #edf5ff; border-left: 3px solid #0f62fe; padding: 8px 10px; font-size: 0.6875rem;">
                <strong>Otomasi Sertifikat:</strong> Jika kolom <code>tanggal_penerbitan</code> diisi, sistem otomatis menyetujui pendaftaran dan menerbitkan data sertifikat aktif (masa berlaku 2 tahun).
            </div>
        </div>
    </div>
</div>

<!-- Riwayat Eksekusi Impor Batch -->
<div class="card-flat">
    <div class="card-header">
        <h3>Riwayat Eksekusi Impor Terakhir</h3>
    </div>
    <div class="table-responsive">
        <table class="table-flat">
            <thead>
                <tr>
                    <th style="width: 140px;">Waktu Eksekusi</th>
                    <th>Operator</th>
                    <th>Nama Berkas</th>
                    <th style="text-align: right; width: 90px;">Total Baris</th>
                    <th style="text-align: right; width: 90px;">Berhasil</th>
                    <th style="text-align: right; width: 90px;">Dilewati</th>
                    <th style="text-align: right; width: 80px;">Galat</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentImports as $log)
                    <tr>
                        <td class="font-mono" style="font-size: 0.75rem;">
                            {{ $log->created_at ? $log->created_at->format('d/m/Y H:i') : '-' }}
                        </td>
                        <td><strong>{{ $log->user?->name ?? 'Superadmin' }}</strong></td>
                        <td class="font-mono" style="font-size: 0.75rem;">
                            {{ $log->new_state['file_name'] ?? '-' }}
                        </td>
                        <td style="text-align: right;" class="font-mono">
                            {{ number_format($log->new_state['total_rows'] ?? 0) }}
                        </td>
                        <td style="text-align: right; color: var(--success);" class="font-mono">
                            <strong>{{ number_format($log->new_state['imported_count'] ?? 0) }}</strong>
                        </td>
                        <td style="text-align: right; color: var(--warning);" class="font-mono">
                            {{ number_format($log->new_state['skipped_count'] ?? 0) }}
                        </td>
                        <td style="text-align: right; color: var(--danger);" class="font-mono">
                            {{ number_format($log->new_state['error_count'] ?? 0) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--ink-muted); padding: 2rem;">
                            Belum ada riwayat impor data historis yang tercatat.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
