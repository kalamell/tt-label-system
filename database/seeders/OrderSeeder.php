<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::all();
        if ($products->isEmpty()) {
            $this->command->warn('ไม่พบสินค้า — รันด้วย --class=ProductSeeder ก่อน');
        }

        $thaiNames = [
            'สมหญิง ใจดี', 'วิภา สุขสันต์', 'นภา พรมมา', 'อรุณี ทองดี', 'มนัส ศรีสุข',
            'ปราณี บุญมา', 'สุดา แก้วมณี', 'รัตนา วงศ์ดี', 'กนกวรรณ ชัยมงคล', 'เบญจมาศ หอมหวาน',
            'ธิดา ประทุม', 'วันเพ็ญ สมบูรณ์', 'ลัดดา พันธ์งาม', 'อัมพร ศรีทอง', 'สายฝน ดวงดี',
            'จิราภรณ์ มั่นคง', 'พรทิพย์ เจริญสุข', 'ทิพวัลย์ ใจงาม', 'กัญญา พงษ์ดี', 'นิตยา แสนสุข',
            'สุภาพร ทองใส', 'ชนิดา บุญส่ง', 'วราภรณ์ สายใจ', 'ดวงใจ ศรีงาม', 'มยุรา พิมพ์ดี',
            'จันทร์เพ็ญ วัฒนา', 'รุ่งนภา ชัยดี', 'สาวิตรี บุญมี', 'ปิยะมาศ สมใจ', 'ณัฐธิดา เพ็ชรดี',
            'กมลวรรณ ทรัพย์มาก', 'เพ็ญพักตร์ ดาวดี', 'อภิญญา แก้วใส', 'สุพรรณี ฤทธิ์ดี', 'ทิพย์สุดา วงค์งาม',
            'ยุพิน สว่างใจ', 'ศิริพร นาคดี', 'นวลจันทร์ บุญทอง', 'กาญจนา พรมดี', 'อมรรัตน์ สุขสม',
        ];

        $provinces = [
            ['province' => 'กรุงเทพมหานคร', 'district' => 'บางรัก',      'zipcode' => '10500'],
            ['province' => 'กรุงเทพมหานคร', 'district' => 'ลาดพร้าว',    'zipcode' => '10230'],
            ['province' => 'กรุงเทพมหานคร', 'district' => 'มีนบุรี',     'zipcode' => '10510'],
            ['province' => 'กรุงเทพมหานคร', 'district' => 'บึงกุ่ม',     'zipcode' => '10230'],
            ['province' => 'นนทบุรี',        'district' => 'ปากเกร็ด',    'zipcode' => '11120'],
            ['province' => 'นนทบุรี',        'district' => 'บางใหญ่',     'zipcode' => '11140'],
            ['province' => 'ปทุมธานี',       'district' => 'คลองหลวง',   'zipcode' => '12120'],
            ['province' => 'ปทุมธานี',       'district' => 'ธัญบุรี',     'zipcode' => '12110'],
            ['province' => 'สมุทรปราการ',    'district' => 'เมือง',       'zipcode' => '10270'],
            ['province' => 'สมุทรปราการ',    'district' => 'บางพลี',      'zipcode' => '10540'],
            ['province' => 'ชลบุรี',         'district' => 'เมือง',       'zipcode' => '20000'],
            ['province' => 'ชลบุรี',         'district' => 'บางละมุง',    'zipcode' => '20150'],
            ['province' => 'เชียงใหม่',      'district' => 'เมือง',       'zipcode' => '50000'],
            ['province' => 'เชียงใหม่',      'district' => 'สันทราย',     'zipcode' => '50210'],
            ['province' => 'ขอนแก่น',        'district' => 'เมือง',       'zipcode' => '40000'],
            ['province' => 'นครราชสีมา',     'district' => 'เมือง',       'zipcode' => '30000'],
            ['province' => 'อุดรธานี',        'district' => 'เมือง',       'zipcode' => '41000'],
            ['province' => 'สุราษฎร์ธานี',   'district' => 'เมือง',       'zipcode' => '84000'],
            ['province' => 'สงขลา',          'district' => 'หาดใหญ่',     'zipcode' => '90110'],
            ['province' => 'ภูเก็ต',         'district' => 'เมือง',       'zipcode' => '83000'],
            ['province' => 'นครศรีธรรมราช',  'district' => 'เมือง',       'zipcode' => '80000'],
            ['province' => 'สระบุรี',        'district' => 'บ้านหมอ',     'zipcode' => '18130'],
            ['province' => 'อยุธยา',         'district' => 'เมือง',       'zipcode' => '13000'],
            ['province' => 'ระยอง',          'district' => 'เมือง',       'zipcode' => '21000'],
            ['province' => 'ลำปาง',          'district' => 'เมือง',       'zipcode' => '52000'],
        ];

        $sortingCodesJt    = ['H1 G10-01', 'L1 T46-36', 'K2 S15-22', 'M3 R08-11', 'N4 P12-05'];
        $sortingCodesFlash = ['17B-16449-03', '22A-08331-01', '05C-12200-07', '31D-55100-02'];
        $sorting2Jt        = ['006A', '007A', '012B', '018C', '024D'];
        $sorting2Flash     = ['C13', 'C05', 'B07', 'A12'];

        $now   = Carbon::now();
        $count = 0;

        DB::transaction(function () use (
            $products, $thaiNames, $provinces,
            $sortingCodesJt, $sortingCodesFlash, $sorting2Jt, $sorting2Flash,
            $now, &$count
        ) {
            for ($i = 0; $i < 300; $i++) {
                // วันที่กระจายใน 30 วันที่ผ่านมา
                $daysAgo     = rand(0, 29);
                $shippingDate = $now->copy()->subDays($daysAgo)->toDateString();

                $name     = $thaiNames[array_rand($thaiNames)];
                $loc      = $provinces[array_rand($provinces)];
                $isFlash  = rand(0, 2) === 0; // ~33% Flash
                $carrier  = $isFlash ? 'FLASH' : 'JT';

                if ($carrier === 'JT') {
                    $tracking    = '79' . str_pad(rand(0, 9999999999), 10, '0', STR_PAD_LEFT);
                    $sortCode    = $sortingCodesJt[array_rand($sortingCodesJt)];
                    $sortCode2   = $sorting2Jt[array_rand($sorting2Jt)];
                    $serviceType = 'EZ';
                    $paymentType = rand(0, 3) === 0 ? 'COD' : 'PREPAID';
                } else {
                    $tracking    = 'THT' . strtoupper(substr(md5(uniqid()), 0, 12));
                    $sortCode    = $sortingCodesFlash[array_rand($sortingCodesFlash)];
                    $sortCode2   = $sorting2Flash[array_rand($sorting2Flash)];
                    $serviceType = 'NDD';
                    $paymentType = 'PREPAID'; // Flash ไม่มี COD
                }

                $orderId = (string)(8000000000000000000 - $i * rand(1000, 9999) + rand(0, 999));

                $product = $products->isNotEmpty() ? $products->random() : null;
                $qty     = rand(1, 3);

                $statuses = ['pending', 'pending', 'pending', 'printed', 'shipped'];
                $status   = $statuses[array_rand($statuses)];

                Order::create([
                    'order_id'           => $orderId,
                    'carrier'            => $carrier,
                    'service_type'       => $serviceType,
                    'tracking_number'    => $tracking,
                    'sorting_code'       => $sortCode,
                    'sorting_code_2'     => $sortCode2,
                    'route_code'         => null,
                    'sender_name'        => 'ร้านทดสอบ',
                    'sender_address'     => '123 ถ.ทดสอบ กรุงเทพฯ',
                    'recipient_name'     => $name,
                    'recipient_phone'    => '(+66)' . rand(80, 99) . str_pad(rand(0, 9999999), 7, '0', STR_PAD_LEFT),
                    'recipient_address'  => rand(1, 999) . '/'. rand(1,50) . ' หมู่ ' . rand(1,15) . ' ' . $loc['district'] . ' ' . $loc['province'],
                    'recipient_district' => $loc['district'],
                    'recipient_province' => $loc['province'],
                    'recipient_zipcode'  => $loc['zipcode'],
                    'payment_type'       => $paymentType,
                    'delivery_type'      => 'DROP-OFF',
                    'shipping_date'      => $shippingDate,
                    'product_id'         => $product?->id,
                    'product_name'       => $product?->name ?? 'สินค้าทดสอบ',
                    'product_sku'        => $product?->sku ?? 'TEST-SKU',
                    'seller_sku'         => $product?->seller_sku,
                    'quantity'           => $qty,
                    'item_quantities'    => (string)$qty,
                    'assigned_lot'       => $product ? '03/' . rand(100, 200) : null,
                    'status'             => $status,
                    'label_printed'      => $status !== 'pending',
                    'printed_at'         => $status !== 'pending' ? $now->copy()->subDays($daysAgo) : null,
                    'original_pdf_path'  => null,
                    'pdf_page_number'    => null,
                    'clean_pdf_path'     => null,
                ]);

                $count++;
            }
        });

        $this->command->info("สร้างออเดอร์ทดสอบสำเร็จ {$count} รายการ");
    }
}
