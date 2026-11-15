@extends('layouts.admin')

@section('title', 'Verifikasi Tahap 1 — Antrian Permohonan ASN')
@section('breadcrumb', 'Verifikasi Tahap 1')

@section('content')
<div class="panel-header">
    <div>
        <h1 class="panel-title">Antrian Verifikasi Berkas Pemohon (Tahap 1)</h1>
        <p style="font-size: 0.8125rem; color: var(--ink-muted); margin-top: 2px;">
            Verifikasi identitas ASN, keabsahan surat rekomendasi, dan rekapitulasi akun email dinas resmi (@cirebonkota.go.id).
        </p>
    </div>
</div>

<!-- ── FILTER BAR & PENCARIAN ─────────────────────────────────── -->
<div class="card-flat" style="padding: 1rem; margin-bottom: 1rem;">
    <form action="{{ route('admin.verifikasi.index') }}" method="GET" style="display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; justify-content: space-between;">
        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center;">
            <!-- Status Tabs -->
            <a href="{{ route('admin.verifikasi.index', ['status' => 'MENUNGGU_VERIFIKASI']) }}" class="btn btn-sm {{ ($currentStatus ?? 'MENUNGGU_VERIFIKASI') === 'MENUNGGU_VERIFIKASI' ? 'btn-primary' : 'btn-outline' }}">
                Menunggu Verifikasi
            </a>
            <a href="{{ route('admin.verifikasi.index', ['status' => 'DISETUJUI']) }}" class="btn btn-sm {{ ($currentStatus ?? '') === 'DISETUJUI' ? 'btn-primary' : 'btn-outline' }}">
                Disetujui
            </a>
            <a href="{{ route('admin.verifikasi.index', ['status' => 'DITOLAK']) }}" class="btn btn-sm {{ ($currentStatus ?? '') === 'DITOLAK' ? 'btn-primary' : 'btn-outline' }}">
                Ditolak
            </a>
            <a href="{{ route('admin.verifikasi.index', ['status' => '']) }}" class="btn btn-sm {{ empty($currentStatus) ? 'btn-primary' : 'btn-outline' }}">
                Semua Status
            </a>
        </div>

        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <select name="skpd_id" class="form-select" style="max-width: 220px; font-size: 0.8125rem; padding: 0.4rem 0.6rem;" onchange="this.form.submit()">
                <option value="">-- Filter Unit Kerja (SKPD) --</option>
                @foreach($skpdList as $skpd)
                    <option value="{{ $skpd->id }}" {{ request('skpd_id') == $skpd->id ? 'selected' : '' }}>
                        {{ $skpd->nama_skpd }}
                    </option>
                @endforeach
            </select>

            <input type="text" name="q" class="form-control" style="max-width: 250px; font-size: 0.8125rem; padding: 0.4rem 0.6rem;" placeholder="Cari Nama / NIP / Deskripsi / Ref..." value="{{ request('q') }}">
            <button type="submit" class="btn btn-secondary btn-sm">Cari</button>
            @if(request('q') || request('deskripsi'))
                <a href="{{ route('admin.verifikasi.index', ['status' => $currentStatus, 'skpd_id' => request('skpd_id')]) }}" class="btn btn-outline btn-sm" style="color: var(--danger);" title="Reset Pencarian">✕</a>
            @endif
        </div>
    </form>

    @if(request('q') || request('deskripsi'))
        <div style="background: rgba(15, 98, 254, 0.05); border-left: 3px solid var(--primary); padding: 0.45rem 0.85rem; font-size: 0.75rem; color: var(--ink-secondary); margin-top: 0.75rem; display: flex; justify-content: space-between; align-items: center;">
            <span>
                🔍 Menampilkan hasil pencarian: <strong>{{ request('q') ?: request('deskripsi') }}</strong> 
                (mencakup Nama, NIP/NIK, Nomor Referensi, dan <strong>Deskripsi Penempatan Kerja ASN</strong>)
            </span>
            <a href="{{ route('admin.verifikasi.index', ['status' => $currentStatus, 'skpd_id' => request('skpd_id')]) }}" style="color: var(--danger); text-decoration: none; font-weight: 600;">✕ Hapus Pencarian</a>
        </div>
    @endif
</div>

