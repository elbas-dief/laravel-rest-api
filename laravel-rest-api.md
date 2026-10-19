# Tutorial Laravel REST API: Autentikasi dan CRUD Produk Makanan

Tutorial ini membangun REST API dari proyek Laravel 13 yang kosong. Kita membuat API register, login, dan logout; melindungi endpoint dengan middleware autentikasi token; lalu membuat CRUD produk makanan menggunakan Eloquent ORM dan relasi kategori.

Tabel bisnis yang dibuat adalah `categories`, `products`, dan `cart_items`. `cart_items` menghubungkan pengguna dengan produk yang dimasukkan ke cart. Tabel `users` berasal dari Laravel, sedangkan `personal_access_tokens` dibutuhkan Sanctum untuk menyimpan token API.

## Hasil Akhir

Endpoint yang akan dibuat:

| Method | Endpoint | Akses | Kegunaan |
| --- | --- | --- | --- |
| `POST` | `/api/register` | Publik | Membuat akun dan memberikan token. |
| `POST` | `/api/login` | Publik | Memeriksa kredensial dan memberikan token. |
| `POST` | `/api/logout` | Token | Menghapus token yang sedang digunakan. |
| `GET` | `/api/categories` | Token | Melihat daftar kategori. |
| `GET` | `/api/products` | Token | Melihat daftar produk beserta kategorinya. |
| `GET` | `/api/products/{id}` | Token | Melihat satu produk beserta kategorinya. |
| `POST` | `/api/products` | Admin | Membuat produk makanan. |
| `PUT/PATCH` | `/api/products/{id}` | Admin | Memperbarui produk. |
| `DELETE` | `/api/products/{id}` | Admin | Menghapus produk. |
| `GET` | `/api/cart/items` | Token | Melihat semua item cart pengguna yang login. |
| `POST` | `/api/cart/items` | Token | Menambahkan produk ke cart pengguna yang login. |
| `DELETE` | `/api/cart/items/{id}` | Token | Menghapus item dari cart pengguna yang login. |

## 1. Buat Proyek Laravel 13

Pastikan PHP 8.3 atau lebih baru dan Composer tersedia. Buat proyek tanpa memilih atau memasang starter kit:

```bash
composer create-project laravel/laravel:^13.0 food-api
cd food-api
```

## 2. Siapkan Database MySQL

Pastikan MySQL server sedang berjalan. Buat database kosong bernama `food_api` melalui MySQL Workbench, phpMyAdmin, atau command line:

```sql
CREATE DATABASE food_api CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Di file `.env` pada root proyek, pilih koneksi MySQL dan isi kredensial server lokal. Sesuaikan username dan password dengan instalasi MySQL-mu:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=food_api
DB_USERNAME=root
DB_PASSWORD=
```

Jika akun MySQL memiliki password, isi `DB_PASSWORD` dengan password tersebut. Jangan commit kredensial ke Git. Setelah mengubah `.env`, Laravel dapat membaca konfigurasi baru dengan:

```bash
php artisan config:clear
```

## 3. Aktifkan API Routing dan Sanctum

Jalankan perintah berikut:

```bash
php artisan install:api
```

Perintah ini memasang Laravel Sanctum, membuat `routes/api.php`, dan menambahkan migration token personal. Tutorial ini memakai **token Bearer**, bukan cookie/session browser: register atau login mengembalikan token, lalu API client mengirim token itu pada setiap request terlindungi melalui header `Authorization`.

Setelah MySQL dan database `food_api` siap, jalankan migration:

```bash
php artisan migrate
```

Migration ini menyiapkan tabel bawaan Laravel seperti `users` dan tabel token Sanctum. Setelah itu kita menambahkan tiga tabel aplikasi: `categories`, `products`, dan `cart_items`.

## 4. Aktifkan Token pada Model `User`

Buka `app/Models/User.php`. Tambahkan import dan trait Sanctum ke model yang sudah ada:

```php
use Laravel\Sanctum\HasApiTokens;
```

Di dalam class `User`, tambahkan trait `HasApiTokens` bersama trait yang sudah digunakan:

```php
use HasApiTokens, HasFactory, Notifiable;
```

Jangan menghapus konfigurasi lain pada model. Trait ini menyediakan method `createToken()` yang dipakai endpoint register dan login.

## 5. Buat Tabel Kategori dan Produk

Buat model beserta migration:

```bash
php artisan make:model Category -m
php artisan make:model Product -m
php artisan make:model CartItem -m
```

### Migration `categories`

Buka migration yang dibuat untuk categories dan isi method `up()`:

