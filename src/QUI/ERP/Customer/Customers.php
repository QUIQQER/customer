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
     * Return the customer group
     *
     * @return QUI\Groups\Group
     * @throws Exception
     */
    public function getCustomerGroup()
    {
        if ($this->Group !== null) {
            return $this->Group;
        }

        $Package = QUI::getPackage('quiqqer/customer');
        $Config  = $Package->getConfig();
        $groupId = $Config->getValue('general', 'groupId');

        if (empty($groupId)) {
            throw new Exception(array(
                'quiqqer/customer',
                'exception.customer.group.not.exists'
            ));
        }

        $this->Group = QUI::getGroups()->get($groupId);

        return $this->Group;
    }
}
