<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         $productsByCategory = [
            'Makanan Utama' => [
                ['Nasi Goreng Ayam', 'Nasi goreng dengan ayam', 25000],
                ['Mie Goreng', 'Mie goreng sayur dan telur', 22000],
                ['Ayam Bakar', 'Ayam bakar dengan nasi', 30000],
                ['Soto Ayam', 'Soto ayam kuah bening', 24000],
                ['Nasi Uduk', 'Nasi uduk dengan lauk sederhana', 23000],
            ],
            'Lauk' => [
                ['Rendang Sapi', 'Rendang sapi berbumbu', 18000],
                ['Tempe Goreng', 'Tempe goreng renyah', 8000],
                ['Tahu Isi', 'Tahu isi sayuran', 10000],
                ['Telur Balado', 'Telur dengan sambal balado', 12000],
                ['Ayam Goreng', 'Ayam goreng bumbu kuning', 17000],
            ],
            'Camilan' => [
                ['Pisang Goreng', 'Pisang goreng hangat', 12000],
                ['Kentang Goreng', 'Kentang goreng dengan saus', 18000],
                ['Siomay', 'Siomay ikan dengan bumbu kacang', 16000],
                ['Risol Mayo', 'Risol isi mayo dan smoked beef', 14000],
                ['Singkong Keju', 'Singkong goreng tabur keju', 15000],
            ],
            'Dessert' => [
                ['Puding Cokelat', 'Puding cokelat dingin', 12000],
                ['Pancake', 'Pancake dengan madu', 20000],
                ['Es Krim Vanila', 'Es krim rasa vanila', 15000],
                ['Pisang Cokelat', 'Pisang dengan cokelat leleh', 16000],
                ['Klepon', 'Klepon isi gula merah', 10000],
            ],
            'Minuman' => [
                ['Es Teh', 'Teh manis dingin', 7000],
                ['Jus Alpukat', 'Jus alpukat segar', 18000],
                ['Kopi Susu', 'Kopi dengan susu', 16000],
                ['Air Mineral', 'Air mineral kemasan', 5000],
                ['Lemon Tea', 'Teh dengan perasan lemon', 12000],
            ],
        ];

        foreach ($productsByCategory as $categoryName => $products) {
            $category = Category::where('name', $categoryName)->firstOrFail();

            foreach ($products as [$name, $description, $price]) {
                Product::firstOrCreate(
                    ['name' => $name],
                    [
                        'category_id' => $category->id,
                        'description' => $description,
                        'price' => $price,
                        'stock' => 20,
                    ]
                );
            }
        }
    }
}