<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'name', 'sku', 'seller_sku', 'description',
        'price', 'min_stock', 'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function inventoryLots(): HasMany
    {
        return $this->hasMany(InventoryLot::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    // ============================================================
    // Computed: สต๊อกรวม
    // ============================================================

    /**
     * จำนวนสต๊อกคงเหลือรวมทุก Lot (เฉพาะ active)
     */
    public function getTotalStockAttribute(): int
    {
        return $this->inventoryLots()
            ->where('status', 'active')
            ->sum('quantity_remaining');
    }

    /**
     * ตรวจสอบว่าสต๊อกต่ำกว่าค่าขั้นต่ำหรือไม่
     */
    public function getIsLowStockAttribute(): bool
    {
        return $this->total_stock <= $this->min_stock;
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock($query)
    {
        return $query->active()
            ->whereHas('inventoryLots', function ($q) {
                $q->where('status', 'active');
            })
            ->get()
            ->filter(fn($product) => $product->is_low_stock);
    }
}
