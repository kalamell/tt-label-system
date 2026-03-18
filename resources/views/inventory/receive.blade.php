@extends('layouts.app')
@section('title', 'รับสินค้าเข้าคลัง')
@section('page-title', 'รับสินค้าเข้าคลัง (สร้าง Lot ใหม่)')

@section('content')
    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <form action="{{ route('inventory.receive') }}" method="POST" class="space-y-5">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">สินค้า <span class="text-red-500">*</span></label>
                    {{-- ช่องค้นหาสินค้า --}}
                    <div class="relative mb-2">
                        <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="text" id="receive-product-search"
                               placeholder="ค้นหาชื่อสินค้า, SKU, Seller SKU..."
                               autocomplete="off"
                               class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <select name="product_id" id="receive-product-select" required
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">— เลือกสินค้า —</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}"
                                    data-search="{{ strtolower($product->name . ' ' . $product->sku . ' ' . ($product->seller_sku ?? '')) }}"
                                    {{ old('product_id') == $product->id ? 'selected' : '' }}>
                                {{ $product->name }} — {{ $product->sku }}{{ $product->seller_sku ? ' · ' . $product->seller_sku : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Lot Number</label>
                        <input type="text" name="lot_number" value="{{ old('lot_number') }}" required
                               placeholder="เช่น 03/100"
                               class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">จำนวน (ชิ้น)</label>
                        <input type="number" name="quantity" value="{{ old('quantity') }}" required min="1"
                               placeholder="200"
                               class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">วันที่รับเข้า</label>
                        <input type="date" name="received_date" value="{{ old('received_date', date('Y-m-d')) }}" required
                               class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">วันหมดอายุ (ถ้ามี)</label>
                        <input type="date" name="expiry_date" value="{{ old('expiry_date') }}"
                               class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ต้นทุนต่อหน่วย (บาท)</label>
                    <input type="number" name="cost_per_unit" value="{{ old('cost_per_unit', 0) }}" step="0.01" min="0"
                           class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">หมายเหตุ</label>
                    <textarea name="notes" rows="3"
                              class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">{{ old('notes') }}</textarea>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button type="submit"
                            class="px-6 py-2.5 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700">
                        บันทึกรับเข้าคลัง
                    </button>
                    <a href="{{ route('inventory.index') }}" class="text-sm text-gray-500 hover:text-gray-700">ยกเลิก</a>
                </div>
            </form>
        </div>
    </div>
    {{-- ประวัติการรับเข้า --}}
    <div class="max-w-2xl mx-auto mt-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">ประวัติการรับเข้าล่าสุด</h3>
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            @forelse($recentTransactions as $tx)
                <div class="flex items-center gap-3 px-4 py-3 border-b border-gray-50 last:border-0 hover:bg-gray-50">
                    <span class="inline-flex w-7 h-7 bg-green-100 text-green-600 rounded-full items-center justify-center text-xs font-bold flex-shrink-0">+</span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-800 truncate">{{ $tx->product->name ?? '-' }}</p>
                        <p class="text-xs text-gray-400">
                            Lot {{ $tx->inventoryLot->lot_number ?? '-' }}
                            · {{ $tx->reference }}
                        </p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <p class="text-sm font-bold text-green-600">+{{ number_format($tx->quantity) }}</p>
                        <p class="text-xs text-gray-400">{{ $tx->created_at->format('d/m/Y H:i') }}</p>
                    </div>
                </div>
            @empty
                <div class="px-4 py-8 text-center text-sm text-gray-400">ยังไม่มีประวัติ</div>
            @endforelse
        </div>
    </div>

@endsection

@push('scripts')
<script>
    document.getElementById('receive-product-search').addEventListener('input', function () {
        const q = this.value.toLowerCase().trim();
        const select = document.getElementById('receive-product-select');
        Array.from(select.options).forEach(opt => {
            if (!opt.value) return;
            opt.hidden = q !== '' && !opt.dataset.search.includes(q);
        });
        // reset selection ถ้า option ที่เลือกอยู่ถูก hide
        const selected = select.options[select.selectedIndex];
        if (selected && selected.hidden) {
            select.value = '';
        }
    });
</script>
@endpush
