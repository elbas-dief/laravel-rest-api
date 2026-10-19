<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;

class CartController extends Controller
{
    public function index(Request $request)
    {
        $items = $request->user()
            ->cartItems()
            ->with('product.category')
            ->get();

        return response()->json(['data' => $items]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
        ]);

        $user = $request->user();
        $quantity = $data['quantity'] ?? 1;

        $item = $user->cartItems()->firstOrNew([
            'product_id' => $data['product_id'],
        ]);

        $item->quantity = ($item->exists ? $item->quantity : 0) + $quantity;
        $item->save();

        $item->load('product.category');

        return response()->json([
            'message' => 'Produk berhasil ditambahkan ke cart.',
            'data' => $item,
        ], 201);
    }

    public function destroy(Request $request, string $id)
    {
        // Pencarian dibatasi ke cart milik pengguna yang sedang login.
        $item = $request->user()->cartItems()->findOrFail($id);
        $item->delete();

        return response()->json([
            'message' => 'Item berhasil dihapus dari cart.',
        ]);
    }
}
