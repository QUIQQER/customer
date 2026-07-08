<?php

/**
 * This file contains QUI\ERP\Customer\EventHandler
 */

namespace QUI\ERP\Customer;

use DOMElement;
use QUI;
use QUI\Database\Exception;
use QUI\Package\Package;
use QUI\System\Console\Tools\MigrationV2;
use QUI\Users\Manager;
use QUI\Smarty\Collector;

use function array_merge;
use function array_values;
use function count;
use function dirname;
use function file_exists;
use function is_array;
use function is_numeric;
use function is_string;
use function json_decode;
use function json_encode;
use function md5;
use function sort;
use function trim;

/**
 * Class EventHandler
 *
 * @package QUI\ERP\Customer
 */
class EventHandler
{
    /**
     * Snapshots of tracked customer data keyed by user UUID.
     *
     * @var array<string, array<string, array<string, string>>>
     */
    protected static array $userSaveSnapshots = [];

    /**
     * @throws QUI\Exception
     */
    protected static function getCustomerConfig(): QUI\Config
    {
        $Config = QUI::getPackage('quiqqer/customer')->getConfig();

        if (!$Config instanceof QUI\Config) {
            throw new QUI\Exception('Customer package config is not available.');
        }

        return $Config;
    }

    /**
     * Snapshots of tracked customer addresses keyed by address UUID.
     *
     * @var array<string, array<string, array<string, string>>>
     */
    protected static array $userAddressSnapshots = [];

    /**
     * quiqqer/core: onPackageSetup
     * - create customer group
     *
     * @param Package $Package
     */
    public static function onPackageSetup(Package $Package): void
    {
        if ($Package->getName() != 'quiqqer/customer') {
            return;
        }

        self::createCustomerGroup();
    }

