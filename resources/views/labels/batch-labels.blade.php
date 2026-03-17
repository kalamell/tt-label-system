{{-- Batch Labels — หลายรายการรวมเป็น PDF เดียว --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @if(file_exists(public_path('fonts/thai-regular.ttf')))
        @font-face {
            font-family: 'ThaiFont';
            src: url('{{ public_path("fonts/thai-regular.ttf") }}') format('truetype');
            font-weight: normal;
        }
        @font-face {
            font-family: 'ThaiFont';
            src: url('{{ public_path("fonts/thai-bold.ttf") }}') format('truetype');
            font-weight: bold;
        }
        @endif
        @page { margin: 0; padding: 0; size: 105.13mm 148.17mm; }
        body { font-family: 'ThaiFont', Arial, sans-serif; margin: 0; padding: 0; font-size: 11px; }
        .label-page { page-break-after: always; padding: 8px; height: 148.17mm; }
        .label-page:last-child { page-break-after: auto; }
        .label { border: 1px solid #000; height: 100%; }
        .header { display: flex; justify-content: space-between; align-items: center; padding: 4px 8px; border-bottom: 1px solid #000; font-size: 10px; }
        .barcode-section { text-align: center; padding: 6px; border-bottom: 1px solid #000; }
        .barcode-number { font-size: 13px; font-weight: bold; letter-spacing: 2px; }
        .info-row { display: flex; border-bottom: 1px solid #000; }
        .sender-info { flex: 3; padding: 5px; border-right: 1px solid #000; font-size: 9px; }
        .sort-code { flex: 1; padding: 5px; text-align: center; }
        .sort-code .code { font-size: 20px; font-weight: bold; }
        .recipient-section { padding: 5px; border-bottom: 1px solid #000; }
        .recipient-name { font-size: 16px; font-weight: bold; }
        .address-section { padding: 5px; border-bottom: 1px solid #000; font-size: 14px; font-weight: bold; }
        .cod-bar { background: #000; color: #fff; text-align: center; font-size: 18px; font-weight: bold; padding: 3px; }
        .lot-display { font-size: 36px; font-weight: bold; text-align: center; padding: 8px 0; }
        .meta { padding: 3px 5px; font-size: 9px; display: flex; justify-content: space-between; border-bottom: 1px solid #000; }
    </style>
</head>
<body>
    @foreach($orders as $order)
        <div class="label-page">
            <div class="label">
                <div class="header">
                    <span style="font-weight:bold">TikTok Shop</span>
                    @if($order->carrier === 'FLASH')
                        <span>Flash Express</span>
                        <span style="font-size:16px; font-weight:bold">{{ $order->service_type ?? 'NDD' }}</span>
                    @else
                        <span>J&amp;T Express</span>
                        <span style="font-size:16px; font-weight:bold">{{ $order->service_type ?? 'EZ' }}</span>
                    @endif
                </div>

                <div class="barcode-section">
                    <div class="barcode-number">{{ $order->tracking_number }}</div>
                </div>

                <div class="info-row">
                    <div class="sender-info">
                        <strong>จาก</strong> {{ $order->sender_name }}<br>
                        {{ Str::limit($order->sender_address, 80) }}
                    </div>
                    <div class="sort-code">
                        <div class="code">{{ $order->sorting_code }}</div>
                        <div>{{ $order->sorting_code_2 }}</div>
                    </div>
                </div>

                <div class="recipient-section">
                    <strong>ถึง</strong> <span class="recipient-name">{{ $order->recipient_name }}</span><br>
                    <span style="font-size:9px;">{{ $order->recipient_phone }}</span>
                </div>

                <div class="address-section">
                    {{ $order->recipient_address }}<br>
                    {{ $order->recipient_district }}, {{ $order->recipient_province }} {{ $order->recipient_zipcode }}
                </div>

                @if($order->payment_type === 'COD')
                    <div class="cod-bar">COD</div>
                @endif

                <div class="meta">
                    <span>Order ID: {{ $order->order_id }}</span>
                    <span>{{ $order->shipping_date?->format('d-m-Y') }}</span>
                </div>

                <div style="padding:4px 5px; border-bottom:1px solid #ccc;">
                    <table style="width:100%; font-size:9px;">
                        <tr><th style="text-align:left">Product Name</th><th>SKU</th><th>Qty</th></tr>
                        <tr><td>-, -</td><td></td><td>{{ $order->quantity }}</td></tr>
                    </table>
                    <div class="lot-display">{{ $order->assigned_lot ?? '03/100' }}</div>
                    <div style="text-align:right; font-size:9px;">Qty Total: {{ $order->quantity }}</div>
                </div>

                <div class="meta" style="border-bottom:none;">
                    <span style="font-weight:bold">TikTok Shop</span>
                    <span>Order ID: {{ $order->order_id }}</span>
                </div>
            </div>
        </div>
    @endforeach
</body>
</html>
