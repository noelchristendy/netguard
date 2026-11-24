# NetGuard – Sistem Network Filtering Berbasis Web

NetGuard merupakan aplikasi web untuk melakukan penyaringan lalu lintas jaringan (network traffic filtering) berdasarkan aturan atau rule yang telah ditentukan. Sistem ini menerapkan autentikasi dan Role-Based Access Control (RBAC) untuk membatasi hak akses pengguna serta menyediakan pencatatan log keputusan dan audit agar aktivitas sistem dapat dipantau dan ditelusuri.

Proyek ini dikembangkan untuk memenuhi tugas Keamanan Data dan Privasi.

## 1. Teknologi yang Digunakan

| Komponen | Teknologi |
|---|---|
| Backend | Laravel 12 |
| Bahasa Backend | PHP 8.2 |
| Frontend | Blade, Tailwind CSS, Alpine.js |
| Grafik | Chart.js |
| Database | MySQL (InnoDB) |
| ORM | Laravel Eloquent |
| Ekspor PDF | Dompdf |
| Build Frontend | Vite |
| Pengujian | PHPUnit |

## 2. Arsitektur Sistem

NetGuard menggunakan arsitektur tiga lapis:

### 2.1 Presentation Layer
Lapisan tampilan yang menggunakan Blade, Tailwind CSS, dan Alpine.js. Lapisan ini menampilkan halaman login, dashboard, manajemen rule, penyaringan, log, audit, dan manajemen pengguna.

### 2.2 Application Layer
Lapisan yang menjalankan logika aplikasi, meliputi:
- Authentication: memverifikasi username dan kata sandi.
- RBAC: memeriksa hak akses berdasarkan role.
- Rule Manager: mengelola aturan filtering.
- Filter Service: mengevaluasi trafik berdasarkan aturan.
- Log dan Audit Service: mencatat serta mengambil riwayat aktivitas.
- Export Service: menghasilkan laporan CSV dan PDF.

### 2.3 Data Layer
Lapisan penyimpanan menggunakan MySQL dengan lima tabel utama:
- `roles`: menyimpan data peran pengguna.
- `users`: menyimpan akun pengguna.
- `rules`: menyimpan aturan filtering.
- `traffic_logs`: menyimpan trafik dan keputusan filtering.
- `audit_logs`: menyimpan riwayat aktivitas pengguna.

## 3. Fitur Aplikasi

### 3.1 Autentikasi dan RBAC
- Login menggunakan username dan kata sandi.
- Kata sandi disimpan dalam bentuk hash.
- Penguncian akun setelah lima kali percobaan login gagal.
- Sesi berakhir setelah 30 menit tidak aktif.
- Pembatasan akses diperiksa di sisi server.
- Penolakan akses dicatat dalam log audit.

### 3.2 Dashboard
- Statistik trafik Allow dan Deny.
- Statistik trafik yang melewati rule dengan aksi Log.
- Grafik jumlah trafik menggunakan Chart.js.
- Lima rule yang paling sering menentukan keputusan trafik.

### 3.3 Manajemen Rule
Pengelolaan aturan filtering meliputi pembuatan, perubahan, penghapusan, pengaturan prioritas, dan aktivasi atau deaktivasi rule.

### 3.4 Penyaringan Trafik
Sistem dirancang untuk menerima data trafik melalui CSV atau input satu trafik. Trafik dievaluasi berdasarkan IP sumber, IP tujuan, protokol, port, dan waktu akses.

### 3.5 Log dan Audit
- Log keputusan menampilkan hasil Allow dan Deny.
- Log audit mencatat login dan perubahan aktivitas pengguna.
- Filter berdasarkan tanggal, IP, jenis aktivitas, dan pelaku.
- Pagination untuk menampilkan data secara bertahap.

### 3.6 Ekspor Laporan
- Ekspor log keputusan ke CSV dan PDF.
- Ekspor log audit ke CSV dan PDF.
- Mengikuti filter yang sedang aktif.
- Perlindungan terhadap CSV Injection.
- Setiap ekspor dicatat ke log audit.

