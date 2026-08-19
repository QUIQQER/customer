<?php

declare(strict_types=1);

namespace QUI\ERP\Customer\Tests\Integration;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\Ajax;
use QUI\ERP\Customer\CustomerFiles;
use QUI\Interfaces\Users\User as UserInterface;
use ReflectionProperty;
use Throwable;

final class CustomerFilesTest extends TestCase
{
    private const USERNAME_PREFIX = 'phpunit-customer-files-';

    private string $customerUuid;
    private ?string $customerFolder = null;
    private ?string $legacyCustomerFolder = null;
    private ?string $temporaryFile = null;
    private ?UserInterface $previousSessionUser = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        QUI::$Ajax = QUI::getAjax();

        foreach (
            [
                'delete.php',
                'get.php',
                'getList.php',
                'getPermissions.php',
                'suggestSearch.php',
                'upload.php',
                'downloadEntry/addFile.php',
                'downloadEntry/removeFile.php'
            ] as $file
        ) {
            require_once dirname(__DIR__, 2) . '/ajax/backend/files/' . $file;
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousSessionUser = $this->replaceSessionUser(QUI::getUsers()->getSystemUser());
        $username = self::USERNAME_PREFIX . bin2hex(random_bytes(6));
        $User = QUI::getUsers()->createChildWithAttributes([
            'username' => $username,
            'firstname' => 'File',
            'lastname' => 'Customer'
        ], QUI::getUsers()->getSystemUser());
        $this->customerUuid = $User->getUUID();
    }

    protected function tearDown(): void
    {
        if ($this->temporaryFile !== null && file_exists($this->temporaryFile)) {
            unlink($this->temporaryFile);
        }

        if ($this->customerFolder !== null && is_dir($this->customerFolder)) {
            foreach (QUI\Utils\System\File::readDir($this->customerFolder) as $file) {
                $path = $this->customerFolder . DIRECTORY_SEPARATOR . $file;

                if (is_file($path)) {
                    unlink($path);
                }
            }

            rmdir($this->customerFolder);
        }

        if ($this->legacyCustomerFolder !== null && is_dir($this->legacyCustomerFolder)) {
            foreach (QUI\Utils\System\File::readDir($this->legacyCustomerFolder) as $file) {
                $path = $this->legacyCustomerFolder . DIRECTORY_SEPARATOR . $file;

                if (is_file($path)) {
                    unlink($path);
                }
            }

            rmdir($this->legacyCustomerFolder);
        }

        try {
            QUI::getUsers()->deleteUser($this->customerUuid);
        } catch (Throwable) {
        }

        if ($this->previousSessionUser !== null) {
            $this->replaceSessionUser($this->previousSessionUser);
        }

        parent::tearDown();
    }

    public function testFilesCanBeAddedListedFoundAndDeleted(): void
    {
        $User = QUI::getUsers()->get($this->customerUuid);
        $this->customerFolder = CustomerFiles::getFolderPath($User);
        self::assertDirectoryExists($this->customerFolder);

        $this->temporaryFile = tempnam(sys_get_temp_dir(), 'customer-file-');
        self::assertIsString($this->temporaryFile);
        file_put_contents($this->temporaryFile, 'customer file fixture');
        $basename = basename($this->temporaryFile);
        $hash = CustomerFiles::addFileToCustomer($this->customerUuid, $this->temporaryFile);
        $this->temporaryFile = null;

        self::assertSame(hash('sha256', $basename), $hash);
        self::assertFileExists($this->customerFolder . DIRECTORY_SEPARATOR . $basename);

        $files = CustomerFiles::getFileList($this->customerUuid);
        self::assertCount(1, $files);
        self::assertSame($basename, $files[0]['basename']);
        self::assertSame($hash, $files[0]['hash']);
        self::assertFalse($files[0]['userDownload']);
        self::assertSame($files[0], CustomerFiles::getFileByHash($this->customerUuid, $hash));
        self::assertFalse(CustomerFiles::getFileByHash($this->customerUuid, 'missing-hash'));
        self::assertFalse(CustomerFiles::isFileInDownloadEntry(
            $this->customerUuid,
            $this->customerFolder . DIRECTORY_SEPARATOR . $basename
        ));

        $permissions = $this->callable('getPermissions')();
        self::assertTrue($permissions['fileEdit']);
        self::assertTrue($permissions['fileView']);
        self::assertTrue($permissions['fileUpload']);
        self::assertCount(1, $this->callable('getList')($this->customerUuid));
        self::assertSame($basename, $this->callable('get')($this->customerUuid, $hash)['basename']);
        self::assertCount(1, $this->callable('suggestSearch')($this->customerUuid, 'customer-file'));
        self::assertSame([], $this->callable('suggestSearch')($this->customerUuid, 'no-match'));
        self::assertFalse($this->callable('upload')(new \stdClass(), $this->customerUuid));

        $this->callable('delete')(json_encode([$basename, 'missing-file']), $this->customerUuid);
        self::assertSame([], CustomerFiles::getFileList($this->customerUuid));
    }

