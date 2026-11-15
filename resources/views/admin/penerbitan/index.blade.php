@extends('layouts.admin')

@section('title', 'Validasi Tahap 2 & Manajemen Sertifikat TTE')
@section('breadcrumb', 'Validasi Sertifikat TTE')

@section('content')
<div class="panel-header">
    <div>
        <h1 class="panel-title">Validasi Sertifikat Elektronik & Manajemen Siklus Hidup (Tahap 2)</h1>
        <p style="font-size: 0.8125rem; color: var(--ink-muted); margin-top: 2px;">
            Validasi penerbitan sertifikat TTE BSrE (masa aktif berbasis parameter sistem) dan pemantauan masa kedaluwarsa berkala.
        </p>
    </div>
</div>

<!-- ── SEKSI 1: ANTRIAN VALIDASI TAHAP 2 ──────────────────────── -->
<div class="card-flat" style="border-top: 4px solid var(--primary); margin-bottom: 2rem;">
    <div class="panel-header">
        <div>
            <h2 style="font-size: 1rem; font-weight: 600;">Antrian Validasi Sertifikat TTE (Tahap 2)</h2>
            <div style="font-size: 0.75rem; color: var(--ink-muted);">
                Permohonan pegawai yang telah disetujui pada Tahap 1 dan siap divalidasi sertifikat TTE-nya.
            </div>
        </div>
        <span class="badge badge-menunggu font-mono">{{ $antrianTahap2->count() }} Permohonan Siap</span>
    </div>

    <!-- ── BAR AKSI MASAL VALIDASI ──────────────────────────────── -->
    <div id="bulkActionBarPenerbitan" style="display: none; background: #eef9fd; border: 1px solid #72c3e8; border-left: 4px solid #0072c3; padding: 0.75rem 1rem; margin-bottom: 1rem; border-radius: 4px;">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span class="badge" style="background: #0072c3; color: #fff; font-size: 0.75rem; font-weight: 700;" id="selectedCountBadgePenerbitan">0</span>
                <span style="font-size: 0.875rem; color: #002d9c; font-weight: 600;">permohonan siap divalidasi secara masal</span>
            </div>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <button type="button" class="btn btn-outline btn-sm" onclick="clearAllPenerbitanSelections()" style="background: #fff;">
                    ✕ Batal Pilih
                </button>
                <button type="button" class="btn btn-primary btn-sm" onclick="openBulkValidateModal()">
                    ⚡ Validasi Masal Terpilih (Tahap 2)
                </button>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 36px; text-align: center;">
                        <input type="checkbox" id="selectAllPenerbitan" title="Pilih Semua di Halaman Ini" onchange="toggleSelectAllPenerbitan(this)">
                    </th>
                    <th style="width: 140px;">No. Referensi</th>
                    <th>Nama Pegawai & Tipe</th>
                    <th>Unit Kerja (SKPD)</th>
                    <th>Email Dinas Terverifikasi</th>
                    <th>Tgl Persetujuan</th>
                    <th style="text-align: right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($antrianTahap2 as $antrian)
                    <tr>
                        <td style="text-align: center;">
                            <input type="checkbox" value="{{ $antrian->uuid ?? $antrian->nomor_referensi }}" class="penerbitan-check" onchange="handlePenerbitanCheckChange()">
                        </td>
                        <td class="font-mono" style="font-weight: 600; color: var(--primary);">
                            {{ $antrian->nomor_referensi }}
                        </td>
                        <td>
                            <div style="font-weight: 600; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                <span>{{ $antrian->nama_lengkap }}</span>
                                @if(($antrian->tipe_pegawai ?? 'ASN') === 'NON_ASN')
                                    <span class="badge" style="background: #6929c4; color: #fff; font-size: 0.625rem; font-weight: 700; padding: 2px 6px;">NON-ASN</span>
                                @else
                                    <span class="badge badge-primary" style="font-size: 0.625rem; font-weight: 700; padding: 2px 6px;">ASN</span>
                                @endif
                            </div>
                            <div style="font-size: 0.6875rem; color: var(--ink-muted);" class="font-mono">
                                NIP: {{ $antrian->nip ?? '-' }}
                            </div>
                        </td>
                        <td>{{ $antrian->skpd->nama_skpd ?? '-' }}</td>
                        <td class="font-mono" style="font-size: 0.8125rem; font-weight: 600; color: #0043ce;">
                            {{ $antrian->email_final_terverifikasi ?? $antrian->email_existing }}
                        </td>
                        <td class="font-mono" style="font-size: 0.75rem;">
                            {{ $antrian->verified_at?->format('d/m/Y H:i') }}
                        </td>
                        <td style="text-align: right;">
                            <button type="button" class="btn btn-primary btn-sm" onclick="openModalTerbitkan('{{ $antrian->uuid ?? $antrian->nomor_referensi }}', '{{ addslashes($antrian->nama_lengkap) }}', '{{ $antrian->email_final_terverifikasi ?? $antrian->email_existing }}')">
                                🔑 Validasi Sertifikat TTE
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--ink-muted); padding: 2rem;">
                            Tidak ada antrian pendaftaran yang menunggu validasi sertifikat Tahap 2.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- ── SEKSI 2: MONITORING SIKLUS HIDUP SERTIFIKAT AKTIF ──────── -->