<!-- ── BAR AKSI MASAL (BULK ACTION) ──────────────────────────── -->
<div id="bulkActionBar" style="display: none; background: #eef9fd; border: 1px solid #72c3e8; border-left: 4px solid #0072c3; padding: 0.75rem 1rem; margin-bottom: 1rem; border-radius: 4px;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <span class="badge" style="background: #0072c3; color: #fff; font-size: 0.75rem; font-weight: 700;" id="selectedCountBadge">0</span>
            <span style="font-size: 0.875rem; color: #002d9c; font-weight: 600;">permohonan berstatus "Menunggu Verifikasi" terpilih</span>
        </div>
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <button type="button" class="btn btn-outline btn-sm" onclick="clearAllSelections()" style="background: #fff;">
                ✕ Batal Pilih
            </button>
            <button type="button" class="btn btn-primary btn-sm" onclick="openBulkApproveModal()">
                ⚡ Setujui Masal Terpilih (Tahap 1)
            </button>
        </div>
    </div>
</div>

<!-- ── TABEL ANTRIAN FIFO ────────────────────────────────────── -->
<div class="table-responsive">
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 36px; text-align: center;">
                    <input type="checkbox" id="selectAllVerifikasi" title="Pilih Semua di Halaman Ini" onchange="toggleSelectAll(this)">
                </th>
                <th style="width: 140px;">No. Referensi</th>
                <th>Nama Pegawai & Tipe</th>
                <th>Unit Kerja (SKPD) & Penempatan</th>
                <th>Status Email Dinas</th>
                <th>Status Berkas</th>
                <th>Tgl Pendaftaran</th>
                <th style="text-align: right;">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse($pendaftaranList as $item)
                <tr>
                    <td style="text-align: center;">
                        @if($item->status === 'MENUNGGU_VERIFIKASI')
                            <input type="checkbox" value="{{ $item->uuid }}" class="verifikasi-check" onchange="handleRowCheckChange()">
                        @else
                            <input type="checkbox" disabled style="opacity: 0.2; cursor: not-allowed;">
                        @endif
                    </td>
                    <td class="font-mono" style="font-weight: 600; color: var(--primary);">
                        {{ $item->nomor_referensi }}
                    </td>
                    <td>
                        <div style="font-weight: 600; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                            <span>{{ $item->nama_lengkap }}</span>
                            @if(($item->tipe_pegawai ?? 'ASN') === 'NON_ASN')
                                <span class="badge" style="background: #6929c4; color: #fff; font-size: 0.625rem; font-weight: 700; padding: 2px 6px;">NON-ASN</span>
                            @else
                                <span class="badge badge-primary" style="font-size: 0.625rem; font-weight: 700; padding: 2px 6px;">ASN</span>
                            @endif
                        </div>
                        <div style="font-size: 0.6875rem; color: var(--ink-muted);" class="font-mono">
                            NIP: {{ $item->nip ?? '-' }}
                        </div>
                    </td>
                    <td>
                        <div style="font-weight: 600;">{{ $item->skpd->nama_skpd ?? '-' }}</div>
                        <div style="font-size: 0.6875rem; color: var(--ink-muted);">
                            {{ $item->jabatan->nama_label ?? '-' }}
                        </div>
                        @if(!empty($item->deskripsi))
                            <div style="margin-top: 4px; font-size: 0.75rem; color: var(--primary); background: rgba(15, 98, 254, 0.08); border: 1px solid rgba(15, 98, 254, 0.2); padding: 2px 7px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;">
                                📍 <strong>Penempatan:</strong> {{ $item->deskripsi }}
                            </div>
                        @endif
                    </td>
                    <td>
                        @if($item->is_email_recap_confirmed)
                            <span class="badge badge-aktif">✓ Terekap</span>
                            <div class="font-mono" style="font-size: 0.6875rem; color: var(--ink-secondary); margin-top: 2px;">
                                {{ $item->email_final_terverifikasi }}
                            </div>
                        @elseif($item->status_memiliki_email)
                            <span class="badge" style="background:#e0e0e0; color:#161616;">Existing</span>
                            <div class="font-mono" style="font-size: 0.6875rem; color: var(--ink-muted); margin-top: 2px;">
                                {{ $item->email_existing }}
                            </div>
                        @else
                            <span class="badge badge-warning-h3">Belum Direkap</span>
                            <div class="font-mono" style="font-size: 0.6875rem; color: var(--primary); margin-top: 2px;">
                                Usulan: {{ $item->email_usulan }}
                            </div>
                        @endif
                    </td>
                    <td>
                        @if($item->status === 'MENUNGGU_VERIFIKASI')
                            <span class="badge badge-menunggu">Menunggu</span>
                        @elseif($item->status === 'DISETUJUI')
                            <span class="badge badge-disetujui">Disetujui</span>
                        @elseif($item->status === 'DITOLAK')
                            <span class="badge badge-ditolak">Ditolak</span>
                        @endif
                    </td>
                    <td class="font-mono" style="font-size: 0.75rem;">
                        {{ $item->created_at?->format('d/m/Y H:i') }}
                    </td>
                    <td style="text-align: right;">
                        <a href="{{ route('admin.verifikasi.show', $item->nomor_referensi) }}" class="btn btn-primary btn-sm">
                            Detail & Verifikasi →
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align: center; color: var(--ink-muted); padding: 2.5rem;">
                        Tidak ada data pendaftaran yang sesuai dengan filter pencarian.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<!-- Pagination Links -->
