<?php

namespace App\Http\Resources\Accounting;

use App\Models\Accounting\Account;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Account $account */
        $account = $this->resource;

        return [
            'id' => $account->id,
            'store_id' => $account->store_id,
            'code' => $account->code,
            'name' => $account->name,
            'account_code' => $account->code,
            'account_name' => $account->name,
            'description' => $account->description,
            'type' => $account->type,
            'account_type' => $account->type,
            'normal_balance' => $account->normal_balance,
            'label' => $account->code.' - '.$account->name,
            'parent_id' => $account->parent_id,
            'parent' => $this->whenLoaded('parent', fn () => [
                'id' => $account->parent?->id,
                'code' => $account->parent?->code,
                'name' => $account->parent?->name,
            ]),
            'is_system' => $account->is_system,
            'is_active' => $account->is_active,
            'sort_order' => $account->sort_order,
            'created_at' => $account->created_at?->toIso8601String(),
            'updated_at' => $account->updated_at?->toIso8601String(),
        ];
    }
}
