<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Superadmin — Sistem Monitoring TTE & Email Dinas</title>
    <style>
        :root {
            --bg-canvas: #f4f4f4;
            --bg-surface: #ffffff;
            --text-primary: #161616;
            --text-secondary: #525252;
            --border-subtle: #e0e0e0;
            --border-strong: #8d8d8d;
            --interactive: #0f62fe;
            --danger: #da1e28;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            border-radius: 0 !important;
        }

        body {
            font-family: 'IBM Plex Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--bg-canvas);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .login-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-top: 4px solid var(--interactive);
            width: 100%;
            max-width: 440px;
            padding: 2.5rem 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .app-badge {
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }

        .login-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            letter-spacing: -0.02em;
        }

        .login-subtitle {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-size: 0.8125rem;
            font-weight: 600;
            margin-bottom: 0.375rem;
            color: var(--text-primary);
        }

        .form-input {
            width: 100%;
            height: 44px;
            padding: 0.5rem 0.875rem;
            font-size: 0.875rem;
            background: #ffffff;
            border: 1px solid var(--border-strong);
            color: var(--text-primary);
            outline: none;
            transition: border-color 0.15s ease;
        }

        .form-input:focus {
            border-color: var(--interactive);
            box-shadow: inset 0 0 0 1px var(--interactive);
        }

        .btn-submit {
            width: 100%;
            height: 48px;
            background: var(--interactive);
            color: #ffffff;
            font-size: 0.875rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s ease;
        }

        .btn-submit:hover {
            background: #0353e9;
        }

        .alert-box {
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.8125rem;
            line-height: 1.4;
            border-left: 3px solid var(--danger);
            background: #fff1f1;
            color: #750e13;
        }
    </style>
</head>

