<?php

namespace App\Services\Accounting;

use Illuminate\Support\Carbon;

class AccountingPeriodService
{
    public function isPeriodLocked(int|string $storeId, string|Carbon $date): bool
    {
        // TODO: Integrasikan dengan tabel accounting_periods saat periode akuntansi tersedia.
        return false;
    }
}