<div class="card-flat">
    <div class="panel-header">
        <div>
            <h2 style="font-size: 1rem; font-weight: 600;">Daftar Sertifikat TTE Terbit & Siklus Hidup</h2>
            <div style="font-size: 0.75rem; color: var(--ink-muted);">
                Pemantauan masa berlaku sertifikat, zona peringatan H-7 kedaluwarsa, dan pemulihan pencabutan sertifikat.
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <form action="{{ route('admin.penerbitan.index') }}" method="GET" style="display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center;">
            <a href="{{ route('admin.penerbitan.index') }}" class="btn btn-sm {{ empty($statusFilter) ? 'btn-primary' : 'btn-outline' }}">
                Semua
            </a>
            <a href="{{ route('admin.penerbitan.index', ['status' => 'AKTIF']) }}" class="btn btn-sm {{ $statusFilter === 'AKTIF' ? 'btn-primary' : 'btn-outline' }}">
                Aktif
            </a>
            <a href="{{ route('admin.penerbitan.index', ['status' => 'WARNING_H7']) }}" class="btn btn-sm {{ ($statusFilter === 'WARNING_H7' || $statusFilter === 'WARNING_H3') ? 'btn-primary' : 'btn-outline' }}">
                Peringatan H-7
            </a>
            <a href="{{ route('admin.penerbitan.index', ['status' => 'EXPIRED']) }}" class="btn btn-sm {{ $statusFilter === 'EXPIRED' ? 'btn-primary' : 'btn-outline' }}">
                Kedaluwarsa
            </a>
            <a href="{{ route('admin.penerbitan.index', ['status' => 'REVOKED']) }}" class="btn btn-sm {{ $statusFilter === 'REVOKED' ? 'btn-primary' : 'btn-outline' }}">
                Dicabut (Revoked)
            </a>
        </div>

        <div>
            <select name="skpd_id" class="form-select" style="max-width: 240px; font-size: 0.8125rem; padding: 0.4rem 0.6rem;" onchange="this.form.submit()">
                <option value="">-- Filter SKPD --</option>
                @foreach($skpdList as $skpd)
                    <option value="{{ $skpd->id }}" {{ request('skpd_id') == $skpd->id ? 'selected' : '' }}>
                        {{ $skpd->nama_skpd }}
                    </option>
                @endforeach
            </select>
        </div>
    </form>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nama Pegawai & Tipe</th>
                    <th>Unit Kerja (SKPD)</th>
                    <th>Email Dinas Resmi</th>
                    <th>Tgl Penerbitan</th>
                    <th>Tgl Kadaluarsa</th>
                    <th>Status Sertifikat</th>
                    <th style="text-align: right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sertifikatList as $cert)
                    <tr class="{{ in_array($cert->status_sertifikat, ['WARNING_H7', 'WARNING_H3']) ? 'is-warning' : '' }}">
                        <td>
                            <div style="font-weight: 600; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                <span>{{ $cert->pendaftaran->nama_lengkap ?? '-' }}</span>
                                @if(($cert->pendaftaran->tipe_pegawai ?? 'ASN') === 'NON_ASN')
                                    <span class="badge" style="background: #6929c4; color: #fff; font-size: 0.625rem; font-weight: 700; padding: 2px 6px;">NON-ASN</span>
                                @endif
                            </div>
                            <div style="font-size: 0.6875rem; color: var(--ink-muted);" class="font-mono">
                                NIP: {{ $cert->pendaftaran->nip ?? '-' }}
                            </div>
                        </td>
                        <td>{{ $cert->pendaftaran->skpd->nama_skpd ?? '-' }}</td>
                        <td class="font-mono" style="font-size: 0.75rem;">{{ $cert->email_dinas }}</td>
                        <td class="font-mono" style="font-size: 0.75rem;">
                            {{ $cert->tanggal_penerbitan?->format('d/m/Y') }}
                        </td>
                        <td class="font-mono" style="font-size: 0.75rem; font-weight: 600;">
                            {{ $cert->tanggal_kadaluarsa?->format('d/m/Y') }}
                        </td>
                        <td>
                            @if($cert->status_sertifikat === 'AKTIF')
                                <span class="badge badge-aktif">Aktif</span>
                            @elseif($cert->status_sertifikat === 'WARNING_H7' || $cert->status_sertifikat === 'WARNING_H3')
                                @php $badge = $cert->status_masa_berlaku_badge; @endphp
                                <span class="{{ $badge['class'] }}">{{ $badge['label'] }}</span>
                            @elseif($cert->status_sertifikat === 'EXPIRED')
                                <span class="badge badge-expired">Kedaluwarsa</span>
                            @elseif($cert->status_sertifikat === 'REVOKED')
                                <span class="badge badge-revoked">Dicabut</span>
                            @elseif($cert->status_sertifikat === 'PERLU_PERPANJANG')
                                <span class="badge" style="background:#fff1f1; color:#da1e28; border:1px solid #da1e28;">Perlu Perpanjang</span>
                            @endif
                        </td>
                        <td style="text-align: right;">
                            <div style="display: flex; gap: 0.5rem; justify-content: flex-end; align-items: center;">
                                @if($cert->status_sertifikat === 'REVOKED')
                                    @can('undoRevoke', $cert)
                                        <button type="button" class="btn btn-outline btn-sm" style="color: var(--warning-text); border-color: var(--warning); background: #fff;" onclick="openModalUndoRevoke('{{ $cert->id }}', '{{ addslashes($cert->pendaftaran->nama_lengkap ?? '') }}')">
                                            ↩ Pulihkan (Undo)
                                        </button>
                                    @endcan
                                @elseif($cert->status_sertifikat === 'AKTIF' || $cert->status_sertifikat === 'WARNING_H7' || $cert->status_sertifikat === 'WARNING_H3')
                                    <button type="button" class="btn btn-outline btn-sm" style="color: var(--danger); border-color: var(--hairline);" onclick="updateStatusSertifikat('{{ $cert->uuid }}', 'REVOKED')">
                                        Cabut (Revoke)
                                    </button>
                                @else
                                    <button type="button" class="btn btn-outline btn-sm" onclick="updateStatusSertifikat('{{ $cert->uuid }}', 'AKTIF')">
                                        Aktifkan
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--ink-muted); padding: 2.5rem;">
                            Belum ada data sertifikat TTE yang tercatat.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 1rem; display: flex; justify-content: flex-end;">
        {{ $sertifikatList->links('vendor.pagination.carbon') }}
    </div>