<div style="margin-top: 1rem; display: flex; justify-content: flex-end;">
    {{ $pendaftaranList->links('vendor.pagination.carbon') }}
</div>

<!-- ── MODAL KONFIRMASI PERSETUJUAN MASAL (TAHAP 1) ─────────────── -->
<div class="modal-backdrop" id="modal-bulk-approve">
    <div class="modal-flat">
        <div class="modal-header">
            <h3>Konfirmasi Persetujuan Masal (Tahap 1)</h3>
            <button type="button" class="modal-close" onclick="closeModal('modal-bulk-approve')">✕</button>
        </div>
        <form id="form-bulk-approve" action="{{ route('admin.verifikasi.bulk-approve') }}" method="POST">
            @csrf
            <div id="bulk-uuids-container"></div>
            <div class="modal-body">
                <p style="font-size: 0.875rem; color: var(--ink); margin-bottom: 1rem;">
                    Apakah Anda yakin ingin menyetujui secara masal <strong id="modal-bulk-count">0</strong> berkas permohonan yang dipilih?
                </p>
                <div class="alert-flat alert-success">
                    ✓ Permohonan yang disetujui akan langsung diteruskan ke antrian <strong>Validasi Sertifikat TTE (Tahap 2)</strong>.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-bulk-approve')">Batal</button>
                <button type="submit" class="btn btn-primary">Ya, Setujui Semua Terpilih</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function getSelectedUuids() {
        const checkboxes = document.querySelectorAll('.verifikasi-check:checked');
        return Array.from(checkboxes).map(cb => cb.value);
    }

    function handleRowCheckChange() {
        const selected = getSelectedUuids();
        const bulkBar = document.getElementById('bulkActionBar');
        const countBadge = document.getElementById('selectedCountBadge');
        const selectAllCb = document.getElementById('selectAllVerifikasi');
        const allCheckboxes = document.querySelectorAll('.verifikasi-check');

        if (countBadge) countBadge.textContent = selected.length;
        if (bulkBar) {
            bulkBar.style.display = selected.length > 0 ? 'block' : 'none';
        }

        if (selectAllCb) {
            selectAllCb.checked = (allCheckboxes.length > 0 && selected.length === allCheckboxes.length);
        }
    }

    function toggleSelectAll(masterCb) {
        const checkboxes = document.querySelectorAll('.verifikasi-check');
        checkboxes.forEach(cb => {
            cb.checked = masterCb.checked;
        });
        handleRowCheckChange();
    }

    function clearAllSelections() {
        const masterCb = document.getElementById('selectAllVerifikasi');
        if (masterCb) masterCb.checked = false;
        const checkboxes = document.querySelectorAll('.verifikasi-check');
        checkboxes.forEach(cb => { cb.checked = false; });
        handleRowCheckChange();
    }

    function openBulkApproveModal() {
        const selected = getSelectedUuids();
        if (selected.length === 0) {
            alert('Silakan pilih minimal 1 permohonan berstatus Menunggu Verifikasi.');
            return;
        }

        const container = document.getElementById('bulk-uuids-container');
        container.innerHTML = '';
        selected.forEach(uuid => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'uuids[]';
            input.value = uuid;
            container.appendChild(input);
        });

        document.getElementById('modal-bulk-count').textContent = selected.length;
        openModal('modal-bulk-approve');
    }
</script>
@endpush
