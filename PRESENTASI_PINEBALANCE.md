# Presentasi PineBalance

## Slide 1 - Judul

**PineBalance**  
**Daily Water Balance Monitoring System**

Subjudul: Dashboard monitoring kondisi air lahan berbasis data harian

**Yang disampaikan:**
- Nama project dan tujuan singkatnya.
- Sistem ini membantu monitoring air pada setiap PG dan lokasi.

---

## Slide 2 - Latar Belakang Masalah

- Data curah hujan, irigasi, luas penyiraman, dan evapotranspirasi berasal dari file Excel.
- Data mentah sulit dibaca jika hanya berupa tabel.
- Tim perlu mengetahui lokasi mana yang aman, mulai kering, atau kritis.
- Pengambilan keputusan penyiraman membutuhkan informasi yang cepat dan terukur.

**Kalimat presentasi:**
> Sebelum ada sistem ini, data monitoring perlu dibaca dan dibandingkan secara manual. PineBalance mengubah data tersebut menjadi informasi visual yang lebih mudah dipahami.

---

## Slide 3 - Tujuan Sistem

- Memusatkan data water balance harian.
- Mengurangi proses perhitungan manual.
- Menampilkan kondisi air berdasarkan PG dan lokasi.
- Membantu menemukan lokasi yang membutuhkan perhatian lebih cepat.
- Menyediakan data yang dapat diimpor dan diekspor.

---

## Slide 4 - Konsep Water Balance

Water balance menunjukkan perubahan ketersediaan air dari hari ke hari.

```text
Water Balance hari ini =
Water Balance kemarin + Curah Hujan + Irigasi Efektif - Evapotranspirasi
```

Keterangan:
- Curah hujan menambah ketersediaan air.
- Irigasi menambah ketersediaan air.
- Irigasi efektif mempertimbangkan luas aktual dibandingkan luas rencana.
- Evapotranspirasi mengurangi ketersediaan air karena kehilangan air dari tanah dan tanaman.

**Yang wajib dijelaskan:**
- Perhitungan berjalan secara berurutan berdasarkan tanggal.
- Nilai dibatasi pada rentang 54 mm sampai 105 mm.

---

## Slide 5 - Klasifikasi Status Air

| Status | Rentang | Makna |
|---|---:|---|
| At FC | 105 mm | Kondisi air penuh |
| FC - MAD 50% | 80 sampai kurang dari 105 mm | Kondisi aman atau optimal |
| MAD 50% - WP | Lebih dari 54 sampai kurang dari 80 mm | Mulai kering dan perlu diwaspadai |
| At WP | 54 mm | Kondisi sangat kering atau titik layu |

**Poin penting:**
- Warna hijau menunjukkan kondisi penuh.
- Warna biru menunjukkan kondisi optimal.
- Warna kuning menunjukkan kondisi waspada.
- Warna merah menunjukkan kondisi kritis.

---

## Slide 6 - Fitur Utama Dashboard

- Filter data berdasarkan PG.
- Filter data berdasarkan lokasi.
- Menampilkan jumlah hari monitoring.
- Grafik tren water balance harian.
- Grafik distribusi status zona.
- Tabel data harian.
- Ringkasan kondisi seluruh lokasi dalam satu PG.
- Rekap jumlah penyiraman per bulan.
- Import data Excel atau CSV.
- Export data ke CSV.

---

## Slide 7 - Alur Penggunaan Sistem

1. User menyiapkan file Excel atau CSV.
2. User meng-upload file melalui dashboard.
3. Sistem membaca dan menormalisasi nama kolom serta tanggal.
4. Sistem menghitung irigasi efektif dan water balance.
5. Sistem menentukan status zona untuk setiap hari.
6. Data disimpan berdasarkan kombinasi PG, lokasi, dan tanggal.
7. User memilih PG dan lokasi untuk melihat hasil monitoring.
8. User dapat memakai grafik, ringkasan, atau export data.

**Demo yang disarankan:**
- Upload file.
- Pilih PG.
- Pilih lokasi.
- Tunjukkan grafik tren.
- Tunjukkan status zona.
- Tunjukkan ringkasan lokasi.
- Tunjukkan tombol export.

---

## Slide 8 - Arsitektur Teknologi

### Backend
- Laravel dan PHP.
- Controller menyediakan endpoint API.
- Model `DailyWaterBalance` merepresentasikan data monitoring.
- Library Laravel Excel menangani import data.

### Database
- Tabel utama: `daily_water_balances`.
- Satu data unik berdasarkan PG, lokasi, dan tanggal.
- Kolom menyimpan curah hujan, irigasi, evapotranspirasi, water balance, dan status zona.

