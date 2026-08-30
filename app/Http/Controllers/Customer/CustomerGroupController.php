<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerGroupRequest;
use App\Http\Requests\Customer\UpdateCustomerGroupRequest;
use App\Http\Requests\Customer\ValidateDeleteGroupRequest;
use App\Models\Customer\CustomerGroup;
use App\Services\Customer\CustomerGroupService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomerGroupController extends Controller
{
    protected CustomerGroupService $service;

    public function __construct(CustomerGroupService $service)
    {
        $this->service = $service;
    }

    /**
     * GET /api/v1/customers/groups
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', CustomerGroup::class);

        $filters = $request->only(['search', 'sort_by', 'sort_dir']);
        $perPage = (int) $request->input('per_page', 10);

        $groups = $this->service->getPaginatedGroups($request->user(), $filters, $perPage);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $groups,
            ]);
        }

        return Inertia::render('Customer/CustomerGroup/Index', [
            'groups' => $groups,
            'filters' => $filters,
        ]);
    }

    /**
     * GET /api/v1/customers/groups/create
     */
    public function create()
    {
        $this->authorize('create', CustomerGroup::class);

        return Inertia::render('Customer/CustomerGroup/Create');
    }

    /**
     * POST /api/v1/customers/groups
     */
    public function store(StoreCustomerGroupRequest $request)
    {
        $group = $this->service->createGroup(
            $request->user(),
            $request->validated(),
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Grup pelanggan berhasil ditambahkan.',
                'data' => $group,
            ], 201);
        }

        return redirect()->route('customer-groups.index')
            ->with('success', 'Grup pelanggan berhasil ditambahkan.');
    }

    /**
     * GET /api/v1/customers/groups/{group}
     */
    public function show(CustomerGroup $group)
    {
        $this->authorize('view', $group);

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $group->load('activeCustomers'),
            ]);
        }

        return Inertia::render('Customer/CustomerGroup/Show', [
            'group' => $group,
        ]);
    }

    /**
     * GET /api/v1/customers/groups/{group}/edit
     */
    public function edit(CustomerGroup $group)
    {
        $this->authorize('update', $group);

        return Inertia::render('Customer/CustomerGroup/Edit', [
            'group' => $group,
        ]);
    }

    /**
     * PUT /api/v1/customers/groups/{group}
     */
    public function update(UpdateCustomerGroupRequest $request, CustomerGroup $group)
    {
        $updated = $this->service->updateGroup(
            $request->user(),
            $group,
            $request->validated(),
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Grup pelanggan berhasil diperbarui.',
                'data' => $updated,
            ]);
        }

        return redirect()->route('customer-groups.index')
            ->with('success', 'Grup pelanggan berhasil diperbarui.');
    }

    /**
     * DELETE /api/v1/customers/groups/{group}
     */
    public function destroy(Request $request, CustomerGroup $group)
    {
        $this->authorize('delete', $group);

        $force = $request->boolean('confirm');

        try {
            $this->service->deleteGroup($request->user(), $group, $force);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Grup pelanggan berhasil dihapus.',
                ]);
            }

            return redirect()->route('customer-groups.index')
                ->with('success', 'Grup pelanggan berhasil dihapus.');
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/v1/customers/groups/{group}/validate-delete
     */
    public function validateDelete(ValidateDeleteGroupRequest $request, CustomerGroup $group)
    {
        // Otorisasi sudah ditangani oleh ValidateDeleteGroupRequest

        $validation = $this->service->validateDelete($request->user(), $group);

        $activeCustomerCount = $validation['affected_count'] ?? 0;

        return response()->json([
            'success' => true,
            'data' => [
                'has_active_customers' => $activeCustomerCount > 0,
                'active_customers_count' => $activeCustomerCount,
                'can_delete' => true,
            ],
        ]);
    }
}
