<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function showLoginForm(): Response
    {
        return Inertia::render('Auth/Login', [
            'status' => session('status'),
            'error' => session('error'),
            'canResetPassword' => true,
        ]);
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $identity = $request->input('identity', $request->input('login'));

        $user = User::query()
            ->with('role')
            ->whereNull('deleted_at')
            ->where(function ($query) use ($identity): void {
                $query->where('email', $identity)
                    ->orWhere('name', $identity);
            })
            ->first();

        if (! $user) {
            return back()
                ->withErrors(['identity' => 'Akun tidak ditemukan.'])
                ->withInput();
        }

        if (! $user->is_active) {
            return back()
                ->withErrors(['identity' => 'Akun Anda sedang nonaktif.'])
                ->withInput();
        }

        if (! Hash::check($request->password, $user->password)) {
            return back()
                ->withErrors(['password' => 'Kata sandi salah.'])
                ->withInput();
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function adminResetPassword(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $actor = $request->user();

        abort_unless($actor && $actor->role && $actor->role->name === 'owner', 403);
        abort_unless(in_array($user->role?->name, ['kasir', 'petugas_gudang'], true), 403);

        $user->forceFill([
            'password' => Hash::make($request->password),
        ])->save();

        return back()->with('status', 'Password staf berhasil direset oleh owner.');
    }
}
