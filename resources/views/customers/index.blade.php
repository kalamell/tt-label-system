@extends('layouts.app')

@section('title', 'รายชื่อลูกค้า')
@section('page-title', 'รายชื่อลูกค้า')

@section('content')
<div class="space-y-4">

    {{-- Header Stats --}}
    <div class="flex items-center justify-between">
        <p class="text-sm text-gray-500">ลูกค้าทั้งหมด <span class="font-semibold text-gray-900">{{ number_format($totalCount) }}</span> ราย</p>
    </div>

    {{-- Search & Filter --}}
    <form method="GET" class="flex gap-3">
        <input type="text" name="search" value="{{ request('search') }}"
               placeholder="ค้นหาชื่อ หรือเบอร์โทร..."
               class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">

        <select name="province"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">-- ทุกจังหวัด --</option>
            @foreach($provinces as $prov)
                <option value="{{ $prov }}" @selected(request('province') === $prov)>{{ $prov }}</option>
            @endforeach
        </select>

        <button type="submit"
                class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">
            ค้นหา
        </button>

        @if(request('search') || request('province'))
            <a href="{{ route('customers.index') }}"
               class="border border-gray-300 text-gray-600 px-4 py-2 rounded-lg text-sm hover:bg-gray-50">
                ล้าง
            </a>
        @endif
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">ชื่อลูกค้า</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">เบอร์โทร</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">จังหวัด</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600">ออเดอร์ทั้งหมด</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">ออเดอร์ล่าสุด</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">หมายเหตุ</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($customers as $customer)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-900">{{ $customer->name ?: '-' }}</td>
                    <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $customer->phone ?: '-' }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $customer->province ?? '-' }}</td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                            {{ $customer->total_orders }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-gray-500 text-xs">
                        {{ $customer->last_order_at?->diffForHumans() ?? '-' }}
                    </td>
                    <td class="px-4 py-3 text-gray-500 text-xs truncate max-w-xs">
                        {{ $customer->notes ?? '-' }}
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('customers.show', $customer) }}"
                           class="text-blue-600 hover:underline text-xs">
                            ดูรายละเอียด
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-10 text-center text-gray-400">ยังไม่มีข้อมูลลูกค้า</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($customers->hasPages())
    <div class="flex justify-center">
        {{ $customers->links() }}
    </div>
    @endif

</div>
@endsection
