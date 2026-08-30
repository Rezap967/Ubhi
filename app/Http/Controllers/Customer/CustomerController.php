<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\QuickCreateCustomerRequest;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerStatusRequest;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGroup;
use App\Services\Customer\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $customerService) {}

    public function index(Request $request): InertiaResponse|JsonResponse
    {
        $this->authorize('viewAny', Customer::class);

        $storeId = (int) Auth::user()->store_id;

        $filters = $request->only([
            'search',
            'status',
            'group_id',
            'sort_by',
            'sort_dir',
            'page',
            'per_page',
        ]);

        $customers = $this->customerService->getList($storeId, $filters);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $customers,
            ]);
        }

        $activeGroups = CustomerGroup::query()
            ->where('store_id', $storeId)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Pelanggan/DaftarPelanggan/Index', [
            'customers' => $customers,
            'groups' => $activeGroups,
            'filters' => $filters,
            'total' => $customers->total(),
        ]);
    }

    public function create(Request $request): InertiaResponse
    {
        $this->authorize('create', Customer::class);

        $storeId = (int) Auth::user()->store_id;

        $activeGroups = CustomerGroup::query()
            ->where('store_id', $storeId)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Pelanggan/DaftarPelanggan/Create', [
            'groups' => $activeGroups,
            'statuses' => [
                ['value' => 'aktif', 'label' => 'Aktif'],
                ['value' => 'nonaktif', 'label' => 'Nonaktif'],
            ],
        ]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse|JsonResponse
    {
        $this->authorize('create', Customer::class);

        $storeId = (int) Auth::user()->store_id;
        $userId = (int) Auth::id();

        $customer = $this->customerService->store(
            $storeId,
            $userId,
            $request->validated()
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Pelanggan berhasil ditambahkan.',
                'data' => [
                    'id' => $customer->id,
                    'code' => $customer->code,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'email' => $customer->email,
                    'status' => $customer->status,
                    'customer_group_id' => $customer->customer_group_id,
                ],
            ], 201);
        }

        return redirect()
            ->route('customers.index')
            ->with('success', 'Pelanggan berhasil ditambahkan.');
    }

    public function show(int $id, Request $request): InertiaResponse|JsonResponse
    {
        $this->authorize('view', Customer::class);

        $storeId = (int) Auth::user()->store_id;
        $customer = $this->customerService->findById($id, $storeId);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $customer,
            ]);
        }

        return Inertia::render('Pelanggan/DaftarPelanggan/Show', [
            'customer' => $customer,
        ]);
    }

    public function edit(int $id, Request $request): InertiaResponse
    {
        $this->authorize('update', Customer::class);

        $storeId = (int) Auth::user()->store_id;
        $customer = $this->customerService->findById($id, $storeId);

        $activeGroups = CustomerGroup::query()
            ->where('store_id', $storeId)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Pelanggan/DaftarPelanggan/Edit', [
            'customer' => $customer,
            'groups' => $activeGroups,
            'statuses' => [
                ['value' => 'aktif', 'label' => 'Aktif'],
                ['value' => 'nonaktif', 'label' => 'Nonaktif'],
            ],
        ]);
    }

    public function update(UpdateCustomerRequest $request, int $id): RedirectResponse|JsonResponse
    {
        $this->authorize('update', Customer::class);

        $storeId = (int) Auth::user()->store_id;
        $userId = (int) Auth::id();

        $customer = $this->customerService->update(
            $id,
            $storeId,
            $userId,
            $request->validated()
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Data pelanggan berhasil diperbarui.',
                'data' => $customer,
            ]);
        }

        return redirect()
            ->route('customers.index')
            ->with('success', 'Data pelanggan berhasil diperbarui.');
    }

    public function destroy(int $id, Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', Customer::class);

        $storeId = (int) Auth::user()->store_id;
        $userId = (int) Auth::id();
        $confirm = (bool) $request->input('confirm', false);

        $this->customerService->delete($id, $storeId, $userId, $confirm);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Pelanggan berhasil dihapus.',
            ]);
        }

        return redirect()
            ->route('customers.index')
            ->with('success', 'Pelanggan berhasil dihapus.');
    }

    public function updateStatus(UpdateCustomerStatusRequest $request, int $id): RedirectResponse|JsonResponse
    {
        $this->authorize('update', Customer::class);

        $storeId = (int) Auth::user()->store_id;
        $userId = (int) Auth::id();

        $customer = $this->customerService->updateStatus(
            $id,
            $storeId,
            $userId,
            $request->validated('status')
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Status pelanggan berhasil diperbarui.',
                'data' => $customer,
            ]);
        }

        return redirect()
            ->route('customers.index')
            ->with('success', 'Status pelanggan berhasil diperbarui.');
    }

    public function quickCreateFromPOS(QuickCreateCustomerRequest $request): JsonResponse
    {
        $this->authorize('quickCreateFromPOS', Customer::class);

        $storeId = (int) Auth::user()->store_id;
        $userId = (int) Auth::id();

        $customer = $this->customerService->quickCreateFromPOS(
            $storeId,
            $userId,
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Pelanggan berhasil ditambahkan.',
            'data' => [
                'id' => $customer->id,
                'code' => $customer->code,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'status' => $customer->status,
                'customer_group_id' => $customer->customer_group_id,
            ],
        ], 201);
    }

    public function posLookup(Request $request): JsonResponse
    {
        $storeId = (int) Auth::user()->getStoreId();
        $search = $request->input('search');
        $limit = (int) $request->input('limit', 20);

        if ($limit > 50) {
            $limit = 50;
        }

        $query = Customer::query()
            ->select([
                'customers.id',
                'customers.code',
                'customers.name',
                'customers.phone',
                'customers.email',
                'customers.status',
                'customers.customer_group_id',
            ])
            ->selectRaw('customer_groups.name as group_name')
            ->leftJoin('customer_groups', function ($join) {
                $join->on('customer_groups.id', '=', 'customers.customer_group_id')
                    ->whereNull('customer_groups.deleted_at');
            })
            ->where('customers.store_id', $storeId)
            ->where('customers.status', 'aktif')
            ->whereNull('customers.deleted_at');

        if (! empty($search)) {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like) {
                $q->where('customers.name', 'like', $like)
                    ->orWhere('customers.code', 'like', $like)
                    ->orWhere('customers.phone', 'like', $like)
                    ->orWhere('customers.email', 'like', $like);
            });
        }

        // Urutkan: MBR-000000 di atas, sisanya berdasarkan nama asc
        $query->orderByRaw("CASE WHEN customers.code = 'MBR-000000' THEN 0 ELSE 1 END")
            ->orderBy('customers.name', 'asc');

        $customers = $query->limit($limit)->get();

        // Format mapping agar sesuai dengan PosCartResource
        $data = $customers->map(function ($c) {
            return [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'phone' => $c->phone,
                'email' => $c->email,
                'customer_group' => $c->customer_group_id ? [
                    'id' => $c->customer_group_id,
                    'name' => $c->group_name,
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Daftar pelanggan berhasil dimuat.',
            'data' => $data,
        ]);
    }
}
