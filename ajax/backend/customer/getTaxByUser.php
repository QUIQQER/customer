<?php

/**
 * This file contains package_quiqqer_customer_ajax_backend_customer_getTaxByUser
 */

/**
 * Return the tax of this user
 *
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_customer_ajax_backend_customer_getTaxByUser',
    function ($userId) {
        $User = QUI::getUsers()->get($userId);
        $Tax  = QUI\ERP\Tax\Utils::getTaxByUser($User);
        $Area = $Tax->getArea();

        return [
            'id'   => $Tax->getId(),
            'vat'  => $Tax->getValue(),
            'area' => [
                'id'    => $Area->getId(),
                'title' => $Area->getTitle()
            ]
        ];
    },
    ['userId'],
    'Permission::checkAdminUser'
);
