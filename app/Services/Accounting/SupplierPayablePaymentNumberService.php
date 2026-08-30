<?php

namespace App\Services\Accounting;

use App\Models\Accounting\SupplierPayablePayment;
use Illuminate\Support\Carbon;

class SupplierPayablePaymentNumberService
{
    public function generate(int $storeId, ?Carbon $date = null): string
    {
        $date ??= now();
        $prefix = 'APPAY-'.$date->format('Ym').'-';
        $last = SupplierPayablePayment::query()->forStore($storeId)->where('payment_no', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('payment_no')->value('payment_no');
        $sequence = $last === null ? 1 : ((int) substr($last, -4)) + 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
