@extends('layouts.app')
@section('title', 'รายงานยอดบิลรายวัน')
@section('page-title', 'รายงานยอดบิลรายวัน')

@push('styles')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
@endpush

@section('content')

{{-- Filter --}}
<div class="bg-white rounded-xl border border-gray-200 p-5 mb-6">
    <form method="GET" action="{{ route('reports.daily') }}" class="flex flex-wrap items-end gap-4">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">ตั้งแต่วันที่</label>
            <input type="date" name="date_from" value="{{ $dateFrom }}"
                   class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">ถึงวันที่</label>
            <input type="date" name="date_to" value="{{ $dateTo }}"
                   class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="flex gap-2">
            <button type="submit"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                ดูรายงาน
            </button>
            {{-- Shortcuts --}}
            <a href="{{ route('reports.daily', ['date_from' => today()->toDateString(), 'date_to' => today()->toDateString()]) }}"
               class="px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-50">วันนี้</a>
            <a href="{{ route('reports.daily', ['date_from' => now()->startOfWeek()->toDateString(), 'date_to' => today()->toDateString()]) }}"
               class="px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-50">สัปดาห์นี้</a>
            <a href="{{ route('reports.daily', ['date_from' => now()->startOfMonth()->toDateString(), 'date_to' => today()->toDateString()]) }}"
               class="px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-50">เดือนนี้</a>
        </div>
        <div class="ml-auto">
            <a href="{{ route('reports.export', ['date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
               class="inline-flex items-center gap-2 px-4 py-2 border border-green-500 text-green-600 rounded-lg text-sm font-medium hover:bg-green-50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Export CSV
            </a>
        </div>
    </form>
</div>