```php
Schema::create('categories', function (Blueprint $table) {
    $table->id();
    $table->string('name')->unique();
    $table->text('description')->nullable();
    $table->timestamps();
});
```

### Migration `products`

Isi migration products. `category_id` menjadi foreign key yang menghubungkan produk ke kategori:

```php
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->foreignId('category_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->text('description')->nullable();
    $table->decimal('price', 12, 2);
    $table->unsignedInteger('stock')->default(0);
    $table->string('image_url')->nullable();
    $table->timestamps();
});
```

### Migration `cart_items`

Satu baris menyatakan bahwa seorang pengguna memasukkan satu produk ke cart. Kombinasi `user_id` dan `product_id` dibuat unik agar produk yang sama tidak menghasilkan baris cart ganda; jumlahnya akan ditambahkan pada baris yang sama.

```php
Schema::create('cart_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->unsignedInteger('quantity')->default(1);
    $table->timestamps();
    $table->unique(['user_id', 'product_id']);
});
```

Setelah mengisi ketiga migration, jalankan:

```bash
php artisan migrate
```

Relasinya: **satu kategori memiliki banyak produk**, **satu produk termasuk ke satu kategori**, dan setiap baris cart dimiliki satu pengguna serta merujuk ke satu produk.

## 6. Definisikan Relasi Eloquent

Pada `app/Models/Category.php`, tambahkan relasi `hasMany`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
```

Pada `app/Models/Product.php`, tambahkan relasi `belongsTo`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'description',
        'price',
        'stock',
        'image_url',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
```

`$fillable` menentukan kolom yang boleh diisi secara mass assignment, misalnya melalui `Product::create($data)`.

Buat `app/Models/CartItem.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = ['product_id', 'quantity'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
```

Tambahkan relasi berikut di model `User`:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;

public function cartItems(): HasMany
{
    return $this->hasMany(CartItem::class);
}
```

Tambahkan relasi berikut di model `Product`:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;

public function cartItems(): HasMany
{
    return $this->hasMany(CartItem::class);
}
```

`user_id` sengaja tidak dimasukkan ke `$fillable` pada `CartItem`. Pemilik item selalu ditentukan dari pengguna yang sudah terautentikasi, bukan dari data kiriman client.

## 7. Seed 5 Kategori dan 25 Produk

Seeder mengisi data contoh ke database supaya endpoint kategori dan produk dapat dicoba. Buat dua seeder:

```bash
php artisan make:seeder CategorySeeder
php artisan make:seeder ProductSeeder
```

### Seeder kategori

Isi `database/seeders/CategorySeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
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
```

### Seeder produk

Buat tepat lima produk pada setiap kategori, sehingga totalnya 25 produk. Isi `database/seeders/ProductSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
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
```

### Jalankan seeder secara berurutan

Edit `database/seeders/DatabaseSeeder.php` agar kategori dibuat sebelum produk:

```php
public function run(): void
{
    $this->call([
        CategorySeeder::class,
        ProductSeeder::class,
    ]);
}
```

Kemudian jalankan:

```bash
php artisan db:seed
```

Seeder menggunakan `firstOrCreate`, sehingga dapat dijalankan ulang tanpa menambahkan duplikat untuk nama kategori dan produk yang sama. Seeder menambahkan data; ia tidak membuat tabel baru.

### Buat Factory untuk Test

Factory menyediakan data palsu yang konsisten — berguna dalam test otomatis untuk membuat record tanpa memikirkan nilai nyatanya.

Buat factory untuk `Category` dan `Product`:

```bash
php artisan make:factory CategoryFactory --model=Category
php artisan make:factory ProductFactory --model=Product
```

