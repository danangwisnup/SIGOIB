# DEPLOYMENT GUIDE — Sistem Monitoring IB & Quick Check

Panduan memindahkan `/app/project/` ke server produksi (cPanel atau VPS) dan menyiapkan build mobile.

---

## A. Kebutuhan Server

- PHP **8.2+** (extension: `pdo_mysql`, `json`, `mbstring`)
- MariaDB 10.4+ / MySQL 8+
- Apache (mod_rewrite + .htaccess aktif) **atau** Nginx + PHP-FPM
- HTTPS (wajib untuk produksi — device token & GPS melewati jaringan)

---

## B. Deployment via cPanel (tanpa SSH)

1. **Upload kode**
   - Buka **File Manager** → masuk ke `public_html` (atau docroot subdomain, mis. `monitoring.domainanda.com`).
   - Upload seluruh isi folder `project/` (zip lalu extract). Struktur akhir:
     `public_html/.htaccess`, `public_html/backend/`, `public_html/web/`, dst.
   - Document root = folder yang berisi `.htaccess` root project (bukan `web/`).

2. **Versi PHP**
   - cPanel → **Select PHP Version** / **MultiPHP Manager** → pilih **PHP 8.2+** untuk domain/subdomain.

3. **Database**
   - cPanel → **MySQL Database Wizard**:
     - Buat database, mis. `user_monitoring`.
     - Buat user DB baru dengan **password kuat** (JANGAN pakai credential development `monitoring`/`mon1torING_dev`).
     - Berikan **ALL PRIVILEGES** ke database tsb.
   - cPanel → **phpMyAdmin** → pilih database → tab **Import**:
     1. `backend/database/schema.sql`
     2. `backend/database/seed.sql`
     3. `backend/database/seed_users.sql` (akun default; alternatif `php database/seed.php` jika ada SSH)
     4. (opsional, untuk pilot) `backend/database/test_data.sql`

4. **Konfigurasi backend `.env`**
   - Di File Manager, salin `backend/.env.example` → `backend/.env`, lalu edit:

   ```ini
   DB_HOST=localhost
   DB_PORT=3306
   DB_NAME=user_monitoring        # nama DB dari langkah 3 (perhatikan prefix cPanel user_)
   DB_USER=user_monitoring_app    # user DB dari langkah 3
   DB_PASS=PASSWORD_KUAT_BARU

   APP_TIMEZONE=Asia/Jakarta
   TOKEN_TTL_HOURS=12
   TRACKING_INTERVAL=30
   STANDBY_POLL_INTERVAL=60
   ```
   - File `.htaccess` root sudah memblokir akses web ke `backend/.env` dan `backend/` selain `backend/api/`.

