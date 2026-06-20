# Feature Flag System Design

## Tujuan

Admin RT belum bisa menonaktifkan modul yang tidak dipakai. Setiap instalasi menampilkan seluruh 15 item menu dashboard dan 8 kartu layanan portal, terlepas dari apakah RT tersebut benar-benar memakainya. Misalnya RT kecil tanpa inventaris, atau RT yang tidak menjalankan voting.

Desain ini menambahkan saklar aktif/nonaktif per modul yang dikelola khusus oleh `admin_rt`. Modul yang dinonaktifkan disembunyikan dari menu (dashboard dan portal) dan mengembalikan 403 jika URL-nya diakses langsung.

## Ruang Lingkup

Masuk lingkup:

1. Tabel `feature_settings` untuk menyimpan status tiap modul opsional.
2. Helper `App\Support\Feature` dengan cache untuk membaca status.
3. Middleware `EnsureFeatureEnabled` yang memblokir rute modul nonaktif (403).
4. Penyembunyian item menu di sidebar dashboard dan kartu layanan portal.
5. Halaman pengaturan `/dashboard/pengaturan` khusus `admin_rt`.
6. Audit log saat status modul diubah.

Di luar lingkup:

1. Toggle per peran (semua perubahan berlaku global untuk RT).
2. Toggle untuk modul inti (Dashboard, Rumah/KK, Warga selalu aktif).
3. Penjadwalan aktif/nonaktif otomatis.

## Modul Opsional

Tujuh modul dapat dinonaktifkan. Setiap modul memetakan ke item dashboard dan kartu portal terkait.

| Key | Item Dashboard | Kartu Portal |
|---|---|---|
| `ronda` | Ronda, Sesi Scan, Denda | Jadwal Ronda, Absen Ronda |
| `kas` | Kas | Pindai Iuran |
| `announcements` | Pengumuman | Pengumuman |
| `reports` | Laporan | Laporan Warga |
| `letters` | Surat | Surat Pengantar |
| `voting` | Voting | Pemungutan Suara |
| `inventory` | Inventaris | — |

Inti (selalu aktif): Dashboard, Rumah/KK, Warga, Cek Nomor HP (portal).

## Arsitektur

### Penyimpanan

Tabel `feature_settings`:

| kolom | tipe | catatan |
|---|---|---|
| `id` | bigint, PK | |
| `key` | string, unique | mis. `ronda`, `kas` |
| `is_enabled` | boolean | default `true` |
| `updated_by` | FK → users, nullable | siapa yang terakhir mengubah |
| timestamps | | |

Seeder `FeatureSettingSeeder` mengisi ketujuh key sebagai aktif menggunakan `firstOrCreate`, dipanggil dari `DatabaseSeeder`. Instalasi baru otomatis punya semua modul aktif.

### Helper Feature

`App\Support\Feature` adalah helper statis mengikuti pola `App\Support\Audit` dan `App\Support\PhoneNumber`.

- `enabled(string $key): bool` — `Cache::remember("feature.{$key}", 3600, ...)`, default `true` bila baris belum ada.
- `all(): Collection` — koleksi tercache di-key oleh `key`.
- `flush(): void` — hapus semua cache key feature.
- `const OPTIONAL_FEATURES` — daftar ketujuh key.

Cache TTL 1 jam; di-flush setiap toggle agar perubahan langsung berlaku.

### Penegakan Rute

Middleware `EnsureFeatureEnabled` (alias `feature`) menerima parameter nama modul dan `abort(403)` bila `Feature::enabled($feature)` bernilai `false`.

Rute modul opsional dibungkus grup middleware di `routes/web.php`:

```php
Route::middleware(['auth', 'pengurus', 'feature:ronda'])->group(function () {
    Volt::route('/dashboard/ronda', 'dashboard.ronda.index')->name('ronda.index');
    Volt::route('/dashboard/ronda/{schedule}', 'dashboard.ronda.show')->name('ronda.show');
    Volt::route('/dashboard/sesi-scan', 'dashboard.scan.index')->name('scan-sessions.index');
    Volt::route('/dashboard/denda', 'dashboard.denda.index')->name('denda.index');
});
```

Rute portal terkait memakai pola sama, mis. `feature:voting` pada `/voting` dan `/voting/{vote}`.

### Penyembunyian UI

- Sidebar dashboard (`resources/views/components/layouts/app.blade.php`): tiap item opsional dibungkus pemeriksaan `Feature::enabled('key')`. Item inti tanpa syarat. `$mobileRoutes` juga menyaring `kas.index` saat `kas` nonaktif.
- Portal home (`resources/views/livewire/portal/home.blade.php`): array `services` disaring `Feature::enabled()` sebelum dirender.

### Halaman Pengaturan

Rute baru `GET /dashboard/pengaturan` (nama `settings.index`) di grup `['auth', 'pengurus']`.

Komponen Volt `resources/views/livewire/dashboard/settings/index.blade.php`:

- `mount()` memanggil `abort_unless(auth()->user()->role === UserRole::ADMIN_RT, 403)` — Bendahara tidak boleh akses.
- Menampilkan baris saklar per modul opsional dengan label dan deskripsi.
- Saat simpan: perbarui `feature_settings`, panggil `Feature::flush()`, dan tulis `Audit::record()` dengan nilai lama/baru tiap perubahan.

Tautan "Pengaturan" ditambahkan ke sidebar, hanya tampil bila peran `admin_rt`.

## Pengujian

`tests/Feature/FeatureFlag/FeatureFlagTest.php`:

- Admin dapat menonaktifkan lalu mengaktifkan kembali sebuah modul.
- Modul nonaktif mengembalikan 403 pada rute dashboard.
- Modul nonaktif mengembalikan 403 pada rute portal.
- `Feature::enabled()` bernilai `false` untuk modul nonaktif (dipakai untuk asersi penyembunyian menu).
- Bendahara mendapat 403 saat mengakses halaman pengaturan.

## File

Dibuat:

- `database/migrations/YYYY_MM_DD_000001_create_feature_settings_table.php`
- `database/seeders/FeatureSettingSeeder.php`
- `app/Models/FeatureSetting.php`
- `app/Support/Feature.php`
- `app/Http/Middleware/EnsureFeatureEnabled.php`
- `resources/views/livewire/dashboard/settings/index.blade.php`
- `tests/Feature/FeatureFlag/FeatureFlagTest.php`

Diubah:

- `database/seeders/DatabaseSeeder.php`
- `bootstrap/app.php`
- `routes/web.php`
- `resources/views/components/layouts/app.blade.php`
- `resources/views/livewire/portal/home.blade.php`

## Utilitas yang Dipakai Ulang

- `App\Support\Audit::record()` — audit log saat toggle.
- `App\Enums\UserRole::ADMIN_RT` — pemeriksaan khusus admin di `mount()` halaman pengaturan.
