<?php

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FixedAssetCategoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $methods = ['straight_line' => 'Garis Lurus', 'declining_balance' => 'Saldo Menurun', 'units_of_production' => 'Unit Produksi', 'none' => 'Tanpa Depresiasi'];
        $assetsCount = (int) ($this->assets_count ?? 0);
        $assetLabel = fn ($account): ?array => $account ? ['id' => $account->id, 'code' => $account->code, 'name' => $account->name, 'account_code' => $account->code, 'account_name' => $account->name, 'label' => $account->code.' - '.$account->name] : null;
        $active = (bool) $this->is_active;
        $used = $assetsCount > 0;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'category_code' => $this->code,
            'category_name' => $this->name,
            'description' => $this->description,
            'asset_account_id' => $this->asset_account_id,
            'asset_account' => $assetLabel($this->assetAccount),
            'accumulated_depreciation_account_id' => $this->accumulated_depreciation_account_id,
            'accumulated_depreciation_account' => $assetLabel($this->accumulatedDepreciationAccount),
            'depreciation_expense_account_id' => $this->depreciation_expense_account_id,
            'depreciation_expense_account' => $assetLabel($this->depreciationExpenseAccount),
            'default_useful_life_months' => $this->default_useful_life_months,
            'useful_life_label' => $this->default_useful_life_months ? $this->default_useful_life_months.' bulan' : '-',
            'depreciation_method' => $this->depreciation_method,
            'depreciation_method_label' => $methods[$this->depreciation_method] ?? $this->depreciation_method,
            'depreciation_rate' => $this->depreciation_rate,
            'residual_value_type' => $this->residual_value_type,
            'residual_value' => (float) ($this->residual_value ?? 0),
            'is_active' => $active,
            'status' => $active ? 'active' : 'inactive',
            'status_label' => $active ? 'Aktif' : 'Nonaktif',
            'status_color' => $active ? 'green' : 'red',
            'is_system' => (bool) $this->is_system,
            'assets_count' => $assetsCount,
            'asset_count' => $assetsCount,
            'is_used' => $used,
            'used_label' => $used ? 'Sudah Digunakan' : 'Belum Digunakan',
            'can_edit' => true,
            'can_delete' => ! $used && ! $this->is_system,
            'can_toggle_status' => ! $this->is_system,
            'kode' => $this->code,
            'nama_kategori' => $this->name,
            'akun_aset' => $this->assetAccount?->code.' - '.$this->assetAccount?->name,
            'akun_akumulasi_depresiasi' => $this->accumulatedDepreciationAccount?->code.' - '.$this->accumulatedDepreciationAccount?->name,
            'akun_beban_depresiasi' => $this->depreciationExpenseAccount?->code.' - '.$this->depreciationExpenseAccount?->name,
            'umur_manfaat' => $this->default_useful_life_months ? $this->default_useful_life_months.' bulan' : '-',
            'metode' => $methods[$this->depreciation_method] ?? $this->depreciation_method,
            'jumlah_aset' => $assetsCount,
            'status_text' => $active ? 'Aktif' : 'Nonaktif',
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
