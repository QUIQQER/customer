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
     * @param $customerId
     * @param array $address
     * @param array $groupIds
     *
     * @return QUI\Users\User
     *
     * @throws QUI\Exception
     * @throws QUI\Permissions\Exception
     */
    public function createCustomer($customerId, $address = [], $groupIds = [])
    {
        QUI\Permissions\Permission::checkPermission('quiqqer.customer.create');

        $User = QUI::getUsers()->createChild($customerId);

        $User->setAttribute('customerId', $customerId);
        $User->setAttribute('mainGroup', $this->getCustomerGroupId());
        $User->save();

        if (!empty($address)) {
            try {
                $Address = $User->getStandardAddress();
            } catch (QUI\Exception $Exception) {
                $Address = $User->addAddress();
            }

            $needles = [
                'salutation',
                'firstname',
                'lastname',
                'company',
                'delivery',
                'street_no',
                'zip',
                'city',
                'country'
            ];

            foreach ($needles as $needle) {
                if (!isset($address[$needle])) {
                    $address[$needle] = '';
                }
            }

            $Address->setAttribute('salutation', $address['salutation']);
            $Address->setAttribute('firstname', $address['firstname']);
            $Address->setAttribute('lastname', $address['lastname']);
            $Address->setAttribute('company', $address['company']);
            $Address->setAttribute('delivery', $address['delivery']);
            $Address->setAttribute('street_no', $address['street_no']);
            $Address->setAttribute('zip', $address['zip']);
            $Address->setAttribute('city', $address['city']);
            $Address->setAttribute('country', $address['country']);

            $Address->save();
        }

        // groups
        $this->addUserToCustomerGroup($User->getId());

        foreach ($groupIds as $groupId) {
            $User->addToGroup($groupId);
        }

        $User->save();

        return $User;
    }

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
     * Returns whether the customer can log in to the system or not
     *
     * @return bool
     */
    public function getCustomerLoginFlag()
    {
        try {
            $Package = QUI::getPackage('quiqqer/customer');
            $Config  = $Package->getConfig();
        } catch (QUI\Exception $Exception) {
            return false;
        }

        return (bool)$Config->getValue('customer', 'customerLogin');
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
        if (!$userId) {
            return;
        }

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

        if ($User->isInGroup($customerGroup)) {
            return;
        }

        $User->addToGroup($customerGroup);
        $User->save();
    }

    /**
     * Remove a user from the customer group
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

        if (!empty($attributes['password1'])
            && !empty($attributes['password2'])
            && $attributes['password1'] === $attributes['password2']) {
            $User->setPassword($attributes['password1']);

            unset($attributes['password1']);
            unset($attributes['password2']);
        }

        // defaults
        $User->setAttributes($attributes);

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

        // tel, fax, mobile
        if (!empty($attributes['address-communication'])) {
            $Address->clearPhone();

            foreach ($attributes['address-communication'] as $entry) {
                if (isset($entry['no']) && isset($entry['type'])) {
                    $Address->addPhone($entry);
                }
            }
        }

        // mail
        if (!empty($attributes['address-mail'])) {
            $Address->clearMail();
            $Address->addMail($attributes['address-mail']);
            unset($attributes['address-mail']);
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

    //region comments

    /**
     * Add a comment to the customer user comments
     *
     * @param QUI\Users\User $User
     * @param string $comment - comment message
     *
     * @throws QUI\Exception
     */
    public function addCommentToUser(QUI\Users\User $User, $comment)
    {
        $Comments = $this->getUserComments($User);

        $Comments->addComment(
            $comment,
            false,
            'quiqqer/customer',
            'fa fa-user'
        );

        $User->setAttribute('comments', $Comments->serialize());
        $User->save();
    }

    /**
     * @param QUI\Users\User $User
     * @return QUI\ERP\Comments
     */
    public function getUserComments(QUI\Users\User $User)
    {
        $comments = $User->getAttribute('comments');
        $comments = \json_decode($comments, true);

        if ($comments) {
            $Comments = new QUI\ERP\Comments($comments);
        } else {
            $Comments = new QUI\ERP\Comments($comments);
        }

        return $Comments;
    }

    //endregion
}
