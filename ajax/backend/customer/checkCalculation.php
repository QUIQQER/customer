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
        $shipping  = false;
        $isCompany = false;

        $euVatId = $User->getAttribute('quiqqer.erp.euVatId');
        $taxId   = $User->getAttribute('quiqqer.erp.taxId');
        $chUID   = $User->getAttribute('quiqqer.erp.chUID');

        // default address
        try {
            $Address = QUI\ERP\Utils\User::getUserERPAddress($User);
            $address = $Address->getAttributes();

            $isCompany = $Address->getAttribute('company');
            $isCompany = !empty($isCompany);

            $address['text'] = $Address->getText();
        } catch (QUI\Exception $Exception) {
        }

        // shipping address
        try {
            $shippingId = $User->getAttribute('quiqqer.delivery.address');
            $Shipping   = $User->getAddress($shippingId);
            $shipping   = $Shipping->getAttributes();

            $shipping['text'] = $Shipping->getText();
        } catch (QUI\Exception $Exception) {
        }

        return [
            'status'    => $status,
            'address'   => $address,
            'shipping'  => $shipping,
            'isCompany' => $isCompany,
            'euVatId'   => $euVatId,
            'taxId'     => $taxId,
            'chUID'     => $chUID
        ];
    },
    ['userId'],
    'Permission::checkAdminUser'
);
