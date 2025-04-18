<?php

namespace QUI\ERP\Customer;

use QUI;
use QUI\Exception;

use function current;
use function method_exists;

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
    public function getCategoriesForCustomerCreate(): array
    {
        $categories = [];

        $categories[] = [
            'text' => QUI::getLocale()->get('quiqqer/customer', 'customer.create.category.details'),
            'textimage' => 'fa fa-id-card',
            'require' => ''
        ];

        $categories[] = [
            'text' => QUI::getLocale()->get('quiqqer/customer', 'customer.create.category.address'),
            'textimage' => 'fa fa-address-book',
            'require' => ''
        ];

        return $categories;
    }

    /**
     * @param int|string $uid
     * @return int
     */
    public function getPaymentTimeForUser(int | string $uid): int
    {
        $defaultPaymentTime = 0;

        if (class_exists('QUI\ERP\Accounting\Invoice\Settings')) {
            $defaultPaymentTime = (int)QUI\ERP\Accounting\Invoice\Settings::getInstance()
                ->get('invoice', 'time_for_payment');
        }

        try {
            $User = QUI::getUsers()->get($uid);
        } catch (QUI\Exception) {
            // default time for payment
            return $defaultPaymentTime;
        }

        $permission = $User->getPermission('quiqqer.invoice.timeForPayment', 'maxInteger');

        if (empty($permission)) {
            $permission = $defaultPaymentTime;
        }

        if ($User->getAttribute('quiqqer.erp.customer.payment.term')) {
            $permission = $User->getAttribute('quiqqer.erp.customer.payment.term');
        }

        return $permission;
    }

    /**
     * Return the global customer group
     *
     * @return QUI\Groups\Everyone|QUI\Groups\Group|QUI\Groups\Guest|null
     * @throws Exception
     */
    public function getCustomerGroup(): QUI\Groups\Group | QUI\Groups\Everyone | QUI\Groups\Guest | null
    {
        $Package = QUI::getPackage('quiqqer/customer');
        $Config = $Package->getConfig();
        $groupId = $Config->getValue('customer', 'groupId');

        if (empty($groupId)) {
            return null;
        }

        return QUI::getGroups()->get($groupId);
    }

    /**
     * Get e-mail address of a customer user in the following order:
     *
     * 1. Email address of erp address
     * 2. Email address of default address
     * 3. Email address of QUIQQER user
     * 4. Email address of contact address
     *
     * @param QUI\Interfaces\Users\User $Customer
     * @return string|false - Email address or false if no address exists
     */
    public function getEmailByCustomer(QUI\Interfaces\Users\User $Customer): bool | string
    {
        $email = $this->getEmailByErpAddress($Customer);

        if (!empty($email)) {
            return $email;
        }

        $email = $this->getEmailByStandardAddress($Customer);

        if (!empty($email)) {
            return $email;
        }

        $email = $this->getEmailByCustomerObject($Customer);

        if (!empty($email)) {
            return $email;
        }

        return $this->getEmailByContactPersonAddress($Customer);
    }

    /**
     * Get e-mail address of the CONTACT PERSON of a customer user in the following order:
     *
     * 1. Email address of contact address
     * 2. Email address of default address
     * 3. Email address of QUIQQER user
     *
     * @param QUI\Interfaces\Users\User $Customer
     * @return string|false - Email address or false if no address exists
     */
    public function getContactEmailByCustomer(QUI\Interfaces\Users\User $Customer): bool | string
    {
        $email = $this->getEmailByContactPersonAddress($Customer);

        if (!empty($email)) {
            return $email;
        }

        $email = $this->getEmailByStandardAddress($Customer);

        if (!empty($email)) {
            return $email;
        }

        return $this->getEmailByCustomerObject($Customer);
    }

    /**
     * Get customer e-mail address from customer standard address.
     *
     * @param QUI\Interfaces\Users\User $Customer
     * @return string|false
     */
    protected function getEmailByErpAddress(QUI\Interfaces\Users\User $Customer): bool | string
    {
        $address = $Customer->getAttribute('quiqqer.erp.address');

        try {
            $Address = $Customer->getAddress($address);
            $mailAddresses = $Address->getMailList();

            if (!empty($mailAddresses)) {
                return current($mailAddresses);
            }
        } catch (\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        return $this->getEmailByStandardAddress($Customer);
    }

    /**
     * Get customer e-mail address from customer standard address.
     *
     * @param QUI\Interfaces\Users\User $Customer
     * @return string|false
     */
    protected function getEmailByStandardAddress(QUI\Interfaces\Users\User $Customer): bool | string
    {
        if (!method_exists($Customer, 'getStandardAddress')) {
            return false;
        }

        try {
            /** @var QUI\Users\Address $StandardAddress */
            $StandardAddress = $Customer->getStandardAddress();
            $mailAddresses = $StandardAddress->getMailList();

            if (!empty($mailAddresses)) {
                return current($mailAddresses);
            }
        } catch (\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        return false;
    }

    /**
     * Get customer e-mail address from customer contact address
     *
     * @param QUI\Interfaces\Users\User $Customer
     * @return string|false
     */
    protected function getEmailByContactPersonAddress(QUI\Interfaces\Users\User $Customer): bool | string
    {
        try {
            if (!($Customer instanceof QUI\Users\User)) {
                $Customer = QUI::getUsers()->get($Customer->getUUID());
            }

            $contactPersonAddressId = $Customer->getAttribute('quiqqer.erp.customer.contact.person');

            if (!empty($contactPersonAddressId)) {
                $ContactAddress = new QUI\Users\Address($Customer, $contactPersonAddressId);
                $mailAddresses = $ContactAddress->getMailList();

                if (!empty($mailAddresses)) {
                    return current($mailAddresses);
                }
            }
        } catch (\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        return false;
    }

    /**
     * Get customer e-mail address directly from customer object
     *
     * @param QUI\Interfaces\Users\User $Customer
     * @return string|false
     */
    protected function getEmailByCustomerObject(QUI\Interfaces\Users\User $Customer): bool | string
    {
        if (!empty($Customer->getAttribute('email'))) {
            return $Customer->getAttribute('email');
        }

        return false;
    }

    /**
     * Get contact person address.
     *
     * @param QUI\Interfaces\Users\User $Customer
     * @return QUI\ERP\Address|false
     */
    public function getContactPersonAddress(QUI\Interfaces\Users\User $Customer): bool | QUI\ERP\Address
    {
        try {
            $contactPersonAddressId = $Customer->getAttribute('quiqqer.erp.customer.contact.person');

            if (!empty($contactPersonAddressId)) {
                if (!($Customer instanceof QUI\Users\User)) {
                    $Customer = QUI::getUsers()->get($Customer->getUUID());
                }

                $Address = new QUI\Users\Address($Customer, $contactPersonAddressId);

                return new QUI\ERP\Address($Address->getAttributes(), $Customer);
            }
        } catch (\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        return false;
    }
}
