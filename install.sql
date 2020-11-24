CREATE TABLE IF NOT EXISTS `PREFIX_monri_paymentfee`
(
    `id`           int(11) unsigned    NOT NULL AUTO_INCREMENT,
    `id_cart_rule` int(11)             NOT NULL,
    `id_cart`      int(11)             NOT NULL,
    `id_customer`  int(11)             NOT NULL,
    `is_used`      tinyint(1) unsigned NOT NULL DEFAULT '0',
    `date_add`     datetime            NOT NULL,
    `date_upd`     datetime            NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE = ENGINE_TYPE
  DEFAULT CHARSET = utf8;