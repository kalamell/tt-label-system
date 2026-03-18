{{-- Shopee Label — แสดงกรอบแดงปิดทับข้อมูลสินค้า + กล่องเขียวจำนวนรวม --}}
@php
    use Picqer\Barcode\BarcodeGeneratorPNG;
    $barcodeGen = new BarcodeGeneratorPNG();
    $barcodePng = base64_encode(
        $barcodeGen->getBarcode($order->tracking_number, BarcodeGeneratorPNG::TYPE_CODE_128, 2, 55)
    );
    $carrierNames = ['JT' => 'J&T Express', 'FLASH' => 'Flash Express', 'SPX' => 'SPX Express'];
    $carrierName  = $carrierNames[$order->carrier] ?? ($order->carrier ?? 'Express');
@endphp
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
        * { box-sizing: border-box; }
        @page { margin: 0; padding: 0; size: 105.13mm 148.17mm; }
        html, body { margin: 0; padding: 0; width: 105.13mm; height: 148.17mm; }
        body {
            font-family: 'ThaiFont', Arial, sans-serif;
            font-size: 8pt;
            background: #fff;
        }
        .label {
            width: 105.13mm;
            height: 148.17mm;
            border: 0.5px solid #000;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Header */
        .header {
            display: flex;
            align-items: center;
            padding: 1.5mm 3mm;
            border-bottom: 0.5px solid #000;
            background: #fff3e0;
        }
        .header-brand { font-weight: bold; font-size: 12pt; color: #e64e00; flex: 1; }
        .header-carrier { font-size: 8.5pt; font-weight: bold; text-align: right; }

        /* Barcode */
        .barcode-section {
            text-align: center;
            padding: 1.5mm 3mm 1mm;
            border-bottom: 0.5px solid #000;
        }
        .barcode-section img { display: block; margin: 0 auto; }
        .barcode-number { font-size: 9pt; font-weight: bold; letter-spacing: 2px; margin-top: 1mm; font-family: 'Courier New', monospace; }

        /* Sender row */
        .sender-row {
            display: flex;
            border-bottom: 0.5px solid #000;
            font-size: 7.5pt;
        }
        .sender-col { flex: 3; padding: 1.5mm 2mm; border-right: 0.5px solid #ccc; }
        .sort-col {
            flex: 1;
            padding: 1.5mm 2mm;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }
        .sort-main { font-size: 13pt; font-weight: bold; line-height: 1.1; }
        .sort-sub  { font-size: 8pt; font-weight: bold; margin-top: 0.5mm; }
        .sort-route { font-size: 6pt; color: #555; margin-top: 0.5mm; }

        /* Recipient */
        .recipient-section {
            padding: 1.5mm 2mm;
            border-bottom: 0.5px solid #000;
        }
        .recipient-label { font-size: 6.5pt; color: #555; }
        .recipient-name  { font-size: 13pt; font-weight: bold; line-height: 1.2; }
        .recipient-phone { font-size: 8pt; }

        /* Address */
        .address-section {
            padding: 1mm 2mm;
            border-bottom: 0.5px solid #000;
            font-size: 9pt;
            font-weight: bold;
            line-height: 1.4;
        }

        /* COD */
        .cod-bar {
            background: #000;
            color: #fff;
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            padding: 1.5mm;
            letter-spacing: 3px;
        }
        .prepaid-bar {
            text-align: center;
            font-size: 7pt;
            padding: 0.8mm;
            border-bottom: 0.5px solid #ddd;
            color: #444;
        }

        /* Info row */
        .info-row {
            display: flex;
            padding: 1mm 2mm;
            border-bottom: 0.5px dashed #999;
            font-size: 7pt;
            justify-content: space-between;
        }

        /* Product table header */
        .product-header-row {
            display: flex;
            padding: 0.5mm 2mm;
            background: #f5f5f5;
            border-bottom: 0.5px solid #ccc;
            font-size: 6.5pt;
            color: #444;
            font-weight: bold;
        }
        .ph-num { width: 5mm; }
        .ph-name { flex: 2; }
        .ph-variant { flex: 1.5; }
        .ph-qty { width: 10mm; text-align: right; }

        /* Red product box */
        .product-hidden-box {
            background: #e53935;
            flex: 1;
            min-height: 9mm;
        }

        /* Footer */
        .footer {
            display: flex;
            align-items: center;
            padding: 1.5mm 2mm;
            border-top: 0.5px solid #000;
            gap: 2mm;
        }
        .footer-order { flex: 1; font-size: 7pt; }
        .footer-order strong { font-size: 7.5pt; }
        .footer-right { display: flex; flex-direction: column; align-items: flex-end; gap: 0.5mm; }
        .footer-qty-label { font-size: 6.5pt; color: #555; }
        .footer-qty-box {
            background: #43a047;
            color: #fff;
            font-size: 18pt;
            font-weight: bold;
            padding: 0.5mm 3mm;
            border-radius: 1mm;
            text-align: center;
            line-height: 1.1;
        }
    </style>
</head>
<body>
<div class="label">

    {{-- Header --}}
    <div class="header">
        <span class="header-brand">Shopee</span>
        <span class="header-carrier">{{ $carrierName }}</span>
    </div>

    {{-- Barcode --}}
    <div class="barcode-section">
        <img src="data:image/png;base64,{{ $barcodePng }}" alt="{{ $order->tracking_number }}" style="max-width:88mm; height:10mm;">
        <div class="barcode-number">{{ $order->tracking_number }}</div>
    </div>

    {{-- Sender + Sorting Code --}}
    <div class="sender-row">
        <div class="sender-col">
            <div style="font-size:6.5pt; color:#555;">ผู้ส่ง (FROM)</div>
            <div style="font-weight:bold;">{{ $order->sender_name }}</div>
            @if($order->delivery_type)
                <div style="font-size:6.5pt; margin-top:0.5mm;">{{ $order->delivery_type }}</div>
            @endif
        </div>
        <div class="sort-col">
            <div class="sort-main">{{ $order->sorting_code }}</div>
            <div class="sort-sub">{{ $order->sorting_code_2 }}</div>
            @if($order->route_code)
                <div class="sort-route">{{ $order->route_code }}</div>
            @endif
        </div>
    </div>

    {{-- Recipient --}}
    <div class="recipient-section">
        <div class="recipient-label">ผู้รับ (TO)</div>
        <div class="recipient-name">{{ $order->recipient_name }}</div>
        <div class="recipient-phone">{{ $order->recipient_phone }}</div>
    </div>

    {{-- Address --}}
    <div class="address-section">
        {{ $order->recipient_address }}<br>
        @if($order->recipient_district){{ $order->recipient_district }}, @endif{{ $order->recipient_province }} {{ $order->recipient_zipcode }}
    </div>

    {{-- COD / PREPAID --}}
    @if($order->payment_type === 'COD')
        <div class="cod-bar">COD</div>
    @else
        <div class="prepaid-bar">ไม่ต้องเก็บเงิน</div>
    @endif

    {{-- Info: Ship date + Order No --}}
    <div class="info-row">
        @if($order->shipping_date)
            <span>SHIP BY DATE {{ $order->shipping_date->format('d-m-Y') }}</span>
        @endif
        <span>Shopee Order No. {{ $order->order_id }}</span>
    </div>

    {{-- Product Table Header --}}
    <div class="product-header-row">
        <span class="ph-num">#</span>
        <span class="ph-name">ชื่อสินค้า</span>
        <span class="ph-variant">ตัวเลือกสินค้า</span>
        <span class="ph-qty">จำนวน</span>
    </div>

    {{-- Red Box — แสดง SKU + Qty ภายในกรอบแดง --}}
    <div class="product-hidden-box">
        <div style="color:#fff; text-align:center; line-height:1.3;">
            <div style="font-size:14pt; font-weight:bold; letter-spacing:1px;">
                {{ $order->seller_sku ?? $order->product_sku ?? '-' }}
            </div>
            <div style="font-size:32pt; font-weight:bold; line-height:1;">
                {{ $order->quantity }}
            </div>
        </div>
    </div>

    {{-- Footer: Order No + Green Qty Box --}}
    <div class="footer">
        <div class="footer-order">
            <strong>Shopee Order No.</strong><br>
            {{ $order->order_id }}
        </div>
        <div class="footer-right">
            <span class="footer-qty-label">จำนวนรวม</span>
            <div class="footer-qty-box">{{ $order->quantity }}</div>
        </div>
    </div>

</div>
</body>
</html>
