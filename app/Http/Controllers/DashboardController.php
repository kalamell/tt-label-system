<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Services\FifoInventoryService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(FifoInventoryService $fifoService)
    {
        $today = today();

        // ── Stats วันนี้ (ใช้ shipping_date เป็นวันที่หลัก) ──────────
        $todayDateStr = $today->toDateString();
        $todayQ       = Order::whereRaw('COALESCE(DATE(shipping_date), DATE(created_at)) = ?', [$todayDateStr])
                              ->where('status', '!=', 'cancelled');
        $todayOrders    = (clone $todayQ)->count();
        $todayBoxes     = (clone $todayQ)->sum('quantity');
        $todayCod       = (clone $todayQ)->where('payment_type', 'COD')->count();
        $todayPrepaid   = (clone $todayQ)->where('payment_type', 'PREPAID')->count();
        $pendingOrders  = Order::pending()->count();
        $printedToday   = (clone $todayQ)->where('label_printed', true)->count();
        $todayJt        = (clone $todayQ)->where('carrier', 'JT')->count();
        $todayFlash     = (clone $todayQ)->where('carrier', 'FLASH')->count();

        // ── Trend 7 วัน ───────────────────────────────────────────
        $since = now()->subDays(6)->toDateString();
        $trendRaw = Order::selectRaw('
                COALESCE(DATE(shipping_date), DATE(created_at)) AS date,
                COUNT(*) AS orders,
                SUM(quantity) AS boxes,
                SUM(carrier = "JT") AS jt_count,
                SUM(carrier = "FLASH") AS flash_count
            ')
            ->whereRaw('COALESCE(DATE(shipping_date), DATE(created_at)) >= ?', [$since])
            ->where('status', '!=', 'cancelled')
            ->groupByRaw('COALESCE(DATE(shipping_date), DATE(created_at))')
            ->orderBy('date')
            ->get()->keyBy('date');

        $trend = collect();
        for ($i = 6; $i >= 0; $i--) {
            $d = now()->subDays($i)->toDateString();
            $trend->push([
                'date'        => $d,
                'label'       => Carbon::parse($d)->format('d/m'),
                'orders'      => $trendRaw[$d]->orders      ?? 0,
                'boxes'       => $trendRaw[$d]->boxes        ?? 0,
                'jt_count'    => $trendRaw[$d]->jt_count    ?? 0,
                'flash_count' => $trendRaw[$d]->flash_count ?? 0,
            ]);
        }

        // ── สรุปสินค้าวันนี้ ──────────────────────────────────────
        $todayProducts = Order::selectRaw('product_id, COUNT(*) AS orders, SUM(quantity) AS boxes')
            ->whereRaw('COALESCE(DATE(shipping_date), DATE(created_at)) = ?', [$todayDateStr])
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('product_id')
            ->groupBy('product_id')
            ->with('product')
            ->orderByRaw('SUM(quantity) DESC')
            ->get();

        // ── จังหวัดวันนี้ Top 6 ───────────────────────────────────
        $todayProvinces = Order::selectRaw('recipient_province, COUNT(*) AS cnt')
            ->whereRaw('COALESCE(DATE(shipping_date), DATE(created_at)) = ?', [$todayDateStr])
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('recipient_province')
            ->groupBy('recipient_province')
            ->orderBy('cnt', 'desc')
            ->limit(6)->get();

        // ── สต๊อกสินค้า ───────────────────────────────────────────
        $products = Product::active()->get()->map(fn($p) => [
            'product'    => $p,
            'total_stock'=> $p->total_stock,
            'is_low_stock'=> $p->is_low_stock,
        ]);

        // ── ออเดอร์ล่าสุด ─────────────────────────────────────────
        $recentOrders = Order::with('product')->latest()->limit(15)->get();

        // ── Lot ใกล้หมดอายุ ───────────────────────────────────────
        $expiringLots = $fifoService->getExpiringLots(30);

        return view('dashboard.index', compact(
            'todayOrders', 'todayBoxes', 'todayCod', 'todayPrepaid',
            'pendingOrders', 'printedToday', 'todayJt', 'todayFlash',
            'trend', 'todayProducts', 'todayProvinces',
            'products', 'recentOrders', 'expiringLots'
        ));
    }
}
