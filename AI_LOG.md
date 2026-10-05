# AI_LOG

## Araçlar
- claude.ai (planlama), Claude Code (geliştirme)

## Planlama aşaması (sayaç öncesi)
- claude.ai ile görev analizi yapıldı. Benim kararlarım: Hostinger + PHP + MySQL (yeni hesap açmamak için), React yerine düz JS (basitlik için).
- Planlama sohbetinin özeti `CLAUDE.md` ve `BASLANGIC.md` dosyalarına yazıldı; Claude Code bu dosyaları proje bağlamı olarak kullandı.
- Claude Code ile ortam kontrolü yapıldı: PC'de Git kurulu, PHP yoktu → PHP 8.3 kuruldu (yerel test için). Hostinger PHP sürümü 8.3.
- Kurgusal hizmet: AI "Akışla" adını (küçük işletmeler için görev otomasyonu) ve 4 hizmet seçeneğini önerdi (otomasyon-kurulumu, entegrasyon, raporlama, danismanlik). Kararım: kabul.

## Kayıtlar
### 15:13 Sayaç başladı — repo ve klasör yapısı
- İstek: Git reposu, `.gitignore`, klasör yapısı, AI_LOG ilk kayıt.
- AI önerisi: `api/config.php` (DB şifresi) ve `api/data/` (rate limit çalışma verisi) `.gitignore`'a eklendi.
- Kararım: kabul.
- Doğrulama: `git status` ile config.php'nin takip edilmediği kontrol edilecek (config oluşturulduğunda).

### 15:15 Uygulama ve geliştirme ortamı kararları
- Uygulama: "Akışla" — küçük işletmeler için görev otomasyonu (kurgusal). Landing page + talep formu; kayıt Hostinger MySQL'de kalıcı.
- Yerel geliştirme: AI iki yol sundu — (A) PC'ye PHP kurup yerelde test etmek, (B) her denemeyi Hostinger'a yüklemek. Kararım: A, çünkü daha hızlı geri bildirim ve testleri yerelde çalıştırabilmek için.
- AI, yerel PHP'de `php.ini` oluşturup `mbstring` (Türkçe karakter uzunluğu için `mb_strlen`) ve `pdo_mysql` eklentilerini açtı. Doğrulama: `php -m` çıktısında ikisi de görünüyor.
- Yerelde MySQL yok: doğrulama testleri yerelde, veritabanına yazma canlı ortamda denenecek.
- Kaynak kod: GitHub repo, GitHub Desktop ile yayınlanacak.

### 15:18 Repo görünürlüğü: private → public
- Planda private repo + değerlendiriciye davet vardı. Kararım: public, çünkü görev "incelemeye açık kaynak kod" istiyor ve değerlendiricinin davet beklemesine gerek kalmıyor.
- Koşulum: GitHub'a hassas bilgi girmeyecek. AI kontrolü: repoda şifre, DB bilgisi, kişisel e-posta veya hosting hesabı detayı aranıp bulunmadı; DB bilgisi yalnızca `.gitignore`'daki `api/config.php`'de olacak.
- AI, commit yazar e-postasının public repoda görüneceğini hatırlattı ve GitHub noreply adresini önerdi. Kararım: mevcut e-posta kalsın (reddettim).

### 15:20 Veritabanı şeması ve sunucu tarafı (`schema.sql`, `api/`)
- İstek: Tek tablo + `submit.php` (yalnızca POST, sunucu doğrulaması, honeypot, rate limit, JSON yanıt).
- AI önerileri:
  - Doğrulama ayrı dosyada (`api/validate.php`, saf fonksiyon) → DB olmadan test edilebilir.
  - Rate limit için ayrı dosya yerine aynı tablo kullanıldı: son 10 dakikada aynı IP'den en fazla 5 kayıt. Bu yüzden `api/data/` `.gitignore`'dan çıkarıldı.
  - IP düz saklanmıyor; tuzlu SHA-256 özeti (`ip_hash`). `X-Forwarded-For` sahte gönderilebileceği için kullanılmadı.
  - Honeypot dolu gelirse 400 dönülüyor (sahte başarı verilmiyor), çünkü görev "başarı mesajı yalnızca kayıt gerçekten başarılıysa" diyor.
  - `api/.htaccess`: `submit.php` dışındaki PHP dosyaları doğrudan açılamaz.
