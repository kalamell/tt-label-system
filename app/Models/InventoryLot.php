<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryLot extends Model
{
    protected $fillable = [
        'product_id', 'lot_number', 'quantity_received', 'quantity_remaining',
        'received_date', 'expiry_date', 'cost_per_unit', 'status', 'notes',
    ];

    protected $casts = [
        'received_date' => 'date',
        'expiry_date' => 'date',
        'cost_per_unit' => 'decimal:2',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    // ============================================================
    // Computed
    // ============================================================

    public function getIsExpiredAttribute(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    public function getIsNearExpiryAttribute(): bool
    {
        return $this->expiry_date && $this->expiry_date->diffInDays(now()) <= 30;
    }

    // ============================================================
    // Scopes
    // ============================================================

    /**
     * FIFO: เรียงลำดับ Lot ที่เข้าก่อน (received_date เก่าสุด) ก่อน
     */
    public function scopeFifoOrder($query)
    {
        return $query->where('status', 'active')
            ->where('quantity_remaining', '>', 0)
            ->orderBy('received_date', 'asc')
            ->orderBy('id', 'asc');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // ============================================================
    // Actions
    // ============================================================

    /**
     * ตัดสต๊อกจาก Lot นี้
     */
    public function deduct(int $quantity): int
    {
        $actualDeducted = min($quantity, $this->quantity_remaining);
        $this->quantity_remaining -= $actualDeducted;

        if ($this->quantity_remaining <= 0) {
            $this->status = 'depleted';
        }

        $this->save();

        return $actualDeducted;
    }
}
