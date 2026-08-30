<?php

namespace App\Observers\Belanja;

use App\Models\Belanja\PurchaseReturnItem;

class PurchaseReturnItemObserver
{
    public function saved(PurchaseReturnItem $item): void
    {
        $this->recalculateTotal($item);
    }

    public function deleted(PurchaseReturnItem $item): void
    {
        $this->recalculateTotal($item);
    }

    protected function recalculateTotal(PurchaseReturnItem $item): void
    {
        $return = $item->purchaseReturn;
        if ($return) {
            $total = $return->items()->sum('subtotal');
            $return->updateQuietly(['total' => $total]);
        }
    }
}
