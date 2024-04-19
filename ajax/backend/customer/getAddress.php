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
        $Address = null;

        try {
            $Address = $User->getStandardAddress();
        } catch (QUI\Exception $Exception) {
            if ($Exception->getCode() === 404) {
                $Address = $User->addAddress();
            }
        }

        if (!$Address) {
            return false;
        }

        $attributes = $Address->getAttributes();
        $attributes['id'] = $Address->getId();
        $attributes['uuid'] = $Address->getUUID();
        $attributes['text'] = $Address->getText();

        return $attributes;
    },
    ['userId'],
    'Permission::checkAdminUser'
);
