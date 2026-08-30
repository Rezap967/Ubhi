<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStoreScope
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->getStoreId()) {
            return response()->json([
                'success' => false,
                'code' => 'ERR-STORE-SCOPE-01',
                'message' => 'Toko aktif tidak ditemukan untuk akun ini.',
                'errors' => (object) [],
            ], 403);
        }

        return $next($request);
    }
}
