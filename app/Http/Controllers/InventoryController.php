<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\InventoryLot;
use App\Models\InventoryTransaction;
use App\Services\FifoInventoryService;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(
        protected FifoInventoryService $fifoService
    ) {}

    /**
     * ============================================================
     * หน้ารวมสต๊อกสินค้าทั้งหมด
     * ============================================================
     */
    public function index()
    {
        $products = Product::active()->get()->map(function ($product) {
            $activeLots = $product->inventoryLots()
                ->where('status', 'active')
                ->orderBy('received_date')
                ->get();

            return [
                'product' => $product,
                'total_stock' => $activeLots->sum('quantity_remaining'),
                'active_lots' => $activeLots,
                'is_low_stock' => $product->is_low_stock,
            ];
        });

        return view('inventory.index', compact('products'));
    }

    /**
     * ============================================================
     * รายละเอียดสต๊อกของสินค้า + FIFO Lots
     * ============================================================
     */
    public function show(Product $product)
    {
        $summary = $this->fifoService->getStockSummary($product->id);

        $transactions = InventoryTransaction::where('product_id', $product->id)
            ->with('inventoryLot', 'order')
            ->latest()
            ->paginate(50);

        return view('inventory.show', compact('summary', 'transactions'));
    }

    /**
     * ============================================================
     * หน้ารับเข้า + จ่ายออก แบบ side-by-side
     * ============================================================
     */
    public function actionsForm(Request $request)
    {
        $products = Product::active()->get();

        $issueProducts = Product::active()->get()->map(function ($p) {
            return [
                'product'     => $p,
                'total_stock' => $p->total_stock,
                'next_lot'    => $p->inventoryLots()
                    ->where('status', 'active')
                    ->orderBy('received_date')
                    ->first()?->lot_number,
            ];
        })->filter(fn($p) => $p['total_stock'] > 0)->values();

        return view('inventory.stock-actions', compact('products', 'issueProducts'));
    }

    /**
     * ============================================================
     * ฟอร์มรับสินค้าเข้าคลัง (สร้าง Lot ใหม่)
     * ============================================================
     */
    public function receiveForm()
    {
        $products = Product::active()->get();

        $recentTransactions = InventoryTransaction::with(['product', 'inventoryLot'])
            ->where('type', 'in')
            ->latest()
            ->limit(30)
            ->get();

        return view('inventory.receive', compact('products', 'recentTransactions'));
    }

    /**
     * ============================================================
     * บันทึกรับสินค้าเข้าคลัง
     * ============================================================
     */
    public function receive(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'lot_number' => 'required|string|max:50',
            'quantity' => 'required|integer|min:1',
            'received_date' => 'required|date',
            'expiry_date' => 'nullable|date|after:received_date',
            'cost_per_unit' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $lot = $this->fifoService->receiveStock(
            productId: $request->input('product_id'),
            lotNumber: $request->input('lot_number'),
            quantity: $request->input('quantity'),
            receivedDate: $request->input('received_date'),
            expiryDate: $request->input('expiry_date'),
            costPerUnit: $request->input('cost_per_unit', 0),
            notes: $request->input('notes'),
        );

        return redirect()->route('inventory.receive.form')
            ->with('success', "รับเข้า Lot {$lot->lot_number} จำนวน {$lot->quantity_received} ชิ้น สำเร็จ");
    }

    /**
     * ============================================================
     * ปรับปรุงสต๊อก Lot
     * ============================================================
     */
    public function adjust(Request $request, InventoryLot $lot)
    {
        $request->validate([
            'new_quantity' => 'required|integer|min:0',
            'reason' => 'required|string|max:500',
        ]);

        $this->fifoService->adjustStock(
            lotId: $lot->id,
            newQuantity: $request->input('new_quantity'),
            reason: $request->input('reason'),
        );

        return back()->with('success', "ปรับปรุงสต๊อก Lot {$lot->lot_number} สำเร็จ");
    }

    /**
     * ============================================================
     * ฟอร์มจ่ายออก (ออฟไลน์)
     * ============================================================
     */
    public function issueForm(Request $request)
    {
        $products = Product::active()->get()->map(function ($p) {
            return [
                'product'     => $p,
                'total_stock' => $p->total_stock,
                'next_lot'    => $p->inventoryLots()
                    ->where('status', 'active')
                    ->orderBy('received_date')
                    ->first()?->lot_number,
            ];
        })->filter(fn($p) => $p['total_stock'] > 0);

        $selectedProductId = $request->get('product_id');

        $recentTransactions = InventoryTransaction::with(['product', 'inventoryLot'])
            ->where('type', 'out')
            ->where('reference', 'like', 'OFFLINE-%')
            ->latest()
            ->limit(30)
            ->get();

        return view('inventory.issue', compact('products', 'selectedProductId', 'recentTransactions'));
    }

    /**
     * ============================================================
     * บันทึกจ่ายออก (ออฟไลน์) — ตัด FIFO
     * ============================================================
     */
    public function issue(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity'   => 'required|integer|min:1',
            'channel'    => 'required|string|max:100',
            'notes'      => 'nullable|string|max:500',
        ]);

        $result = $this->fifoService->issueStock(
            productId: (int) $request->input('product_id'),
            quantity:  (int) $request->input('quantity'),
            channel:   $request->input('channel'),
            notes:     $request->input('notes'),
        );

        if (!$result['success']) {
            return back()->withInput()->with('error', $result['message']);
        }

        return redirect()
            ->route('inventory.issue.form')
            ->with('success', $result['message'] . " (Ref: {$result['reference']})");
    }

    /**
     * ============================================================
     * ประวัติ Transaction ทั้งหมด
     * ============================================================
     */
    public function transactions(Request $request)
    {
        $query = InventoryTransaction::with(['product', 'inventoryLot', 'order'])
            ->latest();

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        if ($productId = $request->get('product_id')) {
            $query->where('product_id', $productId);
        }

        $transactions = $query->paginate(100);
        $products = Product::active()->get();

        return view('inventory.transactions', compact('transactions', 'products'));
    }
}