- Kararım: kabul.
- Doğrulama (yerel, `php -S`): `php -l` hatasız. GET → 405, bozuk JSON → 400, honeypot → 400, hatalı alanlar → 422 + alan bazlı hatalar, geçerli veri → 500 (yerelde config/DB yok; kayıt olmadan başarı dönmediği görüldü). Canlı DB'de 201 testi sonra yapılacak.

### 15:27 Canlı veritabanı ve commit kuralı
- Hostinger'da MySQL veritabanı oluşturdum, `schema.sql`'i phpMyAdmin'de çalıştırdım.
- Kararım: commit mesajlarında Claude "Co-Authored-By" satırı olmayacak; AI katkısı bu dosyada belgeleniyor. Henüz push edilmemiş 4 commit'ten bu satır `git filter-branch --msg-filter` ile kaldırıldı (commit kimlikleri değişti).

### 15:28 Ön yüz (`index.html`, `style.css`, `app.js`)
- İstek: Landing page + form; gönderiliyor/başarı/hata durumları, istemci doğrulaması, erişilebilirlik.
- AI önerileri:
  - İstemci kuralları sunucudakiyle birebir aynı; karakter sayımı `Array.from(v).length` (PHP `mb_strlen` ile aynı sonuç, `v.length` emojiyi 2 sayar).
  - Başarı yalnızca `201` VE `ok: true` gelince gösteriliyor; JSON olmayan yanıt, ağ hatası ve 15 sn zaman aşımı hata durumuna düşüyor.
  - Gönderim sırasında buton `disabled` + `submitting` bayrağı (çift gönderim yok). Sunucunun 422 alan hataları da alanlara işleniyor.
  - Erişilebilirlik: gerçek `<label>`, `aria-invalid`, `aria-describedby`, hata ve durum mesajları `aria-live`, ilk hatalı alana odak, "forma atla" bağlantısı, görünür odak halkası.
  - Honeypot `display:none` yerine ekran dışında (bazı botlar gizli alanları atlıyor), `tabindex=-1` + `aria-hidden` ile klavye/ekran okuyucudan gizli.
- Kararım: kabul.
- Doğrulama (yerel tarayıcı): boş gönderimde 4 alan hatası + ilk alana odak; geçerli veriyle buton kilitlendi ve "Gönderiliyor…" göründü; yerelde DB olmadığı için 500 → hata mesajı gösterildi, form verisi korundu. 375 px mobil genişlikte yatay kaydırma yok.

### 15:35 Landing page zenginleştirme + responsive
- İsteğim: Sayfa yalnızca formdan ibaret kalmasın, daha dolu ve responsive olsun.
- AI önerisi: Hero'da örnek otomasyon akışı kartı, üç adımlı "Nasıl çalışır", ikonlu hizmet kartları, "önce/sonra" örnekleri, formun yanında "talepten sonra ne olur", `<details>` ile SSS, masaüstünde üst menü. Görseller resim dosyası değil, HTML/CSS ve elle yazılmış basit SVG (lisans sorunu yok, kütüphane yok). Kırılım noktaları: 700 px (tablet), 960 px (masaüstü). Form ve `app.js` değişmedi.
- Kararım: kabul.
- Doğrulama (yerel tarayıcı): 1280 px'te hero ve form iki sütun, hizmetler dört sütun; 375 px'te tek sütun, yatay kaydırma yok. Yapışkan üst barın bölüm başlıklarını kapattığı görüldü → `scroll-margin-top` ile düzeltildi.

### 15:48 Canlıya yükleme — engel ve çözüm
- AI, yüklenecek dosyaları `deploy.zip`'te topladı. İlk zip'te yollar Windows ters bölüsüyle (`api\submit.php`) yazılmıştı; Linux sunucuda klasör yerine tek dosya adı olarak açılabileceği fark edildi ve zip düz bölüyle (`api/submit.php`) yeniden üretildi.
- Kök `.htaccess` eklendi: HTTP → HTTPS yönlendirmesi, dizin listeleme kapalı.
- Sorun: Hostinger Dosya Yöneticisi'nde `config.php`'yi düzenleyip kaydederken bir kez **403 Forbidden (openresty)** alındı. AI, güvenlik duvarı engeli olabileceğini düşünüp dosyayı PC'de hazırlayıp yüklemeyi önerdi.
- Kararım: öneriyi uygulamadım; tekrar denediğimde kaydetme başarılı oldu (geçici hosting sorunu). AI'ın PC'de oluşturduğu `config.php` silindi.

