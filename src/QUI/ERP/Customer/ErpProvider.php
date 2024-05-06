<?php

namespace QUI\ERP\Customer;

use QUI\Controls\Sitemap\Item;
use QUI\Controls\Sitemap\Map;
use QUI\ERP\Api\AbstractErpProvider;

/**
 * Class ErpProvider
 *
 * ERP provider class for quiqqer/customer
 */
class ErpProvider extends AbstractErpProvider
{
    /**
     * @param Map $Map
     */
    public static function addMenuItems(Map $Map): void
    {
        $Accounting = $Map->getChildrenByName('accounting');

        if ($Accounting === null) {
            $Accounting = new Item([
                'icon' => 'fa fa-book',
                'name' => 'accounting',
                'text' => ['quiqqer/customer', 'erp.panel.accounting.text'],
                'opened' => true,
                'priority' => 1
            ]);

            $Map->appendChild($Accounting);
        }

        $Purchasing = new Item([
            'icon' => 'fa fa-money',
            'name' => 'open_items',
            'text' => ['quiqqer/customer', 'erp.panel.open_items.text'],
            'require' => 'package/quiqqer/customer/bin/backend/controls/OpenItems/OpenItems'
        ]);

        $Accounting->appendChild($Purchasing);
    }

    /**
     * @return array
     */
    public static function getNumberRanges(): array
    {
        return [
            new NumberRange()
        ];
    }
}
