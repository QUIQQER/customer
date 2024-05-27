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
use function dirname;
use function file_exists;
use function is_array;
use function is_numeric;
use function json_decode;
use function md5;
use function trim;

/**
 * Class EventHandler
 *
 * @package QUI\ERP\Customer
 */
class EventHandler
{
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
            $Package = QUI::getPackage('quiqqer/customer');
            $Config = $Package->getConfig();
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
            $Config = $Package->getConfig();
            $groupId = $Config->getValue('customer', 'groupId');

            echo '<script>window.QUIQQER_CUSTOMER_GROUP = "' . $groupId . '"</script>';
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * Extend user with customer.xml attributes
     *
     * @param QUI\Users\User $User
     * @param array $attributes
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
     * @return array
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
        $list = $Attributes->getElementsByTagName('attribute');

        if (!$list->length) {
            return [];
        }

        $attributes = [];

        for ($c = 0; $c < $list->length; $c++) {
            $Attribute = $list->item($c);

            if ($Attribute->nodeName == '#text') {
                continue;
            }

            $attributes[] = [
                'name' => trim($Attribute->nodeValue),
                'encrypt' => !!$Attribute->getAttribute('encrypt')
            ];
        }

        QUI\Cache\Manager::set($cache, $attributes);

        return $attributes;
    }

    public static function onUserSaveBegin(QUI\Users\User $User): void
    {
        if (!QUI::getUsers()->isUser($User)) {
            return;
        }

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
                        (int)$data['quiqqer.erp.customer.contact.person']
                    );
                } catch (QUI\Exception) {
                }
            }

            unset($data['quiqqer.erp.customer.contact.person']);
        }
    }

    /**
     * @param QUI\Users\User $User
     */
    public static function onUserSaveEnd(QUI\Users\User $User): void
    {
        $attributes = $User->getAttributes();
        $data = [];

        if (isset($attributes['mainGroup'])) {
            try {
                $mainGroup = $attributes['mainGroup'];
                QUI::getGroups()->get($mainGroup);

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

        if (empty($data)) {
            return;
        }

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

    /**
     * @param QUI\Users\User $User
     * @param bool|string $code
     * @param null|QUI\Interfaces\Users\User $ParentUser
     *
     * @throws QUI\Users\Exception|QUI\Exception
     */
    public static function onUserActivateBegin(
        QUI\Users\User $User,
        bool|string $code,
        ?QUI\Interfaces\Users\User $ParentUser
    ): void {
        $Group = Utils::getInstance()->getCustomerGroup();

        if (!$Group) {
            return;
        }

        if (!$User->isInGroup($Group->getUUID())) {
            return;
        }

        try {
            $Package = QUI::getPackage('quiqqer/customer');
            $Config = $Package->getConfig();
            $login = (int)$Config->getValue('customer', 'customerLogin');
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
     */
    public static function onQuiqqerOrderCustomerDataSaveEnd(
        QUI\ERP\Order\Controls\OrderProcess\CustomerData $Step
    ): void {
        $Order = $Step->getOrder();
        $Customer = $Order->getCustomer();

        try {
            $User = QUI::getUsers()->get($Customer->getUUID());
            QUI\ERP\Customer\Customers::getInstance()->addUserToCustomerGroup($User->getUUID());
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addDebug($Exception->getMessage());
        }
    }

    /**
     * Handle the frontend user data event
     *
     * @param Collector $Collector - The collector object
     * @param QUI\Users\User $User - The user object
     * @param mixed $Address - The address data
     * @return void
     * @throws Exception
     */
    public static function onFrontendUserDataMiddle(
        Collector $Collector,
        QUI\Users\User $User,
        $Address
    ): void {
        try {
            $Engine = QUI::getTemplateManager()->getEngine();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return;
        }

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
            'currentContactPerson' => (int)$currentContactPerson,

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

        // migrate settings
        $Console->writeLn('- Migrate customer settings');

        $Config = QUI::getPackage('quiqqer/customer')->getConfig();
        $groupId = $Config->get('customer', 'groupId');

        if (is_numeric($groupId)) {
            try {
                $Config->setValue('customer', 'groupId', QUI::getGroups()->get($groupId)->getUUID());
                $Config->save();
            } catch (QUI\Exception) {
            }
        }
    }
}
