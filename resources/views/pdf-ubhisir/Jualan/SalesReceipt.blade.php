<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Struk Order - {{ $order->order_number ?? '-' }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #111;
            margin: 0;
            padding: 18px;
        }
        .header { text-align: center; margin-bottom: 10px; }
        .store-title { font-size: 16px; font-weight: bold; text-transform: uppercase; }
        .store-info { font-size: 11px; line-height: 1.45; margin-top: 3px; }
        .line-double {
            border-top: 2px solid #111;
            border-bottom: 1px solid #111;
            height: 3px;
            margin: 9px 0;
        }
        .doc-title {
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            margin: 10px 0;
            letter-spacing: .6px;
        }
        .meta-table { width: 100%; margin-bottom: 14px; border-collapse: collapse; }
        .meta-table td { padding: 2px 0; vertical-align: top; line-height: 1.35; }
        .meta-label { width: 125px; font-weight: 600; }
        .meta-separator { width: 12px; text-align: center; }
        .item-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .item-table th,
        .item-table td { border: 1px solid #666; padding: 6px 7px; vertical-align: top; }
        .item-table th { background: #f2f2f2; font-weight: bold; text-align: left; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .muted { color: #555; font-size: 10px; }
        .summary-table { width: 330px; float: right; border-collapse: collapse; margin: 4px 0 18px; }
        .summary-table td { padding: 3px 0; }
        .summary-table .grand-total td { border-top: 1px solid #111; padding-top: 5px; font-weight: bold; }
        .clear { clear: both; }
        .note-box { margin-top: 10px; font-size: 11px; line-height: 1.45; }
        .line-single { border-top: 1px solid #111; margin: 18px 0 28px; }
        .signature-container { width: 100%; margin-top: 20px; }
        .signature-box { width: 45%; float: left; text-align: center; }
        .signature-box.right { float: right; }
        .signature-space { height: 62px; }
        .footer { clear: both; margin-top: 95px; text-align: center; font-size: 10px; color: #555; }
    </style>
</head>
<body>
@php
    $storeName = $store->name ?? config('app.name', 'Toko');
    $storeAddress = $store->address ?? null;
    $storePhone = $store->phone ?? null;

    $customer = $order->customer ?? null;
    $warehouse = $order->warehouse ?? null;
    $creator = $order->createdBy ?? $order->user ?? null;

    $statusLabels = [
        'menunggu' => 'Menunggu',
        'diproses' => 'Diproses',
        'selesai' => 'Selesai',
        'dibatalkan' => 'Dibatalkan',
    ];
    $paymentStatusLabels = [
        'belum_bayar' => 'Belum Bayar',
        'parsial' => 'Parsial',
        'lunas' => 'Lunas',
    ];
    $deliveryMethodLabels = [
        'ambil_ditempat' => 'Ambil di Tempat',
        'pengiriman' => 'Pengiriman',
    ];

    $status = $statusLabels[$order->status ?? ''] ?? ucfirst(str_replace('_', ' ', $order->status ?? '-'));
    $paymentStatus = $paymentStatusLabels[$order->payment_status ?? ''] ?? ucfirst(str_replace('_', ' ', $order->payment_status ?? '-'));
    $deliveryMethod = $deliveryMethodLabels[$order->delivery_method ?? ''] ?? ucfirst(str_replace('_', ' ', $order->delivery_method ?? '-'));

    $subtotal = (float) ($order->subtotal ?? 0);
    $discountTotal = (float) ($order->discount_total ?? 0);
    $taxTotal = (float) ($order->tax_total ?? 0);
    $total = (float) ($order->total ?? ($subtotal - $discountTotal + $taxTotal));
@endphp

    <div class="header">
        <div class="store-title">{{ $storeName }}</div>
        <div class="store-info">
            @if($storeAddress) {{ $storeAddress }}<br> @endif
            @if($storePhone) Telp: {{ $storePhone }} @endif
        </div>
    </div>

    <div class="line-double"></div>
    <div class="doc-title">STRUK ORDER PELANGGAN</div>
    <div class="line-double"></div>

    <table class="meta-table">
        <tr><td class="meta-label">No. Order</td><td class="meta-separator">:</td><td><strong>{{ $order->order_number ?? '-' }}</strong></td></tr>
        <tr><td class="meta-label">Tanggal Order</td><td class="meta-separator">:</td><td>{{ optional($order->created_at)->timezone(config('app.timezone', 'Asia/Jakarta'))->translatedFormat('d F Y H:i') ?? '-' }}</td></tr>
        <tr><td class="meta-label">Jatuh Tempo</td><td class="meta-separator">:</td><td>{{ $order->due_date ? \Carbon\Carbon::parse($order->due_date)->translatedFormat('d F Y') : '-' }}</td></tr>
        <tr><td class="meta-label">Status Order</td><td class="meta-separator">:</td><td>{{ $status }}</td></tr>
        <tr><td class="meta-label">Status Bayar</td><td class="meta-separator">:</td><td>{{ $paymentStatus }}</td></tr>
        <tr><td class="meta-label">Pelanggan</td><td class="meta-separator">:</td><td>{{ $customer->name ?? 'Umum' }}</td></tr>
        <tr><td class="meta-label">Telepon</td><td class="meta-separator">:</td><td>{{ $customer->phone ?? '-' }}</td></tr>
        <tr><td class="meta-label">Gudang</td><td class="meta-separator">:</td><td>{{ $warehouse->name ?? '-' }}</td></tr>
        <tr><td class="meta-label">Penyerahan</td><td class="meta-separator">:</td><td>{{ $deliveryMethod }}</td></tr>
        <tr><td class="meta-label">Dibuat Oleh</td><td class="meta-separator">:</td><td>{{ $creator->name ?? '-' }}</td></tr>
        <tr><td class="meta-label">Catatan</td><td class="meta-separator">:</td><td>{{ $order->notes ?: '-' }}</td></tr>
    </table>

    <table class="item-table">
        <thead>
            <tr>
                <th class="text-center" style="width: 30px;">No</th>
                <th>Produk</th>
                <th class="text-center" style="width: 55px;">Qty</th>
                <th class="text-right" style="width: 95px;">Harga</th>
                <th class="text-right" style="width: 85px;">Diskon</th>
                <th class="text-right" style="width: 105px;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @forelse($order->items ?? [] as $index => $item)
                @php
                    $qty = (float) ($item->quantity ?? 0);
                    $unitPrice = (float) ($item->unit_price ?? $item->price ?? 0);
                    $discount = (float) ($item->discount_value ?? 0);
                    $lineSubtotal = (float) ($item->subtotal ?? (($qty * $unitPrice) - $discount));
                    $productCode = $item->product_code ?? $item->product?->sku ?? $item->product?->code ?? null;
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>
                        {{ $item->product_name ?? $item->product?->name ?? '-' }}
                        @if($productCode)
                            <br><span class="muted">Kode/SKU: {{ $productCode }}</span>
                        @endif
                    </td>
                    <td class="text-center">{{ rtrim(rtrim(number_format($qty, 3, ',', '.'), '0'), ',') }}</td>
                    <td class="text-right">{{ number_format($unitPrice, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($discount, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($lineSubtotal, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center">Tidak ada item order.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="summary-table">
        <tr><td>Subtotal</td><td class="meta-separator">:</td><td class="text-right">Rp {{ number_format($subtotal, 0, ',', '.') }}</td></tr>
        <tr><td>Diskon</td><td class="meta-separator">:</td><td class="text-right">Rp {{ number_format($discountTotal, 0, ',', '.') }}</td></tr>
        <tr><td>Pajak</td><td class="meta-separator">:</td><td class="text-right">Rp {{ number_format($taxTotal, 0, ',', '.') }}</td></tr>
        <tr class="grand-total"><td>Total</td><td class="meta-separator">:</td><td class="text-right">Rp {{ number_format($total, 0, ',', '.') }}</td></tr>
    </table>

    <div class="clear"></div>

    <div class="note-box">
        <strong>Catatan:</strong><br>
        Struk ini merupakan bukti order pelanggan. Harga produk memakai snapshot saat order dibuat, sehingga perubahan harga produk berikutnya tidak mengubah histori order.
    </div>

    <div class="line-single"></div>

    <div class="signature-container">
        <div class="signature-box">
            Pelanggan,
            <div class="signature-space"></div>
            ( {{ $customer->name ?? 'Umum' }} )
        </div>
        <div class="signature-box right">
            Kasir / Admin,
            <div class="signature-space"></div>
            ( {{ $creator->name ?? '-' }} )
        </div>
    </div>

    <div class="footer">
        Dicetak pada {{ now()->timezone(config('app.timezone', 'Asia/Jakarta'))->translatedFormat('d F Y H:i') }}
    </div>
</body>
</html>
