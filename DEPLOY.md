# Deploy Kanal IT ke VPS melalui GitHub

## 1. Dari WSL Ubuntu
Buat repository **private** kosong bernama kanal-it di GitHub (tanpa README). Dari terminal WSL:

```bash
cd ~/code/kanal-it
git status --short
git add .
git diff --cached --name-only
git commit -m "Siapkan Kanal IT dan deployment Docker"
git remote add origin git@github.com:USERNAME/kanal-it.git
git push -u origin main
```

Ganti USERNAME dengan pemilik repo. Gunakan kunci SSH GitHub untuk push. Database SQLite, .env, token, kredensial Office dan sesi WhatsApp tersimpan di luar Git. Jika tidak ada perubahan untuk di-commit, teruskan ke remote dan push.

## 2. Clone di VPS
Masuk melalui SSH dengan pengguna yang dapat menjalankan Docker. Pastikan `docker compose version` berhasil. Untuk repo private, buat deploy key khusus di VPS:

```bash
ssh-keygen -t ed25519 -f ~/.ssh/kanal_it_deploy -C kanal-it-vps
cat ~/.ssh/kanal_it_deploy.pub
```

Tambahkan **public key** di GitHub → repository → Settings → Deploy keys, dengan akses baca. Jika file kunci sudah ada, gunakan kembali. Lalu:

```bash
sudo mkdir -p /opt/kanal-it
sudo chown "$(id -un):$(id -gn)" /opt/kanal-it
git -c core.sshCommand="ssh -i $HOME/.ssh/kanal_it_deploy -o IdentitiesOnly=yes" clone git@github.com:USERNAME/kanal-it.git /opt/kanal-it
cd /opt/kanal-it
git config core.sshCommand "ssh -i $HOME/.ssh/kanal_it_deploy -o IdentitiesOnly=yes"
```

## 3. Konfigurasi untuk instalasi baru
Pilih port kosong (contoh 8443, periksa dengan `ss -ltn`). Jalankan blok berikut sekali. Ganti IP contoh dengan IP VPS yang akan digunakan browser.

```bash
cd /opt/kanal-it
(
set -euC
umask 077
ip=203.0.113.10
port=8443
cat > deploy.env <<EOF
VPS_IP=$ip
KANAL_PORT=$port
APP_NAME="Kanal IT"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://$ip:$port
APP_KEY=base64:$(openssl rand -base64 32)
REPORT_BOT_TOKEN=$(openssl rand -hex 32)
DB_CONNECTION=sqlite
DB_DATABASE=/data/kanal.sqlite
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
LOG_CHANNEL=stderr
EOF
)
docker compose --env-file deploy.env up -d --build
docker compose --env-file deploy.env ps
docker compose --env-file deploy.env exec -u www-data web php artisan kanal:admin admin@kanal.internal
```

Buka TCP 8443 pada firewall VPS dan security group provider untuk IP kantor atau jaringan VPN. Untuk UFW aktif, contohnya `sudo ufw allow from IP_PUBLIK_KANTOR to any port 8443 proto tcp`. Akses luar hanya melalui proxy HTTPS. WhatsApp memerlukan koneksi internet keluar.

## 4. Percayai sertifikat HTTPS di perangkat kantor
Caddy menerbitkan sertifikat untuk IP VPS dengan CA internal. Di VPS:

```bash
cd /opt/kanal-it
docker compose --env-file deploy.env cp proxy:/data/caddy/pki/authorities/local/root.crt ./kanal-root.crt
```

Di PowerShell Windows, unduh **sertifikat publik** melalui SSH dan pasang untuk pengguna Windows saat ini:

```powershell
scp USER@IP_VPS:/opt/kanal-it/kanal-root.crt .\kanal-root.crt
certutil -user -addstore Root .\kanal-root.crt
```

Buka **https://IP_VPS:8443**. Login dengan akun yang dibuat. Ulangi pemasangan CA di perangkat kantor lain yang akan mengakses portal. Akses HTTPS terpercaya juga diperlukan oleh fitur salin kredensial.

## 5. Data lama dan peralihan WhatsApp
Instalasi baru mulai dengan database kosong. Untuk membawa data WSL, lakukan migrasi sebelum membuat admin atau menerima laporan di VPS. Hentikan penulisan sementara, ambil backup SQLite yang konsisten dengan SQLite backup API, simpan APP_KEY asli dari .env WSL secara privat sebagai APP_KEY di deploy.env, lalu pulihkan database ke /data/kanal.sqlite pada volume data dengan pemilik www-data. APP_KEY asli diperlukan untuk membaca kredensial Office lama. Simpan backup database dan kunci terpisah dari Git.

Saat siap memindahkan penerimaan laporan, hentikan bot WSL dengan `sudo systemctl stop kanal-report-bot`. Di portal VPS buka Laporan harian → Bot WhatsApp, lalu pindai QR dari nomor bot. Periksa daftar pegawai dan grup yang diizinkan. Uji /penting dari akun pegawai. Setelah penerimaan di VPS terkonfirmasi, gunakan `sudo systemctl disable kanal-report-bot` di WSL untuk menghindari dua bot aktif setelah restart.

## Pembaruan
```bash
cd /opt/kanal-it
git pull --ff-only
docker compose --env-file deploy.env up -d --build
docker compose --env-file deploy.env ps
docker compose --env-file deploy.env logs --tail 60 web bot proxy
```

Volume data menyimpan SQLite, botstate menyimpan sesi WhatsApp, dan tls menyimpan CA. Pertahankan volume beserta deploy.env pada setiap pembaruan. Hindari down -v karena opsi tersebut menghapus volume.
