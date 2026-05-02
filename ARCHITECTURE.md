# Arsitektur Standar (Rapi dan Portable)

## Tujuan

- Runtime produksi stabil di Proxmox.
- Struktur file jelas antara runtime, data, dan operasi.
- Mudah dipindah ke server lain tanpa mengubah behavior ACS ONT.

## Struktur direktori yang dipakai

- `docker-compose.yml`
  - baseline runtime yang saat ini aktif.
- `docker-compose.portable.yml`
  - override khusus mode portable/migrasi.
- `.env` / `.env.example`
  - semua konfigurasi environment.
- `imports/`
  - baseline dump Mongo awal.
- `overlays/`
  - provision + virtual parameter overlay.
- `dashboard/app/`
  - source custom dashboard.
- `scripts/`
  - automation operasional (sync, normalisasi ACS URL, preflight, export bundle).
- `inventory/`
  - data inventaris OLT/ONU.
- `backups/`
  - output migration bundle runtime.

## Profile deploy

### 1) Runtime aktif (existing)

```bash
docker compose --env-file .env up -d --build
```

### 2) Portable mode (untuk server baru)

```bash
docker compose --env-file .env -f docker-compose.portable.yml up -d --build
```

Portable mode membuat dashboard tidak tergantung host network, sehingga lebih mudah dipindah.

## Guardrail agar ONT tidak terdampak

- Endpoint CWMP tetap: `http://10.88.0.100:7547`.
- Jangan ubah credential TR-069 saat migration.
- Jangan reboot ONT massal saat cutover.
- Cutover dilakukan di layer IP/VIP/NAT, bukan ubah setting ONT satu per satu.
