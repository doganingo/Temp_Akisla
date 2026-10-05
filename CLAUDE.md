# Proje: Enteksis "alex" Aday Görevi (ALEX-24H-v1.0)

Bu dosya, claude.ai sohbetinde yapılan planlamanın özetidir. Claude Code bu dosyayı proje bağlamı olarak kullanır.

## Görev özeti
- Kurgusal bir teknoloji hizmeti için landing page + talep formu.
- Form kaydı sunucu tarafında KALICI saklanmalı.
- 24 saatlik teslim penceresi, hedef aktif emek 3–4 saat.
- AI kullanımı serbest; kararlar AI_LOG.md'de görünür olmalı.
- Mülakatta kodu kendi sözlerimle anlatıp küçük bir değişiklik yapacağım → her parçayı anlayarak ilerle, kısa açıklamalar yap.

## Teslimde istenenler
- Mobil ve masaüstü uyumlu landing page
- Form: isim, e-posta, hizmet seçimi, açıklama
- İstemci VE sunucu tarafında alan doğrulaması
- Gönderiliyor / başarı / hata durumları
- Test kaydının sunucuda kalıcı saklanması
- Başarı mesajı YALNIZCA kayıt gerçekten başarılıysa
- Canlı URL + incelemeye açık kaynak kod
- README.md, AI_LOG.md, teslim commit kimliği
- Yalnızca kurgusal test verisi

## Puanlama (100)
- Çalışan ürün ve gereksinimler: 25
- Kod, veri akışı ve temel güvenlik: 20
- AI ile üretim ve doğrulama: 20
- Kullanılabilirlik ve erişilebilirlik: 10
- Test, hata yönetimi ve teslim: 10
- Yazılı problem çözme: 10
- Geçmiş proje ve kişisel katkı: 5
- Görsel süsleme ayrı puan değil.

## Alınan kararlar
- Barındırma: Mevcut kişisel Hostinger web hosting (paylaşımlı, VPS değil), alt alan adı ile. Yeni harici hesap açılmayacak.
- Stack: Düz HTML/CSS/JS (build yok) + PHP + MySQL. (React opsiyoneldi; basitlik için düz JS tercih edildi.)
- Kaynak kod: GitHub public repo (başta private planlanmıştı; "incelemeye açık" şartı için public'e çevrildi). Repoya hassas bilgi girmez.

## Planlanan yapı
```
index.html        landing page + form
app.js            form durumları, istemci doğrulaması, fetch
style.css
api/submit.php    sunucu doğrulaması, MySQL'e yazma, JSON yanıt
api/config.php    DB bilgileri (.gitignore'da, repoya girmez)
config.example.php
schema.sql        tek tablo: requests
tests/            PHPUnit veya basit test betiği
README.md
AI_LOG.md
```

## Teknik kurallar
- DB erişimi: PDO + prepared statement.
- Uzunluk limitleri, e-posta formatı, hizmet seçimi beyaz listeden.
- Basit spam koruması: honeypot alanı (+ mümkünse basit rate limit).
- Gönderim sırasında buton kilitli (çift kayıt yok).
- Erişilebilirlik: gerçek <label>, hata mesajları aria-live, klavye ile tam kullanım, yeterli kontrast.
- Hata durumunda iç detay (SQL hatası vb.) kullanıcıya gösterilmez, loglanır.

## Kullanıcı tercihleri
- Yalnızca ücretsiz ve ticari kullanıma da uygun kütüphane/ürün/görsel. Dışında bir şey gerekirse **kalın** yazıp onay iste.
- Adım adım talimatlarda her komutun NEREDE çalıştırılacağını açıkça yaz (PC PowerShell, Hostinger hPanel, SSH vb.).
- İletişim dili: Türkçe.
- Commit mesajlarına "Co-Authored-By: Claude" satırı EKLENMEZ (AI kullanımı AI_LOG.md'de belgelenir).

## Zaman planı (sayaç başladıktan sonra)
1. 0:00–0:20 repo, klasör yapısı, AI_LOG ilk kayıt
2. 0:20–1:30 backend: submit.php, doğrulama, tablo
3. 1:30–2:30 frontend: sayfa + form + durumlar
4. 2:30–3:10 testler, Hostinger'a yükleme, canlıda deneme kaydı
5. 3:10–3:45 README, AI_LOG, son commit kimliği

## Not
Sayaç başlamadan kod yazılmayacak. Bu planlama sohbeti de AI_LOG.md'de "planlama aşaması" olarak belirtilmeli.
