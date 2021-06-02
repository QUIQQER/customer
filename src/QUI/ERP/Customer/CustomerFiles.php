<?php

namespace QUI\ERP\Customer;

use QUI;
use QUI\Permissions\Permission;
use QUI\UserDownloads\DownloadEntry;
use QUI\UserDownloads\Handler as UserDownloadsHandler;

/**
 * Class CustomerFiles
 *
 * @package QUI\ERP\Customer
 */
class CustomerFiles
{
    /**
     * User attributes specific to customer files
     */
    const USER_ATTRIBUTE_DOWNLOAD_ENTRY_ID = 'quiqqer.erp.customer.download_entry_id';

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
    public static function getFolderPath(QUI\Interfaces\Users\User $User): string
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
    public static function getFileList($customerId): array
    {
        Permission::checkPermission('quiqqer.customer.fileView');

        try {
            $Customer = QUI::getUsers()->get($customerId);

            self::createFolder($Customer);
        } catch (QUI\Exception $Exception) {
            return [];
        }

        $customerDir            = self::getFolderPath($Customer);
        $files                  = QUI\Utils\System\File::readDir($customerDir);
        $result                 = [];
        $userDownloadsInstalled = QUI::getPackageManager()->isInstalled('quiqqer/user-downloads');

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

            $info['icon']         = QUI\Projects\Media\Utils::getIconByExtension($info['extension']);
            $info['userDownload'] = false;

            if ($userDownloadsInstalled) {
                $info['userDownload'] = self::isFileInDownloadEntry(
                    $customerId,
                    $customerDir.DIRECTORY_SEPARATOR.$file
                );
            }

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
    public static function deleteFiles($customerId, array $files = [])
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

    // region Download entry

    /**
     * Adds a customer file to the customer's download entry.
     *
     * This makes the file available for download in the user's frontend profile!
     *
     * @param int $customerId
     * @param string $file
     * @return void
     *
     * @throws QUI\Exception
     */
    public static function addFileToDownloadEntry(int $customerId, string $file): void
    {
        if (!QUI::getPackageManager()->isInstalled('quiqqer/user-downloads')) {
            throw new QUI\Exception('This feature requires quiqqer/user-downloads to be installed.');
        }

        $fileList = self::getFileList($customerId);
        $fileName = false;
        $fileExt  = false;

        foreach ($fileList as $entry) {
            if ($entry['basename'] === $file) {
                $fileName = $entry['filename'];
                $fileExt  = $entry['extension'];
                break;
            }
        }

        if (!$fileName || !$fileExt) {
            throw new QUI\Exception('File '.$file.' was not found in user files.');
        }

        $DownloadEntry = self::createDownloadEntry($customerId);
        $langs         = QUI::availableLanguages();

        $downloadUrl = URL_OPT_DIR.'quiqqer/customer/bin/backend/download.php?';
        $query       = \http_build_query([
            'file'       => $fileName,
            'extension'  => $fileExt,
            'customerId' => $customerId
        ]);

        $DownloadEntry->addUrl(
            $downloadUrl.$query,
            \array_fill_keys($langs, $file)
        );

        $DownloadEntry->update();
    }

    /**
     * Removes a customer file from the customer's download entry.
     *
     * @param int $customerId
     * @param string $file - File name
     * @return void
     *
     * @throws QUI\Exception
     */
    public static function removeFileFromDownloadEntry(int $customerId, string $file): void
    {
        if (!QUI::getPackageManager()->isInstalled('quiqqer/user-downloads')) {
            throw new QUI\Exception('This feature requires quiqqer/user-downloads to be installed.');
        }

        $fileList = self::getFileList($customerId);
        $fileName = false;
        $fileExt  = false;

        foreach ($fileList as $entry) {
            if ($entry['basename'] === $file) {
                $fileName = $entry['filename'];
                $fileExt  = $entry['extension'];
                break;
            }
        }

        if (!$fileName || !$fileExt) {
            throw new QUI\Exception('File '.$file.' was not found in user files.');
        }

        $DownloadEntry = self::getDownloadEntry($customerId);

        $downloadUrl = URL_OPT_DIR.'quiqqer/customer/bin/backend/download.php?';
        $query       = \http_build_query([
            'file'       => $fileName,
            'extension'  => $fileExt,
            'customerId' => $customerId
        ]);

        $DownloadEntry->removeUrl($downloadUrl.$query);
        $DownloadEntry->update();

        $itemCount = \count($DownloadEntry->getUrls()) + \count($DownloadEntry->getQuiqqerMediaUrls());

        if ($itemCount === 0) {
            self::deleteDownloadEntry($customerId);
        }
    }

    /**
     * Check if a file is avaiable for the customer to download via frontend profile
     *
     * @param int $customerId
     * @param string $filePath - Fully qualified file path
     * @return bool
     */
    public static function isFileInDownloadEntry(int $customerId, string $filePath): bool
    {
        if (!QUI::getPackageManager()->isInstalled('quiqqer/user-downloads')) {
            return false;
        }

        try {
            $DownloadEntry = self::getDownloadEntry($customerId);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return false;
        }

        if (!$DownloadEntry) {
            return false;
        }

        $pathInfo = \pathinfo($filePath);
        $fileName = $pathInfo['filename'];
        $fileExt  = $pathInfo['extension'];

        foreach ($DownloadEntry->getUrls() as $entry) {
            $url = $entry['url'];

            if (\mb_strpos($url, 'file='.$fileName) !== false &&
                \mb_strpos($url, 'extension='.$fileExt) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get customer DownloadEntry for customer fiels or false if no entry exists
     *
     * @param int $customerId
     * @return DownloadEntry|false
     *
     * @throws QUI\Exception
     */
    public static function getDownloadEntry(int $customerId)
    {
        if (!QUI::getPackageManager()->isInstalled('quiqqer/user-downloads')) {
            throw new QUI\Exception('This feature requires quiqqer/user-downloads to be installed.');
        }

        $Customer        = QUI::getUsers()->get($customerId);
        $downloadEntryId = $Customer->getAttribute(self::USER_ATTRIBUTE_DOWNLOAD_ENTRY_ID);

        if (empty($downloadEntryId)) {
            return false;
        }

        return UserDownloadsHandler::getDownloadEntryById($downloadEntryId);
    }

    /**
     * Create a DownloadEntry for customer files.
     *
     * @param int $customerId
     * @return DownloadEntry
     *
     * @throws QUI\Exception
     */
    public static function createDownloadEntry(int $customerId): DownloadEntry
    {
        if (!QUI::getPackageManager()->isInstalled('quiqqer/user-downloads')) {
            throw new QUI\Exception('This feature requires quiqqer/user-downloads to be installed.');
        }

        $DownloadEntry = self::getDownloadEntry($customerId);

        if ($DownloadEntry) {
            return $DownloadEntry;
        }

        $User          = QUI::getUsers()->get($customerId);
        $DownloadEntry = new DownloadEntry($User);
        $Locale        = QUI::getLocale();

        foreach (QUI::availableLanguages() as $lang) {
            $DownloadEntry->setTitle($lang, $Locale->getByLang($lang, 'quiqqer/customer', 'DownloadEntry.title'));
            $DownloadEntry->setDescription(
                $lang,
                $Locale->getByLang($lang, 'quiqqer/customer', 'DownloadEntry.description')
            );
        }

        $downloadEntryId = UserDownloadsHandler::addDownloadEntry($DownloadEntry);

        $User->setAttribute(self::USER_ATTRIBUTE_DOWNLOAD_ENTRY_ID, $downloadEntryId);
        $User->save(QUI::getUsers()->getSystemUser());

        return UserDownloadsHandler::getDownloadEntryById($downloadEntryId);
    }

    /**
     * Delete customer DownloadEntry.
     *
     * This makes ALL customer files that have been made available for download in the frontendusers profile
     * UNAVAILABLE for the customer.
     *
     * @param int $customerId
     *
     * @throws QUI\Database\Exception
     * @throws QUI\Exception
     * @throws QUI\UserDownloads\Exception
     */
    public static function deleteDownloadEntry(int $customerId): void
    {
        if (!QUI::getPackageManager()->isInstalled('quiqqer/user-downloads')) {
            throw new QUI\Exception('This feature requires quiqqer/user-downloads to be installed.');
        }

        $DownloadEntry = self::getDownloadEntry($customerId);

        if ($DownloadEntry) {
            $User = QUI::getUsers()->get($customerId);
            $DownloadEntry->delete();

            $User->setAttribute(self::USER_ATTRIBUTE_DOWNLOAD_ENTRY_ID, false);
            $User->save(QUI::getUsers()->getSystemUser());
        }
    }

    // endregion
}
