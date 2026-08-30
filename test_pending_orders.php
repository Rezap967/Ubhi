<?php

use Illuminate\Support\Facades\DB;

// Get recent orders
$orders = DB::table('pending_orders')
    ->orderBy('created_at', 'desc')
    ->limit(5)
    ->get();

echo "=== PENDING ORDERS ===\n";
echo 'Total: '.$orders->count()."\n\n";

foreach ($orders as $order) {
    echo 'ID: '.$order->id."\n";
    echo 'Name: '.$order->name."\n";
    echo 'Store ID: '.$order->store_id."\n";
    echo 'Items: '.$order->items."\n";
    echo 'Total: '.$order->total."\n";
    echo 'Created: '.$order->created_at."\n";
    echo "---\n";
}
