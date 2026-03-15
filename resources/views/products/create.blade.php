@extends('layouts.app')
@section('title', 'เพิ่มสินค้าใหม่')
@section('page-title', 'เพิ่มสินค้าใหม่')

@section('content')
    <div class="max-w-2xl">
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <form action="{{ route('products.store') }}" method="POST" class="space-y-5">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        ชื่อสินค้า <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           placeholder="เช่น ที่ตรวจครรภ์ 4 แบบ"
                           class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('name') border-red-400 @enderror">
                    @error('name')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                    <p class="text-xs text-gray-400 mt-1">ชื่อสินค้านี้จะ <strong>ไม่แสดง</strong> ใน Label ที่พิมพ์ เก็บไว้ใช้ภายในระบบเท่านั้น</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            SKU <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="sku" value="{{ old('sku') }}" required
                               placeholder="เช่น PRG-TEST-4IN1"
                               class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 @error('sku') border-red-400 @enderror">
                        @error('sku')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Seller SKU (TikTok)</label>
                        <input type="text" name="seller_sku" value="{{ old('seller_sku') }}"
                               placeholder="เช่น TT-PRG-4"
                               class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">คำอธิบาย</label>
                    <textarea name="description" rows="3" placeholder="รายละเอียดสินค้า (ไม่บังคับ)"
                              class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">{{ old('description') }}</textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ราคาขาย (บาท)</label>
                        <input type="number" name="price" value="{{ old('price', 0) }}" step="0.01" min="0"
                               class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            สต๊อกขั้นต่ำ (แจ้งเตือน) <span class="text-red-500">*</span>
                        </label>
                        <input type="number" name="min_stock" value="{{ old('min_stock', 10) }}" required min="0"
                               class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('min_stock') border-red-400 @enderror">
                        @error('min_stock')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                        <p class="text-xs text-gray-400 mt-1">แจ้งเตือนเมื่อสต๊อกต่ำกว่าจำนวนนี้</p>
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                    <button type="submit"
                            class="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                        บันทึกสินค้า
                    </button>
                    <a href="{{ route('products.index') }}" class="text-sm text-gray-500 hover:text-gray-700">ยกเลิก</a>
                </div>
            </form>
        </div>
    </div>
@endsection
