<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /**
     * รายชื่อลูกค้าทั้งหมด
     */
    public function index(Request $request)
    {
        $query = Customer::latest('last_order_at');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($province = $request->get('province')) {
            $query->where('province', $province);
        }

        $customers = $query->paginate(50)->withQueryString();
        $totalCount = Customer::count();
        $provinces  = Customer::whereNotNull('province')
            ->distinct()
            ->orderBy('province')
            ->pluck('province');

        return view('customers.index', compact('customers', 'totalCount', 'provinces'));
    }

    /**
     * รายละเอียดลูกค้า + ประวัติออเดอร์
     */
    public function show(Customer $customer)
    {
        $orders = $customer->orders()
            ->with('product')
            ->latest()
            ->take(20)
            ->get();

        // นับ seller_sku แบบแยก pipe-separated ทีละรายการ
        $skuCounts = [];
        $customer->orders()->get(['seller_sku', 'product_sku', 'item_quantities', 'quantity'])
            ->each(function ($o) use (&$skuCounts) {
                $skus = array_map('trim', explode('|', $o->seller_sku ?: $o->product_sku ?: ''));
                $qtys = array_map('trim', explode('|', $o->item_quantities ?: $o->quantity ?: '1'));
                foreach ($skus as $i => $sku) {
                    if ($sku === '') continue;
                    $skuCounts[$sku] = ($skuCounts[$sku] ?? 0) + (int)($qtys[$i] ?? 1);
                }
            });
        arsort($skuCounts);

        $stats = [
            'total_orders'  => $customer->total_orders,
            'cod_count'     => $customer->orders()->where('payment_type', 'COD')->count(),
            'prepaid_count' => $customer->orders()->where('payment_type', 'PREPAID')->count(),
            'products'      => collect($skuCounts),
        ];

        return view('customers.show', compact('customer', 'orders', 'stats'));
    }
}
