<?php

namespace QUI\ERP\Customer;

use QUI;
use QUI\Exception;
use QUI\Permissions\Permission;
use QUI\UserDownloads\DownloadEntry;
use QUI\UserDownloads\Handler as UserDownloadsHandler;

use function array_fill_keys;
use function count;
use function file_exists;
use function filemtime;
use function hash;
use function http_build_query;
use function is_dir;
use function is_readable;
use function mb_strpos;
use function pathinfo;
use function strnatcmp;
use function unlink;
use function urldecode;
use function usort;

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
     * @return string
     *
     * @throws QUI\Exception
     */
    public static function getFolderPath(QUI\Interfaces\Users\User $User): string
    {
        if (!$User->getId()) {
            throw new QUI\Exception('Users without ID cannot have a customer file folder.');
        }

        try {
            $Package = QUI::getPackageManager()->getInstalledPackage('quiqqer/customer');
            $varDir = $Package->getVarDir();
        } catch (QUI\Exception) {
            return '';
        }

        $fileDir = $varDir . $User->getId();

        QUI\Utils\System\File::mkdir($fileDir);

        if (!is_dir($fileDir) || !is_readable($fileDir)) {
            throw new QUI\Exception('Users without ID cannot have a customer file folder.');
        }

        return $fileDir;
    }

    /**
     * Return the file list from the customer
     *
     * @param $customerId
     * @return array
     *
     * @throws QUI\Permissions\Exception|QUI\Exception
     */
    public static function getFileList($customerId): array
    {
        Permission::checkPermission('quiqqer.customer.fileView');

        try {
            $Customer = QUI::getUsers()->get($customerId);
        } catch (QUI\Exception) {
            return [];
        }

        $customerDir = self::getFolderPath($Customer);
        $files = QUI\Utils\System\File::readDir($customerDir);
        $result = [];
        $userDownloadsInstalled = QUI::getPackageManager()->isInstalled('quiqqer/user-downloads');

        foreach ($files as $file) {
            try {
                $info = QUI\Utils\System\File::getInfo(
                    $customerDir . DIRECTORY_SEPARATOR . $file
                );
            } catch (\Exception) {
                $info = [
                    'basename' => $file,
                    'filesize' => '---',
                    'filesize_formatted' => '---',
                    'extension' => ''
                ];
            }

            $filePath = $customerDir . DIRECTORY_SEPARATOR . $file;

            $info['uploadTime'] = filemtime($filePath);

            if ($info['filesize'] !== '---') {
                $info['filesize_formatted'] = QUI\Utils\System\File::formatSize($info['filesize']);
            }

            $info['icon'] = QUI\Projects\Media\Utils::getIconByExtension($info['extension']);
            $info['userDownload'] = false;

            if ($userDownloadsInstalled) {
                $info['userDownload'] = self::isFileInDownloadEntry($customerId, $filePath);
            }

            $result[] = $info;
        }

        usort($result, function ($a, $b) {
            return strnatcmp($a['basename'], $b['basename']);
        });

        foreach ($result as $k => $file) {
            $result[$k]['hash'] = hash('sha256', $file['basename']);
        }

        return $result;
    }

    /**
     * @param integer|string $customerId
     * @param array $files
     *
     * @throws QUI\Permissions\Exception|QUI\Exception
     */
    public static function deleteFiles(int|string $customerId, array $files = []): void
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
            $file = $customerDir . DIRECTORY_SEPARATOR . $fileName;

            if (file_exists($file)) {
                unlink($file);
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
    public static function addFileToCustomer($customerId, $file): void
    {
        Permission::checkPermission('quiqqer.customer.fileUpload');

        if (!file_exists($file)) {
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
            $customerDir . DIRECTORY_SEPARATOR . $fileInfo['basename']
        );
    }

    /**
     * Get file data by file hash
     *
     * @param int $customerId
     * @param string $fileHash
     * @return false|array - File data or false if file is not found in customer files
     *
     * @throws QUI\Permissions\Exception
     * @throws Exception
     */
    public static function getFileByHash(int $customerId, string $fileHash): bool|array
    {
        $files = self::getFileList($customerId);

        foreach ($files as $file) {
            if ($file['hash'] === $fileHash) {
                return $file;
            }
        }

        return false;
    }

    // region Download entry

    /**
     * Adds a customer file to the customer's download entry.
     *
     * This makes the file available for download in the user's frontend profile!
     *
     * @param int $customerId
     * @param string $file - The filename of the file (excluding the path!); file must already exist
     * in the user files (i.e. added via addFileToCustomer())
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
        $fileExt = false;

        foreach ($fileList as $entry) {
            if ($entry['basename'] === $file) {
                $fileName = $entry['filename'];
                $fileExt = $entry['extension'];
                break;
            }
        }

        if (!$fileName || !$fileExt) {
            throw new QUI\Exception('File ' . $file . ' was not found in user files.');
        }

        $DownloadEntry = self::createDownloadEntry($customerId);
        $langs = QUI::availableLanguages();

        $downloadUrl = URL_OPT_DIR . 'quiqqer/customer/bin/backend/download.php?';
        $query = http_build_query([
            'file' => $fileName,
            'extension' => $fileExt,
            'customerId' => $customerId
        ]);

        $DownloadEntry->addUrl(
            $downloadUrl . $query,
            array_fill_keys($langs, $file)
        );

        $DownloadEntry->update();

        $User = QUI::getUsers()->get($customerId);
        $EditUser = QUI::getUserBySession();

        Customers::getInstance()->addCommentToUser(
            $User,
            QUI::getLocale()->get(
                'quiqqer/customer',
                'comment.DownloadEntry.add_file',
                [
                    'editUser' => $EditUser->getName() . ' (#' . $EditUser->getId() . ')',
                    'file' => $file
                ]
            )
        );
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
        $fileExt = false;

        foreach ($fileList as $entry) {
            if ($entry['basename'] === $file) {
                $fileName = $entry['filename'];
                $fileExt = $entry['extension'];
                break;
            }
        }

        if (!$fileName || !$fileExt) {
            throw new QUI\Exception('File ' . $file . ' was not found in user files.');
        }

        $DownloadEntry = self::getDownloadEntry($customerId);

        $downloadUrl = URL_OPT_DIR . 'quiqqer/customer/bin/backend/download.php?';
        $query = http_build_query([
            'file' => $fileName,
            'extension' => $fileExt,
            'customerId' => $customerId
        ]);

        $DownloadEntry->removeUrl($downloadUrl . $query);
        $DownloadEntry->update();

        $itemCount = count($DownloadEntry->getUrls()) + count($DownloadEntry->getQuiqqerMediaUrls());

        if ($itemCount === 0) {
            self::deleteDownloadEntry($customerId);
        }

        $User = QUI::getUsers()->get($customerId);
        $EditUser = QUI::getUserBySession();

        Customers::getInstance()->addCommentToUser(
            $User,
            QUI::getLocale()->get(
                'quiqqer/customer',
                'comment.DownloadEntry.remove_file',
                [
                    'editUser' => $EditUser->getName() . ' (#' . $EditUser->getId() . ')',
                    'file' => $file
                ]
            )
        );
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

        $pathInfo = pathinfo($filePath);
        $fileName = $pathInfo['filename'];
        $fileExt = !empty($pathInfo['extension']) ? $pathInfo['extension'] : false;

        foreach ($DownloadEntry->getUrls() as $entry) {
            $url = urldecode($entry['url']);

            if (mb_strpos($url, 'file=' . $fileName) !== false) {
                if ($fileExt) {
                    if (mb_strpos($url, 'extension=' . $fileExt) !== false) {
                        return true;
                    }
                } else {
                    return true;
                }
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
    public static function getDownloadEntry(int $customerId): bool|DownloadEntry
    {
        if (!QUI::getPackageManager()->isInstalled('quiqqer/user-downloads')) {
            throw new QUI\Exception('This feature requires quiqqer/user-downloads to be installed.');
        }

        $Customer = QUI::getUsers()->get($customerId);
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

        $User = QUI::getUsers()->get($customerId);
        $DownloadEntry = new DownloadEntry($User);
        $Locale = QUI::getLocale();

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