## 4. Persyaratan Sistem

Sebelum menjalankan aplikasi, pastikan perangkat sudah memiliki:
- PHP 8.2 atau lebih baru.
- Composer.
- Node.js dan npm.
- MySQL.
- Git.

Ekstensi PHP yang dibutuhkan Laravel dan dependensinya juga harus aktif.

## 5. Instalasi dan Konfigurasi

### 5.1 Clone Repository

Clone repository proyek:

```bash
git clone <URL_REPOSITORY> netguard
cd netguard
```

### 5.2 Instalasi Dependensi

Instal dependensi PHP:

```bash
composer install
```

Instal dependensi frontend:

```bash
npm install
```

### 5.3 Membuat File Environment

Pada Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

Buat application key:

```bash
php artisan key:generate
```

### 5.4 Konfigurasi Database

Buat database MySQL dengan nama:

```sql
CREATE DATABASE netguard
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
```

Kemudian sesuaikan konfigurasi `.env`:

```env
APP_NAME=NetGuard
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
APP_TIMEZONE=Asia/Jakarta

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=netguard
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=database
SESSION_LIFETIME=30
```

Sesuaikan username dan password database dengan konfigurasi MySQL pada komputer masing-masing.

### 5.5 Migration dan Seeding

Jalankan migration dan seeding:

```bash
php artisan migrate:fresh --seed
```

Perintah tersebut membuat ulang tabel database dan mengisi data awal seperti role, akun contoh, serta rule dasar.

Pastikan `DatabaseSeeder.php` memanggil:
- `RoleSeeder`
- `UserSeeder`
- `RuleSeeder`

**Peringatan:** `migrate:fresh` menghapus seluruh tabel dan data yang sudah ada. Gunakan hanya pada database pengembangan yang boleh direset.

Jika database sudah berisi data dan hanya ingin menjalankan seeder tertentu:

```bash
php artisan db:seed --class=RuleSeeder
```

## 6. Akun Pengembangan

Berikut akun contoh untuk pengujian:

| Username | Password | Role |
|---|---|---|
| admin | Admin#2026 | Administrator |
| operator | Operator#2026 | Operator |
| auditor | Auditor#2026 | Auditor |

Akun tersebut hanya digunakan untuk pengembangan dan demonstrasi.

## 7. Menjalankan Aplikasi

Jalankan Laravel:

```bash
php artisan serve
```

Pada terminal kedua jalankan Vite:

```bash
npm run dev
```

Buka aplikasi melalui browser:

```text
http://127.0.0.1:8000
```

Untuk membuat aset frontend produksi:

```bash
npm run build
```

## 8. Data Uji

Generator data trafik berada di:

```text
tests/fixtures/generate_traffic.php
```

Jalankan generator:

```bash
php tests/fixtures/generate_traffic.php
```

Generator menghasilkan:

```text
tests/fixtures/traffic_sample.csv
```

Data uji mencakup:
- Trafik HTTPS dari jaringan LAN.
- Trafik HTTPS dari luar jaringan LAN.
- Trafik HTTP port 80.
- Trafik DNS.
- Percobaan SSH pada jam kerja.
- Percobaan SSH di luar jam kerja.
- Simulasi port scanning.
- Trafik ICMP.
- Pengujian batas waktu rule.
- Lima baris data rusak untuk menguji validasi parser.

Berkas CSV tersebut digunakan pada halaman Penyaringan setelah fitur impor trafik tersedia.

## 9. Menjalankan Pengujian

Jalankan seluruh pengujian otomatis menggunakan:

```bash
php artisan test
```

Pengujian manual juga dilakukan menggunakan tiga akun untuk memastikan:
- Login berjalan dengan benar.
- Menu mengikuti hak akses setiap role.
- Request tanpa hak ditolak dengan HTTP 403.
- Keputusan filtering sesuai rule.
- Aktivitas penting tercatat dalam log audit.
- Ekspor laporan menghasilkan file yang sesuai.

