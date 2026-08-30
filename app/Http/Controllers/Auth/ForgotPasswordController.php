<?php

// app/Http/Controllers/Auth/ForgotPasswordController.php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Services\PasswordResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ForgotPasswordController extends Controller
{
    public function __construct(
        protected PasswordResetService $passwordResetService
    ) {}

    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    public function sendResetLink(ForgotPasswordRequest $request): RedirectResponse
    {
        $this->passwordResetService->sendResetLink($request->input('email'));

        // Pesan generik — tidak bocorkan status akun (AC-03)
        return back()->with('status', 'Jika akun terdaftar, instruksi reset password telah dikirim ke email Anda.');
    }

    public function showResetForm(Request $request): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $request->query('token'),
            'email' => $request->query('email'),
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): RedirectResponse
    {
        $success = $this->passwordResetService->resetPassword(
            $request->input('email'),
            $request->input('token'),
            $request->input('password')
        );

        if (! $success) {
            return back()->withErrors([
                'email' => 'Token reset password tidak valid atau sudah kedaluwarsa.',
            ]);
        }

        return redirect()->route('login')
            ->with('status', 'Password berhasil diperbarui. Silakan login dengan password baru Anda.');
    }
}
