<?php
// Bu dosyayı api/config.php adıyla kopyalayıp gerçek değerleri girin.
// api/config.php .gitignore'dadır; repoya girmez.

return [
    'db' => [
        'host' => 'localhost',
        'name' => 'veritabani_adi',
        'user' => 'kullanici_adi',
        'pass' => 'sifre',
    ],
    // IP adresleri DB'ye düz yazılmaz; bu tuzla SHA-256 özeti alınır.
    // Uzun, rastgele bir değer girin.
    'ip_salt' => 'buraya-uzun-rastgele-bir-deger',
    // Rate limit: aynı IP'den bu süre içinde en fazla bu kadar kayıt.
    'rate_limit' => [
        'max'     => 5,
        'minutes' => 10,
    ],
];
