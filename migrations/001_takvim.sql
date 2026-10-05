-- Mevcut requests tablosuna ön görüşme randevusu sütunlarını ekler.
-- Hostinger hPanel > phpMyAdmin > veritabanı > SQL sekmesinde BİR KEZ çalıştırılır.

ALTER TABLE requests
    ADD COLUMN meeting_date DATE    NULL AFTER message,
    ADD COLUMN meeting_slot CHAR(5) NULL AFTER meeting_date,
    ADD UNIQUE KEY uq_meeting (meeting_date, meeting_slot);
