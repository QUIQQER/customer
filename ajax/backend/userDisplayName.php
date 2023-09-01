<?php

/**
 * This file contains package_quiqqer_customer_ajax_backend_userDisplayName
 */

/**
 * Return the display name for a user control
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_customer_ajax_backend_userDisplayName',
    function ($userId, $showAddressName) {
        $User = QUI::getUsers()->get($userId);

        if (QUI::getUsers()->isNobodyUser($User)) {
            return '';
        }

        if (empty($showAddressName)) {
            return $User->getName();
        }

        $userData = $User->getAttributes();
        $Address = $User->getStandardAddress();
        $addressData = [];

        if ($Address && (empty($userData['firstname']) || empty($userData['lastname']))) {
            $name = [];

            if ($Address->getAttribute('firstname')) {
                $name[] = $Address->getAttribute('firstname');
            }

            if ($Address->getAttribute('lastname')) {
                $name[] = $Address->getAttribute('lastname');
            }

            if (!empty($name)) {
                $addressData[] = implode(' ', $name);
            }
        }

        if ($Address) {
            $addressData[] = $Address->getText();
        }

        $result = implode(' - ', $addressData);
        $result = trim($result, ' - ');
        $result = trim($result);

        if (!empty($result)) {
            $result = $User->getUsername() . ': ' . $result;
        } else {
            $result = $User->getUsername();
        }

        return $result;
    },
    ['userId', 'showAddressName'],
    'Permission::checkAdminUser'
);
