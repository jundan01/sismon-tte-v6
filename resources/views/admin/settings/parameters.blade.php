@extends('layouts.admin')

@section('title', 'Pengaturan Parameter Kebijakan Sistem')
@section('breadcrumb', 'Pengaturan Parameter')

@section('content')
<div class="panel-header">
    <div>
        <h1 class="panel-title">Pengaturan Parameter Kebijakan Sistem (Anti Hard-Coding)</h1>
        <p style="font-size: 0.8125rem; color: var(--ink-muted); margin-top: 2px;">
            Konfigurasi tata kelola regulasi, masa aktif sertifikat TTE, ambang scanner notifikasi, dan batasan operasional Superadmin.
        </p>
    </div>
</div>

<!-- Informasi Kebijakan Dinamis -->
<div style="background: #eef9fd; border: 1px solid #72c3e8; border-left: 4px solid #0072c3; padding: 1rem 1.25rem; margin-bottom: 1.5rem;">
    <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
        <span style="font-size: 1.25rem; line-height: 1;">⚡</span>
        <div style="font-size: 0.8125rem; color: #0043ce; line-height: 1.5;">
            <strong>Integrasi Cache Redis Aktif:</strong> Setiap perubahan parameter di bawah ini akan langsung menginvalidasi cache dan diterapkan secara seketika (*real-time*) pada transaksi penerbitan sertifikat, validasi pendaftaran, dan penjadwalan scanner tanpa perlu melakukan restart server.
        </div>
    </div>
</div>

