@extends('layouts.app')
@section('title', 'รับเข้า / จ่ายออก')
@section('page-title', 'รับเข้า / จ่ายออก')

@section('content')
<div class="grid grid-cols-2 gap-6">

    {{-- ===== ฝั่งซ้าย: รับสินค้าเข้าคลัง ===== --}}
    <div>
        <div class="flex items-center gap-2 mb-3">
            <span class="inline-flex w-7 h-7 bg-green-100 text-green-600 rounded-full items-center justify-center text-sm font-bold">+</span>
            <h3 class="text-base font-semibold text-gray-800">รับสินค้าเข้าคลัง</h3>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <form action="{{ route('inventory.receive') }}" method="POST" class="space-y-4">
                @csrf

                {{-- สินค้า --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">สินค้า <span class="text-red-500">*</span></label>
                    <div class="relative mb-2">
                        <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="text" id="receive-search"
                               placeholder="ค้นหาชื่อ, SKU, Seller SKU..."
                               autocomplete="off"
                               class="w-full pl-9 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <select name="product_id" id="receive-select" required
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
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

                {{-- Lot + จำนวน --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Lot Number <span class="text-red-500">*</span></label>
                        <input type="text" name="lot_number" value="{{ old('lot_number') }}" required
                               placeholder="เช่น 03/100"
                               class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">จำนวน (ชิ้น) <span class="text-red-500">*</span></label>
                        <input type="number" name="quantity" value="{{ old('quantity') }}" required min="1"
                               placeholder="200"
                               class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                </div>

                {{-- วันที่รับ + วันหมดอายุ --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">วันที่รับเข้า <span class="text-red-500">*</span></label>
                        <input type="date" name="received_date" value="{{ old('received_date', date('Y-m-d')) }}" required
                               class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">วันหมดอายุ</label>
                        <input type="date" name="expiry_date" value="{{ old('expiry_date') }}"
                               class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                </div>

                {{-- ต้นทุน --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ต้นทุนต่อหน่วย (บาท)</label>
                    <input type="number" name="cost_per_unit" value="{{ old('cost_per_unit', 0) }}" step="0.01" min="0"
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>

                {{-- หมายเหตุ --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">หมายเหตุ</label>
                    <textarea name="notes" rows="2"
                              class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500 resize-none">{{ old('notes') }}</textarea>
                </div>

                <button type="submit"
                        class="w-full py-2.5 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    บันทึกรับเข้าคลัง
                </button>
            </form>
        </div>
    </div>

    {{-- ===== ฝั่งขวา: จ่ายออก ===== --}}
    <div>
        <div class="flex items-center gap-2 mb-3">
            <span class="inline-flex w-7 h-7 bg-red-100 text-red-600 rounded-full items-center justify-center text-sm font-bold">−</span>
            <h3 class="text-base font-semibold text-gray-800">จ่ายออก (ออฟไลน์)</h3>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <form action="{{ route('inventory.issue') }}" method="POST" id="issue-form" class="space-y-4">
                @csrf

                {{-- สินค้า --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">สินค้า <span class="text-red-500">*</span></label>
                    <div class="relative mb-2">
                        <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="text" id="issue-search"
                               placeholder="ค้นหาชื่อ, SKU, Seller SKU..."
                               autocomplete="off"
                               class="w-full pl-9 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
                    </div>
                    <select name="product_id" id="issue-select" required
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-500"
                            onchange="updatePreview()">
                        <option value="">— เลือกสินค้า —</option>
                        @foreach($issueProducts as $item)
                            <option value="{{ $item['product']->id }}"
                                    data-stock="{{ $item['total_stock'] }}"
                                    data-lot="{{ $item['next_lot'] }}"
                                    data-search="{{ strtolower($item['product']->name . ' ' . $item['product']->sku . ' ' . ($item['product']->seller_sku ?? '')) }}">
                                {{ $item['product']->name }} — {{ $item['product']->sku }}{{ $item['product']->seller_sku ? ' · ' . $item['product']->seller_sku : '' }}
                                ({{ number_format($item['total_stock']) }} ชิ้น)
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Stock Preview --}}
                <div id="stock-preview" class="hidden bg-blue-50 border border-blue-200 rounded-xl p-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-blue-500 mb-0.5">สต๊อกคงเหลือ</p>
                            <p class="text-2xl font-bold text-blue-700" id="preview-stock">—</p>
                            <p class="text-xs text-blue-400 mt-0.5">Lot: <span id="preview-lot" class="font-medium">—</span></p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-gray-400 mb-0.5">หลังจ่ายออก</p>
                            <p class="text-2xl font-bold text-gray-700" id="preview-after">—</p>
                        </div>
                    </div>
                </div>

                {{-- จำนวน --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">จำนวนที่จ่ายออก (ชิ้น) <span class="text-red-500">*</span></label>
                    <input type="number" name="quantity" id="qty-input"
                           value="{{ old('quantity', 1) }}" min="1" required
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-500"
                           oninput="updatePreview()">
                </div>

                {{-- ช่องทาง --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">ช่องทาง / ลูกค้า <span class="text-red-500">*</span></label>
                    <div class="flex flex-wrap gap-1.5 mb-2">
                        @foreach(['หน้าร้าน', 'ตัวแทน', 'Line', 'Facebook', 'แจกตัวอย่าง', 'อื่นๆ'] as $ch)
                            <button type="button"
                                    onclick="setChannel('{{ $ch }}')"
                                    class="channel-btn px-2.5 py-1 border border-gray-200 rounded-lg text-xs text-gray-600 hover:border-red-400 hover:text-red-600 hover:bg-red-50 transition-colors">
                                {{ $ch }}
                            </button>
                        @endforeach
                    </div>
                    <input type="text" name="channel" id="channel-input"
                           value="{{ old('channel') }}" placeholder="ระบุช่องทาง..." required
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
                </div>

                {{-- หมายเหตุ --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">หมายเหตุ</label>
                    <textarea name="notes" rows="2"
                              placeholder="เช่น ชื่อลูกค้า, เลข Order..."
                              class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-500 resize-none">{{ old('notes') }}</textarea>
                </div>

                <button type="submit"
                        class="w-full py-2.5 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    บันทึกจ่ายออก
                </button>
            </form>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
    // ===== Receive search =====
    document.getElementById('receive-search').addEventListener('input', function () {
        filterSelect('receive-select', this.value);
    });

    // ===== Issue search =====
    document.getElementById('issue-search').addEventListener('input', function () {
        filterSelect('issue-select', this.value);
        updatePreview();
    });

    function filterSelect(selectId, query) {
        const q = query.toLowerCase().trim();
        const select = document.getElementById(selectId);
        Array.from(select.options).forEach(opt => {
            if (!opt.value) return;
            opt.hidden = q !== '' && !opt.dataset.search.includes(q);
        });
        const sel = select.options[select.selectedIndex];
        if (sel && sel.hidden) select.value = '';
    }

    // ===== Stock preview =====
    function updatePreview() {
        const select = document.getElementById('issue-select');
        const opt    = select.options[select.selectedIndex];
        const qty    = parseInt(document.getElementById('qty-input').value) || 0;

        if (!opt.value) {
            document.getElementById('stock-preview').classList.add('hidden');
            return;
        }

        const stock = parseInt(opt.dataset.stock) || 0;
        const after = stock - qty;

        document.getElementById('preview-stock').textContent = stock.toLocaleString();
        document.getElementById('preview-lot').textContent   = opt.dataset.lot || '—';
        document.getElementById('preview-after').textContent = after >= 0 ? after.toLocaleString() : '—';
        document.getElementById('preview-after').className   =
            'text-2xl font-bold ' + (after < 0 ? 'text-red-600' : (after === 0 ? 'text-orange-500' : 'text-gray-700'));
        document.getElementById('stock-preview').classList.remove('hidden');
    }

    // ===== Channel buttons =====
    function setChannel(name) {
        document.getElementById('channel-input').value = name;
        document.querySelectorAll('.channel-btn').forEach(btn => {
            const active = btn.textContent.trim() === name;
            btn.classList.toggle('border-red-500',  active);
            btn.classList.toggle('bg-red-50',       active);
            btn.classList.toggle('text-red-600',    active);
        });
    }

    // ===== Validate before submit =====
    document.getElementById('issue-form').addEventListener('submit', function (e) {
        const select = document.getElementById('issue-select');
        const opt    = select.options[select.selectedIndex];
        const qty    = parseInt(document.getElementById('qty-input').value) || 0;
        const stock  = parseInt(opt.dataset?.stock) || 0;
        if (opt.value && qty > stock) {
            e.preventDefault();
            alert(`สต๊อกไม่พอ! มีเพียง ${stock.toLocaleString()} ชิ้น แต่ต้องการ ${qty.toLocaleString()} ชิ้น`);
        }
    });
</script>
@endpush