</div>

<!-- ── MODAL VALIDASI SERTIFIKAT TTE TAHAP 2 (INDIVIDUAL) ─────── -->
<div class="modal-backdrop" id="modal-terbitkan">
    <div class="modal-flat">
        <div class="modal-header">
            <h3>Validasi Sertifikat TTE BSrE (Tahap 2)</h3>
            <button type="button" class="modal-close" onclick="closeModal('modal-terbitkan')">✕</button>
        </div>
        <form id="form-terbitkan" method="POST">
            @csrf
            <div class="modal-body">
                <div style="background: var(--surface-1); border: 1px solid var(--hairline); padding: 0.875rem; margin-bottom: 1.25rem;">
                    <div style="font-size: 0.75rem; color: var(--ink-secondary);">Pemohon Pegawai:</div>
                    <div id="modal-asn-name" style="font-size: 0.9375rem; font-weight: 700;">-</div>
                    <div id="modal-asn-email" class="font-mono" style="font-size: 0.8125rem; color: var(--primary); margin-top: 2px;">-</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="tanggal_penerbitan">Tanggal Penerbitan Sertifikat <span style="color: var(--danger);">*</span></label>
                    <input type="date" name="tanggal_penerbitan" id="tanggal_penerbitan" class="form-control font-mono" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" required onchange="calculateExpiryDate(this.value)">
                    <div class="form-helper">Tanggal penerbitan resmi oleh Balai Sertifikasi Elektronik (BSrE).</div>
                </div>

                <!-- Preview Kalkulasi Otomatis Masa Berlaku -->
                <div style="background: #edf5ff; border: 1px solid #a6c8ff; padding: 0.875rem; margin-top: 1rem;">
                    <div style="font-size: 0.75rem; color: #0043ce; font-weight: 600;">Masa Berlaku Otomatis:</div>
                    <div style="font-size: 1.125rem; font-weight: 700; color: #002d9c; margin-top: 2px;" id="modal-expiry-preview" class="font-mono">
                        {{ date('d F Y', strtotime('+2 years')) }}
                    </div>
                    <div style="font-size: 0.6875rem; color: #0043ce; margin-top: 4px;">
                        Masa berlaku dihitung secara dinamis sesuai konfigurasi parameter sistem.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-terbitkan')">Batal</button>
                <button type="submit" class="btn btn-primary">Konfirmasi Validasi Sertifikat</button>
            </div>
        </form>
    </div>
