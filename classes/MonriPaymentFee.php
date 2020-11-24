<?php
class MonriPaymentFee extends ObjectModel
{
    public $id;
    public $id_cart_rule;
    public $id_cart;
    public $id_customer;
    public $is_used;
    public $date_add;
    public $date_upd;

    public static $definition = array(
        'table' => 'monri_paymentfee',
        'primary' => 'id',
        'fields' => array(
            'id_cart_rule' => array('type' => self::TYPE_INT, 'validate' => 'isInt', 'required' => true),
            'id_cart' => array('type' => self::TYPE_INT, 'validate' => 'isInt', 'required' => true),
            'id_customer' => array('type' => self::TYPE_INT, 'validate' => 'isInt', 'required' => true),
            'is_used' => array('type' => self::TYPE_BOOL, 'validate' => 'isBool'),
            'date_add' => array('type' => self::TYPE_DATE,'validate' => 'isDateFormat'),
            'date_upd' => array('type' => self::TYPE_DATE,'validate' => 'isDateFormat'),
        ),
    );

    public function getIdCartRuleByIdCart($idCart, $idCustomer)
    {
        return Db::getInstance()->getRow(
            'SELECT * FROM `'._DB_PREFIX_.'monri_paymentfee`
            WHERE `id_cart` = '. (int)$idCart.'
            AND `id_customer` = ' .(int)$idCustomer
        );
    }

    public function getUsedVoucherByIdCustomer($idCustomer)
    {
        return Db::getInstance()->getRow(
            'SELECT * FROM `'._DB_PREFIX_.'monri_paymentfee`
            WHERE `id_customer` = '. (int)$idCustomer.'
            AND `is_used` = 1'
        );
    }
}
