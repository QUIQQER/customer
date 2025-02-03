<?php

/**
 * This file contains package_quiqqer_customer_ajax_backend_create_createCustomer
 */

/**
 * Create a new customer
 *
 * @return integer
 */

QUI::$Ajax->registerFunction(
    'package_quiqqer_customer_ajax_backend_create_createCustomer',
    function ($customerId, $address, $groups, $attributes) {
        $address = json_decode($address, true);
        $groups = json_decode($groups, true);
        $attributes = json_decode($attributes, true);

        $User = QUI\ERP\Customer\Customers::getInstance()->createCustomer(
            $customerId,
            $address,
            $groups
        );

        if (is_array($attributes)) {
            $User->setAttributes($attributes);
            $User->save();
        }

        return $User->getUUID();
    },
    ['customerId', 'address', 'groups', 'attributes'],
    'Permission::checkAdminUser'
);
