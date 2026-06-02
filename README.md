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
- Audit log untuk aksi pengurus dan check-in ronda
- Portal warga publik di `/`
- Cek nomor HP publik dengan rate limit dan respons tanpa membocorkan nama warga
- Jadwal ronda publik di `/jadwal-ronda` dalam bentuk table desktop dan card mobile
- Check-in ronda publik di `/checkin-ronda`
- Manajemen jadwal ronda pengurus di `/dashboard/ronda`
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
- `/login` - login pengurus
- `/dashboard` - dashboard pengurus
- `/dashboard/rumah` - manajemen rumah
- `/dashboard/warga` - manajemen warga
- `/dashboard/ronda` - manajemen jadwal ronda

## Catatan Implementasi

- Warga tidak memakai akun atau password. Aksi publik memakai nomor HP aktif yang sudah terdaftar.
- Verifikasi nomor publik hanya menampilkan status terdaftar, bukan nama warga.
- Check-in ronda hanya berhasil untuk warga aktif yang terjadwal pada tanggal tersebut.
- Check-in disimpan secara atomic dengan guard `checked_in_at IS NULL`, sehingga double submit paralel tidak mencatat kehadiran dua kali.
- `checked_in_at` tidak mass assignable dan hanya ditulis lewat flow check-in.
- Semua waktu aplikasi mengikuti `APP_TIMEZONE=Asia/Jakarta`.

## Status

Sprint 1 dan Sprint 2 sudah terimplementasi. Verifikasi terakhir:

```text
ddev exec php artisan test
62 passed, 127 assertions
```

Dokumen sprint ada di `docs/superpowers/sprints/`.
