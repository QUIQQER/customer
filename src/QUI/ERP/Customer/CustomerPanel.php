<?php

namespace QUI\ERP\Customer;

use QUI;
use QUI\Utils\Singleton;

use function file_exists;
use function is_array;

/**
 * Class CustomerPanel
 *
 * @package QUI\ERP\Customer
 */
class CustomerPanel extends Singleton
{
    /**
     * @return array
     */
    public function getPanelCategories(): array
    {
        $cache = 'quiqqer/customer/panel/categories';

        try {
            $result = QUI\Cache\Manager::get($cache);
        } catch (QUI\Exception) {
            $files = [];

            $PackageHandler = QUI::getPackageManager();
            $packages = $PackageHandler->getInstalled();

            foreach ($packages as $package) {
                try {
                    $Package = $PackageHandler->getInstalledPackage($package['name']);
                } catch (QUI\Exception $Exception) {
                    QUI\System\Log::addDebug($Exception->getMessage());
                    continue;
                }

                if (!$Package->isQuiqqerPackage()) {
                    continue;
                }

                $packageDir = $Package->getDir();
                $customerXml = $packageDir . '/customer.xml';

                if (file_exists($customerXml)) {
                    $files[] = $customerXml;
                }
            }


            $Settings = QUI\Utils\XML\Settings::getInstance();
            $Settings->setXMLPath('//quiqqer/window');

            $result = $Settings->getPanel($files);

            $result['categories'] = $result['categories']->toArray();

            foreach ($result['categories'] as $key => $category) {
                $result['categories'][$key]['items'] = $result['categories'][$key]['items']->toArray();
            }

            QUI\Cache\Manager::set($cache, $result);
        }


        // category translation
        $categories = $result['categories'];
        $result['categories'] = [];

        foreach ($categories as $category) {
            if (isset($category['title']) && is_array($category['title'])) {
                $category['text'] = QUI::getLocale()->get(
                    $category['title'][0],
                    $category['title'][1]
                );

                $category['title'] = QUI::getLocale()->get(
                    $category['title'][0],
                    $category['title'][1]
                );
            }

            if (empty($category['text']) && !empty($category['title'])) {
                $category['text'] = $category['title'];
            }

            $result['categories'][] = $category;
        }

        return $result;
    }

    /**
     * @param string $category
     * @return string
     */
    public function getPanelCategory(string $category): string
    {
        $files = [];

        $PackageHandler = QUI::getPackageManager();
        $packages = $PackageHandler->getInstalled();

        foreach ($packages as $package) {
            try {
                $Package = $PackageHandler->getInstalledPackage($package['name']);
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::addDebug($Exception->getMessage());
                continue;
            }

            if (!$Package->isQuiqqerPackage()) {
                continue;
            }

            $packageDir = $Package->getDir();
            $customerXml = $packageDir . '/customer.xml';

            if (file_exists($customerXml)) {
                $files[] = $customerXml;
            }
        }

        $Settings = QUI\Utils\XML\Settings::getInstance();
        $Settings->setXMLPath('//quiqqer/window');

        return $Settings->getCategoriesHtml($files, $category);
    }
}
