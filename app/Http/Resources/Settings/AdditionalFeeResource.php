<?php

namespace App\Http\Resources\Settings;

use App\Models\Settings\AdditionalFeeSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdditionalFeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'fee_type' => $this->fee_type,
            'fee_type_label' => $this->feeTypeLabel($this->fee_type),
            'amount' => (float) $this->amount,
            'amount_formatted' => $this->amountFormatted($this->fee_type, $this->amount),
            'status' => $this->status,
            'status_label' => $this->statusLabel($this->status),
            'sort_order' => (int) $this->sort_order,
            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }

    private function feeTypeLabel(string $type): string
    {
        return $type === AdditionalFeeSetting::TYPE_FIXED ? 'Nominal Tetap' : 'Persentase';
    }

    private function statusLabel(string $status): string
    {
        return $status === AdditionalFeeSetting::STATUS_ACTIVE ? 'Aktif' : 'Nonaktif';
    }

    private function amountFormatted(string $type, $amount): string
    {
        $val = (float) $amount;
        if ($type === AdditionalFeeSetting::TYPE_FIXED) {
            return 'Rp ' . number_format($val, 0, ',', '.');
        }
        return $val . '%';
    }
}