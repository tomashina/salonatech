-- SalonaTech live database structure repair.
-- Based on /Users/tomek/Desktop/baza.sql dumped on 2026-06-25 09:27.
--
-- Findings from the dump:
-- - Catalog/performance indexes are present.
-- - AUTO_INCREMENT is missing from almost all OpenCart id columns.
-- - Some normal PRIMARY/KEY definitions are missing near the end of the dump.
-- - oc_seo_url has a duplicate UNIQUE index: store_id and store_id_2 are identical.
--
-- Run on live only after a fresh database backup.

DROP PROCEDURE IF EXISTS salona_fix_auto_increment;
DROP PROCEDURE IF EXISTS salona_add_index_if_missing;
DROP PROCEDURE IF EXISTS salona_drop_index_if_exists;
DROP PROCEDURE IF EXISTS salona_truncate_table_if_index_missing;

SET @salona_old_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = REPLACE(@@SESSION.sql_mode, 'STRICT_TRANS_TABLES', '');
SET SESSION sql_mode = REPLACE(@@SESSION.sql_mode, 'STRICT_ALL_TABLES', '');
SET SESSION sql_mode = REPLACE(@@SESSION.sql_mode, 'NO_ZERO_DATE', '');
SET SESSION sql_mode = REPLACE(@@SESSION.sql_mode, 'NO_ZERO_IN_DATE', '');

DELIMITER //

CREATE PROCEDURE salona_fix_auto_increment(
    IN table_name_in VARCHAR(64),
    IN column_name_in VARCHAR(64),
    IN column_definition_in TEXT
)
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = table_name_in
          AND column_name = column_name_in
          AND extra NOT LIKE '%auto_increment%'
        LIMIT 1
    ) THEN
        SET @salona_next_id_sql = CONCAT(
            'SET @salona_next_auto_id := (SELECT COALESCE(MAX(`',
            REPLACE(column_name_in, '`', '``'), '`), 0) FROM `',
            REPLACE(table_name_in, '`', '``'), '` WHERE `',
            REPLACE(column_name_in, '`', '``'), '` <> 0)'
        );
        PREPARE salona_next_id_stmt FROM @salona_next_id_sql;
        EXECUTE salona_next_id_stmt;
        DEALLOCATE PREPARE salona_next_id_stmt;

        SET @salona_fix_zero_sql = CONCAT(
            'UPDATE `', REPLACE(table_name_in, '`', '``'),
            '` SET `', REPLACE(column_name_in, '`', '``'),
            '` = (@salona_next_auto_id := @salona_next_auto_id + 1) WHERE `',
            REPLACE(column_name_in, '`', '``'), '` = 0'
        );
        PREPARE salona_fix_zero_stmt FROM @salona_fix_zero_sql;
        EXECUTE salona_fix_zero_stmt;
        DEALLOCATE PREPARE salona_fix_zero_stmt;

        SET @salona_fix_ai_sql = CONCAT(
            'ALTER TABLE `', REPLACE(table_name_in, '`', '``'),
            '` MODIFY `', REPLACE(column_name_in, '`', '``'),
            '` ', column_definition_in, ' AUTO_INCREMENT'
        );
        PREPARE salona_fix_ai_stmt FROM @salona_fix_ai_sql;
        EXECUTE salona_fix_ai_stmt;
        DEALLOCATE PREPARE salona_fix_ai_stmt;
    END IF;
END//

CREATE PROCEDURE salona_add_index_if_missing(
    IN table_name_in VARCHAR(64),
    IN index_name_in VARCHAR(64),
    IN ddl_in TEXT
)
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = table_name_in
        LIMIT 1
    ) AND NOT EXISTS (
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

CREATE PROCEDURE salona_drop_index_if_exists(
    IN table_name_in VARCHAR(64),
    IN index_name_in VARCHAR(64)
)
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = table_name_in
          AND index_name = index_name_in
        LIMIT 1
    ) THEN
        SET @salona_drop_index_sql = CONCAT(
            'ALTER TABLE `', REPLACE(table_name_in, '`', '``'),
            '` DROP INDEX `', REPLACE(index_name_in, '`', '``'), '`'
        );
        PREPARE salona_drop_index_stmt FROM @salona_drop_index_sql;
        EXECUTE salona_drop_index_stmt;
        DEALLOCATE PREPARE salona_drop_index_stmt;
    END IF;
END//

