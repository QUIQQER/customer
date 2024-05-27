<?php

/**
 * This file contains QUI\ERP\Customer\Customers
 */

namespace QUI\ERP\Customer;

use QUI;
use QUI\Exception;
use QUI\ExceptionStack;
use QUI\Groups\Group;
use QUI\Utils\Singleton;

use function array_filter;
use function array_merge;
use function explode;
use function is_array;
use function json_decode;
use function json_encode;
use function reset;
use function trim;

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
    protected ?QUI\Groups\Group $Group = null;

    /**
     * @param $customerId
     * @param array $address
     * @param array $groupIds
     *
     * @return QUI\Interfaces\Users\User
     *
     * @throws QUI\Exception
     * @throws QUI\Permissions\Exception
     */
    public function createCustomer($customerId, array $address = [], array $groupIds = []): QUI\Interfaces\Users\User
    {
        QUI\Permissions\Permission::checkPermission('quiqqer.customer.create');

        $User = QUI::getUsers()->createChild($customerId);

        /**
         * Check if $customerId equals the next customerId in the NumberRange.
         * If so, it increases the next customerId by 1.
         */
        $NumberRange = new NumberRange();
        $nextCustomerNo = $NumberRange->getNextCustomerNo();
        $customerId = (int)$customerId;

        if ((int)$customerId >= $nextCustomerNo) {
            $NumberRange->setRange($customerId + 1);
        }

        $User->setAttribute('customerId', $customerId);
        $User->setAttribute('mainGroup', $this->getCustomerGroupId());
        $User->save();

        if (!empty($address)) {
            try {
                $Address = $User->getStandardAddress();
            } catch (QUI\Exception) {
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
                'country',
                'suffix'
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

            if (!empty($address['suffix'])) {
                $Address->setAddressSuffix($address['suffix']);
            }

            $Address->save();

            if (!$User->getAttribute('firstname') || $User->getAttribute('firstname') === '') {
                $User->setAttribute('firstname', $address['firstname']);
            }

            if (!$User->getAttribute('lastname') || $User->getAttribute('lastname') === '') {
                $User->setAttribute('lastname', $address['lastname']);
            }
        }

        // groups
        $this->addUserToCustomerGroup($User->getUUID());

        foreach ($groupIds as $groupId) {
            $User->addToGroup($groupId);
        }

        $User->save();

        return $User;
    }

    /**
     * Get a customer by customer no.
     *
     * @param string $customerNo
     * @return QUI\Interfaces\Users\User
     *
     * @throws Exception
     * @throws QUI\Exception
     */
    public function getCustomerByCustomerNo(string $customerNo): QUI\Interfaces\Users\User
    {
        $NumberRange = new NumberRange();
        $prefix = $NumberRange->getCustomerNoPrefix();
        $customerNo = preg_replace('#^' . $prefix . '#im', '', $customerNo);

        $result = QUI::getDataBase()->fetch([
            'select' => 'id',
            'from' => QUI::getUsers()::table(),
            'where_or' => [
                'customerId' => $customerNo,
                'id' => $customerNo
            ],
            'limit' => 1
        ]);

        if (empty($result)) {
            throw new Exception('Customer with customer no. ' . $customerNo . ' not found.', 404);
        }

        return QUI::getUsers()->get($result[0]['id']);
    }

    /**
     * @return string
     *
     * @throws Exception
     */
    public function getCustomerGroupId(): string
    {
        $Package = QUI::getPackage('quiqqer/customer');
        $Config = $Package->getConfig();
        $groupId = trim($Config->getValue('customer', 'groupId'));

        if (empty($groupId)) {
            throw new Exception([
                'quiqqer/customer',
                'exception.customer.group.not.exists'
            ]);
        }

        return $groupId;
    }

    /**
     * Returns whether the customer can log in to the system or not
     *
     * @return bool
     */
    public function getCustomerLoginFlag(): bool
    {
        try {
            $Package = QUI::getPackage('quiqqer/customer');
            $Config = $Package->getConfig();
        } catch (QUI\Exception) {
            return false;
        }

        return (bool)$Config->getValue('customer', 'customerLogin');
    }

    /**
     * Return the customer group
     *
     * @return Group|null
     *
     * @throws Exception
     */
    public function getCustomerGroup(): ?QUI\Groups\Group
    {
        if ($this->Group === null) {
            $this->Group = QUI::getGroups()->get($this->getCustomerGroupId());
        }

        return $this->Group;
    }

    /**
     * Add a user to the customer group
     *
     * @param bool|int|string $userId
     * @param QUI\Interfaces\Users\User|null $PermissionUser
     *
     * @throws QUI\Exception
     * @throws QUI\Users\Exception
     */
    public function addUserToCustomerGroup(
        bool|int|string $userId,
        QUI\Interfaces\Users\User $PermissionUser = null
    ): void {
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

        $User = QUI::getUsers()->get($userId);

        if ($User->isInGroup($customerGroup)) {
            return;
        }

        $User->addToGroup($customerGroup);
        $User->save($PermissionUser);
    }

    /**
     * Remove a user from the customer group
     *
     * @param bool|int|string $userId
     *
     * @throws QUI\Exception
     * @throws QUI\Users\Exception
     */
    public function removeUserFromCustomerGroup(bool|int|string $userId): void
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

        $User = QUI::getUsers()->get($userId);
        $User->removeGroup($customerGroup);
        $User->save();
    }

    /**
     * @param bool|int|string $userId
     * @param array $attributes
     *
     * @throws Exception
     * @throws QUI\Database\Exception
     * @throws ExceptionStack
     * @throws QUI\Permissions\Exception
     * @throws QUI\Users\Exception
     */
    public function setAttributesToCustomer(bool|int|string $userId, array $attributes): void
    {
        $User = QUI::getUsers()->get($userId);

        if (
            !empty($attributes['password1'])
            && !empty($attributes['password2'])
            && $attributes['password1'] === $attributes['password2']
        ) {
            $User->setPassword($attributes['password1']);

            unset($attributes['password1']);
            unset($attributes['password2']);
        }

        /**
         * If a new customer no. shall be set and the current username equals the new customer no.
         * then also change the username.
         */
        if (!empty($attributes['customerId'])) {
            $newCustomerId = $attributes['customerId'];
            $currentCustomerId = $User->getAttribute('customerId');

            if (
                $currentCustomerId !== $newCustomerId &&
                $User->getUsername() === $currentCustomerId
            ) {
                $attributes['username'] = $newCustomerId;
            }
        }

        // defaults
        $User->setAttributes($attributes);

        // address
        $this->changeAddress($User, $attributes);

        // delivery Address
        $this->saveDeliveryAddress($User, $attributes);

        if (isset($attributes['address-communication'])) {
            $Address = $User->getStandardAddress();
            $mailList = $Address->getMailList();

            if (!empty($mailList)) {
                $User->setAttribute('email', reset($mailList));
            }
        }


        // group
        $groups = [];

        if (isset($attributes['group'])) {
            $groups[] = $attributes['group'];
            $User->setAttribute('mainGroup', $attributes['group']);
        } elseif (isset($attributes['group']) && $attributes['group'] === null) {
            $User->setAttribute('mainGroup', false);
        }

        if (!empty($attributes['groups'])) {
            $attributes['groups'] = explode(',', $attributes['groups']);
            $groups = array_merge($groups, $attributes['groups']);
        }

        if (!empty($groups)) {
            $User->setGroups($groups);
        }

        if (isset($attributes['address-firstname'])) {
            $User->setAttribute('firstname', $attributes['address-firstname']);
        }

        if (isset($attributes['address-lastname'])) {
            $User->setAttribute('lastname', $attributes['address-lastname']);
        }

        // user email


        // company flag
        // default address
        try {
            $Address = QUI\ERP\Utils\User::getUserERPAddress($User);
            $isCompany = $Address->getAttribute('company');
            $isCompany = !empty($isCompany);

            $User->setCompanyStatus($isCompany);
        } catch (QUI\Exception) {
        }

        $User->save();

        // status
        if (!empty($attributes['status']) && !$User->isActive()) {
            $SystemUser = QUI::getUsers()->getSystemUser();

            try {
                $User->activate(false, $SystemUser);
            } catch (QUI\Exception $Exception) {
                // if no password, set a password
                $userAttr = $User->getAttributes();

                if (!$userAttr['hasPassword']) {
                    $User->setPassword(
                        QUI\Utils\Security\Orthos::getPassword(),
                        $SystemUser
                    );

                    $User->activate(false, $SystemUser);
                } else {
                    throw $Exception;
                }
            }
        }
    }

    /**
     * @param QUI\Interfaces\Users\User $User
     * @param array $attributes
     *
     * @throws QUI\Exception
     * @throws QUI\Users\Exception
     */
    protected function changeAddress(QUI\Interfaces\Users\User $User, array $attributes): void
    {
        $Address = $User->getStandardAddress();

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
            if (isset($attributes['address-' . $addressAttribute])) {
                $Address->setAttribute($addressAttribute, $attributes['address-' . $addressAttribute]);
                unset($attributes['address-' . $addressAttribute]);
            }
        }

        // tel, fax, mobile
        if (!empty($attributes['address-communication'])) {
            $emails = array_filter($attributes['address-communication'], function ($entry) {
                if (!isset($entry['type'])) {
                    return false;
                }

                return $entry['type'] === 'email';
            });

            $mailHasChanged = false;

            foreach ($emails as $entry) {
                if (!empty($entry['no'])) {
                    $Address->editMail(0, $entry['no']);
                    $mailHasChanged = true;
                }
            }

            if ($mailHasChanged === false) {
                $Address->clearMail();
                $User->setAttribute('email', '');
            }


            $phones = array_filter($attributes['address-communication'], function ($entry) {
                if (!isset($entry['type'])) {
                    return false;
                }

                return $entry['type'] !== 'email';
            });

            if (!empty($phones)) {
                $Address->clearPhone();
            }

            foreach ($phones as $entry) {
                if (isset($entry['no'])) {
                    $Address->addPhone($entry);
                }
            }
        }

        if (isset($attributes['address-suffix'])) {
            $Address->setAddressSuffix($attributes['address-suffix']);
        }
    }

    /**
     * @param QUI\Interfaces\Users\User $User
     * @param $attributes
     * @throws QUI\Exception
     * @throws QUI\Permissions\Exception
     * @throws QUI\Users\Exception
     */
    protected function saveDeliveryAddress(QUI\Interfaces\Users\User $User, $attributes): void
    {
        // check if all is empty
        $isEmpty = true;

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
            if (!empty($attributes['address-delivery-' . $addressAttribute])) {
                $isEmpty = false;
                break;
            }
        }

        // dont save delivery address
        if ($isEmpty) {
            return;
        }

        // address save
        try {
            $addressId = $User->getAttribute('quiqqer.delivery.address');
            $Address = $User->getAddress($addressId);
        } catch (QUI\Exception) {
            // create one
            $Address = $User->addAddress();

            $User->setAttribute('quiqqer.delivery.address', $Address->getUUID());
        }

        foreach ($addressAttributes as $addressAttribute) {
            if (isset($attributes['address-delivery-' . $addressAttribute])) {
                $Address->setAttribute($addressAttribute, $attributes['address-delivery-' . $addressAttribute]);
                unset($attributes['address-delivery-' . $addressAttribute]);
            }
        }

        $Address->save();
        $User->save();
    }

    //region comments

    /**
     * Add a comment to the customer user comments
     *
     * @param QUI\Interfaces\Users\User $User
     * @param string $comment - comment message
     *
     * @throws QUI\Exception
     */
    public function addCommentToUser(QUI\Interfaces\Users\User $User, string $comment): void
    {
        QUI\Permissions\Permission::checkPermission('quiqqer.customer.editComments');

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
     * Edit a comment
     *
     * @param QUI\Interfaces\Users\User $User
     * @param $commentId - id of the comment
     * @param $commentSource - comment source
     * @param $message - new comment message
     *
     * @throws QUI\Exception
     */
    public function editComment(
        QUI\Interfaces\Users\User $User,
        $commentId,
        $commentSource,
        $message
    ): void {
        QUI\Permissions\Permission::checkPermission('quiqqer.customer.editComments');

        $comments = $User->getAttribute('comments');
        $comments = json_decode($comments, true);

        foreach ($comments as $key => $comment) {
            if (empty($comment['id']) || empty($comment['source'])) {
                continue;
            }

            if ($comment['source'] !== $commentSource) {
                continue;
            }

            if ($comment['id'] !== $commentId) {
                continue;
            }

            $comments[$key]['message'] = $message;
        }

        $User->setAttribute('comments', json_encode($comments));
        $User->save();
    }

    /**
     * @param QUI\Interfaces\Users\User $User
     * @return QUI\ERP\Comments
     */
    public function getUserComments(QUI\Interfaces\Users\User $User): QUI\ERP\Comments
    {
        $comments = $User->getAttribute('comments');
        $comments = json_decode($comments, true);

        if (is_array($comments)) {
            $Comments = new QUI\ERP\Comments($comments);
        } else {
            $Comments = new QUI\ERP\Comments();
        }

        return $Comments;
    }

    //endregion
}
