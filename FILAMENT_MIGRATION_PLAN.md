# Filament Migration Plan (NETKING-ACS)

Dokumen ini untuk migrasi UI custom GACS saat ini ke Laravel + Filament **tanpa mengganggu ACS produksi**.

## Prinsip Utama

- Jangan ubah engine GenieACS/CWMP aktif.
- Jalankan Filament paralel dulu (canary), bukan replace langsung.
- Semua action write ONT tetap lock default.
- Cutover hanya setelah parity fitur read-only tercapai.

## Target Arsitektur

- App baru: `dashboard-filament` (Laravel 11 + Filament 3/4)
- Data source:
  - MariaDB dashboard (telemetry, inventory OLT, alarm events)
  - GenieACS NBI (read, task monitor, summon read-only)
- Queue worker:
  - job async untuk polling/task monitor/alarm digest
- Auth:
  - role `admin/operator/viewer`

## Strategi Rollout Aman

1. Phase 0 - Foundation (paralel)
2. Phase 1 - Read-only parity
3. Phase 2 - OLT + Alarm operational parity
4. Phase 3 - Bot/monitor integration
5. Phase 4 - Controlled cutover

---

## Phase 0 - Foundation

Output:

- Laravel app + Filament panel aktif di path terpisah (contoh `/admin2` atau host `gacs2.netking.id`)
- Koneksi DB read-only dulu
- Health endpoint + smoke test

Checklist:

- Buat service layer:
  - `GenieAcsService`
  - `OltInventoryService`
  - `AlarmEventService`
- Buat env terpisah:
  - `GENIEACS_NBI_URL`
  - `GENIEACS_USERNAME`
  - `GENIEACS_PASSWORD`
  - `CPE_WRITE_ENABLED=false`
- Tambahkan policy lock:
  - semua action write hidden/disabled saat `CPE_WRITE_ENABLED=false`

---

## Phase 1 - Read-only Parity

Wajib ada:

- Dashboard cards:
  - total, online, offline, availability
- Device table:
  - serial, mac, product class, ip tr069, ssid, pppoe, rx, temp, client, status
- Device inspector:
  - ACS/OLT summary
  - timeline read-only
- Summon read-only action

Kriteria selesai:

- Operator bisa menjalankan workflow harian tanpa masuk UI lama.
- Tidak ada 5xx/error JSON parse di panel Filament.

---

## Phase 2 - OLT Registry + Alarm Parity

Wajib ada:

- OLT Registry CRUD
- Sync OLT manual
- OLT health board
- First-inform funnel
- Alarm center:
  - filter
  - ack
  - resolve
  - timeline per alarm/device

Kriteria selesai:

- OLT operation dan alarm triage full bisa dari Filament.

---

## Phase 3 - Integrasi Monitoring/Bot

Wajib ada:

- Task queue monitor
- Reachability monitor
- Optical trend + unsupported list
- Integrasi telegram events (read and status action)

Kriteria selesai:

- Ops command center sudah satu pintu di Filament.

---

## Phase 4 - Controlled Cutover

Langkah:

1. Canary user (1-2 operator) pakai Filament 3-5 hari.
2. Perbaiki gap yang muncul.
3. Alihkan menu utama ke Filament.
4. Simpan UI lama sebagai fallback 7-14 hari.
5. Nonaktifkan UI lama setelah stabil.

Rollback:

- DNS/path balik ke UI lama.
- Engine ACS dan DB tidak berubah, jadi rollback cepat.

---

## Mapping Fitur Lama -> Filament

### Dashboard

- Lama: `dashboard.php`
- Baru: Filament `Widgets` + custom `Page`

### Devices

- Lama: `devices.php`, `device-detail.php`
- Baru: `DeviceResource` + `Infolist` + `Table` + `SlideOver`

### OLT Registry

- Lama: `olt-registry.php`, `api/olt-registry-*`
- Baru: `OltResource` + `SyncAction`

### Alarm

- Lama: `alarm-events.php`, `api/telegram-alarm-events.php`
- Baru: `AlarmEventResource` + `Table Filters` + row actions (`Ack`,`Resolve`)

### Integrasi

- Lama: `configuration.php` + `api/save-*`
- Baru: `Settings pages` dengan encrypted config store

---

## Guardrail Produksi (Wajib)

- Action write default off:
  - reboot
  - wifi/pppoe change
  - factory reset
  - local web credential change
- Jika nanti dibuka:
  - role `admin` only
  - two-step confirmation
  - reason required
  - audit log mandatory
  - max device per batch

---

## Definisi "Siap Cutover"

- Semua fitur read-only utama parity.
- OLT registry + alarm center parity.
- Summon read-only stabil.
- Tidak ada error parser/route mismatch.
- Canary operator sign-off.

