@extends('layouts.app')
@section('title', 'ยืนยันนำเข้า PDF')
@section('page-title', 'ยืนยันนำเข้า PDF')

@section('content')
<form action="{{ route('orders.upload.confirm.post') }}" method="POST">
@csrf

<div class="max-w-4xl space-y-5">

    {{-- Summary --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center gap-4">
            <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <div>
                <p class="font-semibold text-gray-800">พบ {{ $totalOrders }} ออเดอร์ใน PDF</p>
                <p class="text-sm text-gray-500 mt-0.5">
                    @if($existingCount > 0)
                        <span class="text-orange-600">{{ $existingCount }} รายการมีอยู่แล้ว (จะถูกข้าม)</span> •
                    @endif
                    จะนำเข้าใหม่ {{ $totalOrders - $existingCount }} รายการ
                </p>
            </div>
        </div>
    </div>

    {{-- สินค้าที่พบ + checkbox ตัดสต๊อก --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-gray-800">สินค้าที่พบใน PDF</h3>
            <div class="flex gap-2">
                <button type="button" onclick="toggleAll(true)"
                        class="text-xs px-3 py-1.5 border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50">
                    เลือกทั้งหมด
                </button>
                <button type="button" onclick="toggleAll(false)"
                        class="text-xs px-3 py-1.5 border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50">
                    ยกเลิกทั้งหมด
                </button>
            </div>
        </div>

        @if(empty($uniqueProducts))
            <p class="text-sm text-gray-400 text-center py-4">ไม่พบข้อมูลสินค้า</p>
        @else
            <div class="space-y-3">
                @foreach($uniqueProducts as $key => $product)
                <label class="flex items-start gap-4 p-4 rounded-xl border-2 cursor-pointer transition-colors
                              {{ $product['is_new'] ? 'border-amber-200 bg-amber-50 hover:border-amber-400' : 'border-gray-100 hover:border-blue-200' }}
                              has-[:checked]:border-blue-400 has-[:checked]:bg-blue-50">

                    <input type="checkbox"
                           name="deduct[]"
                           value="{{ $key }}"
                           class="deduct-checkbox mt-1 w-4 h-4 text-blue-600 rounded border-gray-300 cursor-pointer"
                           checked>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-medium text-gray-800 text-sm">{{ $product['product_name'] ?: '(ไม่มีชื่อ)' }}</span>

                            @if($product['is_new'])
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-amber-100 text-amber-700 text-xs rounded-full font-medium">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                    </svg>
                                    สร้างสินค้าใหม่อัตโนมัติ
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded-full font-medium">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    พบในระบบ: {{ $product['matched_name'] }}
                                </span>
                            @endif
                        </div>

                        <div class="flex flex-wrap gap-x-4 gap-y-1 mt-1.5 text-xs text-gray-500">
                            @if($product['seller_sku'])
                                <span>Seller SKU: <span class="font-mono text-gray-700">{{ $product['seller_sku'] }}</span></span>
                            @endif
                            @if($product['product_sku'])
                                <span>SKU: <span class="font-mono text-gray-700">{{ $product['product_sku'] }}</span></span>
                            @endif
                            <span>พบใน <strong>{{ $product['order_count'] }}</strong> ออเดอร์ • รวม <strong>{{ $product['total_qty'] }}</strong> ชิ้น</span>
                        </div>

                        @if(!$product['is_new'])
                            <div class="mt-2 flex items-center gap-2">
                                <span class="text-xs text-gray-500">สต๊อกปัจจุบัน:</span>
                                @if($product['stock'] >= $product['total_qty'])
                                    <span class="text-xs font-semibold text-green-600">{{ $product['stock'] }} ชิ้น ✓ เพียงพอ</span>
                                @elseif($product['stock'] > 0)
                                    <span class="text-xs font-semibold text-orange-500">{{ $product['stock'] }} ชิ้น ⚠ ไม่พอ (ต้องการ {{ $product['total_qty'] }})</span>
                                @else
                                    <span class="text-xs font-semibold text-red-600">0 ชิ้น ✗ ไม่มีสต๊อก</span>
                                @endif
                            </div>
                        @else
                            <p class="mt-2 text-xs text-amber-600">
                                ⚠ จะสร้างสินค้าใหม่โดยอัตโนมัติ — ยังไม่มีสต๊อก (ต้องรับสินค้าเข้าภายหลัง)
                            </p>
                        @endif
                    </div>

                    <div class="text-right flex-shrink-0">
                        <p class="text-xs text-gray-400">ตัดสต๊อก</p>
                        <p class="text-lg font-bold text-gray-800">{{ $product['total_qty'] }}</p>
                        <p class="text-xs text-gray-400">ชิ้น</p>
                    </div>
                </label>
                @endforeach
            </div>

            <p class="text-xs text-gray-400 mt-3">
                ✅ ติ๊ก = ตัดสต๊อก FIFO อัตโนมัติ  •  ไม่ติ๊ก = บันทึกออเดอร์โดยไม่ตัดสต๊อก (ตัดทีหลังได้จากหน้าสต๊อก)
            </p>
        @endif
    </div>

    {{-- รายการออเดอร์ (แสดงย่อ) --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h3 class="font-semibold text-gray-800 mb-3">ออเดอร์ที่จะนำเข้า ({{ $totalOrders }} รายการ)</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-400 uppercase border-b">
                        <th class="pb-2 pr-4">Tracking</th>
                        <th class="pb-2 pr-4">ผู้รับ</th>
                        <th class="pb-2 pr-4">จังหวัด</th>
                        <th class="pb-2 pr-4">สินค้า (ใน PDF)</th>
                        <th class="pb-2">จำนวน</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($parsedOrders as $p)
                    <tr class="border-b border-gray-50 hover:bg-gray-50">
                        <td class="py-2 pr-4 font-mono text-xs">{{ $p['tracking_number'] ?? '-' }}</td>
                        <td class="py-2 pr-4 text-gray-700">{{ Str::limit($p['recipient_name'] ?? '-', 18) }}</td>
                        <td class="py-2 pr-4 text-gray-500">{{ $p['recipient_province'] ?? '-' }}</td>
                        <td class="py-2 pr-4 text-gray-400 text-xs">{{ Str::limit($p['product_name'] ?? '-', 30) }}</td>
                        <td class="py-2 text-gray-700">{{ $p['quantity'] ?? 1 }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Actions --}}
    <div class="flex items-center gap-3">
        <button type="submit" id="btn-confirm"
                class="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 flex items-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed">
            {{-- idle icon --}}
            <svg id="btn-icon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{-- loading spinner (hidden by default) --}}
            <svg id="btn-spinner" class="w-4 h-4 animate-spin hidden" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
            </svg>
            <span id="btn-text">ยืนยันนำเข้า {{ $totalOrders - $existingCount }} ออเดอร์</span>
        </button>
        <a href="{{ route('orders.upload.form') }}" id="link-cancel"
           class="text-sm text-gray-500 hover:text-gray-700">
            ← อัพโหลดใหม่
        </a>
    </div>

</div>
</form>
@endsection

@push('scripts')
<script>
function toggleAll(state) {
    document.querySelectorAll('.deduct-checkbox').forEach(cb => cb.checked = state);
}

document.querySelector('form').addEventListener('submit', function () {
    const btn     = document.getElementById('btn-confirm');
    const icon    = document.getElementById('btn-icon');
    const spinner = document.getElementById('btn-spinner');
    const text    = document.getElementById('btn-text');
    const cancel  = document.getElementById('link-cancel');

    btn.disabled = true;
    icon.classList.add('hidden');
    spinner.classList.remove('hidden');
    text.textContent = 'กำลังนำเข้า...';
    cancel.classList.add('pointer-events-none', 'opacity-40');
});
</script>
@endpush
