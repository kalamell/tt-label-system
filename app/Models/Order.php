<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'order_id', 'tracking_number', 'sorting_code', 'sorting_code_2', 'route_code',
        'sender_name', 'sender_address',
        'recipient_name', 'recipient_phone', 'recipient_address',
        'recipient_district', 'recipient_province', 'recipient_zipcode',
        'payment_type', 'delivery_type', 'shipping_date', 'estimated_date',
        'product_id', 'product_name', 'product_sku', 'seller_sku', 'quantity', 'item_quantities',
        'assigned_lot', 'status', 'label_printed', 'printed_at',
        'original_pdf_path', 'pdf_page_number', 'clean_pdf_path',
        'customer_id',
    ];

    protected $casts = [
        'shipping_date' => 'date',
        'estimated_date' => 'date',
        'printed_at' => 'datetime',
        'label_printed' => 'boolean',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePrinted($query)
    {
        return $query->where('label_printed', true);
    }

    public function scopeNotPrinted($query)
    {
        return $query->where('label_printed', false);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('shipping_date', today());
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * ชื่อผู้รับแบบย่อ (ซ่อนข้อมูลส่วนตัว)
     */
    public function getMaskedRecipientAttribute(): string
    {
        $name = $this->recipient_name;
        if (mb_strlen($name) > 2) {
            return mb_substr($name, 0, 1) . str_repeat('*', mb_strlen($name) - 2) . mb_substr($name, -1);
        }
        return $name;
    }

    /**
     * เบอร์โทรแบบซ่อน
     */
    public function getMaskedPhoneAttribute(): string
    {
        $phone = $this->recipient_phone;
        if (strlen($phone) > 6) {
            return substr($phone, 0, 4) . str_repeat('*', strlen($phone) - 6) . substr($phone, -2);
        }
        return $phone;
    }
}
