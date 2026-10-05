-- Akışla talep formu: tek tablo
-- Hostinger hPanel > Veritabanları > phpMyAdmin > SQL sekmesinde çalıştırılır.
-- (Tablo daha önce takvim sütunları olmadan oluşturulduysa: migrations/001_takvim.sql)

CREATE TABLE IF NOT EXISTS requests (
    id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    name         VARCHAR(100)  NOT NULL,
    email        VARCHAR(254)  NOT NULL,
    service      VARCHAR(40)   NOT NULL,
    message      VARCHAR(2000) NOT NULL,
    meeting_date DATE          NULL,      -- isteğe bağlı ön görüşme günü
    meeting_slot CHAR(5)       NULL,      -- '10:00' gibi; izin verilen saatler api/validate.php'de
    ip_hash      CHAR(64)      NOT NULL,  -- IP'nin kendisi değil, tuzlu SHA-256 özeti (rate limit için)
    created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_ip_created (ip_hash, created_at),
    -- Aynı gün + saat iki kez alınamaz. NULL değerler çakışmaz (randevusuz talepler serbest).
    UNIQUE KEY uq_meeting (meeting_date, meeting_slot)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
