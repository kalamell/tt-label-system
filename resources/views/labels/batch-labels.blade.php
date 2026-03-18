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
                    <span style="font-weight:bold">{{ $order->platform === 'SHOPEE' ? 'Shopee' : 'TikTok Shop' }}</span>
                    @php
                        $carrierNames = ['JT' => 'J&T Express', 'FLASH' => 'Flash Express', 'SPX' => 'SPX Express'];
                        $carrierName = $carrierNames[$order->carrier] ?? ($order->carrier ?? 'Express');
                    @endphp
                    <span>{{ $carrierName }}</span>
                    <span style="font-size:16px; font-weight:bold">{{ $order->service_type ?? '' }}</span>
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

                @if($order->platform === 'SHOPEE')
                    {{-- Shopee: product header + red box + green qty footer --}}
                    <div style="display:flex; padding:1px 5px; background:#f5f5f5; border-bottom:0.5px solid #ccc; font-size:7px; color:#444; font-weight:bold;">
                        <span style="width:14px;">#</span>
                        <span style="flex:2;">ชื่อสินค้า</span>
                        <span style="flex:1.5;">ตัวเลือกสินค้า</span>
                        <span style="width:20px; text-align:right;">จำนวน</span>
                    </div>
                    <div style="background:#e53935; flex:1; min-height:18px; display:flex; align-items:center; justify-content:center;">
                        <div style="color:#fff; text-align:center; line-height:1.2;">
                            <div style="font-size:11px; font-weight:bold; letter-spacing:1px;">{{ $order->seller_sku ?? $order->product_sku ?? '-' }}</div>
                            <div style="font-size:28px; font-weight:bold; line-height:1;">{{ $order->quantity }}</div>
                        </div>
                    </div>
                    <div style="display:flex; align-items:center; padding:3px 5px; border-top:0.5px solid #000; gap:4px;">
                        <div style="flex:1; font-size:8px;">
                            <strong>Shopee Order No.</strong><br>{{ $order->order_id }}
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:7px; color:#555;">จำนวนรวม</div>
                            <div style="background:#43a047; color:#fff; font-size:20px; font-weight:bold; padding:0 5px; border-radius:2px; line-height:1.2;">{{ $order->quantity }}</div>
                        </div>
                    </div>
                @else
                    {{-- TikTok: SKU + Qty ใหญ่ + batch counter N/Total --}}
                    <div style="padding:4px 5px; border-bottom:1px solid #ccc; text-align:center;">
                        <div style="font-size:16px; font-weight:bold; letter-spacing:1px;">
                            {{ $order->seller_sku ?? $order->product_sku ?? '-' }}
                        </div>
                        <div style="display:flex; justify-content:center; align-items:baseline; gap:6px;">
                            <span style="font-size:40px; font-weight:bold; line-height:1;">{{ $order->quantity }}</span>
                            <span style="font-size:14px; font-weight:bold;">ชิ้น</span>
                            <span style="font-size:18px; font-weight:bold; color:#555;">
                                {{ $loop->iteration }}/{{ $loop->count }}
                            </span>
                        </div>
                        <div class="lot-display">{{ $order->assigned_lot ?? '' }}</div>
                    </div>
                    <div class="meta" style="border-bottom:none;">
                        <span style="font-weight:bold">TikTok Shop</span>
                        <span>Order ID: {{ $order->order_id }}</span>
                    </div>
                @endif
            </div>
        </div>
    @endforeach
</body>
</html>
