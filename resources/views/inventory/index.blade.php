@extends('layouts.app')
@section('title', 'สต๊อกสินค้า FIFO')
@section('page-title', 'สต๊อกสินค้า FIFO')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <p class="text-sm text-gray-500">สินค้าทั้งหมด {{ $products->count() }} รายการ</p>
        <a href="{{ route('inventory.receive.form') }}"
           class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
            </svg>
            รับสินค้าเข้าคลัง
        </a>
    </div>

    @if($products->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
            <p class="text-gray-400 mb-4">ยังไม่มีสินค้า</p>
            <a href="{{ route('products.create') }}" class="text-blue-600 hover:underline text-sm">เพิ่มสินค้าใหม่</a>
        </div>
    @else
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">สินค้า / SKU</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Lot</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600">วันรับเข้า</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600">รับเข้า</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600">คงเหลือ</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600" style="min-width:120px">% คงเหลือ</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600">หมดอายุ</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600">รวมคงเหลือ</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($products as $item)
                    @php
                        $lots = $item['active_lots'];
                        $rowspan = max($lots->count(), 1);
                    @endphp

                    @if($lots->isEmpty())
                    <tr class="hover:bg-gray-50">
                        {{-- Product cell --}}
                        <td class="px-4 py-3 align-top">
                            <p class="font-medium text-gray-800">{{ $item['product']->name }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ $item['product']->sku }}
                                @if($item['product']->seller_sku)
                                    · {{ $item['product']->seller_sku }}
                                @endif
                            </p>
                        </td>
                        <td colspan="6" class="px-4 py-3 text-gray-400 text-xs">ไม่มี Lot ที่ active</td>
                        <td class="px-4 py-3 text-center">
                            <span class="text-2xl font-bold text-gray-300">0</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('inventory.show', $item['product']->id) }}"
                               class="text-blue-600 hover:underline text-xs">ดู</a>
                        </td>
                    </tr>
                    @else
                        @foreach($lots as $index => $lot)
                        @php $pct = $lot->quantity_received > 0 ? ($lot->quantity_remaining / $lot->quantity_received) * 100 : 0; @endphp
                        <tr class="hover:bg-gray-50 {{ $index === 0 ? '' : 'border-t-0' }}">

                            {{-- Product cell: แสดงแค่ row แรกของ product นี้ --}}
                            @if($index === 0)
                            <td class="px-4 py-3 align-top" rowspan="{{ $rowspan }}">
                                <p class="font-medium text-gray-800">{{ $item['product']->name }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">{{ $item['product']->sku }}
                                    @if($item['product']->seller_sku)
                                        · {{ $item['product']->seller_sku }}
                                    @endif
                                </p>
                                @if($item['is_low_stock'])
                                    <span class="inline-block mt-1 px-1.5 py-0.5 bg-red-50 text-red-600 rounded text-xs">
                                        สต๊อกต่ำ!
                                    </span>
                                @endif
                            </td>
                            @endif

                            {{-- Lot --}}
                            <td class="px-4 py-3">
                                <span class="font-mono text-sm font-semibold text-gray-800">{{ $lot->lot_number }}</span>
                                @if($index === 0)
                                    <span class="ml-1 text-xs text-blue-600 font-medium">← ตัดก่อน</span>
                                @endif
                            </td>

                            {{-- วันรับเข้า --}}
                            <td class="px-4 py-3 text-center text-xs text-gray-500">
                                {{ $lot->received_date->format('d/m/Y') }}
                            </td>

                            {{-- รับเข้า --}}
                            <td class="px-4 py-3 text-center text-gray-500">
                                {{ number_format($lot->quantity_received) }}
                            </td>

                            {{-- คงเหลือ --}}
                            <td class="px-4 py-3 text-center font-bold {{ $lot->quantity_remaining < 10 ? 'text-red-600' : 'text-green-600' }}">
                                {{ number_format($lot->quantity_remaining) }}
                            </td>

                            {{-- Progress --}}
                            <td class="px-4 py-3">
                                <div class="w-full bg-gray-200 rounded-full h-1.5">
                                    <div class="h-1.5 rounded-full {{ $pct > 30 ? 'bg-green-500' : ($pct > 10 ? 'bg-amber-500' : 'bg-red-500') }}"
                                         style="width: {{ $pct }}%"></div>
                                </div>
                                <p class="text-xs text-gray-400 mt-1">{{ number_format($pct, 0) }}%</p>
                            </td>

                            {{-- หมดอายุ --}}
                            <td class="px-4 py-3 text-center text-xs">
                                @if($lot->expiry_date)
                                    <span class="{{ $lot->is_expired ? 'text-red-600 font-semibold' : ($lot->is_near_expiry ? 'text-amber-600 font-medium' : 'text-gray-500') }}">
                                        {{ $lot->expiry_date->format('d/m/Y') }}
                                        @if($lot->is_expired) <br><span class="text-red-500">(หมดอายุ)</span>
                                        @elseif($lot->is_near_expiry) <br><span class="text-amber-500">(ใกล้หมด)</span>
                                        @endif
                                    </span>
                                @else
                                    <span class="text-gray-300">-</span>
                                @endif
                            </td>

                            {{-- รวมคงเหลือ: แสดงแค่ row แรก --}}
                            @if($index === 0)
                            <td class="px-4 py-3 text-center align-top" rowspan="{{ $rowspan }}">
                                <span class="text-2xl font-bold {{ $item['is_low_stock'] ? 'text-red-600' : 'text-gray-800' }}">
                                    {{ number_format($item['total_stock']) }}
                                </span>
                                <p class="text-xs text-gray-400">ชิ้น</p>
                            </td>
                            @endif

                            {{-- Actions: แสดงแค่ row แรก --}}
                            @if($index === 0)
                            <td class="px-4 py-3 text-right align-top" rowspan="{{ $rowspan }}">
                                <a href="{{ route('inventory.show', $item['product']->id) }}"
                                   class="text-blue-600 hover:underline text-xs">ดูรายละเอียด</a>
                            </td>
                            @endif
                        </tr>
                        @endforeach
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
@endsection
