<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\FifoInventoryService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request, FifoInventoryService $fifoService)
    {
        // ── Date selector ────────────────────────────────────────
        $dateFrom = $request->get('date_from', today()->toDateString());
        $dateTo   = $request->get('date_to', today()->toDateString());

        // Validate dates
        try {
            $dateFrom = Carbon::parse($dateFrom)->toDateString();
            $dateTo   = Carbon::parse($dateTo)->toDateString();
        } catch (\Exception $e) {
            $dateFrom = today()->toDateString();
            $dateTo   = today()->toDateString();
        }

        // สร้าง label แสดงช่วงวันที่
        if ($dateFrom === $dateTo) {
            $periodLabel = Carbon::parse($dateFrom)->locale('th')->isoFormat('D MMM YYYY');
        } else {
            $periodLabel = Carbon::parse($dateFrom)->locale('th')->isoFormat('D MMM') . ' - ' . Carbon::parse($dateTo)->locale('th')->isoFormat('D MMM YYYY');
        }

        // ── Stats ตาม period ───────────────────────────────────────
        $statsQ = Order::whereRaw(
                'DATE(created_at) BETWEEN ? AND ?',
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
        $periodSpx     = (clone $statsQ)->where('carrier', 'SPX')->count();

        // รอพิมพ์ — ยอดรวมเสมอ (ไม่ขึ้นกับ date range)
        $pendingOrders = Order::pending()->count();

        // ── Trend Chart (รายวันตามช่วงวันที่เลือก) ────────────────
        $daysDiff = Carbon::parse($dateFrom)->diffInDays(Carbon::parse($dateTo));

        if ($daysDiff > 60) {
            // ช่วงยาวกว่า 60 วัน → แสดงรายเดือน
            $trendRaw = Order::selectRaw('
                    DATE_FORMAT(created_at, "%Y-%m") AS period_key,
                    COUNT(*) AS orders,
                    SUM(quantity) AS boxes,
                    SUM(carrier = "JT") AS jt_count,
                    SUM(carrier = "FLASH") AS flash_count
                ')
                ->whereRaw('DATE(created_at) BETWEEN ? AND ?', [$dateFrom, $dateTo])
                ->where('status', '!=', 'cancelled')
                ->groupByRaw('DATE_FORMAT(created_at, "%Y-%m")')
                ->orderBy('period_key')
                ->get()->keyBy('period_key');

            $thaiMonths = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.',
                               'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
            $trend = collect();
            $cursor = Carbon::parse($dateFrom)->startOfMonth();
            $end = Carbon::parse($dateTo)->startOfMonth();
            while ($cursor->lte($end)) {
                $key = $cursor->format('Y-m');
                $trend->push([
                    'label'       => $thaiMonths[(int)$cursor->format('m')] . ' ' . $cursor->format('y'),
                    'orders'      => $trendRaw[$key]->orders      ?? 0,
                    'boxes'       => $trendRaw[$key]->boxes        ?? 0,
                    'jt_count'    => $trendRaw[$key]->jt_count    ?? 0,
                    'flash_count' => $trendRaw[$key]->flash_count ?? 0,
                ]);
                $cursor->addMonth();
            }
        } else {
            // ช่วงสั้น → แสดงรายวัน
            $trendRaw = Order::selectRaw('
                    DATE(created_at) AS period_key,
                    COUNT(*) AS orders,
                    SUM(quantity) AS boxes,
                    SUM(carrier = "JT") AS jt_count,
                    SUM(carrier = "FLASH") AS flash_count
                ')
                ->whereRaw('DATE(created_at) BETWEEN ? AND ?', [$dateFrom, $dateTo])
                ->where('status', '!=', 'cancelled')
                ->groupByRaw('DATE(created_at)')
                ->orderBy('period_key')
                ->get()->keyBy('period_key');

            $trend = collect();
            $cursor = Carbon::parse($dateFrom);
            $end = Carbon::parse($dateTo);
            while ($cursor->lte($end)) {
                $d = $cursor->toDateString();
                $trend->push([
                    'label'       => $cursor->format('d/m'),
                    'orders'      => $trendRaw[$d]->orders      ?? 0,
                    'boxes'       => $trendRaw[$d]->boxes        ?? 0,
                    'jt_count'    => $trendRaw[$d]->jt_count    ?? 0,
                    'flash_count' => $trendRaw[$d]->flash_count ?? 0,
                ]);
                $cursor->addDay();
            }
        }

        // ── จังหวัด Top 6 ตาม period ──────────────────────────────
        $periodProvinces = Order::selectRaw('recipient_province, COUNT(*) AS cnt')
            ->whereRaw('DATE(created_at) BETWEEN ? AND ?', [$dateFrom, $dateTo])
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('recipient_province')
            ->groupBy('recipient_province')
            ->orderBy('cnt', 'desc')
            ->limit(6)->get();

        // ── ออเดอร์ล่าสุด (ตามช่วงวันที่) ──────────────────────────
        $recentOrders = Order::with('product')
            ->whereRaw('DATE(created_at) BETWEEN ? AND ?', [$dateFrom, $dateTo])
            ->latest()->limit(15)->get();

        // ── Lot ใกล้หมดอายุ ───────────────────────────────────────
        $expiringLots = $fifoService->getExpiringLots(30);

        return view('dashboard.index', compact(
            'periodLabel', 'dateFrom', 'dateTo',
            'periodOrders', 'periodBoxes', 'periodCod', 'periodPrepaid',
            'periodPrinted', 'periodJt', 'periodFlash', 'periodSpx',
            'pendingOrders',
            'trend', 'periodProvinces',
            'recentOrders', 'expiringLots'
        ));
    }
}
