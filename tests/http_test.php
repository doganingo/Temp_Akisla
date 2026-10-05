<?php
declare(strict_types=1);

// api/submit.php uç noktasını gerçek HTTP istekleriyle dener.
// Çalıştırma (PC PowerShell, proje klasöründe):
//   php tests/http_test.php http://127.0.0.1:8000             (yerel; DB yok → geçerli kayıt 500 beklenir)
//   php tests/http_test.php https://dogantemp.teknoparse.com --insert
//       --insert: bir kurgusal kayıt gönderir ve 201 bekler (canlı DB'ye 1 satır yazar).

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8000', '/');
$insert = in_array('--insert', $argv, true);
$url = $base . '/api/submit.php';

$passed = 0;
$failed = 0;

/** @return array{0:int,1:?array} [HTTP kodu, çözülmüş JSON] */
function request(string $url, string $method, ?string $body = null): array
{
    $context = stream_context_create(['http' => [
        'method'        => $method,
        'header'        => "Content-Type: application/json\r\nAccept: application/json\r\n",
        'content'       => $body ?? '',
        'ignore_errors' => true, // 4xx/5xx yanıt gövdesini de oku
        'timeout'       => 15,
    ]]);
    $response = @file_get_contents($url, false, $context);
    $status = 0;
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('#^HTTP/\S+ (\d{3})#', $line, $m)) {
            $status = (int) $m[1]; // yönlendirme varsa son kod kalır
        }
    }
    return [$status, json_decode((string) $response, true)];
}

function expect(string $title, int $want, array $result): void
{
    global $passed, $failed;
    [$status, $json] = $result;
    $ok = $status === $want && is_array($json) && isset($json['ok']) && ($json['ok'] === ($want === 201));
    $ok ? $passed++ : $failed++;
    printf("  %-5s %-40s beklenen %d, gelen %d\n", $ok ? 'OK' : 'HATA', $title, $want, $status);
}

function payload(array $override = []): string
{
    return json_encode(array_merge([
        'name'    => 'Test Kullanıcı',
        'email'   => 'test@example.com',
        'service' => 'danismanlik',
        'message' => 'Otomatik HTTP testi — kurgusal veri.',
        'website' => '',
    ], $override), JSON_UNESCAPED_UNICODE);
}

echo "Hedef: $url\n";
expect('GET reddedilir', 405, request($url, 'GET'));
expect('bozuk JSON reddedilir', 400, request($url, 'POST', '{bozuk'));
expect('honeypot dolu reddedilir', 400, request($url, 'POST', payload(['website' => 'http://spam.example'])));
expect('geçersiz alanlar 422', 422, request($url, 'POST', payload(['email' => 'yanlis', 'service' => 'hack'])));

$invalid = request($url, 'POST', payload(['name' => 'A']));
$fieldOk = isset($invalid[1]['errors']['name']) && !isset($invalid[1]['errors']['email']);
$fieldOk ? $passed++ : $failed++;
printf("  %-5s %s\n", $fieldOk ? 'OK' : 'HATA', '422 yanıtı yalnızca hatalı alanı içerir');

// Ön görüşme saatleri uç noktası
$slotsUrl = $base . '/api/slots.php';
$tomorrow = new DateTimeImmutable('tomorrow', new DateTimeZone('Europe/Istanbul'));
while ((int) $tomorrow->format('N') > 5) {
    $tomorrow = $tomorrow->modify('+1 day'); // ilk hafta içi gün
}
$day = $tomorrow->format('Y-m-d');
expect('slots: POST reddedilir', 405, request($slotsUrl . '?date=' . $day, 'POST'));
expect('slots: geçersiz tarih 422', 422, request($slotsUrl . '?date=2020-01-01', 'GET'));
expect('randevu: hafta sonu 422', 422, request($url, 'POST', payload(['meeting_date' => '2020-01-04', 'meeting_slot' => '10:00'])));

if ($insert) {
    expect('geçerli kayıt oluşturulur', 201, request($url, 'POST', payload()));

    $slots = request($slotsUrl . '?date=' . $day, 'GET');
    $slotsOk = $slots[0] === 200 && count($slots[1]['slots'] ?? []) === 5;
    $slotsOk ? $passed++ : $failed++;
    printf("  %-5s %-40s gelen %d\n", $slotsOk ? 'OK' : 'HATA', "slots: $day için 5 saat döner", $slots[0]);

    $free = null;
    foreach ($slots[1]['slots'] ?? [] as $slot) {
        if ($slot['available']) {
            $free = $slot['time'];
            break;
        }
    }
    if ($free === null) {
        echo "  (atlandı) $day günü dolu, randevu testi yapılamadı\n";
    } else {
        $booking = payload(['meeting_date' => $day, 'meeting_slot' => $free]);
        expect("randevu $day $free alınır", 201, request($url, 'POST', $booking));
        expect('aynı saat ikinci kez 409', 409, request($url, 'POST', $booking));
    }
} else {
    echo "  (atlandı) kayıt ve randevu testleri — canlı DB'ye yazmak için --insert ekleyin\n";
}

echo "\nSonuç: $passed başarılı, $failed başarısız\n";
exit($failed === 0 ? 0 : 1);
