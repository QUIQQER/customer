<?php

namespace QUI\ERP\Customer\OpenItemsList;

use QUI;
use QUI\ERP\Output\OutputProviderInterface;
use QUI\Interfaces\Users\User;
use QUI\Locale;

/**
 * Class OutputProvider
 *
 * Output provider for quiqqer/customer:
 *
 * Outputs previews and PDF files for OpenItemLists
 */
class OutputProvider implements OutputProviderInterface
{
    /**
     * Get output type
     *
     * The output type determines the type of templates/providers that are used
     * to output documents.
     *
     * @return string
     */
    public static function getEntityType()
    {
        return 'OpenItemsList';
    }

    /**
     * Get title for the output entity
     *
     * @param Locale $Locale (optional) - If ommitted use \QUI::getLocale()
     * @return mixed
     */
    public static function getEntityTypeTitle(Locale $Locale = null)
    {
        if (empty($Locale)) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get('quiqqer/customer', 'OutputProvider.entity.title.OpenItemsList');
    }

    /**
     * Get the entity the output is created for
     *
     * @param string|int $entityId
     * @return mixed
     *
     * @throws QUI\Exception
     */
    public static function getEntity($entityId)
    {
        return QUI\ERP\User::convertUserToErpUser(QUI::getUsers()->get($entityId));
    }

    /**
     * Get download filename (without file extension)
     *
     * @param string|int $entityId
     * @return string
     *
     * @throws QUI\Exception
     */
    public static function getDownloadFileName($entityId)
    {
        /** @var QUI\ERP\User $ERPUser */
        $ERPUser = self::getEntity($entityId);
        $Locale  = $ERPUser->getLocale();
        $Date    = \date_create();

        return $Locale->get('quiqqer/customer', 'OutputProvider.download_filename', [
            'date' => $Date->format('Y-m-d'),
            'uid'  => $ERPUser->getId()
        ]);
    }

    /**
     * Get output Locale by entity
     *
     * @param string|int $entityId
     * @return Locale
     *
     * @throws QUI\Exception
     */
    public static function getLocale($entityId)
    {
        /** @var User $ERPUser */
        $ERPUser = self::getEntity($entityId);
        return $ERPUser->getLocale();
    }

    /**
     * Fill the OutputTemplate with appropriate entity data
     *
     * @param string|int $entityId
     * @return array
     *
     * @throws QUI\Exception
     */
    public static function getTemplateData($entityId)
    {
        /** @var User $ERPUser */
        $ERPUser     = self::getEntity($entityId);
        $QuiqqerUser = QUI::getUsers()->get($ERPUser->getId());
        $Address     = $QuiqqerUser->getStandardAddress();

        $Address->clearMail();
        $Address->clearPhone();

        return [
            'Address'       => $Address,
            'OpenItemsList' => Handler::getOpenItemsList($ERPUser),
            'Customer'      => $ERPUser
        ];
    }

    /**
     * Checks if $User has permission to download the document of $entityId
     *
     * @param string|int $entityId
     * @param User $User
     * @return bool
     */
    public static function hasDownloadPermission($entityId, User $User)
    {
        if (!QUI::getUsers()->isAuth($User) || QUI::getUsers()->isNobodyUser($User)) {
            return false;
        }

        try {
            /** @var User $ERPUser */
            $ERPUser = self::getEntity($entityId);
            return $ERPUser->getId() === $User->getId();
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return false;
        }
    }

    /**
     * Get e-mail address of the document recipient
     *
     * @param string|int $entityId
     * @return string|false - E-Mail address or false if no e-mail address available
     *
     * @throws QUI\Exception
     */
    public static function getEmailAddress($entityId)
    {
        /** @var User $ERPUser */
        $ERPUser = self::getEntity($entityId);
        $email   = $ERPUser->getAttribute('email');

        return $email ?: false;
    }

    /**
     * Get e-mail subject when document is sent via mail
     *
     * @param string|int $entityId
     * @return string
     *
     * @throws QUI\Exception
     */
    public static function getMailSubject($entityId)
    {
        /** @var User $ERPUser */
        $ERPUser = self::getEntity($entityId);
        $Locale  = $ERPUser->getLocale();

        return QUI::getLocale()->get('quiqqer/customer', 'mail.OpenItemsList.subject', [
            'date' => $Locale->formatDate(time())
        ]);
    }

    /**
     * Get e-mail body when document is sent via mail
     *
     * @param string|int $entityId
     * @return string
     *
     * @throws QUI\Exception
     */
    public static function getMailBody($entityId)
    {
        /** @var User $ERPUser */
        $ERPUser = self::getEntity($entityId);
        $Locale  = $ERPUser->getLocale();

        return QUI::getLocale()->get('quiqqer/customer', 'mail.OpenItemsList.body', [
            'date'         => $Locale->formatDate(time()),
            'customerName' => $ERPUser->getName()
        ]);
    }
}
