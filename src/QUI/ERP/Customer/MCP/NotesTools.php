<?php

declare(strict_types=1);

namespace QUI\ERP\Customer\MCP;

use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Builder;
use QUI;
use QUI\AI\MCP\Server;
use QUI\ERP\Comments;
use QUI\ERP\Customer\Customers;
use QUI\Permissions\Permission;

final class NotesTools
{
    public function __construct(private readonly Provider $Provider, private readonly CustomerApi $Api)
    {
    }

    public function register(Builder $Builder): void
    {
        $customerSchema = ['type' => 'string', 'minLength' => 1, 'maxLength' => 64];
        $messageSchema = ['type' => 'string', 'minLength' => 1, 'maxLength' => 10000];

        foreach (['comments', 'history'] as $kind) {
            $Builder->addTool(
                fn(mixed $customerId, mixed $limit = 20, mixed $offset = 0): array | CallToolResult =>
                    $this->Provider->execute('view', fn(): array => $this->list($customerId, $kind, $limit, $offset)),
                name: 'quiqqer_customer_' . $kind . '_list',
                description: 'Read customer ' . $kind . ' from ERP modules, newest first, as plain text.',
                inputSchema: [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['customerId'],
                    'properties' => [
                        'customerId' => $customerSchema,
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
                        'offset' => ['type' => 'integer', 'minimum' => 0, 'default' => 0]
                    ]
                ]
            );
        }

        $Builder->addTool(
            fn(mixed $customerId, mixed $message): array | CallToolResult =>
                $this->Provider->execute('editComments', fn(): array => $this->write($customerId, $message)),
            name: 'quiqqer_customer_comment_add',
            description: 'Add a plain-text customer comment. Requires customer editComments and Core user edit rights.',
            inputSchema: [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['customerId', 'message'],
                'properties' => ['customerId' => $customerSchema, 'message' => $messageSchema]
            ]
        );
        $Builder->addTool(
            fn(mixed $customerId, mixed $commentId, mixed $message): array | CallToolResult =>
                $this->Provider->execute('editComments', fn(): array => $this->update($customerId, $commentId, $message)),
            name: 'quiqqer_customer_comment_update',
            description: 'Edit a comment owned by this customer and the customer module. '
                . 'Comments from other ERP modules must be edited through their own APIs.',
            inputSchema: [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['customerId', 'commentId', 'message'],
                'properties' => [
                    'customerId' => $customerSchema,
                    'commentId' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 64],
                    'message' => $messageSchema
                ]
            ]
        );
    }

    /** @return array<string, mixed> */
    private function list(mixed $customerId, string $kind, mixed $limit, mixed $offset): array
    {
        CustomerData::validate($limit, ['type' => 'integer', 'minimum' => 1, 'maximum' => 100], 'limit');
        CustomerData::validate($offset, ['type' => 'integer', 'minimum' => 0, 'maximum' => 2147483647], 'offset');
        $User = $this->Api->getCustomer($customerId);
        $entries = $kind === 'history'
            ? Comments::getHistoryByUser($User)->toArray()
            : (Comments::getCommentsByUser($User)?->toArray() ?? []);
        usort($entries, static fn(array $a, array $b): int => ($b['time'] ?? 0) <=> ($a['time'] ?? 0));
        $result = array_map($this->parse(...), array_slice($entries, $offset, $limit));

        return [
            'total' => count($entries),
            'limit' => $limit,
            'offset' => $offset,
            $kind => $result
        ];
    }

    /** @return array<string, mixed> */
    private function update(mixed $customerId, mixed $commentId, mixed $message): array
    {
        CustomerData::validate($commentId, ['type' => 'string', 'minLength' => 1, 'maxLength' => 64], 'commentId');

        return $this->write($customerId, $message, $commentId);
    }

    /** @return array<string, mixed> */
    private function write(mixed $customerId, mixed $message, mixed $commentId = null): array
    {
        CustomerData::validate($message, ['type' => 'string', 'minLength' => 1, 'maxLength' => 10000], 'message');
        $User = $this->Api->getCustomer($customerId);
        Permission::checkPermission('quiqqer.admin.users.edit', Server::getRequestUser());
        $Customers = Customers::getInstance();
        $storedMessage = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        if ($commentId !== null) {
            CustomerData::validate($commentId, ['type' => 'string', 'minLength' => 1, 'maxLength' => 64], 'commentId');
            $found = false;

            foreach ($Customers->getUserComments($User)->toArray() as $entry) {
                if (($entry['id'] ?? '') === $commentId && ($entry['source'] ?? '') === 'quiqqer/customer') {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                throw new QUI\Exception('Customer comment not found or owned by another module.', 404);
            }

            $Customers->editComment($User, $commentId, 'quiqqer/customer', $storedMessage);
        } else {
            $Customers->addCommentToUser($User, $storedMessage);
        }

        $entries = $Customers->getUserComments($User)->toArray();

        if ($entries === []) {
            throw new QUI\Exception('Customer comment could not be saved.');
        }

        $entry = $entries[array_key_last($entries)];

        if ($commentId !== null) {
            foreach ($entries as $candidate) {
                if (($candidate['id'] ?? '') === $commentId) {
                    $entry = $candidate;
                    break;
                }
            }
        }

        return $this->parse($entry);
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function parse(array $entry): array
    {
        return [
            'id' => (string)($entry['id'] ?? ''),
            'time' => (int)($entry['time'] ?? 0),
            'source' => (string)($entry['source'] ?? ''),
            'message' => html_entity_decode(strip_tags((string)($entry['message'] ?? '')), ENT_QUOTES, 'UTF-8'),
            'objectHash' => $entry['objectHash'] ?? null
        ];
    }
}
