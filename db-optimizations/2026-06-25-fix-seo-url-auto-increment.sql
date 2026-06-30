-- Repair oc_seo_url after a database transfer/import.
-- Symptom: inserting a new SEO URL fails with "Duplicate entry '0' for key 'PRIMARY'".
-- Cause: seo_url_id lost AUTO_INCREMENT, so omitted primary keys default to 0.

SELECT 'Before repair' AS step;
SHOW COLUMNS FROM `oc_seo_url` LIKE 'seo_url_id';
SELECT COUNT(*) AS rows_with_zero_seo_url_id FROM `oc_seo_url` WHERE `seo_url_id` = 0;

SET @salona_next_seo_url_id := (
    SELECT COALESCE(MAX(`seo_url_id`), 0) + 1
    FROM `oc_seo_url`
    WHERE `seo_url_id` <> 0
);

UPDATE `oc_seo_url`
SET `seo_url_id` = @salona_next_seo_url_id
WHERE `seo_url_id` = 0;

ALTER TABLE `oc_seo_url`
    MODIFY `seo_url_id` int NOT NULL AUTO_INCREMENT;

SET @salona_next_auto_increment := (
    SELECT COALESCE(MAX(`seo_url_id`), 0) + 1
    FROM `oc_seo_url`
);

SET @salona_sql := CONCAT('ALTER TABLE `oc_seo_url` AUTO_INCREMENT = ', @salona_next_auto_increment);
PREPARE salona_stmt FROM @salona_sql;
EXECUTE salona_stmt;
DEALLOCATE PREPARE salona_stmt;

SELECT 'After repair' AS step;
SHOW COLUMNS FROM `oc_seo_url` LIKE 'seo_url_id';
SELECT COUNT(*) AS rows_with_zero_seo_url_id FROM `oc_seo_url` WHERE `seo_url_id` = 0;
