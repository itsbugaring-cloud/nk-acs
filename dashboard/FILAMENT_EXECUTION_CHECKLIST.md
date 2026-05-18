# Filament Execution Checklist (Daily Ops)

Checklist ini dipakai saat implementasi migrasi supaya perubahan terkontrol.

## Day 1-2: Bootstrap

- [ ] Buat app Laravel `dashboard-filament`
- [ ] Install Filament panel
- [ ] Konfigurasi auth admin
- [ ] Setup env koneksi DB + NBI
- [ ] Tambahkan page health check

## Day 3-4: Device Read Model

- [ ] Implement `GenieAcsService` (read-only)
- [ ] Build table device (pagination, search)
- [ ] Build detail inspector (slide-over)
- [ ] Build summon read-only action
- [ ] Tambahkan timeout/retry toast

## Day 5-6: OLT & Alarm

- [ ] OLT registry CRUD
- [ ] Sync action OLT manual
- [ ] Alarm list + filter
- [ ] Ack/resolve actions
- [ ] Timeline panel alarm

## Day 7: Validation

- [ ] Uji login
- [ ] Uji dashboard load
- [ ] Uji device table + detail
- [ ] Uji summon read-only
- [ ] Uji OLT save/sync
- [ ] Uji alarm ack/resolve

## Wajib setiap commit

- [ ] Tidak ada write action ONT terbuka by default
- [ ] Tidak ada hardcoded secret/token
- [ ] Error handling jelas (JSON/API timeout)
- [ ] Catat perubahan di changelog migrasi

