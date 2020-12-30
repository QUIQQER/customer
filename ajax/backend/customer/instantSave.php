<?php

/**
 * This file contains package_quiqqer_customer_ajax_backend_customer_instantSave
 */

/**
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_customer_ajax_backend_customer_instantSave',
    function ($userId, $data) {
        QUI\Permissions\Permission::checkPermission('quiqqer.customer.edit');

        $User = QUI::getUsers()->get($userId);
        $data = \json_decode($data, true);

        try {
            $Address = $User->getStandardAddress();
        } catch (QUI\Exception $Exception) {
            // create one
            $Address = $User->addAddress([]);
        }

        if (isset($data['username'])) {
            $User->setAttribute('username', $data['username']);
        }

        if (isset($data['firstname'])) {
            $User->setAttribute('firstname', $data['firstname']);
            $Address->setAttribute('firstname', $data['firstname']);
        }

        if (isset($data['lastname'])) {
            $User->setAttribute('lastname', $data['lastname']);
            $Address->setAttribute('lastname', $data['lastname']);
        }

        if (isset($data['email'])) {
            if (empty($data['email'])) {
                throw new QUI\Exception(
                    QUI::getLocale()->get('quiqqer/customer', 'exception.empty.mail.not.allowed')
                );
            }

            $User->setAttribute('email', $data['email']);
            $Address->editMail(0, $data['email']);
        }

        $Address->save();
        $User->save();
    },
    ['userId', 'data'],
    'Permission::checkAdminUser'
);
