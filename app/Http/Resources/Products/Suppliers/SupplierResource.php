<?php

namespace App\Http\Resources\Products\Suppliers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'email' => $this->email ?? '-',
            'phone' => $this->phone ?? '-',
            'address' => $this->address ?? '-',
            'notes' => $this->notes,
            // [ACTIVATE WHEN PRODUCT READY]
            // 'product_count' => $this->product_count ?? 0,
            'product_count' => 0, // sementara 0 sampai supplier_products tersedia
            'status' => $this->status,
            'status_label' => $this->status === 'aktif' ? 'Aktif' : 'Nonaktif',
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
