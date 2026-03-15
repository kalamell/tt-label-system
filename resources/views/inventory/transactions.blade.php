@extends('layouts.app')
@section('title', 'ประวัติ Transaction')
@section('page-title', 'ประวัติรายการเข้า-ออกสต๊อก')

@section('content')
    {{-- Filter bar --}}
    <form method="GET" action="{{ route('inventory.transactions') }}"
          class="bg-white rounded-xl border border-gray-200 p-4 mb-5 flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs text-gray-500 mb-1">ประเภทรายการ</label>
            <select name="type"
                    class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">ทั้งหมด</option>
                <option value="in"     {{ request('type') === 'in'         ? 'selected' : '' }}>รับเข้า (+)</option>
                <option value="out"    {{ request('type') === 'out'        ? 'selected' : '' }}>ตัดออก (-)</option>
                <option value="adjustment" {{ request('type') === 'adjustment' ? 'selected' : '' }}>ปรับปรุง (~)</option>
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">สินค้า</label>
            <select name="product_id"
                    class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">ทุกสินค้า</option>
                @foreach($products as $product)
                    <option value="{{ $product->id }}" {{ request('product_id') == $product->id ? 'selected' : '' }}>
                        {{ $product->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit"
                    class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700">
                กรอง
            </button>
            @if(request('type') || request('product_id'))
                <a href="{{ route('inventory.transactions') }}"
                   class="px-4 py-2 border border-gray-200 text-gray-600 text-sm rounded-lg hover:bg-gray-50">
                    ล้าง
                </a>
            @endif
        </div>

        <div class="ml-auto text-xs text-gray-400 self-center">
            แสดง {{ $transactions->firstItem() }}–{{ $transactions->lastItem() }}
            จากทั้งหมด {{ number_format($transactions->total()) }} รายการ
        </div>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="text-left px-5 py-3 font-medium text-gray-600 w-36">วันที่-เวลา</th>
                    <th class="text-center px-4 py-3 font-medium text-gray-600 w-24">ประเภท</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">สินค้า</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">อ้างอิง / Lot</th>
                    <th class="text-right px-4 py-3 font-medium text-gray-600 w-24">จำนวน</th>
                    <th class="text-right px-4 py-3 font-medium text-gray-600 w-28">คงเหลือหลัง</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">หมายเหตุ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($transactions as $tx)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3 text-xs text-gray-500 whitespace-nowrap">
                            {{ $tx->created_at->format('d/m/Y') }}<br>
                            <span class="text-gray-400">{{ $tx->created_at->format('H:i:s') }}</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($tx->type === 'in')
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">รับเข้า</span>
                            @elseif($tx->type === 'out')
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">ตัดออก</span>
                            @else
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">ปรับปรุง</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($tx->product)
                                <p class="text-gray-800 font-medium text-xs">{{ $tx->product->name }}</p>
                                <p class="text-gray-400 text-xs font-mono">{{ $tx->product->sku }}</p>
                            @else
                                <span class="text-gray-300 text-xs">-</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <p class="text-xs font-mono text-gray-700">{{ $tx->reference ?? '-' }}</p>
                            @if($tx->inventoryLot)
                                <p class="text-xs text-gray-400">Lot {{ $tx->inventoryLot->lot_number }}</p>
                            @endif
                            @if($tx->order)
                                <a href="{{ route('orders.show', $tx->order->id) }}"
                                   class="text-xs text-blue-500 hover:underline">
                                    #{{ $tx->order->order_id }}
                                </a>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <span class="font-mono font-semibold text-sm {{ $tx->type === 'in' ? 'text-green-600' : ($tx->type === 'out' ? 'text-red-600' : ($tx->quantity >= 0 ? 'text-amber-600' : 'text-red-500')) }}">
                                @if($tx->type === 'in')
                                    +{{ number_format($tx->quantity) }}
                                @elseif($tx->type === 'out')
                                    -{{ number_format(abs($tx->quantity)) }}
                                @else
                                    {{ $tx->quantity >= 0 ? '+' : '' }}{{ number_format($tx->quantity) }}
                                @endif
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <span class="font-mono text-sm text-gray-700">{{ number_format($tx->balance_after) }}</span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500 max-w-xs truncate">
                            {{ $tx->notes ?? '-' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-10 text-center text-gray-400 text-sm">
                            ไม่พบรายการ Transaction
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($transactions->hasPages())
        <div class="mt-4">
            {{ $transactions->appends(request()->query())->links() }}
        </div>
    @endif
@endsection
