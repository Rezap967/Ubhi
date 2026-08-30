<?php

namespace App\Services\Accounting;

use App\Models\Accounting\CustomerReceivable;
use Illuminate\Support\Carbon;

class CustomerReceivableNumberService
{
    public function generate(int $storeId, ?Carbon $date = null): string
    {
        $date ??= now();
        $prefix = 'AR-'.$date->format('Ym').'-';
        $last = CustomerReceivable::query()->forStore($storeId)->where('receivable_no', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('receivable_no')->value('receivable_no');
        $sequence = $last === null ? 1 : ((int) substr($last, -4)) + 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