Isi `database/factories/CategoryFactory.php`:

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'        => fake()->unique()->word(),
            'description' => fake()->sentence(),
        ];
    }
}
```

Isi `database/factories/ProductFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'name'        => fake()->words(3, true),
            'description' => fake()->sentence(),
            'price'       => fake()->randomFloat(2, 5000, 100000),
            'stock'       => fake()->numberBetween(0, 50),
            'image_url'   => null,
        ];
    }
}
```

`Category::factory()` pada `category_id` otomatis membuat satu kategori baru ketika factory produk dipakai tanpa `category_id` eksplisit — sehingga test tidak perlu menyiapkan kategori secara terpisah.

`UserFactory` sudah ada di `database/factories/UserFactory.php`. Karena kita menambahkan kolom `role`, tambahkan default-nya agar factory selalu menghasilkan data valid:

```php
public function definition(): array
{
    return [
        // field lain...
        'role' => 'user',
    ];
}
```

## 8. Buat Controller Autentikasi API

Buat controller:

```bash
php artisan make:controller Api/AuthController
```

Isi `app/Http/Controllers/Api/AuthController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = new User();
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->password = Hash::make($data['password']);
        $user->save();

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Akun berhasil dibuat.',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json([
                'message' => 'Email atau password salah.',
            ], 401);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil.',
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }
}
```

Token lengkap hanya ditampilkan saat dibuat. Simpan token dari response login/register; kirim token tersebut pada request terlindungi. Logout menghapus token yang dipakai untuk request itu.

## 9. Buat Controller Produk dan Kategori

Buat controller:

```bash
php artisan make:controller Api/ProductController
php artisan make:controller Api/CategoryController
php artisan make:controller Api/CartController
```

Isi `app/Http/Controllers/Api/CategoryController.php` untuk mengambil pilihan kategori:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;

class CategoryController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => Category::orderBy('name')->get(),
        ]);
    }
}
```

Isi `app/Http/Controllers/Api/ProductController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with('category')->latest()->get();

        return response()->json(['data' => $products]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'image_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $product = Product::create($data)->load('category');

        return response()->json([
            'message' => 'Produk berhasil dibuat.',
            'data' => $product,
        ], 201);
    }

    public function show(string $id)
    {
        $product = Product::with('category')->findOrFail($id);

        return response()->json(['data' => $product]);
    }

    public function update(Request $request, string $id)
    {
        $product = Product::findOrFail($id);

        $data = $request->validate([
            'category_id' => ['sometimes', 'required', 'exists:categories,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'stock' => ['sometimes', 'required', 'integer', 'min:0'],
            'image_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
        ]);

        $product->update($data);
        $product->load('category');

        return response()->json([
            'message' => 'Produk berhasil diperbarui.',
            'data' => $product,
        ]);
    }

    public function destroy(string $id)
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json([
            'message' => 'Produk berhasil dihapus.',
        ]);
    }
}
```

Query `Product::with('category')` melakukan eager loading relasi. Karena itu, response daftar dan detail produk menyertakan object `category`, sekaligus menghindari query terpisah untuk kategori setiap produk.

Isi `app/Http/Controllers/Api/CartController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

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
```

Route delete mengembalikan `404` jika item tidak ditemukan di cart pengguna yang login, termasuk saat ID tersebut sebenarnya dimiliki pengguna lain. Dengan query melalui `$request->user()->cartItems()`, client tidak bisa menghapus item cart orang lain dengan menebak ID-nya.

Saat produk yang sama ditambahkan lagi, jumlahnya bertambah pada baris milik pengguna tersebut. Kolom `user_id` tidak pernah diambil dari request; relasi `cartItems()` mengisinya berdasarkan pengguna yang terautentikasi.

Contoh bentuk data produk:

```json
{
  "id": 1,
  "name": "Nasi Goreng",
  "description": "Nasi goreng ayam",
  "price": 25000.0,
  "stock": 10,
  "image_url": "https://example.com/images/nasi-goreng.jpg",
  "category": {
    "id": 1,
    "name": "Makanan Utama",
    "description": "Menu makanan utama"
  },
  "created_at": "2026-10-16T10:00:00.000000Z"
}
```

## 10. Pisahkan Validasi dengan Form Request

Form Request memindahkan aturan validasi dari controller ke class tersendiri. Controller menjadi lebih singkat dan validasi mudah diuji secara terisolasi.

Buat tiga Form Request:

```bash
php artisan make:request StoreProductRequest
php artisan make:request UpdateProductRequest
php artisan make:request StoreCartItemRequest
```

### `StoreProductRequest`

Isi `app/Http/Requests/StoreProductRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'exists:categories,id'],
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price'       => ['required', 'numeric', 'min:0'],
            'stock'       => ['required', 'integer', 'min:0'],
            'image_url'   => ['nullable', 'url', 'max:2048'],
        ];
    }
}
```

`authorize()` mengembalikan `true` karena otorisasi akses sudah ditangani middleware `auth:sanctum` pada route. `rules()` adalah satu-satunya tempat yang perlu diubah jika aturan validasi berubah.

### `UpdateProductRequest`

Isi `app/Http/Requests/UpdateProductRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'required', 'exists:categories,id'],
            'name'        => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'price'       => ['sometimes', 'required', 'numeric', 'min:0'],
            'stock'       => ['sometimes', 'required', 'integer', 'min:0'],
            'image_url'   => ['sometimes', 'nullable', 'url', 'max:2048'],
        ];
    }
}
```

