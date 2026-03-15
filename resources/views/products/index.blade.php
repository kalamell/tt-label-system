@extends('layouts.app')
@section('title', 'รายการสินค้า')
@section('page-title', 'รายการสินค้าทั้งหมด')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <p class="text-sm text-gray-500">สินค้าทั้งหมด {{ $products->count() }} รายการ</p>
        <a href="{{ route('products.create') }}"
           class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
            </svg>
            เพิ่มสินค้า
        </a>
    </div>

    @if($products->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
            <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
            </svg>
            <p class="text-gray-500 mb-4">ยังไม่มีสินค้าในระบบ</p>
            <a href="{{ route('products.create') }}" class="text-blue-600 text-sm hover:underline">เพิ่มสินค้าแรก</a>
        </div>
    @else
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="text-left px-5 py-3 font-medium text-gray-600">ชื่อสินค้า</th>
                        <th class="text-left px-5 py-3 font-medium text-gray-600">SKU</th>
                        <th class="text-left px-5 py-3 font-medium text-gray-600">Seller SKU</th>
                        <th class="text-center px-5 py-3 font-medium text-gray-600">Lots ที่ active</th>
                        <th class="text-center px-5 py-3 font-medium text-gray-600">ออเดอร์ทั้งหมด</th>
                        <th class="text-center px-5 py-3 font-medium text-gray-600">ราคา</th>
                        <th class="text-center px-5 py-3 font-medium text-gray-600">สต๊อกขั้นต่ำ</th>
                        <th class="text-center px-5 py-3 font-medium text-gray-600">สถานะ</th>
                        <th class="text-center px-5 py-3 font-medium text-gray-600">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($products as $product)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                                <div class="font-medium text-gray-900">{{ $product->name }}</div>
                                @if($product->description)
                                    <div class="text-xs text-gray-400 mt-0.5 truncate max-w-xs">{{ $product->description }}</div>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <span class="font-mono text-xs bg-gray-100 px-2 py-1 rounded">{{ $product->sku }}</span>
                            </td>
                            <td class="px-5 py-3 text-gray-500 text-xs">
                                {{ $product->seller_sku ?? '-' }}
                            </td>
                            <td class="px-5 py-3 text-center">
                                <span class="text-gray-700">{{ $product->active_lots_count }}</span>
                            </td>
                            <td class="px-5 py-3 text-center">
                                <span class="text-gray-700">{{ $product->orders_count }}</span>
                            </td>
                            <td class="px-5 py-3 text-center text-gray-700">
                                {{ $product->price > 0 ? number_format($product->price, 2) . ' ฿' : '-' }}
                            </td>
                            <td class="px-5 py-3 text-center text-gray-700">
                                {{ $product->min_stock }} ชิ้น
                            </td>
                            <td class="px-5 py-3 text-center">
                                @if($product->is_active)
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">เปิดใช้งาน</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">ปิด</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="{{ route('inventory.show', $product->id) }}"
                                       class="text-xs text-blue-600 hover:text-blue-800 hover:underline">สต๊อก</a>
                                    <span class="text-gray-300">|</span>
                                    <a href="{{ route('products.edit', $product->id) }}"
                                       class="text-xs text-gray-600 hover:text-gray-800 hover:underline">แก้ไข</a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
