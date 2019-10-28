<?php

namespace QUI\ERP\Customer;

use QUI;
use QUI\Utils\Singleton;

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
    public function getPanelCategories()
    {
        $cache = 'quiqqer/customer/panel/categories';

        try {
            $result = QUI\Cache\Manager::get($cache);
        } catch (QUI\Exception $Exception) {
            $files = [];

            $PackageHandler = QUI::getPackageManager();
            $packages       = $PackageHandler->getInstalled();

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

                $packageDir  = $Package->getDir();
                $customerXml = $packageDir.'/customer.xml';

                if (\file_exists($customerXml)) {
                    $files[] = $customerXml;
                }
            }


            $Settings = QUI\Utils\XML\Settings::getInstance();
            $result   = $Settings->getPanel($files);

            $result['categories'] = $result['categories']->toArray();

            foreach ($result['categories'] as $key => $category) {
                $result['categories'][$key]['items'] = $result['categories'][$key]['items']->toArray();
            }

            QUI\Cache\Manager::set($cache, $result);
        }


        // category translation
        $categories           = $result['categories'];
        $result['categories'] = [];

        foreach ($categories as $key => $category) {
            if (isset($category['title']) && \is_array($category['title'])) {
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
     */
    public function getPanelCategory($category)
    {
        $files = [];

        $PackageHandler = QUI::getPackageManager();
        $packages       = $PackageHandler->getInstalled();

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

            $packageDir  = $Package->getDir();
            $customerXml = $packageDir.'/customer.xml';

            if (\file_exists($customerXml)) {
                $files[] = $customerXml;
            }
        }

        $Settings = QUI\Utils\XML\Settings::getInstance();
        $result   = $Settings->getCategoriesHtml($files, $category);

        return $result;
    }
}