### `StoreCartItemRequest`

Isi `app/Http/Requests/StoreCartItemRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'exists:products,id'],
            'quantity'   => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
```

### Perbarui controller untuk memakai Form Request

Ganti type hint `Request` dengan Form Request yang sesuai. Di `ProductController`, tambahkan import dan ganti signature method:

```php
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;

public function store(StoreProductRequest $request)
{
    $product = Product::create($request->validated())->load('category');

    return response()->json([
        'message' => 'Produk berhasil dibuat.',
        'data'    => $product,
    ], 201);
}

public function update(UpdateProductRequest $request, string $id)
{
    $product = Product::findOrFail($id);
    $product->update($request->validated());
    $product->load('category');

    return response()->json([
        'message' => 'Produk berhasil diperbarui.',
        'data'    => $product,
    ]);
}
```

Di `CartController`, ganti import `Request` dan signature method `store`:

```php
use App\Http\Requests\StoreCartItemRequest;

public function store(StoreCartItemRequest $request)
{
    $data     = $request->validated();
    $user     = $request->user();
    $quantity = $data['quantity'] ?? 1;

    $item = $user->cartItems()->firstOrNew([
        'product_id' => $data['product_id'],
    ]);

    $item->quantity = ($item->exists ? $item->quantity : 0) + $quantity;
    $item->save();
    $item->load('product.category');

    return response()->json([
        'message' => 'Produk berhasil ditambahkan ke cart.',
        'data'    => $item,
    ], 201);
}
```

`$request->validated()` hanya mengembalikan field yang lolos validasi, sehingga field tak terduga dari client tidak ikut masuk ke query.

## 11. Pisahkan Format Response dengan API Resource

API Resource mendefinisikan bentuk JSON yang dikembalikan ke client di class tersendiri. Perubahan format response cukup dilakukan di satu tempat tanpa menyentuh controller.

Buat tiga resource:

```bash
php artisan make:resource CategoryResource
php artisan make:resource ProductResource
php artisan make:resource CartItemResource
```

### `CategoryResource`

Isi `app/Http/Resources/CategoryResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
        ];
    }
}
```

### `ProductResource`

Isi `app/Http/Resources/ProductResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'price'       => (float) $this->price,
            'stock'       => $this->stock,
            'image_url'   => $this->image_url,
            'category'    => new CategoryResource($this->whenLoaded('category')),
            'created_at'  => $this->created_at,
        ];
    }
}
```

`whenLoaded('category')` hanya menyertakan relasi jika sudah di-load (misalnya via `with('category')`), sehingga resource tidak memicu query tambahan saat relasi belum dimuat. `(float) $this->price` memastikan harga dikembalikan sebagai angka, bukan string `"25000.00"`.

### `CartItemResource`

Isi `app/Http/Resources/CartItemResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'quantity' => $this->quantity,
            'product'  => new ProductResource($this->whenLoaded('product')),
        ];
    }
}
```

### Perbarui controller untuk memakai API Resource

Perbarui `CategoryController`:

```php
use App\Http\Resources\CategoryResource;

public function index()
{
    return response()->json([
        'data' => CategoryResource::collection(Category::orderBy('name')->get()),
    ]);
}
```

Perbarui `ProductController` — tambahkan import resource dan ubah setiap return:

```php
use App\Http\Resources\ProductResource;

public function index()
{
    return response()->json([
        'data' => ProductResource::collection(Product::with('category')->latest()->get()),
    ]);
}

public function store(StoreProductRequest $request)
{
    $product = Product::create($request->validated())->load('category');

    return response()->json([
        'message' => 'Produk berhasil dibuat.',
        'data'    => new ProductResource($product),
    ], 201);
}

public function show(string $id)
{
    return response()->json([
        'data' => new ProductResource(Product::with('category')->findOrFail($id)),
    ]);
}

public function update(UpdateProductRequest $request, string $id)
{
    $product = Product::findOrFail($id);
    $product->update($request->validated());
    $product->load('category');

    return response()->json([
        'message' => 'Produk berhasil diperbarui.',
        'data'    => new ProductResource($product),
    ]);
}
```

Perbarui `CartController`:

```php
use App\Http\Resources\CartItemResource;

public function store(StoreCartItemRequest $request)
{
    $data     = $request->validated();
    $user     = $request->user();
    $quantity = $data['quantity'] ?? 1;

    $item = $user->cartItems()->firstOrNew([
        'product_id' => $data['product_id'],
    ]);

    $item->quantity = ($item->exists ? $item->quantity : 0) + $quantity;
    $item->save();
    $item->load('product.category');

    return response()->json([
        'message' => 'Produk berhasil ditambahkan ke cart.',
        'data'    => new CartItemResource($item),
    ], 201);
}
```

