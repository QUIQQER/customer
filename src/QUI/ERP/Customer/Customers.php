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
        $groupId = (int)\trim($Config->getValue('customer', 'groupId'));

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

    /**
     * Add a user to the customer group
     *
     * @param {string|bool} $userId
     *
     * @throws QUI\Exception
     * @throws QUI\Users\Exception
     */
    public function addUserToCustomerGroup($userId)
    {
        $customerGroup = null;

        try {
            $customerGroup = $this->getCustomerGroupId();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addError($Exception->getMessage());
        }

        if (!$customerGroup) {
            return;
        }

        $User = QUI::getUsers()->get((int)$userId);
        $User->addToGroup($customerGroup);
        $User->save();
    }

    /**
     * Remve a user from the customer group
     *
     * @param {string|bool} $userId
     *
     * @throws QUI\Exception
     * @throws QUI\Users\Exception
     */
    public function removeUserFromCustomerGroup($userId)
    {
        $customerGroup = null;

        try {
            $customerGroup = $this->getCustomerGroupId();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addError($Exception->getMessage());
        }

        if (!$customerGroup) {
            return;
        }

        $User = QUI::getUsers()->get((int)$userId);
        $User->removeGroup($customerGroup);
        $User->save();
    }

    /**
     * @param $userId
     * @param array $attributes
     *
     * @throws QUI\Exception
     */
    public function setAttributesToCustomer($userId, array $attributes)
    {
        $User = QUI::getUsers()->get($userId);

        // defaults
        $defaults = ['customerId'];

        foreach ($defaults as $entry) {
            if (isset($attributes[$entry])) {
                $User->setAttribute($entry, $attributes[$entry]);
            }
        }

        // address
        try {
            $Address = $User->getStandardAddress();
        } catch (QUI\Exception $Exception) {
            // create one
            $Address = $User->addAddress([]);
        }

        $addressAttributes = [
            'salutation',
            'firstname',
            'lastname',
            'company',

            'street_no',
            'zip',
            'city',
            'country'
        ];

        foreach ($addressAttributes as $addressAttribute) {
            if (isset($attributes['address-'.$addressAttribute])) {
                $Address->setAttribute($addressAttribute, $attributes['address-'.$addressAttribute]);
                unset($attributes['address-'.$addressAttribute]);
            }
        }

        $Address->save();


        // group
        $groups = [];

        if (isset($attributes['group'])) {
            $groups[] = (int)$attributes['group'];
            $User->setAttribute('mainGroup', (int)$attributes['group']);
        } elseif (isset($attributes['group']) && $attributes['group'] === null) {
            $User->setAttribute('mainGroup', false);
        }

        if (isset($attributes['groups'])) {
            if (\strpos($attributes['groups'], ',') !== false) {
                $attributes['groups'] = \explode(',', $attributes['groups']);
            }

            $groups = \array_merge($groups, $attributes['groups']);
        }

        if (!empty($groups)) {
            $User->setGroups($groups);
        }

        $User->save();
    }
}
