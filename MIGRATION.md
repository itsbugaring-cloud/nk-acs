# Migrasi ACS ke Server Baru (Aman Produksi)

Dokumen ini dibuat untuk memudahkan pemindahan stack ACS ke server lain dengan risiko minimum terhadap ONT pelanggan.

## Prinsip aman

- Jangan ubah `ACS URL` di ONT saat migrasi.
- Pertahankan endpoint CWMP yang sama (`http://10.88.0.100:7547`) lewat IP yang sama, VIP, atau NAT switchover.
- Siapkan server target sampai sehat dulu, baru cutover endpoint.
- Hindari reboot ONT massal selama migrasi.

## 1) Di server lama (aktif)

Jalankan preflight:

```bash
cd /opt/genieacs-production-ready
./scripts/preflight-portability.sh
```

Ekspor migration bundle (tanpa menghentikan service):

```bash
./scripts/export-migration-bundle.sh
```

Hasil bundle ada di:

- `backups/genieacs-migration-YYYYmmdd-HHMMSS.tar.gz`

## 2) Di server baru (target)

Persiapan minimum:

- Docker + Compose plugin aktif
- Direktori kerja: `/opt/genieacs-production-ready`
- Port tersedia: `7547, 7557, 7567, 3000, 80`

Copy bundle ke server baru, lalu extract:

```bash
mkdir -p /opt/genieacs-migration
tar -xzf genieacs-migration-*.tar.gz -C /opt/genieacs-migration
```

Pulihkan file kerja:

```bash
cp /opt/genieacs-migration/migration-*/docker-compose.yml /opt/genieacs-production-ready/docker-compose.yml
cp /opt/genieacs-migration/migration-*/.env /opt/genieacs-production-ready/.env
tar -xzf /opt/genieacs-migration/migration-*/overlays.tar.gz -C /opt/genieacs-production-ready
tar -xzf /opt/genieacs-migration/migration-*/dashboard-app.tar.gz -C /opt/genieacs-production-ready
```

Deploy stack:

```bash
cd /opt/genieacs-production-ready
docker compose --env-file .env up -d --build
```

Restore Mongo runtime:

```bash
zcat /opt/genieacs-migration/migration-*/mongo-*.archive.gz | \
  docker exec -i ${PROJECT_NAME}-mongo mongorestore --archive --gzip --drop
```

Restore ext:

```bash
docker exec -i ${PROJECT_NAME}-genieacs sh -lc "tar -xzf - -C /" < /opt/genieacs-migration/migration-*/genieacs-ext.tar.gz
```

## 3) Validasi sebelum cutover

Checklist wajib:

- `docker compose ps` semua service `healthy`
- `curl -I http://127.0.0.1:7547` dari target berhasil
- `curl -I http://127.0.0.1/login.php` dari target berhasil
- Login dashboard dan GenieACS UI normal
- Sample 3-5 ONT existing terlihat di UI dan parameter masih kebaca

## 4) Cutover endpoint (durasi singkat)

- Pindahkan IP/VIP/NAT endpoint `10.88.0.100:7547` ke server baru.
- Pastikan route/firewall mengizinkan trafik dari seluruh segmen OLT/ONT.
- Pantau `genieacs-cwmp-access.log` selama 15-30 menit.

## 5) Rollback cepat

Jika inform drop signifikan:

- Kembalikan endpoint `10.88.0.100:7547` ke server lama.
- Jangan ubah credential ONT.
- Audit ACL/firewall/routing target lalu coba cutover ulang.
