<?php

// app/Http/Middleware/EnsureRegistrationIsClosed.php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRegistrationIsClosed
{
    public function handle(Request $request, Closure $next): Response
    {
        $ownerRole = Role::query()->where('name', 'owner')->first();

        $ownerAlreadyExists = $ownerRole
            ? User::query()->where('role_id', $ownerRole->id)->whereNull('deleted_at')->exists()
            : false;

        if ($ownerAlreadyExists) {
            return redirect()->route('login')
                ->with('error', 'Pendaftaran sudah ditutup. Aplikasi ini hanya untuk satu akun Owner.');
        }

        return $next($request);
    }
}
