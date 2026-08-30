<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Pesanan Pelanggan - {{ $order->order_number ?? '-' }}</title>
    <style>
        @page { margin: 24px 28px; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
            color: #000;
        }
        table { border-collapse: collapse; width: 100%; }
        .top-table td { vertical-align: top; }
        .store-name { font-size: 14px; font-weight: 700; line-height: 1.1; }
        .store-info { font-size: 8.5px; line-height: 1.25; font-weight: 600; }
        .print-meta { text-align: right; font-size: 9px; line-height: 1.25; font-weight: 700; }
        .line { border-top: 2px solid #444; margin: 28px 0 10px; }
        .line-thin { border-top: 1.5px solid #555; margin: 16px 0 14px; }
        .title { text-align: center; font-size: 12px; font-weight: 800; line-height: 1.2; }
        .subtitle { text-align: center; font-size: 9px; font-weight: 700; }
        .meta { margin-top: 4px; margin-bottom: 8px; width: 46%; }
        .meta td { padding: 1px 0; vertical-align: top; font-weight: 700; }
        .meta .label { width: 78px; }
        .meta .colon { width: 8px; text-align: center; }
        .items { margin-top: 8px; }
        .items th, .items td { border: 1px solid #222; padding: 4px 4px; vertical-align: top; }
        .items th { background: #d9d9d9; text-align: center; font-weight: 800; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .nowrap { white-space: nowrap; }
        .note { margin-top: 10px; font-weight: 700; line-height: 1.35; min-height: 34px; }
        .summary-wrap { width: 34%; margin-left: auto; margin-top: 4px; }
        .summary td { padding: 2px 0; font-weight: 700; }
        .summary .amount { text-align: right; white-space: nowrap; }
        .summary .top-border td { border-top: 1.5px solid #777; padding-top: 4px; }
        .signature { margin-top: 18px; width: 100%; }
        .signature td { width: 50%; vertical-align: top; font-weight: 700; }
        .sig-title { height: 54px; }
        .sig-line { display: inline-block; min-width: 112px; border-top: 1.5px solid #111; padding-top: 3px; text-align: center; }
        .terbilang { margin-top: 7px; font-weight: 800; }
        .small { font-size: 8.5px; }
    </style>
</head>
<body>
@php
    use Carbon\Carbon;

    $storeName = $store->name ?? config('app.name', 'Toko');
    $storeAddress = $store->address ?? '';
    $storePhone = $store->phone ?? '';

    $customer = $order->customer ?? null;
    $warehouse = $order->warehouse ?? null;
    $creator = $order->createdBy ?? $order->user ?? null;

    $orderNumber = $order->order_number ?? $order->customer_order_number ?? '-';
    $printedAt = isset($printedAt) ? Carbon::parse($printedAt) : now();
    $orderDate = $order->created_at ? Carbon::parse($order->created_at) : $printedAt;

    $deliveryLabels = [
        'ambil_ditempat' => 'Ambil di Tempat',
        'pengiriman' => 'Pengiriman',
    ];
    $deliveryMethod = $deliveryLabels[$order->delivery_method ?? ''] ?? ($order->delivery_type ?? '-');

    $items = $order->items ?? collect();
    $subtotal = (float) ($order->subtotal ?? 0);
    $discountTotal = (float) ($order->discount_total ?? 0);
    $taxTotal = (float) ($order->tax_total ?? 0);
    $total = (float) ($order->total ?? ($subtotal - $discountTotal + $taxTotal));
    $paidAmount = (float) ($order->paid_amount ?? 0);
    $dpPo = (float) ($order->dp_po ?? $order->down_payment ?? 0);
    $cash = (float) ($order->cash_amount ?? $order->tunai ?? 0);
    $debit = (float) ($order->debit_amount ?? 0);
    $creditCard = (float) ($order->credit_card_amount ?? 0);
    $credit = (float) ($order->credit_amount ?? max($total - $paidAmount - $dpPo - $cash - $debit - $creditCard, 0));
    $change = (float) ($order->change_amount ?? max($paidAmount - $total, 0));

    if (! function_exists('rupiah_doc')) {
        function rupiah_doc($value) { return 'Rp ' . number_format((float) $value, 0, ',', '.'); }
    }
@endphp

<table class="top-table">
    <tr>
        <td style="width: 60%;">
            <div class="store-name">{{ $storeName }}</div>
            <div class="store-info">
                {!! nl2br(e($storeAddress)) !!}<br>
                @if($storePhone) Telp: {{ $storePhone }} @endif
            </div>
        </td>
        <td class="print-meta" style="width: 40%;">
            Tanggal<br>
            {{ $printedAt->format('d/m/Y H:i:s') }}<br>
            User<br>
            {{ $creator->name ?? auth()->user()->name ?? '-' }}
        </td>
    </tr>
</table>

<div class="line"></div>

<div class="title">SURAT PESANAN PELANGGAN</div>
<div class="subtitle">No: {{ $orderNumber }}</div>

<div class="line-thin"></div>

<table class="meta">
    <tr><td class="label">Nama</td><td class="colon">:</td><td>{{ $customer->name ?? $order->customer_name ?? 'Umum' }}</td></tr>
    <tr><td class="label">Telepon</td><td class="colon">:</td><td>{{ $customer->phone ?? $order->customer_phone ?? '-' }}</td></tr>
    <tr><td class="label">Penyerahan</td><td class="colon">:</td><td>{{ $deliveryMethod }}</td></tr>
    <tr><td class="label">Gudang</td><td class="colon">:</td><td>{{ $warehouse->name ?? $order->warehouse_name ?? '-' }}</td></tr>
</table>

<table class="items">
    <thead>
        <tr>
            <th style="width: 25px;">No</th>
            <th style="width: 58px;">Kode<br>Item</th>
            <th>Nama Item</th>
            <th style="width: 38px;">Jml</th>
            <th style="width: 72px;">Harga</th>
            <th style="width: 48px;">Pot</th>
            <th style="width: 76px;">Total</th>
        </tr>
    </thead>
    <tbody>
        @forelse($items as $index => $item)
            @php
                $qty = (float) ($item->quantity ?? $item->qty ?? 0);
                $unitPrice = (float) ($item->unit_price ?? $item->price ?? 0);
                $discount = (float) ($item->discount_value ?? $item->discount ?? 0);
                $lineTotal = (float) ($item->subtotal ?? (($qty * $unitPrice) - $discount));
                $product = $item->product ?? null;
                $code = $item->product_code ?? $product?->sku ?? $product?->code ?? $product?->barcode ?? '-';
                $name = $item->product_name ?? $product?->name ?? '-';
            @endphp
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $code }}</td>
                <td>{{ $name }}</td>
                <td class="text-center">{{ rtrim(rtrim(number_format($qty, 3, ',', '.'), '0'), ',') }}</td>
                <td class="text-right nowrap">{{ number_format($unitPrice, 0, ',', '.') }}</td>
                <td class="text-right nowrap">{{ number_format($discount, 0, ',', '.') }}</td>
                <td class="text-right nowrap">{{ number_format($lineTotal, 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center">Tidak ada item.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="note">
    Keterangan:<br>
    {!! nl2br(e($order->notes ?: '-')) !!}
</div>

<div class="summary-wrap">
    <table class="summary">
        <tr><td>Jml Item</td><td class="amount">{{ $items instanceof \Illuminate\Support\Collection ? $items->count() : count($items) }}</td></tr>
        <tr class="top-border"><td>Sub Total</td><td class="amount">{{ rupiah_doc($subtotal) }}</td></tr>
        <tr><td>Potongan</td><td class="amount">{{ rupiah_doc($discountTotal) }}</td></tr>
        <tr><td>Total Akhir</td><td class="amount">{{ rupiah_doc($total) }}</td></tr>
        <tr><td>DP PO</td><td class="amount">{{ rupiah_doc($dpPo) }}</td></tr>
        <tr><td>Total</td><td class="amount">{{ rupiah_doc($paidAmount) }}</td></tr>
        <tr><td>Kredit</td><td class="amount">{{ rupiah_doc($credit) }}</td></tr>
        <tr><td>K. Debet</td><td class="amount">{{ rupiah_doc($debit) }}</td></tr>
        <tr><td>K. Kredit</td><td class="amount">{{ rupiah_doc($creditCard) }}</td></tr>
        <tr><td>Kembali</td><td class="amount">{{ rupiah_doc($change) }}</td></tr>
    </table>
</div>

<table class="signature">
    <tr>
        <td>
            <div class="sig-title">Hormat Kami,</div>
            <span class="sig-line">{{ $creator->name ?? auth()->user()->name ?? '-' }}</span>
        </td>
        <td style="text-align: right;">
            <div class="sig-title">Penerima,</div>
            <span class="sig-line">{{ $customer->name ?? $order->customer_name ?? '-' }}</span>
        </td>
    </tr>
</table>

<div class="line-thin" style="margin-top: 18px;"></div>
<div class="terbilang">
    Terbilang: {{ $terbilang }}
</div>
</body>
</html>
