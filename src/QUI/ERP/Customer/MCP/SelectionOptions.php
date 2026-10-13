<?php

declare(strict_types=1);

namespace QUI\ERP\Customer\MCP;

use QUI;
use QUI\ERP\Accounting\Payments\Payments;
use QUI\ERP\Shipping\Shipping;

final class SelectionOptions
{
    /** @return array<string, mixed> */
    public function get(): array
    {
        $paymentsInstalled = QUI::getPackageManager()->isInstalled('quiqqer/payments');
        $shippingInstalled = QUI::getPackageManager()->isInstalled('quiqqer/shipping');
        $payments = $paymentsInstalled ? Payments::getInstance()->getPayments() : [];
        $shipping = $shippingInstalled ? Shipping::getInstance()->getShippingList() : [];

        return [
            'languages' => QUI\Translator::getAvailableLanguages() ?? [],
            'countries' => QUI\Countries\Manager::getAllCountryCodes(),
            'paymentsInstalled' => $paymentsInstalled,
            'shippingInstalled' => $shippingInstalled,
            'paymentMethods' => $this->parseEntries($payments),
            'shippingTypes' => $this->parseEntries($shipping)
        ];
    }

    /** @param array<string, mixed> $data */
    public static function validateSelections(array $data): void
    {
        if (isset($data['paymentMethod'])) {
            if (!QUI::getPackageManager()->isInstalled('quiqqer/payments')) {
                throw new QUI\Exception('Payment selection requires quiqqer/payments.', 400);
            }

            Payments::getInstance()->getPayment($data['paymentMethod']);
        }

        if (isset($data['shippingType'])) {
            if (!QUI::getPackageManager()->isInstalled('quiqqer/shipping')) {
                throw new QUI\Exception('Shipping selection requires quiqqer/shipping.', 400);
            }

            Shipping::getInstance()->getShippingEntry($data['shippingType']);
        }
    }

    /**
     * @param array<QUI\CRUD\Child> $entries
     * @return list<array<string, mixed>>
     */
    private function parseEntries(array $entries): array
    {
        $result = [];

        foreach ($entries as $Entry) {
            $title = $Entry->getAttribute('title');

            if (is_string($title)) {
                $title = json_decode($title, true) ?? $title;
            }

            $result[] = [
                'id' => $Entry->getId(),
                'title' => $title,
                'active' => (bool)$Entry->getAttribute('active')
            ];
        }

        return $result;
    }
}
