<?php

namespace App\Services\Settings;

use App\Models\Settings\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentMethodService
{
    public function index(int $storeId): Collection
    {
        return PaymentMethod::query()
            ->where('store_id', $storeId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function show(PaymentMethod $paymentMethod, int $storeId): PaymentMethod
    {
        $method = PaymentMethod::query()
            ->whereKey($paymentMethod->getKey())
            ->where('store_id', $storeId)
            ->first();

        if (! $method) {
            throw new ModelNotFoundException('Metode pembayaran tidak ditemukan.');
        }

        return $method;
    }

    public function store(array $data, int $storeId, User $user): PaymentMethod
    {
        return DB::transaction(function () use ($data, $storeId, $user) {
            $data['code'] = $this->normalizeCode($data['code'] ?? null, $data['name'] ?? '');
            $data['store_id'] = $storeId;
            $data['created_by'] = $user->id;
            $data['updated_by'] = $user->id;
            $data['sort_order'] = $data['sort_order'] ?? $this->nextSortOrder($storeId);

            return PaymentMethod::query()->create($data);
        });
    }

    public function update(array $data, PaymentMethod $paymentMethod, int $storeId, User $user): PaymentMethod
    {
        $this->show($paymentMethod, $storeId);

        if (array_key_exists('code', $data) || array_key_exists('name', $data)) {
            $data['code'] = $this->normalizeCode(
                $data['code'] ?? $paymentMethod->getAttribute('code'),
                $data['name'] ?? $paymentMethod->getAttribute('name'),
            );
        }

        $data['updated_by'] = $user->id;

        $paymentMethod->fill($data);
        $paymentMethod->save();

        return $paymentMethod->fresh();
    }

    public function destroy(PaymentMethod $paymentMethod, int $storeId): void
    {
        $this->show($paymentMethod, $storeId);
        $paymentMethod->delete();
    }

    public function toggle(PaymentMethod $paymentMethod, int $storeId, User $user): PaymentMethod
    {
        $this->show($paymentMethod, $storeId);

        $paymentMethod->update([
            'is_active' => ! $paymentMethod->is_active,
            'updated_by' => $user->id,
        ]);

        return $paymentMethod->fresh();
    }

    protected function normalizeCode(?string $code, string $name): string
    {
        $normalizedCode = trim((string) ($code ?? ''));

        if ($normalizedCode === '') {
            $normalizedCode = trim((string) $name);
        }

        return strtolower(Str::slug($normalizedCode, '_'));
    }

    protected function nextSortOrder(int $storeId): int
    {
        return (int) PaymentMethod::query()
            ->where('store_id', $storeId)
            ->max('sort_order') + 1;
    }
}
