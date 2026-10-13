<?php

declare(strict_types=1);

namespace QUI\ERP\Customer\MCP;

use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Builder;
use QUI\AI\MCP\ProviderInterface;
use QUI\AI\MCP\Server;
use QUI\AI\MCP\ToolHelper;
use QUI\Permissions\Permission;
use Throwable;

final class Provider implements ProviderInterface
{
    public const PERMISSION = 'quiqqer.customer.mcp';

    public function register(Builder $serverBuilder): void
    {
        try {
            Permission::checkPermission(self::PERMISSION, Server::getRequestUser());
        } catch (Throwable) {
            return;
        }

        $Api = new CustomerApi();
        $serverBuilder->addTool(
            fn(): array | CallToolResult => $this->execute('view', fn(): array => (new SelectionOptions())->get()),
            name: 'quiqqer_customer_options',
            description: 'Lists valid language and country codes and optional payment/shipping IDs for customer data.',
            inputSchema: ['type' => 'object', 'additionalProperties' => false, 'properties' => new \stdClass()]
        );
        $identifierSchema = [
            'type' => 'string',
            'minLength' => 1,
            'maxLength' => 64,
            'description' => 'Customer user UUID or legacy numeric user ID returned by search. '
                . 'This is not the customer number.'
        ];
        $serverBuilder->addTool(
            fn(mixed $query = '', mixed $limit = 20, mixed $offset = 0): array | CallToolResult =>
                $this->execute('view', fn(): array => $Api->search($query, $limit, $offset)),
            name: 'quiqqer_customer_search',
            description: 'Search ERP customers by customer number, name, company or email. '
                . 'Returns customer UUIDs and paginated summaries from the configured customer group.',
            inputSchema: [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'query' => ['type' => 'string', 'maxLength' => 255, 'default' => ''],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
                    'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 2147483647, 'default' => 0]
                ]
            ]
        );
        $serverBuilder->addTool(
            fn(mixed $customerId = '', mixed $customerNo = ''): array | CallToolResult =>
                $this->execute('view', fn(): array => $Api->get($customerId, $customerNo)),
            name: 'quiqqer_customer_get',
            description: 'Read one ERP customer including addresses, payment term, contact person and '
                . 'commercial master data. Supply exactly one of customerId or customerNo.',
            inputSchema: [
                'type' => 'object',
                'additionalProperties' => false,
                'oneOf' => [
                    ['required' => ['customerId'], 'not' => ['required' => ['customerNo']]],
                    ['required' => ['customerNo'], 'not' => ['required' => ['customerId']]]
                ],
                'properties' => [
                    'customerId' => $identifierSchema,
                    'customerNo' => [
                        'type' => 'string',
                        'minLength' => 1,
                        'maxLength' => 64,
                        'description' => 'Exact customer number, with or without the configured prefix.'
                    ]
                ]
            ]
        );
        $serverBuilder->addTool(
            fn(mixed $data): array | CallToolResult =>
                $this->execute('create', fn(): array => $Api->create($data)),
            name: 'quiqqer_customer_create',
            description: 'Create an ERP customer in the configured customer group. Omit customerNo to '
                . 'allocate the next number. Supports address, payment term, contact person and commercial master data.',
            inputSchema: [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['data'],
                'properties' => ['data' => CustomerData::schema()]
            ]
        );
        $serverBuilder->addTool(
            fn(mixed $customerId, mixed $data): array | CallToolResult =>
                $this->execute('edit', fn(): array => $Api->update($customerId, $data)),
            name: 'quiqqer_customer_update',
            description: 'Update selected ERP customer master data fields. Omitted fields remain unchanged. '
                . 'Email and phone lists replace only the supplied lists. Null contactPerson clears its assignment.',
            inputSchema: [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['customerId', 'data'],
                'properties' => [
                    'customerId' => $identifierSchema,
                    'data' => CustomerData::schema()
                ]
            ]
        );

        (new NotesTools($this, $Api))->register($serverBuilder);
        (new OpenItemsTools($this, $Api))->register($serverBuilder);
    }

    /**
     * @param callable(): array<string, mixed> $Callback
     * @return array<string, mixed>|CallToolResult
     */
    public function execute(string $action, callable $Callback): array | CallToolResult
    {
        try {
            $User = Server::getRequestUser();
            Permission::checkPermission(self::PERMISSION, $User);
            Permission::checkPermission('quiqqer.customer.' . $action, $User);

            return $Callback();
        } catch (Throwable $Exception) {
            return ToolHelper::parseExceptionToResult($Exception);
        }
    }
}
