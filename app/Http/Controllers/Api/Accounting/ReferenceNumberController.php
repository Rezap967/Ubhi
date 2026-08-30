<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\ReferenceNumberRequest;
use App\Services\Accounting\JournalNumberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class ReferenceNumberController extends Controller
{
    public function __construct(protected JournalNumberService $numberService) {}

    public function __invoke(ReferenceNumberRequest $request): JsonResponse
    {
        $date = isset($request->validated()['date'])
            ? Carbon::parse($request->validated()['date'])
            : null;

        return response()->json([
            'success' => true,
            'message' => 'Nomor referensi berhasil dibuat.',
            'data' => [
                'reference_no' => $this->numberService->generate(active_store_id(), $date),
            ],
        ]);
    }
}
