@extends('layouts.app')
@section('title', 'รับสินค้าเข้าคลัง')
@section('page-title', 'รับสินค้าเข้าคลัง (สร้าง Lot ใหม่)')

@section('content')
    <div class="max-w-2xl">
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <form action="{{ route('inventory.receive') }}" method="POST" class="space-y-5">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">สินค้า</label>
                    <select name="product_id" required
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">-- เลือกสินค้า --</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}" {{ old('product_id') == $product->id ? 'selected' : '' }}>
                                {{ $product->name }} (SKU: {{ $product->sku }})
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
@endsection
