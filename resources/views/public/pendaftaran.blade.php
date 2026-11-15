@extends('layouts.public')

@section('title', 'Formulir Pendaftaran Mandiri TTE ASN & Non-ASN Kota Cirebon')

@section('content')
<div style="margin-bottom: 1.25rem;">
    <a href="{{ route('home') }}" style="display: inline-flex; align-items: center; gap: 0.375rem; font-size: 0.8125rem; color: var(--primary); text-decoration: none; font-weight: 600;">
        &larr; Kembali ke Beranda Portal
    </a>
    <span style="color: var(--ink-muted); margin: 0 0.5rem;">/</span>
    <span style="font-size: 0.8125rem; color: var(--ink-secondary);">Formulir Pendaftaran TTE</span>
</div>

<!-- SEKSI 1: AGREGAT CAPAIAN TTE KOTA CIREBON (MACRO KPI & PIMPINAN) -->
@if(isset($macroStats))
<div style="margin-bottom: 2rem;">
    <!-- Banner Capaian Makro Kota -->
    <div style="background: var(--surface-dark); color: #ffffff; padding: 1.25rem 1.5rem; border-left: 4px solid var(--primary); margin-bottom: 1rem;">
        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 1rem;">
            <div>
                <span class="badge" style="background: rgba(15, 98, 254, 0.2); color: #78a9ff; font-weight: 700; border: 1px solid #0f62fe; margin-bottom: 0.25rem;">
                    TRANSPARANSI ADOPSI TTE KOTA CIREBON
                </span>
                <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0.25rem 0 0;">
                    Monitoring & Validasi Sertifikat Elektronik Terpadu
                </h2>
                <div style="font-size: 0.8125rem; color: #c6c6c6; margin-top: 0.25rem;">
                    Pengawasan real-time kepemilikan TTE aparatur Pemerintah Kota Cirebon sesuai regulasi BSSN & BSrE.
                </div>
            </div>
            <div style="text-align: right; min-width: 140px;">
                <div style="font-size: 0.75rem; color: #a8a8a8; text-transform: uppercase;">Capaian Adopsi Kota</div>
                <div class="font-mono" style="font-size: 1.875rem; font-weight: 800; color: #42be65; line-height: 1.1;">
                    {{ $macroStats['persentase_kota'] ?? 0 }}%
                </div>
                <div style="font-size: 0.6875rem; color: #8d8d8d;">
                    Target: {{ $macroStats['target_persentase'] ?? 100 }}% Pegawai
                </div>
            </div>
        </div>

        <!-- Progress Bar Capaian -->
        <div style="margin-top: 1rem; background: #393939; height: 6px; width: 100%;">
            <div style="background: #24a148; height: 100%; width: {{ min(100, $macroStats['persentase_kota'] ?? 0) }}%;"></div>
        </div>
    </div>

    <!-- 4 Kartu Metrik Agregat -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.25rem;">
        <div class="card-flat" style="padding: 1rem; border-top: 3px solid #24a148;">
            <div style="font-size: 0.75rem; color: var(--ink-muted); text-transform: uppercase; font-weight: 600;">Sertifikat TTE Aktif</div>
            <div class="font-mono" style="font-size: 1.5rem; font-weight: 700; color: #24a148; margin-top: 0.25rem;">
                {{ number_format($macroStats['total_sertifikat'] ?? 0) }}
            </div>
            <div style="font-size: 0.6875rem; color: var(--ink-muted); margin-top: 0.25rem;">Tervalidasi aktif BSrE</div>
        </div>

        <div class="card-flat" style="padding: 1rem; border-top: 3px solid var(--primary);">
            <div style="font-size: 0.75rem; color: var(--ink-muted); text-transform: uppercase; font-weight: 600;">Total Pendaftar</div>
            <div class="font-mono" style="font-size: 1.5rem; font-weight: 700; color: var(--primary); margin-top: 0.25rem;">
                {{ number_format($macroStats['total_pendaftaran'] ?? 0) }}
            </div>
            <div style="font-size: 0.6875rem; color: var(--ink-muted); margin-top: 0.25rem;">Aparatur terdata di antrian</div>
        </div>

        <div class="card-flat" style="padding: 1rem; border-top: 3px solid #6929c4;">
            <div style="font-size: 0.75rem; color: var(--ink-muted); text-transform: uppercase; font-weight: 600;">Populasi Pegawai</div>
            <div class="font-mono" style="font-size: 1.5rem; font-weight: 700; color: #6929c4; margin-top: 0.25rem;">
                {{ number_format($macroStats['total_pegawai'] ?? 0) }}
            </div>
            <div style="font-size: 0.6875rem; color: var(--ink-muted); margin-top: 0.25rem;">Total ASN & Aparatur Kota</div>
        </div>

        <div class="card-flat" style="padding: 1rem; border-top: 3px solid #1192e8;">
            <div style="font-size: 0.75rem; color: var(--ink-muted); text-transform: uppercase; font-weight: 600;">Unit Kerja (SKPD)</div>
            <div class="font-mono" style="font-size: 1.5rem; font-weight: 700; color: #1192e8; margin-top: 0.25rem;">
                {{ number_format($macroStats['total_skpd'] ?? 0) }}
            </div>
            <div style="font-size: 0.6875rem; color: var(--ink-muted); margin-top: 0.25rem;">Instansi terlayani</div>
        </div>
    </div>
