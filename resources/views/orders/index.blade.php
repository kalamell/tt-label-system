@extends('layouts.app')
@section('title', 'ออเดอร์ทั้งหมด')
@section('page-title', 'ออเดอร์ / Label')

@section('content')
    {{-- Toolbar --}}
    <div class="bg-white rounded-xl border border-gray-200 p-4 mb-4">
        <form action="{{ route('orders.index') }}" method="GET" class="flex flex-wrap items-center gap-3">
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="ค้นหา Tracking, Order ID, ชื่อผู้รับ..."
                   class="flex-1 min-w-[200px] px-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">

            <select name="status" class="px-4 py-2 border border-gray-200 rounded-lg text-sm">
                <option value="">ทุกสถานะ</option>
                <option value="pending"  {{ request('status') == 'pending'  ? 'selected' : '' }}>รอพิมพ์</option>
                <option value="printed"  {{ request('status') == 'printed'  ? 'selected' : '' }}>พิมพ์แล้ว</option>
                <option value="shipped"  {{ request('status') == 'shipped'  ? 'selected' : '' }}>จัดส่งแล้ว</option>
            </select>

            <select name="carrier" class="px-4 py-2 border border-gray-200 rounded-lg text-sm">
                <option value="">ทุกขนส่ง</option>
                <option value="JT"    {{ request('carrier') == 'JT'    ? 'selected' : '' }}>J&amp;T Express</option>
                <option value="FLASH" {{ request('carrier') == 'FLASH' ? 'selected' : '' }}>Flash Express</option>
                <option value="SPX"   {{ request('carrier') == 'SPX'   ? 'selected' : '' }}>SPX Express</option>
            </select>

            <select name="platform" class="px-4 py-2 border border-gray-200 rounded-lg text-sm">
                <option value="">ทุก Platform</option>
                <option value="TIKTOK"  {{ request('platform') === 'TIKTOK'  ? 'selected' : '' }}>TikTok</option>
                <option value="SHOPEE"  {{ request('platform') === 'SHOPEE'  ? 'selected' : '' }}>Shopee</option>
            </select>

            <input type="date" name="date" value="{{ request('date') }}"
                   class="px-4 py-2 border border-gray-200 rounded-lg text-sm">

            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">
                ค้นหา
            </button>

            @if(request()->hasAny(['search', 'status', 'date', 'carrier', 'platform']))
                <a href="{{ route('orders.index') }}" class="text-sm text-gray-500 hover:text-gray-700">ล้าง</a>
            @endif
        </form>
    </div>

    {{-- Actions --}}
    <div class="flex items-center justify-between mb-4">
        <p class="text-sm text-gray-500">ทั้งหมด {{ number_format($orders->total()) }} รายการ</p>
        <div class="flex gap-2">
            <a href="{{ route('orders.upload.form') }}"
               class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                </svg>
                Upload PDF
            </a>

            {{-- Download ZIP button — hidden until rows are selected --}}
            <button onclick="downloadZip(this)" id="btn-zip"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700 hidden flex items-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed">
                <svg class="w-4 h-4 btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                <svg class="w-4 h-4 btn-spinner animate-spin hidden" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                </svg>
                <span class="btn-text">Download ZIP</span>
            </button>

            {{-- Delete selected button --}}
            <button onclick="deleteSelected()" id="btn-delete"
                    class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700 hidden flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                ลบที่เลือก
            </button>

            {{-- Batch Print button --}}
            <button onclick="printSelected(this)" id="btn-batch-print"
                    class="px-4 py-2 bg-purple-600 text-white rounded-lg text-sm hover:bg-purple-700 hidden flex items-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed">
                <svg class="w-4 h-4 btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                <svg class="w-4 h-4 btn-spinner animate-spin hidden" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                </svg>
                <span class="btn-text">พิมพ์ PDF รวม</span>
            </button>
        </div>
    </div>

    {{-- Select-All Banner (ซ่อนไว้ก่อน) --}}
    <div id="select-all-banner" class="hidden mb-3 bg-blue-50 border border-blue-200 rounded-xl px-4 py-3 flex items-center gap-3 text-sm">
        <span class="text-blue-700">เลือก <strong>{{ $orders->count() }}</strong> รายการในหน้านี้แล้ว — ยังมีอีก {{ number_format($orders->total() - $orders->count()) }} รายการในหน้าอื่น</span>
        <button type="button" onclick="selectAllPages()"
                class="px-3 py-1 bg-blue-600 text-white rounded-lg text-xs font-medium hover:bg-blue-700">
            เลือกทั้งหมด {{ number_format($orders->total()) }} รายการ (ทุกหน้า)
        </button>
        <button type="button" onclick="clearSelectAll()" class="text-blue-400 hover:text-blue-600 text-xs ml-auto">ยกเลิก</button>
    </div>
    <div id="select-all-active-banner" class="hidden mb-3 bg-green-50 border border-green-200 rounded-xl px-4 py-3 flex items-center gap-3 text-sm">
        <svg class="w-4 h-4 text-green-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
        </svg>
        <span class="text-green-700">เลือกทั้งหมด <strong>{{ number_format($orders->total()) }}</strong> รายการ (ทุกหน้า) แล้ว</span>
        <button type="button" onclick="clearSelectAll()" class="text-green-500 hover:text-green-700 text-xs ml-auto">ยกเลิก</button>
    </div>

    {{-- Orders Table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        {{-- Batch print form --}}
        <form id="batch-form" action="{{ route('orders.print.batch') }}" method="POST">
            @foreach(request()->only(['search','status','date','carrier','platform']) as $key => $val)
                @if($val)<input type="hidden" name="{{ $key }}" value="{{ $val }}">@endif
            @endforeach
            @csrf
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-4 py-3 text-left">
                            <input type="checkbox" id="check-all" class="rounded border-gray-300">
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Platform</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ขนส่ง</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tracking</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order ID</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ผู้รับ</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ปลายทาง</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Qty</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">จ่าย</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ship Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">สถานะ</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($orders as $order)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <input type="checkbox" value="{{ $order->id }}"
                                       class="order-check rounded border-gray-300">
                            </td>
                            <td class="px-4 py-3">
                                @if($order->platform === 'SHOPEE')
                                    <span class="px-2 py-0.5 bg-orange-500 text-white rounded text-xs font-medium">Shopee</span>
                                @else
                                    <span class="px-2 py-0.5 bg-black text-white rounded text-xs font-medium">TikTok</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if($order->carrier === 'FLASH')
                                    <span class="px-2 py-0.5 bg-orange-100 text-orange-700 rounded text-xs font-medium">Flash</span>
                                @elseif($order->carrier === 'JT')
                                    <span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs font-medium">J&amp;T</span>
                                @elseif($order->carrier === 'SPX')
                                    <span class="px-2 py-0.5 bg-red-100 text-red-700 rounded text-xs font-medium">SPX</span>
                                @else
                                    <span class="text-gray-300 text-xs">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono text-xs font-medium text-blue-600">
                                <a href="{{ route('orders.show', $order) }}" class="hover:underline">
                                    {{ $order->tracking_number }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500">{{ Str::limit($order->order_id, 15) }}</td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-800">{{ $order->recipient_name }}</p>
                                <p class="text-xs text-gray-400">{{ $order->recipient_phone }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-gray-600">{{ $order->recipient_district }}</p>
                                <p class="text-xs text-gray-400">{{ $order->recipient_province }} {{ $order->recipient_zipcode }}</p>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="text-sm font-semibold text-gray-800">{{ $order->quantity ?? 1 }}</span>
                            </td>
                            <td class="px-4 py-3">
                                @if($order->payment_type == 'COD')
                                    <span class="px-2 py-0.5 bg-red-50 text-red-700 rounded text-xs font-medium">COD</span>
                                @else
                                    <span class="px-2 py-0.5 bg-gray-50 text-gray-600 rounded text-xs">PREPAID</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                                {{ $order->shipping_date ? \Carbon\Carbon::parse($order->shipping_date)->format('d/m/y') : '—' }}
                            </td>
                            <td class="px-4 py-3">
                                @switch($order->status)
                                    @case('pending')
                                        <span class="px-2 py-0.5 bg-yellow-50 text-yellow-700 rounded-full text-xs">รอพิมพ์</span>
                                        @break
                                    @case('printed')
                                        <span class="px-2 py-0.5 bg-blue-50 text-blue-700 rounded-full text-xs">พิมพ์แล้ว</span>
                                        @break
                                    @case('shipped')
                                        <span class="px-2 py-0.5 bg-green-50 text-green-700 rounded-full text-xs">จัดส่งแล้ว</span>
                                        @break
                                    @default
                                        <span class="px-2 py-0.5 bg-gray-50 text-gray-600 rounded-full text-xs">{{ $order->status }}</span>
                                @endswitch
                            </td>
                            <td class="px-4 py-3 text-center">
                                <a href="{{ route('orders.print', $order) }}"
                                   class="print-link inline-flex items-center gap-1 text-purple-600 hover:text-purple-800 text-xs font-medium"
                                   title="พิมพ์ Label"
                                   onclick="startPrintLink(this)">
                                    <svg class="w-3 h-3 animate-spin hidden print-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                                    </svg>
                                    <span class="print-label">พิมพ์</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-12 text-center text-gray-400">
                                ยังไม่มีออเดอร์ — <a href="{{ route('orders.upload.form') }}" class="text-blue-600 hover:underline">Upload PDF เลย</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </form>
    </div>

    {{-- Hidden form for ZIP download --}}
    <form id="zip-form" action="{{ route('orders.download.zip') }}" method="POST" class="hidden">
        @csrf
        @foreach(request()->only(['search','status','date','carrier','platform']) as $key => $val)
            @if($val)<input type="hidden" name="{{ $key }}" value="{{ $val }}">@endif
        @endforeach
    </form>

    {{-- Hidden form for batch delete --}}
    <form id="delete-form" action="{{ route('orders.delete.batch') }}" method="POST" class="hidden">
        @csrf
        @foreach(request()->only(['search','status','date','carrier','platform']) as $key => $val)
            @if($val)<input type="hidden" name="{{ $key }}" value="{{ $val }}">@endif
        @endforeach
    </form>

    {{-- Pagination --}}
    <div class="mt-4">
        {{ $orders->withQueryString()->links() }}
    </div>

@endsection

@push('scripts')
<script>
    let _selectAllPages = false; // สถานะ "เลือกทั้งหมดทุกหน้า"
    const totalOrders = {{ $orders->total() }};
    const pageCount   = {{ $orders->count() }};

    // Select all checkboxes ในหน้านี้
    document.getElementById('check-all')?.addEventListener('change', function() {
        document.querySelectorAll('.order-check').forEach(cb => cb.checked = this.checked);
        _selectAllPages = false;
        toggleBatchBtns();
        toggleSelectAllBanner();
    });

    document.querySelectorAll('.order-check').forEach(cb => {
        cb.addEventListener('change', () => {
            _selectAllPages = false;
            toggleBatchBtns();
            toggleSelectAllBanner();
        });
    });

    function toggleBatchBtns() {
        const checked = _selectAllPages || document.querySelectorAll('.order-check:checked').length > 0;
        document.getElementById('btn-batch-print').classList.toggle('hidden', !checked);
        document.getElementById('btn-zip').classList.toggle('hidden', !checked);
        document.getElementById('btn-delete').classList.toggle('hidden', !checked);
    }

    function toggleSelectAllBanner() {
        const checkedCount = document.querySelectorAll('.order-check:checked').length;
        const banner        = document.getElementById('select-all-banner');
        const activeBanner  = document.getElementById('select-all-active-banner');

        if (_selectAllPages) {
            banner.classList.add('hidden');
            activeBanner.classList.remove('hidden');
        } else if (checkedCount === pageCount && checkedCount > 0 && totalOrders > pageCount) {
            // เลือกครบหน้า แต่ยังมีหน้าอื่น → แสดง banner ให้เลือกทั้งหมด
            banner.classList.remove('hidden');
            activeBanner.classList.add('hidden');
        } else {
            banner.classList.add('hidden');
            activeBanner.classList.add('hidden');
        }
    }

    function selectAllPages() {
        _selectAllPages = true;
        toggleBatchBtns();
        toggleSelectAllBanner();
    }

    function clearSelectAll() {
        _selectAllPages = false;
        document.querySelectorAll('.order-check').forEach(cb => cb.checked = false);
        document.getElementById('check-all').checked = false;
        toggleBatchBtns();
        toggleSelectAllBanner();
    }

    function setBtnLoading(btn, text) {
        btn.disabled = true;
        btn.querySelector('.btn-icon').classList.add('hidden');
        btn.querySelector('.btn-spinner').classList.remove('hidden');
        btn.querySelector('.btn-text').textContent = text;
    }

    function resetBtn(btn, text) {
        btn.disabled = false;
        btn.querySelector('.btn-icon').classList.remove('hidden');
        btn.querySelector('.btn-spinner').classList.add('hidden');
        btn.querySelector('.btn-text').textContent = text;
    }

    function _injectOrderIds(form) {
        // เก็บค่า checked ก่อน (ต้องทำก่อนลบ)
        const checkedIds = Array.from(document.querySelectorAll('.order-check:checked')).map(cb => cb.value);

        // ลบเฉพาะ injected hidden inputs ที่ใส่ไว้ก่อนหน้า (ไม่แตะ checkbox จริง)
        form.querySelectorAll('input[data-injected]').forEach(el => el.remove());

        if (_selectAllPages) {
            const input = document.createElement('input');
            input.type  = 'hidden';
            input.name  = 'select_all';
            input.value = '1';
            input.dataset.injected = '1';
            form.appendChild(input);
        } else {
            checkedIds.forEach(id => {
                const input = document.createElement('input');
                input.type  = 'hidden';
                input.name  = 'order_ids[]';
                input.value = id;
                input.dataset.injected = '1';
                form.appendChild(input);
            });
        }
    }

    function printSelected(btn) {
        _injectOrderIds(document.getElementById('batch-form'));
        setBtnLoading(btn, 'กำลังสร้าง PDF...');
        document.getElementById('batch-form').submit();
        setTimeout(() => resetBtn(btn, 'พิมพ์ PDF รวม'), 8000);
    }

    function downloadZip(btn) {
        const form = document.getElementById('zip-form');
        _injectOrderIds(form);
        setBtnLoading(btn, 'กำลังสร้าง ZIP...');
        form.submit();
        setTimeout(() => resetBtn(btn, 'Download ZIP'), 15000);
    }

    function deleteSelected() {
        const count = _selectAllPages ? totalOrders : document.querySelectorAll('.order-check:checked').length;
        if (count === 0) return;

        if (!confirm(`ต้องการลบ ${count} ออเดอร์ที่เลือก?\nระบบจะคืนสต๊อก FIFO ให้อัตโนมัติ`)) return;

        const form = document.getElementById('delete-form');
        _injectOrderIds(form);
        form.submit();
    }

    function startPrintLink(link) {
        link.querySelector('.print-spin').classList.remove('hidden');
        link.querySelector('.print-label').textContent = '...';
        link.classList.add('pointer-events-none', 'opacity-60');
        setTimeout(() => {
            link.querySelector('.print-spin').classList.add('hidden');
            link.querySelector('.print-label').textContent = 'พิมพ์';
            link.classList.remove('pointer-events-none', 'opacity-60');
        }, 5000);
    }
</script>
@endpush
