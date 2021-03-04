<?php

/**
 * This file contains QUI\ERP\Customer\EventHandler
 */

namespace QUI\ERP\Customer;

use QUI;
use QUI\Package\Package;
use QUI\Users\Manager;

/**
 * Class EventHandler
 *
 * @package QUI\ERP\Customer
 */
class EventHandler
{
    /**
     * quiqqer/quiqqer: onPackageSetup
     * - create customer group
     *
     * @param Package $Package
     */
    public static function onPackageSetup(Package $Package)
    {
        if ($Package->getName() != 'quiqqer/customer') {
            return;
        }

        self::createCustomerGroup();
        self::updateOpenItemsRecords();
    }

    /**
     * Update open items records of alle customers
     *
     * @return void
     */
    protected static function updateOpenItemsRecords(): void
    {
        $CustomerGroup = Customers::getInstance()->getCustomerGroup();
        $Users         = QUI::getUsers();

        foreach ($CustomerGroup->getUserIds() as $userId) {
            try {
                $User = $Users->get($userId);
            } catch (\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
                continue;
            }

            try {
                $User = QUI\ERP\User::convertUserToErpUser($User);
            } catch (\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }

            try {
                QUI\ERP\Customer\OpenItemsList\Handler::updateOpenItemsRecord($User);
            } catch (\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }
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
            $Config  = $Package->getConfig();
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

            $Config->setValue('customer', 'groupId', $Customer->getId());
            $Config->save();

            $Customer->activate();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * event : on admin header loaded
     */
    public static function onAdminLoadFooter()
    {
        if (!defined('ADMIN') || !ADMIN) {
            return;
        }

        try {
            $Package = QUI::getPackageManager()->getInstalledPackage('quiqqer/customer');
            $Config  = $Package->getConfig();
            $groupId = $Config->getValue('customer', 'groupId');

            echo '<script>var QUIQQER_CUSTOMER_GROUP = '.$groupId.'</script>';
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
    public static function onUserExtraAttributes(QUI\Users\User $User, array &$attributes)
    {
        $cache = 'quiqqer/package/quiqqer/customer';

        try {
            $customerAttr = QUI\Cache\Manager::get($cache);
        } catch (QUI\Exception $Exception) {
            $customerAttr = [];

            $list = QUI::getPackageManager()->getInstalled();

            foreach ($list as $entry) {
                $plugin  = $entry['name'];
                $userXml = OPT_DIR.$plugin.'/customer.xml';

                if (!\file_exists($userXml)) {
                    continue;
                }

                $customerAttr = \array_merge(
                    $customerAttr,
                    self::readAttributesFromUserXML($userXml)
                );
            }
        }

        $attributes = \array_merge($attributes, $customerAttr);
    }


    /**
     * Read an user.xml and return the attributes,
     * if some extra attributes defined
     *
     * @param string $file
     *
     * @return array
     */
    protected static function readAttributesFromUserXML(string $file): array
    {
        $cache = 'quiqqer/package/customer/user-extra-attributes/'.\md5($file);

        try {
            return QUI\Cache\Manager::get($cache);
        } catch (QUI\Exception $Exception) {
        }

        $Dom  = QUI\Utils\Text\XML::getDomFromXml($file);
        $Attr = $Dom->getElementsByTagName('attributes');

        if (!$Attr->length) {
            return [];
        }

        /* @var $Attributes \DOMElement */
        $Attributes = $Attr->item(0);
        $list       = $Attributes->getElementsByTagName('attribute');

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
                'name'    => \trim($Attribute->nodeValue),
                'encrypt' => !!$Attribute->getAttribute('encrypt')
            ];
        }

        QUI\Cache\Manager::set($cache, $attributes);

        return $attributes;
    }

    /**
     * @param QUI\Users\User $User
     */
    public static function onUserSaveEnd(QUI\Users\User $User)
    {
        $attributes = $User->getAttributes();
        $data       = [];

        if (isset($attributes['mainGroup'])) {
            try {
                $mainGroup = (int)$attributes['mainGroup'];
                QUI::getGroups()->get($mainGroup);

                $data['mainGroup'] = $mainGroup;
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::addDebug($Exception->getMessage());
            }
        }

        if (isset($attributes['customerId'])) {
            $data['customerId'] = $attributes['customerId'];
        }

        // comments
        if ($User->getAttribute('comments')) {
            $comments = $User->getAttribute('comments');
            $json     = \json_decode($comments, true);

            if (\is_array($json)) {
                $data['comments'] = $comments;
            }
        }

        // saving
        try {
            if (!empty($data)) {
                QUI::getDataBase()->update(
                    Manager::table(),
                    $data,
                    ['id' => $User->getId()]
                );
            }
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addDebug($Exception->getMessage());
        }
    }

    /**
     * @param QUI\Users\User $User
     * @param string|false $code
     * @param null|QUI\Users\User $ParentUser
     *
     * @throws QUI\Users\Exception
     */
    public static function onUserActivateBegin(QUI\Users\User $User, $code, $ParentUser)
    {
        $Group = Utils::getInstance()->getCustomerGroup();

        if (!$Group) {
            return;
        }

        if (!$User->isInGroup($Group->getId())) {
            return;
        }

        try {
            $Package = QUI::getPackage('quiqqer/customer');
            $Config  = $Package->getConfig();
            $login   = (int)$Config->getValue('customer', 'customerLogin');
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
    ) {
        $Order    = $Step->getOrder();
        $Customer = $Order->getCustomer();

        try {
            $User = QUI::getUsers()->get($Customer->getId());

            QUI\ERP\Customer\Customers::getInstance()->addUserToCustomerGroup(
                $User->getId()
            );
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addDebug($Exception->getMessage());
        }
    }
}
