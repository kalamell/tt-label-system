@extends('layouts.app')
@section('title', 'แก้ไขสินค้า')
@section('page-title', 'แก้ไขสินค้า: ' . $product->name)

@section('content')
    <div class="max-w-2xl">
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <form action="{{ route('products.update', $product->id) }}" method="POST" class="space-y-5">
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        ชื่อสินค้า <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="name" value="{{ old('name', $product->name) }}" required
                           class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('name') border-red-400 @enderror">
                    @error('name')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                    <p class="text-xs text-gray-400 mt-1">ชื่อสินค้านี้จะ <strong>ไม่แสดง</strong> ใน Label ที่พิมพ์</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            SKU <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="sku" value="{{ old('sku', $product->sku) }}" required
                               class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 @error('sku') border-red-400 @enderror">
                        @error('sku')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Seller SKU (TikTok)</label>
                        <input type="text" name="seller_sku" value="{{ old('seller_sku', $product->seller_sku) }}"
                               class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">คำอธิบาย</label>
                    <textarea name="description" rows="3"
                              class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">{{ old('description', $product->description) }}</textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ราคาขาย (บาท)</label>
                        <input type="number" name="price" value="{{ old('price', $product->price) }}" step="0.01" min="0"
                               class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            สต๊อกขั้นต่ำ (แจ้งเตือน) <span class="text-red-500">*</span>
                        </label>
                        <input type="number" name="min_stock" value="{{ old('min_stock', $product->min_stock) }}" required min="0"
                               class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('min_stock') border-red-400 @enderror">
                        @error('min_stock')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1"
                               {{ old('is_active', $product->is_active) ? 'checked' : '' }}
                               class="w-4 h-4 rounded text-blue-600">
                        <span class="text-sm text-gray-700">เปิดใช้งานสินค้านี้</span>
                    </label>
                </div>

                <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                    <button type="submit"
                            class="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                        บันทึกการเปลี่ยนแปลง
                    </button>
                    <a href="{{ route('products.index') }}" class="text-sm text-gray-500 hover:text-gray-700">ยกเลิก</a>
                </div>
            </form>
        </div>

        {{-- ข้อมูลเพิ่มเติม --}}
        <div class="mt-4 bg-gray-50 rounded-xl border border-gray-200 p-4 text-xs text-gray-500 space-y-1">
            <p>สร้างเมื่อ: {{ $product->created_at->format('d/m/Y H:i') }}</p>
            <p>แก้ไขล่าสุด: {{ $product->updated_at->format('d/m/Y H:i') }}</p>
            <p class="pt-1">
                <a href="{{ route('inventory.show', $product->id) }}" class="text-blue-600 hover:underline">ดูสต๊อก FIFO ของสินค้านี้ →</a>
            </p>
        </div>
    </div>
@endsection
