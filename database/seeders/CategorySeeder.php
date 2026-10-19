<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Makanan Utama', 'description' => 'Menu makanan utama'],
            ['name' => 'Lauk', 'description' => 'Lauk dan hidangan pendamping'],
            ['name' => 'Camilan', 'description' => 'Makanan ringan'],
            ['name' => 'Dessert', 'description' => 'Hidangan penutup'],
            ['name' => 'Minuman', 'description' => 'Minuman dingin dan hangat'],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(
                ['name' => $category['name']],
                ['description' => $category['description']]
            );
        }
    }
}
