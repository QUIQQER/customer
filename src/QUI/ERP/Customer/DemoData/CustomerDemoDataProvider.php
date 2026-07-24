<?php

declare(strict_types=1);

namespace QUI\ERP\Customer\DemoData;

use Doctrine\DBAL\Connection;
use QUI\Locale;
use QUI\ERP\DemoData\Contract\DemoDataCreatorInterface;
use QUI\ERP\DemoData\Contract\DemoDataProviderInterface;
use QUI\ERP\Customer\Customers;

final class CustomerDemoDataProvider implements DemoDataProviderInterface
{
    public function getIdentifier(): string
    {
        return 'quiqqer.customer';
    }

    public function getTitle(?Locale $locale = null): string
    {
        $locale ??= \QUI::getLocale();

        return (string)$locale->get('quiqqer/customer', 'package.title');
    }

    public function getDemoDataCreator(Connection $connection): DemoDataCreatorInterface
    {
        return new CustomerDemoDataCreator(Customers::getInstance());
    }
}
