<?php
declare(strict_types=1);

// Hizmet beyaz listesi: değer => kullanıcının gördüğü ad.
// Formdaki <select> seçenekleri bununla aynı olmalı.
const SERVICES = [
    'otomasyon-kurulumu' => 'Otomasyon kurulumu',
    'entegrasyon'        => 'Uygulama entegrasyonu',
    'raporlama'          => 'Raporlama paneli',
    'danismanlik'        => 'Danışmanlık',
];

// Ön görüşme saatleri (30 dk). Formdaki saat seçenekleri bununla aynı olmalı.
const MEETING_SLOTS = ['10:00', '11:00', '14:00', '15:00', '16:00'];
const MEETING_MAX_DAYS = 14;      // bugünden en fazla kaç gün sonrası
const MEETING_TIMEZONE = 'Europe/Istanbul';

function meeting_today(): DateTimeImmutable
{
    return new DateTimeImmutable('today', new DateTimeZone(MEETING_TIMEZONE));
}

/**
 * Randevu gününü kontrol eder: YYYY-AA-GG, hafta içi, yarından itibaren 14 gün içinde.
 * Hata yoksa null döner. $today parametresi testlerde sabit tarih vermek için.
 */
function meeting_date_error(string $date, DateTimeImmutable $today): ?string
{
    $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $today->getTimezone());
    // Biçim tam eşleşmeli: '2026-02-30' gibi taşan tarihler format() ile farklı çıkar.
    if ($day === false || $day->format('Y-m-d') !== $date) {
        return 'Geçerli bir gün seçin.';
    }
    if ((int) $day->format('N') > 5) {
        return 'Görüşmeler yalnızca hafta içi yapılır.';
    }
    if ($day <= $today || $day > $today->modify('+' . MEETING_MAX_DAYS . ' days')) {
        return 'Yarından itibaren ' . MEETING_MAX_DAYS . ' gün içinde bir gün seçin.';
    }
    return null;
}

/**
 * Form verisini doğrular ve temizler.
 * Veritabanına dokunmaz; bu yüzden tek başına test edilebilir.
 *
 * @return array{data: array<string,string>, errors: array<string,string>}
 */
function validate_request(array $input, ?DateTimeImmutable $today = null): array
{
    // Beklenen alanlar dışında gelenler yok sayılır; string olmayan değerler boş kabul edilir.
    $field = static function (string $key) use ($input): string {
        $value = $input[$key] ?? '';
        return is_string($value) ? trim($value) : '';
    };

    $data = [
        'name'    => $field('name'),
        'email'   => $field('email'),
        'service' => $field('service'),
        'message' => $field('message'),
    ];
    $errors = [];

    foreach ($data as $key => $value) {
        if (!mb_check_encoding($value, 'UTF-8')) {
            $errors[$key] = 'Geçersiz karakter içeriyor.';
        }
    }

    $nameLen = mb_strlen($data['name']);
    if (!isset($errors['name'])) {
        if ($nameLen < 2 || $nameLen > 100) {
            $errors['name'] = 'İsim 2–100 karakter olmalı.';
        } elseif (preg_match('/[\x00-\x1F\x7F]/u', $data['name'])) {
            $errors['name'] = 'İsim geçersiz karakter içeriyor.';
        }
    }

    if (!isset($errors['email'])) {
        if ($data['email'] === '') {
            $errors['email'] = 'E-posta zorunlu.';
        } elseif (strlen($data['email']) > 254 || filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Geçerli bir e-posta adresi girin.';
        }
    }

    if (!isset($errors['service']) && !array_key_exists($data['service'], SERVICES)) {
        $errors['service'] = 'Listeden bir hizmet seçin.';
    }

    $messageLen = mb_strlen($data['message']);
    if (!isset($errors['message']) && ($messageLen < 10 || $messageLen > 2000)) {
        $errors['message'] = 'Açıklama 10–2000 karakter olmalı.';
    }

    // Ön görüşme isteğe bağlı: ikisi de boş olabilir; biri doluysa ikisi de geçerli olmalı.
    $data['meeting_date'] = $field('meeting_date');
    $data['meeting_slot'] = $field('meeting_slot');
    if ($data['meeting_date'] !== '' || $data['meeting_slot'] !== '') {
        if ($data['meeting_date'] === '' || $data['meeting_slot'] === '') {
            $errors['meeting'] = 'Görüşme için hem gün hem saat seçin.';
        } elseif (($dateError = meeting_date_error($data['meeting_date'], $today ?? meeting_today())) !== null) {
            $errors['meeting'] = $dateError;
        } elseif (!in_array($data['meeting_slot'], MEETING_SLOTS, true)) {
            $errors['meeting'] = 'Listeden bir saat seçin.';
        }
    }

    return ['data' => $data, 'errors' => $errors];
}
