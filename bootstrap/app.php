<?php

use App\Exceptions\Brands\BrandInUseException;
use App\Exceptions\Brands\DuplicateBrandException;
use App\Exceptions\Categories\CategoryInUseException;
use App\Exceptions\Categories\DuplicateCategoryException;
use App\Exceptions\DuplicateUnitException;
use App\Exceptions\Kasir\PosSessionAlreadyActiveException;
use App\Exceptions\Kasir\PosSessionAlreadyClosedException;
use App\Exceptions\UnitInUseException;
use App\Exceptions\Warehouses\WarehouseCodeDuplicateException;
use App\Exceptions\Warehouses\WarehouseInUseException;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureRegistrationIsClosed;
use App\Http\Middleware\EnsureStoreScope;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            'role' => CheckRole::class,
            'registration.closed' => EnsureRegistrationIsClosed::class,
            'store.scope' => EnsureStoreScope::class,
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->header('X-Inertia')) {
                return back()->withErrors([
                    'email' => 'Terlalu banyak percobaan. Silakan coba lagi beberapa saat lagi.',
                ]);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'code' => 'ERR-AUTH-01',
                    'message' => 'Anda harus login untuk mengakses resource ini.',
                ], 401);
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'code' => 'ERR-FORBIDDEN-01',
                    'message' => 'Anda tidak memiliki akses untuk melakukan aksi ini.',
                ], 403);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'code' => 'ERR-VALIDATION-01',
                    'message' => 'Data yang dikirim tidak valid.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'code' => 'ERR-UNIT-NOT-FOUND',
                    'message' => 'Satuan tidak ditemukan.',
                ], 404);
            }
        });

        $exceptions->render(function (DuplicateUnitException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'code' => 'ERR-DUPLICATE-01',
                    'message' => $e->getMessage(),
                ], 409);
            }
        });

        $exceptions->render(function (UnitInUseException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'code' => 'ERR-UNIT-IN-USE-01',
                    'message' => $e->getMessage(),
                ], 409);
            }
        });

        $exceptions->render(function (DuplicateCategoryException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'code' => 'ERR-DUPLICATE-01',
                    'message' => $e->getMessage(),
                ], 409);
            }
        });

        $exceptions->render(function (CategoryInUseException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'code' => 'ERR-CATEGORY-IN-USE-01',
                    'message' => $e->getMessage(),
                ], 409);
            }
        });

        $exceptions->render(function (DuplicateBrandException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'code' => 'ERR-DUPLICATE-01',
                    'message' => $e->getMessage(),
                ], 409);
            }
        });

        $exceptions->render(function (BrandInUseException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'code' => 'ERR-BRAND-IN-USE-01',
                    'message' => $e->getMessage(),
                ], 409);
            }
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') && ! app()->hasDebugModeEnabled()) {
                return response()->json([
                    'success' => false,
                    'code' => 'ERR-SERVER-01',
                    'message' => 'Terjadi kesalahan pada server. Silakan coba lagi nanti.',
                ], 500);
            }
        });
        $exceptions->render(function (WarehouseCodeDuplicateException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'code' => 'ERR-WAREHOUSE-CODE-DUPLICATE',
                    'message' => $e->getMessage(),
                ], 409);
            }
        });

        $exceptions->render(function (WarehouseInUseException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'code' => 'ERR-WAREHOUSE-IN-USE-01',
                    'message' => $e->getMessage(),
                ], 409);
            }
        });

        $exceptions->render(function (PosSessionAlreadyActiveException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => (object) [],
            ], 409);
        });

        $exceptions->render(function (PosSessionAlreadyClosedException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => (object) [],
            ], 409);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })
    ->create();
