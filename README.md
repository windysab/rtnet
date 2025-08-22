# RT/RW Net Management System

Sistem manajemen RT/RW Net (ISP rumahan) berbasis MikroTik dan PHP native untuk mengelola pelanggan, billing, monitoring, dan provisioning otomatis.

## 🚀 Fitur Utama

### 📊 Dashboard Admin
- Overview statistik pelanggan, pendapatan, dan status sistem
- Grafik pendapatan bulanan dan tahunan
- Monitoring real-time pelanggan online
- Quick actions untuk operasi cepat

### 👥 Manajemen Pelanggan
- CRUD data pelanggan lengkap (nama, alamat, kontak, koordinat)
- Upload foto KTP dan foto rumah
- Status aktif/non-aktif pelanggan
- Riwayat aktivitas pelanggan

### 📦 Manajemen Paket Layanan
- Konfigurasi paket internet (bandwidth up/down, burst, limit-at)
- Pengaturan masa aktif dan harga paket
- Profil MikroTik terintegrasi

### 🔄 Provisioning Otomatis MikroTik
- Auto create/update/delete user Hotspot
- Manajemen PPPoE accounts
- MAC address binding
- Simple Queue profiles
- Sinkronisasi real-time dengan MikroTik

### 💰 Sistem Billing & Pembayaran
- Generate invoice otomatis bulanan
- Tracking status pembayaran
- Upload bukti pembayaran
- Reminder jatuh tempo otomatis
- Laporan keuangan lengkap

### 📡 Monitoring & Notifikasi
- Status online/offline pelanggan real-time
- Monitoring penggunaan bandwidth
- Notifikasi via WhatsApp, Email, dan Telegram
- Alert sistem dan reminder pembayaran

### 📈 Laporan & Analytics
- Laporan pelanggan aktif/non-aktif
- Analisis pendapatan dan profit
- Top users berdasarkan penggunaan bandwidth
- Export data ke CSV/Excel

## 🛠️ Teknologi yang Digunakan

- **Backend**: PHP 7.4+ (Native)
- **Database**: MySQL 5.7+
- **Frontend**: Bootstrap 5, jQuery, Chart.js
- **MikroTik**: RouterOS API
- **Notification**: WhatsApp API, Email SMTP, Telegram Bot

## 📋 Persyaratan Sistem

### Server Requirements
- PHP 7.4 atau lebih tinggi
- MySQL 5.7 atau MariaDB 10.2+
- Apache/Nginx web server
- PHP Extensions:
  - PDO MySQL
  - cURL
  - GD (untuk upload gambar)
  - OpenSSL
  - JSON

### MikroTik Requirements
- RouterOS 6.40+
- API service enabled
- User dengan privilege yang sesuai

## 🚀 Instalasi

### 1. Clone Repository
```bash
git clone https://github.com/your-repo/rtnet-management.git
cd rtnet-management
```

### 2. Setup Database
1. Buat database MySQL baru:
```sql
CREATE DATABASE rtnet_db;
```

2. Import struktur database:
```bash
mysql -u username -p rtnet_db < database.sql
```

### 3. Konfigurasi Database
Edit file `classes/database.php` dan sesuaikan konfigurasi database:
```php
private $host = 'localhost';
private $dbname = 'rtnet_db';
private $username = 'your_username';
private $password = 'your_password';
```

### 4. Setup Permissions
Pastikan folder berikut memiliki permission write:
```bash
chmod 755 uploads/
chmod 755 uploads/customers/
chmod 755 uploads/payments/
```

### 5. Konfigurasi MikroTik
1. Enable API service di MikroTik:
```
/ip service enable api
/ip service set api port=8728
```

2. Buat user untuk API access:
```
/user add name=api-user password=your-password group=full
```

### 6. Setup Sistem
1. Akses aplikasi melalui browser
2. Login dengan kredensial default:
   - Username: `admin`
   - Password: `admin123`
3. Ubah password default di menu Settings
4. Konfigurasi koneksi MikroTik di menu Settings

## ⚙️ Konfigurasi

### MikroTik API Settings
- **Host**: IP address MikroTik router
- **Port**: 8728 (default API port)
- **Username**: User dengan akses API
- **Password**: Password user API

### Notification Settings

#### WhatsApp API
- **API URL**: Endpoint WhatsApp gateway
- **API Token**: Token autentikasi

#### Email SMTP
- **SMTP Host**: Server SMTP (gmail.com, dll)
- **SMTP Port**: 587 (TLS) atau 465 (SSL)
- **Username**: Email account
- **Password**: Email password/app password

#### Telegram Bot
- **Bot Token**: Token dari @BotFather
- **Chat ID**: ID grup/channel untuk notifikasi

## 📖 Panduan Penggunaan

