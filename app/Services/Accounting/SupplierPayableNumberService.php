<?php

namespace App\Services\Accounting;

use App\Models\Accounting\SupplierPayable;
use Illuminate\Support\Carbon;

class SupplierPayableNumberService
{
    public function generate(int $storeId, ?Carbon $date = null): string
    {
        $date ??= now();
        $prefix = 'AP-'.$date->format('Ym').'-';
        $last = SupplierPayable::query()->forStore($storeId)->where('payable_no', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('payable_no')->value('payable_no');
        $sequence = $last === null ? 1 : ((int) substr($last, -4)) + 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
