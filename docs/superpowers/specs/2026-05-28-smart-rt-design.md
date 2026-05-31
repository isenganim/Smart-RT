# Smart RT MVP System Design

## Tujuan

Smart RT adalah PWA web responsive untuk satu RT. Aplikasi ini membantu pengurus menjalankan administrasi warga, kas ronda harian, jadwal ronda, pengumuman, laporan warga, surat pengantar, inventaris, dan voting sederhana.

Target utama adalah warga yang tidak semuanya melek IT. Karena itu, portal warga dibuat tanpa akun dan tanpa password. Warga cukup membuka link dari WhatsApp dan menggunakan nomor HP terdaftar untuk aksi tertentu. Dashboard pengurus tetap wajib login karena mengelola data sensitif.

## Ruang Lingkup MVP

MVP menggunakan pendekatan operasional lengkap tetapi tetap sederhana.

Fitur masuk MVP:

1. Data warga, KK, dan rumah.
2. Dashboard pengurus dengan login.
3. Portal warga tanpa login.
4. Jadwal ronda.
5. Check-in ronda menggunakan nomor HP terdaftar.
6. Kas ronda harian Rp500 per rumah melalui scan QR rumah.
7. Denda tidak ronda Rp5.000.
8. Pengumuman RT.
9. Laporan warga.
10. Surat pengantar sederhana.
11. Inventaris dasar.
12. Voting sederhana.

Di luar MVP:

1. Multi-RT atau multi-tenant.
2. Payment gateway, QRIS, atau pembayaran otomatis.
3. Akun login warga.
4. Notifikasi otomatis kompleks.
5. Verifikasi biometrik, lokasi, atau foto untuk ronda.

## Platform

Aplikasi dibuat sebagai PWA web responsive.

Alasan:

- Warga cukup membuka link dari WhatsApp.
- Tidak perlu instal aplikasi dari Play Store.
- Bisa ditambahkan ke layar utama HP.
- Dashboard pengurus tetap nyaman digunakan dari laptop.
- Biaya rilis dan perawatan lebih ringan daripada mobile app native.

## Peran Pengguna

### Warga

Warga tidak memiliki akun login. Untuk melihat informasi umum, warga cukup membuka portal warga. Untuk aksi resmi, warga memasukkan nomor HP yang sudah terdaftar di data warga aktif.

Aksi warga:

- Melihat pengumuman.
- Melihat jadwal ronda.
- Melihat ringkasan kas.
- Mengirim laporan warga.
- Mengajukan surat pengantar.
- Mengikuti voting sederhana.
- Check-in ronda jika masuk jadwal hari itu.

Nomor HP menjadi identitas ringan. Nomor HP hanya bisa dibuat atau diubah oleh Admin/Bendahara dari dashboard. Jika warga mengganti nomor HP, warga harus meminta pengurus memperbarui data.

### Admin RT

Admin RT mengelola seluruh data dan konfigurasi operasional RT.

Akses Admin RT:

- Data warga, KK, dan rumah.
- QR rumah.
- Jadwal ronda.
- Sesi PIN harian ronda.
- Pengumuman.
- Laporan warga.
- Surat pengantar.
- Inventaris.
- Voting.
- Rekap kas.
- Koreksi transaksi.

### Bendahara

Bendahara fokus pada kas dan operasional pembayaran.

Akses Bendahara:

- Rekap kas ronda.
- Transaksi iuran harian.
- Denda ronda.
- Sesi PIN harian ronda.
- Koreksi atau pembatalan transaksi.
- Data warga/rumah yang diperlukan untuk kas.

## Arsitektur Produk

Aplikasi dibagi menjadi dua area utama.

### Portal Warga

Portal warga dapat diakses tanpa login. Fitur publik seperti pengumuman dan jadwal ronda dapat dibaca langsung. Fitur yang membuat data baru atau melakukan aksi resmi meminta nomor HP terdaftar.

Jika nomor HP tidak terdaftar atau warga tidak aktif, sistem menolak aksi tersebut.

### Dashboard Pengurus

Dashboard pengurus menggunakan login. Setiap aksi penting dicatat dengan audit log agar bisa ditelusuri.

Dashboard berisi modul:

- Manajemen warga/KK/rumah.
- Jadwal ronda.
- Scan dan sesi ronda.
- Keuangan kas.
- Pengumuman.
- Laporan warga.
- Surat.
- Inventaris.
- Voting.

## Alur Kas Ronda Harian

Kas ronda harian bernilai Rp500 per rumah per hari.

