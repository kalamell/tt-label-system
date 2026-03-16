{{-- Label สำหรับพิมพ์ — ซ่อน Product Name, แสดง Barcode จริง --}}
@php
    use Picqer\Barcode\BarcodeGeneratorPNG;
    $barcodeGen = new BarcodeGeneratorPNG();
    $barcodePng = base64_encode(
        $barcodeGen->getBarcode($order->tracking_number, BarcodeGeneratorPNG::TYPE_CODE_128, 2, 60)
    );
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
            font-size: 9pt;
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
            justify-content: space-between;
            padding: 2mm 3mm;
            border-bottom: 0.5px solid #000;
            font-size: 8pt;
        }
        .header-brand { font-weight: bold; font-size: 9pt; }
        .header-service { font-weight: bold; font-size: 10pt; letter-spacing: 1px; }

        /* Barcode */
        .barcode-section {
            text-align: center;
            padding: 2mm 3mm 1mm;
            border-bottom: 0.5px solid #000;
            line-height: 1;
        }
        .barcode-section img { display: block; margin: 0 auto; }
        .barcode-number { font-size: 9pt; font-weight: bold; letter-spacing: 2px; margin-top: 1mm; font-family: 'Courier New', monospace; }

        /* Sender + Sorting Code */
        .info-row {
            display: flex;
            border-bottom: 0.5px solid #000;
            font-size: 7.5pt;
        }
        .sender-info { flex: 3; padding: 1.5mm 2mm; border-right: 0.5px solid #000; }
        .sort-code { flex: 1; padding: 1.5mm 2mm; text-align: center; display: flex; flex-direction: column; justify-content: center; }
        .sort-code .sort-main { font-size: 14pt; font-weight: bold; line-height: 1.2; }
        .sort-code .sort-sub  { font-size: 9pt; font-weight: bold; }

        /* Recipient */
        .recipient-section {
            padding: 1.5mm 2mm;
            border-bottom: 0.5px solid #000;
        }
        .recipient-label { font-size: 7pt; color: #555; }
        .recipient-name  { font-size: 14pt; font-weight: bold; line-height: 1.2; }
        .recipient-phone { font-size: 9pt; }

        /* Address */
        .address-section {
            padding: 1.5mm 2mm;
            border-bottom: 0.5px solid #000;
            font-size: 10pt;
            font-weight: bold;
            line-height: 1.4;
        }

        /* COD */
        .cod-bar {
            background: #000;
            color: #fff;
            text-align: center;
            font-size: 16pt;
            font-weight: bold;
            padding: 1.5mm;
            letter-spacing: 3px;
        }

        /* Order Info */
        .order-info {
            padding: 1mm 2mm;
            border-bottom: 0.5px solid #000;
            font-size: 7pt;
            display: flex;
            justify-content: space-between;
        }

        /* Product Section */
        .product-section {
            padding: 1mm 2mm;
            border-bottom: 0.5px solid #000;
            flex: 1;
        }
        .product-header {
            display: flex;
            font-size: 6.5pt;
            color: #666;
            border-bottom: 0.5px solid #ddd;
            padding-bottom: 0.5mm;
            margin-bottom: 0.5mm;
        }
        .product-header span:first-child { flex: 3; }
        .product-header span { flex: 1; }
        .product-data {
            display: flex;
            font-size: 7pt;
            align-items: center;
        }
        .product-data span:first-child { flex: 3; }
        .product-data span { flex: 1; }
        .lot-display {
            font-size: 28pt;
            font-weight: bold;
            text-align: center;
            letter-spacing: 2px;
            line-height: 1;
            margin: 1mm 0;
        }
        .qty-total { text-align: right; font-size: 7pt; }

        /* Footer */
        .footer {
            padding: 1mm 2mm;
            display: flex;
            justify-content: space-between;
            font-size: 7pt;
            border-top: 0.5px solid #000;
        }
    </style>
</head>
<body>
<div class="label">

    {{-- Header --}}
    <div class="header">
        <span class="header-brand">TikTok Shop</span>
        <span>J&amp;T Express</span>
        <span class="header-service">EZ</span>
    </div>

    {{-- Barcode --}}
    <div class="barcode-section">
        <img src="data:image/png;base64,{{ $barcodePng }}" alt="{{ $order->tracking_number }}" style="max-width:90mm; height:10mm;">
        <div class="barcode-number">{{ $order->tracking_number }}</div>
    </div>

    {{-- Sender + Sorting Code --}}
    <div class="info-row">
        <div class="sender-info">
            <div><strong>จาก</strong> {{ $order->sender_name }}</div>
            <div>{{ $order->sender_address }}</div>
            @if($order->delivery_type)
                <div style="margin-top:0.5mm; font-size:7pt;">{{ $order->delivery_type }}</div>
            @endif
        </div>
        <div class="sort-code">
            <div class="sort-main">{{ $order->sorting_code }}</div>
            <div class="sort-sub">{{ $order->sorting_code_2 }}</div>
        </div>
    </div>

    {{-- Recipient --}}
    <div class="recipient-section">
        <div class="recipient-label">ถึง</div>
        <div class="recipient-name">{{ $order->recipient_name }}</div>
        <div class="recipient-phone">{{ $order->recipient_phone }}</div>
    </div>

    {{-- Address --}}
    <div class="address-section">
        {{ $order->recipient_address }}<br>
        {{ $order->recipient_district }}, {{ $order->recipient_province }} {{ $order->recipient_zipcode }}
    </div>

    {{-- COD Bar --}}
    @if($order->payment_type === 'COD')
        <div class="cod-bar">COD</div>
    @endif

    {{-- Order Info --}}
    <div class="order-info">
        <span>Order ID: {{ $order->order_id }}</span>
        <span>{{ $order->shipping_date?->format('d-m-Y') }}</span>
    </div>

    {{-- Product Section — ซ่อนชื่อสินค้า แสดง Lot Number --}}
    <div class="product-section">
        <div class="product-header">
            <span>Product Name</span>
            <span>SKU</span>
            <span>Seller SKU</span>
            <span>Qty</span>
        </div>
        <div class="product-data">
            <span>-, -</span>
            <span></span>
            <span></span>
            <span>{{ $order->quantity }}</span>
        </div>
        <div class="lot-display">{{ $order->assigned_lot }}</div>
        <div class="qty-total">Qty Total: {{ $order->quantity }}</div>
    </div>

    {{-- Footer --}}
    <div class="footer">
        <span><strong>TikTok Shop</strong></span>
        <span>Order ID: {{ $order->order_id }}</span>
    </div>

</div>
</body>
</html>
