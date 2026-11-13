# REST API produk dan kategori dengan PHP

API REST tanpa framework yang ditulis dengan PHP dan PDO, dengan MySQL sebagai penyimpan data. API ini mengelola dua resource, yaitu produk dan kategori. Proyek ini adalah tugas kelompok mata kuliah Pemrograman Web 1 pada materi REST API dengan PHP.

Produk mengikuti contoh CRUD dari materi dosen, lalu ditambah pencarian dan pagination. Kategori adalah resource baru dengan pola CRUD yang sama.

## Yang dikerjakan

| Instruksi tugas | Dikerjakan di |
|---|---|
| Tabel `categories` dengan id, name, description, created_at | `database/db_api.sql` |
| CRUD kategori dengan pola yang sama seperti produk | `api/categories.php` |
| Pencarian produk berdasarkan nama lewat parameter `q` | `api/products.php` |
| Pagination dengan parameter `page` dan `limit` | `api/products.php` |
| Minimal 5 skenario uji berhasil dan 5 gagal | 7 berhasil dan 7 gagal, lihat bagian pengujian |
| Source code, SQL, tangkapan layar Postman, dan penjelasan status code | Repository ini untuk kode, SQL, dan koleksi request. Tangkapan layar dibuat dengan Bruno, bukan Postman, dan disusun terpisah bersama laporan |

## Kebutuhan

- PHP 8.5 dengan extension `pdo_mysql`, `json`, dan `mbstring`
- MySQL yang bisa diakses lewat TCP, secara default di `127.0.0.1:3306`
- Bruno atau Postman untuk menjalankan koleksi request (opsional)

Proyek diuji dengan PHP 8.5.12. Kalau di mesinmu terpasang lebih dari satu PHP, misalnya XAMPP bersama Homebrew, cek dulu versi yang dipakai shell:

```bash
php -v
```

Jika hasilnya bukan 8.5.x, panggil binary yang benar secara langsung, misalnya `/opt/homebrew/bin/php`.

## Menyiapkan database

Salin `.env.example` menjadi `.env`, lalu isi `DB_USER` dan `DB_PASSWORD`:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=db_api
DB_USER=root
DB_PASSWORD=
```

Pakai `127.0.0.1`, bukan `localhost`. Dengan `localhost`, PDO mencoba unix socket, padahal MySQL di Docker atau di mesin lain biasanya hanya membuka TCP. File `.env` sudah masuk `.gitignore`.

Aplikasi membaca environment variable, bukan file `.env`. Muat isinya ke shell sebelum menjalankan server atau test:

```bash
set -a; source .env; set +a
```

Import schema dan data awal:

```bash
mysql -u "$DB_USER" -p < database/db_api.sql
```

Untuk MySQL di Docker, jalankan client di dalam container (ganti `<nama-container>`):

```bash
docker exec -i -e MYSQL_PWD="$DB_PASSWORD" <nama-container> mysql -u "$DB_USER" < database/db_api.sql
```

File ini membuat database `db_api` yang baru, berisi tabel `products` (14 baris) dan `categories` (4 baris). Isinya hanya perintah pembuatan dan pengisian, jadi akan gagal bila `db_api` sudah ada. Itu disengaja agar data yang sudah ada tidak tertimpa.

## Menjalankan API

Dari folder repository:

```bash
php -S 127.0.0.1:8000 -t .
```

Semua endpoint ada di bawah `http://127.0.0.1:8000/api`. Server bawaan PHP ini hanya untuk development.

## Endpoint

| Method | Path | Fungsi |
|---|---|---|
| GET | `/products.php` | Daftar produk dengan pencarian q, page, dan limit |
| GET | `/products.php?id={id}` | Detail satu produk |
| POST | `/products.php` | Membuat produk |
| PUT | `/products.php?id={id}` | Mengganti seluruh field produk |
| DELETE | `/products.php?id={id}` | Menghapus produk |
| GET | `/categories.php` | Daftar seluruh kategori |
| GET | `/categories.php?id={id}` | Detail satu kategori |
| POST | `/categories.php` | Membuat kategori |
| PUT | `/categories.php?id={id}` | Mengganti seluruh field kategori |
| DELETE | `/categories.php?id={id}` | Menghapus kategori |