    /**
     * Create customer user group
     *
     * @return void
     */
    protected static function createCustomerGroup(): void
    {
        try {
            $Config = self::getCustomerConfig();
            $groupId = $Config->getValue('customer', 'groupId');

            if (!empty($groupId)) {
                return;
            }

            $Root = QUI::getGroups()->firstChild();

            if (!QUI::getLocale()->exists('quiqqer/customer', 'customer.group.name')) {
                try {
                    QUI\Translator::batchImportFromPackage(QUI::getPackage('quiqqer/customer'));
                } catch (QUI\Exception $Exception) {
                    QUI\System\Log::addDebug($Exception->getMessage());
                }
            }

            $Customer = $Root->createChild(
                QUI::getLocale()->get('quiqqer/customer', 'customer.group.name'),
                QUI::getUsers()->getSystemUser()
            );

            $Config->setValue('customer', 'groupId', $Customer->getUUID());
            $Config->save();

            $Customer->activate();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * event : on admin header loaded
     */
    public static function onAdminLoadFooter(): void
    {
        if (!defined('ADMIN') || !ADMIN) {
            return;
        }

        try {
            $Package = QUI::getPackageManager()->getInstalledPackage('quiqqer/customer');
            $groupId = $Package->getConfig()?->getValue('customer', 'groupId');

            if (!is_scalar($groupId)) {
                return;
            }

            echo '<script>window.QUIQQER_CUSTOMER_GROUP = "' . $groupId . '"</script>';
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * Extend user with customer.xml attributes
     *
     * @param QUI\Users\User $User
     * @param array<mixed> $attributes
     */
    public static function onUserExtraAttributes(QUI\Interfaces\Users\User $User, array &$attributes): void
    {
        $cache = 'quiqqer/package/quiqqer/customer';

        try {
            $customerAttr = QUI\Cache\Manager::get($cache);
        } catch (QUI\Exception) {
            $customerAttr = [];

            $list = QUI::getPackageManager()->getInstalled();

            foreach ($list as $entry) {
                $plugin = $entry['name'];
                $userXml = OPT_DIR . $plugin . '/customer.xml';

                if (!file_exists($userXml)) {
                    continue;
                }

                $customerAttr = array_merge(
                    $customerAttr,
                    self::readAttributesFromUserXML($userXml)
                );
            }
        }

        $attributes = array_merge($attributes, $customerAttr);
    }


    /**
     * Read a user.xml and return the attributes,
     * if some extra attributes defined
     *
     * @param string $file
     *
     * @return list<array{name: string, encrypt: bool}>
     */
    protected static function readAttributesFromUserXML(string $file): array
    {
        $cache = 'quiqqer/package/customer/user-extra-attributes/' . md5($file);

        try {
            return QUI\Cache\Manager::get($cache);
        } catch (QUI\Exception) {
        }

        $Dom = QUI\Utils\Text\XML::getDomFromXml($file);
        $Attr = $Dom->getElementsByTagName('attributes');

        if (!$Attr->length) {
            return [];
        }

        /* @var $Attributes DOMElement */
        $Attributes = $Attr->item(0);

        if (!$Attributes instanceof DOMElement) {
            return [];
        }

        $list = $Attributes->getElementsByTagName('attribute');

        if (!$list->length) {
            return [];
        }

        $attributes = [];

        for ($c = 0; $c < $list->length; $c++) {
            $Attribute = $list->item($c);

            if (!$Attribute instanceof DOMElement) {
                continue;
            }

            if ($Attribute->nodeName == '#text') {
                continue;
            }

            $attributes[] = [
                'name' => trim($Attribute->nodeValue ?? ''),
                'encrypt' => !!$Attribute->getAttribute('encrypt')
            ];
        }

        QUI\Cache\Manager::set($cache, $attributes);

        return $attributes;
    }

    public static function onUserSaveBegin(QUI\Interfaces\Users\User $User): void
    {
        if (!($User instanceof QUI\Users\User)) {
            return;
        }

        self::rememberUserSnapshot($User);

        if (QUI::isBackend()) {
            return;
        }

        $Request = QUI::getRequest()->request;
        $data = $Request->all();

        if (empty($data)) {
            return;
        }

        if (isset($data['data'])) {
            $data = json_decode($data['data'], true);
        }

        if (isset($data['quiqqer.erp.customer.contact.person'])) {
            if (QUI\Permissions\Permission::hasPermission('quiqqer.customer.FrontendUsers.contactPerson.edit')) {
                try {
                    $User->getAddress($data['quiqqer.erp.customer.contact.person']);
                    $User->setAttribute(
                        'quiqqer.erp.customer.contact.person',
                        $data['quiqqer.erp.customer.contact.person']
                    );
                } catch (QUI\Exception) {
                }
            }

            unset($data['quiqqer.erp.customer.contact.person']);
        }
    }

    /**
     * @param QUI\Interfaces\Users\User $User
     * @throws QUI\Exception
     */
    public static function onUserSaveEnd(QUI\Interfaces\Users\User $User): void
    {
        if (!($User instanceof QUI\Users\User)) {
            return;
        }

        $snapshotKey = $User->getUUID();
        $beforeSnapshot = self::$userSaveSnapshots[$snapshotKey] ?? null;
        unset(self::$userSaveSnapshots[$snapshotKey]);

        $attributes = $User->getAttributes();
        $data = [];

        if (isset($attributes['mainGroup'])) {
            try {
                $mainGroup = $attributes['mainGroup'];

                if (!empty($mainGroup)) {
                    QUI::getGroups()->get($mainGroup);
                }

                $data['mainGroup'] = $mainGroup;
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::addDebug($Exception->getMessage());
            }
        }

        $newNextCustomerNo = false;
        $NumberRange = new NumberRange();

        if (isset($attributes['customerId'])) {
            $data['customerId'] = $attributes['customerId'];

            // Check: if customerId is set to the next ID in line, then the next ID must be increased by 1
            $nextCustomerNo = $NumberRange->getNextCustomerNo();

            if ((int)$data['customerId'] === $nextCustomerNo) {
                $newNextCustomerNo = $nextCustomerNo + 1;
            }
        }

        // comments
        if ($User->getAttribute('comments')) {
            $comments = $User->getAttribute('comments');
            $json = json_decode($comments, true);

            if (is_array($json)) {
                $data['comments'] = $comments;
            }
        }

        // history
        if ($User->getAttribute('history')) {
            $history = $User->getAttribute('history');
            $json = json_decode($history, true);

            if (is_array($json)) {
                $data['history'] = $history;
            }
        }

        if (!empty($data)) {
            // saving
            try {
                QUI::getDataBase()->update(
                    Manager::table(),
                    $data,
                    ['uuid' => $User->getUUID()]
                );

                if ($newNextCustomerNo) {
                    $NumberRange->setRange($newNextCustomerNo);
                }
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::addDebug($Exception->getMessage());
            }
        }

        if (empty($beforeSnapshot) || !self::isCustomerUser($User)) {
            return;
        }

        $afterSnapshot = self::createUserSnapshot($User);
        $changes = self::diffUserSnapshots($beforeSnapshot, $afterSnapshot);

        if (empty($changes)) {
            return;
        }

        $History = Customers::getInstance()->getUserHistory($User);

        foreach ($changes as $change) {
            $History->addComment(
                self::createHistoryMessage($change),
                false,
                'quiqqer/customer',
                'fa fa-history'
            );
        }

        Customers::getInstance()->updateHistory($User, $History);
    }

    /**
     * Remember the current standard address snapshot before saving.
     *
     * @param QUI\Users\Address $Address
     * @param QUI\Interfaces\Users\User $User
     * @return void
     */
    public static function onUserAddressSaveBegin(
        QUI\Users\Address $Address,
        QUI\Interfaces\Users\User $User
    ): void {
        if (!($User instanceof QUI\Users\User)) {
            return;
        }

        self::rememberAddressSnapshot($Address, $User);
    }

    /**
     * Compare the current standard address after direct address saves.
     *
     * @param QUI\Users\Address $Address
     * @param QUI\Interfaces\Users\User $User
     * @return void
     */
    public static function onUserAddressSave(
        QUI\Users\Address $Address,
        QUI\Interfaces\Users\User $User
    ): void {
        if (!($User instanceof QUI\Users\User)) {
            return;
        }

        $addressUuid = $Address->getUUID();

        if (empty($addressUuid)) {
            return;
        }

        $beforeSnapshot = self::$userAddressSnapshots[$addressUuid] ?? null;
        unset(self::$userAddressSnapshots[$addressUuid]);

        if (empty($beforeSnapshot) || !self::shouldTrackAddressHistory($Address, $User)) {
            return;
        }

        // If the address save is part of User::save(), the user snapshot will write the history once.
        if (isset(self::$userSaveSnapshots[$User->getUUID()])) {
            return;
        }

        $afterSnapshot = self::createAddressSnapshot($Address);
        $changes = self::diffUserSnapshots($beforeSnapshot, $afterSnapshot);

        if (empty($changes)) {
            return;
        }

        $History = Customers::getInstance()->getUserHistory($User);

        foreach ($changes as $change) {
            $History->addComment(
                self::createHistoryMessage($change),
                false,
                'quiqqer/customer',
                'fa fa-history'
            );
        }

        Customers::getInstance()->updateHistory($User, $History);
    }

    /**
     * @param QUI\Interfaces\Users\User $User
     * @param bool|string $code
     * @param null|QUI\Interfaces\Users\User $PermissionUser
     *
     * @throws QUI\Users\Exception|QUI\Exception
     */
    public static function onUserActivateBegin(
        QUI\Interfaces\Users\User $User,
        bool | string $code,
        ?QUI\Interfaces\Users\User $PermissionUser
    ): void {
        $Group = Utils::getInstance()->getCustomerGroup();

        if (!$Group) {
            return;
        }

        if (!$User->isInGroup($Group->getUUID())) {
            return;
        }

        try {
            $login = !empty(QUI::getPackage('quiqqer/customer')
                ->getConfig()
                ?->getValue('customer', 'customerLogin'));
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addError($Exception->getMessage());

            throw new QUI\Users\Exception(
                QUI::getLocale()->get('quiqqer/customer', 'exception.customer.login.not.allowed')
            );
        }

        if (empty($login)) {
            throw new QUI\Users\Exception(
                QUI::getLocale()->get('quiqqer/customer', 'exception.customer.login.not.allowed')
            );
        }
    }

    /**
     * event handling for onQuiqqerOrderCustomerDataSaveEnd
     * - set the user to the customer group
     *
     * @param QUI\ERP\Order\Controls\OrderProcess\CustomerData $Step
     * @throws QUI\Exception
     */
    public static function onQuiqqerOrderCustomerDataSaveEnd(
        QUI\ERP\Order\Controls\OrderProcess\CustomerData $Step
    ): void {
        $Order = $Step->getOrder();

        if (!$Order instanceof QUI\ERP\Order\AbstractOrder) {
            return;
        }

        $Customer = $Order->getCustomer();

        if (!$Customer instanceof QUI\ERP\User) {
            return;
        }

        try {
            $User = QUI::getUsers()->get($Customer->getUUID());
            QUI\ERP\Customer\Customers::getInstance()->addUserToCustomerGroup($User->getUUID());
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addDebug($Exception->getMessage());
            return;
        }

        // setting: automatically add customer number when ordering
        $setCustomerNoAtOrder = QUI::getPackage('quiqqer/customer')
            ->getConfig()
            ?->get('customer', 'setCustomerNoAtOrder');

        if ($setCustomerNoAtOrder && !$User->getAttribute('customerId')) {
            $NumberRange = new NumberRange();
            $nextCustomerNo = (int)$NumberRange->getNextCustomerNo();

            try {
                $User->setAttribute('customerId', $nextCustomerNo);
                $User->save(QUI::getUsers()->getSystemUser());

                $NumberRange->setRange($nextCustomerNo + 1);
            } catch (QUI\Exception) {
            }
        }
    }

    /**
     * Handle the frontend user data event
     *
     * @param Collector $Collector - The collector object
     * @param QUI\Interfaces\Users\User $User - The user object
     * @param mixed $Address - The address data
     * @return void
     * @throws Exception
     */
    public static function onFrontendUserDataMiddle(
        Collector $Collector,
        QUI\Interfaces\Users\User $User,
        mixed $Address
    ): void {
        $Engine = QUI::getTemplateManager()->getEngine();
        $canEdit = QUI\Permissions\Permission::hasPermission('quiqqer.customer.FrontendUsers.contactPerson.edit');
        $canView = QUI\Permissions\Permission::hasPermission('quiqqer.customer.FrontendUsers.contactPerson.view');

        $addressList = $User->getAddressList();
        $addressList = array_values($addressList);

        $currentContactPerson = $User->getAttribute('quiqqer.erp.customer.contact.person');

        if (empty($currentContactPerson) && count($addressList)) {
            $currentContactPerson = $addressList[0]->getUUID();
        }

        $Engine->assign([
            'canEdit' => $canEdit,
            'canView' => $canView,

            'User' => $User,
            'Address' => $Address,
            'addressList' => $addressList,
            'currentContactPerson' => $currentContactPerson,

            'businessTypeIsChangeable' => !(QUI\ERP\Utils\Shop::isOnlyB2C() || QUI\ERP\Utils\Shop::isOnlyB2B()),
            'isB2C' => QUI\ERP\Utils\Shop::isB2C(),
            'isB2B' => QUI\ERP\Utils\Shop::isB2B(),
            'isOnlyB2B' => QUI\ERP\Utils\Shop::isOnlyB2B()
        ]);

        try {
            $Collector->append(
                $Engine->fetch(dirname(__FILE__) . '/FrontendUsers/ContactPerson.html')
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    public static function onQuiqqerMigrationV2(MigrationV2 $Console): void
    {
        $Console->writeLn('- Migrate customer open items');

        $customerOpenItemsTable = QUI::getDBTableName('customer_open_items');

        QUI::getDataBaseConnection()->executeStatement(
            'ALTER TABLE `' . $customerOpenItemsTable . '` CHANGE `userId` `userId` VARCHAR(50) NOT NULL;'
        );

        QUI\Utils\MigrationV1ToV2::migrateUsers(
            $customerOpenItemsTable,
            ['userId'],
            'userId'
        );

        // users extra fields
        $Console->writeLn('- Migrate customer attributes');

        $userTable = QUI::getUsers()->table();
        $tableAddresses = QUI::getUsers()->tableAddress();

        $result = QUI::getDataBase()->fetch([
            'from' => $userTable
        ]);

        foreach ($result as $entry) {
            $extra = json_decode($entry['extra'], true);

            if (!empty($extra['quiqqer.erp.customer.contact.person'])) {
                if (is_numeric($extra['quiqqer.erp.customer.contact.person'])) {
                    try {
                        $addressData = QUI::getDataBase()->fetch([
                            'from' => $tableAddresses,
                            'where' => [
                                'id' => $extra['quiqqer.erp.customer.contact.person']
                            ]
                        ]);

                        if (count($addressData)) {
                            $extra['quiqqer.erp.customer.contact.person'] = $addressData[0]['uuid'];
                        }
                    } catch (QUI\Exception) {
                    }
                }
            }

            if (!empty($extra['quiqqer.erp.address'])) {
                if (is_numeric($extra['quiqqer.erp.address'])) {
                    try {
                        $addressData = QUI::getDataBase()->fetch([
                            'from' => $tableAddresses,
                            'where' => [
                                'id' => $extra['quiqqer.erp.address']
                            ]
                        ]);

                        if (count($addressData)) {
                            $extra['quiqqer.erp.address'] = $addressData[0]['uuid'];
                        }
                    } catch (QUI\Exception) {
                    }
                }
            }

            if (!empty($extra['quiqqer.erp.supplier.contact.person'])) {
                if (is_numeric($extra['quiqqer.erp.supplier.contact.person'])) {
                    try {
                        $extra['quiqqer.erp.supplier.contact.person'] = QUI::getUsers()->get(
                            (string)$extra['quiqqer.erp.supplier.contact.person']
                        )->getUUID();
                    } catch (QUI\Exception) {
                    }
                }
            }

            try {
                QUI::getDataBase()->update(
                    $userTable,
                    ['extra' => json_encode($extra)],
                    ['id' => $entry['id']]
                );
            } catch (QUI\Exception) {
            }
        }


        // migrate settings
        $Console->writeLn('- Migrate customer settings');

        $Config = self::getCustomerConfig();
        $groupId = $Config->get('customer', 'groupId');

        if (is_numeric($groupId)) {
            try {
                $Config->setValue('customer', 'groupId', QUI::getGroups()->get((string)$groupId)->getUUID());
                $Config->save();
            } catch (QUI\Exception) {
            }
        }
    }

    /**
     * Import customer history entries into the generic ERP history feed.
     *
     * @param QUI\Interfaces\Users\User $User
     * @param QUI\ERP\Comments $History
     * @return void
     */
    public static function onQuiqqerErpGetHistoryByUser(
        QUI\Interfaces\Users\User $User,
        QUI\ERP\Comments $History
    ): void {
        $History->import(
            Customers::getInstance()->getUserHistory($User)
        );
    }

    /**
     * Remember the current customer snapshot before saving.
     *
     * @param QUI\Users\User $User
     * @return void
     */
    protected static function rememberUserSnapshot(QUI\Users\User $User): void
    {
        $snapshotKey = (string)$User->getUUID();

        if (!self::isCustomerUser($User)) {
            unset(self::$userSaveSnapshots[$snapshotKey]);
            return;
        }

        try {
            $DbUser = new QUI\Users\User($User->getUUID());
            self::$userSaveSnapshots[$snapshotKey] = self::createUserSnapshot($DbUser);
        } catch (QUI\Exception) {
            self::$userSaveSnapshots[$snapshotKey] = self::createUserSnapshot($User);
        }
    }

    /**
     * Remember the current address snapshot before saving.
     *
     * @param QUI\Users\Address $Address
     * @param QUI\Users\User $User
     * @return void
     */
    protected static function rememberAddressSnapshot(
        QUI\Users\Address $Address,
        QUI\Users\User $User
    ): void {
        if (!self::shouldTrackAddressHistory($Address, $User)) {
            return;
        }

        $addressUuid = $Address->getUUID();

        if (empty($addressUuid)) {
            return;
        }

        try {
            $DbUser = new QUI\Users\User($User->getUUID());
            $DbAddress = new QUI\Users\Address($DbUser, $addressUuid);

            self::$userAddressSnapshots[$addressUuid] = self::createAddressSnapshot($DbAddress);
        } catch (QUI\Exception) {
            self::$userAddressSnapshots[$addressUuid] = self::createAddressSnapshot($Address);
        }
    }

    /**
     * Return whether the given user is a customer.
     *
     * @param QUI\Users\User $User
     * @return bool
     */
    protected static function isCustomerUser(QUI\Users\User $User): bool
    {
        if (!empty($User->getAttribute('customerId'))) {
            return true;
        }

        try {
            $CustomerGroup = Utils::getInstance()->getCustomerGroup();

            if ($CustomerGroup === null) {
                return false;
            }

            if ($User->getAttribute('mainGroup') === $CustomerGroup->getUUID()) {
                return true;
            }

            return $User->isInGroup($CustomerGroup->getUUID());
        } catch (QUI\Exception) {
            return false;
        }
    }

    /**
     * Return whether a given address save should be tracked for history.
     *
     * Currently only the standard customer address is tracked here.
     *
     * @param QUI\Users\Address $Address
     * @param QUI\Users\User $User
     * @return bool
     */
    protected static function shouldTrackAddressHistory(
        QUI\Users\Address $Address,
        QUI\Users\User $User
    ): bool {
        if (!self::isCustomerUser($User)) {
            return false;
        }

        $standardAddressUuid = $User->getStandardAddress()?->getUUID();

        if (empty($standardAddressUuid)) {
            return false;
        }

        return $Address->getUUID() === $standardAddressUuid;
    }

    /**
     * Create a normalized snapshot of the tracked customer data.
     *
     * @param QUI\Users\User $User
     * @return array<string, array<string, string>>
     */
    protected static function createUserSnapshot(QUI\Users\User $User): array
    {
        $Address = null;
        $groupIds = [];

        foreach ($User->getGroups(false) as $groupId) {
            if (!is_string($groupId) || $groupId === '') {
                continue;
            }

            $groupIds[] = $groupId;
        }

        sort($groupIds);

        try {
            $Address = $User->getStandardAddress();
        } catch (QUI\Exception) {
        }

        return [
            'username' => self::createSnapshotEntry(
                $User->getUsername()
            ),
            'email' => self::createSnapshotEntry(
                $User->getAttribute('email')
            ),
            'customerId' => self::createSnapshotEntry(
                $User->getAttribute('customerId')
            ),
            'mainGroup' => self::createSnapshotEntry(
                $User->getAttribute('mainGroup'),
                self::getGroupDisplay($User->getAttribute('mainGroup'))
            ),
            'groups' => self::createSnapshotEntry(
                implode(',', $groupIds),
                self::getGroupsDisplay($groupIds)
            ),
            'referenceCode' => self::createSnapshotEntry(
                $User->getAttribute('quiqqer.erp.customer.referenceCode')
            ),
            'paymentTerm' => self::createSnapshotEntry(
                $User->getAttribute('quiqqer.erp.customer.payment.term')
            ),
            'contactPerson' => self::createSnapshotEntry(
                $User->getAttribute('quiqqer.erp.customer.contact.person'),
                self::getContactPersonDisplay(
                    $User,
                    $User->getAttribute('quiqqer.erp.customer.contact.person')
                )
            ),
            'address.salutation' => self::createSnapshotEntry(
                self::getAddressAttribute($Address, 'salutation')
            ),
            'address.firstname' => self::createSnapshotEntry(
                self::getAddressAttribute($Address, 'firstname')
            ),
            'address.lastname' => self::createSnapshotEntry(
                self::getAddressAttribute($Address, 'lastname')
            ),
            'address.company' => self::createSnapshotEntry(
                self::getAddressAttribute($Address, 'company')
            ),
            'address.street_no' => self::createSnapshotEntry(
                self::getAddressAttribute($Address, 'street_no')
            ),
            'address.zip' => self::createSnapshotEntry(
                self::getAddressAttribute($Address, 'zip')
            ),
            'address.city' => self::createSnapshotEntry(
                self::getAddressAttribute($Address, 'city')
            ),
            'address.country' => self::createSnapshotEntry(
                self::getAddressAttribute($Address, 'country'),
                self::getCountryDisplay(
                    self::getAddressAttribute($Address, 'country')
                )
            ),
            'address.suffix' => self::createSnapshotEntry(
                self::getAddressAttribute($Address, 'suffix')
            ),
            'address.phone.tel' => self::createSnapshotEntry(
                self::getAddressPhone($Address, 'tel')
            ),
            'address.phone.mobile' => self::createSnapshotEntry(
                self::getAddressPhone($Address, 'mobile')
            ),
            'address.phone.fax' => self::createSnapshotEntry(
                self::getAddressPhone($Address, 'fax')
            )
        ];
    }

    /**
     * Create a normalized snapshot of a customer address.
     *
     * @param QUI\Users\Address $Address
     * @return array<string, array<string, string>>
     */
    protected static function createAddressSnapshot(QUI\Users\Address $Address): array
    {
        return [
            'address.salutation' => self::createSnapshotEntry(
                self::getAddressAttribute($Address, 'salutation')
            ),
            'address.firstname' => self::createSnapshotEntry(
                self::getAddressAttribute($Address, 'firstname')
            ),
            'address.lastname' => self::createSnapshotEntry(
                self::getAddressAttribute($Address, 'lastname')
            ),
            'address.company' => self::createSnapshotEntry(
                self::getAddressAttribute($Address, 'company')
            ),
            'address.street_no' => self::createSnapshotEntry(
                self::getAddressAttribute($Address, 'street_no')
            ),
            'address.zip' => self::createSnapshotEntry(
                self::getAddressAttribute($Address, 'zip')
            ),
            'address.city' => self::createSnapshotEntry(
                self::getAddressAttribute($Address, 'city')
            ),
            'address.country' => self::createSnapshotEntry(
                self::getAddressAttribute($Address, 'country'),
                self::getCountryDisplay(
                    self::getAddressAttribute($Address, 'country')
                )
            ),
            'address.suffix' => self::createSnapshotEntry(
                self::getAddressAttribute($Address, 'suffix')
            ),
            'address.phone.tel' => self::createSnapshotEntry(
                self::getAddressPhone($Address, 'tel')
            ),
            'address.phone.mobile' => self::createSnapshotEntry(
                self::getAddressPhone($Address, 'mobile')
            ),
            'address.phone.fax' => self::createSnapshotEntry(
                self::getAddressPhone($Address, 'fax')
            )
        ];
    }

    /**
     * Create a normalized snapshot entry.
     *
     * @param mixed $value
     * @param string|null $display
     * @return array<string, string>
     */
    protected static function createSnapshotEntry(mixed $value, ?string $display = null): array
    {
        $value = self::normalizeSnapshotValue($value);

        if ($display === null) {
            $display = $value;
        }

        return [
            'value' => $value,
            'display' => self::normalizeSnapshotValue($display)
        ];
    }

    /**
     * Normalize a snapshot value to a comparable string.
     *
     * @param mixed $value
     * @return string
     */
    protected static function normalizeSnapshotValue(mixed $value): string
    {
        if ($value === false || $value === null) {
            return '';
        }

        if (is_array($value)) {
            $value = json_encode($value);
        }

        return trim((string)$value);
    }

    /**
     * Return the normalized value of an address attribute.
     *
     * @param QUI\Users\Address|null $Address
     * @param string $attribute
     * @return string
     */
    protected static function getAddressAttribute(?QUI\Users\Address $Address, string $attribute): string
    {
        if ($Address === null) {
            return '';
        }

        return self::normalizeSnapshotValue(
            $Address->getAttribute($attribute)
        );
    }

    /**
     * Return the normalized phone number of an address by type.
     *
     * @param QUI\Users\Address|null $Address
     * @param string $type
     * @return string
     */
    protected static function getAddressPhone(?QUI\Users\Address $Address, string $type): string
    {
        if ($Address === null) {
            return '';
        }

        return match ($type) {
            'mobile' => self::normalizeSnapshotValue($Address->getMobile()),
            'fax' => self::normalizeSnapshotValue($Address->getFax()),
            default => self::normalizeSnapshotValue($Address->getPhone())
        };
    }

    /**
     * Return a readable display value for a group UUID.
     *
     * @param mixed $groupId
     * @return string
     */
    protected static function getGroupDisplay(mixed $groupId): string
    {
        $groupId = self::normalizeSnapshotValue($groupId);

        if ($groupId === '') {
            return '';
        }

        try {
            return QUI::getGroups()->get($groupId)->getName();
        } catch (QUI\Exception) {
            return $groupId;
        }
    }

    /**
     * Return readable display values for a list of group UUIDs.
     *
     * @param array<int, int|string> $groupIds
     * @return string
     */
    protected static function getGroupsDisplay(array $groupIds): string
    {
        $display = [];

        foreach ($groupIds as $groupId) {
            $display[] = self::getGroupDisplay($groupId);
        }

        sort($display);

        return implode(', ', $display);
    }

    /**
     * Resolve the display value of a contact person address.
     *
     * @param QUI\Users\User $User
     * @param mixed $addressId
     * @return string
     */
    protected static function getContactPersonDisplay(QUI\Users\User $User, mixed $addressId): string
    {
        $addressId = self::normalizeSnapshotValue($addressId);

        if ($addressId === '') {
            return '';
        }

        try {
            return $User->getAddress($addressId)->getName();
        } catch (QUI\Exception) {
            return $addressId;
        }
    }

    /**
     * Resolve the display value of a country code.
     *
     * @param string $countryCode
     * @return string
     */
    protected static function getCountryDisplay(string $countryCode): string
    {
        if ($countryCode === '') {
            return '';
        }

        try {
            return QUI\Countries\Manager::get($countryCode)->getName();
        } catch (QUI\Exception) {
            return $countryCode;
        }
    }

    /**
     * Compare two customer snapshots.
     *
     * @param array<string, array<string, string>> $before
     * @param array<string, array<string, string>> $after
     * @return list<array<string, array<string, string>|string>>
     */
    protected static function diffUserSnapshots(array $before, array $after): array
    {
        $changes = [];

        foreach ($before as $field => $oldEntry) {
            if (!isset($after[$field])) {
                continue;
            }

            if ($oldEntry['value'] === $after[$field]['value']) {
                continue;
            }

            $changes[] = [
                'field' => $field,
                'old' => $oldEntry,
                'new' => $after[$field]
            ];
        }

        return $changes;
    }

    /**
     * Create a readable history message for one customer change.
     *
     * @param array<string, array<string, string>|string> $change
     * @return string
     */
    protected static function createHistoryMessage(array $change): string
    {
        $Actor = QUI::getUserBySession();

        if (!$Actor->getUUID()) {
            $Actor = QUI::getUsers()->getSystemUser();
        }

        $old = '';
        $new = '';

        if (isset($change['old']) && is_array($change['old'])) {
            $old = $change['old']['display'] ?? '';
        }

        if (isset($change['new']) && is_array($change['new'])) {
            $new = $change['new']['display'] ?? '';
        }

        $field = $change['field'] ?? '';

        if (!is_string($field)) {
            $field = '';
        }

        if ($old === '') {
            $old = QUI::getLocale()->get(
                'quiqqer/customer',
                'customer.history.value.empty'
            );
        }

        if ($new === '') {
            $new = QUI::getLocale()->get(
                'quiqqer/customer',
                'customer.history.value.empty'
            );
        }

        return QUI::getLocale()->get(
            'quiqqer/customer',
            'customer.history.change',
            [
                'field' => QUI::getLocale()->get(
                    'quiqqer/customer',
                    'customer.history.field.' . $field
                ),
                'old' => $old,
                'new' => $new,
                'username' => $Actor->getUsername(),
                'userId' => $Actor->getUUID()
            ]
        );
    }
}
