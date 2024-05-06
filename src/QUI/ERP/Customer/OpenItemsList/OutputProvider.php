<?php

namespace QUI\ERP\Customer\OpenItemsList;

use QUI;
use QUI\ERP\Output\OutputProviderInterface;
use QUI\Interfaces\Users\User;
use QUI\Locale;

use function date_create;

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
    public static function getEntityType(): string
    {
        return 'OpenItemsList';
    }

    /**
     * Get title for the output entity
     *
     * @param Locale|null $Locale $Locale (optional) - If ommitted use \QUI::getLocale()
     * @return string
     */
    public static function getEntityTypeTitle(Locale $Locale = null): string
    {
        if (empty($Locale)) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get('quiqqer/customer', 'OutputProvider.entity.title.OpenItemsList');
    }

    /**
     * Get the entity the output is created for
     *
     * @param int|string $entityId
     * @return mixed
     *
     * @throws QUI\Exception
     */
    public static function getEntity(int|string $entityId): mixed
    {
        return QUI\ERP\User::convertUserToErpUser(QUI::getUsers()->get($entityId));
    }

    /**
     * Get download filename (without file extension)
     *
     * @param int|string $entityId
     * @return string
     *
     * @throws QUI\Exception
     */
    public static function getDownloadFileName(int|string $entityId): string
    {
        /** @var QUI\ERP\User $ERPUser */
        $ERPUser = self::getEntity($entityId);
        $Locale = $ERPUser->getLocale();
        $Date = date_create();

        return $Locale->get('quiqqer/customer', 'OutputProvider.download_filename', [
            'date' => $Date->format('Y-m-d'),
            'uid' => $ERPUser->getUUID()
        ]);
    }

    /**
     * Get output Locale by entity
     *
     * @param int|string $entityId
     * @return Locale
     *
     * @throws QUI\Exception
     */
    public static function getLocale(int|string $entityId): Locale
    {
        /** @var User $ERPUser */
        $ERPUser = self::getEntity($entityId);

        return $ERPUser->getLocale();
    }

    /**
     * Fill the OutputTemplate with appropriate entity data
     *
     * @param int|string $entityId
     * @return array
     *
     * @throws QUI\Exception
     */
    public static function getTemplateData(int|string $entityId): array
    {
        /** @var User $ERPUser */
        $ERPUser = self::getEntity($entityId);
        $QuiqqerUser = QUI::getUsers()->get($ERPUser->getUUID());
        $Address = $QuiqqerUser->getStandardAddress();

        $Address->clearMail();
        $Address->clearPhone();

        return [
            'Address' => $Address,
            'OpenItemsList' => Handler::getOpenItemsList($ERPUser),
            'Customer' => $ERPUser
        ];
    }

    /**
     * Checks if $User has permission to download the document of $entityId
     *
     * @param int|string $entityId
     * @param User $User
     * @return bool
     */
    public static function hasDownloadPermission(int|string $entityId, User $User): bool
    {
        if (!QUI::getUsers()->isAuth($User) || QUI::getUsers()->isNobodyUser($User)) {
            return false;
        }

        try {
            /** @var User $ERPUser */
            $ERPUser = self::getEntity($entityId);

            return $ERPUser->getUUID() === $User->getUUID();
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return false;
        }
    }

    /**
     * Get e-mail address of the document recipient
     *
     * @param int|string $entityId
     * @return string|false - E-Mail address or false if no e-mail address available
     *
     * @throws QUI\Exception
     */
    public static function getEmailAddress(int|string $entityId): bool|string
    {
        /** @var User $ERPUser */
        $ERPUser = self::getEntity($entityId);
        return QUI\ERP\Customer\Utils::getInstance()->getEmailByCustomer($ERPUser);
    }

    /**
     * Get e-mail subject when document is sent via mail
     *
     * @param int|string $entityId
     * @return string
     *
     * @throws QUI\Exception
     */
    public static function getMailSubject(int|string $entityId): string
    {
        /** @var User $ERPUser */
        $ERPUser = self::getEntity($entityId);
        $Locale = $ERPUser->getLocale();

        return QUI::getLocale()->get('quiqqer/customer', 'mail.OpenItemsList.subject', [
            'date' => $Locale->formatDate(time())
        ]);
    }

    /**
     * Get e-mail body when document is sent via mail
     *
     * @param int|string $entityId
     * @return string
     *
     * @throws QUI\Exception
     */
    public static function getMailBody(int|string $entityId): string
    {
        /** @var User $ERPUser */
        $ERPUser = self::getEntity($entityId);
        $Locale = $ERPUser->getLocale();

        return QUI::getLocale()->get('quiqqer/customer', 'mail.OpenItemsList.body', [
            'date' => $Locale->formatDate(time()),
            'customerName' => $ERPUser->getName()
        ]);
    }
}
