<?php

// app/Services/PasswordResetService.php

namespace App\Services;

use App\Models\PasswordResetToken;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordResetService
{
    /**
     * Masa berlaku token reset (menit)
     */
    protected int $tokenExpiryMinutes = 60;

    /**
     * Kirim link/kode reset password.
     * Selalu return true secara "logika luar", terlepas dari apakah
     * akun ditemukan atau tidak — mencegah user enumeration (AC-03).
     */
    public function sendResetLink(string $identifier): void
    {
        $user = $this->findUserByIdentifier($identifier);

        // Jika user tidak ditemukan, diam-diam berhenti di sini.
        // Controller tetap mengembalikan response sukses generik.
        if (! $user) {
            return;
        }

        // Hindari spam: hapus token lama yang belum dipakai untuk email ini
        PasswordResetToken::where('email', $user->email)
            ->where('is_used', false)
            ->delete();

        $rawToken = Str::random(64);

        PasswordResetToken::create([
            'email' => $user->email,
            'token' => Hash::make($rawToken),
            'is_used' => false,
            'expires_at' => now()->addMinutes($this->tokenExpiryMinutes),
        ]);

        // Kirim notifikasi berisi rawToken (bukan hashed token)
        $user->notify(new ResetPasswordNotification($rawToken, $user->email));
    }

    /**
     * Validasi token reset (dipakai saat user membuka link/masukkan kode).
     */
    public function verifyToken(string $email, string $token): bool
    {
        $record = $this->getValidTokenRecord($email, $token);

        return $record !== null;
    }

    /**
     * Set password baru menggunakan token valid.
     * Return true jika berhasil, false jika token invalid/expired.
     */
    public function resetPassword(string $email, string $token, string $newPassword): bool
    {
        return DB::transaction(function () use ($email, $token, $newPassword) {
            $record = $this->getValidTokenRecord($email, $token, forUpdate: true);

            if (! $record) {
                return false;
            }

            $user = User::where('email', $email)->first();

            if (! $user) {
                return false;
            }

            $user->forceFill([
                'password' => Hash::make($newPassword),
            ])->save();

            // Tandai token yang dipakai sebagai used
            $record->update(['is_used' => true]);

            // Invalidate SEMUA token reset milik akun ini (bukan hanya yang dipakai)
            PasswordResetToken::where('email', $email)
                ->where('is_used', false)
                ->update(['is_used' => true]);

            return true;
        });
    }

    /**
     * Cari record token valid (belum used & belum expired) yang cocok
     * dengan token mentah yang dikirim user.
     */
    protected function getValidTokenRecord(string $email, string $rawToken, bool $forUpdate = false): ?PasswordResetToken
    {
        $query = PasswordResetToken::validTokens()
            ->where('email', $email)
            ->latest('id');

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        $candidates = $query->get();

        foreach ($candidates as $candidate) {
            if (Hash::check($rawToken, $candidate->token)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Cari user berdasarkan email ATAU username.
     * Sesuaikan kolom 'username' dengan struktur tabel users Anda.
     */
    protected function findUserByIdentifier(string $identifier): ?User
    {
        return User::query()
            ->whereNull('deleted_at')
            ->where(function ($query) use ($identifier): void {
                $query->where('email', $identifier)
                    ->orWhere('name', $identifier);
            })
            ->first();
    }
}
