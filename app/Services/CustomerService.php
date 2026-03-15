<?php

namespace App\Services;

use App\Models\Customer;

class CustomerService
{
    /**
     * Sync customer จาก parsed order data
     * ใช้ phone + zipcode เป็น composite key —
     * เพราะ TikTok ซ่อนเบอร์กลาง (081****5) ทำให้เบอร์ต้น/ท้ายเหมือนกันได้
     * zipcode ช่วยแยกแยะให้แม่นขึ้น
     */
    public function syncFromOrder(array $parsed): ?Customer
    {
        $phone   = trim($parsed['recipient_phone'] ?? '');
        $zipcode = trim($parsed['recipient_zipcode'] ?? '');

        // ถ้าไม่มีเบอร์โทร ไม่สร้าง customer (identify ไม่ได้)
        if (empty($phone)) {
            return null;
        }

        // composite key: phone + zipcode (ถ้ามี zipcode)
        $matchKeys = ['phone' => $phone];
        if (!empty($zipcode)) {
            $matchKeys['zipcode'] = $zipcode;
        }

        // อัปเดตข้อมูลล่าสุด — ไม่ทับ zipcode ถ้า PDF ครั้งนี้ไม่มี
        $updateData = [
            'name'     => $parsed['recipient_name'] ?? '',
            'address'  => $parsed['recipient_address'] ?? null,
            'district' => $parsed['recipient_district'] ?? null,
            'province' => $parsed['recipient_province'] ?? null,
        ];
        if (!empty($zipcode)) {
            $updateData['zipcode'] = $zipcode;
        }

        $customer = Customer::updateOrCreate($matchKeys, $updateData);

        $customer->increment('total_orders');
        $customer->update(['last_order_at' => now()]);

        return $customer;
    }
}
