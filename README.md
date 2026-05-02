# GenieACS Production Ready

Baseline deploy ini dirancang untuk produksi di Proxmox dengan fokus:

- GenieACS stabil dari `genieacs-container-main`
- dump Mongo utama dari `ACS-DB-main`
- overlay `provisions` dan `virtual-params` dari `virparams-main`
- dashboard utama dari `GACS-Dashboard-main`
- `genieacs-master` dipakai sebagai referensi source inti, bukan runtime produksi
- `genieacs-main` dan `parameter-main` diperlakukan sebagai referensi cadangan agar baseline deploy tetap bersih

## Arsitektur

- `mongo`: penyimpanan utama GenieACS dengan auth aktif
- `mongo-seed`: restore baseline Mongo idempotent
- `genieacs-init`: menulis `cwmp.auth`, membuat admin UI, dan mengimpor overlay provision/vparam
- `genieacs`: layanan ACS live
- `mariadb`: database dashboard
- `dashboard`: GACS Dashboard yang sudah di-hardening untuk env-based config

## Kredensial TR-069 Aktif

- ACS URL: `http://10.88.0.100:7547`
- CPE username: `caesarbugar`
- CPE password: `CaesarBugar007`

`genieacs-init` akan menulis config `cwmp.auth` agar ONT yang sudah dipointing ke tiga nilai di atas langsung diterima GenieACS.

## Port Produksi

- `7547`: CWMP / TR-069
- `7567`: File server GenieACS
- `3000`: GenieACS UI
- `80`: GACS Dashboard

Port `7557` sengaja tidak diekspos publik. Dashboard mengakses NBI lewat network internal Docker.

## Folder Penting

- `imports/mongo-base`: dump utama Mongo dari `ACS-DB-main`
- `overlays/provisions`: provision overlay dari `virparams-main`
- `overlays/virtual-params`: virtual parameters overlay dari `virparams-main`
- `dashboard/app`: source dashboard yang sudah siap dibangun jadi image

## Deploy

Di host Linux:

```bash
docker compose --env-file .env up -d --build
docker compose ps
```

## Operasional Cepat

Normalisasi massal device yang masih memakai URL ACS lama atau trailing slash:

```bash
./scripts/normalize-acs-url.sh --dry-run --include-slash
./scripts/normalize-acs-url.sh --include-slash
```

Script ini menarget device yang masih memakai:

- `http://ont.alinos-dashboard.my.id`
- `http://10.88.0.100:7547/` bila `--include-slash` dipakai

Lalu menulis ulang:

- URL ACS: `http://10.88.0.100:7547`
- Username: `caesarbugar`
- Password: `CaesarBugar007`
- `PeriodicInformEnable=true`
- `PeriodicInformInterval=300`

## Portability / Pindah Server

Untuk menyiapkan migrasi ke server baru tanpa ribet, gunakan:

```bash
./scripts/preflight-portability.sh
./scripts/export-migration-bundle.sh
```

Dokumen langkah lengkap dan aman produksi:

- `MIGRATION.md`
- `ARCHITECTURE.md`

Mode portable (untuk target server baru):

```bash
docker compose --env-file .env -f docker-compose.portable.yml up -d --build
```

## Catatan Aman

- `init.php` dashboard dinonaktifkan otomatis saat container start
- Mongo auth diaktifkan
- secret runtime dipisah ke `.env`
- import Mongo dan overlay dibuat idempotent agar redeploy aman
- `genieacs-init` juga menghapus preset/provision kredensial lama seperti `useradmin` agar password web ONT tidak tertimpa otomatis saat inform
- write action ke ONT (reboot/WAN/WiFi/DHCP/factory reset) dilindungi `CPE_WRITE_ENABLED` (default lock)

Untuk mengizinkan aksi write ONT secara sadar:

```bash
# di env dashboard
CPE_WRITE_ENABLED=true
```
