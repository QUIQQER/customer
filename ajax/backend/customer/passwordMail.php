<?php

/**
 * This file contains package_quiqqer_customer_ajax_backend_customer_passwordMail
 */

/**
 * Send the customer a password reset mail
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_customer_ajax_backend_customer_passwordMail',
    function ($userId) {
        $User = QUI::getUsers()->get($userId);

        $Handler = QUI\Users\Auth\Handler::getInstance();
        $Handler->sendPasswordResetVerificationMail($User);

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get('quiqqer/customer', 'message.customer.password.mail.send')
        );
    },
    ['userId'],
    'Permission::checkAdminUser'
);