5. **HTTPS**
   - cPanel → **SSL/TLS Status** → jalankan **AutoSSL** (Let's Encrypt) untuk domain/subdomain.
   - Setelah sertifikat aktif, paksa HTTPS — tambahkan di `.htaccess` root (paling atas):

   ```apache
   RewriteCond %{HTTPS} off
   RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```

6. **Health check API**
   - Buka browser: `https://domainanda/api/auth/login` → harus merespons JSON error `405 Method tidak diizinkan` (artinya route hidup).
   - Login web admin di `https://domainanda/` dengan akun default, lalu **segera ganti password** (Pengaturan → Ganti Password) untuk semua akun seed.

7. **Cron retensi GPS (opsional, disarankan)**
   - cPanel → **Cron Jobs**, harian:
   ```bash
   mysql -u USERDB -p'PASS' NAMEDB -e "DELETE FROM locations WHERE received_at < NOW() - INTERVAL 90 DAY;"
   ```

---

## C. Deployment via VPS (Apache / Nginx)

**Apache:**
- Docroot → folder `project/`. Aktifkan `mod_rewrite`, `AllowOverride All`.
- Import DB: `mysql -u root -p < backend/database/schema.sql` lalu `seed.sql`, lalu `php database/seed.php` (atau `seed_users.sql`).
- HTTPS: certbot (`sudo certbot --apache`).

**Nginx + PHP-FPM** (tidak membaca .htaccess — blokir manual folder backend):

```nginx
server {
    listen 443 ssl;
    server_name domainanda;
    root /path/project/web;
    # ssl_certificate ...; ssl_certificate_key ...;

    location / { try_files $uri $uri/ /index.php; }

    location /api/ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME /path/project/backend/api/index.php;
        include fastcgi_params;
    }

    location ~* \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
# backend/ tidak di-expose sama sekali oleh konfigurasi di atas.
```

---

## D. Keamanan Produksi (WAJIB)

- [ ] **Ganti semua password default** (admin, komandan, wadan, danki.a, danton.a1) via menu Pengaturan, atau nonaktifkan akun yang tidak dipakai.
- [ ] **Ganti credential database** — jangan pernah memakai `monitoring`/`mon1torING_dev` (credential development) di produksi.
- [ ] Pastikan `backend/.env` tidak bisa diakses web (sudah diblokir `.htaccess`; untuk Nginx, `backend/` memang tidak di-serve).
- [ ] HTTPS aktif dan HTTP dialihkan ke HTTPS.
- [ ] **CORS**: tidak diperlukan konfigurasi tambahan — web admin dan API satu origin, dan Flutter (native) tidak terkena CORS. Hanya tambahkan header CORS jika nanti ada klien browser dari domain lain (tidak disarankan untuk MVP).
- [ ] Jangan menaruh credential di file web/mobile; token disimpan server-side (`auth_tokens`, `devices.device_token`).

---

## E. Konfigurasi Mobile (Flutter)

API base URL diatur saat build (bukan hardcode di repo):

```bash
cd mobile
flutter create .          # sekali saja: melengkapi file platform (gradle/xcodeproj)
flutter pub get
flutter analyze
flutter run --dart-define=API_BASE_URL=https://domainanda
flutter build apk --release --dart-define=API_BASE_URL=https://domainanda
# iOS (di macOS): flutter build ios --release --dart-define=API_BASE_URL=https://domainanda
```

Detail permission/background: lihat `mobile/README.md`.

---

## F. Deployment Checklist

### SERVER
- [ ] PHP 8.2+ aktif (pdo_mysql tersedia)
- [ ] MariaDB/MySQL berjalan
- [ ] Database imported (schema.sql + seed.sql + seed_users.sql)
- [ ] `.env` configured (credential produksi, bukan development)
- [ ] HTTPS aktif + redirect HTTP→HTTPS
- [ ] API health check (`/api/auth/login` merespons JSON)
- [ ] Password default diganti
- [ ] (Opsional) cron retensi GPS 90 hari

### WEB
- [ ] Login
- [ ] Dashboard (cards + map termuat)
- [ ] Personel (list + import CSV)
- [ ] Device (pending/approve/revoke)
- [ ] Monitoring (buat IB + quick check + detail map)
- [ ] Alert
- [ ] Geofence (buat area via map picker)
- [ ] History (route map)
- [ ] Report (export CSV)

### MOBILE
- [ ] Flutter build (`flutter analyze` bersih)
- [ ] Android APK ter-install
- [ ] iOS build (macOS + Xcode)
- [ ] API connection (status terhubung server produksi via HTTPS)
- [ ] NRP activation
- [ ] Device approval (admin approve → app lanjut otomatis)
- [ ] GPS (tracking saat monitoring aktif)
- [ ] Background GPS (app minimized / screen locked)
- [ ] Offline queue (airplane mode → point tersimpan lokal)
- [ ] Sync (online kembali → batch terkirim tanpa duplikat)

---

## G. Hal yang Masih Membutuhkan Pengujian Perangkat Nyata

- Background GPS Android (foreground service, battery optimization per vendor: Xiaomi/Oppo/dsb).
- Background location iOS (capability + review Apple).
- Perilaku setelah force-stop / reboot (batasan OS — tidak dijamin, sesuai desain).
- Akurasi interval 30 dtk di kondisi lapangan & konsumsi battery.
- Pilot 5–10 perangkat sebelum deployment penuh.
