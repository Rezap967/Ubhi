<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\JournalEntrySearchRequest;
use App\Http\Resources\Accounting\JournalEntryResource;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\JournalEntryService;
use Illuminate\Http\JsonResponse;

class JournalEntrySearchController extends Controller
{
    public function __construct(protected JournalEntryService $service) {}

    public function __invoke(JournalEntrySearchRequest $request): JsonResponse
    {
        $this->authorize('viewAny', JournalEntry::class);
        $journals = $this->service->search(active_store_id(), $request->validated(), (int) ($request->validated()['limit'] ?? 20));

        return response()->json([
            'success' => true,
            'message' => 'Data jurnal berhasil dimuat.',
            'data' => JournalEntryResource::collection($journals)->resolve($request),
        ]);
    }
}
