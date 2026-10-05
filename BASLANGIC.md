# BAŞLANGIÇ: Claude Code için devir notu

> Claude Code: Bu dosyayı baştan sona oku, sonra aşağıdaki "İlk yanıtın" bölümündeki gibi cevap ver. Kod yazmaya kullanıcı onay vermeden başlama.

## 1. Bağlam
Kullanıcı: Dogan, bilgisayar mühendisi, ~1.5 yıl deneyim. Türkçe konuşuyoruz.
Enteksis şirketinin "alex Aday Merkezi" üzerinden verdiği 24 saatlik uygulama görevini yapıyoruz (görev sürümü ALEX-24H-v1.0).
Planlama claude.ai sohbetinde yapıldı; kararların tamamı aşağıda. Ayrıntılı kurallar için `CLAUDE.md` dosyasına da bak.

## 2. Görev (kısaca)
Kurgusal bir teknoloji hizmeti (ör. küçük işletmeler için görev otomasyonu) için:
- Mobil + masaüstü uyumlu bir landing page
- İsim, e-posta, hizmet seçimi, açıklama alanlı bir talep formu
- İstemci ve sunucu tarafında doğrulama
- Gönderiliyor / başarı / hata durumları
- Kaydın sunucuda KALICI saklanması; başarı mesajı yalnızca kayıt gerçekten başarılıysa
- Canlı URL, incelemeye açık kaynak kod, README.md, AI_LOG.md, teslim commit kimliği
- Yalnızca kurgusal test verisi

Puanlama: Çalışan ürün 25 · Kod/veri akışı/güvenlik 20 · AI ile üretim ve doğrulama 20 · Kullanılabilirlik/erişilebilirlik 10 · Test/hata yönetimi/teslim 10 · Yazılı problem çözme 10 · Geçmiş katkı 5. Görsel süsleme puan getirmez.

Sonraki adımda mülakat var: Dogan kodu kendi sözleriyle anlatacak ve canlı küçük bir değişiklik yapacak.

## 3. Alınan kararlar (değiştirme, önce sor)
- **Barındırma:** Dogan'ın kişisel Hostinger web hosting'i (paylaşımlı, VPS değil), bir alt alan adı üzerinde. Yeni harici hesap açılmayacak.
- **Stack:** Düz HTML + CSS + JavaScript (build yok) + PHP + MySQL.
- **Kaynak kod:** GitHub private repo; değerlendiriciye erişim verilecek.
- **Test:** PHPUnit veya basit bir PHP test betiği.

## 4. Çalışma şeklin
1. **Küçük adımlar.** Her adımda önce ne yapacağını 1–2 cümleyle söyle, sonra yap.
2. **Öğreterek ilerle.** Yazdığın her dosyadan sonra 3–5 maddeyle "bu kod ne yapıyor, neden böyle" açıkla. Dogan mülakatta bunu anlatabilmeli.
3. **Komutların yerini belirt.** Her komutta nerede çalışacağını yaz: `PC PowerShell`, `Hostinger hPanel`, `Hostinger SSH`, `tarayıcı` vb.
4. **Lisans kuralı.** Yalnızca ücretsiz ve ticari kullanıma da uygun kütüphane/araç/görsel kullan. Bunun dışında bir şey gerekirse **kalın** yaz ve onay iste.
5. **AI_LOG'u sürekli güncelle.** Her önemli adımdan sonra AI_LOG.md'ye kısa bir kayıt ekle (aşağıdaki şablon). Dogan'ın kendi kararlarını ve değiştirdiği önerileri ayrıca belirt. Hata bulunmadıysa hata uydurma.
6. **Gizli bilgi yok.** DB şifresi asla repoya girmez; `api/config.php` `.gitignore`'da, repoda `config.example.php` bulunur.
7. **Abartma.** Gereksiz kütüphane, framework veya süs ekleme. Basit, okunur, savunulabilir kod.

## 5. Teknik kurallar
- PDO + prepared statement; `utf8mb4`.
- Sunucu doğrulaması: isim 2–100 karakter, geçerli e-posta, hizmet sabit bir beyaz listeden, açıklama 10–2000 karakter.
- Honeypot alanı; mümkünse IP başına basit rate limit.
- Yalnızca POST kabul et; JSON yanıt ve doğru HTTP durum kodları (200/201, 400/422, 405, 429, 500).
- İç hata detayı (SQL hatası vb.) kullanıcıya gösterilmez, sunucu loguna yazılır.
- Gönderim sırasında buton kilitli; çift gönderim yok.
- Erişilebilirlik: gerçek `<label>`, `aria-invalid`, hata mesajları `aria-live`, klavyeyle tam kullanım, yeterli kontrast, ilk hatalı alana odaklanma.
- İstemci doğrulaması kullanıcı deneyimi içindir; asıl güvence sunucu doğrulamasıdır.

## 6. Hedef klasör yapısı
```
index.html
style.css
app.js
api/submit.php
api/config.php          (.gitignore)
api/config.example.php
schema.sql
tests/
README.md
AI_LOG.md
CLAUDE.md
.gitignore
```

## 7. Sıra (sayaç başladıktan sonra)
1. Repo, `.gitignore`, klasör yapısı, AI_LOG ilk kayıt (~20 dk)
2. `schema.sql` + `api/submit.php` + doğrulama (~70 dk)
3. `index.html` + `style.css` + `app.js`, form durumları (~60 dk)
4. Testler; Hostinger'da veritabanı ve dosya yükleme; canlıda deneme kaydı (~40 dk)
5. README, AI_LOG'un son hali, son commit kimliği (~35 dk)

**Önemli:** Dogan 24 saatlik sayacı başlattığını söylemeden kod yazma. Başlatmadıysa yalnızca ortam kontrolü yap (Node gerekmez; PHP/Git kurulu mu, Hostinger PHP sürümü kaç).

## 8. AI_LOG.md şablonu
```markdown
# AI_LOG

## Araçlar
- claude.ai (planlama), Claude Code (geliştirme)

## Planlama aşaması (sayaç öncesi)
- claude.ai ile görev analizi yapıldı. Benim kararlarım: Hostinger + PHP + MySQL (yeni hesap açmamak için), React yerine düz JS (basitlik için).

## Kayıtlar
### [saat] Başlık
- İstek: ...
- AI önerisi: ...
- Kararım: kabul / değiştirdim / reddettim, çünkü ...
- Doğrulama: nasıl test ettim, sonuç ...
```

## 9. İlk yanıtın
Bu dosyayı okuduktan sonra:
1. Planı 5–6 maddede Türkçe özetle.
2. Belirsiz gördüğün en fazla 2 noktayı sor (ör. kurgusal hizmetin adı, hizmet seçenekleri).
3. Dogan'a sayacı başlatıp başlatmadığını sor.
