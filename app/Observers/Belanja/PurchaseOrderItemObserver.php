<?php

namespace App\Observers\Belanja;

use App\Models\Belanja\PurchaseOrderItem;

class PurchaseOrderItemObserver
{
    public function saved(PurchaseOrderItem $item): void
    {
        $this->recalculateTotal($item);
    }

    public function deleted(PurchaseOrderItem $item): void
    {
        $this->recalculateTotal($item);
    }

    protected function recalculateTotal(PurchaseOrderItem $item): void
    {
        $po = $item->purchaseOrder;
        if ($po) {
            $total = $po->items()->sum('subtotal');
            $po->updateQuietly(['total' => $total]);
        }
    }
}
