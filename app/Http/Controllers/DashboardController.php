<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Services\FifoInventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request, FifoInventoryService $fifoService)
    {
        // ── Period selector ────────────────────────────────────────
        $period = in_array($request->get('period'), ['today', 'week', 'month', 'year'])
            ? $request->get('period')
            : 'today';

        [$dateFrom, $dateTo, $periodLabel, $periodShort] = match($period) {
            'week'  => [now()->startOfWeek()->toDateString(),  today()->toDateString(), 'สัปดาห์นี้', 'สัปดาห์'],
            'month' => [now()->startOfMonth()->toDateString(), today()->toDateString(), 'เดือนนี้',   'เดือน'],
            'year'  => [now()->startOfYear()->toDateString(),  today()->toDateString(), 'ปีนี้',       'ปี'],
            default => [today()->toDateString(),               today()->toDateString(), 'วันนี้',      'วัน'],
        };

        // ── Stats ตาม period ───────────────────────────────────────
        $statsQ = Order::whereRaw(
                'COALESCE(DATE(shipping_date), DATE(created_at)) BETWEEN ? AND ?',
                [$dateFrom, $dateTo]
            )
            ->where('status', '!=', 'cancelled');

        $periodOrders  = (clone $statsQ)->count();
        $periodBoxes   = (clone $statsQ)->sum('quantity');
        $periodCod     = (clone $statsQ)->where('payment_type', 'COD')->count();
        $periodPrepaid = (clone $statsQ)->where('payment_type', 'PREPAID')->count();
        $periodPrinted = (clone $statsQ)->where('label_printed', true)->count();
        $periodJt      = (clone $statsQ)->where('carrier', 'JT')->count();
        $periodFlash   = (clone $statsQ)->where('carrier', 'FLASH')->count();

        // รอพิมพ์ — ยอดรวมเสมอ (ไม่ขึ้นกับ period)
        $pendingOrders = Order::pending()->count();

        // ── Trend Chart ────────────────────────────────────────────
        if ($period === 'year') {
            // Trend รายเดือน (12 เดือน)
            $trendRaw = Order::selectRaw('
                    DATE_FORMAT(COALESCE(shipping_date, DATE(created_at)), "%Y-%m") AS period_key,
                    COUNT(*) AS orders,
                    SUM(quantity) AS boxes,
                    SUM(carrier = "JT") AS jt_count,
                    SUM(carrier = "FLASH") AS flash_count
                ')
                ->whereYear(DB::raw('COALESCE(shipping_date, created_at)'), now()->year)
                ->where('status', '!=', 'cancelled')
                ->groupByRaw('DATE_FORMAT(COALESCE(shipping_date, DATE(created_at)), "%Y-%m")')
                ->orderBy('period_key')
                ->get()->keyBy('period_key');

            $thaiMonths = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.',
                               'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
            $trend = collect();
            for ($m = 1; $m <= 12; $m++) {
                $key = now()->year . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);
                $trend->push([
                    'label'       => $thaiMonths[$m],
                    'orders'      => $trendRaw[$key]->orders      ?? 0,
                    'boxes'       => $trendRaw[$key]->boxes        ?? 0,
                    'jt_count'    => $trendRaw[$key]->jt_count    ?? 0,
                    'flash_count' => $trendRaw[$key]->flash_count ?? 0,
                ]);
            }
        } else {
            // Trend รายวัน — today/week = 7 วัน, month = 30 วัน
            $trendDays = $period === 'month' ? 29 : 6;
            $since     = now()->subDays($trendDays)->toDateString();

            $trendRaw = Order::selectRaw('
                    COALESCE(DATE(shipping_date), DATE(created_at)) AS period_key,
                    COUNT(*) AS orders,
                    SUM(quantity) AS boxes,
                    SUM(carrier = "JT") AS jt_count,
                    SUM(carrier = "FLASH") AS flash_count
                ')
                ->whereRaw('COALESCE(DATE(shipping_date), DATE(created_at)) >= ?', [$since])
                ->where('status', '!=', 'cancelled')
                ->groupByRaw('COALESCE(DATE(shipping_date), DATE(created_at))')
                ->orderBy('period_key')
                ->get()->keyBy('period_key');

            $trend = collect();
            for ($i = $trendDays; $i >= 0; $i--) {
                $d = now()->subDays($i)->toDateString();
                $trend->push([
                    'label'       => Carbon::parse($d)->format('d/m'),
                    'orders'      => $trendRaw[$d]->orders      ?? 0,
                    'boxes'       => $trendRaw[$d]->boxes        ?? 0,
                    'jt_count'    => $trendRaw[$d]->jt_count    ?? 0,
                    'flash_count' => $trendRaw[$d]->flash_count ?? 0,
                ]);
            }
        }

        // ── สรุปสินค้าตาม period ───────────────────────────────────
        $periodProducts = Order::selectRaw('product_id, COUNT(*) AS orders, SUM(quantity) AS boxes')
            ->whereRaw('COALESCE(DATE(shipping_date), DATE(created_at)) BETWEEN ? AND ?', [$dateFrom, $dateTo])
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('product_id')
            ->groupBy('product_id')
            ->with('product')
            ->orderByRaw('SUM(quantity) DESC')
            ->get();

        // ── จังหวัด Top 6 ตาม period ──────────────────────────────
        $periodProvinces = Order::selectRaw('recipient_province, COUNT(*) AS cnt')
            ->whereRaw('COALESCE(DATE(shipping_date), DATE(created_at)) BETWEEN ? AND ?', [$dateFrom, $dateTo])
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('recipient_province')
            ->groupBy('recipient_province')
            ->orderBy('cnt', 'desc')
            ->limit(6)->get();

        // ── สต๊อก ──────────────────────────────────────────────────
        $products = Product::active()->get()->map(fn($p) => [
            'product'     => $p,
            'total_stock' => $p->total_stock,
            'is_low_stock'=> $p->is_low_stock,
        ]);

        // ── ออเดอร์ล่าสุด ─────────────────────────────────────────
        $recentOrders = Order::with('product')->latest()->limit(15)->get();

        // ── Lot ใกล้หมดอายุ ───────────────────────────────────────
        $expiringLots = $fifoService->getExpiringLots(30);

        return view('dashboard.index', compact(
            'period', 'periodLabel', 'periodShort',
            'periodOrders', 'periodBoxes', 'periodCod', 'periodPrepaid',
            'periodPrinted', 'periodJt', 'periodFlash',
            'pendingOrders',
            'trend', 'periodProducts', 'periodProvinces',
            'products', 'recentOrders', 'expiringLots'
        ));
    }
}
