@extends('layouts.app')
@section('title', 'รายละเอียดออเดอร์')
@section('page-title', 'รายละเอียดออเดอร์ #' . $order->tracking_number)

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- ข้อมูลหลัก --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Shipping Info --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h3 class="font-semibold text-gray-800 mb-4">ข้อมูลการจัดส่ง</h3>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="text-gray-400 text-xs">Tracking Number</p>
                        <p class="font-mono font-bold text-lg text-blue-600">{{ $order->tracking_number }}</p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-xs">Order ID</p>
                        <p class="font-mono text-gray-700">{{ $order->order_id }}</p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-xs">ขนส่ง</p>
                        <p class="font-medium text-gray-800">
                            @if($order->carrier === 'FLASH')
                                <span class="px-2 py-0.5 bg-orange-100 text-orange-700 rounded text-sm font-medium">Flash Express</span>
                                @if($order->service_type)
                                    <span class="text-gray-500 text-sm ml-1">· {{ $order->service_type }}</span>
                                @endif
                            @elseif($order->carrier === 'JT')
                                <span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-sm font-medium">J&amp;T Express</span>
                                @if($order->service_type)
                                    <span class="text-gray-500 text-sm ml-1">· {{ $order->service_type }}</span>
                                @endif
                            @else
                                <span class="text-gray-400 text-sm">—</span>
                            @endif
                        </p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-xs">Sorting Code</p>
                        <p class="font-bold text-xl">{{ $order->sorting_code }} / {{ $order->sorting_code_2 }}</p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-xs">ประเภท</p>
                        <div class="flex gap-2 mt-1">
                            @if($order->payment_type === 'COD')
                                <span class="px-3 py-1 bg-red-100 text-red-700 rounded font-bold">COD</span>
                            @else
                                <span class="px-3 py-1 bg-gray-100 text-gray-700 rounded">PREPAID</span>
                            @endif
                            <span class="px-3 py-1 bg-gray-100 text-gray-700 rounded">{{ $order->delivery_type }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Recipient --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h3 class="font-semibold text-gray-800 mb-4">ข้อมูลผู้รับ</h3>
                <div class="text-sm space-y-2">
                    <p><span class="text-gray-400 w-20 inline-block">ชื่อ:</span> <strong class="text-lg">{{ $order->recipient_name }}</strong></p>
                    <p><span class="text-gray-400 w-20 inline-block">โทร:</span> {{ $order->recipient_phone }}</p>
                    <p><span class="text-gray-400 w-20 inline-block">ที่อยู่:</span> {{ $order->recipient_address }}</p>
                    <p><span class="text-gray-400 w-20 inline-block">อำเภอ:</span> {{ $order->recipient_district }}, {{ $order->recipient_province }} {{ $order->recipient_zipcode }}</p>
                </div>
            </div>

            {{-- Product (ข้อมูลภายใน — ไม่แสดงใน Label) --}}
            <div class="bg-amber-50 rounded-xl border border-amber-200 p-6">
                <h3 class="font-semibold text-amber-800 mb-2">ข้อมูลสินค้า (ข้อมูลภายในระบบ — ไม่แสดงใน Label)</h3>
                <p class="text-xs text-amber-600 mb-3">ข้อมูลนี้จะถูกซ่อนเมื่อพิมพ์ Label</p>

                @php
                    $names    = array_map('trim', explode('|', $order->product_name ?? ''));
                    $skus     = array_map('trim', explode('|', $order->product_sku ?? ''));
                    $sellers  = array_map('trim', explode('|', $order->seller_sku ?? ''));
                    $qtys     = array_map('trim', explode('|', $order->item_quantities ?? $order->quantity ?? '1'));
                    $count    = max(count($names), count($sellers), 1);
                @endphp

                <table class="w-full text-sm border border-amber-200 rounded-lg overflow-hidden">
                    <thead class="bg-amber-100">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-amber-700">#</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-amber-700">Product Name</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-amber-700">Seller SKU</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-amber-700">Product SKU</th>
                            <th class="px-3 py-2 text-center text-xs font-medium text-amber-700">จำนวน</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-amber-100">
                        @for($i = 0; $i < $count; $i++)
                        <tr class="bg-white/60">
                            <td class="px-3 py-2 text-amber-600 text-xs">{{ $i + 1 }}</td>
                            <td class="px-3 py-2 text-amber-900">{{ $names[$i] ?? '-' }}</td>
                            <td class="px-3 py-2 font-mono text-amber-900">{{ $sellers[$i] ?? '-' }}</td>
                            <td class="px-3 py-2 font-mono text-amber-700 text-xs">{{ $skus[$i] ?? '-' }}</td>
                            <td class="px-3 py-2 text-center font-bold text-amber-900">{{ $qtys[$i] ?? 1 }}</td>
                        </tr>
                        @endfor
                    </tbody>
                    <tfoot class="bg-amber-100">
                        <tr>
                            <td colspan="4" class="px-3 py-2 text-xs text-amber-700 text-right font-medium">รวมทั้งหมด</td>
                            <td class="px-3 py-2 text-center font-bold text-amber-900">{{ $order->quantity }}</td>
                        </tr>
                    </tfoot>
                </table>

                <div class="mt-3 text-sm text-amber-800">
                    <span class="text-amber-700/60 text-xs">Lot ที่ตัดสต๊อก (FIFO):</span>
                    <span class="font-bold ml-1">{{ $order->assigned_lot ?? 'ยังไม่ได้ตัด' }}</span>
                </div>
            </div>

            {{-- Transactions --}}
            @if($order->transactions->isNotEmpty())
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">ประวัติ Transaction</h3>
                    <div class="space-y-2">
                        @foreach($order->transactions as $txn)
                            <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg text-sm">
                                <span class="px-2 py-0.5 rounded text-xs font-medium {{ $txn->type === 'out' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">
                                    {{ $txn->type === 'out' ? 'ตัดออก' : 'เข้า' }}
                                </span>
                                <span>{{ $txn->notes }}</span>
                                <span class="ml-auto text-gray-400 text-xs">{{ $txn->created_at->format('d/m/Y H:i') }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Sidebar --}}
        <div class="space-y-4">
            {{-- Customer Card --}}
            @if($order->customer)
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h4 class="text-sm font-semibold text-gray-600 mb-3">ข้อมูลลูกค้า</h4>
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-bold text-sm flex-shrink-0">
                        {{ mb_substr($order->customer->name, 0, 1) }}
                    </div>
                    <div>
                        <p class="font-medium text-gray-800 text-sm">{{ $order->customer->masked_name }}</p>
                        <p class="text-xs text-gray-400 font-mono">{{ $order->customer->masked_phone }}</p>
                    </div>
                </div>
                <div class="text-xs text-gray-500 space-y-1 border-t border-gray-100 pt-3">
                    @if($order->customer->district || $order->customer->province)
                    <p>{{ implode(', ', array_filter([$order->customer->district, $order->customer->province, $order->customer->zipcode])) }}</p>
                    @endif
                    <p>ออเดอร์ทั้งหมด: <span class="font-semibold text-gray-700">{{ $order->customer->total_orders }}</span> รายการ</p>
                </div>
                <a href="{{ route('customers.show', $order->customer) }}"
                   class="mt-3 w-full block text-center px-3 py-1.5 border border-blue-300 text-blue-600 rounded-lg text-xs hover:bg-blue-50">
                    ดูประวัติลูกค้า
                </a>
            </div>
            @endif

            {{-- Status Card --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h4 class="text-sm font-semibold text-gray-600 mb-3">สถานะ</h4>
                @switch($order->status)
                    @case('pending')
                        <span class="inline-block px-4 py-2 bg-yellow-100 text-yellow-800 rounded-lg font-medium">รอพิมพ์ Label</span>
                        @break
                    @case('printed')
                        <span class="inline-block px-4 py-2 bg-blue-100 text-blue-800 rounded-lg font-medium">พิมพ์ Label แล้ว</span>
                        <p class="text-xs text-gray-400 mt-2">พิมพ์เมื่อ: {{ $order->printed_at?->format('d/m/Y H:i') }}</p>
                        @break
                    @case('shipped')
                        <span class="inline-block px-4 py-2 bg-green-100 text-green-800 rounded-lg font-medium">จัดส่งแล้ว</span>
                        @break
                @endswitch

                <div class="mt-4 text-sm text-gray-500 space-y-1">
                    <p>นำเข้าเมื่อ: <span class="text-gray-700">{{ $order->created_at->format('d/m/Y H:i') }}</span></p>
                    <p>วันที่จัดส่ง: {{ $order->shipping_date?->format('d/m/Y') ?? '-' }}</p>
                </div>
            </div>

            {{-- Actions --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
                <h4 class="text-sm font-semibold text-gray-600 mb-2">จัดการ</h4>

                <a href="{{ route('orders.print', $order) }}"
                   class="w-full block text-center px-4 py-2.5 bg-purple-600 text-white rounded-lg text-sm font-medium hover:bg-purple-700">
                    พิมพ์ Label (ซ่อนสินค้า)
                </a>

                <a href="{{ route('orders.index') }}"
                   class="w-full block text-center px-4 py-2.5 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200">
                    กลับรายการออเดอร์
                </a>
            </div>
        </div>
    </div>
@endsection
