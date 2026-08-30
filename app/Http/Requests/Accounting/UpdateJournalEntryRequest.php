<?php

namespace App\Http\Requests\Accounting;

class UpdateJournalEntryRequest extends StoreJournalEntryRequest
{
    public function rules(): array
    {
        return parent::rules();
    }
}