</div>

<!-- ── MODAL VALIDASI MASAL TAHAP 2 (BULK VALIDATE) ────────────── -->
<div class="modal-backdrop" id="modal-bulk-validate">
    <div class="modal-flat">
        <div class="modal-header">
            <h3>Validasi Masal Sertifikat TTE (Tahap 2)</h3>
            <button type="button" class="modal-close" onclick="closeModal('modal-bulk-validate')">✕</button>
        </div>
        <form id="form-bulk-validate" action="{{ route('admin.penerbitan.bulk-validate') }}" method="POST">
            @csrf
            <div id="bulk-validate-uuids-container"></div>
            <div class="modal-body">
                <p style="font-size: 0.875rem; color: var(--ink); margin-bottom: 1rem;">
                    Apakah Anda yakin ingin memvalidasi dan menerbitkan sertifikat untuk <strong id="modal-bulk-validate-count">0</strong> permohonan terpilih?
                </p>

                <div class="form-group">
                    <label class="form-label" for="bulk_tanggal_penerbitan">Tanggal Penerbitan Bersama <span style="color: var(--danger);">*</span></label>
                    <input type="date" name="tanggal_penerbitan" id="bulk_tanggal_penerbitan" class="form-control font-mono" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" required>
                    <div class="form-helper">Tanggal penerbitan yang akan diterapkan ke seluruh sertifikat terpilih.</div>
                </div>

                <div class="alert-flat alert-success" style="margin-top: 1rem;">
                    ✓ Sertifikat akan otomatis diaktifkan dan dipantau siklus hidupnya oleh sistem.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-bulk-validate')">Batal</button>
                <button type="submit" class="btn btn-primary">Konfirmasi Validasi Masal</button>
            </div>
        </form>
    </div>
</div>

