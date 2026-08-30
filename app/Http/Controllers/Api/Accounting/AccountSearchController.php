<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\AccountSearchRequest;
use App\Http\Resources\Accounting\AccountResource;
use App\Models\Accounting\Account;
use App\Services\Accounting\AccountService;
use Illuminate\Http\JsonResponse;

class AccountSearchController extends Controller
{
    public function __construct(protected AccountService $service) {}

    public function __invoke(AccountSearchRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Account::class);

        $accounts = $this->service->search($request->validated(), 50);

        return response()->json([
            'success' => true,
            'message' => 'Data akun berhasil dimuat.',
            'data' => AccountResource::collection($accounts)->resolve($request),
        ]);
    }
}