    public function testMissingFileAndOptionalDownloadPackageAreReported(): void
    {
        try {
            CustomerFiles::addFileToCustomer($this->customerUuid, '/tmp/customer-file-does-not-exist');
            self::fail('A missing upload file must be rejected.');
        } catch (QUI\Exception $Exception) {
            self::assertSame(404, $Exception->getCode());
        }

        $this->temporaryFile = tempnam(sys_get_temp_dir(), 'customer-file-missing-user-');
        self::assertIsString($this->temporaryFile);
        file_put_contents($this->temporaryFile, 'orphan upload');

        try {
            CustomerFiles::addFileToCustomer('missing-user', $this->temporaryFile);
            self::fail('An upload for an unknown customer must be rejected.');
        } catch (QUI\Exception) {
            self::assertFileExists($this->temporaryFile);
        }

        if (QUI::getPackageManager()->isInstalled('quiqqer/user-downloads')) {
            self::markTestSkipped('The optional user-downloads package is installed.');
        }

        foreach (['getDownloadEntry', 'createDownloadEntry', 'deleteDownloadEntry'] as $method) {
            try {
                CustomerFiles::$method($this->customerUuid);
                self::fail($method . ' must require quiqqer/user-downloads.');
            } catch (QUI\Exception $Exception) {
                self::assertStringContainsString('quiqqer/user-downloads', $Exception->getMessage());
            }
        }

        foreach (['addFileToDownloadEntry', 'removeFileFromDownloadEntry'] as $method) {
            try {
                CustomerFiles::$method($this->customerUuid, 'missing.pdf');
                self::fail($method . ' must require quiqqer/user-downloads.');
            } catch (QUI\Exception $Exception) {
                self::assertStringContainsString('quiqqer/user-downloads', $Exception->getMessage());
            }
        }

        foreach (['downloadEntry_addFile', 'downloadEntry_removeFile'] as $endpoint) {
            try {
                $this->callable($endpoint)('missing.pdf', $this->customerUuid);
                self::fail($endpoint . ' must report the unavailable feature.');
            } catch (QUI\Exception) {
                self::assertTrue(true);
            }
        }

        self::assertSame([], CustomerFiles::getFileList('missing-user'));
        CustomerFiles::deleteFiles('missing-user', ['missing.pdf']);

        $UserWithoutId = $this->createMock(UserInterface::class);
        $UserWithoutId->method('getId')->willReturn(false);

        $this->expectException(QUI\Exception::class);
        CustomerFiles::getFolderPath($UserWithoutId);
    }

    public function testLegacyNumericFolderIsMergedIntoUuidFolder(): void
    {
        $User = QUI::getUsers()->get($this->customerUuid);
        $Package = QUI::getPackageManager()->getInstalledPackage('quiqqer/customer');
        $varDir = $Package->getVarDir();
        $this->legacyCustomerFolder = $varDir . $User->getId();
        $this->customerFolder = $varDir . $User->getUUID();

        QUI\Utils\System\File::mkdir($this->legacyCustomerFolder);
        QUI\Utils\System\File::mkdir($this->customerFolder);
        file_put_contents(
            $this->legacyCustomerFolder . DIRECTORY_SEPARATOR . 'legacy.txt',
            'legacy file'
        );
        file_put_contents(
            $this->legacyCustomerFolder . DIRECTORY_SEPARATOR . 'duplicate.txt',
            'old duplicate'
        );
        file_put_contents(
            $this->customerFolder . DIRECTORY_SEPARATOR . 'duplicate.txt',
            'current duplicate'
        );

        self::assertSame($this->customerFolder, CustomerFiles::getFolderPath($User));
        self::assertDirectoryDoesNotExist($this->legacyCustomerFolder);
        self::assertFileExists($this->customerFolder . DIRECTORY_SEPARATOR . 'legacy.txt');
        self::assertSame(
            'current duplicate',
            file_get_contents($this->customerFolder . DIRECTORY_SEPARATOR . 'duplicate.txt')
        );
    }