### Frontend
- Laravel Blade sebagai dashboard utama.
- JavaScript mengambil data dari API.
- Chart.js digunakan untuk grafik.
- CSS mengatur tampilan responsif.

**Catatan teknis:**
- Folder `pinebalance-next` berisi alternatif frontend Next.js dengan Supabase dan masih merupakan versi pengembangan. Saat demo utama, gunakan dashboard Laravel yang endpoint-nya sudah sesuai dengan route backend.

---

## Slide 9 - Manfaat Sistem

- Monitoring menjadi lebih cepat.
- Data lebih mudah dibandingkan antar lokasi.
- Area berisiko kekeringan dapat segera terlihat.
- Perhitungan lebih konsisten karena dilakukan oleh sistem.
- Riwayat penyiraman dapat dianalisis per bulan.
- Data dapat digunakan sebagai dasar evaluasi dan keputusan operasional.

**Jangan klaim berlebihan:**
- Sistem memberikan informasi pendukung keputusan.
- Sistem belum otomatis mengendalikan pompa atau irigasi.
- Sistem belum menggantikan validasi lapangan.

---

## Slide 10 - Kesimpulan dan Pengembangan

### Kesimpulan
- PineBalance mengubah data Excel menjadi dashboard monitoring water balance.
- Sistem menampilkan kondisi air harian secara visual dan terstruktur.
- Informasi status zona membantu menentukan lokasi yang perlu diprioritaskan.

### Pengembangan berikutnya
- Menambahkan autentikasi dan hak akses user.
- Menambahkan notifikasi untuk lokasi berstatus At WP.
- Menambahkan filter tanggal.
- Menambahkan export laporan berbentuk PDF.
- Menghubungkan data dengan sensor atau sumber data otomatis.
- Menambahkan rekomendasi jadwal penyiraman.

**Kalimat penutup:**
> PineBalance membantu mengubah data monitoring menjadi informasi yang dapat digunakan untuk mengambil keputusan pengelolaan air secara lebih cepat dan berbasis data.

---

# Pertanyaan Wajib yang Harus Bisa Dijawab

## Tentang masalah

- Masalah apa yang diselesaikan oleh PineBalance?
- Siapa pengguna utama sistem ini?
- Mengapa data Excel perlu diubah menjadi dashboard?

## Tentang perhitungan

- Apa itu water balance?
- Apa saja input perhitungannya?
- Apa perbedaan irigasi biasa dan irigasi efektif?
- Mengapa nilai water balance dibatasi antara 54 mm dan 105 mm?
- Apa arti FC, MAD 50%, dan WP?

## Tentang fitur

- Bagaimana cara user memasukkan data?
- Bagaimana sistem menentukan status zona?
- Apa yang ditampilkan oleh grafik?
- Apa kegunaan ringkasan per lokasi?
- Apakah data dapat diekspor?

## Tentang teknologi

- Mengapa menggunakan Laravel?
- Apa fungsi database?
- Apa fungsi API?
- Bagaimana sistem mencegah data duplikat?
- Apa peran Chart.js?

## Tentang batasan

- Apakah sistem mengambil data sensor secara real-time?
- Apakah sistem otomatis mengatur irigasi?
- Apakah hasil sistem menggantikan pengecekan lapangan?
- Apa yang dilakukan jika file Excel memiliki kolom yang salah?

# Prompt Singkat untuk Membuat Slide dengan AI

```text
Buatkan presentasi profesional berbahasa Indonesia sebanyak 10 slide tentang PineBalance, yaitu Daily Water Balance Monitoring System untuk memantau kondisi air lahan berdasarkan PG, lokasi, dan tanggal. Bahas latar belakang masalah data Excel yang sulit dianalisis, tujuan sistem, konsep water balance, rumus water balance, irigasi efektif, klasifikasi status At FC, FC - MAD 50%, MAD 50% - WP, dan At WP, fitur dashboard, alur import data, grafik tren, ringkasan lokasi, export CSV, arsitektur Laravel dan database, manfaat, keterbatasan, serta pengembangan berikutnya. Gunakan gaya visual profesional bertema pertanian dan data, dengan warna hijau, biru, kuning, dan merah untuk status zona. Setiap slide harus memiliki judul singkat, maksimal 5 poin utama, dan catatan pembicara yang mudah dipresentasikan. Jangan mengklaim bahwa sistem mengontrol irigasi otomatis atau mengambil data sensor real-time.
```
