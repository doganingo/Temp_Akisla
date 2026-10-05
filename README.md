# Akışla — talep formu (Enteksis aday görevi ALEX-24H-v1.0)

Kurgusal bir görev otomasyonu hizmeti için landing page ve talep formu. Talepler sunucuda MySQL'e **kalıcı** olarak kaydedilir; başarı mesajı yalnızca kayıt gerçekten oluştuğunda gösterilir.

- **Canlı:** https://dogantemp.teknoparse.com
- **Stack:** HTML + CSS + JavaScript (build yok, kütüphane yok) · PHP 8.3 · MySQL (Hostinger paylaşımlı hosting)
- **AI kullanımı ve kararlar:** [AI_LOG.md](AI_LOG.md)
- Sitedeki tüm içerik ve test kayıtları kurgusaldır.

## Özellikler

| Gereksinim | Nerede |
|---|---|
| Mobil + masaüstü uyumlu landing page | `index.html`, `style.css` (mobil öncelikli; 700 px ve 960 px kırılımları) |
| Form: isim, e-posta, hizmet, açıklama | `index.html` |
| İstemci doğrulaması | `app.js` (`rules`) |
| Sunucu doğrulaması | `api/validate.php` (`validate_request`) |
| Gönderiliyor / başarı / hata durumları | `app.js` (`setSubmitting`, `setStatus`) |
| Kalıcı kayıt | `api/submit.php` → MySQL `requests` tablosu |
| Başarı yalnızca gerçek kayıtta | `submit.php` 201'i yalnızca INSERT'ten sonra döner; `app.js` yalnızca `201 + ok:true` için başarı gösterir |
| Ek: isteğe bağlı ön görüşme randevusu | `api/slots.php`, `app.js`; çift rezervasyon DB'de `UNIQUE` ile engelli |

## Veri akışı

```
Tarayıcı (app.js)
  │ 1. İstemci doğrulaması (kullanıcı deneyimi için)
  │ 2. POST /api/submit.php  {name, email, service, message, meeting_date?, meeting_slot?, website}
  ▼
api/submit.php
  │ 3. Yalnızca POST, gövde JSON (≤ 20 KB)
  │ 4. Honeypot (website) doluysa 400
  │ 5. validate_request() → hata varsa 422 + alan bazlı hatalar
  │ 6. Rate limit: aynı IP özetinden 10 dk'da en fazla 5 kayıt → 429
  │ 7. PDO prepared statement ile INSERT
  │      ├─ randevu saati dolu (UNIQUE ihlali, MySQL 1062) → 409
  │      └─ diğer DB hatası → loglanır, kullanıcıya genel mesaj, 500
  ▼
201 {ok: true}  →  app.js başarı mesajını gösterir, formu temizler
```

Randevu seçilirse `app.js`, seçilen gün için `GET /api/slots.php?date=YYYY-MM-DD` ile dolu/boş saatleri alır.

### HTTP yanıtları

| Kod | Anlamı |
|---|---|
| 201 | Kayıt oluşturuldu |
| 400 | Gövde JSON değil / bozuk, ya da honeypot dolu |
| 405 | Yanlış HTTP metodu (`Allow` başlığıyla) |
| 409 | Seçilen randevu saati bu arada doldu |
| 422 | Alan doğrulaması başarısız (`errors` içinde alan bazlı mesajlar) |
| 429 | Rate limit aşıldı (`Retry-After` başlığıyla) |
| 500 | Sunucu/DB hatası (detay yalnızca sunucu logunda) |

## Doğrulama kuralları

İstemci ve sunucuda aynıdır; asıl güvence sunucudadır.

| Alan | Kural |
|---|---|
| İsim | 2–100 karakter (Türkçe karakterler `mb_strlen` ile 1 sayılır), kontrol karakteri yok |
| E-posta | `FILTER_VALIDATE_EMAIL`, en fazla 254 karakter |
| Hizmet | Beyaz liste: `otomasyon-kurulumu`, `entegrasyon`, `raporlama`, `danismanlik` |
| Açıklama | 10–2000 karakter |
| Randevu (isteğe bağlı) | Gün ve saat birlikte; `YYYY-AA-GG`, hafta içi, yarından itibaren 14 gün içinde; saat `10:00, 11:00, 14:00, 15:00, 16:00` |

String olmayan değerler (dizi, sayı, `null`) boş kabul edilir; geçersiz UTF-8 reddedilir; beklenmeyen alanlar yok sayılır.

## Güvenlik

- **SQL injection:** tüm sorgular PDO prepared statement (`ATTR_EMULATE_PREPARES = false`).
- **XSS:** kullanıcı verisi sayfaya hiç basılmaz; mesajlar `textContent` ile yazılır. Ek katman olarak sıkı CSP (`default-src 'self'`).
- **CSRF:** uç nokta yalnızca JSON gövde kabul eder; başka sitedeki klasik bir HTML formu JSON gönderemez, CORS açık değil.
- **Gizli bilgi:** DB bilgisi `api/config.php`'de; dosya `.gitignore`'da ve `api/.htaccess` ile dışarıdan erişime kapalı. Repoda yalnızca `config.example.php` var.
- **Hata yönetimi:** SQL/PHP hata detayı kullanıcıya gösterilmez, `error_log` ile sunucu loguna yazılır.
- **Spam:** honeypot alanı + IP başına rate limit. IP düz saklanmaz, tuzlu SHA-256 özeti (`ip_hash`) tutulur; `X-Forwarded-For` sahte gönderilebildiği için kullanılmaz.
- **Çift gönderim:** gönderim sırasında buton kilitli; randevu çakışması DB `UNIQUE(meeting_date, meeting_slot)` kuralıyla eşzamanlı isteklerde de engelli.
- **Başlıklar** (kök `.htaccess`): HTTPS yönlendirmesi, HSTS, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, CSP; `X-Powered-By` gizli; dizin listeleme kapalı.

