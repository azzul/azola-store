# Azola Store

Template web toko online Laravel yang tersinkron dengan **Azola Pos** (desktop & Android).

## Tujuan
- Stok realtime di web, desktop, dan Android (satu database + satu API)
- Jurnal akuntansi otomatis dari satu `JournalService`
- Penjualan idempoten dan ringan (queue)
- SEO bagus dan tampilan modern

## Arsitektur
Web (Blade) / Azola Pos Desktop (C# WinForms) / Android -> Laravel API (Sanctum) -> MySQL pusat, dengan Redis queue dan Reverb (WebSocket) untuk stok realtime.

## Status
- [ ] Tahap 1: skema, StockService, JournalService, API
- [ ] Tahap 2: web toko + SEO
- [ ] Tahap 3: dashboard admin (stok realtime, jurnal, rekonsiliasi)
