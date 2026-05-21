-- Jalankan sekali setelah backup database.
-- Aplikasi versi ini tidak lagi memakai role/owner_id/isi_pax/isi_slop.

SET @db_name = DATABASE();

SELECT CONSTRAINT_NAME INTO @fk_barang_owner
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = @db_name
  AND TABLE_NAME = 'barang'
  AND COLUMN_NAME = 'owner_id'
  AND REFERENCED_TABLE_NAME IS NOT NULL
LIMIT 1;
SET @sql = IF(@fk_barang_owner IS NULL, 'SELECT 1', CONCAT('ALTER TABLE barang DROP FOREIGN KEY ', @fk_barang_owner));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT CONSTRAINT_NAME INTO @fk_detail_owner
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = @db_name
  AND TABLE_NAME = 'detail_penjualan'
  AND COLUMN_NAME = 'owner_id'
  AND REFERENCED_TABLE_NAME IS NOT NULL
LIMIT 1;
SET @sql = IF(@fk_detail_owner IS NULL, 'SELECT 1', CONCAT('ALTER TABLE detail_penjualan DROP FOREIGN KEY ', @fk_detail_owner));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT CONSTRAINT_NAME INTO @fk_stok_owner
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = @db_name
  AND TABLE_NAME = 'stok_masuk'
  AND COLUMN_NAME = 'owner_id'
  AND REFERENCED_TABLE_NAME IS NOT NULL
LIMIT 1;
SET @sql = IF(@fk_stok_owner IS NULL, 'SELECT 1', CONCAT('ALTER TABLE stok_masuk DROP FOREIGN KEY ', @fk_stok_owner));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE barang
    DROP COLUMN isi_pax,
    DROP COLUMN isi_slop,
    DROP COLUMN owner_id;

ALTER TABLE detail_penjualan
    DROP COLUMN owner_id;

ALTER TABLE stok_masuk
    DROP COLUMN owner_id;

ALTER TABLE users
    DROP COLUMN role;
