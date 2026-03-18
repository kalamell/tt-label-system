<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('q');

        $products = Product::withCount([
            'inventoryLots as active_lots_count' => fn($q) => $q->where('status', 'active'),
            'orders',
        ])
        ->when($search, function ($q, $s) {
            $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('sku', 'like', "%{$s}%")
                  ->orWhere('seller_sku', 'like', "%{$s}%");
            });
        })
        ->get();

        return view('products.index', compact('products', 'search'));
    }

    public function create()
    {
        return view('products.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100|unique:products,sku',
            'seller_sku' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'min_stock' => 'required|integer|min:0',
        ]);

        Product::create($validated);

        return redirect()->route('products.index')
            ->with('success', 'เพิ่มสินค้าสำเร็จ');
    }

    public function edit(Product $product)
    {
        return view('products.edit', compact('product'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100|unique:products,sku,' . $product->id,
            'seller_sku' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'min_stock' => 'required|integer|min:0',
        ]);

        $product->update($validated);

        return redirect()->route('products.index')
            ->with('success', 'อัพเดทสินค้าสำเร็จ');
    }

    public function destroy(Product $product)
    {
        $name = $product->name;
        $product->delete();
        return back()->with('success', "ลบสินค้า \"{$name}\" สำเร็จ");
    }

    public function bulkDestroy(Request $request)
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return back()->with('error', 'ไม่ได้เลือกสินค้า');
        }
        $count = Product::whereIn('id', $ids)->delete();
        return back()->with('success', "ลบสินค้า {$count} รายการสำเร็จ");
    }
}
