# Feature Suggestions — Backlink Checker Pro v2

Bu belge, mevcut altyapı ve mimari göz önünde bulundurularak hazırlanmış yeni özellik önerilerini içerir.

---

## 1. Rakip Backlink Analizi (Competitor Backlink Analysis)

**Öncelik:** Yüksek

Kullanıcıların kendi sitelerinin backlink profilini rakipleriyle karşılaştırabilmesi.

- Proje bazında rakip domain tanımlama
- Ortak backlink kaynakları tespiti (link intersection)
- Rakipte olup sizde olmayan backlink fırsatları
- DA/PA karşılaştırma tablosu
- Rakip backlink büyüme trendi

**Etkilenen bileşenler:** `ScanService`, `BacklinkAnalyzerService`, yeni `CompetitorService`, `WebController`, yeni şablon

---

## 2. Backlink Sağlık Skoru (Backlink Health Score)

**Öncelik:** Yüksek

Her proje ve tarama için toplam bir "sağlık skoru" hesaplama.

- DA, PA, link durumu (dofollow/nofollow), HTTP status kodu, redirect zinciri uzunluğu gibi parametrelerden ağırlıklı skor
- Skor trendi (zaman içinde iyileşme/kötüleşme)
- Tehlikeli backlink uyarıları (düşük DA, spam siteler)
- Dashboard'da görsel gösterge (gauge/progress bar)

**Etkilenen bileşenler:** Yeni `HealthScoreService`, `ScanService`, `WebController`, dashboard şablonu

---

## 3. Google Disavow Dosyası Oluşturucu

**Öncelik:** Yüksek

Zararlı backlink'ler için Google Disavow Tool uyumlu dosya üretme.

- Tarama sonuçlarından tek tıkla zararlı link seçimi
- Domain veya URL bazında disavow kuralı
- Google Disavow formatında `.txt` dışa aktarma
- Daha önce disavow edilmiş linklerin takibi

**Etkilenen bileşenler:** Yeni `DisavowService`, `ExportService`, `WebController`, `ApiController`

---

## 4. Anchor Text Dağılım Analizi

**Öncelik:** Yüksek

Backlink'lerdeki anchor (bağlantı) metinlerinin analizi.

- Anchor text toplama ve kategorilendirme (exact match, partial match, branded, generic, naked URL)
- Dağılım grafiği / pasta chart
- Over-optimizasyon uyarısı (aynı anchor text oranı çok yüksekse)
- Zaman içinde anchor text değişim trendi

**Etkilenen bileşenler:** `BacklinkAnalyzerService` (anchor text çıkarma), yeni `AnchorAnalysisService`, `scan_links` tablosu (anchor_text sütunu)

---

## 5. Link Hız Takibi (Link Velocity Tracking)

**Öncelik:** Orta

Belirli bir zaman aralığında kazanılan ve kaybedilen backlink sayısının izlenmesi.

- Günlük/haftalık/aylık yeni backlink kazanımı
- Kaybedilen backlink tespiti (önceki taramada var, şimdikinde yok)
- Velocity grafiği (trend çizgisi)
- Anormal artış/azalış için otomatik uyarı

**Etkilenen bileşenler:** `ScanService`, yeni `VelocityService`, `NotificationService`, `schedules` tablosu

---

## 6. Toplu URL İçe Aktarma (Bulk Import)

**Öncelik:** Orta

Tarama hedeflerini tek tek girmek yerine toplu olarak yükleme.

- CSV dosyasından URL listesi içe aktarma
- XML Sitemap URL'sinden otomatik çekme
- Google Search Console entegrasyonundan URL listesi
- Yinelenen URL tespiti ve filtreleme
- Mevcut 500 URL sınırı içinde validasyon

**Etkilenen bileşenler:** `ScanService`, yeni `ImportService`, `WebController`, `ApiController`

---

## 7. Ek SEO Metrik Sağlayıcıları (Provider Entegrasyonu)

**Öncelik:** Orta

Moz dışında ek SEO veri kaynaklarının entegrasyonu.

- **Ahrefs**: Domain Rating (DR), URL Rating (UR), referring domains
- **SEMrush**: Authority Score, backlink sayısı
- **Majestic**: Trust Flow, Citation Flow
- Google PageRank alternatif metrikleri
- Provider seçimi proje bazında yapılandırılabilir

**Etkilenen bileşenler:** `Providers/` dizini (yeni adapter sınıfları), `ProviderCacheService`, `SettingsService`, `.env` yapılandırması

---

## 8. Dashboard Grafikleri ve Görsel Analitik

**Öncelik:** Orta

Dashboard'da interaktif grafikler ve görsel veri sunumu.

- Backlink sayısı zaman serisi grafiği
- DA/PA dağılım histogramı
- Dofollow vs Nofollow oranı pasta grafiği
- HTTP durum kodu dağılımı
- En çok backlink veren domainler (top referring domains)
- Lightweight JS chart kütüphanesi (Chart.js veya ApexCharts — tek bağımlılık, CDN üzerinden)

**Etkilenen bileşenler:** Dashboard şablonu, `WebController`, yeni `AnalyticsService`

---

## 9. İki Faktörlü Kimlik Doğrulama (2FA / TOTP)

**Öncelik:** Orta

Güvenliği artırmak için TOTP tabanlı iki faktörlü kimlik doğrulama.

