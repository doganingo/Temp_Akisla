<?php
declare(strict_types=1);

// submit.php ve slots.php'nin ortak yardımcıları: JSON yanıt, yapılandırma, DB bağlantısı.

header_remove('X-Powered-By'); // PHP sürümü açıklanmasın
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

// Kullanıcıya gösterilen genel hata; iç detay yalnızca sunucu loguna yazılır.
const GENERIC_ERROR = 'Talebiniz şu anda kaydedilemedi. Lütfen biraz sonra tekrar deneyin.';

/** JSON yanıt gönderir ve betiği bitirir. */
function respond(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

/** api/config.php'yi yükler; yoksa istisna fırlatır (çağıran 500'e çevirir). */
function app_config(): array
{
    $file = __DIR__ . '/config.php';
    if (!is_file($file)) {
        throw new RuntimeException('api/config.php bulunamadı');
    }
    return require $file;
}

function db_connect(array $config): PDO
{
    $db = $config['db'];
    return new PDO(
        "mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4",
        $db['user'],
        $db['pass'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
}
