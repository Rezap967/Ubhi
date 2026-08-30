<?php

// app/Http/Controllers/Auth/RegisterOwnerController.php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterOwnerRequest;
use App\Models\Role;
use App\Models\Settings\UserProfile;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterOwnerController extends Controller
{
    public function register(RegisterOwnerRequest $request): RedirectResponse
    {
        $ownerRole = Role::query()->where('name', 'owner')->first();

        if (! $ownerRole) {
            report(new \RuntimeException('Role "owner" tidak ditemukan. Pastikan RoleSeeder sudah dijalankan.'));

            return back()
                ->withErrors(['email' => 'Pendaftaran gagal karena kesalahan konfigurasi sistem.'])
                ->withInput();
        }

        return DB::transaction(function () use ($request, $ownerRole): RedirectResponse {
            // Lock row untuk cegah race condition — 2 request register bersamaan
            $ownerExists = User::query()
                ->where('role_id', $ownerRole->id)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->exists();

            if ($ownerExists) {
                return redirect()->route('login')
                    ->with('error', 'Pendaftaran sudah ditutup. Owner sudah terdaftar.');
            }

            $user = User::query()->create([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'password' => Hash::make($request->input('password')),
                'role_id' => $ownerRole->id,
                'created_by' => null,
                'is_active' => 1,
            ]);

            Store::query()->create([
                'owner_id' => $user->id,
                'code' => 'STR-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
                'name' => $request->input('store_name'),
            ]);

            $store = Store::query()->where('owner_id', $user->id)->first();
            UserProfile::query()->create([
                'user_id' => $user->id,
                'store_id' => $store->id,
                'full_name' => $request->input('name'),
            ]);

            return redirect()->route('login')
                ->with('status', 'Pendaftaran berhasil! Silakan login dengan akun Anda.');
        });
    }
}
