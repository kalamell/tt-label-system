@extends('layouts.app')
@section('title', 'จ่ายออก (ขายออฟไลน์)')
@section('page-title', 'จ่ายออก / ขายออฟไลน์')

@section('content')

@if(session('error'))
    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">
        {{ session('error') }}
    </div>
@endif

<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-xl border border-gray-200 p-6">

        <p class="text-sm text-gray-500 mb-6">ตัดสต๊อกแบบ FIFO สำหรับการขายหน้าร้าน ตัวแทน หรือแจกตัวอย่าง</p>

        <form action="{{ route('inventory.issue') }}" method="POST" id="issue-form">
            @csrf

            {{-- สินค้า --}}
            <div class="mb-5">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    สินค้า <span class="text-red-500">*</span>
                </label>
                {{-- ช่องค้นหาสินค้า --}}
                <div class="relative mb-2">
                    <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" id="issue-product-search"
                           placeholder="ค้นหาชื่อสินค้า, SKU, Seller SKU..."
                           autocomplete="off"
                           class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <select name="product_id" id="product-select" required
                        class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        onchange="updateStockPreview()">
                    <option value="">— เลือกสินค้า —</option>
                    @foreach($products as $item)
                        <option value="{{ $item['product']->id }}"
                                data-stock="{{ $item['total_stock'] }}"
                                data-lot="{{ $item['next_lot'] }}"
                                data-search="{{ strtolower($item['product']->name . ' ' . $item['product']->sku . ' ' . ($item['product']->seller_sku ?? '')) }}"
                                {{ old('product_id', $selectedProductId) == $item['product']->id ? 'selected' : '' }}>
                            {{ $item['product']->name }} — {{ $item['product']->sku }}{{ $item['product']->seller_sku ? ' · ' . $item['product']->seller_sku : '' }}
                            ({{ number_format($item['total_stock']) }} ชิ้น)
                        </option>
                    @endforeach
                </select>
                @error('product_id')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Stock Preview --}}
            <div id="stock-preview" class="hidden mb-5 bg-blue-50 border border-blue-200 rounded-xl p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-blue-500 mb-0.5">สต๊อกคงเหลือ</p>
                        <p class="text-2xl font-bold text-blue-700" id="preview-stock">—</p>
                        <p class="text-xs text-blue-400 mt-0.5">Lot ถัดไป: <span id="preview-lot" class="font-medium">—</span></p>
                    </div>
                    <div class="text-right" id="preview-after-wrap">
                        <p class="text-xs text-gray-400 mb-0.5">หลังจ่ายออก</p>
                        <p class="text-2xl font-bold text-gray-700" id="preview-after">—</p>
                    </div>
                </div>
            </div>

            {{-- จำนวน --}}
            <div class="mb-5">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    จำนวนที่จ่ายออก (ชิ้น) <span class="text-red-500">*</span>
                </label>
                <input type="number" name="quantity" id="quantity-input"
                       value="{{ old('quantity', 1) }}" min="1" required
                       class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                       oninput="updateStockPreview()">
                @error('quantity')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- ช่องทาง --}}
            <div class="mb-5">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    ช่องทาง / ลูกค้า <span class="text-red-500">*</span>
                </label>
                <div class="flex flex-wrap gap-2 mb-2">
                    @foreach(['หน้าร้าน', 'ตัวแทน', 'Line', 'Facebook', 'แจกตัวอย่าง', 'อื่นๆ'] as $ch)
                        <button type="button"
                                onclick="setChannel('{{ $ch }}')"
                                class="channel-btn px-3 py-1.5 border border-gray-200 rounded-lg text-xs text-gray-600 hover:border-blue-400 hover:text-blue-600 hover:bg-blue-50 transition-colors">
                            {{ $ch }}
                        </button>
                    @endforeach
                </div>
                <input type="text" name="channel" id="channel-input"
                       value="{{ old('channel') }}" placeholder="ระบุช่องทาง..." required
                       class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                @error('channel')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- หมายเหตุ --}}
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">หมายเหตุ (ไม่บังคับ)</label>
                <textarea name="notes" rows="2"
                          placeholder="เช่น ชื่อลูกค้า, เลข Order, รายละเอียดเพิ่มเติม"
                          class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none">{{ old('notes') }}</textarea>
            </div>

            {{-- Actions --}}
            <div class="flex items-center gap-3">
                <button type="submit"
                        class="px-6 py-2.5 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    บันทึกจ่ายออก
                </button>
                <a href="{{ route('inventory.index') }}"
                   class="px-5 py-2.5 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200">
                    ยกเลิก
                </a>
            </div>
        </form>
    </div>
