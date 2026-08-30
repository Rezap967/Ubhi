<?php

namespace App\Http\Controllers\Accounting;

use App\Enums\Accounting\AccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\AccountIndexRequest;
use App\Http\Requests\Accounting\StoreAccountRequest;
use App\Http\Requests\Accounting\UpdateAccountRequest;
use App\Http\Resources\Accounting\AccountResource;
use App\Models\Accounting\Account;
use App\Services\Accounting\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function __construct(protected AccountService $service) {}

    public function index(AccountIndexRequest $request): Response|JsonResponse
    {
        $this->authorize('viewAny', Account::class);

        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 20);
        $accounts = $this->service->paginate($validated, $perPage);

        $props = [
            'accounts' => AccountResource::collection($accounts),
            'filters' => [
                'search' => $validated['search'] ?? '',
                'type' => $validated['type'] ?? '',
                'is_active' => $validated['is_active'] ?? null,
                'per_page' => $perPage,
            ],
            'accountTypes' => collect(AccountType::cases())
                ->map(fn (AccountType $type): array => [
                    'value' => $type->value,
                    'label' => match ($type) {
                        AccountType::ASSET => 'Aset',
                        AccountType::LIABILITY => 'Liabilitas',
                        AccountType::EQUITY => 'Ekuitas',
                        AccountType::REVENUE => 'Pendapatan',
                        AccountType::EXPENSE => 'Beban',
                    },
                ])
                ->values(),
        ];

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Data akun berhasil dimuat.',
                'data' => AccountResource::collection($accounts)->resolve($request),
                'pagination' => [
                    'current_page' => $accounts->currentPage(),
                    'per_page' => $accounts->perPage(),
                    'total' => $accounts->total(),
                    'last_page' => $accounts->lastPage(),
                ],
            ]);
        }

        return Inertia::render('Akuntansi/BaganAkun/Index', $props);
    }

    public function store(StoreAccountRequest $request): RedirectResponse|JsonResponse
    {
        $this->authorize('create', Account::class);

        $account = $this->service->create($request->validated());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Data akun berhasil ditambahkan.',
                'data' => (new AccountResource($account))->resolve($request),
            ], 201);
        }

        return redirect()->route('accounting.accounts.index')
            ->with('success', 'Data akun berhasil ditambahkan.');
    }

    public function update(UpdateAccountRequest $request, Account $account): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $account);

        $account = $this->service->update($account, $request->validated());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Data akun berhasil diperbarui.',
                'data' => (new AccountResource($account))->resolve($request),
            ]);
        }

        return redirect()->route('accounting.accounts.index')
            ->with('success', 'Data akun berhasil diperbarui.');
    }

    public function destroy(Request $request, Account $account): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $account);

        $account = $this->service->deactivate($account);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Akun berhasil dinonaktifkan.',
                'data' => (new AccountResource($account))->resolve($request),
            ]);
        }

        return redirect()->route('accounting.accounts.index')
            ->with('success', 'Akun berhasil dinonaktifkan.');
    }
}