Setelah semua resource diterapkan, response JSON memiliki tipe data yang konsisten dan dapat diubah formatnya tanpa menyentuh controller.

## 12. Batasi CRUD Produk untuk Admin

Saat ini setiap pengguna yang login dapat membuat, mengubah, dan menghapus produk. Section ini menambahkan kolom `role` ke tabel `users`, membuat middleware pemeriksa role, lalu memisahkan route produk berdasarkan akses.

### Tambahkan kolom `role` ke tabel `users`

Buat migration:

```bash
php artisan make:migration add_role_to_users_table --table=users
```

Isi method `up()` dan `down()` pada file migration yang dibuat:

```php
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('role')->default('user')->after('email');
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn('role');
    });
}
```

Jalankan migration:

```bash
php artisan migrate
```

Kolom `role` tidak dimasukkan ke `$fillable` pada model `User`. Nilai role ditentukan aplikasi, bukan diterima dari input client — sehingga user tidak bisa mendaftarkan diri sebagai admin.

### Buat middleware `EnsureUserIsAdmin`

```bash
php artisan make:middleware EnsureUserIsAdmin
```

Isi `app/Http/Middleware/EnsureUserIsAdmin.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== 'admin') {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        return $next($request);
    }
}
```

Middleware ini berjalan setelah `auth:sanctum`, sehingga `$request->user()` sudah terisi. Jika role bukan `admin`, request dihentikan dengan `403 Forbidden`.

### Daftarkan alias middleware

Di Laravel 11, alias middleware didaftarkan di `bootstrap/app.php`. Tambahkan pemanggilan `withMiddleware`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'role.admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
    ]);
})
```

### Jadikan akun sebagai admin

Kolom `role` memiliki default `user`. Untuk mengangkat satu akun menjadi admin, jalankan Tinker:

```bash
php artisan tinker
```

```php
\App\Models\User::where('email', 'ayu@example.com')->update(['role' => 'admin']);
```

Ganti email dengan akun yang ingin dijadikan admin. Keluar dari Tinker dengan `exit`.

## 13. Definisikan API Routes

Isi `routes/api.php`:

```php
<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/categories', [CategoryController::class, 'index']);

    // Semua pengguna yang login dapat membaca produk
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{id}', [ProductController::class, 'show']);

    // Hanya admin yang dapat menulis produk
    Route::middleware('role.admin')->group(function () {
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{id}', [ProductController::class, 'update']);
        Route::patch('/products/{id}', [ProductController::class, 'update']);
        Route::delete('/products/{id}', [ProductController::class, 'destroy']);
    });

    Route::get('/cart/items', [CartController::class, 'index']);
    Route::post('/cart/items', [CartController::class, 'store']);
    Route::delete('/cart/items/{id}', [CartController::class, 'destroy']);
});
```

Laravel otomatis memberi prefix `/api` pada route dalam `routes/api.php`. Middleware `auth:sanctum` memeriksa bearer token dan mengembalikan `401 Unauthorized` jika token tidak ada atau tidak valid. Middleware `role.admin` berjalan setelahnya dan mengembalikan `403 Forbidden` jika pengguna bukan admin.

Route produk read (`GET`) sengaja dipisah dari route write (`POST`, `PUT`, `PATCH`, `DELETE`) agar `apiResource` tidak dipakai — `apiResource` mendaftarkan semua method sekaligus sehingga tidak bisa diberi middleware yang berbeda per kelompok method.

## 14. Tangani Error API secara Konsisten

Secara default, Laravel mencoba redirect ke route bernama `login` ketika request tidak terautentikasi. Route itu tidak ada di proyek API-only ini, sehingga menghasilkan error 500. Selain itu, pesan error bawaan seperti `"Unauthenticated."` dan `"No query results for model"` tidak informatif bagi client API.

Perbarui `bootstrap/app.php` menjadi:

```php
<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : route('login'));
        $middleware->alias([
            'role.admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Token tidak ditemukan atau tidak valid. Silakan login terlebih dahulu.',
                ], 401);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                $message = $e->getPrevious() instanceof ModelNotFoundException
                    ? 'Data tidak ditemukan.'
                    : 'Endpoint tidak ditemukan.';

                return response()->json(['message' => $message], 404);
            }
        });
    })->create();
