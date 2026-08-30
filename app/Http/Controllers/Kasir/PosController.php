<?php

namespace App\Http\Controllers\Kasir;

use App\Exceptions\Kasir\PosSessionAlreadyActiveException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kasir\ClosePosSessionRequest;
use App\Http\Requests\Kasir\CurrentPosSessionRequest;
use App\Http\Requests\Kasir\OpenPosSessionRequest;
use App\Http\Resources\Kasir\PosCartResource;
use App\Http\Resources\Kasir\PosSessionResource;
use App\Models\Kasir\PosSession;
use App\Services\Kasir\PosSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PosController extends Controller
{
    public function __construct(
        protected PosSessionService $posSessionService,
    ) {}

    /**
     * Load halaman POS via Inertia.
     * Endpoint: GET /api/v1/pos
     */
    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();
        $deviceId = $request->query('device_id');

        $session = null;
        $cart = null;

        if ($deviceId) {
            $session = $this->posSessionService->getCurrentSession($user, $deviceId);
            $cart = $session?->activeCart;
        }

        return Inertia::render('Kasir/POS/Index', [
            'session' => $session ? new PosSessionResource($session) : null,
            'cart' => $cart ? new PosCartResource($cart) : null,
            'summary' => [
                'transaction_count' => 0,
                'gross_revenue' => 0,
                'net_revenue' => 0,
            ],
        ]);
    }

    /**
     * Mengambil sesi POS aktif milik user login.
     * Endpoint: GET /api/v1/pos/sessions/current
     */
    public function currentSession(CurrentPosSessionRequest $request): JsonResponse
    {
        $session = $this->posSessionService->getCurrentSession(
            user: $request->user(),
            deviceId: $request->validated('device_id'),
        );

        return response()->json([
            'success' => true,
            'message' => $session ? 'Sesi kasir aktif ditemukan.' : 'Tidak ada sesi kasir aktif.',
            'data' => [
                'session' => $session ? new PosSessionResource($session) : null,
            ],
        ]);
    }

    /**
     * Membuka sesi kasir baru.
     * Endpoint: POST /api/v1/pos/sessions/open
     */
    public function openSession(OpenPosSessionRequest $request): JsonResponse
    {
        try {
            $session = $this->posSessionService->openSession(
                user: $request->user(),
                data: $request->validated(),
            );

            return response()->json([
                'success' => true,
                'message' => 'Sesi kasir berhasil dibuka.',
                'data' => [
                    'session_id' => $session->id,
                    'status' => $session->status,
                    'opening_cash' => (float) $session->opening_cash,
                    'opened_at' => $session->opened_at?->toIso8601String(),
                    'cart' => $session->activeCart ? new PosCartResource($session->activeCart) : null,
                ],
            ], 201);
        } catch (PosSessionAlreadyActiveException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => (object) [],
            ], 409);
        }
    }

    /**
     * Menutup sesi kasir.
     * Endpoint: PATCH /api/v1/pos/sessions/{session}/close
     */
    public function closeSession(ClosePosSessionRequest $request, PosSession $session): JsonResponse
    {
        try {
            $closedSession = $this->posSessionService->closeSession(
                user: $request->user(),
                session: $session,
                data: $request->validated(),
            );

            return response()->json([
                'success' => true,
                'message' => 'Sesi kasir berhasil ditutup.',
                'data' => [
                    'session_id' => $closedSession->id,
                    'status' => $closedSession->status,
                    'opening_cash' => (float) $closedSession->opening_cash,
                    'closing_cash' => (float) $closedSession->closing_cash,
                    'expected_cash' => (float) $closedSession->expected_cash,
                    'cash_difference' => (float) $closedSession->cash_difference,
                    'closed_at' => $closedSession->closed_at?->toIso8601String(),
                ],
            ]);
        } catch (HttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => (object) [],
            ], $e->getStatusCode());
        }
    }
}
