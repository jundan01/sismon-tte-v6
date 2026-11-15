@extends('layouts.admin')

@section('title', 'Penetapan Target Adopsi TTE SKPD Bulanan')
@section('breadcrumb', 'Target Bulanan SKPD')

@section('content')
<div class="panel-header">
    <div>
        <h1 class="panel-title">Penetapan Target Adopsi TTE SKPD Bulanan (Micro KPI)</h1>
        <p style="font-size: 0.8125rem; color: var(--ink-muted); margin-top: 2px;">
            Atur target kepatuhan sertifikat elektronik bulanan per masing-masing Satuan Kerja Perangkat Daerah (SKPD) di lingkungan Pemkot Cirebon.
        </p>
    </div>
</div>

<!-- ── BAR FILTER PERIODE & AKSI CEPAT ─────────────────────────── -->
<div class="card-flat" style="padding: 1rem 1.25rem; margin-bottom: 1.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <!-- Form Pemilih Periode -->
        <form action="{{ route('admin.target-skpd.index') }}" method="GET" style="display: flex; gap: 0.75rem; align-items: center;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <label for="bulan" style="font-size: 0.8125rem; font-weight: 600;">Bulan:</label>
                <select name="bulan" id="bulan" class="form-select font-mono" style="min-width: 130px;">
                    @foreach($namaBulan as $num => $nama)
                        <option value="{{ $num }}" {{ $bulan === $num ? 'selected' : '' }}>{{ $nama }}</option>
                    @endforeach
                </select>
            </div>

            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <label for="tahun" style="font-size: 0.8125rem; font-weight: 600;">Tahun:</label>
                <select name="tahun" id="tahun" class="form-select font-mono" style="min-width: 90px;">
                    @for($y = date('Y') - 1; $y <= date('Y') + 2; $y++)
                        <option value="{{ $y }}" {{ $tahun === $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>

            <button type="submit" class="btn btn-outline btn-sm">Tampilkan</button>
        </form>

        <!-- Tombol Terapkan Target 100% Otomatis -->
        <form action="{{ route('admin.target-skpd.bulk-default') }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menetapkan target 100% kepatuhan untuk seluruh SKPD pada periode {{ $namaBulan[$bulan] }} {{ $tahun }}?')">
            @csrf
            <input type="hidden" name="tahun" value="{{ $tahun }}">
            <input type="hidden" name="bulan" value="{{ $bulan }}">
            <button type="submit" class="btn btn-secondary btn-sm" style="font-weight: 600;">
                ⚡ Terapkan Target 100% ke Seluruh SKPD
            </button>
        </form>
    </div>
</div>

<!-- ── TABEL PENETAPAN TARGET SKPD ─────────────────────────────── -->
<form action="{{ route('admin.target-skpd.update') }}" method="POST" id="form-target-skpd">
    @csrf
    @method('PUT')
    <input type="hidden" name="tahun" value="{{ $tahun }}">
    <input type="hidden" name="bulan" value="{{ $bulan }}">

    <div class="card-flat" style="border-top: 4px solid var(--primary); margin-bottom: 1.5rem;">
        <div class="panel-header" style="background: var(--surface-1); padding: 0.875rem 1.25rem;">
            <div style="font-weight: 700; font-size: 0.875rem;">
                Periode Target: <span style="color: var(--primary);">{{ $namaBulan[$bulan] }} {{ $tahun }}</span>
            </div>
            <span class="badge badge-menunggu font-mono">{{ $skpdList->count() }} SKPD Terdaftar</span>
        </div>

        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 120px;">Kode SKPD</th>
                        <th>Satuan Kerja Perangkat Daerah (SKPD)</th>
                        <th style="width: 120px; text-align: center;">Total ASN</th>
                        <th style="width: 160px; text-align: center;">Target Adopsi (%)</th>
                        <th style="width: 160px; text-align: center;">Target Pegawai</th>
                        <th style="width: 130px; text-align: center;">Status Target</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($skpdList as $skpd)
                        @php
                            $metric = $existingMetrics->get($skpd->id);
                            $targetPct = $metric ? $metric->target_persentase : 100.00;
                            $targetCount = $metric ? $metric->target_jumlah_pegawai : ($skpd->total_asn ?? 0);
                        @endphp
                        <tr>
                            <td class="font-mono" style="font-size: 0.75rem; color: var(--ink-muted);">
                                {{ $skpd->kode_skpd }}
                            </td>
                            <td>
                                <strong style="font-size: 0.875rem;">{{ $skpd->nama_skpd }}</strong>
                                @if($skpd->singkatan)
                                    <span class="badge" style="background: #e0e0e0; color: #161616; font-size: 0.6875rem; margin-left: 4px;">
                                        {{ $skpd->singkatan }}
                                    </span>
                                @endif
                            </td>
                            <td style="text-align: center;" class="font-mono font-semibold">
                                {{ number_format($skpd->total_asn ?? 0) }}
                            </td>
                            <td style="text-align: center;">
                                <div style="display: inline-flex; align-items: center; gap: 4px;">
                                    <input type="number" step="0.01" min="0" max="100" name="targets[{{ $skpd->id }}][target_persentase]" class="form-control font-mono" style="width: 90px; text-align: right;" value="{{ old('targets.'.$skpd->id.'.target_persentase', $targetPct) }}" required onchange="calculatePegawai({{ $skpd->id }}, {{ $skpd->total_asn ?? 0 }})">
                                    <span style="font-size: 0.75rem; font-weight: 600;">%</span>
                                </div>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: inline-flex; align-items: center; gap: 4px;">
                                    <input type="number" min="0" name="targets[{{ $skpd->id }}][target_jumlah_pegawai]" id="target_count_{{ $skpd->id }}" class="form-control font-mono" style="width: 90px; text-align: right;" value="{{ old('targets.'.$skpd->id.'.target_jumlah_pegawai', $targetCount) }}" required>
                                    <span style="font-size: 0.75rem; font-weight: 600;">Org</span>
                                </div>
                            </td>
                            <td style="text-align: center;">
                                @if($metric)
                                    <span class="badge badge-success" style="font-size: 0.6875rem;">TETAP</span>
                                @else
                                    <span class="badge badge-warning" style="font-size: 0.6875rem;">DEFAULT</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="background: #ffffff; border-top: 1px solid var(--hairline); padding: 1rem 1.25rem; display: flex; justify-content: space-between; align-items: center;">
            <div style="font-size: 0.75rem; color: var(--ink-muted);">
                Target ini digunakan sebagai acuan KPI kepatuhan pada analitik capaian bulanan SKPD.
            </div>
            <button type="submit" class="btn btn-primary" id="btn-save-targets" style="padding: 0.625rem 2rem; font-weight: 600;">
                💾 Simpan Seluruh Target SKPD
            </button>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    function calculatePegawai(skpdId, totalAsn) {
        if (!totalAsn || totalAsn <= 0) return;
        const pctInput = document.querySelector('input[name="targets[' + skpdId + '][target_persentase]"]');
        const countInput = document.getElementById('target_count_' + skpdId);
        if (pctInput && countInput) {
            const pct = parseFloat(pctInput.value) || 0;
            const calculated = Math.round((pct / 100) * totalAsn);
            countInput.value = calculated;
        }
    }
</script>
@endpush