## Erişilebilirlik

- Her alan için gerçek `<label>`; hata metni `aria-describedby` ile alana bağlı, hatalı alanda `aria-invalid="true"`.
- Hata ve durum mesajları `aria-live`; gönderimde hata varsa **ilk hatalı alana odak**, başarıda durum kutusuna odak.
- Tüm işlemler klavyeyle yapılabilir; görünür odak halkası; "Talep formuna atla" bağlantısı.
- Randevu seçimi gerçek radio butonlarla (`fieldset` + `legend`); dolu saatler yalnızca renkle değil "dolu" yazısı ve `disabled` ile belirtilir.
- Metin kontrastı WCAG AA (4.5:1) üzerinde; `prefers-reduced-motion` destekli.

## Klasör yapısı

```
index.html, style.css, app.js     landing page + form
.htaccess                         HTTPS, güvenlik başlıkları, charset
api/submit.php                    talep kaydı (POST)
api/slots.php                     randevu saatleri (GET)
api/validate.php                  doğrulama kuralları (DB'siz, test edilebilir)
api/db.php                        JSON yanıt, config, PDO bağlantısı
api/.htaccess                     yalnızca submit.php ve slots.php dışarıya açık
api/config.example.php            config şablonu (gerçek config.php repoda yok)
schema.sql                        tablo tanımı
migrations/001_takvim.sql         eski tabloya randevu sütunlarını ekler
tests/                            test betikleri
AI_LOG.md                         AI kullanımı, kararlar, doğrulamalar
CLAUDE.md, BASLANGIC.md           planlama aşamasından AI'a verilen proje bağlamı
```

## Kurulum

### Sunucu (Hostinger veya herhangi bir PHP 8.1+ / MySQL barındırma)

1. **phpMyAdmin → SQL:** `schema.sql` içeriğini çalıştırın. (Tablo takvim sütunları olmadan önceden oluşturulduysa `migrations/001_takvim.sql`.)
2. **Dosya yöneticisi:** `index.html`, `style.css`, `app.js`, `.htaccess` ve `api/` klasörünü web köküne yükleyin.
3. `api/config.example.php` dosyasını `api/config.php` olarak kopyalayıp DB bilgilerini ve uzun rastgele bir `ip_salt` girin.
4. CSS/JS değiştiğinde `index.html`'deki `?v=` numarasını artırın (Hostinger CDN'i bu dosyaları 7 gün önbellekte tutuyor).

### Yerel

PHP 8.1+ (`mbstring`, `pdo_mysql`, canlı test için `openssl`) yeterli:

```bash
php -S 127.0.0.1:8000
```

Yerelde MySQL yoksa sayfa ve doğrulama çalışır, geçerli gönderim 500 döner (başarı mesajı gösterilmez).

## Testler

Bağımlılık yok (PHPUnit/Composer gerekmez). Proje klasöründe çalıştırılır:

```bash
php tests/validate_test.php
```
Doğrulama kuralları: 42 test (sınır değerler, Türkçe karakter, beyaz liste, tip karışıklığı, geçersiz UTF-8, randevu tarihi kuralları; "bugün" sabitlenerek).

```bash
php tests/http_test.php https://dogantemp.teknoparse.com
```
Uç nokta: 405, 400, 422, slots 405/422. `--insert` eklenirse canlı DB'ye kurgusal kayıt yazar ve 201, randevu 201 ve aynı saat için 409 dener.

```bash
php tests/security_test.php https://dogantemp.teknoparse.com
```
33 güvenlik kontrolü (gizli dosyalar, HTTPS, metot/biçim, CSRF, SQL injection, XSS yansıtma, hata sızıntısı, CORS, güvenlik başlıkları). DB'ye kayıt eklemez.

Son sonuçlar: `validate_test` 42/42 (yerel), `http_test --insert` 12/12 ve `security_test` 33/33 (canlı site).

## Bilinen sınırlar

- Rate limit kontrolü (SAY → EKLE) atomik değil; aynı anda gelen istekler sınırı birkaç kayıt aşabilir. Spam'i yavaşlatmak için yeterli görüldü. Randevu çakışması ise DB kuralıyla kesin engelli.
- Rate limit yalnızca başarılı kayıtları sayar; reddedilen istekler (400/422) sayılmaz.
- Talepleri görüntülemek için yönetim paneli yok; kayıtlar phpMyAdmin'den görülür.
- Randevu sonrası e-posta bildirimi gönderilmiyor.
- Eşzamanlı randevu (409) senaryosu sıralı istekle doğrulandı; eşzamanlı denemede rate limit (429) devreye girdi (ayrıntı: AI_LOG).
