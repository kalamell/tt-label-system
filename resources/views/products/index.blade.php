@extends('layouts.app')
@section('title', 'รายการสินค้า')
@section('page-title', 'รายการสินค้าทั้งหมด')

@section('content')
    @if(session('success'))
        <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">{{ session('error') }}</div>
    @endif

    {{-- Search form (แยกออกจาก bulk-form เพื่อป้องกัน nested form) --}}
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
            <p class="text-sm text-gray-500">สินค้าทั้งหมด {{ $products->count() }} รายการ</p>
            @if($search)
                <span class="text-xs text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full">ค้นหา: "{{ $search }}"</span>
                <a href="{{ route('products.index') }}" class="text-xs text-gray-400 hover:text-gray-600">× ล้าง</a>
            @endif
            <span id="selected-count" class="hidden text-xs font-medium text-red-600 bg-red-50 px-2 py-0.5 rounded-full"></span>
        </div>
        <div class="flex items-center gap-2">
            <form method="GET" action="{{ route('products.index') }}" class="flex items-center gap-2">
                <div class="relative">
                    <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="q" value="{{ $search }}" placeholder="ค้นหาชื่อ, SKU..."
                           class="pl-9 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 w-56">
                </div>
                <button type="submit" class="px-3 py-2 bg-gray-100 text-gray-700 text-sm rounded-lg hover:bg-gray-200">ค้นหา</button>
            </form>
            <button type="submit" form="bulk-form" id="bulk-delete-btn"
                    onclick="return confirmBulk()"
                    class="hidden px-3 py-2 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700 flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                ลบที่เลือก
            </button>
            <a href="{{ route('products.create') }}"
               class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                เพิ่มสินค้า
            </a>
        </div>
    </div>

    <form id="bulk-form" method="POST" action="{{ route('products.bulk-destroy') }}">
        @csrf @method('DELETE')

        @if($products->isEmpty())
            <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
                <p class="text-gray-500 mb-4">ยังไม่มีสินค้าในระบบ</p>
                <a href="{{ route('products.create') }}" class="text-blue-600 text-sm hover:underline">เพิ่มสินค้าแรก</a>
            </div>
        @else
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th class="px-4 py-3 w-8">
                                <input type="checkbox" id="check-all" class="rounded border-gray-300 cursor-pointer"
                                       onclick="toggleAll(this)">
                            </th>
                            <th class="text-left px-4 py-3 font-medium text-gray-600">ชื่อสินค้า</th>
                            <th class="text-left px-4 py-3 font-medium text-gray-600">SKU</th>
                            <th class="text-left px-4 py-3 font-medium text-gray-600">Seller SKU</th>
                            <th class="text-center px-4 py-3 font-medium text-gray-600">Lots</th>
                            <th class="text-center px-4 py-3 font-medium text-gray-600">ออเดอร์</th>
                            <th class="text-center px-4 py-3 font-medium text-gray-600">สถานะ</th>
                            <th class="text-center px-4 py-3 font-medium text-gray-600">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($products as $product)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <input type="checkbox" name="ids[]" value="{{ $product->id }}"
                                           class="row-check rounded border-gray-300 cursor-pointer"
                                           onchange="updateBulk()">
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900">{{ $product->name }}</div>
                                    @if($product->description)
                                        <div class="text-xs text-gray-400 mt-0.5 truncate max-w-xs">{{ $product->description }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="font-mono text-xs bg-gray-100 px-2 py-1 rounded">{{ $product->sku }}</span>
                                </td>
                                <td class="px-4 py-3 text-gray-500 text-xs">{{ $product->seller_sku ?? '-' }}</td>
                                <td class="px-4 py-3 text-center text-gray-700">{{ $product->active_lots_count }}</td>
                                <td class="px-4 py-3 text-center text-gray-700">{{ $product->orders_count }}</td>
                                <td class="px-4 py-3 text-center">
                                    @if($product->is_active)
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">เปิด</span>
                                    @else
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">ปิด</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <a href="{{ route('inventory.show', $product->id) }}"
                                           class="text-xs text-blue-600 hover:underline">สต๊อก</a>
                                        <span class="text-gray-300">|</span>
                                        <a href="{{ route('products.edit', $product->id) }}"
                                           class="text-xs text-gray-600 hover:underline">แก้ไข</a>
                                        <span class="text-gray-300">|</span>
                                        <form method="POST" action="{{ route('products.destroy', $product->id) }}"
                                              onsubmit="return confirm('ลบ \"{{ addslashes($product->name) }}\" ?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-xs text-red-500 hover:text-red-700 hover:underline">ลบ</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </form>

    <script>
        function toggleAll(cb) {
            document.querySelectorAll('.row-check').forEach(c => c.checked = cb.checked);
            updateBulk();
        }
        function updateBulk() {
            const checked = document.querySelectorAll('.row-check:checked');
            const btn = document.getElementById('bulk-delete-btn');
            const cnt = document.getElementById('selected-count');
            if (checked.length > 0) {
                btn.classList.remove('hidden');
                cnt.classList.remove('hidden');
                cnt.textContent = `เลือก ${checked.length} รายการ`;
            } else {
                btn.classList.add('hidden');
                cnt.classList.add('hidden');
            }
            document.getElementById('check-all').indeterminate =
                checked.length > 0 && checked.length < document.querySelectorAll('.row-check').length;
        }
        function confirmBulk() {
            const n = document.querySelectorAll('.row-check:checked').length;
            return confirm(`ยืนยันลบ ${n} รายการ?`);
        }
    </script>
@endsection
