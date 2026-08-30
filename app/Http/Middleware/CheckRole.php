<?php

// app/Http/Middleware/CheckRole.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Contoh pemakaian di route:
     * ->middleware('role')             // Semua role yang terautentikasi
     * ->middleware('role:owner')       // Khusus owner
     * ->middleware('role:owner,kasir') // Multiple role dipisah koma
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->role) {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak memiliki role yang valid.',
                'data' => null,
            ], 403);
        }

        // Ambil nama role (Aman jika $user->role berupa String atau Object Relasi)
        $userRoleName = is_object($user->role) ? $user->role->name : $user->role;

        // 1. SUPER PRIVILEGE: Jika role adalah 'owner', LANGSUNG LOLOSKAN!
        if (strtolower($userRoleName) === 'owner') {
            return $next($request);
        }

        // 2. Jika middleware dipanggil tanpa parameter (misal: 'role'),
        // dan user sudah punya role valid, izinkan masuk.
        if (empty($roles)) {
            return $next($request);
        }

        // 3. Cek apakah role user ada di dalam daftar $roles yang diizinkan
        if (! in_array($userRoleName, $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk melakukan aksi ini.',
                'data' => null,
            ], 403);
        }

        return $next($request);
    }
}