{{-- Summary Cards แถว 1: ภาพรวม --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-3">
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <p class="text-sm text-gray-500">ออเดอร์ทั้งหมด</p>
        <p class="text-3xl font-bold text-gray-900 mt-1">{{ number_format($totalOrders) }}</p>
        <p class="text-xs text-gray-400 mt-1">รายการ</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <p class="text-sm text-gray-500">จำนวนกล่อง</p>
        <p class="text-3xl font-bold text-blue-600 mt-1">{{ number_format($totalBoxes) }}</p>
        <p class="text-xs text-gray-400 mt-1">ชิ้น/กล่อง</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <p class="text-sm text-gray-500">COD</p>
        <p class="text-3xl font-bold text-orange-500 mt-1">{{ number_format($codCount) }}</p>
        <p class="text-xs text-gray-400 mt-1">รายการ</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <p class="text-sm text-gray-500">PREPAID</p>
        <p class="text-3xl font-bold text-green-600 mt-1">{{ number_format($prepaidCount) }}</p>
        <p class="text-xs text-gray-400 mt-1">รายการ</p>
    </div>
</div>

{{-- Summary Cards แถว 2: แยกขนส่ง --}}
@php $carrierTotal = $jtCount + $flashCount; @endphp
<div class="grid grid-cols-2 gap-4 mb-6">
    <div class="bg-blue-50 rounded-xl border border-blue-200 p-5 flex items-center gap-4">
        <div class="flex-1">
            <p class="text-sm text-blue-500 font-medium">J&amp;T Express</p>
            <p class="text-3xl font-bold text-blue-700 mt-1">{{ number_format($jtCount) }}</p>
            <p class="text-xs text-blue-400 mt-1">ออเดอร์</p>
        </div>
        @if($carrierTotal > 0)
        <div class="text-right flex-shrink-0">
            <p class="text-2xl font-bold text-blue-600">{{ round($jtCount / $carrierTotal * 100) }}<span class="text-base font-normal">%</span></p>
            <p class="text-xs text-blue-400">ของขนส่งทั้งหมด</p>
        </div>
        @endif
    </div>
    <div class="bg-orange-50 rounded-xl border border-orange-200 p-5 flex items-center gap-4">
        <div class="flex-1">
            <p class="text-sm text-orange-500 font-medium">Flash Express</p>
            <p class="text-3xl font-bold text-orange-600 mt-1">{{ number_format($flashCount) }}</p>
            <p class="text-xs text-orange-400 mt-1">ออเดอร์</p>
        </div>
        @if($carrierTotal > 0)
        <div class="text-right flex-shrink-0">
            <p class="text-2xl font-bold text-orange-500">{{ round($flashCount / $carrierTotal * 100) }}<span class="text-base font-normal">%</span></p>
            <p class="text-xs text-orange-400">ของขนส่งทั้งหมด</p>
        </div>
        @endif
    </div>
</div>

{{-- กราฟ 30 วัน --}}
<div class="bg-white rounded-xl border border-gray-200 p-5 mb-6">
    <h3 class="font-semibold text-gray-800 mb-4">ออเดอร์ย้อนหลัง 30 วัน</h3>
    <div style="height:200px">
        <canvas id="trendChart"></canvas>
    </div>
</div>

{{-- สรุปรายวัน + สรุปสินค้า + จังหวัด --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

    {{-- สรุปรายวัน --}}
    <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 p-5">
        <h3 class="font-semibold text-gray-800 mb-3">สรุปรายวัน</h3>
        @if($dailySummary->isEmpty())
            <p class="text-sm text-gray-400 text-center py-6">ไม่มีข้อมูล</p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs text-gray-400 uppercase border-b text-left">
                        <th class="pb-2 pr-4">วันที่</th>
                        <th class="pb-2 pr-4 text-right">ออเดอร์</th>
                        <th class="pb-2 pr-4 text-right">กล่อง</th>
                        <th class="pb-2 pr-4 text-right">COD</th>
                        <th class="pb-2 pr-4 text-right">PREPAID</th>
                        <th class="pb-2 pr-4 text-right text-blue-400">J&amp;T</th>
                        <th class="pb-2 text-right text-orange-400">Flash</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($dailySummary as $row)
                    <tr class="border-b border-gray-50 hover:bg-gray-50">
                        <td class="py-2.5 pr-4 font-medium">
                            <a href="{{ route('reports.daily', ['date_from' => $row->date, 'date_to' => $row->date]) }}"
                               class="text-blue-600 hover:underline">
                                {{ \Carbon\Carbon::parse($row->date)->locale('th')->isoFormat('D MMM YYYY') }}
                            </a>
                        </td>
                        <td class="py-2.5 pr-4 text-right font-semibold">{{ number_format($row->orders) }}</td>
                        <td class="py-2.5 pr-4 text-right text-blue-600 font-semibold">{{ number_format($row->boxes) }}</td>
                        <td class="py-2.5 pr-4 text-right text-orange-500">{{ number_format($row->cod_count) }}</td>
                        <td class="py-2.5 pr-4 text-right text-green-600">{{ number_format($row->prepaid_count) }}</td>
                        <td class="py-2.5 pr-4 text-right text-blue-600 font-medium">{{ number_format($row->jt_count) }}</td>
                        <td class="py-2.5 text-right text-orange-500 font-medium">{{ number_format($row->flash_count) }}</td>
                    </tr>
                    @endforeach
                    {{-- รวม --}}
                    @if($dailySummary->count() > 1)
                    <tr class="font-semibold bg-gray-50">
                        <td class="py-2.5 pr-4">รวม</td>
                        <td class="py-2.5 pr-4 text-right">{{ number_format($dailySummary->sum('orders')) }}</td>
                        <td class="py-2.5 pr-4 text-right text-blue-600">{{ number_format($dailySummary->sum('boxes')) }}</td>
                        <td class="py-2.5 pr-4 text-right text-orange-500">{{ number_format($dailySummary->sum('cod_count')) }}</td>
                        <td class="py-2.5 pr-4 text-right text-green-600">{{ number_format($dailySummary->sum('prepaid_count')) }}</td>
                        <td class="py-2.5 pr-4 text-right text-blue-600">{{ number_format($dailySummary->sum('jt_count')) }}</td>
                        <td class="py-2.5 text-right text-orange-500">{{ number_format($dailySummary->sum('flash_count')) }}</td>
                    </tr>
                    @endif
                </tbody>
            </table>
        @endif
    </div>

    {{-- สรุปจังหวัด --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h3 class="font-semibold text-gray-800 mb-3">จังหวัดยอดนิยม</h3>
        @if($provinceSummary->isEmpty())
            <p class="text-sm text-gray-400 text-center py-6">ไม่มีข้อมูล</p>
        @else
            @php $maxCnt = $provinceSummary->max('cnt'); @endphp
            @foreach($provinceSummary as $prov)
            <div class="mb-2.5">
                <div class="flex justify-between text-sm mb-1">
                    <span class="text-gray-700">{{ $prov->recipient_province }}</span>
                    <span class="font-semibold text-gray-800">{{ number_format($prov->cnt) }}</span>
                </div>
                <div class="h-2 bg-gray-100 rounded-full">
                    <div class="h-2 bg-blue-400 rounded-full"
                         style="width: {{ $maxCnt > 0 ? round($prov->cnt / $maxCnt * 100) : 0 }}%"></div>
                </div>
            </div>
            @endforeach
        @endif
    </div>
</div>

{{-- สรุปสินค้า --}}
<div class="bg-white rounded-xl border border-gray-200 p-5 mb-6">
    <h3 class="font-semibold text-gray-800 mb-3">สรุปตามสินค้า</h3>
    @if($productSummary->isEmpty())
        <p class="text-sm text-gray-400 text-center py-4">ไม่มีข้อมูล</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs text-gray-400 uppercase border-b text-left">
                        <th class="pb-2 pr-4">สินค้า</th>
                        <th class="pb-2 pr-4">Seller SKU</th>
                        <th class="pb-2 pr-4 text-right">ออเดอร์</th>
                        <th class="pb-2 text-right">กล่อง/ชิ้น</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($productSummary as $row)
                    <tr class="border-b border-gray-50 hover:bg-gray-50">
                        <td class="py-2.5 pr-4 font-medium text-gray-800">
                            {{ $row->product?->name ?? Str::limit($row->product_name ?? '-', 40) }}
                        </td>
                        <td class="py-2.5 pr-4 text-gray-500 font-mono text-xs">
                            {{ Str::limit($row->seller_sku ?? '-', 30) }}
                        </td>
                        <td class="py-2.5 pr-4 text-right">{{ number_format($row->orders) }}</td>
                        <td class="py-2.5 text-right font-semibold text-blue-600">{{ number_format($row->boxes) }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="font-semibold bg-gray-50">
                    <tr>
                        <td colspan="2" class="py-2.5 pr-4 text-gray-700">รวม</td>
                        <td class="py-2.5 pr-4 text-right">{{ number_format($productSummary->sum('orders')) }}</td>
                        <td class="py-2.5 text-right text-blue-600">{{ number_format($productSummary->sum('boxes')) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>

{{-- รายการออเดอร์ทั้งหมด --}}
<div class="bg-white rounded-xl border border-gray-200 p-5">
    <div class="flex items-center justify-between mb-3">
        <h3 class="font-semibold text-gray-800">รายการออเดอร์ ({{ number_format($ordersInRange->count()) }} รายการ)</h3>
        <a href="{{ route('reports.export', ['date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
           class="text-sm text-green-600 hover:underline flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            Export CSV
        </a>
    </div>
    @if($ordersInRange->isEmpty())
        <p class="text-sm text-gray-400 text-center py-6">ไม่มีออเดอร์ในช่วงวันที่เลือก</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs text-gray-400 uppercase border-b text-left">
                        <th class="pb-2 pr-3">วันที่</th>
                        <th class="pb-2 pr-3">ขนส่ง</th>
                        <th class="pb-2 pr-3">Tracking</th>
                        <th class="pb-2 pr-3">ผู้รับ</th>
                        <th class="pb-2 pr-3">จังหวัด</th>
                        <th class="pb-2 pr-3">สินค้า</th>
                        <th class="pb-2 pr-3 text-center">จำนวน</th>
                        <th class="pb-2 pr-3 text-center">ชำระ</th>
                        <th class="pb-2 text-center">สถานะ</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($ordersInRange as $order)
                    <tr class="border-b border-gray-50 hover:bg-gray-50">
                        <td class="py-2 pr-3 text-xs text-gray-500 whitespace-nowrap">
                            {{ ($order->shipping_date ?? $order->created_at)->format('d/m/Y') }}
                        </td>
                        <td class="py-2 pr-3">
                            @if($order->carrier === 'FLASH')
                                <span class="px-1.5 py-0.5 bg-orange-50 text-orange-600 rounded text-xs font-medium">Flash</span>
                            @elseif($order->carrier === 'JT')
                                <span class="px-1.5 py-0.5 bg-blue-50 text-blue-600 rounded text-xs font-medium">J&amp;T</span>
                            @else
                                <span class="text-gray-300 text-xs">—</span>
                            @endif
                        </td>
                        <td class="py-2 pr-3 font-mono text-xs">
                            <a href="{{ route('orders.show', $order) }}" class="text-blue-600 hover:underline">
                                {{ $order->tracking_number ?? '-' }}
                            </a>
                        </td>
                        <td class="py-2 pr-3 text-gray-700">{{ Str::limit($order->recipient_name ?? '-', 16) }}</td>
                        <td class="py-2 pr-3 text-gray-500 text-xs">{{ $order->recipient_province ?? '-' }}</td>
                        <td class="py-2 pr-3 text-gray-600 text-xs">
                            {{ Str::limit($order->product?->name ?? $order->product_name ?? '-', 25) }}
                        </td>
                        <td class="py-2 pr-3 text-center font-semibold">{{ $order->quantity }}</td>
                        <td class="py-2 pr-3 text-center">
                            @if($order->payment_type === 'COD')
                                <span class="px-2 py-0.5 bg-orange-50 text-orange-600 rounded-full text-xs">COD</span>
                            @else
                                <span class="px-2 py-0.5 bg-green-50 text-green-600 rounded-full text-xs">PREPAID</span>
                            @endif
                        </td>
                        <td class="py-2 text-center">
                            @switch($order->status)
                                @case('pending')
                                    <span class="px-2 py-0.5 bg-yellow-50 text-yellow-700 rounded-full text-xs">รอพิมพ์</span>@break
                                @case('printed')
                                    <span class="px-2 py-0.5 bg-blue-50 text-blue-700 rounded-full text-xs">พิมพ์แล้ว</span>@break
                                @case('shipped')
                                    <span class="px-2 py-0.5 bg-green-50 text-green-700 rounded-full text-xs">จัดส่งแล้ว</span>@break
                                @default
                                    <span class="px-2 py-0.5 bg-gray-50 text-gray-600 rounded-full text-xs">{{ $order->status }}</span>
                            @endswitch
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@endsection

@push('scripts')
<script>
const trendData = @json($trendDays);
const labels  = trendData.map(d => d.label);
const orders  = trendData.map(d => d.orders);
const boxes   = trendData.map(d => d.boxes);

new Chart(document.getElementById('trendChart'), {
    type: 'bar',
    data: {
        labels,
        datasets: [
            {
                label: 'J&T',
                data: trendData.map(d => d.jt_count),
                backgroundColor: 'rgba(59,130,246,0.75)',
                borderRadius: 3,
                stack: 'orders',
                yAxisID: 'y',
            },
            {
                label: 'Flash',
                data: trendData.map(d => d.flash_count),
                backgroundColor: 'rgba(249,115,22,0.75)',
                borderRadius: 3,
                stack: 'orders',
                yAxisID: 'y',
            },
            {
                label: 'กล่อง',
                data: boxes,
                type: 'line',
                borderColor: 'rgba(234,179,8,0.9)',
                backgroundColor: 'rgba(234,179,8,0.1)',
                pointRadius: 3,
                tension: 0.3,
                yAxisID: 'y1',
            },
        ],
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'top' } },
        scales: {
            y:  { beginAtZero: true, stacked: true, position: 'left',  title: { display: true, text: 'ออเดอร์' } },
            y1: { beginAtZero: true, position: 'right', title: { display: true, text: 'กล่อง' }, grid: { drawOnChartArea: false } },
        },
    },
});
</script>
@endpush