### Menambah Pelanggan Baru
1. Masuk ke menu **Pelanggan**
2. Klik **Tambah Pelanggan**
3. Isi data lengkap pelanggan
4. Upload foto KTP dan rumah
5. Pilih paket layanan
6. Klik **Simpan**

### Provisioning ke MikroTik
1. Masuk ke menu **MikroTik**
2. Klik **Test Connection** untuk memastikan koneksi
3. Klik **Sync Customers** untuk sinkronisasi
4. Monitor status provisioning di dashboard

### Generate Invoice Bulanan
1. Masuk ke menu **Invoice**
2. Klik **Generate Invoice Bulanan**
3. Pilih bulan dan tahun
4. Klik **Generate**
5. Invoice akan dibuat otomatis untuk semua pelanggan aktif

### Monitoring Pelanggan
1. Masuk ke menu **Monitoring**
2. Lihat status online/offline real-time
3. Monitor penggunaan bandwidth
4. Kirim reminder pembayaran jika diperlukan

## 🔧 Troubleshooting

### Koneksi MikroTik Gagal
- Pastikan API service aktif di MikroTik
- Cek firewall rules tidak memblokir port 8728
- Verifikasi username/password API user
- Pastikan user memiliki privilege yang cukup

### Upload File Gagal
- Cek permission folder uploads/
- Pastikan ukuran file tidak melebihi limit PHP
- Verifikasi format file yang diizinkan

### Notifikasi Tidak Terkirim
- Cek konfigurasi API WhatsApp/Email/Telegram
- Verifikasi koneksi internet server
- Periksa log error di browser console

### Database Error
- Cek koneksi database di `classes/database.php`
- Pastikan user database memiliki privilege yang cukup
- Verifikasi struktur tabel sesuai dengan `database.sql`

## 📁 Struktur Direktori

```
rtnet/
├── assets/
│   ├── css/
│   │   └── style.css
│   └── js/
│       └── app.js
├── classes/
│   ├── Auth.php
│   ├── Customer.php
│   ├── Package.php
│   ├── Provisioning.php
│   ├── Invoice.php
│   ├── Payment.php
│   ├── Monitoring.php
│   ├── Notification.php
│   ├── Report.php
│   └── database.php
├── uploads/
│   ├── customers/
│   └── payments/
├── index.php (Dashboard)
├── login.php
├── logout.php
├── customers.php
├── packages.php
├── mikrotik.php
├── invoices.php
├── payments.php
├── monitoring.php
├── notifications.php
├── reports.php
├── settings.php
├── database.sql
└── README.md
```

## 🔐 Keamanan

### Best Practices
- Selalu gunakan HTTPS di production
- Ubah password default setelah instalasi
- Backup database secara berkala
- Update sistem secara rutin
- Gunakan strong password untuk semua akun

### File Permissions
```bash
# Set proper permissions
chmod 644 *.php
chmod 755 uploads/
chmod 644 uploads/*
chown -R www-data:www-data /path/to/rtnet/
```

## 📊 Database Schema

### Tabel Utama
- `customers` - Data pelanggan
- `packages` - Paket layanan
- `invoices` - Invoice/tagihan
- `payments` - Pembayaran
- `mikrotik_config` - Konfigurasi MikroTik
- `system_settings` - Pengaturan sistem
- `bandwidth_monitoring` - Data monitoring bandwidth
- `notification_logs` - Log notifikasi

## 🤝 Kontribusi

1. Fork repository ini
2. Buat branch fitur baru (`git checkout -b feature/AmazingFeature`)
3. Commit perubahan (`git commit -m 'Add some AmazingFeature'`)
4. Push ke branch (`git push origin feature/AmazingFeature`)
5. Buat Pull Request

## 📝 Changelog

### Version 1.0.0 (2024-01-20)
- Initial release
- Complete customer management
- MikroTik integration
- Billing system
- Monitoring & notifications
- Reporting system

## 📄 Lisensi

Project ini dilisensikan di bawah MIT License - lihat file [LICENSE](LICENSE) untuk detail.

## 📞 Support

Jika Anda membutuhkan bantuan atau memiliki pertanyaan:

- 📧 Email: support@rtnet-system.com
- 💬 WhatsApp: +62-xxx-xxxx-xxxx
- 🐛 Issues: [GitHub Issues](https://github.com/your-repo/rtnet-management/issues)

## 🙏 Acknowledgments

- [MikroTik RouterOS](https://mikrotik.com/) untuk API yang powerful
- [Bootstrap](https://getbootstrap.com/) untuk UI framework
- [Chart.js](https://www.chartjs.org/) untuk visualisasi data
- Komunitas PHP Indonesia untuk dukungan dan inspirasi

---

**RT/RW Net Management System** - Solusi lengkap untuk manajemen ISP rumahan berbasis MikroTik.

Dibuat dengan ❤️ untuk komunitas RT/RW Net Indonesia.