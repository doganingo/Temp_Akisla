<?php
declare(strict_types=1);

require __DIR__ . '/db.php';
require __DIR__ . '/validate.php';

// 1) Yalnızca POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, ['ok' => false, 'message' => 'Yalnızca POST isteği kabul edilir.']);
}

// 2) Gövde: JSON, makul boyutta
$raw = file_get_contents('php://input', false, null, 0, 20000);
$input = json_decode($raw === false ? '' : $raw, true);
if (!is_array($input)) {
    respond(400, ['ok' => false, 'message' => 'İstek biçimi geçersiz.']);
}

// 3) Honeypot: gerçek kullanıcı bu gizli alanı görmez ve doldurmaz.
//    Başarı mesajı yalnızca gerçek kayıtta verildiği için bot da hata alır.
$honeypot = $input['website'] ?? '';
if (!is_string($honeypot) || trim($honeypot) !== '') {
    error_log('[akisla] honeypot dolu, istek reddedildi');
    respond(400, ['ok' => false, 'message' => 'Talep gönderilemedi.']);
}

// 4) Sunucu doğrulaması (asıl güvence burası; istemci doğrulaması yalnızca kullanıcı deneyimi için)
$result = validate_request($input);
if ($result['errors'] !== []) {
    respond(422, [
        'ok'      => false,
        'message' => 'Lütfen işaretli alanları düzeltin.',
        'errors'  => $result['errors'],
    ]);
}
$data = $result['data'];

// 5) Veritabanı: rate limit kontrolü + kayıt
try {
    $config = app_config();
    $pdo = db_connect($config);

    // IP düz saklanmaz; tuzlu özetle aynı kişiden gelen istekler sayılır.
    // X-Forwarded-For kullanılmaz: istemci tarafından sahte gönderilebilir.
    $ipHash = hash('sha256', $config['ip_salt'] . ($_SERVER['REMOTE_ADDR'] ?? ''));
    $limit = $config['rate_limit'] ?? ['max' => 5, 'minutes' => 10];

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM requests
         WHERE ip_hash = ? AND created_at > (NOW() - INTERVAL ? MINUTE)'
    );
    $stmt->execute([$ipHash, (int) $limit['minutes']]);
    if ((int) $stmt->fetchColumn() >= (int) $limit['max']) {
        header('Retry-After: ' . ((int) $limit['minutes'] * 60));
        respond(429, ['ok' => false, 'message' => 'Çok fazla talep gönderildi. Lütfen daha sonra tekrar deneyin.']);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO requests (name, email, service, message, meeting_date, meeting_slot, ip_hash)
         VALUES (:name, :email, :service, :message, :meeting_date, :meeting_slot, :ip_hash)'
    );
    $stmt->execute([
        ':name'         => $data['name'],
        ':email'        => $data['email'],
        ':service'      => $data['service'],
        ':message'      => $data['message'],
        ':meeting_date' => $data['meeting_date'] !== '' ? $data['meeting_date'] : null,
        ':meeting_slot' => $data['meeting_slot'] !== '' ? $data['meeting_slot'] : null,
        ':ip_hash'      => $ipHash,
    ]);
    $id = (int) $pdo->lastInsertId();
} catch (PDOException $e) {
    // 1062 = UNIQUE ihlali: aynı gün + saat bu arada başka biri tarafından alındı.
    // Kontrol ayrı bir SELECT ile değil DB kuralıyla yapıldığı için eşzamanlı iki istekte de tek kayıt kalır.
    if (($e->errorInfo[1] ?? null) === 1062) {
        respond(409, [
            'ok'      => false,
            'message' => 'Seçtiğiniz görüşme saati az önce doldu. Lütfen başka bir saat seçin.',
            'errors'  => ['meeting' => 'Bu saat dolu, başka bir saat seçin.'],
        ]);
    }
    error_log('[akisla] DB hatası: ' . $e->getMessage());
    respond(500, ['ok' => false, 'message' => GENERIC_ERROR]);
} catch (Throwable $e) {
    error_log('[akisla] hata: ' . $e->getMessage());
    respond(500, ['ok' => false, 'message' => GENERIC_ERROR]);
}

// 6) Buraya yalnızca INSERT başarılıysa gelinir.
respond(201, ['ok' => true, 'message' => 'Talebiniz alındı. En kısa sürede size dönüş yapacağız.', 'id' => $id]);
