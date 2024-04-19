<?php

namespace QUI\ERP\Customer;

use QUI;
use QUI\ERP\Api\NumberRangeInterface;

use function preg_match_all;

/**
 * Class NumberRange
 *
 * Number range for customer no.
 */
class NumberRange implements NumberRangeInterface
{
    /**
     * @param null|QUI\Locale $Locale
     *
     * @return string
     */
    public function getTitle($Locale = null): string
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get('quiqqer/customer', 'NumberRange.title');
    }

    /**
     * Return the current start range value
     *
     * If the next customer no. is set via config / backend settings, then it should be used
     * automatically for the next customer that is created.
     *
     * Otherwise, it is determined by fetching the highest current customerId from database.
     *
     * @return int
     */
    public function getRange(): int
    {
        // Get from config
        try {
            $Conf = QUI::getPackage('quiqqer/customer')->getConfig();
            $nextCustomerNoFromConfig = $Conf->get('customer', 'nextCustomerNo');

            if (!empty($nextCustomerNoFromConfig)) {
                return (int)$nextCustomerNoFromConfig;
            }
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        // Determine from current customer numbers in user table
        try {
            $tbl = QUI::getUsers()::table();
            $sql = "SELECT * FROM $tbl WHERE `customerId` REGEXP '.*[0-9]+$' ORDER BY cast(`customerId` as UNSIGNED) DESC LIMIT 1";
            $result = QUI::getDataBase()->fetchSQL($sql);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return 1;
        }

        if (empty($result)) {
            return 1;
        }

        preg_match_all('#([^\d]*)([0-9]+)#', $result[0]['customerId'], $matches);
        return (int)$matches[2][0] + 1;
    }

    /**
     * @param int $range
     * @return void
     */
    public function setRange(int $range): void
    {
        try {
            $Conf = QUI::getPackage('quiqqer/customer')->getConfig();
            $Conf->set('customer', 'nextCustomerNo', $range);
            $Conf->save();
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * Get the next customer no.
     *
     * @return int|string
     */
    public function getNextCustomerNo(): int|string
    {
        return $this->getRange();
    }

    /**
     * Get customer no. prefix
     *
     * @return string
     */
    public function getCustomerNoPrefix(): string
    {
        try {
            $Conf = QUI::getPackage('quiqqer/customer')->getConfig();
            $prefix = $Conf->get('customer', 'customerNoPrefix');

            if (!empty($prefix)) {
                return $prefix;
            }
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        return '';
    }
}