    public function testOptionalDownloadEntryLifecycleWithPackageContract(): void
    {
        require_once dirname(__DIR__) . '/stubs/QUI/UserDownloads/Exception.php';
        require_once dirname(__DIR__) . '/stubs/QUI/UserDownloads/DownloadEntry.php';
        require_once dirname(__DIR__) . '/stubs/QUI/UserDownloads/Handler.php';

        $PackageManager = QUI::getPackageManager();
        $InstalledProperty = new ReflectionProperty($PackageManager, 'installed');
        $previousInstalled = $InstalledProperty->getValue($PackageManager);
        $installed = $previousInstalled;
        $installed['quiqqer/user-downloads'] = true;
        $InstalledProperty->setValue($PackageManager, $installed);

        try {
            $User = QUI::getUsers()->get($this->customerUuid);
            $this->customerFolder = CustomerFiles::getFolderPath($User);
            $this->temporaryFile = tempnam(sys_get_temp_dir(), 'customer-download-');
            self::assertIsString($this->temporaryFile);
            $temporaryPdf = $this->temporaryFile . '.pdf';
            rename($this->temporaryFile, $temporaryPdf);
            $this->temporaryFile = $temporaryPdf;
            file_put_contents($this->temporaryFile, 'download fixture');
            $basename = basename($this->temporaryFile);
            CustomerFiles::addFileToCustomer($this->customerUuid, $this->temporaryFile);
            $this->temporaryFile = null;

            self::assertFalse(CustomerFiles::getDownloadEntry($this->customerUuid));
            self::assertFalse(CustomerFiles::isFileInDownloadEntry(
                $this->customerUuid,
                $this->customerFolder . DIRECTORY_SEPARATOR . $basename
            ));

            foreach (['addFileToDownloadEntry', 'removeFileFromDownloadEntry'] as $method) {
                try {
                    CustomerFiles::$method($this->customerUuid, 'missing.pdf');
                    self::fail($method . ' must reject an unknown customer file.');
                } catch (QUI\Exception $Exception) {
                    self::assertStringContainsString('was not found', $Exception->getMessage());
                }
            }

            $DownloadEntry = CustomerFiles::createDownloadEntry($this->customerUuid);
            self::assertEquals($DownloadEntry, CustomerFiles::getDownloadEntry($this->customerUuid));
            self::assertEquals($DownloadEntry, CustomerFiles::createDownloadEntry($this->customerUuid));

            $this->callable('downloadEntry_addFile')($basename, $this->customerUuid);
            self::assertTrue(CustomerFiles::isFileInDownloadEntry(
                $this->customerUuid,
                $this->customerFolder . DIRECTORY_SEPARATOR . $basename
            ));
            self::assertTrue(CustomerFiles::getFileList($this->customerUuid)[0]['userDownload']);

            $this->callable('downloadEntry_removeFile')($basename, $this->customerUuid);
            $User->refresh();
            self::assertFalse($User->getAttribute(CustomerFiles::USER_ATTRIBUTE_DOWNLOAD_ENTRY_ID));
            self::assertFalse(CustomerFiles::getDownloadEntry($this->customerUuid));
            CustomerFiles::deleteDownloadEntry($this->customerUuid);
        } finally {
            $InstalledProperty->setValue($PackageManager, $previousInstalled);
            QUI\UserDownloads\Handler::reset();
        }
    }

    private function callable(string $suffix): callable
    {
        $name = 'package_quiqqer_customer_ajax_backend_files_' . $suffix;
        $callables = Ajax::getRegisteredCallables();
        self::assertArrayHasKey($name, $callables);

        return $callables[$name]['callable'];
    }

    private function replaceSessionUser(UserInterface $User): ?UserInterface
    {
        $Users = QUI::getUsers();
        $Property = new ReflectionProperty($Users, 'Session');
        $PreviousUser = $Property->getValue($Users);
        $Property->setValue($Users, $User);

        return $PreviousUser instanceof UserInterface ? $PreviousUser : null;
    }
}
