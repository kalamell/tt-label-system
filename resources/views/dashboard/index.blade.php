@extends('layouts.app')
@section('title', 'Dashboard')
@section('page-title')Dashboard@endsection

@section('content')

    {{-- Expiring Lots Alert --}}
    @if($expiringLots->isNotEmpty())
        <div class="mb-5 bg-amber-50 border border-amber-200 rounded-xl p-4 flex items-start gap-3">
            <svg class="w-5 h-5 text-amber-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
            <div class="flex-1">
                <p class="font-semibold text-amber-800 text-sm mb-1">Lot ใกล้หมดอายุ (ภายใน 30 วัน)</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($expiringLots as $lot)
                        <span class="inline-flex items-center gap-1 text-xs bg-white border border-amber-200 text-amber-700 rounded-lg px-2.5 py-1">
                            <strong>{{ $lot->product->name }}</strong>
                            Lot {{ $lot->lot_number }}
                            · หมด {{ $lot->expiry_date->format('d/m/Y') }}
                            · เหลือ {{ number_format($lot->quantity_remaining) }} ชิ้น
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- Date Selector --}}
    <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap items-center gap-3 mb-5">
        <span class="text-sm text-gray-400">ช่วงวันที่:</span>
        <input type="date" name="date_from" value="{{ $dateFrom }}"
               class="px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
        <span class="text-sm text-gray-400">ถึง</span>
        <input type="date" name="date_to" value="{{ $dateTo }}"
               class="px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
        <button type="submit" class="px-4 py-1.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors shadow-sm">
            ดูข้อมูล
        </button>
        <a href="{{ route('dashboard') }}" class="px-4 py-1.5 bg-white border border-gray-200 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
            วันนี้
        </a>
        <span class="text-sm text-gray-500 ml-1">{{ $periodLabel }}</span>
    </form>

    {{-- Stat Cards แถว 1: ภาพรวม --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-3">
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-400 mb-1">ออเดอร์</p>
            <p class="text-2xl font-bold text-gray-900">{{ number_format($periodOrders) }}</p>
            <p class="text-xs text-gray-400 mt-0.5">รายการ</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-400 mb-1">สินค้า</p>
            <p class="text-2xl font-bold text-purple-600">{{ number_format($periodBoxes) }}</p>
            <p class="text-xs text-gray-400 mt-0.5">ชิ้น</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-400 mb-1">รอพิมพ์ (ทั้งหมด)</p>
            <p class="text-2xl font-bold text-orange-500">{{ number_format($pendingOrders) }}</p>
            <p class="text-xs text-gray-400 mt-0.5">ออเดอร์</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-400 mb-1">พิมพ์แล้ว</p>
            <p class="text-2xl font-bold text-green-600">{{ number_format($periodPrinted) }}</p>
            <p class="text-xs text-gray-400 mt-0.5">ออเดอร์</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-400 mb-1">COD</p>
            <p class="text-2xl font-bold text-rose-500">{{ number_format($periodCod) }}</p>
            <p class="text-xs text-gray-400 mt-0.5">ออเดอร์</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-400 mb-1">โอน</p>
            <p class="text-2xl font-bold text-blue-600">{{ number_format($periodPrepaid) }}</p>
            <p class="text-xs text-gray-400 mt-0.5">ออเดอร์</p>
        </div>
    </div>

    {{-- Stat Cards แถว 2: แยกขนส่ง --}}
    @php
        $carrierTotal = $periodJt + $periodFlash + $periodSpx;
        $periodParams = ['date_from' => $dateFrom, 'date_to' => $dateTo];
    @endphp
    <div class="grid grid-cols-3 gap-4 mb-6">
        <a href="{{ route('orders.index', array_merge($periodParams, ['carrier' => 'JT'])) }}"
           class="bg-blue-50 rounded-xl border border-blue-200 p-4 flex items-center gap-4 hover:bg-blue-100 transition-colors">
            <div class="flex-1 min-w-0">
                <p class="text-xs text-blue-500 mb-1 font-medium">J&amp;T Express</p>
                <p class="text-3xl font-bold text-blue-700">{{ number_format($periodJt) }}</p>
                <p class="text-xs text-blue-400 mt-0.5">ออเดอร์</p>
            </div>
            @if($carrierTotal > 0)
            <div class="text-right flex-shrink-0">
                <p class="text-2xl font-bold text-blue-600">{{ round($periodJt / $carrierTotal * 100) }}<span class="text-base font-normal">%</span></p>
                <p class="text-xs text-blue-400">ของขนส่ง</p>
            </div>
            @endif
        </a>
        <a href="{{ route('orders.index', array_merge($periodParams, ['carrier' => 'FLASH'])) }}"
           class="bg-orange-50 rounded-xl border border-orange-200 p-4 flex items-center gap-4 hover:bg-orange-100 transition-colors">
            <div class="flex-1 min-w-0">
                <p class="text-xs text-orange-500 mb-1 font-medium">Flash Express</p>
                <p class="text-3xl font-bold text-orange-600">{{ number_format($periodFlash) }}</p>
                <p class="text-xs text-orange-400 mt-0.5">ออเดอร์</p>
            </div>
            @if($carrierTotal > 0)
            <div class="text-right flex-shrink-0">
                <p class="text-2xl font-bold text-orange-500">{{ round($periodFlash / $carrierTotal * 100) }}<span class="text-base font-normal">%</span></p>
                <p class="text-xs text-orange-400">ของขนส่ง</p>
            </div>
            @endif
        </a>
        <a href="{{ route('orders.index', array_merge($periodParams, ['carrier' => 'SPX'])) }}"
           class="bg-red-50 rounded-xl border border-red-200 p-4 flex items-center gap-4 hover:bg-red-100 transition-colors">
            <div class="flex-1 min-w-0">
                <p class="text-xs text-red-500 mb-1 font-medium">SPX Express</p>
                <p class="text-3xl font-bold text-red-600">{{ number_format($periodSpx) }}</p>
                <p class="text-xs text-red-400 mt-0.5">ออเดอร์</p>
            </div>
            @if($carrierTotal > 0)
            <div class="text-right flex-shrink-0">
                <p class="text-2xl font-bold text-red-500">{{ round($periodSpx / $carrierTotal * 100) }}<span class="text-base font-normal">%</span></p>
                <p class="text-xs text-red-400">ของขนส่ง</p>
            </div>
            @endif
        </a>
    </div>

    {{-- Row: Trend + Province --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">

        {{-- Trend Chart --}}
        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-gray-800">แนวโน้ม · {{ $periodLabel }}</h3>
                <a href="{{ route('reports.daily') }}" class="text-xs text-blue-600 hover:underline">ดูรายงานเต็ม →</a>
            </div>
            <canvas id="trendChart" height="100"></canvas>
        </div>

        {{-- Top Provinces --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h3 class="font-semibold text-gray-800 mb-4">จังหวัด (Top 6)</h3>
            @php $maxCnt = $periodProvinces->max('cnt') ?: 1; @endphp
            @forelse($periodProvinces as $prov)
                <div class="mb-3">
                    <div class="flex justify-between text-xs mb-0.5">
                        <span class="text-gray-700 font-medium truncate pr-2">{{ $prov->recipient_province }}</span>
                        <span class="text-gray-500 flex-shrink-0">{{ $prov->cnt }} ออเดอร์</span>
                    </div>
                    <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-blue-500 rounded-full"
                             style="width: {{ round($prov->cnt / $maxCnt * 100) }}%"></div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-400 text-center py-8">ยังไม่มีออเดอร์ในช่วงนี้</p>
            @endforelse
        </div>
    </div>

    {{-- Recent Orders --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-gray-800">ออเดอร์ล่าสุด</h3>
            <a href="{{ route('orders.index') }}" class="text-xs text-blue-600 hover:underline">ดูทั้งหมด →</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-400 uppercase border-b">
                        <th class="pb-2 pr-4">ขนส่ง</th>
                        <th class="pb-2 pr-4">Tracking</th>
                        <th class="pb-2 pr-4">ผู้รับ</th>
                        <th class="pb-2 pr-4">จังหวัด</th>
                        <th class="pb-2 pr-4">สินค้า</th>
                        <th class="pb-2 pr-4">ประเภท</th>
                        <th class="pb-2 pr-4">สถานะ</th>
                        <th class="pb-2">Lot</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentOrders as $order)
                        <tr class="border-b border-gray-50 hover:bg-gray-50 cursor-pointer"
                            onclick="window.location='{{ route('orders.show', $order) }}'">
                            <td class="py-2 pr-4">
                                @if($order->carrier === 'FLASH')
                                    <span class="px-1.5 py-0.5 bg-orange-50 text-orange-600 rounded text-xs font-medium">Flash</span>
                                @elseif($order->carrier === 'JT')
                                    <span class="px-1.5 py-0.5 bg-blue-50 text-blue-600 rounded text-xs font-medium">J&amp;T</span>
                                @else
                                    <span class="text-gray-300 text-xs">—</span>
                                @endif
                            </td>
                            <td class="py-2 pr-4 font-mono text-xs text-gray-600">{{ $order->tracking_number }}</td>
                            <td class="py-2 pr-4 text-gray-700">{{ Str::limit($order->recipient_name, 18) }}</td>
                            <td class="py-2 pr-4 text-gray-500 text-xs">{{ $order->recipient_province }}</td>
                            <td class="py-2 pr-4 text-gray-500 text-xs">{{ $order->product ? Str::limit($order->product->name, 20) : '-' }}</td>
                            <td class="py-2 pr-4">
                                @if($order->payment_type === 'COD')
                                    <span class="px-1.5 py-0.5 bg-rose-50 text-rose-600 rounded text-xs font-medium">COD</span>
                                @else
                                    <span class="px-1.5 py-0.5 bg-blue-50 text-blue-600 rounded text-xs font-medium">โอน</span>
                                @endif
                            </td>
                            <td class="py-2 pr-4">
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
                            <td class="py-2 text-xs text-gray-400 font-mono">{{ $order->assigned_lot ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-8 text-gray-400">ยังไม่มีออเดอร์</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const trendData = @json($trend->values());

const ctx = document.getElementById('trendChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: trendData.map(d => d.label),
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
                label: 'จำนวนสินค้า',
                data: trendData.map(d => d.boxes),
                type: 'line',
                borderColor: 'rgb(168,85,247)',
                backgroundColor: 'rgba(168,85,247,0.1)',
                pointRadius: 4,
                pointBackgroundColor: 'rgb(168,85,247)',
                tension: 0.3,
                fill: true,
                yAxisID: 'y2',
            }
        ]
    },
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'top', labels: { font: { size: 11 } } } },
        scales: {
            y:  { beginAtZero: true, stacked: true, ticks: { stepSize: 1, font: { size: 11 } }, grid: { color: '#f3f4f6' }, title: { display: true, text: 'ออเดอร์', font: { size: 11 } } },
            y2: { beginAtZero: true, position: 'right', ticks: { font: { size: 11 } }, grid: { drawOnChartArea: false }, title: { display: true, text: 'จำนวนสินค้า', font: { size: 11 } } },
        }
    }
});
</script>
@endpush