Method lain mendapat 405 dengan header `Allow: GET, POST, PUT, DELETE`. Body POST dan PUT harus berupa objek JSON dengan header `Content-Type: application/json`.

## Pencarian dan pagination

Keduanya berlaku untuk daftar produk dan bisa dipakai bersamaan:

```http
GET /api/products.php?q=kopi&page=1&limit=10
```

- `q` mencari sebagian nama produk tanpa membedakan huruf besar dan kecil. Spasi di tepi dibuang, dan karakter `%` serta `_` dicari apa adanya.
- `q` yang ada tetapi kosong menghasilkan 400. Pencarian valid yang tidak menemukan apa pun menghasilkan 200 dengan `data` kosong.
- `page` default 1 dan `limit` default 10, dengan `limit` maksimal 100.
- Nilai yang bukan bilangan bulat positif, atau `limit` di atas 100, menghasilkan 400.
- Halaman yang melewati data terakhir menghasilkan 200 dengan `data` kosong dan `meta` tetap akurat.
- Daftar kategori tidak memakai pagination.

Contoh response daftar produk:

```json
{
  "success": true,
  "data": [
    {
      "id": 3,
      "name": "Kopi Liberika",
      "price": 72000,
      "stock": 15,
      "created_at": "2026-11-14 00:00:00",
      "updated_at": "2026-11-14 00:00:00"
    }
  ],
  "meta": { "q": "kopi", "page": 1, "limit": 1, "total": 3, "total_pages": 3 }
}
```

## Aturan data

Produk:

- `name` wajib, teks, maksimal 120 karakter setelah spasi tepi dibuang.
- `price` wajib berupa angka dari 0 sampai 9999999999.99.
- `stock` wajib berupa bilangan bulat dari 0 sampai 2147483647.

Kategori:

- `name` wajib, maksimal 120 karakter, dan unik tanpa membedakan huruf besar dan kecil. `Minuman` dan `minuman` dianggap nama yang sama.
- `description` opsional. Teks kosong disimpan sebagai `null`.

Kegagalan validasi menghasilkan 422. Body yang bukan JSON, kosong, atau bukan objek menghasilkan 400.

## Format response

Response sukses membawa `"success": true`, dan `data` bila ada resource yang dikembalikan. Operasi tulis juga menyertakan `message`:

```json
{
  "success": true,
  "message": "Kategori dibuat",
  "data": { "id": 5, "name": "Frozen food", "description": "Makanan beku", "created_at": "2026-11-14 00:00:00" }
}
```

Response error selalu berbentuk berikut, tanpa SQL, stack trace, atau detail koneksi:

```json
{ "success": false, "message": "Nama kategori sudah digunakan" }
```

## Status code

| Kode | Nama | Dipakai untuk |
|---|---|---|
| 200 | OK | GET, PUT, dan DELETE yang berhasil, termasuk pencarian tanpa hasil |
| 201 | Created | POST yang berhasil membuat produk atau kategori |
| 400 | Bad Request | ID, q, page, atau limit tidak valid, dan body yang bukan objek JSON |
| 404 | Not Found | Produk atau kategori dengan ID tersebut tidak ada |
| 405 | Method Not Allowed | HTTP method selain GET, POST, PUT, dan DELETE |
| 422 | Unprocessable Content | Isi body gagal validasi, termasuk nama kategori yang sudah dipakai |
| 500 | Internal Server Error | Kesalahan server atau database yang tidak terduga, tanpa detail internal |

Operasi DELETE yang berhasil memakai 200 dengan body JSON, mengikuti kode contoh pada materi, bukan 204.

## Pengujian

Test otomatis ditulis dengan runner PHP sederhana tanpa dependency. Tanpa server, hanya unit test yang jalan:

```bash
set -a; source .env; set +a
php tests/run.php
```

Untuk ikut menjalankan integration test, nyalakan server di satu terminal lalu jalankan test di terminal lain:

```bash
php -S 127.0.0.1:8000 -t .
```

```bash
API_BASE_URL=http://127.0.0.1:8000/api php tests/run.php
```

