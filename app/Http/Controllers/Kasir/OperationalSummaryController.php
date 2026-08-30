<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kasir\AllSessionsSummaryRequest;
use App\Http\Requests\Kasir\OperationalSummaryRequest;
use App\Http\Resources\Kasir\OperationalSummaryResource;
use App\Services\Kasir\OperationalSummaryService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OperationalSummaryController extends Controller
{
    public function __construct(
        protected OperationalSummaryService $summaryService
    ) {}

    public function footer(OperationalSummaryRequest $request): JsonResponse
    {
        try {
            $summary = $this->summaryService->getFooterSummary(
                $request->user(),
                $request->input('device_id')
            );

            return response()->json([
                'success' => true,
                'message' => 'Ringkasan operasional berhasil dimuat.',
                'data' => new OperationalSummaryResource($summary),
            ]);
        } catch (HttpException $e) {
            return $this->httpError($e);
        }
    }

    public function session(OperationalSummaryRequest $request): JsonResponse
    {
        try {
            $summary = $this->summaryService->getSessionSummary(
                $request->user(),
                $request->input('device_id')
            );

            return response()->json([
                'success' => true,
                'message' => 'Ringkasan sesi berhasil dimuat.',
                'data' => new OperationalSummaryResource($summary),
            ]);
        } catch (HttpException $e) {
            return $this->httpError($e);
        }
    }

    public function allSessions(AllSessionsSummaryRequest $request): JsonResponse
    {
        try {
            $summary = $this->summaryService->getAllSessionsSummary(
                $request->user(),
                $request->validated()
            );

            return response()->json([
                'success' => true,
                'message' => 'Ringkasan semua sesi berhasil dimuat.',
                'data' => new OperationalSummaryResource($summary),
            ]);
        } catch (HttpException $e) {
            return $this->httpError($e);
        }
    }

    private function httpError(HttpException $e): JsonResponse
    {
        $message = $e->getMessage();
        $errors = (object) [];

        $decoded = json_decode($message, true);
        if (is_array($decoded)) {
            $message = $decoded['message'] ?? $message;
            unset($decoded['message']);
            $errors = (object) $decoded;
        }

        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $e->getStatusCode());
    }
}