CREATE PROCEDURE salona_truncate_table_if_index_missing(
    IN table_name_in VARCHAR(64),
    IN index_name_in VARCHAR(64)
)
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = table_name_in
        LIMIT 1
    ) AND NOT EXISTS (
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = table_name_in
          AND index_name = index_name_in
        LIMIT 1
    ) THEN
        SET @salona_truncate_sql = CONCAT(
            'TRUNCATE TABLE `', REPLACE(table_name_in, '`', '``'), '`'
        );
        PREPARE salona_truncate_stmt FROM @salona_truncate_sql;
        EXECUTE salona_truncate_stmt;
        DEALLOCATE PREPARE salona_truncate_stmt;
    END IF;
END//

DELIMITER ;

CALL salona_add_index_if_missing('oc_hb_url', 'PRIMARY', 'ALTER TABLE `oc_hb_url` ADD PRIMARY KEY (`id`)');
-- The analyzed dump has duplicate oc_session.session_id values. Sessions are transient;
-- this clears current logins only when the PRIMARY key is missing.
CALL salona_truncate_table_if_index_missing('oc_session', 'PRIMARY');
CALL salona_add_index_if_missing('oc_session', 'PRIMARY', 'ALTER TABLE `oc_session` ADD PRIMARY KEY (`session_id`)');
CALL salona_add_index_if_missing('oc_shipping_courier', 'PRIMARY', 'ALTER TABLE `oc_shipping_courier` ADD PRIMARY KEY (`shipping_courier_id`)');
CALL salona_add_index_if_missing('oc_statistics', 'PRIMARY', 'ALTER TABLE `oc_statistics` ADD PRIMARY KEY (`statistics_id`)');
CALL salona_add_index_if_missing('oc_stock_status', 'PRIMARY', 'ALTER TABLE `oc_stock_status` ADD PRIMARY KEY (`stock_status_id`,`language_id`)');
CALL salona_add_index_if_missing('oc_store', 'PRIMARY', 'ALTER TABLE `oc_store` ADD PRIMARY KEY (`store_id`)');
CALL salona_add_index_if_missing('oc_tax_class', 'PRIMARY', 'ALTER TABLE `oc_tax_class` ADD PRIMARY KEY (`tax_class_id`)');
CALL salona_add_index_if_missing('oc_tax_rate', 'PRIMARY', 'ALTER TABLE `oc_tax_rate` ADD PRIMARY KEY (`tax_rate_id`)');
CALL salona_add_index_if_missing('oc_tax_rate_to_customer_group', 'PRIMARY', 'ALTER TABLE `oc_tax_rate_to_customer_group` ADD PRIMARY KEY (`tax_rate_id`,`customer_group_id`)');
CALL salona_add_index_if_missing('oc_tax_rule', 'PRIMARY', 'ALTER TABLE `oc_tax_rule` ADD PRIMARY KEY (`tax_rule_id`)');
CALL salona_add_index_if_missing('oc_testimonial', 'PRIMARY', 'ALTER TABLE `oc_testimonial` ADD PRIMARY KEY (`testimonial_id`)');
CALL salona_add_index_if_missing('oc_testimonial_to_store', 'PRIMARY', 'ALTER TABLE `oc_testimonial_to_store` ADD PRIMARY KEY (`testimonial_id`,`store_id`)');
CALL salona_add_index_if_missing('oc_testimonialsettings', 'PRIMARY', 'ALTER TABLE `oc_testimonialsettings` ADD PRIMARY KEY (`testimonialsettings_id`)');
CALL salona_add_index_if_missing('oc_tf_filter', 'PRIMARY', 'ALTER TABLE `oc_tf_filter` ADD PRIMARY KEY (`filter_id`)');
CALL salona_add_index_if_missing('oc_tf_filter_description', 'PRIMARY', 'ALTER TABLE `oc_tf_filter_description` ADD PRIMARY KEY (`filter_id`,`language_id`)');
CALL salona_add_index_if_missing('oc_tf_filter_to_category', 'PRIMARY', 'ALTER TABLE `oc_tf_filter_to_category` ADD PRIMARY KEY (`filter_id`,`category_id`)');
CALL salona_add_index_if_missing('oc_tf_filter_value', 'filter_id', 'ALTER TABLE `oc_tf_filter_value` ADD KEY `filter_id` (`filter_id`)');
CALL salona_add_index_if_missing('oc_tf_filter_value', 'PRIMARY', 'ALTER TABLE `oc_tf_filter_value` ADD PRIMARY KEY (`value_id`)');
CALL salona_add_index_if_missing('oc_tf_filter_value_description', 'PRIMARY', 'ALTER TABLE `oc_tf_filter_value_description` ADD PRIMARY KEY (`value_id`,`language_id`)');
CALL salona_add_index_if_missing('oc_tf_filter_value_to_product', 'PRIMARY', 'ALTER TABLE `oc_tf_filter_value_to_product` ADD PRIMARY KEY (`value_id`,`product_id`)');
CALL salona_add_index_if_missing('oc_tf_filter_value_to_product', 'product_id', 'ALTER TABLE `oc_tf_filter_value_to_product` ADD KEY `product_id` (`product_id`)');
CALL salona_add_index_if_missing('oc_theme', 'PRIMARY', 'ALTER TABLE `oc_theme` ADD PRIMARY KEY (`theme_id`)');
CALL salona_add_index_if_missing('oc_translation', 'PRIMARY', 'ALTER TABLE `oc_translation` ADD PRIMARY KEY (`translation_id`)');
CALL salona_add_index_if_missing('oc_upload', 'PRIMARY', 'ALTER TABLE `oc_upload` ADD PRIMARY KEY (`upload_id`)');
CALL salona_add_index_if_missing('oc_url_alias', 'keyword', 'ALTER TABLE `oc_url_alias` ADD KEY `keyword` (`keyword`)');
CALL salona_add_index_if_missing('oc_url_alias', 'PRIMARY', 'ALTER TABLE `oc_url_alias` ADD PRIMARY KEY (`url_alias_id`)');
CALL salona_add_index_if_missing('oc_url_alias', 'query', 'ALTER TABLE `oc_url_alias` ADD KEY `query` (`query`)');
CALL salona_add_index_if_missing('oc_user', 'PRIMARY', 'ALTER TABLE `oc_user` ADD PRIMARY KEY (`user_id`)');
CALL salona_add_index_if_missing('oc_user_group', 'PRIMARY', 'ALTER TABLE `oc_user_group` ADD PRIMARY KEY (`user_group_id`)');
CALL salona_add_index_if_missing('oc_voucher', 'PRIMARY', 'ALTER TABLE `oc_voucher` ADD PRIMARY KEY (`voucher_id`)');
CALL salona_add_index_if_missing('oc_voucher_history', 'PRIMARY', 'ALTER TABLE `oc_voucher_history` ADD PRIMARY KEY (`voucher_history_id`)');
CALL salona_add_index_if_missing('oc_voucher_theme', 'PRIMARY', 'ALTER TABLE `oc_voucher_theme` ADD PRIMARY KEY (`voucher_theme_id`)');
CALL salona_add_index_if_missing('oc_voucher_theme_description', 'PRIMARY', 'ALTER TABLE `oc_voucher_theme_description` ADD PRIMARY KEY (`voucher_theme_id`,`language_id`)');
CALL salona_add_index_if_missing('oc_weight_class', 'PRIMARY', 'ALTER TABLE `oc_weight_class` ADD PRIMARY KEY (`weight_class_id`)');
CALL salona_add_index_if_missing('oc_weight_class_description', 'PRIMARY', 'ALTER TABLE `oc_weight_class_description` ADD PRIMARY KEY (`weight_class_id`,`language_id`)');
CALL salona_add_index_if_missing('oc_zone', 'PRIMARY', 'ALTER TABLE `oc_zone` ADD PRIMARY KEY (`zone_id`)');
CALL salona_add_index_if_missing('oc_zone_to_geo_zone', 'PRIMARY', 'ALTER TABLE `oc_zone_to_geo_zone` ADD PRIMARY KEY (`zone_to_geo_zone_id`)');

