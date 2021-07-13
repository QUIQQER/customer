<?php

namespace QUI\ERP\Customer;

use QUI;
use QUI\Countries\Manager;
use QUI\GDPR\DataRequest\AbstractDataProvider;
use QUI\Users\Address;
use QUI\Users\User;

/**
 * Class QuiqqerUserDataProvider
 *
 * GDPR provider for QUIQQER customers data
 */
class GdprDataProvider extends AbstractDataProvider
{
    /**
     * Get general title of the data section / provider.
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->Locale->get(
            'quiqqer/customer',
            'GdprDataProvider.title'
        );
    }

    /**
     * Does this GDPR data provider have any data of the user?
     *
     * @return bool
     */
    public function hasUserData(): bool
    {
        // Check if user has customer id
        if (!empty($this->User->getAttribute('customerId'))) {
            return true;
        }

        // Check if user is in customer group
        $CustomerGroup = Utils::getInstance()->getCustomerGroup();

        if ($CustomerGroup && $this->User->isInGroup($CustomerGroup->getId())) {
            return true;
        }

        return false;
    }

    /**
     * Get description of the purpose (=reason why) the concrete user data is
     * used by this provider.
     *
     * @return string
     */
    public function getPurpose(): string
    {
        return $this->Locale->get(
            'quiqqer/customer',
            'GdprDataProvider.purpose'
        );
    }

    /**
     * Get list of recipients of the user data.
     *
     * @return string
     */
    public function getRecipients(): string
    {
        return $this->Locale->get(
            'quiqqer/customer',
            'GdprDataProvider.recipients'
        );
    }

    /**
     * Get description of the storage duration of the user data.
     *
     * If no concrete duration is available, the criteria for the storage duration shall be provided.
     *
     * @return string
     */
    public function getStorageDuration(): string
    {
        return $this->Locale->get(
            'quiqqer/customer',
            'GdprDataProvider.storageDuration'
        );
    }

    /**
     * Get description of the origin of the data.
     *
     * @return string
     */
    public function getOrigin(): string
    {
        return $this->Locale->get(
            'quiqqer/customer',
            'GdprDataProvider.origin'
        );
    }

    /**
     * Custom text for individual text relevant to GDPR data requests.
     *
     * @return string
     */
    public function getCustomText(): string
    {
        return '';
    }

    /**
     * Get all individual user data fields this provider has saved of the user.
     *
     * @return array - Key is title, value is concrete user data value
     */
    public function getUserDataFields(): array
    {
        $lg         = 'quiqqer/customer';
        $prefix     = 'GdprDataProvider.userDataField.';
        $dataFields = [];

        // Customer id
        $customerId = $this->User->getAttribute('customerId');

        if (!empty($customerId)) {
            $dataFields[$this->Locale->get($lg, $prefix.'customerId')] = $customerId;
        }

        // Addresses
        /** @var User $this- >User */
        $addresses = $this->User->getAddressList();

        $addressIndex = 1;

        /** @var Address $Address */
        foreach ($addresses as $k => $Address) {
            $addressLines = [
                $Address->getAttribute('salutation'),
                $Address->getAttribute('firstname').' '.$Address->getAttribute('lastname'),
                $Address->getAttribute('street_no'),
                $Address->getAttribute('zip').' '.$Address->getAttribute('city'),
            ];

            $countryCode = $Address->getAttribute('country');

            if (!empty($countryCode)) {
                try {
                    $Country        = Manager::get($countryCode);
                    $addressLines[] = $Country->getName($this->Locale);
                } catch (\Exception $Exception) {
                    QUI\System\Log::writeException($Exception);
                }
            }

            // Phone
            $numbers = $Address->getPhoneList();

            if (!empty($numbers)) {
                $numberList = [];

                foreach ($numbers as $entry) {
                    if (!empty($entry['no'])) {
                        $numberList[] = $entry['no'];
                    }
                }

                if (!empty($numberList)) {
                    $addressLines[] = '<br/>'.$this->Locale->get(
                            $lg,
                            $prefix.'numbers',
                            [
                                'numbers' => \implode("<br/>", $numberList)
                            ]
                        );
                }
            }

            // Email
            $emailAddresses = $Address->getMailList();

            if (!empty($emailAddresses)) {
                $addressLines[] = '<br/>'.$this->Locale->get(
                        $lg,
                        $prefix.'emailAddresses',
                        [
                            'addresses' => \implode("<br/>", $emailAddresses)
                        ]
                    );
            }

            $dataFields[$this->Locale->get($lg, $prefix.'address', ['no' => $addressIndex++])] = \implode(
                "<br/>",
                $addressLines
            );
        }

        return $dataFields;
    }

    /**
     * Delete all user data this provider has saved.
     *
     * Only has to delete GDPR relevant user data and user data that is not required
     * to be kept for legal purposes (e.g. invoice, tax etc.).
     *
     * @return string[] - List of data fields that were deleted.
     */
    public function deleteUserData(): array
    {
        // @TODO: Implement deleteUserData() method.

        return [];
    }
}