</div>

    {{-- ประวัติการจ่ายออก --}}
    <div class="max-w-2xl mx-auto mt-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">ประวัติการจ่ายออกล่าสุด</h3>
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            @forelse($recentTransactions as $tx)
                <div class="flex items-center gap-3 px-4 py-3 border-b border-gray-50 last:border-0 hover:bg-gray-50">
                    <span class="inline-flex w-7 h-7 bg-red-100 text-red-600 rounded-full items-center justify-center text-xs font-bold flex-shrink-0">−</span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-800 truncate">{{ $tx->product->name ?? '-' }}</p>
                        <p class="text-xs text-gray-400 truncate">{{ $tx->notes }}</p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <p class="text-sm font-bold text-red-600">−{{ number_format($tx->quantity) }}</p>
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
    function updateStockPreview() {
        const select = document.getElementById('product-select');
        const option = select.options[select.selectedIndex];
        const qty    = parseInt(document.getElementById('quantity-input').value) || 0;

        if (!option.value) {
            document.getElementById('stock-preview').classList.add('hidden');
            return;
        }

        const stock = parseInt(option.dataset.stock) || 0;
        const lot   = option.dataset.lot || '—';
        const after = stock - qty;

        document.getElementById('preview-stock').textContent = stock.toLocaleString();
        document.getElementById('preview-lot').textContent   = lot;
        document.getElementById('preview-after').textContent = after >= 0 ? after.toLocaleString() : '—';
        document.getElementById('preview-after').className   = 'text-2xl font-bold ' + (after < 0 ? 'text-red-600' : (after === 0 ? 'text-orange-500' : 'text-gray-700'));
        document.getElementById('stock-preview').classList.remove('hidden');
    }

    function setChannel(name) {
        document.getElementById('channel-input').value = name;
        document.querySelectorAll('.channel-btn').forEach(btn => {
            const active = btn.textContent.trim() === name;
            btn.classList.toggle('border-blue-500', active);
            btn.classList.toggle('bg-blue-50', active);
            btn.classList.toggle('text-blue-600', active);
        });
        document.getElementById('channel-input').focus();
    }

    // กัน submit เมื่อสต๊อกไม่พอ
    document.getElementById('issue-form').addEventListener('submit', function(e) {
        const select = document.getElementById('product-select');
        const option = select.options[select.selectedIndex];
        const qty    = parseInt(document.getElementById('quantity-input').value) || 0;
        const stock  = parseInt(option.dataset?.stock) || 0;

        if (option.value && qty > stock) {
            e.preventDefault();
            alert(`สต๊อกไม่พอ! มีเพียง ${stock.toLocaleString()} ชิ้น แต่ต้องการ ${qty.toLocaleString()} ชิ้น`);
        }
    });

    // Init preview ถ้ามีค่าเดิม
    updateStockPreview();

    // ค้นหาสินค้าใน dropdown
    document.getElementById('issue-product-search').addEventListener('input', function () {
        const q = this.value.toLowerCase().trim();
        const select = document.getElementById('product-select');
        Array.from(select.options).forEach(opt => {
            if (!opt.value) return;
            opt.hidden = q !== '' && !opt.dataset.search.includes(q);
        });
        const selected = select.options[select.selectedIndex];
        if (selected && selected.hidden) {
            select.value = '';
            updateStockPreview();
        }
    });
</script>
@endpush
