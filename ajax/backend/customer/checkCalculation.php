<?php

/**
 * This file contains package_quiqqer_customer_ajax_backend_customer_checkCalculation
 */

/**
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_customer_ajax_backend_customer_checkCalculation',
    function ($userId) {
        $User = QUI::getUsers()->get($userId);
        $User->setAttribute('quiqqer.erp.isNettoUser', false);

        $status    = QUI\ERP\Utils\User::getBruttoNettoUserStatus($User);
        $address   = false;
        $isCompany = false;

        try {
            $Address = QUI\ERP\Utils\User::getUserERPAddress($User);
            $address = $Address->getAttributes();

            $isCompany = $Address->getAttribute('company');
            $isCompany = !empty($isCompany);

            $address['text'] = $Address->getText();
        } catch (QUI\Exception $Exception) {
        }

        return [
            'status'    => $status,
            'address'   => $address,
            'isCompany' => $isCompany
        ];
    },
    ['userId'],
    'Permission::checkAdminUser'
);
