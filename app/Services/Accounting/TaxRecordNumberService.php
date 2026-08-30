<?php

namespace App\Services\Accounting;

use App\Models\Accounting\TaxRecord;
use Illuminate\Support\Carbon;

class TaxRecordNumberService
{
    public function generate(int $storeId, ?Carbon $date = null): string
    {
        $date ??= now();
        $prefix = 'TAX-'.$date->format('Ym').'-';
        $last = TaxRecord::query()->forStore($storeId)->where('transaction_no', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('transaction_no')->value('transaction_no');

        return $prefix.str_pad((string) ($last ? ((int) substr($last, -4) + 1) : 1), 4, '0', STR_PAD_LEFT);
    }
}
