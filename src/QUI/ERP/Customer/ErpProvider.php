<?php

namespace QUI\ERP\Customer;

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
    }

    /**
     * @return list<NumberRange>
     */
    public static function getNumberRanges(): array
    {
        return [
            new NumberRange()
        ];
    }
}
