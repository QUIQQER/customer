<?php

declare(strict_types=1);

namespace QUI\ERP\Customer\MCP;

use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Builder;
use QUI;
use QUI\AI\MCP\Server;
use QUI\ERP\Customer\OpenItemsList\Handler;
use QUI\ERP\Customer\OpenItemsList\Item;
use QUI\ERP\Customer\OpenItemsList\ItemsList;
use QUI\Permissions\Permission;

final class OpenItemsTools
{
    public function __construct(private readonly Provider $Provider, private readonly CustomerApi $Api)
    {
    }

    public function register(Builder $Builder): void
    {
        $Builder->addTool(
            fn(
                mixed $customerId,
                mixed $currency = '',
                mixed $query = '',
                mixed $overdueOnly = false,
                mixed $limit = 20,
                mixed $offset = 0
            ): array | CallToolResult => $this->Provider->execute(
                'view',
                fn(): array => $this->get($customerId, $currency, $query, $overdueOnly, $limit, $offset)
            ),
            name: 'quiqqer_customer_open_items',
            description: 'Read live customer open invoices and configured open orders, newest first. '
                . 'Returns numeric amounts and totals per currency across all matching items, before pagination. '
                . 'Requires the additional customer OpenItemsList.view permission.',
            inputSchema: [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['customerId'],
                'properties' => [
                    'customerId' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 64],
                    'currency' => ['type' => 'string', 'maxLength' => 3, 'default' => ''],
                    'query' => ['type' => 'string', 'maxLength' => 255, 'default' => ''],
                    'overdueOnly' => ['type' => 'boolean', 'default' => false],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
                    'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 2147483647, 'default' => 0]
                ]
            ]
        );
    }

    /** @return array<string, mixed> */
    private function get(
        mixed $customerId,
        mixed $currency,
        mixed $query,
        mixed $overdueOnly,
        mixed $limit,
        mixed $offset
    ): array {
        Permission::checkPermission(Handler::PERMISSION_OPENITEMS_VIEW, Server::getRequestUser());
        CustomerData::validate($currency, ['type' => 'string', 'maxLength' => 3], 'currency');
        CustomerData::validate($query, ['type' => 'string', 'maxLength' => 255], 'query');
        CustomerData::validate($overdueOnly, ['type' => 'boolean'], 'overdueOnly');
        CustomerData::validate($limit, ['type' => 'integer', 'minimum' => 1, 'maximum' => 100], 'limit');
        CustomerData::validate($offset, ['type' => 'integer', 'minimum' => 0, 'maximum' => 2147483647], 'offset');

        if ($currency !== '') {
            if (!preg_match('/^[A-Z]{3}$/D', $currency)) {
                throw new QUI\Exception('Currency must be an uppercase three-letter currency code.', 400);
            }

            QUI\ERP\Currency\Handler::getCurrency($currency);
        }

        $User = $this->Api->getCustomer($customerId);
        $result = self::format(Handler::getOpenItemsList($User), $currency, trim($query), $overdueOnly, $limit, $offset);
        $result['invoicesInstalled'] = QUI::getPackageManager()->isInstalled('quiqqer/invoice');
        $result['ordersInstalled'] = QUI::getPackageManager()->isInstalled('quiqqer/order');

        return $result;
    }

    /** @return array<string, mixed> */
    public static function format(
        ItemsList $List,
        string $currency,
        string $query,
        bool $overdueOnly,
        int $limit,
        int $offset
    ): array {
        $items = [];
        $totals = [];

        foreach ($List->getItems() as $Item) {
            $code = $Item->getCurrency()->getCode();

            if (
                ($currency !== '' && $code !== $currency)
                || ($query !== '' && mb_stripos($Item->getDocumentNo(), $query) === false)
                || ($overdueOnly && $Item->getDaysDue() <= 0)
            ) {
                continue;
            }

            $entry = self::parse($Item);
            $items[] = $entry;
            $totals[$code] ??= [
                'net' => 0.0,
                'vat' => 0.0,
                'gross' => 0.0,
                'paid' => 0.0,
                'open' => 0.0
            ];

            foreach (array_keys($totals[$code]) as $amount) {
                $totals[$code][$amount] += $entry[$amount];
            }
        }

        usort($items, static fn(array $a, array $b): int => strtotime($b['date']) <=> strtotime($a['date']));
        ksort($totals);

        return [
            'asOf' => $List->getDate()->format(DATE_ATOM),
            'total' => count($items),
            'limit' => $limit,
            'offset' => $offset,
            'totalsByCurrency' => $totals,
            'items' => array_slice($items, $offset, $limit)
        ];
    }

    /** @return array<string, mixed> */
    private static function parse(Item $Item): array
    {
        return [
            'documentId' => $Item->getDocumentId(),
            'documentNo' => $Item->getDocumentNo(),
            'documentType' => $Item->getDocumentType(),
            'hash' => $Item->getHash(),
            'globalProcessId' => $Item->getGlobalProcessId(),
            'date' => $Item->getDate()->format(DATE_ATOM),
            'dueDate' => $Item->getDueDate()->format(DATE_ATOM),
            'daysDue' => $Item->getDaysDue(),
            'dunningLevel' => $Item->getDunningLevel(),
            'currency' => $Item->getCurrency()->getCode(),
            'net' => $Item->getAmountTotalNet(),
            'vat' => $Item->getAmountTotalVat(),
            'gross' => $Item->getAmountTotalSum(),
            'paid' => $Item->getAmountPaid(),
            'open' => $Item->getAmountOpen()
        ];
    }
}
