<?php

namespace App\Services\Accounting;

use App\Models\Accounting\JournalEntry;
use Illuminate\Support\Carbon;

class JournalNumberService
{
    public function generate(int $storeId, ?Carbon $date = null): string
    {
        $date ??= now();
        $prefix = 'JU-'.$date->format('Ym').'-';

        $lastNumber = JournalEntry::query()
            ->forStore($storeId)
            ->where('journal_no', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('journal_no')
            ->value('journal_no');

        $sequence = $lastNumber !== null
            ? ((int) substr($lastNumber, -4)) + 1
            : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
