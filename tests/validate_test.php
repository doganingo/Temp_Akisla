<?php
declare(strict_types=1);

// Sunucu doğrulaması için basit test betiği (PHPUnit gerektirmez).
// Çalıştırma (PC PowerShell, proje klasöründe):  php tests/validate_test.php

require __DIR__ . '/../api/validate.php';

$passed = 0;
$failed = 0;

function check(string $title, bool $condition): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  OK    $title\n";
    } else {
        $failed++;
        echo "  HATA  $title\n";
    }
}

// Geçerli bir temel veri; her test yalnızca bir alanı bozar.
function valid(array $override = []): array
{
    return array_merge([
        'name'    => 'Ayşe Yılmaz',
        'email'   => 'ayse@example.com',
        'service' => 'entegrasyon',
        'message' => 'Kurgusal deneme mesajı, test verisi.',
    ], $override);
}

function errors(array $input): array
{
    return validate_request($input)['errors'];
}

echo "Geçerli veri\n";
check('tüm alanlar geçerliyse hata yok', errors(valid()) === []);
check('baş/son boşluklar kırpılır', validate_request(valid(['name' => '  Ali Veli  ']))['data']['name'] === 'Ali Veli');
check('her hizmet değeri kabul edilir', array_reduce(
    array_keys(SERVICES),
    fn ($ok, $s) => $ok && errors(valid(['service' => $s])) === [],
    true
));

echo "İsim\n";
check('boş isim reddedilir', isset(errors(valid(['name' => '']))['name']));
check('1 karakter reddedilir', isset(errors(valid(['name' => 'A']))['name']));
check('2 karakter kabul edilir', !isset(errors(valid(['name' => 'Al']))['name']));
check('100 karakter kabul edilir', !isset(errors(valid(['name' => str_repeat('a', 100)]))['name']));
check('101 karakter reddedilir', isset(errors(valid(['name' => str_repeat('a', 101)]))['name']));
check('Türkçe karakter 1 sayılır (100 x "ş" kabul)', !isset(errors(valid(['name' => str_repeat('ş', 100)]))['name']));
check('yalnızca boşluk reddedilir', isset(errors(valid(['name' => '     ']))['name']));
check('satır sonu içeren isim reddedilir', isset(errors(valid(['name' => "Ali\nVeli"]))['name']));

echo "E-posta\n";
check('boş e-posta reddedilir', isset(errors(valid(['email' => '']))['email']));
check('@ olmayan reddedilir', isset(errors(valid(['email' => 'ayse.example.com']))['email']));
check('alan adı olmayan reddedilir', isset(errors(valid(['email' => 'ayse@']))['email']));
check('254 karakterden uzun reddedilir', isset(errors(valid(['email' => str_repeat('a', 250) . '@example.com']))['email']));

echo "Hizmet\n";
check('boş hizmet reddedilir', isset(errors(valid(['service' => '']))['service']));
check('listede olmayan hizmet reddedilir', isset(errors(valid(['service' => 'hack']))['service']));
check('kullanıcıya görünen ad (değer değil) reddedilir', isset(errors(valid(['service' => 'Danışmanlık']))['service']));

echo "Açıklama\n";
check('9 karakter reddedilir', isset(errors(valid(['message' => str_repeat('a', 9)]))['message']));
check('10 karakter kabul edilir', !isset(errors(valid(['message' => str_repeat('a', 10)]))['message']));
check('2000 karakter kabul edilir', !isset(errors(valid(['message' => str_repeat('a', 2000)]))['message']));
check('2001 karakter reddedilir', isset(errors(valid(['message' => str_repeat('a', 2001)]))['message']));

echo "Kötü niyetli / beklenmeyen girdi\n";
check('alanlar hiç yoksa 4 hata', count(errors([])) === 4);
check('dizi gönderilen alan boş sayılır', isset(errors(valid(['name' => ['a', 'b']]))['name']));
check('sayı gönderilen alan boş sayılır', isset(errors(valid(['service' => 123]))['service']));
check('geçersiz UTF-8 reddedilir', isset(errors(valid(['name' => "Ali\xC3\x28"]))['name']));
check('fazladan alan veriye girmez', !array_key_exists('is_admin', validate_request(valid(['is_admin' => '1']))['data']));
check('HTML olduğu gibi saklanır (çıktıda kaçışlanmalı)', validate_request(valid(['message' => '<b>kalın yazı</b> deneme']))['data']['message'] === '<b>kalın yazı</b> deneme');

echo "Ön görüşme randevusu (sabit 'bugün': 2026-10-05 Pazartesi)\n";
$today = new DateTimeImmutable('2026-10-05', new DateTimeZone(MEETING_TIMEZONE));
$meeting = fn (string $date, string $slot) => validate_request(valid(['meeting_date' => $date, 'meeting_slot' => $slot]), $today);
check('randevusuz talep geçerli', $meeting('', '')['errors'] === []);
check('yarın (Salı) + geçerli saat kabul', $meeting('2026-10-06', '10:00')['errors'] === []);
check('bugün reddedilir', isset($meeting('2026-10-05', '10:00')['errors']['meeting']));
check('geçmiş gün reddedilir', isset($meeting('2026-10-01', '10:00')['errors']['meeting']));
check('Cumartesi reddedilir', isset($meeting('2026-10-10', '10:00')['errors']['meeting']));
check('Pazar reddedilir', isset($meeting('2026-10-11', '10:00')['errors']['meeting']));
check('14. gün (Pazartesi) kabul', $meeting('2026-10-19', '10:00')['errors'] === []);
check('15. gün reddedilir', isset($meeting('2026-10-20', '10:00')['errors']['meeting']));
check('olmayan tarih (30 Şubat) reddedilir', isset($meeting('2027-02-30', '10:00')['errors']['meeting']));
check('yanlış biçim (06.10.2026) reddedilir', isset($meeting('06.10.2026', '10:00')['errors']['meeting']));
check('listede olmayan saat reddedilir', isset($meeting('2026-10-06', '12:00')['errors']['meeting']));
check('yalnızca gün seçilirse reddedilir', isset($meeting('2026-10-06', '')['errors']['meeting']));
check('yalnızca saat seçilirse reddedilir', isset($meeting('', '10:00')['errors']['meeting']));
check('SQL benzeri girdi reddedilir', isset($meeting("2026-10-06' OR 1=1 --", '10:00')['errors']['meeting']));

echo "\nSonuç: $passed başarılı, $failed başarısız\n";
exit($failed === 0 ? 0 : 1);
