<?php

namespace QUI\ERP\Customer;

use QUI;
use QUI\Permissions\Permission;

/**
 * Class CustomerFiles
 *
 * @package QUI\ERP\Customer
 */
class CustomerFiles
{
    /**
     * @param QUI\Interfaces\Users\User $User
     * @throws QUI\Exception
     */
    public static function createFolder(QUI\Interfaces\Users\User $User)
    {
        if (!$User->getId()) {
            return;
        }

        $customerDir = self::getFolderPath($User);

        if (empty($customerDir)) {
            throw new QUI\Exception('Could not create customer folder');
        }

        if (!\is_dir($customerDir)) {
            QUI\Utils\System\File::mkdir($customerDir);
        }
    }

    /**
     * @param QUI\Interfaces\Users\User $User
     * @return string
     */
    public static function getFolderPath(QUI\Interfaces\Users\User $User)
    {
        try {
            $Package = QUI::getPackageManager()->getInstalledPackage('quiqqer/customer');
            $varDir  = $Package->getVarDir();
        } catch (QUI\Exception $Exception) {
            return '';
        }

        return $varDir.$User->getId();
    }

    /**
     * Return the file list from the customer
     *
     * @param $customerId
     * @return array
     *
     * @throws QUI\Permissions\Exception
     */
    public static function getFileList($customerId)
    {
        Permission::checkPermission('quiqqer.customer.fileView');

        try {
            $Customer = QUI::getUsers()->get($customerId);

            self::createFolder($Customer);
        } catch (QUI\Exception $Exception) {
            return [];
        }

        $customerDir = self::getFolderPath($Customer);
        $files       = QUI\Utils\System\File::readDir($customerDir);
        $result      = [];

        foreach ($files as $file) {
            try {
                $info = QUI\Utils\System\File::getInfo(
                    $customerDir.DIRECTORY_SEPARATOR.$file
                );
            } catch (\Exception $Exception) {
                $info = [
                    'basename'           => $file,
                    'filesize'           => '---',
                    'filesize_formatted' => '---',
                    'extension'          => ''
                ];
            }

            if ($info['filesize'] !== '---') {
                $info['filesize_formatted'] = QUI\Utils\System\File::formatSize($info['filesize']);
            }

            $info['icon'] = QUI\Projects\Media\Utils::getIconByExtension($info['extension']);

            $result[] = $info;
        }

        \usort($result, function ($a, $b) {
            return \strnatcmp($a['basename'], $b['basename']);
        });

        return $result;
    }

    /**
     * @param string|integer $customerId
     * @param array $files
     *
     * @throws QUI\Permissions\Exception
     */
    public static function deleteFiles($customerId, $files = [])
    {
        Permission::checkPermission('quiqqer.customer.fileEdit');

        try {
            $Customer = QUI::getUsers()->get($customerId);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addDebug($Exception->getMessage());

            return;
        }

        $customerDir = self::getFolderPath($Customer);

        foreach ($files as $fileName) {
            $file = $customerDir.DIRECTORY_SEPARATOR.$fileName;

            if (\file_exists($file)) {
                \unlink($file);
            }
        }
    }

    /**
     * Add a file to the customer
     *
     * @param $customerId
     * @param $file
     *
     * @throws QUI\Exception
     * @throws QUI\Permissions\Exception
     */
    public static function addFileToCustomer($customerId, $file)
    {
        Permission::checkPermission('quiqqer.customer.fileUpload');

        if (!\file_exists($file)) {
            throw new QUI\Exception('File not found', 404);
        }

        try {
            $Customer = QUI::getUsers()->get($customerId);
            $fileInfo = QUI\Utils\System\File::getInfo($file);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addDebug($Exception->getMessage());

            return;
        }

        $customerDir = self::getFolderPath($Customer);

        rename(
            $file,
            $customerDir.DIRECTORY_SEPARATOR.$fileInfo['basename']
        );
    }
}
