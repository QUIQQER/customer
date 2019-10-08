<?php

namespace QUI\ERP\Customer;

use QUI;

/**
 * Class Utils
 *
 * @package QUI\ERP\Customer
 */
class Utils extends QUI\Utils\Singleton
{
    /**
     * @return array
     */
    public function getCategoriesForCustomerCreate()
    {
        $categories = [];

        $categories[] = [
            'text'      => QUI::getLocale()->get('quiqqer/customer', 'customer.create.category.details'),
            'textimage' => 'fa fa-id-card',
            'require'   => ''
        ];

        $categories[] = [
            'text'      => QUI::getLocale()->get('quiqqer/customer', 'customer.create.category.address'),
            'textimage' => 'fa fa-address-book',
            'require'   => ''
        ];

        return $categories;
    }
}
