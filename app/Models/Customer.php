<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'phone', 'name', 'address', 'district', 'province', 'zipcode',
        'tags', 'notes', 'total_orders', 'last_order_at',
    ];

    protected $casts = [
        'tags'          => 'array',
        'last_order_at' => 'datetime',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopeHasPhone($query)
    {
        return $query->whereNotNull('phone');
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * เบอร์โทรแบบซ่อน
     */
    public function getMaskedPhoneAttribute(): string
    {
        $phone = $this->phone ?? '';
        if (strlen($phone) > 6) {
            return substr($phone, 0, 4) . str_repeat('*', strlen($phone) - 6) . substr($phone, -2);
        }
        return $phone;
    }

    /**
     * ชื่อแบบย่อ
     */
    public function getMaskedNameAttribute(): string
    {
        $name = $this->name;
        if (mb_strlen($name) > 2) {
            return mb_substr($name, 0, 1) . str_repeat('*', mb_strlen($name) - 2) . mb_substr($name, -1);
        }
        return $name;
    }
}