Setiap rumah/KK memiliki QR unik. QR hanya berisi kode rumah atau token unik, bukan data pribadi lengkap.

Alur pembayaran:

1. Admin/Bendahara membuat sesi ronda untuk tanggal tertentu.
2. Sistem menghasilkan PIN harian 4-6 digit.
3. PIN aktif dalam rentang waktu tertentu, misalnya 18:00 sampai 06:00.
4. PIN dibagikan ke regu ronda melalui WhatsApp.
5. Petugas ronda membuka halaman scan iuran.
6. Petugas memasukkan PIN harian.
7. Petugas scan QR rumah.
8. Sistem menampilkan alamat, nama kepala keluarga, dan status iuran tanggal itu.
9. Petugas menekan tombol Terima Cash.
10. Sistem mencatat transaksi iuran harian Rp500 sebagai lunas.

Aturan:

- Satu rumah hanya bisa lunas satu kali untuk tanggal yang sama.
- Scan hanya bisa dilakukan dengan PIN harian aktif.
- Transaksi menyimpan tanggal, jam, rumah, nominal, sesi PIN, dan pelaku/sumber aksi.
- Jika terjadi salah input, hanya Admin/Bendahara yang bisa membatalkan atau membuat koreksi dengan alasan.

## Alur Check-in Ronda dan Denda

Denda tidak ronda bernilai Rp5.000.

Sistem memiliki jadwal ronda per tanggal yang berisi warga yang bertugas. Check-in hanya dibuka untuk warga yang terjadwal pada tanggal tersebut.

Alur check-in:

1. Warga membuka halaman check-in ronda.
2. Warga memasukkan nomor HP.
3. Sistem memeriksa apakah nomor HP terdaftar, aktif, dan masuk jadwal ronda hari itu.
4. Jika sesuai, warga dapat menekan Check-in Hadir.
5. Sistem mencatat kehadiran untuk tanggal tersebut.
6. Warga tidak bisa check-in dua kali pada tanggal yang sama.
7. Jika nomor HP tidak masuk jadwal, check-in ditolak.

Setelah batas waktu ronda lewat, warga terjadwal yang belum check-in masuk daftar calon denda. Admin/Bendahara meninjau daftar tersebut sebelum denda Rp5.000 ditetapkan. Ini mengurangi risiko salah paham jika ada tukar jadwal atau alasan khusus.

## Modul Data Utama

### Rumah/KK

Menyimpan data rumah atau kepala keluarga.

Field utama:

- ID rumah/KK.
- Nomor rumah atau alamat.
- Nama kepala keluarga.
- QR token unik.
- Status aktif.

### Warga

Menyimpan anggota warga.

Field utama:

- ID warga.
- Nama.
- Nomor HP.
- Relasi ke rumah/KK.
- Status aktif.
- Catatan jadwal ronda.

Nomor HP harus unik untuk warga aktif.

### Jadwal Ronda

Menyimpan jadwal ronda per tanggal.

Field utama:

- Tanggal ronda.
- Daftar warga bertugas.
- Status hadir/tidak hadir.
- Catatan pengurus.

### Sesi Scan Ronda

Menyimpan PIN harian untuk akses scan iuran.

Field utama:

- Tanggal.
- PIN.
- Jam mulai.
- Jam selesai.
- Status aktif/kedaluwarsa.
- Dibuat oleh.

### Transaksi Kas

Menyimpan semua transaksi kas.

Jenis transaksi:

- Iuran harian.
- Denda ronda.
- Koreksi.

Field utama:

- Tanggal transaksi.
- Rumah/KK terkait.
- Warga terkait jika ada.
- Jenis transaksi.
- Nominal.
- Status.
- Sumber transaksi.
- Dicatat oleh.
- Alasan koreksi jika ada.

### Pengumuman

Menyimpan informasi RT untuk warga.

Field utama:

- Judul.
- Isi.
- Tanggal publikasi.
- Status tampil.

### Laporan Warga

Menyimpan laporan dari warga.

Field utama:

- Nomor HP pelapor.
- Kategori.
- Deskripsi.
- Status tindak lanjut.
- Catatan pengurus.

### Surat Pengantar

Menyimpan pengajuan surat sederhana.

Field utama:

- Nomor HP pemohon.
- Jenis surat.
- Keperluan.
- Status pengajuan.
- Catatan pengurus.

### Inventaris

Menyimpan barang milik RT.

Field utama:

- Nama barang.
- Kondisi.
- Lokasi atau peminjam.
- Status.