<body>
    <div class="login-card">
        <div class="app-badge">Pemerintah Kota Cirebon</div>
        <h1 class="login-title" id="login-title">{{ ($mfaPrompt ?? false) ? 'Verifikasi Dua Langkah' : 'Masuk Superadmin' }}</h1>
        <p class="login-subtitle" id="login-subtitle">
            {{ ($mfaPrompt ?? false) ? 'Masukkan 6 digit kode dari aplikasi authenticator Anda atau Recovery Code.' : 'Sistem Monitoring TTE & Pelayanan Email Dinas' }}
        </p>

        <div
            style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: #e8f5e9; border: 1px solid #24a148; color: #0e6027; font-size: 0.75rem; font-weight: 600; margin-bottom: 1.5rem; letter-spacing: 0.02em;">
            <span style="display: inline-block; width: 8px; height: 8px; background: #24a148;"></span>
            JARINGAN TERVERIFIKASI
        </div>

        <div id="login-alert" class="alert-box" style="{{ $errors->any() ? '' : 'display: none;' }}">
            @if ($errors->any())
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            @endif
        </div>

        <!-- Section 1: Formulir Kredensial Email & Password -->
        <div id="login-credentials-section" style="{{ ($mfaPrompt ?? false) ? 'display: none;' : 'display: block;' }}">
            <form id="login-form" action="{{ route('login', [], false) }}" method="POST">
                @csrf
                <div class="form-group">
                    <label for="email" class="form-label">Email Akun Superadmin</label>
                    <input type="email" id="email" name="email" class="form-input" value="{{ old('email') }}"
                        placeholder="xxxxxxxxxxx@gmail.com" required {{ !($mfaPrompt ?? false) ? 'autofocus' : '' }} autocomplete="email">
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Kata Sandi</label>
                    <input type="password" id="password" name="password" class="form-input"
                        placeholder="Masukkan kata sandi..." required autocomplete="current-password">
                </div>

                <button type="submit" id="btn-submit" class="btn-submit">
                    <span>Masuk ke Panel Kontrol</span>
                </button>
            </form>
        </div>

        <!-- Section 2: Formulir Tantangan MFA TOTP -->
        <div id="login-mfa-section" style="{{ ($mfaPrompt ?? false) ? 'display: block;' : 'display: none;' }}">
            <form id="mfa-form" action="{{ route('gateway.login.mfa', [], false) }}" method="POST">
                @csrf
                <div class="form-group">
                    <label for="totp_code" class="form-label">Kode Keamanan Authenticator</label>
                    <input type="text" id="totp_code" name="totp_code" class="form-input"
                        placeholder="6 digit angka (contoh: 123456) atau Kode Cadangan" required {{ ($mfaPrompt ?? false) ? 'autofocus' : '' }}
                        autocomplete="one-time-code" maxlength="32" style="font-family: monospace; letter-spacing: 0.1em; font-size: 1rem;">
                    <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 6px;">
                        Buka Google Authenticator / Aegis Anda. Anda juga dapat memasukkan salah satu Recovery Code darurat.
                    </div>
                </div>

                <button type="submit" id="btn-mfa-submit" class="btn-submit">
                    <span>Verifikasi & Masuk</span>
                </button>

                <div style="margin-top: 1.25rem; text-align: center;">
                    <a href="{{ route('login', [], false) }}" style="font-size: 0.8125rem; color: var(--interactive); text-decoration: none;">
                        &larr; Batalkan & Kembali ke Login
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Handler Form Kredensial
        document.getElementById('login-form').addEventListener('submit', async function (e) {
            e.preventDefault();
            const form = this;
            const btn = document.getElementById('btn-submit');
            const alertBox = document.getElementById('login-alert');
            const originalBtnHtml = btn.innerHTML;

            alertBox.style.display = 'none';
            alertBox.innerHTML = '';
            btn.disabled = true;
            btn.innerHTML = '<span>Memverifikasi Kredensial...</span>';

            const formData = new FormData(form);
            const tokenInput = form.querySelector('input[name="_token"]');
            const tokenVal = tokenInput ? tokenInput.value : '';

            try {
                const response = await fetch(form.getAttribute('action') || form.action, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': tokenVal
                    }
                });

                if (response.status === 419) {
                    btn.disabled = false;
                    btn.innerHTML = originalBtnHtml;
                    alertBox.replaceChildren();
                    const errP = document.createElement('p');
                    errP.textContent = 'Sesi keamanan telah kedaluwarsa (CSRF mismatch). Memuat ulang halaman untuk memperbarui token...';
                    alertBox.appendChild(errP);
                    alertBox.style.display = 'block';
                    setTimeout(() => window.location.reload(), 1500);
                    return;
                }

                const data = await response.json();

                if (response.ok && data.success) {
                    if (data.mfa_required) {
                        // Alihkan tampilan ke langkah MFA TOTP
                        document.getElementById('login-credentials-section').style.display = 'none';
                        document.getElementById('login-mfa-section').style.display = 'block';
                        document.getElementById('login-title').textContent = 'Verifikasi Dua Langkah';
                        document.getElementById('login-subtitle').textContent = 'Masukkan 6 digit kode dari aplikasi authenticator Anda.';
                        btn.disabled = false;
                        btn.innerHTML = originalBtnHtml;
                        const totpInput = document.getElementById('totp_code');
                        if (totpInput) {
                            totpInput.focus();
                        }
                        return;
                    }

                    btn.innerHTML = '<span>Autentikasi Berhasil! Mengalihkan...</span>';
                    btn.style.background = '#24a148';
                    setTimeout(() => {
                        window.location.href = data.redirect_url || '/admin/dashboard';
                    }, 400);
                } else {
                    btn.disabled = false;
                    btn.innerHTML = originalBtnHtml;
                    alertBox.replaceChildren();
                    const errP = document.createElement('p');
                    errP.textContent = data.message || 'Login Gagal';
                    alertBox.appendChild(errP);
                    alertBox.style.display = 'block';
                }
            } catch (err) {
                // Fallback standard submit jika terjadi masalah jaringan
                form.submit();
            }
        });

        // Handler Form MFA TOTP
        document.getElementById('mfa-form').addEventListener('submit', async function (e) {
            e.preventDefault();
            const form = this;
            const btn = document.getElementById('btn-mfa-submit');
            const alertBox = document.getElementById('login-alert');
            const originalBtnHtml = btn.innerHTML;

            alertBox.style.display = 'none';
            alertBox.innerHTML = '';
            btn.disabled = true;
            btn.innerHTML = '<span>Memvalidasi Kode TOTP...</span>';

            const formData = new FormData(form);
            const tokenInput = form.querySelector('input[name="_token"]');
            const tokenVal = tokenInput ? tokenInput.value : '';

            try {
                const response = await fetch(form.getAttribute('action') || form.action, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': tokenVal
                    }
                });

                if (response.status === 419) {
                    btn.disabled = false;
                    btn.innerHTML = originalBtnHtml;
                    alertBox.replaceChildren();
                    const errP = document.createElement('p');
                    errP.textContent = 'Sesi keamanan telah kedaluwarsa (CSRF mismatch). Memuat ulang halaman untuk memperbarui token...';
                    alertBox.appendChild(errP);
                    alertBox.style.display = 'block';
                    setTimeout(() => window.location.reload(), 1500);
                    return;
                }

                const data = await response.json();

                if (response.ok && data.success) {
                    btn.innerHTML = '<span>Verifikasi Berhasil! Mengalihkan...</span>';
                    btn.style.background = '#24a148';
                    setTimeout(() => {
                        window.location.href = data.redirect_url || '/admin/dashboard';
                    }, 400);
                } else {
                    btn.disabled = false;
                    btn.innerHTML = originalBtnHtml;
                    alertBox.replaceChildren();
                    const errP = document.createElement('p');
                    errP.textContent = data.message || 'Kode TOTP atau Recovery Code tidak valid.';
                    alertBox.appendChild(errP);
                    alertBox.style.display = 'block';
                }
            } catch (err) {
                form.submit();
            }
        });
    </script>
</body>

</html>