<?php

namespace App\Services;

use App\Models\Product;
use App\Models\InventoryLot;
use App\Models\InventoryTransaction;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * ระบบจัดการสต๊อกแบบ FIFO (First In, First Out)
 * - รับสินค้าเข้า (Lot)
 * - ตัดสต๊อกอัตโนมัติจาก Lot ที่เข้าก่อน
 * - บันทึก Transaction ทุกรายการ
 */
class FifoInventoryService
{
    /**
     * ============================================================
     * รับสินค้าเข้าคลัง (สร้าง Lot ใหม่)
     * ============================================================
     */
    public function receiveStock(
        int    $productId,
        string $lotNumber,
        int    $quantity,
        string $receivedDate,
        ?string $expiryDate = null,
        float  $costPerUnit = 0,
        ?string $notes = null
    ): InventoryLot {
        return DB::transaction(function () use ($productId, $lotNumber, $quantity, $receivedDate, $expiryDate, $costPerUnit, $notes) {
            // สร้าง Lot ใหม่
            $lot = InventoryLot::create([
                'product_id' => $productId,
                'lot_number' => $lotNumber,
                'quantity_received' => $quantity,
                'quantity_remaining' => $quantity,
                'received_date' => $receivedDate,
                'expiry_date' => $expiryDate,
                'cost_per_unit' => $costPerUnit,
                'status' => 'active',
                'notes' => $notes,
            ]);

            // คำนวณยอมคงเหลือหลังรับเข้า
            $totalStock = InventoryLot::where('product_id', $productId)
                ->where('status', 'active')
                ->sum('quantity_remaining');

            // บันทึก Transaction
            InventoryTransaction::create([
                'product_id' => $productId,
                'inventory_lot_id' => $lot->id,
                'type' => 'in',
                'quantity' => $quantity,
                'balance_after' => $totalStock,
                'reference' => "LOT-{$lotNumber}",
                'notes' => $notes ?? "รับเข้า Lot {$lotNumber} จำนวน {$quantity} ชิ้น",
            ]);

            return $lot;
        });
    }

    /**
     * ============================================================
     * ตัดสต๊อกแบบ FIFO (ตัดจาก Lot เก่าสุดก่อน)
     * ============================================================
     *
     * @return array ['success' => bool, 'deductions' => [...], 'message' => string]
     */
    public function deductStock(int $productId, int $quantity, ?int $orderId = null): array
    {
        return DB::transaction(function () use ($productId, $quantity, $orderId) {
            // ดึง Lot ที่ยัง active เรียงตาม FIFO
            $lots = InventoryLot::where('product_id', $productId)
                ->fifoOrder()
                ->lockForUpdate() // ล็อคเพื่อป้องกัน race condition
                ->get();

            $totalAvailable = $lots->sum('quantity_remaining');

            if ($totalAvailable < $quantity) {
                return [
                    'success' => false,
                    'deductions' => [],
                    'message' => "สต๊อกไม่เพียงพอ ต้องการ {$quantity} ชิ้น แต่มีเพียง {$totalAvailable} ชิ้น",
                ];
            }

            $remaining = $quantity;
            $deductions = [];
            $assignedLots = [];

            foreach ($lots as $lot) {
                if ($remaining <= 0) break;

                $deducted = $lot->deduct($remaining);
                $remaining -= $deducted;

                $assignedLots[] = $lot->lot_number;

                // คำนวณยอดคงเหลือรวม
                $totalStock = InventoryLot::where('product_id', $productId)
                    ->where('status', 'active')
                    ->sum('quantity_remaining');

                // บันทึก Transaction
                InventoryTransaction::create([
                    'product_id' => $productId,
                    'inventory_lot_id' => $lot->id,
                    'order_id' => $orderId,
                    'type' => 'out',
                    'quantity' => $deducted,
                    'balance_after' => $totalStock,
                    'reference' => $orderId ? "ORDER-{$orderId}" : null,
                    'notes' => "ตัดจาก Lot {$lot->lot_number} จำนวน {$deducted} ชิ้น",
                ]);

                $deductions[] = [
                    'lot_id' => $lot->id,
                    'lot_number' => $lot->lot_number,
                    'deducted' => $deducted,
                    'remaining_in_lot' => $lot->quantity_remaining,
                ];
            }

            // อัพเดท Order ถ้ามี
            if ($orderId) {
                Order::where('id', $orderId)->update([
                    'assigned_lot' => implode(', ', $assignedLots),
                ]);
            }

            return [
                'success' => true,
                'deductions' => $deductions,
                'assigned_lots' => implode(', ', $assignedLots),
                'message' => "ตัดสต๊อกสำเร็จ จำนวน {$quantity} ชิ้น จาก " . count($deductions) . " Lot",
            ];
        });
    }

    /**
     * ============================================================
     * ปรับปรุงสต๊อก (เพิ่ม/ลด) — ใช้กรณีนับสต๊อกจริง
     * ============================================================
     */
    public function adjustStock(int $lotId, int $newQuantity, string $reason = ''): InventoryLot
    {
        return DB::transaction(function () use ($lotId, $newQuantity, $reason) {
            $lot = InventoryLot::lockForUpdate()->findOrFail($lotId);
            $diff = $newQuantity - $lot->quantity_remaining;

            $lot->quantity_remaining = $newQuantity;
            if ($newQuantity <= 0) {
                $lot->status = 'depleted';
                $lot->quantity_remaining = 0;
            } else {
                $lot->status = 'active';
            }
            $lot->save();

            $totalStock = InventoryLot::where('product_id', $lot->product_id)
                ->where('status', 'active')
                ->sum('quantity_remaining');

            InventoryTransaction::create([
                'product_id' => $lot->product_id,
                'inventory_lot_id' => $lot->id,
                'type' => 'adjustment',
                'quantity' => $diff,
                'balance_after' => $totalStock,
                'reference' => "ADJ-{$lot->lot_number}",
                'notes' => $reason ?: "ปรับปรุงสต๊อก Lot {$lot->lot_number} เป็น {$newQuantity} ชิ้น",
            ]);

            return $lot;
        });
    }

    /**
     * ============================================================
     * ดึงข้อมูลสต๊อกรวมของสินค้า
     * ============================================================
     */
    public function getStockSummary(int $productId): array
    {
        $product = Product::findOrFail($productId);
        $activeLots = InventoryLot::where('product_id', $productId)
            ->where('status', 'active')
            ->orderBy('received_date')
            ->get();

        $totalStock = $activeLots->sum('quantity_remaining');
        $totalReceived = InventoryLot::where('product_id', $productId)->sum('quantity_received');
        $totalSold = InventoryTransaction::where('product_id', $productId)
            ->where('type', 'out')
            ->sum('quantity');

        return [
            'product' => $product,
            'total_stock' => $totalStock,
            'total_received' => $totalReceived,
            'total_sold' => $totalSold,
            'active_lots' => $activeLots,
            'is_low_stock' => $totalStock <= $product->min_stock,
            'near_expiry_lots' => $activeLots->filter(fn($lot) => $lot->is_near_expiry),
        ];
    }

    /**
     * ============================================================
     * ตรวจสอบ Lot ที่ใกล้หมดอายุ
     * ============================================================
     */
    public function getExpiringLots(int $daysAhead = 30)
    {
        return InventoryLot::where('status', 'active')
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now()->addDays($daysAhead))
            ->with('product')
            ->orderBy('expiry_date')
            ->get();
    }
}
