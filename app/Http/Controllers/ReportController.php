<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function daily(Request $request)
    {
        $dateFrom = $request->get('date_from', today()->toDateString());
        $dateTo   = $request->get('date_to',   today()->toDateString());

        $from = Carbon::parse($dateFrom)->startOfDay();
        $to   = Carbon::parse($dateTo)->endOfDay();

        // ออเดอร์ในช่วงที่เลือก (ใช้ shipping_date เป็นหลัก)
        $ordersInRange = Order::with('product')
            ->whereRaw('COALESCE(shipping_date, DATE(created_at)) BETWEEN ? AND ?', [$dateFrom, $dateTo])
            ->where('status', '!=', 'cancelled')
            ->orderByRaw('COALESCE(shipping_date, DATE(created_at)) DESC')
            ->get();

        $totalOrders  = $ordersInRange->count();
        $totalBoxes   = $ordersInRange->sum('quantity');
        $codCount     = $ordersInRange->where('payment_type', 'COD')->count();
        $prepaidCount = $ordersInRange->where('payment_type', 'PREPAID')->count();

        // สรุปรายวัน (ในช่วงที่เลือก)
        $dailySummary = Order::selectRaw('
                COALESCE(shipping_date, DATE(created_at))  AS date,
                COUNT(*)                                    AS orders,
                SUM(quantity)                               AS boxes,
                SUM(payment_type = "COD")                   AS cod_count,
                SUM(payment_type = "PREPAID")               AS prepaid_count
            ')
            ->whereRaw('COALESCE(shipping_date, DATE(created_at)) BETWEEN ? AND ?', [$dateFrom, $dateTo])
            ->where('status', '!=', 'cancelled')
            ->groupByRaw('COALESCE(shipping_date, DATE(created_at))')
            ->orderBy('date', 'desc')
            ->get();

        // สรุปสินค้า (groupby product)
        $productSummary = Order::selectRaw('
                product_id,
                seller_sku,
                product_name,
                COUNT(*)       AS orders,
                SUM(quantity)  AS boxes
            ')
            ->whereRaw('COALESCE(shipping_date, DATE(created_at)) BETWEEN ? AND ?', [$dateFrom, $dateTo])
            ->where('status', '!=', 'cancelled')
            ->groupBy('product_id', 'seller_sku', 'product_name')
            ->with('product')
            ->orderByRaw('SUM(quantity) DESC')
            ->get();

        // สรุปจังหวัด Top 10
        $provinceSummary = Order::selectRaw('recipient_province, COUNT(*) AS cnt')
            ->whereRaw('COALESCE(shipping_date, DATE(created_at)) BETWEEN ? AND ?', [$dateFrom, $dateTo])
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('recipient_province')
            ->groupBy('recipient_province')
            ->orderBy('cnt', 'desc')
            ->limit(10)
            ->get();

        // Trend 30 วันล่าสุด (สำหรับกราฟ)
        $since30 = now()->subDays(29)->toDateString();
        $trend = Order::selectRaw('COALESCE(shipping_date, DATE(created_at)) AS date, COUNT(*) AS orders, SUM(quantity) AS boxes')
            ->whereRaw('COALESCE(shipping_date, DATE(created_at)) >= ?', [$since30])
            ->where('status', '!=', 'cancelled')
            ->groupByRaw('COALESCE(shipping_date, DATE(created_at))')
            ->orderBy('date', 'asc')
            ->get()
            ->keyBy('date');

        // เติมวันที่ขาดหายด้วย 0
        $trendDays = collect();
        for ($i = 29; $i >= 0; $i--) {
            $d = now()->subDays($i)->toDateString();
            $trendDays->push([
                'date'   => $d,
                'label'  => Carbon::parse($d)->format('d/m'),
                'orders' => $trend[$d]->orders ?? 0,
                'boxes'  => $trend[$d]->boxes  ?? 0,
            ]);
        }

        return view('reports.daily', compact(
            'dateFrom', 'dateTo',
            'totalOrders', 'totalBoxes', 'codCount', 'prepaidCount',
            'dailySummary', 'productSummary', 'provinceSummary',
            'ordersInRange', 'trendDays'
        ));
    }

    public function export(Request $request)
    {
        $dateFrom = $request->get('date_from', today()->toDateString());
        $dateTo   = $request->get('date_to',   today()->toDateString());

        $from = Carbon::parse($dateFrom)->startOfDay();
        $to   = Carbon::parse($dateTo)->endOfDay();

        $orders = Order::with('product')
            ->whereRaw('COALESCE(shipping_date, DATE(created_at)) BETWEEN ? AND ?', [$dateFrom, $dateTo])
            ->where('status', '!=', 'cancelled')
            ->orderByRaw('COALESCE(shipping_date, DATE(created_at)) ASC')
            ->get();

        $filename = "รายงาน_{$dateFrom}_ถึง_{$dateTo}.csv";

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($orders) {
            $f = fopen('php://output', 'w');
            fputs($f, "\xEF\xBB\xBF"); // BOM สำหรับ Excel

            fputcsv($f, [
                'วันที่จัดส่ง', 'Order ID', 'Tracking Number',
                'ผู้รับ', 'อำเภอ', 'จังหวัด', 'รหัสไปรษณีย์',
                'ชำระเงิน', 'ประเภทจัดส่ง',
                'สินค้าในระบบ', 'Seller SKU', 'จำนวน', 'Lot',
                'สถานะ', 'พิมพ์แล้ว',
            ]);

            foreach ($orders as $o) {
                fputcsv($f, [
                    ($o->shipping_date ?? $o->created_at)->format('Y-m-d'),
                    $o->order_id,
                    $o->tracking_number,
                    $o->recipient_name,
                    $o->recipient_district,
                    $o->recipient_province,
                    $o->recipient_zipcode,
                    $o->payment_type,
                    $o->delivery_type,
                    $o->product?->name ?? '-',
                    $o->seller_sku ?? '-',
                    $o->quantity,
                    $o->assigned_lot ?? '-',
                    $o->status,
                    $o->label_printed ? 'ใช่' : 'ไม่',
                ]);
            }

            fclose($f);
        };

        return response()->stream($callback, 200, $headers);
    }
}