Integration test menjalankan `tests/fixtures/reset_test_data.sql`, yang mengosongkan lalu mengisi ulang tabel `products` dan `categories` di `db_api`. Test menolak jalan bila `DB_NAME` bukan `db_api`, tetapi data apa pun yang kamu tambahkan sendiri ke dua tabel itu akan hilang.

### Koleksi request

`collection-api/REST-API-PHP.postman_collection.json` adalah Postman Collection v2.1 yang bisa diimpor ke Bruno maupun Postman tanpa menyusun ulang request.

- Bruno: Import Collection, pilih Postman Collection, lalu pilih file JSON tersebut.
- Postman: Import, lalu pilih file JSON yang sama.

Alamat server disimpan di variable `base_url` (default `http://127.0.0.1:8000/api`), dan koleksi tidak menyimpan kredensial. Koleksi punya empat folder: Products, Categories, Success scenarios, dan Failed scenarios. Nama setiap request diakhiri `(expect NNN)` yang menunjukkan status yang diharapkan.

Sebelum menjalankan folder skenario, kembalikan data ke kondisi awal karena S-03, S-04, dan S-05 mengubah data:

```bash
mysql -u "$DB_USER" -p db_api < tests/fixtures/reset_test_data.sql
```

### Skenario

| ID | Skenario | Request | Status |
|---|---|---|---|
| S-01 | Menampilkan daftar kategori | `GET /categories.php` | 200 |
| S-02 | Menampilkan detail kategori dengan ID valid | `GET /categories.php?id=1` | 200 |
| S-03 | Membuat kategori dengan input valid | `POST /categories.php` | 201 |
| S-04 | Memperbarui kategori dengan ID dan input valid | `PUT /categories.php?id=2` | 200 |
| S-05 | Menghapus kategori dengan ID valid | `DELETE /categories.php?id=3` | 200 |
| S-06 | Mencari produk dengan parameter q | `GET /products.php?q=kopi` | 200 |
| S-07 | Menampilkan produk dengan page dan limit valid | `GET /products.php?page=2&limit=5` | 200 |
| F-01 | Detail kategori dengan format ID tidak valid | `GET /categories.php?id=abc` | 400 |
| F-02 | Detail kategori yang tidak ditemukan | `GET /categories.php?id=9999` | 404 |
| F-03 | Membuat kategori dengan name kosong | `POST /categories.php` | 422 |
| F-04 | Membuat kategori dengan nama yang sudah ada | `POST /categories.php` | 422 |
| F-05 | Memperbarui kategori dengan body yang tidak valid | `PUT /categories.php?id=1` | 422 |
| F-06 | Pagination dengan parameter tidak valid | `GET /products.php?page=0&limit=101` | 400 |
| F-07 | HTTP method yang tidak didukung | `PATCH /categories.php?id=1` | 405 |

## Struktur repository

```text
.
├── README.md
├── .env.example
├── .gitignore
├── api/
│   ├── config/database.php
│   ├── lib/http.php
│   ├── lib/validation.php
│   ├── products.php
│   └── categories.php
├── database/db_api.sql
├── collection-api/REST-API-PHP.postman_collection.json
└── tests/
    ├── run.php
    ├── bootstrap.php
    ├── unit/
    ├── integration/
    └── fixtures/reset_test_data.sql
```

## Ruang lingkup

- CRUD produk sebagai baseline dari materi
- CRUD kategori dengan name wajib dan unik, description opsional
- Pencarian produk berdasarkan nama lewat parameter q
- Pagination daftar produk lewat parameter page dan limit
- Response JSON dengan validasi input dan status code yang konsisten

## Di luar ruang lingkup

- Frontend dan framework PHP
- Autentikasi, role, dan otorisasi
- Relasi produk dan kategori
- Pagination kategori dan endpoint PATCH
- Upload gambar, CORS khusus, rate limiting, logging produksi, dan API versioning
- Docker, deployment, dan dokumentasi Swagger atau OpenAPI

Tugas ini juga punya laporan (DOCX dan PDF) dan screenshot dari Bruno. Keduanya disusun terpisah dan tidak termasuk repository ini.
