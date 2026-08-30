<?php

namespace App\Http\Resources\Kasir;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OperationalSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // $this->resource is the array returned by the service
        return $this->resource;
    }
}
