<?php

declare(strict_types=1);

namespace QUI\ERP\Customer\MCP;

use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Builder;
use QUI;
use QUI\ERP\Customer\CustomerFiles;

final class FileTools
{
    private const MAX_UPLOAD_BYTES = 5_242_880;
    private const MAX_CHUNK_BYTES = 1_048_576;

    public function __construct(private readonly Provider $Provider, private readonly CustomerApi $Api)
    {
    }

    public function register(Builder $Builder): void
    {
        $customerSchema = ['type' => 'string', 'minLength' => 1, 'maxLength' => 64];
        $hashSchema = ['type' => 'string', 'pattern' => '^[a-f0-9]{64}$'];
        $Builder->addTool(
            fn(mixed $customerId, mixed $limit = 20, mixed $offset = 0): array | CallToolResult =>
                $this->Provider->execute('fileView', fn(): array => $this->getList($customerId, $limit, $offset)),
            name: 'quiqqer_customer_files_list',
            description: 'List customer files with filename, hash and size. Requires customer fileView permission.',
            inputSchema: [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['customerId'],
                'properties' => [
                    'customerId' => $customerSchema,
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
                    'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 2147483647, 'default' => 0]
                ]
            ]
        );
        $Builder->addTool(
            fn(mixed $customerId, mixed $fileHash, mixed $offset = 0, mixed $maxBytes = 1_048_576): array | CallToolResult =>
                $this->Provider->execute('fileView', fn(): array => $this->download($customerId, $fileHash, $offset, $maxBytes)),
            name: 'quiqqer_customer_file_download',
            description: 'Download a customer file by hash as a bounded base64 chunk. '
                . 'Use nextOffset until complete is true. Requires customer fileView permission.',
            inputSchema: [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['customerId', 'fileHash'],
                'properties' => [
                    'customerId' => $customerSchema,
                    'fileHash' => $hashSchema,
                    'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 2147483647, 'default' => 0],
                    'maxBytes' => ['type' => 'integer', 'minimum' => 1, 'maximum' => self::MAX_CHUNK_BYTES]
                ]
            ]
        );
        $Builder->addTool(
            fn(mixed $customerId, mixed $filename, mixed $contentBase64): array | CallToolResult =>
                $this->Provider->execute('fileUpload', fn(): array => $this->upload($customerId, $filename, $contentBase64)),
            name: 'quiqqer_customer_file_upload',
            description: 'Create a new customer file from base64 content, up to 5 MiB. '
                . 'Existing filenames are rejected. Requires customer fileUpload permission.',
            inputSchema: [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['customerId', 'filename', 'contentBase64'],
                'properties' => [
                    'customerId' => $customerSchema,
                    'filename' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
                    'contentBase64' => ['type' => 'string', 'maxLength' => 6_990_508]
                ]
            ]
        );
        $Builder->addTool(
            fn(mixed $customerId, mixed $fileHash): array | CallToolResult =>
                $this->Provider->execute('fileEdit', fn(): array => $this->delete($customerId, $fileHash)),
            name: 'quiqqer_customer_file_delete',
            description: 'Delete one customer file by its hash. Requires customer fileEdit permission.',
            inputSchema: [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['customerId', 'fileHash'],
                'properties' => ['customerId' => $customerSchema, 'fileHash' => $hashSchema]
            ]
        );
    }

    /** @return array<string, mixed> */
    private function getList(mixed $customerId, mixed $limit, mixed $offset): array
    {
        CustomerData::validate($limit, ['type' => 'integer', 'minimum' => 1, 'maximum' => 100], 'limit');
        CustomerData::validate($offset, ['type' => 'integer', 'minimum' => 0, 'maximum' => 2147483647], 'offset');
        $User = $this->Api->getCustomer($customerId);
        $folder = $this->getFolder($User);
        $entries = [];

        foreach (CustomerFiles::getFileList($User->getUUID()) as $file) {
            $path = $folder . DIRECTORY_SEPARATOR . $file['basename'];

            if (!is_file($path) || is_link($path)) {
                continue;
            }

            $entries[] = $this->parseFile($path);
        }

        return [
            'total' => count($entries),
            'limit' => $limit,
            'offset' => $offset,
            'files' => array_slice($entries, $offset, $limit)
        ];
    }

    private function resolveFile(mixed $customerId, mixed $hash): string
    {
        CustomerData::validate($hash, ['type' => 'string', 'minLength' => 64, 'maxLength' => 64], 'fileHash');

        if (!preg_match('/^[a-f0-9]{64}$/D', $hash)) {
            throw new QUI\Exception('Invalid customer file hash.', 400);
        }

        $User = $this->Api->getCustomer($customerId);
        $folder = $this->getFolder($User);

        foreach (QUI\Utils\System\File::readDir($folder) as $filename) {
            $path = $folder . DIRECTORY_SEPARATOR . $filename;

            if (hash_equals(hash('sha256', $filename), $hash) && is_file($path) && !is_link($path)) {
                return $path;
            }
        }

        throw new QUI\Exception('Customer file not found.', 404);
    }