### 15:49 Testler (`tests/`)
- AI önerisi: PHPUnit/Composer kurmak yerine bağımlılıksız iki betik (kurulum yükü yok, değerlendirici tek komutla çalıştırır).
  - `validate_test.php`: `validate_request()` için 28 test — sınır değerler (1/2, 100/101, 9/10, 2000/2001 karakter), Türkçe karakter sayımı, beyaz liste, dizi/sayı/geçersiz UTF-8 gibi beklenmeyen girdiler.
  - `http_test.php`: uç noktaya gerçek HTTP istekleri — 405, 400 (bozuk JSON, honeypot), 422 (alan bazlı hata); `--insert` ile canlıda 201.
- Kararım: kabul.
- Doğrulama: `php tests/validate_test.php` → 28/28; `php tests/http_test.php http://127.0.0.1:8000` → 5/5. (Canlı HTTPS testi için yerel PHP'de `openssl` eklentisi açıldı.)

### 15:57 Canlı doğrulama — https://dogantemp.teknoparse.com
- `php tests/http_test.php https://dogantemp.teknoparse.com --insert` → **6/6**: 405, 400 (bozuk JSON), 400 (honeypot), 422, 422 alan bazlı, **201 (kurgusal kayıt canlı DB'ye yazıldı)**.
- Erişim kontrolleri: `/api/config.php`, `/api/validate.php`, `/api/` → 403 (tarayıcıda da kontrol ettim). `http://` → 301 ile `https://`'e yönleniyor.
- AI'ın fark ettiği: sunucu HTML'i `Content-Type: text/html` olarak charset'siz gönderiyor (tarayıcı `<meta charset>` ile doğru gösteriyor, PowerShell bozuk okudu). Kök `.htaccess`'e `AddDefaultCharset UTF-8` eklendi.

### 16:04 Ön görüşme randevusu (takvim) — `takvim` dalı
- İsteğim: Forma takvim gibi bir işlev eklensin. Kararım: ayrı git dalında yapılsın; beğenmezsek ana dala döneriz.
- AI önerisi ve uygulama:
  - Randevu **isteğe bağlı** (görevin istediği 4 zorunlu alan değişmedi). Yarından itibaren 10 iş günü, saatler 10:00/11:00/14:00/15:00/16:00.
  - DB: `meeting_date`, `meeting_slot` sütunları + `UNIQUE(meeting_date, meeting_slot)`. Mevcut tablo için `migrations/001_takvim.sql`.
  - Çift rezervasyon önce SELECT ile değil **DB'nin UNIQUE kuralıyla** engelleniyor: iki kişi aynı anda gönderse de yalnızca biri yazılır, diğeri MySQL 1062 hatası → **409** + "saat az önce doldu" mesajı, istemci saat listesini yeniler.
  - `api/slots.php` (GET): seçilen günün saatlerinin dolu/boş durumu; kişisel veri döndürmez.
  - Ortak kod `api/db.php`'ye taşındı (JSON yanıt, config, PDO). `api/.htaccess` artık `submit.php` ve `slots.php` dışındakileri engelliyor.
  - Sunucu kuralı (`meeting_date_error`): tam `Y-m-d` biçimi (30 Şubat gibi taşan tarihler reddedilir), hafta içi, yarın…+14 gün, Europe/Istanbul saat dilimi. Testte sabit "bugün" verilebilsin diye `validate_request(..., $today)`.
  - Arayüz: gün/saat seçimleri gerçek radio butonlar (`fieldset/legend`), klavye ile oklarla gezilebilir; dolu saatler `disabled` + "dolu" yazısı (yalnızca renge dayanmıyor).
- Kararım: kabul.
- Doğrulama (yerel): `validate_test.php` 42/42 (14 yeni randevu testi), `http_test.php` 8/8 (slots 405/422, hafta sonu randevusu 422). Tarayıcıda saat yanıtı taklit edilerek: dolu saatler seçilemiyor, gün seçip saat seçmeden gönderince hata + saate odak. 201/409 testi canlıda migration sonrası yapılacak.

### 16:08 Takvim canlı doğrulama
- Migration'ı phpMyAdmin'de çalıştırdım, yeni dosyaları yükledim.
- `php tests/http_test.php https://dogantemp.teknoparse.com --insert` → **12/12**: randevu alındı (201), aynı saat ikinci kez **409**, slots 5 saat döndü. `api/db.php`, `api/validate.php`, `api/config.php` → 403.
- Eşzamanlılık denemesi (aynı boş saate aynı anda 2 istek, curl):
  - İlk denemede ikisi de 400 aldı: Git Bash Türkçe karakterleri bozuk kodladığı için JSON geçersizdi (sunucu doğru davrandı). ASCII veriyle tekrarlandı.
  - İkinci denemede istek 1 → 201, istek 2 → **429**: testlerle 10 dakikada 5 kayıt sınırına ulaşılmıştı, yani rate limit canlıda çalışıyor. Bu yüzden eşzamanlı 409 senaryosu bu denemede gözlenemedi; sıralı 409 testi geçti.
- Bilinen sınır: rate limit kontrolü (SELECT COUNT → INSERT) atomik değil; aynı anda gelen istekler sınırı birkaç kayıt aşabilir. Spam'i yavaşlatmak için yeterli görüldü; randevu çakışması ise UNIQUE kuralıyla kesin engelli.

### 16:11 Hata: canlıda takvim günleri görünmüyordu (CDN önbelleği)
- Belirti (benim fark ettiğim): localhost'ta gün/saat seçimi görünüyor, canlıda yalnızca başlık ve açıklama var.
- AI teşhisi: sunucudaki `app.js` güncel (`app.js?v=rastgele` yeni sürümü döndürüyor), ama Hostinger CDN'i (`Server: hcdn`, `Cache-Control: max-age=604800`) düz `app.js` için eski sürümü veriyor. `index.html` yenilendiği için kutu var, günleri dolduran JS eski.
- Çözüm: `index.html`'de `style.css?v=2` ve `app.js?v=2` (cache busting). CSS/JS her değiştiğinde bu numara artırılacak.

### 16:17 Güvenlik testleri (`tests/security_test.php`)
- İsteğim: canlı sitede güvenlik testleri. AI, DB'ye kayıt eklemeyen (yalnızca reddedilmesi gereken isteklerden oluşan) tekrar çalıştırılabilir bir betik yazdı.
- Kapsam: gizli dosyalar (config/db/validate, .htaccess, .git, .env, schema, deploy.zip, AI_LOG, tests), HTTP→HTTPS, PUT/DELETE/PATCH → 405, form-urlencoded POST (CSRF) → 400, 1 MB gövde → 400, SQL injection (slots tarih, hizmet), `date[]` dizi, tip karışıklığı, XSS yansıtma, bozuk UTF-8, hata detayı sızıntısı, CORS, güvenlik başlıkları.
- İlk sonuç: **29/33**. Bulunan eksikler: X-Frame-Options, HSTS, Referrer-Policy, sayfada nosniff yok; API `X-Powered-By: PHP/8.3.33` ile sürüm açıklıyor (uyarı).
- Düzeltme: kök `.htaccess`'e güvenlik başlıkları + sıkı CSP (`default-src 'self'`; sayfada satır içi script/stil olmadığı grep ile doğrulandı), `X-Powered-By` kaldırıldı (`.htaccess` + `db.php`'de `header_remove`).
- 16:24 Düzeltilmiş `.htaccess` ve `api/db.php`'yi yükledim. Tekrar test: `security_test.php` **33/33**, `http_test.php` 8/8. Tarayıcıda CSP sonrası kontrol: CSS yükleniyor, takvim günleri ve saatleri geliyor, konsolda hata yok.

### 16:28 README ve commit geçmişini toparlama
- AI, README.md'yi yazdı: canlı URL, gereksinim → dosya eşlemesi, veri akışı, HTTP kodları, doğrulama kuralları, güvenlik ve erişilebilirlik kararları, kurulum, test komutları ve sonuçları, bilinen sınırlar.
- Kararım: `takvim` dalı `main`'e alındı (canlıda bu sürüm çalışıyor). 16 küçük commit, GitHub'a gönderilmeden önce 5 konu bazlı commit'e toplandı (iskelet, sunucu, ön yüz, testler, dokümantasyon). Adım adım zaman çizelgesi bu dosyada korunuyor.
