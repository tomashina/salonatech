-- OpenCart catalog DB indexes for SalonaTech.
-- Run on live only after a fresh database backup.
-- The helper keeps this script idempotent for existing index names.

DROP PROCEDURE IF EXISTS salona_add_index_if_missing;

SET @salona_old_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = REPLACE(@@SESSION.sql_mode, 'STRICT_TRANS_TABLES', '');
SET SESSION sql_mode = REPLACE(@@SESSION.sql_mode, 'STRICT_ALL_TABLES', '');
SET SESSION sql_mode = REPLACE(@@SESSION.sql_mode, 'NO_ZERO_DATE', '');
SET SESSION sql_mode = REPLACE(@@SESSION.sql_mode, 'NO_ZERO_IN_DATE', '');

DELIMITER //
CREATE PROCEDURE salona_add_index_if_missing(
    IN table_name_in VARCHAR(64),
    IN index_name_in VARCHAR(64),
    IN ddl_in TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = table_name_in
          AND index_name = index_name_in
        LIMIT 1
    ) THEN
        SET @salona_add_index_sql = ddl_in;
        PREPARE salona_add_index_stmt FROM @salona_add_index_sql;
        EXECUTE salona_add_index_stmt;
        DEALLOCATE PREPARE salona_add_index_stmt;
    END IF;
END//
DELIMITER ;

CALL salona_add_index_if_missing('oc_product', 'idx_product_catalog_sort', 'ALTER TABLE `oc_product` ADD INDEX `idx_product_catalog_sort` (`status`, `sort_order`, `product_id`, `date_available`)');
CALL salona_add_index_if_missing('oc_product', 'idx_product_catalog_date', 'ALTER TABLE `oc_product` ADD INDEX `idx_product_catalog_date` (`status`, `date_added`, `product_id`, `date_available`)');
CALL salona_add_index_if_missing('oc_product', 'idx_product_catalog_viewed', 'ALTER TABLE `oc_product` ADD INDEX `idx_product_catalog_viewed` (`status`, `viewed`, `date_added`, `product_id`, `date_available`)');
CALL salona_add_index_if_missing('oc_product', 'idx_product_manufacturer', 'ALTER TABLE `oc_product` ADD INDEX `idx_product_manufacturer` (`manufacturer_id`, `status`, `sort_order`, `product_id`, `date_available`)');

CALL salona_add_index_if_missing('oc_product_to_category', 'idx_p2c_category_product', 'ALTER TABLE `oc_product_to_category` ADD INDEX `idx_p2c_category_product` (`category_id`, `product_id`)');
CALL salona_add_index_if_missing('oc_product_to_store', 'idx_p2s_store_product', 'ALTER TABLE `oc_product_to_store` ADD INDEX `idx_p2s_store_product` (`store_id`, `product_id`)');
CALL salona_add_index_if_missing('oc_category_path', 'idx_cp_path_level_category', 'ALTER TABLE `oc_category_path` ADD INDEX `idx_cp_path_level_category` (`path_id`, `level`, `category_id`)');
CALL salona_add_index_if_missing('oc_product_filter', 'idx_pf_filter_product', 'ALTER TABLE `oc_product_filter` ADD INDEX `idx_pf_filter_product` (`filter_id`, `product_id`)');

CALL salona_add_index_if_missing('oc_product_discount', 'idx_discount_lookup', 'ALTER TABLE `oc_product_discount` ADD INDEX `idx_discount_lookup` (`product_id`, `customer_group_id`, `quantity`, `date_start`, `date_end`, `priority`, `price`)');
CALL salona_add_index_if_missing('oc_product_special', 'idx_special_lookup', 'ALTER TABLE `oc_product_special` ADD INDEX `idx_special_lookup` (`product_id`, `customer_group_id`, `date_start`, `date_end`, `priority`, `price`)');
CALL salona_add_index_if_missing('oc_review', 'idx_review_product_status_rating', 'ALTER TABLE `oc_review` ADD INDEX `idx_review_product_status_rating` (`product_id`, `status`, `rating`)');

CALL salona_add_index_if_missing('oc_order_product', 'idx_order_product_product_order', 'ALTER TABLE `oc_order_product` ADD INDEX `idx_order_product_product_order` (`product_id`, `order_id`, `quantity`)');
CALL salona_add_index_if_missing('oc_order', 'idx_order_status_order', 'ALTER TABLE `oc_order` ADD INDEX `idx_order_status_order` (`order_status_id`, `order_id`)');

CALL salona_add_index_if_missing('oc_product_image', 'idx_product_image_sort', 'ALTER TABLE `oc_product_image` ADD INDEX `idx_product_image_sort` (`product_id`, `sort_order`)');
CALL salona_add_index_if_missing('oc_product_to_layout', 'idx_p2l_store_product', 'ALTER TABLE `oc_product_to_layout` ADD INDEX `idx_p2l_store_product` (`store_id`, `product_id`)');
CALL salona_add_index_if_missing('oc_manufacturer_to_store', 'idx_m2s_store_manufacturer', 'ALTER TABLE `oc_manufacturer_to_store` ADD INDEX `idx_m2s_store_manufacturer` (`store_id`, `manufacturer_id`)');

CALL salona_add_index_if_missing('oc_category', 'idx_category_parent_status_sort', 'ALTER TABLE `oc_category` ADD INDEX `idx_category_parent_status_sort` (`parent_id`, `status`, `sort_order`, `category_id`)');
CALL salona_add_index_if_missing('oc_category_to_store', 'idx_c2s_store_category', 'ALTER TABLE `oc_category_to_store` ADD INDEX `idx_c2s_store_category` (`store_id`, `category_id`)');
CALL salona_add_index_if_missing('oc_category_description', 'idx_cd_lang_name_category', 'ALTER TABLE `oc_category_description` ADD INDEX `idx_cd_lang_name_category` (`language_id`, `name`(128), `category_id`)');

CALL salona_add_index_if_missing('oc_setting', 'idx_setting_store_code_key', 'ALTER TABLE `oc_setting` ADD INDEX `idx_setting_store_code_key` (`store_id`, `code`, `key`)');
CALL salona_add_index_if_missing('oc_layout_route', 'idx_layout_route_store_route', 'ALTER TABLE `oc_layout_route` ADD INDEX `idx_layout_route_store_route` (`store_id`, `route`)');
CALL salona_add_index_if_missing('oc_layout_module', 'idx_layout_module_layout_position_sort', 'ALTER TABLE `oc_layout_module` ADD INDEX `idx_layout_module_layout_position_sort` (`layout_id`, `position`, `sort_order`)');

ANALYZE TABLE
    `oc_product`,
    `oc_product_description`,
    `oc_product_to_category`,
    `oc_product_to_store`,
    `oc_category`,
    `oc_category_description`,
    `oc_category_path`,
    `oc_category_to_store`,
    `oc_product_filter`,
    `oc_product_discount`,
    `oc_product_special`,
    `oc_review`,
    `oc_order_product`,
    `oc_order`,
    `oc_product_image`,
    `oc_product_to_layout`,
    `oc_manufacturer_to_store`,
    `oc_setting`,
    `oc_layout_route`,
    `oc_layout_module`;

DROP PROCEDURE IF EXISTS salona_add_index_if_missing;

SET SESSION sql_mode = @salona_old_sql_mode;