CALL salona_fix_auto_increment('oc_address', 'address_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_affiliate_activity', 'affiliate_activity_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_affiliate_login', 'affiliate_login_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_api', 'api_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_api_ip', 'api_ip_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_api_session', 'api_session_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_attribute', 'attribute_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_attribute_group', 'attribute_group_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_banner', 'banner_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_banner_image', 'banner_image_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_blog', 'blog_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_blog_category', 'blog_category_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_blog_comment', 'blog_comment_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_boost_category_special', 'cat_special_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_boost_sitemap_custom_link', 'boost_sitemap_custom_link_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_cart', 'cart_id', 'int(10) UNSIGNED NOT NULL');
CALL salona_fix_auto_increment('oc_category', 'category_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_category_canonical', 'id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_cmpltguagaf', 'cmpltguagaf_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_country', 'country_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_coupon', 'coupon_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_coupon_history', 'coupon_history_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_coupon_product', 'coupon_product_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_currency', 'currency_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_custom_canonical', 'id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_custom_field', 'custom_field_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_custom_field_value', 'custom_field_value_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_custom_seo_url', 'id', 'int(10) UNSIGNED NOT NULL');
CALL salona_fix_auto_increment('oc_customer', 'customer_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_customer_activity', 'customer_activity_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_customer_approval', 'customer_approval_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_customer_group', 'customer_group_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_customer_history', 'customer_history_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_customer_ip', 'customer_ip_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_customer_login', 'customer_login_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_customer_reward', 'customer_reward_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_customer_search', 'customer_search_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_customer_transaction', 'customer_transaction_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_d_validator', 'validator_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_download', 'download_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_event', 'event_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_extension', 'extension_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_extension_install', 'extension_install_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_extension_path', 'extension_path_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_filter', 'filter_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_filter_group', 'filter_group_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_geo_zone', 'geo_zone_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_googleshopping_product', 'product_advertise_google_id', 'int(10) UNSIGNED NOT NULL');
CALL salona_fix_auto_increment('oc_hb_onpage_templates', 'id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_hb_route_meta', 'id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_hb_seo_keywords', 'id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_hb_url_preserve', 'id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_image_rename_logs', 'id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_information', 'information_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_klarna_checkout_order', 'klarna_checkout_order_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_language', 'language_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_layout', 'layout_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_layout_module', 'layout_module_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_layout_route', 'layout_route_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_length_class', 'length_class_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_location', 'location_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_manufacturer', 'manufacturer_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_marketing', 'marketing_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_mega_category_to_sale', 'id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_mega_customer_group_to_sale', 'id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_mega_exclude_products', 'id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_mega_filter_to_sale', 'id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_mega_manufacturer_to_sale', 'id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_mega_menu', 'id', 'int(10) UNSIGNED NOT NULL');
CALL salona_fix_auto_increment('oc_mega_sales', 'id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_menu', 'menu_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_modification', 'modification_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_module', 'module_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_mpgdpr_datarequest', 'mpgdpr_datarequest_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_mpgdpr_deleteme', 'mpgdpr_deleteme_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_mpgdpr_policyacceptance', 'mpgdpr_policyacceptance_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_mpgdpr_requestlist', 'mpgdpr_requestlist_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_mpgdpr_restrict_processing', 'mpgdpr_restrict_processing_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_mpgdpr_upload', 'upload_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_msmart_search_extra_field', 'id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_msmart_search_history', 'id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_msmart_search_replaced_phrase', 'phrase_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_newsletter', 'id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_newslettersubscription', 'subscription_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_option', 'option_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_option_value', 'option_value_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_order', 'order_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_order_custom_field', 'order_custom_field_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_order_history', 'order_history_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_order_option', 'order_option_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_order_product', 'order_product_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_order_recurring', 'order_recurring_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_order_recurring_transaction', 'order_recurring_transaction_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_order_shipment', 'order_shipment_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_order_status', 'order_status_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_order_total', 'order_total_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_order_voucher', 'order_voucher_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_product', 'product_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_product_canonical', 'id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_product_discount', 'product_discount_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_product_energy_info', 'energy_info_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_product_image', 'product_image_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_product_option', 'product_option_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_product_option_value', 'product_option_value_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_product_reward', 'product_reward_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_product_special', 'product_special_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_product_tabs', 'tab_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_question', 'question_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_recurring', 'recurring_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_relatedoptions', 'relatedoptions_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_relatedoptions_variant', 'relatedoptions_variant_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_relatedoptions_variant_product', 'relatedoptions_variant_product_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_return', 'return_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_return_action', 'return_action_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_return_history', 'return_history_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_return_reason', 'return_reason_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_return_status', 'return_status_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_review', 'review_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_seo_url', 'seo_url_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_setting', 'setting_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_statistics', 'statistics_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_stock_status', 'stock_status_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_store', 'store_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_tax_class', 'tax_class_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_tax_rate', 'tax_rate_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_tax_rule', 'tax_rule_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_testimonial', 'testimonial_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_testimonialsettings', 'testimonialsettings_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_tf_filter', 'filter_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_tf_filter_value', 'value_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_theme', 'theme_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_translation', 'translation_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_upload', 'upload_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_url_alias', 'url_alias_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_user', 'user_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_user_group', 'user_group_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_voucher', 'voucher_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_voucher_history', 'voucher_history_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_voucher_theme', 'voucher_theme_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_weight_class', 'weight_class_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_zone', 'zone_id', 'int(11) NOT NULL');
CALL salona_fix_auto_increment('oc_zone_to_geo_zone', 'zone_to_geo_zone_id', 'int(11) NOT NULL');

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

-- Exact duplicate of oc_seo_url.store_id in the analyzed dump.
CALL salona_drop_index_if_exists('oc_seo_url', 'store_id_2');

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
    `oc_layout_module`,
    `oc_seo_url`;

DROP PROCEDURE IF EXISTS salona_fix_auto_increment;
DROP PROCEDURE IF EXISTS salona_add_index_if_missing;
DROP PROCEDURE IF EXISTS salona_drop_index_if_exists;
DROP PROCEDURE IF EXISTS salona_truncate_table_if_index_missing;

SET SESSION sql_mode = @salona_old_sql_mode;
