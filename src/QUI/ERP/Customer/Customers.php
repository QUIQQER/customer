<?php

/**
 * This file contains QUI\ERP\Customer\Customers
 */

namespace QUI\ERP\Customer;

use QUI;
use QUI\Utils\Singleton;

/**
 * Class Customers
 * - Main customer API
 *
 * @package QUI\ERP\Customer
 */
class Customers extends Singleton
{
    /**
     * @var null|QUI\Groups\Group
     */
    protected $Group = null;

    /**
     * @return int
     *
     * @throws Exception
     * @throws QUI\Exception
     */
    public function getCustomerGroupId()
    {
        $Package = QUI::getPackage('quiqqer/customer');
        $Config  = $Package->getConfig();
        $groupId = $Config->getValue('customer', 'groupId');

        if (empty($groupId)) {
            throw new Exception([
                'quiqqer/customer',
                'exception.customer.group.not.exists'
            ]);
        }

        return (int)$groupId;
    }

    /**
     * Return the customer group
     *
     * @return QUI\Groups\Group
     *
     * @throws Exception
     * @throws QUI\Exception
     */
    public function getCustomerGroup()
    {
        if ($this->Group === null) {
            $this->Group = QUI::getGroups()->get($this->getCustomerGroupId());
        }

        return $this->Group;
    }
}
