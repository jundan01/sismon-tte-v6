<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\Security\TotpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller Autentikasi Superadmin IAM dengan Progressive Lockout.
 * Ref: SECURITY.md §6.1–6.2, ARCHITECTURE.md §2
 */
class AuthController extends Controller
{
    public const MAX_LOGIN_ATTEMPTS = 5;
    public const DECAY_SECONDS = 300; // 5 Menit Lockout

    /**
     * Tampilkan formulir login Superadmin.
     */
    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('auth.login');
    }

    /**
     * Proses autentikasi masuk (Login) dengan proteksi lockout progresif.
     */
    public function login(LoginRequest $request): JsonResponse|Response
    {
        $throttleKey = $this->throttleKey($request);

        // Periksa apakah IP/Email terkena lockout
        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => "Terlalu banyak percobaan login gagal. Akun dikunci sementara selama {$seconds} detik.",
                    'locked'  => true,
                    'retry_after' => $seconds,
                ], 429)->header('Retry-After', (string)$seconds);
            }

            return back()->withErrors([
                'email' => "Terlalu banyak percobaan login gagal. Akun dikunci sementara selama {$seconds} detik.",
            ])->withInput($request->only('email'));
        }

        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember');

        if (!Auth::attempt($credentials, $remember)) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            $attemptsLeft = self::MAX_LOGIN_ATTEMPTS - RateLimiter::attempts($throttleKey);

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Login Gagal',
                    'attempts_left' => max(0, $attemptsLeft),
                ], 401);
            }

            return back()->withErrors([
                'email' => 'Login Gagal',
            ])->withInput($request->only('email'));
        }

        /** @var User $user */
        $user = Auth::user();

        // 1. Verifikasi Tantangan Multi-Factor Authentication (MFA / TOTP)
        if ($user->is_mfa_enabled) {
            Auth::logout(); // Tangguhkan pembuatan sesi admin penuh

            $request->session()->put('mfa:pending_user_id', $user->id);
            $request->session()->put('mfa:remember', $remember);

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success'      => true,
                    'mfa_required' => true,
                    'message'      => 'Kredensial valid. Silakan masukkan 6-digit kode TOTP Authenticator Anda.',
                    'mfa_url'      => route('gateway.login.mfa', [], false),
                ]);
            }

            return redirect()->route('gateway.login.mfa_prompt');
        }

        // 2. Alur Login Penuh jika MFA belum diaktifkan
        RateLimiter::clear($throttleKey);

        // Regenerasi session ID untuk mencegah Session Fixation
        $request->session()->regenerate();

        // Update catatan jejak login terakhir
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => 'Autentikasi berhasil.',
                'user' => [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                    'role'  => $user->role,
                    'is_mfa_enabled' => $user->is_mfa_enabled,
                ],
                'redirect_url' => route('admin.dashboard', [], false) ?: '/admin/dashboard',
            ]);
        }

        return redirect()->intended('/admin/dashboard');
    }

    /**
     * Tampilkan formulir tantangan input kode TOTP MFA.
     */
    public function showMfaForm(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('mfa:pending_user_id')) {
            return redirect()->route('login');
        }

        return view('auth.login', ['mfaPrompt' => true]);
    }

    /**
     * Verifikasi kode 6-digit TOTP atau Recovery Code saat proses login Superadmin.
     */
    public function verifyMfa(Request $request, TotpService $totpService): JsonResponse|RedirectResponse
    {
        $userId = $request->session()->get('mfa:pending_user_id');
        if (!$userId) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sesi tantangan MFA telah kedaluwarsa. Silakan login kembali.',
                    'redirect_url' => route('login', [], false),
                ], 401);
            }
            return redirect()->route('login')->withErrors(['email' => 'Sesi MFA telah berakhir. Silakan login kembali.']);
        }

        $request->validate([
            'code' => ['required', 'string', 'min:6', 'max:20'],
        ], [
            'code.required' => 'Kode OTP atau kode pemulihan wajib diisi.',
            'code.min'      => 'Panjang kode minimal 6 karakter.',
        ]);

        $code = trim((string) $request->input('code'));
        $user = User::findOrFail($userId);

        // Uji kode 6 digit TOTP
        $isValidOtp = $user->mfa_secret && $totpService->verifyCode($user->mfa_secret, $code);
        $isRecoveryUsed = false;

        // Jika bukan OTP 6-digit, uji cadangan kode pemulihan (Recovery Code)
        if (!$isValidOtp && $user->mfa_recovery_codes) {
            $remainingCodes = $totpService->verifyRecoveryCode($user->mfa_recovery_codes, $code);
            if ($remainingCodes !== null) {
                $user->update([
                    'mfa_recovery_codes' => json_encode($remainingCodes),
                ]);
                $isValidOtp = true;
                $isRecoveryUsed = true;
            }
        }

        if (!$isValidOtp) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kode OTP atau kode pemulihan tidak valid atau telah kedaluwarsa.',
                ], 422);
            }
            return back()->withErrors(['code' => 'Kode OTP atau kode pemulihan tidak valid.']);
        }

        // Verifikasi Sukses -> Login Penuh
        $remember = (bool) $request->session()->get('mfa:remember', false);
        $request->session()->forget(['mfa:pending_user_id', 'mfa:remember']);

        Auth::login($user, $remember);
        $request->session()->regenerate();

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        $throttleKey = $this->throttleKey($request);
        RateLimiter::clear($throttleKey);

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success'      => true,
                'message'      => $isRecoveryUsed ? 'Autentikasi kode darurat berhasil. 1 kode pemulihan telah terpakai.' : 'Autentikasi MFA berhasil.',
                'redirect_url' => route('admin.dashboard', [], false) ?: '/admin/dashboard',
            ]);
        }

        return redirect()->intended('/admin/dashboard');
    }

    /**
     * Proses keluar sistem (Logout) dan regenerasi token keamanan.
     */
    public function logout(Request $request): JsonResponse|Response
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => 'Sesi berhasil diakhiri.',
            ]);
        }

        return redirect()->route('login');
    }

    /**
     * Registrasi akun Superadmin internal.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name'           => $request->name,
            'email'          => $request->email,
            'password'       => $request->password, // Di-hash otomatis oleh model cast
            'role'           => 'superadmin',
            'is_mfa_enabled' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Akun Superadmin berhasil didaftarkan.',
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ],
        ], 201);
    }

    /**
     * Cek status sesi & informasi profil Superadmin yang sedang aktif.
     */
    public function me(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'authenticated' => false,
                'user' => null,
            ], 401);
        }

        return response()->json([
            'authenticated' => true,
            'user' => [
                'id'             => $user->id,
                'name'           => $user->name,
                'email'          => $user->email,
                'role'           => $user->role,
                'is_mfa_enabled' => $user->is_mfa_enabled,
                'last_login_at'  => $user->last_login_at?->toIso8601String(),
                'last_login_ip'  => $user->last_login_ip,
            ],
        ]);
    }

    /**
     * Refresh CSRF token dan status sesi.
     */
    public function refreshToken(Request $request): JsonResponse
    {
        return response()->json([
            'success'    => true,
            'csrf_token' => csrf_token(),
            'session_id' => $request->session()->getId(),
        ]);
    }

    /**
     * Bangun kunci unik throttling berdasarkan kombinasi IP dan email.
     */
    protected function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower($request->input('email')) . '|' . $request->ip());
    }
}