### 9.1 Skenario Pengujian

| Kode | Skenario | Hasil yang Diharapkan |
|---|---|---|
| U1 | TCP port 443 dari jaringan LAN | Allow oleh rule 10 |
| U2 | TCP port 22 di luar jam kerja | Deny oleh rule 20 |
| U3 | Trafik tanpa rule yang cocok | Default deny |
| U4 | Dua rule cocok dengan prioritas berbeda | Rule prioritas lebih kecil digunakan |
| U5 | Auditor membuka Manajemen Rule | HTTP 403 |
| U6 | Operator membuka Manajemen Pengguna | HTTP 403 |
| U7 | Login gagal lima kali berturut-turut | Akun terkunci dan tercatat di audit |
| U8 | Rule diubah atau dihapus | Perubahan tercatat di audit |

## 10. Matriks Hak Akses

NetGuard menggunakan tiga peran pengguna berdasarkan prinsip Least Privilege.

| Fitur | Administrator | Operator | Auditor |
|---|:---:|:---:|:---:|
| Login | Ya | Ya | Ya |
| Dashboard | Ya | Ya | Ya |
| Manajemen Rule | Ya | Ya | Tidak |
| Penyaringan | Ya | Ya | Tidak |
| Log Keputusan | Ya | Ya | Ya |
| Log Audit | Ya | Tidak | Ya |
| Manajemen Pengguna | Ya | Tidak | Tidak |
| Ekspor Log Keputusan | Ya | Ya | Ya |
| Ekspor Log Audit | Ya | Tidak | Ya |

Hak akses diterapkan melalui middleware pada sisi server. Penyembunyian menu pada antarmuka bukan satu-satunya mekanisme kontrol akses.

## 11. Keterbatasan Sistem

Beberapa keterbatasan NetGuard yang perlu diperhatikan:

### 11.1 Risiko Penguncian Akun
Penguncian akun setelah lima kali login gagal dapat dimanfaatkan untuk menyebabkan penolakan layanan (Denial of Service). Seseorang yang mengetahui username dapat sengaja membuat akun tersebut terkunci.

Mitigasi yang disarankan:
- Menyediakan minimal dua akun Administrator.
- Menyediakan prosedur pemulihan melalui server.
- Pengembangan lanjutan berupa penguncian berdasarkan IP atau CAPTCHA.

### 11.2 Perlindungan Audit Append-Only
Log audit dirancang agar tidak dapat diubah atau dihapus melalui aplikasi. Namun, perlindungan ini belum sepenuhnya mencegah perubahan melalui akses SQL langsung atau akun database yang memiliki hak berlebihan.

Pengamanan lebih lanjut membutuhkan pembatasan hak akses akun database.

### 11.3 Fitur Perubahan Kata Sandi
Belum tersedia fitur bagi pengguna untuk mengganti kata sandi sendiri.

### 11.4 Dukungan IP
Sistem saat ini hanya mendukung IPv4 dan belum mencakup IPv6.

## 12. Catatan Pengembangan pada Windows

Apabila folder proyek berada pada jalur yang mengandung karakter `&`, proses `npm run dev` dapat mengalami masalah ketika menjalankan script.

Solusi yang disarankan adalah memindahkan proyek ke folder yang tidak mengandung karakter `&`.

Jika tetap menggunakan lokasi tersebut, PowerShell dapat ditetapkan sebagai script shell npm:

```powershell
npm config set script-shell "$env:SystemRoot\System32\WindowsPowerShell\v1.0\powershell.exe"
```

Setelah mengubah route, tampilan, atau konfigurasi, bersihkan cache yang terkait:

```bash
php artisan route:clear
php artisan view:clear
php artisan config:clear
```

Jika kode yang diperbarui belum terbaca oleh Tinker, tutup dan buka kembali Tinker.

## 13. Lisensi

Proyek ini dikembangkan untuk proyek akhir pada mata kuliah Keamanan Data dan Privasi.
