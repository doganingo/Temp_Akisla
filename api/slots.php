<?php
declare(strict_types=1);

// Seçilen gün için ön görüşme saatlerinin dolu/boş durumunu döndürür.
// GET /api/slots.php?date=2026-10-06
// Yalnızca saat bilgisi döner; kimin aldığı gibi kişisel veri dönmez.

require __DIR__ . '/db.php';
require __DIR__ . '/validate.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    respond(405, ['ok' => false, 'message' => 'Yalnızca GET isteği kabul edilir.']);
}

$date = $_GET['date'] ?? '';
$date = is_string($date) ? trim($date) : '';
$error = meeting_date_error($date, meeting_today());
if ($error !== null) {
    respond(422, ['ok' => false, 'message' => $error]);
}

try {
    $pdo = db_connect(app_config());
    $stmt = $pdo->prepare('SELECT meeting_slot FROM requests WHERE meeting_date = ?');
    $stmt->execute([$date]);
    $taken = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    error_log('[akisla] slots hatası: ' . $e->getMessage());
    respond(500, ['ok' => false, 'message' => 'Uygun saatler şu anda alınamadı.']);
}

$slots = array_map(
    fn (string $time) => ['time' => $time, 'available' => !in_array($time, $taken, true)],
    MEETING_SLOTS
);
respond(200, ['ok' => true, 'date' => $date, 'slots' => $slots]);