- Google Authenticator / Authy uyumlu TOTP
- QR kod ile kolay kurulum
- Kurtarma kodları (recovery codes)
- Admin tarafından zorunlu kılınabilir
- API token'ları için ayrı 2FA bypass politikası

**Etkilenen bileşenler:** `AuthService`, `SecurityService`, `users` tablosu (totp_secret, recovery_codes sütunları), login şablonu

---

## 10. Zamanlanmış E-posta Raporları

**Öncelik:** Orta

Periyodik olarak backlink durumu hakkında özet e-posta raporları gönderme.

- Haftalık/aylık otomatik rapor
- Proje bazında yapılandırılabilir alıcı listesi
- Rapor içeriği: yeni/kaybedilen backlinkler, sağlık skoru değişimi, uyarılar
- HTML e-posta şablonu
- Rapor geçmişi görüntüleme

**Etkilenen bileşenler:** `NotificationService`, `ScheduleService`, yeni `ReportService`, yeni e-posta şablonları

---

## 11. Robots.txt ve Meta Robots Detaylı Analizi

**Öncelik:** Düşük

Backlink kaynaklarının robots.txt ve meta robots direktiflerinin derinlemesine analizi.

- Kaynak sitenin robots.txt kurallarını ayrıştırma
- Crawl-delay, Sitemap referansları
- `noindex`, `nofollow`, `noarchive` meta tag tespiti
- X-Robots-Tag HTTP header analizi
- Taranabilirlik skoru

**Etkilenen bileşenler:** `BacklinkAnalyzerService`, `HttpClient`, `scan_results` tablosu

---

## 12. Webhook Olay Zenginleştirme

**Öncelik:** Düşük

Mevcut webhook sisteminin daha fazla olay türü ile genişletilmesi.

- `backlink.lost` — backlink kaybedildiğinde
- `backlink.gained` — yeni backlink tespit edildiğinde
- `health_score.drop` — sağlık skoru belirli bir eşiğin altına düştüğünde
- `scan.error` — tarama hatası oluştuğunda
- Olay bazında webhook filtresi

**Etkilenen bileşenler:** `WebhookService`, `NotificationService`, `notifications` tablosu

---

## 13. Kullanıcı Aktivite Günlüğü ve Dashboard

**Öncelik:** Düşük

Ekip üyelerinin aktivitelerini izlemek için gelişmiş günlük ve panel.

- Kullanıcı bazında son aktiviteler listesi
- Filtrelenebilir audit log dashboard'u (tarih, kullanıcı, eylem türü)
- CSV dışa aktarma
- Aktivite istatistikleri (en aktif kullanıcılar, en çok yapılan işlemler)

**Etkilenen bileşenler:** `AuditService`, `WebController`, yeni şablon

---

## 14. Karanlık Mod (Dark Mode)

**Öncelik:** Düşük

Kullanıcı arayüzü için karanlık tema desteği.

- CSS değişkenleri ile tema sistemi
- Kullanıcı tercihine göre kaydetme
- Sistem temasını otomatik algılama (`prefers-color-scheme`)
- Tüm şablonlarda tutarlı karanlık tema

**Etkilenen bileşenler:** CSS/stil dosyaları, şablonlar, `users` tablosu (theme_preference)

---

## 15. Public API v2 (Genişletilmiş)

**Öncelik:** Düşük

Mevcut API v1'in genişletilmiş versiyonu.

- Sayfalama (pagination) desteği tüm liste endpoint'lerinde
- Filtreleme ve sıralama parametreleri
- Batch işlem endpoint'leri (toplu tarama başlatma)
- Rate limit bilgisini response header'larında döndürme
- OpenAPI/Swagger dokümantasyonu otomatik üretimi
- Webhook yönetim endpoint'leri

**Etkilenen bileşenler:** `ApiController`, `Router`, yeni dokümantasyon

---

## Uygulama Öncelik Sıralaması

| Sıra | Özellik | Öncelik | Mevcut Altyapı Uyumu |
|------|---------|---------|----------------------|
| 1 | Rakip Backlink Analizi | Yüksek | ScanService genişletme |
| 2 | Backlink Sağlık Skoru | Yüksek | Mevcut metrikler üzerine |
| 3 | Google Disavow Oluşturucu | Yüksek | ExportService genişletme |
| 4 | Anchor Text Analizi | Yüksek | BacklinkAnalyzer genişletme |
| 5 | Link Velocity Tracking | Orta | Schedule sistemi mevcut |
| 6 | Toplu URL İçe Aktarma | Orta | ScanService genişletme |
| 7 | Ek Provider Entegrasyonu | Orta | Provider mimarisi hazır |
| 8 | Dashboard Grafikleri | Orta | Veri katmanı hazır |
| 9 | 2FA (TOTP) | Orta | AuthService genişletme |
| 10 | E-posta Raporları | Orta | Notification altyapısı hazır |
| 11 | Robots.txt Detaylı Analiz | Düşük | Analyzer genişletme |
| 12 | Webhook Olay Zenginleştirme | Düşük | Webhook altyapısı hazır |
| 13 | Aktivite Günlüğü Dashboard | Düşük | AuditService mevcut |
| 14 | Karanlık Mod | Düşük | CSS değişikliği |
| 15 | Public API v2 | Düşük | ApiController genişletme |
