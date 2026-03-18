<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Services\PdfParserService;
use App\Services\LabelGeneratorService;
use App\Services\FifoInventoryService;
use App\Services\CustomerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function __construct(
        protected PdfParserService $pdfParser,
        protected LabelGeneratorService $labelGenerator,
        protected FifoInventoryService $fifoService,
        protected CustomerService $customerService,
    ) {}

    /**
     * รายการ Order ทั้งหมด
     */
    public function index(Request $request)
    {
        $query = Order::with('product')->orderByRaw('COALESCE(shipping_date, created_at) DESC');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tracking_number', 'like', "%{$search}%")
                    ->orWhere('order_id', 'like', "%{$search}%")
                    ->orWhere('recipient_name', 'like', "%{$search}%")
                    ->orWhere('carrier', 'like', "%{$search}%");
            });
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($carrier = $request->get('carrier')) {
            $query->where('carrier', $carrier);
        }

        if ($date = $request->get('date')) {
            $query->whereDate('shipping_date', $date);
        }

        $orders = $query->paginate(50);

        return view('orders.index', compact('orders'));
    }

    /**
     * แสดงรายละเอียด Order
     */
    public function show(Order $order)
    {
        $order->load('product', 'customer', 'transactions.inventoryLot');
        return view('orders.show', compact('order'));
    }

    /**
     * หน้า Upload PDF
     */
    public function uploadForm()
    {
        return view('orders.upload');
    }

    /**
     * Upload PDF → Parse → เก็บใน session → ไป Confirm page
     */
    public function upload(Request $request)
    {
        $request->validate([
            'pdf_file' => 'required|file|mimes:pdf|max:51200',
        ]);

        // เก็บไฟล์ต้นฉบับ
        $originalPath = $request->file('pdf_file')->store('uploads/originals', 'public_files');

        // อ่าน PDF
        $parsedOrders = $this->pdfParser->parseFile(public_path($originalPath));

        if (empty($parsedOrders)) {
            return back()->with('error', 'ไม่พบข้อมูลออเดอร์ใน PDF กรุณาตรวจสอบไฟล์');
        }

        // สรุปสินค้า unique ที่พบ (groupby seller_sku)
        $uniqueProducts = $this->_buildProductMap($parsedOrders);

        session([
            'upload_preview' => [
                'original_path'   => $originalPath,
                'parsed_orders'   => $parsedOrders,
                'unique_products' => $uniqueProducts,
            ],
        ]);

        return redirect()->route('orders.upload.confirm');
    }

    /**
     * สร้าง product map จาก parsed orders
     * key = seller_sku (หรือ product_sku ถ้าไม่มี)
     */
    private function _buildProductMap(array $parsedOrders): array
    {
        $map = [];

        foreach ($parsedOrders as $parsed) {
            // แต่ละ order อาจมีหลายรายการสินค้า คั่นด้วย " | "
            $names     = array_map('trim', explode('|', $parsed['product_name'] ?? ''));
            $skus      = array_map('trim', explode('|', $parsed['product_sku'] ?? ''));
            $sellers   = array_map('trim', explode('|', $parsed['seller_sku'] ?? ''));
            $itemQtys  = array_map('trim', explode('|', $parsed['item_quantities'] ?? ($parsed['quantity'] ?? '1')));

            $count = max(count($names), count($sellers), 1);

            for ($i = 0; $i < $count; $i++) {
                $name      = $names[$i]   ?? '';
                $sku       = $skus[$i]    ?? '';
                $sellerSku = $sellers[$i] ?? '';
                $qty       = (int)($itemQtys[$i] ?? 1);

                // ใช้ seller_sku เป็น key หลัก ถ้าไม่มีใช้ product_sku
                $key = $sellerSku ?: $sku ?: 'UNKNOWN_' . $i;

                if (!isset($map[$key])) {
                    // หาสินค้าในระบบ
                    $product = Product::where('seller_sku', $sellerSku)
                        ->orWhere('sku', $sellerSku)
                        ->orWhere('sku', $sku)
                        ->first();

                    $map[$key] = [
                        'key'          => $key,
                        'product_name' => $name,
                        'product_sku'  => $sku,
                        'seller_sku'   => $sellerSku,
                        'total_qty'    => 0,
                        'order_count'  => 0,
                        'product_id'   => $product?->id,
                        'matched_name' => $product?->name,
                        'stock'        => $product?->total_stock ?? 0,
                        'is_new'       => $product === null,
                    ];
                }

                $map[$key]['total_qty']   += $qty;
                $map[$key]['order_count'] += 1;
            }
        }

        return $map;
    }

    /**
     * หน้า Confirm — แสดงสินค้าที่พบ + checkbox ตัดสต๊อก
     */
    public function confirmForm()
    {
        $preview = session('upload_preview');
        if (!$preview) {
            return redirect()->route('orders.upload.form')->with('error', 'Session หมดอายุ กรุณา upload ใหม่');
        }

        $parsedOrders   = $preview['parsed_orders'];
        $uniqueProducts = $preview['unique_products'];
        $totalOrders    = count($parsedOrders);
        $existingCount  = Order::whereIn('order_id', array_column($parsedOrders, 'order_id'))->count();

        return view('orders.confirm', compact('parsedOrders', 'uniqueProducts', 'totalOrders', 'existingCount'));
    }

    /**
     * Confirm Upload — บันทึกออเดอร์ + auto-create สินค้าใหม่ + ตัดสต๊อก
     */
    public function confirmUpload(Request $request)
    {
        $preview = session('upload_preview');
        if (!$preview) {
            return redirect()->route('orders.upload.form')->with('error', 'Session หมดอายุ กรุณา upload ใหม่');
        }

        $originalPath   = $preview['original_path'];
        $parsedOrders   = $preview['parsed_orders'];
        $uniqueProducts = $preview['unique_products'];
        $deductKeys     = $request->input('deduct', []); // seller_sku keys ที่ติ๊กว่าจะตัดสต๊อก

        $savedCount = 0;
        $skipCount  = 0;
        $errors     = [];
        $newOrders  = collect();

        DB::beginTransaction();
        try {
            // 1. Auto-create สินค้าใหม่ทุกตัวที่พบใน PDF (ไม่ขึ้นกับ checkbox ตัดสต๊อก)
            $resolvedProducts = []; // key => product_id
            foreach ($uniqueProducts as $key => $info) {
                if ($info['is_new']) {
                    // สร้างสินค้าใหม่อัตโนมัติ
                    $skuBase = $info['seller_sku'] ?: $info['product_sku'] ?: 'AUTO-' . strtoupper(substr(md5($key), 0, 6));
                    $sku     = $skuBase;
                    $suffix  = 1;
                    while (Product::where('sku', $sku)->exists()) {
                        $sku = $skuBase . '-' . $suffix++;
                    }

                    $product = Product::create([
                        'name'       => $info['product_name'] ?: $key,
                        'sku'        => $sku,
                        'seller_sku' => $info['seller_sku'] ?: null,
                        'price'      => 0,
                        'min_stock'  => 5,
                        'is_active'  => true,
                    ]);
                    $resolvedProducts[$key] = $product->id;
                    $errors[] = "✅ สร้างสินค้าใหม่: {$product->name} (SKU: {$product->sku})";
                } else {
                    $resolvedProducts[$key] = $info['product_id'];
                }
            }

            // 2. บันทึกออเดอร์
            foreach ($parsedOrders as $parsed) {
                if (Order::where('order_id', $parsed['order_id'])->exists()) {
                    $skipCount++;
                    continue;
                }

                // หา product_id หลักสำหรับ order นี้ (ใช้รายการแรกที่ match)
                $primaryProductId  = null;
                $primarySellerSku  = trim(explode('|', $parsed['seller_sku'] ?? '')[0]);
                $primaryProductSku = trim(explode('|', $parsed['product_sku'] ?? '')[0]);
                $primaryKey        = $primarySellerSku ?: $primaryProductSku;

                if ($primaryKey && isset($resolvedProducts[$primaryKey])) {
                    $primaryProductId = $resolvedProducts[$primaryKey];
                } elseif ($primaryKey && isset($uniqueProducts[$primaryKey]['product_id'])) {
                    $primaryProductId = $uniqueProducts[$primaryKey]['product_id'];
                }

                $order = Order::create([
                    'order_id'           => $parsed['order_id'],
                    'carrier'            => $parsed['carrier']       ?? null,
                    'service_type'       => $parsed['service_type']  ?? null,
                    'tracking_number'    => $parsed['tracking_number'],
                    'sorting_code'       => $parsed['sorting_code'],
                    'sorting_code_2'     => $parsed['sorting_code_2'],
                    'route_code'         => $parsed['route_code'] ?? null,
                    'sender_name'        => $parsed['sender_name'] ?? null,
                    'sender_address'     => $parsed['sender_address'] ?? null,
                    'recipient_name'     => $parsed['recipient_name'],
                    'recipient_phone'    => $parsed['recipient_phone'],
                    'recipient_address'  => $parsed['recipient_address'],
                    'recipient_district' => $parsed['recipient_district'],
                    'recipient_province' => $parsed['recipient_province'],
                    'recipient_zipcode'  => $parsed['recipient_zipcode'],
                    'payment_type'       => $parsed['payment_type'],
                    'delivery_type'      => $parsed['delivery_type'],
                    'shipping_date'      => isset($parsed['shipping_date']) && $parsed['shipping_date']
                        ? \Carbon\Carbon::createFromFormat('d-m-Y', $parsed['shipping_date'])
                        : null,
                    'product_id'         => $primaryProductId,
                    'product_name'       => $parsed['product_name'],
                    'product_sku'        => $parsed['product_sku'],
                    'seller_sku'         => $parsed['seller_sku'],
                    'quantity'           => $parsed['quantity'],
                    'item_quantities'    => $parsed['item_quantities'] ?? null,
                    'original_pdf_path'  => $originalPath,
                    'pdf_page_number'    => $parsed['page_number'],
                    'status'             => 'pending',
                ]);

                // 2.5 Sync customer
                $customer = $this->customerService->syncFromOrder($parsed);
                if ($customer) {
                    $order->update(['customer_id' => $customer->id]);
                }

                // 3. ตัดสต๊อก FIFO สำหรับแต่ละรายการสินค้าที่ติ๊กไว้
                $itemQtys = array_map('trim', explode('|', $parsed['item_quantities'] ?? ($parsed['quantity'] ?? '1')));
                $sellers  = array_map('trim', explode('|', $parsed['seller_sku'] ?? ''));
                $pSkus    = array_map('trim', explode('|', $parsed['product_sku'] ?? ''));

                $firstLot = null;
                foreach ($sellers as $i => $sellerSku) {
                    $itemKey = $sellerSku ?: ($pSkus[$i] ?? '');
                    if (!$itemKey || !in_array($itemKey, $deductKeys)) continue;
                    if (!isset($resolvedProducts[$itemKey])) continue;

                    $qty    = (int)($itemQtys[$i] ?? 1);
                    $result = $this->fifoService->deductStock($resolvedProducts[$itemKey], $qty, $order->id);
                    if ($result['success'] && !$firstLot) {
                        $firstLot = $result['lot_number'] ?? null;
                    } elseif (!$result['success']) {
                        $errors[] = "Order {$parsed['order_id']}: {$result['message']}";
                    }
                }

                if ($firstLot) {
                    $order->update(['assigned_lot' => $firstLot]);
                }

                $newOrders->push($order);
                $savedCount++;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }

        // Render PNG (best-effort)
        if ($newOrders->isNotEmpty()) {
            try {
                $this->labelGenerator->renderPagesFromPdf(public_path($originalPath), $newOrders);
            } catch (\Exception $e) {
                $errors[] = 'PNG render ล้มเหลว: ' . $e->getMessage();
            }
        }

        session()->forget('upload_preview');

        return redirect()->route('orders.index')
            ->with('success', "นำเข้าสำเร็จ {$savedCount} รายการ" . ($skipCount ? " (ข้าม {$skipCount} ซ้ำ)" : ''))
            ->with('import_errors', $errors);
    }

    /**
     * Print Label เดี่ยว
     */
    public function printLabel(Order $order)
    {
        $path = $this->labelGenerator->generateSingleLabel($order);
        return response()->download($path);
    }

    /**
     * Build filtered query (ใช้ร่วมกับ index + batch actions)
     */
    private function _buildFilteredQuery(Request $request)
    {
        $query = Order::orderByRaw('COALESCE(shipping_date, created_at) DESC');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tracking_number', 'like', "%{$search}%")
                    ->orWhere('order_id', 'like', "%{$search}%")
                    ->orWhere('recipient_name', 'like', "%{$search}%")
                    ->orWhere('carrier', 'like', "%{$search}%");
            });
        }
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($carrier = $request->get('carrier')) {
            $query->where('carrier', $carrier);
        }
        if ($date = $request->get('date')) {
            $query->whereDate('shipping_date', $date);
        }

        return $query;
    }

    /**
     * คืน collection ของ Order จาก request (select_all หรือ order_ids)
     */
    private function _resolveOrders(Request $request)
    {
        if ($request->boolean('select_all')) {
            return $this->_buildFilteredQuery($request)->get();
        }

        $request->validate([
            'order_ids'   => 'required|array|min:1',
            'order_ids.*' => 'exists:orders,id',
        ]);
        return Order::whereIn('id', $request->input('order_ids'))->get();
    }

    /**
     * Print Label หลายรายการ (Batch PDF รวมไฟล์เดียว)
     */
    public function printBatch(Request $request)
    {
        $orders = $this->_resolveOrders($request);
        $path   = $this->labelGenerator->generateBatchLabels($orders);

        return response()->download($path);
    }

    /**
     * Download Label หลายรายการเป็น ZIP (แต่ละ order = ไฟล์ PDF แยก)
     */
    public function downloadZip(Request $request)
    {
        $orders = $this->_resolveOrders($request);

        $orders = Order::whereIn('id', $request->input('order_ids'))->get();

        // Generate label สำหรับ order ที่ยังไม่มีไฟล์
        foreach ($orders as $order) {
            if (!$order->clean_pdf_path || !file_exists(public_path($order->clean_pdf_path))) {
                $this->labelGenerator->generateSingleLabel($order);
                $order->refresh();
            }
        }

        // สร้าง ZIP
        $zipFilename = 'labels_' . now()->format('Ymd_His') . '.zip';
        $zipPath     = public_path("labels/{$zipFilename}");

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return back()->with('error', 'ไม่สามารถสร้างไฟล์ ZIP ได้');
        }

        foreach ($orders as $order) {
            if ($order->clean_pdf_path) {
                $labelPath = public_path($order->clean_pdf_path);
                if (file_exists($labelPath)) {
                    $zip->addFile($labelPath, "{$order->tracking_number}.pdf");
                }
            }
        }

        $zip->close();

        return response()->download($zipPath, $zipFilename)->deleteFileAfterSend(true);
    }

    /**
     * ลบออเดอร์ที่เลือก + คืนสต๊อก FIFO
     */
    public function deleteBatch(Request $request)
    {
        $orderIds = $request->boolean('select_all')
            ? $this->_buildFilteredQuery($request)->pluck('id')
            : collect($request->input('order_ids', []));

        $orders = Order::with('transactions.inventoryLot')
            ->whereIn('id', $orderIds)
            ->get();

        $deletedCount = 0;

        DB::beginTransaction();
        try {
            foreach ($orders as $order) {
                // คืนสต๊อก — หา transactions ประเภท 'out' ของ order นี้
                foreach ($order->transactions->where('type', 'out') as $txn) {
                    $lot = $txn->inventoryLot;
                    if ($lot) {
                        $lot->quantity_remaining += $txn->quantity;
                        if ($lot->status === 'depleted' && $lot->quantity_remaining > 0) {
                            $lot->status = 'active';
                        }
                        $lot->save();
                    }
                }

                // ลบ transactions + order
                $order->transactions()->delete();

                // ลด total_orders ของ customer
                if ($order->customer_id) {
                    $order->customer?->decrement('total_orders');
                }

                $order->delete();
                $deletedCount++;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }

        return back()->with('success', "ลบ {$deletedCount} ออเดอร์สำเร็จ และคืนสต๊อกเรียบร้อยแล้ว");
    }

    /**
     * Print Label จากไฟล์ PDF ต้นฉบับ (Overlay method)
     */
    public function printOverlay(Request $request)
    {
        $request->validate([
            'pdf_file' => 'required|file|mimes:pdf|max:51200',
        ]);

        $file     = $request->file('pdf_file');
        $tempPath = $file->store('uploads/temp', 'public_files');
        $fullPath = public_path($tempPath);

        // ดึง orders จาก database ตาม tracking numbers ใน PDF
        $parsedOrders    = $this->pdfParser->parseFile($fullPath);
        $trackingNumbers = collect($parsedOrders)->pluck('tracking_number')->filter();
        $orders          = Order::whereIn('tracking_number', $trackingNumbers)->get();

        $cleanPdfPath = $this->labelGenerator->overlayOriginalPdf($fullPath, $orders);

        return response()->download($cleanPdfPath, 'clean_labels.pdf');
    }
}
