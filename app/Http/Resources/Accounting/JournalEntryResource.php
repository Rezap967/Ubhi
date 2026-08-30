<?php

namespace App\Http\Resources\Accounting;

use App\Models\Accounting\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalEntryResource extends JsonResource
{
    public function __construct(mixed $resource)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var JournalEntry $journalEntry */
        $journalEntry = $this->resource;

        return [
            'id' => $journalEntry->id,
            'store_id' => $journalEntry->store_id,
            'journal_no' => $journalEntry->journal_no,
            'journal_date' => $journalEntry->journal_date->toDateString(),
            'description' => $journalEntry->description,
            'status' => $journalEntry->status,
            'source_type' => $journalEntry->source_type,
            'source_id' => $journalEntry->source_id,
            'reference_no' => $journalEntry->reference_no,
            'total_debit' => $journalEntry->total_debit,
            'total_credit' => $journalEntry->total_credit,
            'posted_at' => $journalEntry->posted_at?->toIso8601String(),
            'posted_by' => $journalEntry->posted_by,
            'reversed_at' => $journalEntry->reversed_at?->toIso8601String(),
            'reversed_by' => $journalEntry->reversed_by,
            'reversed_entry_id' => $journalEntry->reversed_entry_id,
            'lines' => JournalEntryLineResource::collection($this->whenLoaded('lines')),
            'created_at' => $journalEntry->created_at?->toIso8601String(),
            'updated_at' => $journalEntry->updated_at?->toIso8601String(),
        ];
    }
}
