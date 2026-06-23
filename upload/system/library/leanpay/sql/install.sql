CREATE TABLE IF NOT EXISTS `PREFIX_mojakoda_leanpay_transactions` (
  `transaction_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `leanpay_transaction_id` VARCHAR(32) DEFAULT NULL,
  `vendor_transaction_id` CHAR(36) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `currency_code` CHAR(3) NOT NULL,
  `currency_value` FLOAT(15,8) NOT NULL,
  `order_id` INT(10) UNSIGNED DEFAULT NULL,
  `status` VARCHAR(255),
  PRIMARY KEY (`transaction_id`)
) ENGINE=DB_ENGINE DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;