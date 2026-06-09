# Smart RT

Smart RT adalah aplikasi PWA responsive untuk operasional satu RT. Fokus saat ini adalah dashboard pengurus, data rumah dan warga, portal warga tanpa akun, serta jadwal ronda dengan check-in berbasis nomor HP terdaftar.

## Stack

- Laravel 12
- Livewire 4 + Volt
- Tailwind CSS 4 + Vite
- Pest PHP
- DDEV + MariaDB untuk local development

## Fitur Saat Ini

- Login pengurus dengan role `admin_rt` dan `bendahara`
- Dashboard pengurus yang terlindungi login
- Manajemen rumah, warga, status aktif, dan QR rumah
- Audit log untuk aksi pengurus, check-in ronda, dan transaksi kas
- Portal warga publik di `/`
- Cek nomor HP publik dengan rate limit dan respons tanpa membocorkan nama warga
- Jadwal ronda publik di `/jadwal-ronda` dalam bentuk table desktop dan card mobile
- Check-in ronda publik di `/checkin-ronda`
- Manajemen jadwal ronda pengurus di `/dashboard/ronda`
- Sesi scan iuran harian (PIN harian berdurasi waktu)
- Scan iuran harian Rp500 per rumah via QR dengan perlindungan scan ganda
- Review denda warga tidak check-in ronda (denda Rp5.000)
- Rekap kas harian, mingguan, bulanan, daftar rumah belum bayar, dan ronda absen
- Koreksi & pembatalan transaksi kas secara non-destructive
- Timezone aplikasi `Asia/Jakarta` / WIB

## Setup Lokal

Project ini disiapkan untuk DDEV.

```bash
ddev start
ddev exec composer install
ddev exec npm install
ddev exec cp .env.example .env
ddev exec php artisan key:generate
ddev exec php artisan migrate --seed
ddev exec npm run build
```

URL lokal default:

```text
https://smart-rt.ddev.site
```

Demo login hasil seeder:

```text
admin@smartrt.test / password
bendahara@smartrt.test / password
```

## Development

Jalankan Vite:

```bash
ddev exec npm run dev
```

Jalankan test:

```bash
ddev exec php artisan test
```

Format kode PHP:

```bash
ddev exec ./vendor/bin/pint --dirty
```

## Route Penting

- `/` - portal warga
- `/cek-nomor` - cek status nomor HP terdaftar
- `/jadwal-ronda` - jadwal ronda publik
- `/checkin-ronda` - check-in ronda publik
- `/scan-iuran` - scan iuran warga via QR code
- `/login` - login pengurus
- `/dashboard` - dashboard pengurus
- `/dashboard/rumah` - manajemen rumah
- `/dashboard/warga` - manajemen warga
- `/dashboard/ronda` - manajemen jadwal ronda
- `/dashboard/sesi-scan` - manajemen sesi scan iuran harian
- `/dashboard/denda` - review dan penetapan denda ronda
- `/dashboard/kas` - ringkasan & rekap kas RT
- `/dashboard/kas/transaksi` - daftar & koreksi transaksi kas

## Catatan Implementasi

- Warga tidak memakai akun atau password. Aksi publik memakai nomor HP aktif yang sudah terdaftar.
- Verifikasi nomor publik hanya menampilkan status terdaftar, bukan nama warga.
- Check-in ronda hanya berhasil untuk warga aktif yang terjadwal pada tanggal tersebut.
- Check-in disimpan secara atomic dengan guard `checked_in_at IS NULL`, sehingga double submit paralel tidak mencatat kehadiran dua kali.
- `checked_in_at` tidak mass assignable dan hanya ditulis lewat flow check-in.
- Transaksi kas tidak boleh di-hard-delete; kesalahan dicatat sebagai pembatalan/koreksi yang menuliskan baris koreksi penyeimbang dengan menyertakan alasan.
- Scan iuran harian dilindungi oleh sesi PIN harian yang aktif dalam jendela waktu tertentu.
- Semua waktu aplikasi mengikuti `APP_TIMEZONE=Asia/Jakarta`.

## Status

Sprint 1, Sprint 2, dan Sprint 3 sudah terimplementasi. Verifikasi terakhir:

```text
ddev exec php artisan test
all tests pass
```

Dokumen sprint ada di `docs/superpowers/sprints/`.
