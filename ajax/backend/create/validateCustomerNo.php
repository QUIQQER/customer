<?php

/**
 * Validate customer no.
 *
 * @param string|int $customerNo
 * @return integer
 */

use QUI\ERP\Customer\Customers;
use QUI\ERP\Customer\NumberRange;

QUI::getAjax()->registerFunction(
    'package_quiqqer_customer_ajax_backend_create_validateCustomerNo',
    function ($customerNo) {
        $customerNo = (string)$customerNo;

        try {
            $customerGroupId = Customers::getInstance()->getCustomerGroupId();
        } catch (Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
            return;
        }

        $QueryBuilder = QUI::getQueryBuilder();
        $username = QUI\Utils\Doctrine::quoteIdentifier('username');
        $customerId = QUI\Utils\Doctrine::quoteIdentifier('customerId');
        $usergroup = QUI\Utils\Doctrine::quoteIdentifier('usergroup');

        $result = $QueryBuilder
            ->select(QUI\Utils\Doctrine::quoteIdentifier('id'))
            ->from(QUI\Utils\Doctrine::quoteIdentifier(QUI::getUsers()::table()))
            ->where($QueryBuilder->expr()->or(
                $QueryBuilder->expr()->eq($username, ':customerNo'),
                $QueryBuilder->expr()->and(
                    $QueryBuilder->expr()->eq($customerId, ':customerNo'),
                    $QueryBuilder->expr()->like($usergroup, ':customerGroup')
                )
            ))
            ->setParameter('customerNo', $customerNo)
            ->setParameter('customerGroup', '%,' . $customerGroupId . ',%')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        if (empty($result)) {
            return;
        }

        $NumberRange = new NumberRange();
        $prefix = $NumberRange->getCustomerNoPrefix();

        throw new \QUI\ERP\Customer\Exception([
            'quiqqer/customer',
            'exception.customer_no_already_exists',
            [
                'customerNo' => $prefix . $customerNo
            ]
        ]);
    },
    ['customerNo'],
    'Permission::checkAdminUser'
);