</div>
@endif

<!-- SEKSI 2: FORMULIR PENDAFTARAN DUAL-TRACK (ASN & NON-ASN) -->
<div class="card-flat" style="border-top: 4px solid var(--primary);" id="seksi-formulir">
    <div class="panel-header" style="flex-direction: column; align-items: flex-start; gap: 0.25rem;">
        <h1 style="font-size: 1.5rem; font-weight: 700;">Formulir Pendaftaran Sertifikat Elektronik (TTE)</h1>
        <p style="font-size: 0.8125rem; color: var(--ink-secondary);">
            Layanan resmi permohonan sertifikat TTE BSrE dan penerbitan email dinas resmi Pemerintah Kota Cirebon.
        </p>
    </div>

    <!-- Draft Auto-Save Banner -->
    <div id="draft-alert" style="display: none; background: #edf5ff; border: 1px solid #a6c8ff; padding: 8px 12px; margin-bottom: 1.25rem; font-size: 0.75rem; color: #0043ce; justify-content: space-between; align-items: center;">
        <span>Ditemukan draft pengisian sebelumnya yang tersimpan di browser Anda.</span>
        <button type="button" class="btn btn-ghost btn-sm" onclick="clearDraft()" style="color: var(--danger); font-weight: 600;">Hapus Draft</button>
    </div>

    <form action="{{ route('pendaftaran.store', [], false) }}" method="POST" enctype="multipart/form-data" id="form-pendaftaran">
        @csrf

        {{-- ── 1. PEMILIH JALUR DUAL-TRACK (ASN vs NON-ASN) ── --}}
        <div style="margin-bottom: 1.5rem; background: var(--surface-1); border: 1px solid var(--hairline); padding: 1rem;">
            <label class="form-label" style="font-weight: 700; font-size: 0.875rem; margin-bottom: 0.5rem; display: block;">
                Jalur Kepegawaian Pemohon <span style="color: var(--danger);">*</span>
            </label>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.75rem;">
                <div>
                    @php
                        $isNonAsnInitial = old('tipe_pegawai') === 'NON_ASN' || request('tipe') === 'NON_ASN' || request('kategori') === 'non-asn';
                    @endphp
                    <input type="radio" name="tipe_pegawai" id="tipe_asn" value="ASN" class="radio-tile-input" style="display:none;" {{ !$isNonAsnInitial ? 'checked' : '' }} onchange="setTipePegawai('ASN')">
                    <label for="tipe_asn" id="label_tipe_asn" class="radio-tile-label" style="border: 2px solid {{ !$isNonAsnInitial ? 'var(--primary)' : 'var(--hairline)' }}; padding: 0.875rem; cursor: pointer; display: block; background: #ffffff;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-weight: 700; font-size: 0.875rem;">🏢 Jalur ASN</span>
                            <span class="badge badge-info" style="font-size: 0.6875rem;">PNS / PPPK</span>
                        </div>
                        <div style="font-size: 0.75rem; color: var(--ink-secondary); margin-top: 4px;">
                            Wajib melampirkan NIP 18 digit resmi BKN beserta data kepangkatan.
                        </div>
                    </label>
                </div>
                <div>
                    <input type="radio" name="tipe_pegawai" id="tipe_non_asn" value="NON_ASN" class="radio-tile-input" style="display:none;" {{ $isNonAsnInitial ? 'checked' : '' }} {{ empty($allowNonAsn) ? 'disabled' : '' }} onchange="setTipePegawai('NON_ASN')">
                    <label for="tipe_non_asn" id="label_tipe_non_asn" class="radio-tile-label" style="border: 2px solid {{ $isNonAsnInitial ? 'var(--primary)' : 'var(--hairline)' }}; padding: 0.875rem; cursor: {{ empty($allowNonAsn) ? 'not-allowed' : 'pointer' }}; display: block; background: #ffffff; opacity: {{ empty($allowNonAsn) ? '0.6' : '1' }};">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-weight: 700; font-size: 0.875rem;">👥 Jalur Non-ASN</span>
                            @if(!empty($allowNonAsn))
                                <span class="badge" style="background: #fef3c7; color: #92400e; font-size: 0.6875rem;">THL / BLUD</span>
                            @else
                                <span class="badge badge-danger" style="font-size: 0.6875rem;">Pendaftaran Ditutup</span>
                            @endif
                        </div>
                        <div style="font-size: 0.75rem; color: var(--ink-secondary); margin-top: 4px;">
                            Jalur aparatur penunjang/kontrak SKPD (Tanpa pengisian NIP).
                        </div>
                    </label>
                </div>
            </div>
            @error('tipe_pegawai') <div class="form-error">{{ $message }}</div> @enderror
        </div>

        {{-- ── 2. DATA IDENTITAS PEGAWAI ────────────────────── --}}
        <div style="margin-bottom: 2rem;">
            <div class="form-group">
                <label class="form-label" for="nama_lengkap">Nama Lengkap Sesuai KTP <span style="color: var(--danger);">*</span></label>
                <input type="text" name="nama_lengkap" id="nama_lengkap" class="form-control @error('nama_lengkap') is-invalid @enderror" value="{{ old('nama_lengkap') }}" placeholder="Contoh: Budi Santoso" required>
                <div class="form-helper">Isi nama lengkap sesuai KTP tanpa gelar akademik/keagamaan dan tanpa tanda baca titik (.) atau koma (,).</div>
                @error('nama_lengkap') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            {{-- ── IDENTITAS NIK & NIP BESERTA WARNING BANNER DIBAWAH FIELD NIP ── --}}
            @php
                $nikErr = $errors->first('nik');
                $nipErr = $errors->first('nip');
                $nikSudahTerdata = $errors->has('nik') && str_contains($nikErr, 'sudah terdata');
                $nipSudahTerdata = $errors->has('nip') && str_contains($nipErr, 'sudah terdata');
                $isDataSudahMasuk = $nikSudahTerdata || $nipSudahTerdata;

                $nikFormatError = ($errors->has('nik') && !$nikSudahTerdata) ? $nikErr : null;
                $nipFormatError = ($errors->has('nip') && !$nipSudahTerdata) ? $nipErr : null;
            @endphp

            <!-- 1. Field NIK -->
            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label class="form-label" for="nik">Nomor Induk Kependudukan (NIK 16 Digit) <span style="color: var(--danger);">*</span></label>
                <input type="text" name="nik" id="nik" maxlength="16" pattern="\d{16}" class="form-control font-mono {{ $nikFormatError ? 'is-invalid' : '' }}" value="{{ old('nik') }}" placeholder="3274xxxxxxxxxxxx" required>
                <div class="form-helper">Wajib 16 digit angka sesuai KTP.</div>
                @if($nikFormatError)
                    <div class="form-error">{{ $nikFormatError }}</div>
                @endif
            </div>

            <!-- 2. Field NIP -->
            <div class="form-group" style="margin-bottom: 1.25rem; {{ $isNonAsnInitial ? 'display: none;' : '' }}" id="wrapper-nip">
                <label class="form-label" for="nip" id="label-nip">
                    Nomor Induk Pegawai (NIP 18 Digit) <span style="color: var(--danger);" id="asterisk-nip">*</span>
                </label>
                <input type="text" name="nip" id="nip" maxlength="18" pattern="\d{18}" class="form-control font-mono {{ $nipFormatError ? 'is-invalid' : '' }}" value="{{ old('nip') }}" placeholder="1990xxxxxxxxxxxxxx" required>
                <div class="form-helper" id="helper-nip">Wajib 18 digit angka NIP resmi BKN.</div>
                @if($nipFormatError)
                    <div class="form-error">{{ $nipFormatError }}</div>
                @endif
            </div>

            <!-- 3. Warning NIP/NIK Sudah Terdata -->
            <div id="warning-nik-nip-banner" style="{{ $isDataSudahMasuk ? 'display: block;' : 'display: none;' }} margin-bottom: 1.25rem;">
                <div class="alert-flat alert-warning" style="margin-bottom: 0; display: flex; align-items: center; gap: 0.625rem; font-weight: 600; font-size: 0.875rem;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" style="flex-shrink: 0; color: #b28900;">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                    </svg>
                    <span>NIP/NIK sudah terdata</span>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem;">
                <div class="form-group">
                    <label class="form-label" for="no_hp">Nomor WhatsApp Aktif <span style="color: var(--danger);">*</span></label>
                    <input type="tel" name="no_hp" id="no_hp" class="form-control font-mono @error('no_hp') is-invalid @enderror" value="{{ old('no_hp') }}" placeholder="08xxxxxxxxxx" required>
                    @error('no_hp') <div class="form-error">{{ $message }}</div> @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="skpd_id">Unit Kerja (SKPD) <span style="color: var(--danger);">*</span></label>
                    <select name="skpd_id" id="skpd_id" class="form-select @error('skpd_id') is-invalid @enderror" required>
                        <option value="">-- Pilih Unit Kerja / SKPD --</option>
                        @foreach($skpdList as $skpd)
                            <option value="{{ $skpd->id }}" {{ old('skpd_id') == $skpd->id ? 'selected' : '' }}>
                                {{ $skpd->nama_skpd }}
                            </option>
                        @endforeach
                    </select>
                    @error('skpd_id') <div class="form-error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="form-group" style="margin-top: 1rem;">
                <label class="form-label" for="deskripsi">
                    Deskripsi Penempatan Kerja / Tugas
                    <span style="font-size: 0.75rem; color: var(--ink-muted); font-weight: normal;">(Tambahan Informasi Detail Penempatan Kerja)</span>
                </label>
                <textarea name="deskripsi" id="deskripsi" rows="2" class="form-control @error('deskripsi') is-invalid @enderror" placeholder="Contoh: Puskesmas Kesambi, SDN 1 Kejaksan, RSUD Gunung Jati, Bidang Penataan Ruang, dll. (Diisi manual oleh pemohon)">{{ old('deskripsi') }}</textarea>
                @error('deskripsi') <div class="form-error">{{ $message }}</div> @enderror
                <div class="form-helper">Isi secara manual jika lokasi atau unit penempatan tugas Anda memerlukan informasi detail spesifik di bawah SKPD terkait.</div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; margin-top: 1rem;" id="wrapper-jabatan-golongan">
                <div class="form-group">
                    <label class="form-label" for="jabatan_id">Jabatan ASN</label>
                    <select name="jabatan_id" id="jabatan_id" class="form-select @error('jabatan_id') is-invalid @enderror">
                        <option value="">-- Pilih Jabatan --</option>
                        @foreach($jabatanList as $jabatan)
                            <option value="{{ $jabatan->id }}" {{ old('jabatan_id') == $jabatan->id ? 'selected' : '' }}>
                                {{ $jabatan->nama_label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="golongan_id">Pangkat / Golongan</label>
                    <select name="golongan_id" id="golongan_id" class="form-select @error('golongan_id') is-invalid @enderror">
                        <option value="">-- Pilih Golongan --</option>
                        @foreach($golonganList as $golongan)
                            <option value="{{ $golongan->id }}" {{ old('golongan_id') == $golongan->id ? 'selected' : '' }}>
                                {{ $golongan->nama_label }} ({{ $golongan->kode }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- ── 3. STATUS EMAIL DINAS ───────────────────────── --}}
        <div style="margin-bottom: 2rem;">
            <div class="form-group">
                <div class="radio-tile-group">
                    <div>
                        <input type="radio" name="status_memiliki_email" id="email_status_belum" value="0" class="radio-tile-input" style="display:none;" {{ old('status_memiliki_email', '0') == '0' ? 'checked' : '' }} onchange="toggleEmailOption(false)">
                        <label for="email_status_belum" class="radio-tile-label">
                            <div style="font-weight: 600; font-size: 0.875rem; margin-bottom: 4px;">Belum Memiliki Email Dinas</div>
                            <div style="font-size: 0.75rem; color: var(--ink-secondary);">Sistem akan membuatkan usulan akun email dinas baru resmi secara otomatis.</div>
                        </label>
                    </div>
                    <div>
                        <input type="radio" name="status_memiliki_email" id="email_status_sudah" value="1" class="radio-tile-input" style="display:none;" {{ old('status_memiliki_email') == '1' ? 'checked' : '' }} onchange="toggleEmailOption(true)">
                        <label for="email_status_sudah" class="radio-tile-label">
                            <div style="font-weight: 600; font-size: 0.875rem; margin-bottom: 4px;">Sudah Memiliki Email Dinas</div>
                            <div style="font-size: 0.75rem; color: var(--ink-secondary);">Saya telah memiliki akun email aktif berakhiran @cirebonkota.go.id.</div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Input Email Existing -->
            <div id="wrapper-email-existing" style="display: none; margin-top: 1rem;">
                <label class="form-label" for="email_existing">Alamat Email Dinas Aktif <span style="color: var(--danger);">*</span></label>
                <input type="email" name="email_existing" id="email_existing" class="form-control font-mono @error('email_existing') is-invalid @enderror" value="{{ old('email_existing') }}" placeholder="nama.anda@cirebonkota.go.id">
                <div class="form-helper">Wajib menggunakan domain resmi instansi (@cirebonkota.go.id).</div>
                @error('email_existing') <div class="form-error">{{ $message }}</div> @enderror
            </div>
        </div>

        {{-- ── 4. VERIFIKASI KEAMANAN (Google reCAPTCHA) ─────────────── --}}
        <div style="margin-bottom: 2rem;">
            @if(config('services.recaptcha.site_key'))
                <div class="form-group" style="margin-bottom: 0.75rem;">
                    <div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.site_key') }}"></div>
                    @error('g-recaptcha-response') <div class="form-error">{{ $message }}</div> @enderror
                </div>
            @endif
            @error('captcha') <div class="form-error">{{ $message }}</div> @enderror
        </div>

        <!-- Tombol Submit -->
        <div style="border-top: 1px solid var(--hairline); padding-top: 1.5rem; display: flex; justify-content: flex-end;">
            <button type="submit" class="btn btn-primary btn-lg" id="btn-submit" style="width: 100%; max-width: 300px;">
                Kirim Permohonan TTE
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<script>
    const DRAFT_KEY = 'tte_registration_draft_temp';

    function setTipePegawai(tipe) {
        const wrapperNip = document.getElementById('wrapper-nip');
        const nipInput = document.getElementById('nip');
        const labelAsn = document.getElementById('label_tipe_asn');
        const labelNonAsn = document.getElementById('label_tipe_non_asn');

        if (tipe === 'NON_ASN') {
            if (wrapperNip) wrapperNip.style.display = 'none';
            if (nipInput) {
                nipInput.removeAttribute('required');
                nipInput.removeAttribute('pattern');
                nipInput.value = '';
            }
            
            labelAsn.style.borderColor = 'var(--hairline)';
            labelNonAsn.style.borderColor = 'var(--primary)';
        } else {
            if (wrapperNip) wrapperNip.style.display = 'block';
            if (nipInput) {
                nipInput.setAttribute('required', 'required');
                nipInput.setAttribute('pattern', '\\d{18}');
            }
            
            labelAsn.style.borderColor = 'var(--primary)';
            labelNonAsn.style.borderColor = 'var(--hairline)';
        }
        saveDraft();
    }

    function toggleEmailOption(hasEmail) {
        if (hasEmail) {
            document.getElementById('wrapper-email-existing').style.display = 'block';
        } else {
            document.getElementById('wrapper-email-existing').style.display = 'none';
        }
        saveDraft();
    }

    // SessionStorage Auto-Save & Restore Engine (Aman Tanpa PII NIK/NIP Mentah)
    function saveDraft() {
        const draft = {
            tipe_pegawai: document.querySelector('input[name="tipe_pegawai"]:checked')?.value || 'ASN',
            nama_lengkap: document.getElementById('nama_lengkap').value,
            no_hp: document.getElementById('no_hp').value,
            skpd_id: document.getElementById('skpd_id').value,
            deskripsi: document.getElementById('deskripsi')?.value || '',
            jabatan_id: document.getElementById('jabatan_id').value,
            golongan_id: document.getElementById('golongan_id').value,
            status_memiliki_email: document.querySelector('input[name="status_memiliki_email"]:checked')?.value || '0',
            email_existing: document.getElementById('email_existing').value,
        };
        sessionStorage.setItem(DRAFT_KEY, JSON.stringify(draft));
    }

    function restoreDraft() {
        const raw = sessionStorage.getItem(DRAFT_KEY);
        if (!raw) return;

        try {
            const draft = JSON.parse(raw);
            if (draft.tipe_pegawai && draft.tipe_pegawai === 'NON_ASN') {
                const nonAsnRadio = document.getElementById('tipe_non_asn');
                if (nonAsnRadio && !nonAsnRadio.disabled) {
                    nonAsnRadio.checked = true;
                    setTipePegawai('NON_ASN');
                }
            }
            if (draft.nama_lengkap) document.getElementById('nama_lengkap').value = draft.nama_lengkap;
            if (draft.no_hp) document.getElementById('no_hp').value = draft.no_hp;
            if (draft.skpd_id) {
                document.getElementById('skpd_id').value = draft.skpd_id;
            }
            if (draft.deskripsi && document.getElementById('deskripsi')) {
                document.getElementById('deskripsi').value = draft.deskripsi;
            }
            if (draft.jabatan_id) document.getElementById('jabatan_id').value = draft.jabatan_id;
            if (draft.golongan_id) document.getElementById('golongan_id').value = draft.golongan_id;
            if (draft.email_existing) document.getElementById('email_existing').value = draft.email_existing;

            if (draft.status_memiliki_email === '1') {
                document.getElementById('email_status_sudah').checked = true;
                toggleEmailOption(true);
            } else {
                document.getElementById('email_status_belum').checked = true;
                toggleEmailOption(false);
            }

            document.getElementById('draft-alert').style.display = 'flex';
        } catch (e) {
            console.error('Gagal memulihkan draft:', e);
        }
    }

    function clearDraft() {
        sessionStorage.removeItem(DRAFT_KEY);
        document.getElementById('draft-alert').style.display = 'none';
        document.getElementById('form-pendaftaran').reset();
        setTipePegawai('ASN');
    }

    // Event listeners untuk auto-save
    document.getElementById('nama_lengkap').addEventListener('input', saveDraft);
    document.getElementById('nip').addEventListener('input', saveDraft);
    document.getElementById('no_hp').addEventListener('input', saveDraft);
    document.getElementById('skpd_id').addEventListener('change', saveDraft);
    document.getElementById('deskripsi')?.addEventListener('input', saveDraft);
    document.getElementById('jabatan_id').addEventListener('change', saveDraft);
    document.getElementById('golongan_id').addEventListener('change', saveDraft);

    // Pembersihan draft otomatis saat submit formulir berhasil
    document.getElementById('form-pendaftaran').addEventListener('submit', function () {
        sessionStorage.removeItem(DRAFT_KEY);
    });

    // Sembunyikan warning banner jika pengguna mengetik ulang NIK atau NIP
    const warningBanner = document.getElementById('warning-nik-nip-banner');
    if (warningBanner) {
        document.getElementById('nik')?.addEventListener('input', function () {
            warningBanner.style.display = 'none';
        });
        document.getElementById('nip')?.addEventListener('input', function () {
            warningBanner.style.display = 'none';
        });
    }

    // Inisialisasi awal tipe pegawai dari form
    const currentTipe = document.querySelector('input[name="tipe_pegawai"]:checked')?.value || 'ASN';
    setTipePegawai(currentTipe);

    // Initialize on load
    restoreDraft();

    // Inisialisasi Fitur Pencarian / Autocomplete untuk SKPD, Jabatan, dan Golongan
    if (typeof window.initSearchableCombobox === 'function') {
        window.initSearchableCombobox(document.getElementById('skpd_id'), {
            placeholder: '🔍 Cari Unit Kerja / SKPD...'
        });
        window.initSearchableCombobox(document.getElementById('jabatan_id'), {
            placeholder: '🔍 Cari Jabatan ASN...'
        });
        window.initSearchableCombobox(document.getElementById('golongan_id'), {
            placeholder: '🔍 Cari Pangkat / Golongan...'
        });
    }
</script>
@endpush
