@extends('layouts.app')
@section('title', 'สต๊อก: ' . $summary['product']->name)
@section('page-title', 'สต๊อก FIFO: ' . $summary['product']->name)

@section('content')
    @php
        $product = $summary['product'];
        $activeLots = $summary['active_lots'];
        $nearExpiryLots = $summary['near_expiry_lots'];
    @endphp

    {{-- แจ้งเตือนสต๊อกต่ำ --}}
    @if($summary['is_low_stock'])
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <span>สต๊อกต่ำกว่าขั้นต่ำ! เหลือ <strong>{{ $summary['total_stock'] }}</strong> ชิ้น (ขั้นต่ำ {{ $product->min_stock }} ชิ้น)</span>
        </div>
    @endif

    {{-- แจ้งเตือน Lot ใกล้หมดอายุ --}}
    @if($nearExpiryLots->isNotEmpty())
        <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-lg mb-4">
            <p class="font-medium text-sm mb-1">Lot ใกล้หมดอายุ (ภายใน 30 วัน)</p>
            @foreach($nearExpiryLots as $lot)
                <p class="text-xs">• Lot {{ $lot->lot_number }} — หมดอายุ {{ $lot->expiry_date->format('d/m/Y') }}
                    (เหลือ {{ now()->diffInDays($lot->expiry_date) }} วัน, {{ $lot->quantity_remaining }} ชิ้น)</p>
            @endforeach
        </div>
    @endif

    {{-- Stat Cards --}}
    <div class="grid grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 mb-1">สต๊อกคงเหลือ</p>
            <p class="text-2xl font-bold {{ $summary['is_low_stock'] ? 'text-red-600' : 'text-gray-900' }}">
                {{ number_format($summary['total_stock']) }}
            </p>
            <p class="text-xs text-gray-400">ชิ้น</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 mb-1">รับเข้าทั้งหมด</p>
            <p class="text-2xl font-bold text-gray-900">{{ number_format($summary['total_received']) }}</p>
            <p class="text-xs text-gray-400">ชิ้น</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 mb-1">ขายออกทั้งหมด</p>
            <p class="text-2xl font-bold text-gray-900">{{ number_format($summary['total_sold']) }}</p>
            <p class="text-xs text-gray-400">ชิ้น</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 mb-1">SKU</p>
            <p class="text-lg font-mono font-bold text-gray-900">{{ $product->sku }}</p>
            <p class="text-xs text-gray-400">{{ $product->seller_sku ?? 'ไม่มี Seller SKU' }}</p>
        </div>
    </div>

    <div class="grid grid-cols-5 gap-6">
        {{-- Lots FIFO --}}
        <div class="col-span-3">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-gray-700">Lots ที่ Active (เรียง FIFO)</h3>
                <a href="{{ route('inventory.receive.form') }}"
                   class="px-3 py-1.5 bg-green-600 text-white text-xs font-medium rounded-lg hover:bg-green-700">
                    + รับเข้าคลัง
                </a>
            </div>

            @if($activeLots->isEmpty())
                <div class="bg-white rounded-xl border border-gray-200 p-8 text-center text-gray-400 text-sm">
                    ไม่มีสต๊อกในคลัง
                </div>
            @else
                <div class="space-y-3">
                    @foreach($activeLots as $index => $lot)
                        @php
                            $percent = $lot->quantity_received > 0
                                ? round(($lot->quantity_remaining / $lot->quantity_received) * 100)
                                : 0;
                            $isFirst = $index === 0;
                        @endphp
                        <div class="bg-white rounded-xl border {{ $isFirst ? 'border-blue-300 ring-1 ring-blue-200' : 'border-gray-200' }} p-4">
                            <div class="flex items-start justify-between mb-2">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-bold text-gray-900">Lot {{ $lot->lot_number }}</span>
                                        @if($isFirst)
                                            <span class="text-xs px-2 py-0.5 bg-blue-100 text-blue-700 rounded-full">ตัดก่อน</span>
                                        @else
                                            <span class="text-xs text-gray-400">ลำดับที่ {{ $index + 1 }}</span>
                                        @endif
                                        @if($lot->is_near_expiry)
                                            <span class="text-xs px-2 py-0.5 bg-amber-100 text-amber-700 rounded-full">ใกล้หมดอายุ</span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        รับเข้า: {{ $lot->received_date->format('d/m/Y') }}
                                        @if($lot->expiry_date)
                                            · หมดอายุ: {{ $lot->expiry_date->format('d/m/Y') }}
                                        @endif
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-lg font-bold text-gray-900">{{ number_format($lot->quantity_remaining) }}</p>
                                    <p class="text-xs text-gray-400">จาก {{ number_format($lot->quantity_received) }}</p>
                                </div>
                            </div>

                            {{-- Progress bar --}}
                            <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden mb-3">
                                <div class="h-full rounded-full {{ $percent <= 20 ? 'bg-red-500' : ($percent <= 50 ? 'bg-amber-400' : 'bg-green-500') }}"
                                     style="width: {{ $percent }}%"></div>
                            </div>

                            {{-- Adjust form --}}
                            <details class="group">
                                <summary class="text-xs text-gray-400 cursor-pointer hover:text-gray-600 list-none flex items-center gap-1">
                                    <svg class="w-3 h-3 group-open:rotate-90 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                    ปรับปรุงสต๊อก
                                </summary>
                                <form action="{{ route('inventory.adjust', $lot->id) }}" method="POST"
                                      class="mt-3 pt-3 border-t border-gray-100 flex gap-2 items-end">
                                    @csrf
                                    <div class="flex-1">
                                        <label class="block text-xs text-gray-500 mb-1">จำนวนใหม่ (ชิ้น)</label>
                                        <input type="number" name="new_quantity" min="0"
                                               value="{{ $lot->quantity_remaining }}"
                                               class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                    <div class="flex-1">
                                        <label class="block text-xs text-gray-500 mb-1">เหตุผล</label>
                                        <input type="text" name="reason" placeholder="เช่น นับสต๊อกจริง"
                                               class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                    <button type="submit"
                                            class="px-4 py-2 bg-gray-700 text-white text-xs rounded-lg hover:bg-gray-800 whitespace-nowrap">
                                        บันทึก
                                    </button>
                                </form>
                            </details>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Transaction History --}}
        <div class="col-span-2">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-gray-700">ประวัติรายการ</h3>
                <a href="{{ route('inventory.transactions') }}?product_id={{ $product->id }}"
                   class="text-xs text-blue-600 hover:underline">ดูทั้งหมด</a>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-50 overflow-hidden">
                @forelse($transactions as $tx)
                    <div class="px-4 py-3 flex items-start gap-3">
                        {{-- Icon by type --}}
                        <div class="flex-shrink-0 mt-0.5">
                            @if($tx->type === 'in')
                                <span class="inline-flex w-6 h-6 bg-green-100 text-green-600 rounded-full items-center justify-center text-xs font-bold">+</span>
                            @elseif($tx->type === 'out')
                                <span class="inline-flex w-6 h-6 bg-red-100 text-red-600 rounded-full items-center justify-center text-xs font-bold">-</span>
                            @else
                                <span class="inline-flex w-6 h-6 bg-amber-100 text-amber-600 rounded-full items-center justify-center text-xs font-bold">~</span>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-baseline justify-between gap-2">
                                <p class="text-xs font-medium text-gray-800 truncate">{{ $tx->reference ?? $tx->notes }}</p>
                                <span class="text-xs font-mono font-semibold {{ $tx->type === 'in' ? 'text-green-600' : ($tx->type === 'out' ? 'text-red-600' : 'text-amber-600') }} flex-shrink-0">
                                    {{ $tx->type === 'in' ? '+' : ($tx->quantity < 0 ? '' : ($tx->type === 'out' ? '-' : ($tx->quantity >= 0 ? '+' : ''))) }}{{ abs($tx->quantity) }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="text-xs text-gray-400">{{ $tx->created_at->format('d/m H:i') }}</span>
                                <span class="text-xs text-gray-300">·</span>
                                <span class="text-xs text-gray-400">คงเหลือ {{ $tx->balance_after }}</span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-4 py-8 text-center text-sm text-gray-400">ยังไม่มีรายการ</div>
                @endforelse
            </div>

            @if($transactions->hasPages())
                <div class="mt-3">{{ $transactions->links() }}</div>
            @endif
        </div>
    </div>

    {{-- Footer actions --}}
    <div class="mt-6 flex items-center gap-3">
        <a href="{{ route('inventory.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← กลับสต๊อกรวม</a>
        <span class="text-gray-300">|</span>
        <a href="{{ route('products.edit', $product->id) }}" class="text-sm text-gray-500 hover:text-gray-700">แก้ไขสินค้า</a>
    </div>
@endsection