<form action="{{ route('admin.settings.parameters.update') }}" method="POST" id="form-system-parameters">
    @csrf
    @method('PUT')

    @foreach($groupedParameters as $groupName => $params)
        <div class="card-flat" style="border-top: 4px solid var(--primary); margin-bottom: 1.5rem;">
            <div class="panel-header" style="background: var(--surface-1); padding: 0.875rem 1.25rem; border-bottom: 1px solid var(--hairline);">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    @if($groupName === 'CERTIFICATE')
                        <span>🛡️</span>
                        <strong style="font-size: 0.9375rem;">KEBIJAKAN SERTIFIKAT ELEKTRONIK (BSrE)</strong>
                    @elseif($groupName === 'REGISTRATION')
                        <span>👥</span>
                        <strong style="font-size: 0.9375rem;">KEBIJAKAN REGISTRASI & INKLUSIVITAS PEGAWAI</strong>
                    @elseif($groupName === 'OPERATIONAL')
                        <span>⚡</span>
                        <strong style="font-size: 0.9375rem;">BATASAN OPERASIONAL & BATCH VALIDASI</strong>
                    @elseif($groupName === 'ANALYTICS')
                        <span>📊</span>
                        <strong style="font-size: 0.9375rem;">TARGET CAPAIAN ADOPSI KOTA</strong>
                    @else
                        <span>⚙️</span>
                        <strong style="font-size: 0.9375rem;">KELOMPOK {{ $groupName }}</strong>
                    @endif
                </div>
            </div>

            <div style="padding: 1.25rem;">
                <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                    @foreach($params as $param)
                        <div style="display: grid; grid-template-columns: minmax(280px, 1.5fr) minmax(220px, 1fr); gap: 1.5rem; align-items: center; padding-bottom: 1rem; border-bottom: 1px solid var(--hairline);">
                            <div>
                                <label class="form-label" for="param_{{ $param->param_key }}" style="font-weight: 700; margin-bottom: 2px;">
                                    {{ $param->label }}
                                </label>
                                <div style="font-size: 0.75rem; color: var(--ink-secondary); line-height: 1.4;">
                                    {{ $param->description }}
                                </div>
                                <div class="font-mono" style="font-size: 0.6875rem; color: var(--ink-muted); margin-top: 4px;">
                                    Key: <code>{{ $param->param_key }}</code> · Tipe: {{ $param->data_type }}
                                </div>
                            </div>

                            <div>
                                @if($param->data_type === 'BOOLEAN')
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <label style="position: relative; display: inline-block; width: 48px; height: 26px; margin: 0;">
                                            <input type="checkbox" name="parameters[{{ $param->param_key }}]" id="param_{{ $param->param_key }}" value="1" {{ filter_var(old('parameters.'.$param->param_key, $param->param_value), FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }} style="opacity: 0; width: 0; height: 0;">
                                            <span class="toggle-slider" onclick="this.previousElementSibling.click()" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .3s; border-radius: 26px;"></span>
                                        </label>
                                        <span id="label_toggle_{{ $param->param_key }}" style="font-size: 0.8125rem; font-weight: 600;">
                                            {{ filter_var($param->param_value, FILTER_VALIDATE_BOOLEAN) ? 'AKTIF (Diizinkan)' : 'NONAKTIF (Ditutup)' }}
                                        </span>
                                    </div>
                                @elseif($param->data_type === 'INTEGER')
                                    <div style="display: flex; align-items: center; gap: 0.5rem; max-width: 220px;">
                                        <input type="number" name="parameters[{{ $param->param_key }}]" id="param_{{ $param->param_key }}" class="form-control font-mono @error('parameters.'.$param->param_key) is-invalid @enderror" value="{{ old('parameters.'.$param->param_key, $param->param_value) }}" required>
                                        @if($param->param_key === 'cert_validity_years')
                                            <span style="font-size: 0.8125rem; font-weight: 600; color: var(--ink-muted);">Tahun</span>
                                        @elseif($param->param_key === 'cert_expiry_warning_days')
                                            <span style="font-size: 0.8125rem; font-weight: 600; color: var(--ink-muted);">Hari</span>
                                        @elseif($param->param_key === 'bulk_verification_max_batch')
                                            <span style="font-size: 0.8125rem; font-weight: 600; color: var(--ink-muted);">Item</span>
                                        @elseif($param->param_key === 'city_adoption_target_percent')
                                            <span style="font-size: 0.8125rem; font-weight: 600; color: var(--ink-muted);">%</span>
                                        @endif
                                    </div>
                                    @error('parameters.'.$param->param_key)
                                        <div class="form-error" style="margin-top: 4px;">{{ $message }}</div>
                                    @enderror
                                @else
                                    <input type="text" name="parameters[{{ $param->param_key }}]" id="param_{{ $param->param_key }}" class="form-control @error('parameters.'.$param->param_key) is-invalid @enderror" value="{{ old('parameters.'.$param->param_key, $param->param_value) }}">
                                    @error('parameters.'.$param->param_key)
                                        <div class="form-error" style="margin-top: 4px;">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach

    <!-- Action Bar Sticky -->
    <div style="background: #ffffff; border: 1px solid var(--hairline); padding: 1rem 1.5rem; display: flex; justify-content: space-between; align-items: center;">
        <span style="font-size: 0.75rem; color: var(--ink-muted);">
            Pastikan parameter telah sesuai dengan Keputusan Kepala Dinas Komunikasi dan Informatika Kota Cirebon.
        </span>
        <button type="submit" class="btn btn-primary" id="btn-save-parameters" style="padding: 0.625rem 2rem; font-weight: 600;">
            💾 Simpan Perubahan Parameter
        </button>
    </div>
</form>
@endsection

@push('styles')
<style>
    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .3s;
        border-radius: 50%;
    }
    input:checked + .toggle-slider {
        background-color: var(--primary);
    }
    input:checked + .toggle-slider:before {
        transform: translateX(22px);
    }
</style>
@endpush

@push('scripts')
<script>
    document.querySelectorAll('input[type="checkbox"]').forEach(toggle => {
        toggle.addEventListener('change', function () {
            const label = document.getElementById('label_toggle_' + this.id.replace('param_', ''));
            if (label) {
                label.textContent = this.checked ? 'AKTIF (Diizinkan)' : 'NONAKTIF (Ditutup)';
            }
        });
    });
</script>
@endpush
