<?php
declare(strict_types=1);

// Canlı site için temel güvenlik kontrolleri. Hiçbir test DB'ye kayıt eklemez
// (tüm istekler reddedilmesi gereken isteklerdir), bu yüzden tekrar tekrar çalıştırılabilir.
// Çalıştırma (PC PowerShell, proje klasöründe):
//   php tests/security_test.php https://dogantemp.teknoparse.com

$base = rtrim($argv[1] ?? 'https://dogantemp.teknoparse.com', '/');
$passed = 0;
$failed = 0;
$warnings = 0;

/** @return array{status:int, headers:array<string,string>, body:string} */
function http(string $url, string $method = 'GET', string $body = '', string $contentType = 'application/json', bool $follow = true): array
{
    $context = stream_context_create([
        'http' => [
            'method'          => $method,
            'header'          => "Content-Type: $contentType\r\nAccept: application/json\r\n",
            'content'         => $body,
            'ignore_errors'   => true,
            'follow_location' => $follow ? 1 : 0,
            'timeout'         => 20,
        ],
    ]);
    $response = @file_get_contents($url, false, $context);
    $status = 0;
    $headers = [];
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('#^HTTP/\S+ (\d{3})#', $line, $m)) {
            $status = (int) $m[1];
            $headers = []; // yönlendirmede son yanıtın başlıkları kalsın
        } elseif (str_contains($line, ':')) {
            [$k, $v] = explode(':', $line, 2);
            $headers[strtolower(trim($k))] = trim($v);
        }
    }
    return ['status' => $status, 'headers' => $headers, 'body' => (string) $response];
}

function check(string $title, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    printf("  %-5s %s%s\n", $ok ? 'OK' : 'HATA', $title, $detail !== '' ? "  ($detail)" : '');
}

function warn(string $title): void
{
    global $warnings;
    $warnings++;
    echo "  UYARI $title\n";
}

$submit = "$base/api/submit.php";
$slots = "$base/api/slots.php";
$valid = ['name' => 'Guvenlik Test', 'email' => 'sec@example.com', 'service' => 'danismanlik',
          'message' => 'Guvenlik testi, kurgusal veri.', 'website' => ''];

echo "Hedef: $base\n\n1) Gizli dosyalar ve dizinler dışarıdan okunamamalı\n";
$paths = ['api/config.php', 'api/db.php', 'api/validate.php', 'api/config.example.php', 'api/.htaccess',
          '.htaccess', '.git/config', '.env', 'schema.sql', 'migrations/001_takvim.sql', 'deploy.zip',
          'AI_LOG.md', 'tests/http_test.php', 'api/'];
foreach ($paths as $p) {
    $r = http("$base/$p");
    $leak = $r['status'] === 200 && preg_match('/(\$config|PDO|password|\'pass\'|\[core\]|CREATE TABLE)/i', $r['body']);
    check("/$p", !$leak && $r['status'] !== 200, "HTTP {$r['status']}");
}

echo "\n2) HTTPS\n";
$r = http(str_replace('https://', 'http://', $base) . '/', 'GET', '', 'text/plain', false);
check('HTTP → HTTPS yönlendirmesi', in_array($r['status'], [301, 308], true) && str_starts_with($r['headers']['location'] ?? '', 'https://'), "HTTP {$r['status']}");

echo "\n3) HTTP metodu ve istek biçimi\n";
foreach (['PUT', 'DELETE', 'PATCH'] as $m) {
    check("submit.php $m → 405", http($submit, $m, json_encode($valid))['status'] === 405);
}
// Klasik HTML formu (urlencoded) ile başka siteden POST: JSON olmadığı için reddedilmeli (CSRF koruması)
$r = http($submit, 'POST', http_build_query($valid), 'application/x-www-form-urlencoded');
check('form-urlencoded POST reddedilir (CSRF)', $r['status'] === 400, "HTTP {$r['status']}");
$r = http($submit, 'POST', json_encode(['message' => str_repeat('A', 1_000_000)] + $valid));
check('1 MB gövde reddedilir', in_array($r['status'], [400, 413], true), "HTTP {$r['status']}");
$r = http($submit, 'POST', '[1,2,3]');
check('JSON dizi (nesne değil) → 422/400', in_array($r['status'], [400, 422], true), "HTTP {$r['status']}");

echo "\n4) Enjeksiyon ve tip karışıklığı\n";
$r = http($slots . '?date=' . rawurlencode("2026-10-06' OR '1'='1"));
check('slots.php SQL injection tarihi → 422', $r['status'] === 422, "HTTP {$r['status']}");
$r = http($slots . '?date[]=2026-10-06');
check('slots.php date[] (dizi) → 422, PHP hatası yok', $r['status'] === 422 && !preg_match('/(Warning|Fatal|Stack trace|\.php on line)/', $r['body']), "HTTP {$r['status']}");
$r = http($submit, 'POST', json_encode(['name' => ['$ne' => ''], 'email' => true, 'service' => 1, 'message' => null]));
check('tip karışıklığı (dizi/bool/sayı/null) → 422', $r['status'] === 422, "HTTP {$r['status']}");
$xss = '<script>alert(1)</script>';
$r = http($submit, 'POST', json_encode(['name' => $xss, 'email' => $xss] + $valid));
check('422 yanıtı kullanıcı girdisini geri yansıtmaz (XSS)', $r['status'] === 422 && !str_contains($r['body'], '<script>'), "HTTP {$r['status']}");
$r = http($submit, 'POST', json_encode(['service' => 'danismanlik\' OR 1=1 --'] + $valid));
check('beyaz liste dışı hizmet (SQL) → 422', $r['status'] === 422, "HTTP {$r['status']}");

echo "\n5) Hata detayı sızmamalı\n";
$r = http($submit, 'POST', "{\"name\": \"\xff\xfe\"}");
check('bozuk UTF-8 → 400/422, iç hata yok', in_array($r['status'], [400, 422], true) && !preg_match('/(SQLSTATE|PDO|Stack trace|\.php on line)/', $r['body']), "HTTP {$r['status']}");
check('API yanıtı JSON + nosniff', str_contains($r['headers']['content-type'] ?? '', 'application/json') && ($r['headers']['x-content-type-options'] ?? '') === 'nosniff');
check('CORS açık değil (başka site JS ile okuyamaz)', !isset($r['headers']['access-control-allow-origin']));

echo "\n6) Güvenlik başlıkları (sayfa)\n";
$h = http("$base/")['headers'];
check('X-Frame-Options / frame-ancestors (clickjacking)', isset($h['x-frame-options']) || str_contains($h['content-security-policy'] ?? '', 'frame-ancestors'));
check('Strict-Transport-Security (HSTS)', isset($h['strict-transport-security']));
check('Referrer-Policy', isset($h['referrer-policy']));
check('X-Content-Type-Options: nosniff', ($h['x-content-type-options'] ?? '') === 'nosniff');
$api = http($submit)['headers'];
if (isset($api['x-powered-by'])) {
    warn("API X-Powered-By ile PHP sürümünü açıklıyor ({$api['x-powered-by']})");
}

echo "\nSonuç: $passed başarılı, $failed başarısız, $warnings uyarı\n";
exit($failed === 0 ? 0 : 1);
