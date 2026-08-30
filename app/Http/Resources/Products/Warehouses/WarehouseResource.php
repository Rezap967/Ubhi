<?php

// app/Http/Resources/Products/Warehouses/WarehouseResource.php

namespace App\Http\Resources\Products\Warehouses;

use App\Models\Products\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'display_name' => $this->code ? "{$this->name} ({$this->code})" : $this->name,
            'name' => $this->name,
            'code' => $this->code,
            'address' => $this->address ?? '-',
            'phone' => $this->phone ?? '-',
            'description' => $this->description,
            'warehouse_type' => $this->warehouse_type,
            'type_label' => Warehouse::$typeLabels[$this->warehouse_type] ?? $this->warehouse_type,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
