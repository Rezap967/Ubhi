<?php

namespace App\Http\Resources\Accounting;

use App\Models\Accounting\JournalEntryLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalEntryLineResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var JournalEntryLine $line */
        $line = $this->resource;

        return [
            'id' => $line->id,
            'account_id' => $line->account_id,
            'account_code' => $line->account?->code,
            'account_name' => $line->account?->name,
            'description' => $line->description,
            'debit' => $line->debit,
            'credit' => $line->credit,
            'line_order' => $line->line_order,
        ];
    }
}