### Voting

Menyimpan voting sederhana.

Field utama:

- Pertanyaan.
- Pilihan jawaban.
- Periode aktif.
- Status.
- Hasil.

Untuk MVP, voting menggunakan nomor HP terdaftar sebagai identitas. Satu nomor HP hanya bisa memilih satu kali per voting.

## Data Flow Utama

### Aksi Warga dengan Nomor HP

1. Warga membuka portal.
2. Warga memilih aksi.
3. Sistem meminta nomor HP.
4. Sistem memvalidasi nomor HP terhadap data warga aktif.
5. Jika valid, sistem memproses aksi.
6. Jika tidak valid, sistem menampilkan pesan bahwa nomor belum terdaftar dan warga perlu menghubungi pengurus.

### Scan Iuran Ronda

1. Petugas memasukkan PIN harian.
2. Sistem memvalidasi PIN dan waktu aktif.
3. Petugas scan QR rumah.
4. Sistem mengambil data rumah dari QR token.
5. Sistem mengecek transaksi iuran harian untuk tanggal itu.
6. Jika belum lunas, sistem mencatat pembayaran Rp500.
7. Jika sudah lunas, sistem menampilkan status sudah dibayar.

### Check-in Ronda

1. Warga memasukkan nomor HP.
2. Sistem mencari warga aktif.
3. Sistem mengecek jadwal ronda tanggal itu.
4. Jika terjadwal dan belum check-in, sistem mencatat hadir.
5. Jika tidak terjadwal atau sudah check-in, sistem menolak dengan alasan jelas.

## Error Handling dan Keamanan

Prinsip keamanan MVP:

- Dashboard pengurus wajib login.
- Portal warga tidak menyimpan data sensitif secara terbuka.
- Nomor HP hanya dapat diubah oleh Admin/Bendahara.
- QR rumah tidak memuat data pribadi lengkap.
- PIN scan ronda memiliki masa aktif.
- Transaksi kas tidak dihapus langsung; kesalahan dicatat sebagai pembatalan atau koreksi.
- Aksi penting memiliki audit trail.
- Input dari portal warga divalidasi di server.

Pesan error dibuat sederhana:

- Nomor HP belum terdaftar.
- Nomor HP tidak terjadwal ronda hari ini.
- Iuran rumah ini sudah tercatat hari ini.
- PIN sudah kedaluwarsa.
- Akses hanya untuk pengurus.

## Rekap dan Laporan

Dashboard Bendahara menyediakan rekap:

- Pemasukan iuran harian Rp500.
- Pemasukan denda ronda Rp5.000.
- Koreksi transaksi.
- Total kas harian.
- Total kas mingguan.
- Total kas bulanan.
- Rumah yang belum bayar pada tanggal tertentu.
- Warga terjadwal ronda yang belum check-in.

## Testing MVP

Pengujian utama:

1. Admin dapat login dan mengelola data warga/rumah.
2. Warga tanpa login dapat melihat pengumuman dan jadwal ronda.
3. Nomor HP tidak terdaftar ditolak untuk aksi resmi.
4. Nomor HP terdaftar dapat membuat laporan dan pengajuan surat.
5. PIN harian valid dapat membuka mode scan iuran.
6. PIN kedaluwarsa ditolak.
7. QR rumah valid mencatat iuran Rp500.
8. Rumah yang sama tidak bisa dibayar dua kali pada tanggal yang sama.
9. Warga terjadwal dapat check-in ronda dengan nomor HP.
10. Warga tidak terjadwal tidak bisa check-in.
11. Warga belum check-in setelah batas waktu muncul sebagai calon denda.
12. Admin/Bendahara dapat menetapkan denda Rp5.000.
13. Bendahara dapat melihat rekap kas harian, mingguan, dan bulanan.
14. Voting hanya menerima satu suara per nomor HP terdaftar.

## Prioritas Implementasi

Urutan implementasi yang disarankan:

1. Fondasi aplikasi PWA dan autentikasi dashboard.
2. Data rumah/KK/warga.
3. Portal warga dasar.
4. Jadwal ronda dan check-in nomor HP.
5. QR rumah, sesi PIN harian, dan scan iuran Rp500.
6. Denda ronda Rp5.000 dan rekap kas.
7. Pengumuman dan laporan warga.
8. Surat pengantar sederhana.
9. Inventaris dasar.
10. Voting sederhana.

Prioritas tertinggi adalah modul ronda dan kas karena itu kebutuhan operasional harian paling jelas.