<!-- ── MODAL KONFIRMASI UNDO REVOKE ────────────────────────────── -->
<div class="modal-backdrop" id="modal-undo-revoke">
    <div class="modal-flat">
        <div class="modal-header">
            <h3>Konfirmasi Pemulihan (Undo Revoke) Sertifikat</h3>
            <button type="button" class="modal-close" onclick="closeModal('modal-undo-revoke')">✕</button>
        </div>
        <form id="form-undo-revoke" method="POST">
            @csrf
            <div class="modal-body">
                <p style="font-size: 0.875rem; color: var(--ink); margin-bottom: 1rem;">
                    Apakah Anda yakin ingin memulihkan status sertifikat TTE atas nama <strong id="modal-undo-asn-name">-</strong> kembali menjadi <strong>AKTIF</strong>?
                </p>
                <div class="alert-flat alert-warning">
                    ⚠️ Aksi ini akan mencatat riwayat pemulihan (audit trail) dan memulihkan status sertifikat pemohon sehingga dapat digunakan kembali.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-undo-revoke')">Batal</button>
                <button type="submit" class="btn btn-warning">Ya, Pulihkan Sertifikat</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function openModalTerbitkan(pendaftaranId, nama, email) {
        document.getElementById('modal-asn-name').textContent = nama;
        document.getElementById('modal-asn-email').textContent = email;
        document.getElementById('form-terbitkan').action = '/admin/penerbitan/' + pendaftaranId;
        openModal('modal-terbitkan');
    }

    function calculateExpiryDate(dateVal) {
        if (!dateVal) return;
        const d = new Date(dateVal);
        d.setFullYear(d.getFullYear() + 2);
        const options = { day: 'numeric', month: 'long', year: 'numeric' };
        document.getElementById('modal-expiry-preview').textContent = d.toLocaleDateString('id-ID', options);
    }

    function updateStatusSertifikat(sertifikatId, status) {
        if (!confirm('Apakah Anda yakin ingin mengubah status sertifikat ini menjadi ' + status + '?')) {
            return;
        }

        fetch('/admin/penerbitan/sertifikat/' + sertifikatId + '/status', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ status_sertifikat: status })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.showToast(data.message, 'success');
                setTimeout(() => location.reload(), 800);
            } else {
                window.showToast(data.message || 'Gagal mengubah status sertifikat.', 'danger');
            }
        })
        .catch(err => {
            console.error('Gagal update status:', err);
            window.showToast('Terjadi kesalahan jaringan.', 'danger');
        });
    }

    /* ── BULK VALIDATION SCRIPTS ── */
    function getSelectedPenerbitanUuids() {
        const checkboxes = document.querySelectorAll('.penerbitan-check:checked');
        return Array.from(checkboxes).map(cb => cb.value);
    }

    function handlePenerbitanCheckChange() {
        const selected = getSelectedPenerbitanUuids();
        const bulkBar = document.getElementById('bulkActionBarPenerbitan');
        const countBadge = document.getElementById('selectedCountBadgePenerbitan');
        const selectAllCb = document.getElementById('selectAllPenerbitan');
        const allCheckboxes = document.querySelectorAll('.penerbitan-check');

        if (countBadge) countBadge.textContent = selected.length;
        if (bulkBar) {
            bulkBar.style.display = selected.length > 0 ? 'block' : 'none';
        }

        if (selectAllCb) {
            selectAllCb.checked = (allCheckboxes.length > 0 && selected.length === allCheckboxes.length);
        }
    }

    function toggleSelectAllPenerbitan(masterCb) {
        const checkboxes = document.querySelectorAll('.penerbitan-check');
        checkboxes.forEach(cb => {
            cb.checked = masterCb.checked;
        });
        handlePenerbitanCheckChange();
    }

    function clearAllPenerbitanSelections() {
        const masterCb = document.getElementById('selectAllPenerbitan');
        if (masterCb) masterCb.checked = false;
        const checkboxes = document.querySelectorAll('.penerbitan-check');
        checkboxes.forEach(cb => { cb.checked = false; });
        handlePenerbitanCheckChange();
    }

    function openBulkValidateModal() {
        const selected = getSelectedPenerbitanUuids();
        if (selected.length === 0) {
            alert('Silakan pilih minimal 1 permohonan dalam antrian validasi.');
            return;
        }

        const container = document.getElementById('bulk-validate-uuids-container');
        container.innerHTML = '';
        selected.forEach(uuid => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'uuids[]';
            input.value = uuid;
            container.appendChild(input);
        });

        document.getElementById('modal-bulk-validate-count').textContent = selected.length;
        openModal('modal-bulk-validate');
    }

    /* ── UNDO REVOKE SCRIPTS ── */
    function openModalUndoRevoke(certId, asnName) {
        document.getElementById('modal-undo-asn-name').textContent = asnName;
        document.getElementById('form-undo-revoke').action = '/admin/penerbitan/sertifikat/' + certId + '/undo-revoke';
        openModal('modal-undo-revoke');
    }
</script>
@endpush

