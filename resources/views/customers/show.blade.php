@extends('layouts.app')

@section('title', 'ข้อมูลลูกค้า')
@section('page-title', 'ข้อมูลลูกค้า')

@section('content')
<div class="space-y-6">

    {{-- Back --}}
    <a href="{{ route('customers.index') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">
        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        รายชื่อลูกค้า
    </a>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Profile Card --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
            <div class="flex items-center space-x-4">
                <div class="w-14 h-14 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 text-xl font-bold">
                    {{ mb_substr($customer->name, 0, 1) }}
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900 text-lg">{{ $customer->name ?: 'ไม่มีชื่อ' }}</h3>
                    <p class="text-gray-500 text-sm font-mono">{{ $customer->phone ?: 'ไม่มีเบอร์' }}</p>
                </div>
            </div>

            <div class="border-t border-gray-100 pt-4 space-y-2 text-sm">
                @if($customer->address)
                <div>
                    <span class="text-gray-500">ที่อยู่:</span>
                    <span class="text-gray-700 ml-1">{{ $customer->address }}</span>
                </div>
                @endif
                @if($customer->district)
                <div>
                    <span class="text-gray-500">อำเภอ:</span>
                    <span class="text-gray-700 ml-1">{{ $customer->district }}</span>
                </div>
                @endif
                @if($customer->province)
                <div>
                    <span class="text-gray-500">จังหวัด:</span>
                    <span class="text-gray-700 ml-1">{{ $customer->province }}</span>
                </div>
                @endif
                @if($customer->zipcode)
                <div>
                    <span class="text-gray-500">รหัสไปรษณีย์:</span>
                    <span class="text-gray-700 ml-1">{{ $customer->zipcode }}</span>
                </div>
                @endif
            </div>

            @if($customer->notes)
            <div class="border-t border-gray-100 pt-4">
                <p class="text-xs text-gray-500 mb-1">หมายเหตุ</p>
                <p class="text-sm text-gray-700">{{ $customer->notes }}</p>
            </div>
            @endif
        </div>

        {{-- Stats --}}
        <div class="lg:col-span-2 space-y-4">
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
                    <p class="text-2xl font-bold text-blue-600">{{ $stats['total_orders'] }}</p>
                    <p class="text-xs text-gray-500 mt-1">ออเดอร์ทั้งหมด</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
                    <p class="text-2xl font-bold text-orange-500">{{ $stats['cod_count'] }}</p>
                    <p class="text-xs text-gray-500 mt-1">COD</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
                    <p class="text-2xl font-bold text-green-600">{{ $stats['prepaid_count'] }}</p>
                    <p class="text-xs text-gray-500 mt-1">Prepaid</p>
                </div>
            </div>

            {{-- สินค้าที่ซื้อ --}}
            @if($stats['products']->isNotEmpty())
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <h4 class="text-sm font-semibold text-gray-700 mb-3">สินค้าที่ซื้อ</h4>
                <div class="space-y-2">
                    @foreach($stats['products'] as $sku => $cnt)
                    <div class="flex justify-between items-center text-sm">
                        <span class="font-mono text-gray-700 text-xs truncate">{{ $sku }}</span>
                        <span class="text-gray-500 text-xs ml-2 whitespace-nowrap">{{ $cnt }} ชิ้น</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- Order History --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="font-semibold text-gray-800">ประวัติออเดอร์ (ล่าสุด {{ $orders->count() }} รายการ)</h3>
        </div>

        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Tracking</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Order ID</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Seller SKU</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600">จ่าย</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600">สถานะ</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">วันที่</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($orders as $order)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ $order->tracking_number }}</td>
                    <td class="px-4 py-3 text-xs text-gray-500">{{ $order->order_id }}</td>
                    <td class="px-4 py-3 font-mono text-xs text-gray-700">
                        {{ $order->seller_sku ?? $order->product_sku ?? '-' }}
                        <span class="text-gray-400 ml-1">x{{ $order->quantity }}</span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        @if($order->payment_type === 'COD')
                            <span class="inline-block bg-orange-100 text-orange-700 text-xs px-2 py-0.5 rounded-full">COD</span>
                        @else
                            <span class="inline-block bg-green-100 text-green-700 text-xs px-2 py-0.5 rounded-full">Prepaid</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-block text-xs px-2 py-0.5 rounded-full
                            {{ $order->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : '' }}
                            {{ $order->status === 'printed' ? 'bg-blue-100 text-blue-700' : '' }}
                            {{ $order->status === 'shipped' ? 'bg-purple-100 text-purple-700' : '' }}
                            {{ $order->status === 'delivered' ? 'bg-green-100 text-green-700' : '' }}
                            {{ $order->status === 'cancelled' ? 'bg-red-100 text-red-700' : '' }}
                        ">
                            {{ $order->status }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500">
                        {{ $order->shipping_date?->format('d/m/Y') ?? $order->created_at->format('d/m/Y') }}
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('orders.show', $order) }}" class="text-blue-600 hover:underline text-xs">ดู</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-8 text-center text-gray-400 text-sm">ยังไม่มีออเดอร์</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
@endsection
