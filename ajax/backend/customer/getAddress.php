<?php

/**
 * This file contains package_quiqqer_customer_ajax_backend_customer_getCategory
 */

/**
 * Return one customer panel from customer categories
 *
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_customer_ajax_backend_customer_getAddress',
    function ($userId) {
        $User = QUI::getUsers()->get($userId);

        try {
            $Address = $User->getStandardAddress();
        } catch (QUI\Exception $Exception) {
            $Address = $User->addAddress();
        }

        $attributes         = $Address->getAttributes();
        $attributes['id']   = $Address->getId();
        $attributes['text'] = $Address->getText();

        return $attributes;
    },
    ['userId'],
    'Permission::checkAdminUser'
);