```

Tiga hal yang dikonfigurasi:

- **`redirectGuestsTo`** — mengembalikan `null` untuk `api/*` agar middleware autentikasi tidak mencoba redirect ke route `login`. Akibatnya `AuthenticationException` dilempar dan ditangkap exception handler di bawah.
- **`AuthenticationException`** — mengembalikan `401` dengan pesan informatif saat token tidak ada atau tidak valid.
- **`NotFoundHttpException`** — membedakan antara data tidak ditemukan (`findOrFail` melempar `ModelNotFoundException` yang dikonversi Laravel menjadi `NotFoundHttpException`) dan endpoint yang tidak ada, lalu mengembalikan pesan `404` yang sesuai.

`shouldRenderJsonWhen` memastikan seluruh error pada `api/*` selalu dikembalikan sebagai JSON, bukan HTML, tanpa perlu mengirim header `Accept: application/json` secara manual.

## 15. Jalankan dan Periksa Route

Jalankan aplikasi:

```bash
php artisan serve
```

Lihat semua API route:

```bash
php artisan route:list --path=api
```

Gunakan Postman, Insomnia, Bruno, atau `curl` untuk mengirim request. Set header berikut pada request JSON:

```http
Accept: application/json
Content-Type: application/json
```

Untuk route yang dilindungi, kirim header:

```http
Authorization: Bearer 1|abc123tokencontoh
```

Nilai setelah `Bearer ` adalah token dari response register/login. Contoh di atas hanya ilustrasi: jangan mengetik tanda `<` dan `>` di header. Token harus dikirim dalam header, bukan sebagai query parameter atau field JSON.

### Register

`POST http://127.0.0.1:8000/api/register`

```json
{
  "name": "Ayu",
  "email": "ayu@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

Salin nilai `token` dari response.

### Login

`POST http://127.0.0.1:8000/api/login`

```json
{
  "email": "ayu@example.com",
  "password": "password123"
}
```

Login juga mengembalikan token. Pakai token itu sebagai bearer token untuk request yang memerlukan autentikasi.

Contoh menggunakan token tersebut untuk mengambil daftar produk:

```bash
curl -H 'Accept: application/json' \
     -H 'Authorization: Bearer 1|abc123tokencontoh' \
     http://127.0.0.1:8000/api/products
```

Ganti token contoh dengan nilai token sebenarnya. Middleware `auth:sanctum` membaca header Bearer dan mengidentifikasi pengguna sebelum controller dijalankan.

### Daftar kategori

`GET http://127.0.0.1:8000/api/categories`

### Membuat produk

`POST http://127.0.0.1:8000/api/products`

```json
{
  "category_id": 1,
  "name": "Nasi Goreng",
  "description": "Nasi goreng ayam",
  "price": 25000,
  "stock": 10,
  "image_url": "https://example.com/images/nasi-goreng.jpg"
}
```

### Membaca produk dengan kategori

- Semua produk: `GET http://127.0.0.1:8000/api/products`
- Satu produk: `GET http://127.0.0.1:8000/api/products/1`

Keduanya menyertakan relasi `category` karena controller menjalankan `with('category')`.

### Memperbarui produk

Endpoint update menerima dua method: **PUT** dan **PATCH**. Keduanya memanggil method `update` yang sama di `ProductController` dan menggunakan `UpdateProductRequest` dengan rule `sometimes`.

**PUT** — dipakai saat mengirim seluruh field produk sekaligus. Cocok ketika client memiliki form edit yang memuat semua field dan mengirim ulang semua nilainya:

`PUT http://127.0.0.1:8000/api/products/1`

```json
{
  "category_id": 1,
  "name": "Nasi Goreng Spesial",
  "description": "Nasi goreng ayam dengan telur",
  "price": 27000,
  "stock": 8,
  "image_url": "https://example.com/images/nasi-goreng.jpg"
}
```

**PATCH** — dipakai saat hanya mengubah satu atau beberapa field tanpa mengirim ulang seluruh data. Cocok untuk aksi spesifik seperti update stok setelah transaksi atau koreksi harga:

`PATCH http://127.0.0.1:8000/api/products/1`

```json
{
  "price": 27000,
  "stock": 8
}
```

Karena `UpdateProductRequest` menggunakan rule `sometimes` pada semua field, field yang tidak dikirim tidak akan disentuh di database — perilaku ini sama untuk PUT maupun PATCH. Perbedaannya hanya pada konvensi: PUT mengomunikasikan bahwa seluruh resource dikirim, PATCH mengomunikasikan bahwa hanya sebagian yang diubah.

### Menghapus produk dan logout

- `DELETE http://127.0.0.1:8000/api/products/1`
- `POST http://127.0.0.1:8000/api/logout`

Keduanya memerlukan bearer token. Logout menghapus token saat ini; gunakan token baru setelah login kembali.

### Menambahkan produk ke cart

`POST http://127.0.0.1:8000/api/cart/items`

```json
{
  "product_id": 1,
  "quantity": 2
}
```

`quantity` opsional; jika tidak dikirim, nilainya `1`. Jika produk sudah ada di cart pengguna yang login, endpoint menambahkan jumlahnya. Response menyertakan produk beserta kategorinya.

### Melihat semua item cart

`GET http://127.0.0.1:8000/api/cart/items`

Mengembalikan semua item cart milik pengguna yang sedang login beserta detail produk dan kategorinya.

### Menghapus item cart

`DELETE http://127.0.0.1:8000/api/cart/items/1`

ID `1` adalah ID baris `cart_items`, bukan ID produk. Item hanya dihapus jika baris tersebut dimiliki pengguna pada bearer token. Jika baris tidak ada atau milik pengguna lain, API mengembalikan `404` dengan pesan `"Data tidak ditemukan."`.

Semua endpoint cart memerlukan header `Authorization: Bearer ...`.

## 16. Izinkan Client dari Server/Origin Berbeda (CORS)

Jika aplikasi frontend berjalan di origin berbeda, misalnya frontend `http://localhost:5173` dan API `http://127.0.0.1:8000`, browser menerapkan aturan **CORS (Cross-Origin Resource Sharing)**. Origin terdiri dari skema, host, dan port; perubahan salah satunya membuat origin berbeda.

CORS mengatur apakah browser boleh membaca response API dari origin tersebut. CORS **bukan autentikasi atau authorization**. Endpoint tetap dilindungi `auth:sanctum`, dan client tetap harus mengirim bearer token yang valid.

Laravel menyertakan middleware CORS dan menangani request preflight `OPTIONS` secara otomatis. Untuk mengubah origin yang diizinkan, terbitkan file konfigurasi:

```bash
php artisan config:publish cors
```

Edit `config/cors.php`. Contoh untuk development dengan frontend pada `http://localhost:5173`:

```php
'paths' => ['api/*'],
'allowed_methods' => ['*'],
'allowed_origins' => ['http://localhost:5173'],
'allowed_origins_patterns' => [],
'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Requested-With'],
'exposed_headers' => [],
'max_age' => 0,
'supports_credentials' => false,
```

Sesuaikan nilai tersebut di dalam array `return [...]` yang sudah ada pada file konfigurasi. Saat aplikasi dipublikasikan, ganti origin development dengan origin frontend sebenarnya, misalnya `https://app.example.com`. Jangan memakai `*` untuk origin produksi tanpa alasan yang jelas. Karena tutorial ini memakai bearer token pada header Authorization, `supports_credentials` tetap `false`; konfigurasi cookie/session lintas origin tidak diperlukan.

Request browser dengan header `Authorization` biasanya memicu preflight `OPTIONS`. Laravel akan menjawabnya melalui middleware CORS. Pastikan path `api/*`, method yang diperlukan, dan header `Authorization` diizinkan. Setelah mengubah konfigurasi, bersihkan cache:

```bash
php artisan config:clear
```

Contoh pemanggilan dari frontend JavaScript:

```javascript
const response = await fetch('http://127.0.0.1:8000/api/products', {
    headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${token}`,
    },
});

const result = await response.json();
```

Ganti `token` dengan token yang diperoleh saat register/login. Jika request dilakukan oleh backend server ke server secara langsung, aturan CORS browser tidak berlaku; CORS dibutuhkan saat browser frontend meminta API pada origin berbeda.

## 17. Tulis Feature Test

Feature test menguji endpoint dari luar, persis seperti client nyata, tanpa server yang harus berjalan. Laravel mengirim request melalui router, middleware, controller, dan database in-memory, lalu kita periksa responsnya.

### Konfigurasi database untuk test

Buka `phpunit.xml` di root proyek. Pastikan dua baris berikut tidak dalam kondisi komentar agar test menggunakan SQLite in-memory:

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

Database in-memory dibuat baru setiap kali test suite dijalankan dan tidak mengubah data development sama sekali.

### Test autentikasi

Buat `tests/Feature/RegisterApiTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_berhasil_dengan_data_valid(): void
    {
        $response = $this->postJson('/api/register', [
            'name'                  => 'Ayu',
            'email'                 => 'ayu@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'token',
                'user' => ['id', 'name', 'email'],
            ]);
    }

    public function test_register_gagal_jika_email_sudah_dipakai(): void
    {
        User::factory()->create(['email' => 'ayu@example.com']);

        $response = $this->postJson('/api/register', [
            'name'                  => 'Ayu Lain',
            'email'                 => 'ayu@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_register_gagal_jika_password_tidak_cocok(): void
    {
        $response = $this->postJson('/api/register', [
            'name'                  => 'Ayu',
            'email'                 => 'ayu@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'salah456',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_register_gagal_jika_field_wajib_tidak_dikirim(): void
    {
        $response = $this->postJson('/api/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }
}
```

`RefreshDatabase` mengosongkan database sebelum setiap test sehingga test tidak saling mempengaruhi. `postJson` otomatis menambahkan header `Accept: application/json` dan `Content-Type: application/json`.

### Test produk dan otorisasi

Buat `tests/Feature/ProductApiTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_products_tanpa_token_ditolak(): void
    {
        $response = $this->getJson('/api/products');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Token tidak ditemukan atau tidak valid. Silakan login terlebih dahulu.']);
    }

    public function test_get_products_berhasil_dengan_token(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_get_products_mengembalikan_daftar_produk_beserta_kategori(): void
    {
        $user     = User::factory()->create();
        $category = Category::factory()->create();
        Product::factory()->count(3)->create(['category_id' => $category->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'price', 'stock', 'category'],
                ],
            ]);
    }

    public function test_user_biasa_tidak_bisa_membuat_produk(): void
    {
        $user     = User::factory()->create(['role' => 'user']);
        $category = Category::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/products', [
                'category_id' => $category->id,
                'name'        => 'Nasi Goreng',
                'price'       => 25000,
                'stock'       => 10,
            ]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Akses ditolak.']);
    }

    public function test_admin_bisa_membuat_produk(): void
    {
        $admin    = User::factory()->create(['role' => 'admin']);
        $category = Category::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/products', [
                'category_id' => $category->id,
                'name'        => 'Nasi Goreng',
                'price'       => 25000,
                'stock'       => 10,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => ['id', 'name', 'price', 'stock', 'category'],
            ]);
    }
}
```

`actingAs($user, 'sanctum')` mensimulasikan pengguna yang sudah login tanpa harus memanggil endpoint login. Test otorisasi memverifikasi bahwa middleware `role.admin` berjalan dengan benar: user biasa mendapat `403`, admin mendapat `201`.

### Jalankan test

```bash
php artisan test
```

Untuk menjalankan satu file test saja:

```bash
php artisan test tests/Feature/RegisterApiTest.php
php artisan test tests/Feature/ProductApiTest.php
```

## Ringkasan Flow

```text
Register/login
    → Laravel memvalidasi kredensial
    → Sanctum membuat personal access token
    → client menyimpan token

Request tanpa token (api/*)
    → middleware auth:sanctum melempar AuthenticationException
    → exception handler mengembalikan 401 JSON

Request write produk oleh user biasa
    → middleware auth:sanctum lolos
    → middleware role.admin memeriksa role
    → mengembalikan 403 Forbidden

Request CRUD produk (admin)
    → Form Request memvalidasi input sebelum masuk controller
    → ProductController menjalankan query Eloquent
    → ProductResource memformat JSON response (price sebagai float, relasi category)

Request cart
    → middleware auth:sanctum mengidentifikasi pengguna
    → GET mengembalikan cart milik pengguna itu saja
    → POST menyimpan item melalui relasi pengguna (user_id tidak dipercaya dari client)
    → DELETE mencari item hanya dalam cart pengguna itu
    → pengguna lain tidak dapat menghapus item tersebut
    → findOrFail yang gagal dikembalikan sebagai 404 JSON
```

## Latihan

1. Tambahkan pencarian produk berdasarkan nama menggunakan query parameter `?search=nasi`.
2. Tambahkan filter berdasarkan `category_id`.
3. Ubah daftar produk agar memakai pagination.
4. Tambahkan endpoint untuk mencabut seluruh token pengguna saat ini.
5. Tambahkan `PUT /api/cart/items/{id}` agar pengguna bisa memperbarui quantity item cart.

## Referensi

- [Laravel 13 — Routing dan API Routes](https://laravel.com/framework/docs/13.x/routing)
- [Laravel 13 — Sanctum](https://laravel.com/framework/docs/13.x/sanctum)
- [Laravel 13 — Eloquent Relationships](https://laravel.com/framework/docs/13.x/eloquent-relationships)
- [Laravel 13 — Eloquent API Resources](https://laravel.com/framework/docs/13.x/eloquent-resources)