    /** @return array<string, mixed> */
    private function download(mixed $customerId, mixed $hash, mixed $offset, mixed $maxBytes): array
    {
        CustomerData::validate($offset, ['type' => 'integer', 'minimum' => 0, 'maximum' => 2147483647], 'offset');
        CustomerData::validate($maxBytes, ['type' => 'integer', 'minimum' => 1, 'maximum' => self::MAX_CHUNK_BYTES], 'maxBytes');
        $path = $this->resolveFile($customerId, $hash);
        $metadata = $this->parseFile($path);

        if ($offset > $metadata['size']) {
            throw new QUI\Exception('Download offset exceeds file size.', 400);
        }

        $length = min($maxBytes, $metadata['size'] - $offset);
        $content = $length > 0 ? file_get_contents($path, false, null, $offset, $length) : '';

        if ($content === false) {
            throw new QUI\Exception('Could not read customer file.');
        }

        $nextOffset = $offset + strlen($content);

        return $metadata + [
            'encoding' => 'base64',
            'contentBase64' => base64_encode($content),
            'offset' => $offset,
            'chunkSize' => strlen($content),
            'complete' => $nextOffset >= $metadata['size'],
            'nextOffset' => $nextOffset < $metadata['size'] ? $nextOffset : null
        ];
    }

    /** @return array<string, mixed> */
    private function upload(mixed $customerId, mixed $filename, mixed $contentBase64): array
    {
        CustomerData::validate($filename, ['type' => 'string', 'minLength' => 1, 'maxLength' => 255], 'filename');
        CustomerData::validate($contentBase64, ['type' => 'string', 'maxLength' => 6_990_508], 'contentBase64');
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (
            $filename !== basename($filename)
            || str_contains($filename, '\\')
            || str_starts_with($filename, '.')
            || trim($filename) !== $filename
            || strlen($filename) > 255
            || preg_match('/[\x00-\x1f\x7f]/', $filename)
            || strpbrk($filename, '<>"\'&:') !== false
            || preg_match('/^(php\d*|phtml|phar|cgi|sh|exe)$/D', $extension)
        ) {
            throw new QUI\Exception('Unsafe customer filename.', 400);
        }

        $content = base64_decode($contentBase64, true);

        if (
            $content === false
            || base64_encode($content) !== $contentBase64
            || strlen($content) > self::MAX_UPLOAD_BYTES
        ) {
            throw new QUI\Exception('Invalid base64 content or upload exceeds 5 MiB.', 400);
        }

        $User = $this->Api->getCustomer($customerId);
        $path = $this->getFolder($User) . DIRECTORY_SEPARATOR . $filename;

        if (file_exists($path) || is_link($path)) {
            throw new QUI\Exception('Customer filename already exists.', 409);
        }

        $Handle = fopen($path, 'x+b');

        if ($Handle === false) {
            throw new QUI\Exception('Could not create customer file.');
        }

        try {
            $written = fwrite($Handle, $content);

            if ($written !== strlen($content)) {
                throw new QUI\Exception('Could not write customer file.');
            }
        } catch (\Throwable $Exception) {
            fclose($Handle);
            unlink($path);
            throw $Exception;
        }

        fclose($Handle);

        return $this->parseFile($path);
    }

    /** @return array<string, mixed> */
    private function delete(mixed $customerId, mixed $hash): array
    {
        $path = $this->resolveFile($customerId, $hash);

        if (!unlink($path)) {
            throw new QUI\Exception('Could not delete customer file.');
        }

        return ['deleted' => true, 'fileHash' => $hash];
    }

    /** @return array<string, mixed> */
    private function parseFile(string $path): array
    {
        $size = filesize($path);

        if ($size === false) {
            throw new QUI\Exception('Could not read customer file size.');
        }

        return [
            'filename' => basename($path),
            'hash' => hash('sha256', basename($path)),
            'size' => $size,
            'mimeType' => class_exists(\finfo::class)
                ? ((new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream')
                : 'application/octet-stream'
        ];
    }

    private function getFolder(QUI\Interfaces\Users\User $User): string
    {
        $folder = CustomerFiles::getFolderPath($User);

        if (is_link($folder)) {
            throw new QUI\Exception('Customer file directories must not be symbolic links.', 403);
        }

        return $folder;
    }
}
