<?php

declare(strict_types=1);

namespace QUI\ERP\Customer\MCP;

use QUI;

/**
 * The same contract is advertised to MCP clients and checked before any writes.
 */
final class CustomerData
{
    public const ATTRIBUTES = [
        'language' => 'lang',
        'website' => 'quiqqer.erp.customer.website',
        'referenceCode' => 'quiqqer.erp.customer.referenceCode',
        'paymentTerm' => 'quiqqer.erp.customer.payment.term',
        'vatId' => 'quiqqer.erp.euVatId',
        'taxId' => 'quiqqer.erp.taxId',
        'chUid' => 'quiqqer.erp.chUID',
        'companyNumber' => 'quiqqer.erp.company.identification.number',
        'pricingMode' => 'quiqqer.erp.isNettoUser',
        'discount' => 'quiqqer.erp.discount',
        'paymentMethod' => 'quiqqer.erp.standard.payment',
        'shippingType' => 'quiqqer.erp.standard.shippingType'
    ];

    public const ADDRESS_FIELDS = [
        'salutation',
        'firstname',
        'lastname',
        'company',
        'street_no',
        'zip',
        'city',
        'country',
        'suffix'
    ];

    /** @return array<string, mixed> */
    public static function schema(): array
    {
        $properties = [];

        foreach (self::ATTRIBUTES as $field => $attribute) {
            $properties[$field] = ['type' => 'string', 'maxLength' => 255];
        }

        $properties['customerNo'] = [
            'type' => 'integer',
            'minimum' => 1,
            'maximum' => 2147483646,
            'description' => 'Unprefixed customer number. Omit on creation to use the configured number range.'
        ];
        $properties['paymentTerm'] = [
            'type' => ['integer', 'null'],
            'minimum' => 0,
            'maximum' => 3650,
            'description' => 'Payment term in days. Null restores the ERP default.'
        ];
        $properties['pricingMode'] = [
            'type' => 'integer',
            'enum' => [0, 1, 2],
            'description' => '0=automatic, 1=net prices, 2=gross prices.'
        ];
        $properties['discount'] = ['type' => 'number', 'minimum' => 0, 'maximum' => 100];
        foreach (['paymentMethod', 'shippingType'] as $field) {
            $properties[$field] = [
                'type' => ['integer', 'null'],
                'minimum' => 1,
                'maximum' => 2147483647,
                'description' => 'Existing ID from quiqqer_customer_options. Null clears the default.'
            ];
        }
        $properties['address'] = self::addressSchema();
        $properties['deliveryAddress'] = self::addressSchema();
        $contactSchema = self::addressSchema();
        $contactSchema['type'] = ['object', 'null'];
        $contactSchema['properties']['addressId'] = [
            'type' => 'string',
            'minLength' => 1,
            'maxLength' => 64,
            'description' => 'Existing address UUID or numeric ID belonging to this customer. '
                . 'Omit to edit the current contact or create one. Null contactPerson clears the assignment.'
        ];
        $properties['contactPerson'] = $contactSchema;

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'minProperties' => 1,
            'properties' => $properties
        ];
    }

    /** @return array<string, mixed> */
    private static function addressSchema(): array
    {
        $properties = [];

        foreach (self::ADDRESS_FIELDS as $field) {
            $properties[$field] = ['type' => 'string', 'maxLength' => 255];
        }

        $properties['mails'] = [
            'type' => 'array',
            'maxItems' => 20,
            'items' => ['type' => 'string', 'format' => 'email', 'maxLength' => 254],
            'description' => 'Replaces the email list. An empty list clears it; omission preserves it.'
        ];
        $properties['phones'] = [
            'type' => 'array',
            'maxItems' => 20,
            'description' => 'Replaces the phone list. An empty list clears it; omission preserves it.',
            'items' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['type', 'no'],
                'properties' => [
                    'type' => ['type' => 'string', 'enum' => ['tel', 'mobile', 'fax']],
                    'no' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 100]
                ]
            ]
        ];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'minProperties' => 1,
            'properties' => $properties
        ];
    }

    /**
     * Validate the deliberately small JSON Schema subset used by these tools.
     * Do not rely on the client or SDK to validate nested data or coerce values.
     *
     * @param array<string, mixed> $schema
     */
    public static function validate(mixed $value, array $schema, string $path = 'data'): void
    {
        $types = (array)$schema['type'];
        $validType = false;

        foreach ($types as $type) {
            $validType = $validType || match ($type) {
                'null' => $value === null,
                'string' => is_string($value),
                'integer' => is_int($value),
                'boolean' => is_bool($value),
                'number' => (is_int($value) || is_float($value)) && is_finite((float)$value),
                'array' => is_array($value) && array_is_list($value),
                'object' => is_array($value) && ($value === [] || !array_is_list($value)),
                default => false
            };
        }

        if (!$validType) {
            throw new QUI\Exception($path . ' has an invalid type.', 400);
        }

        if ($value === null) {
            return;
        }

        if (isset($schema['enum']) && !in_array($value, $schema['enum'], true)) {
            throw new QUI\Exception($path . ' has an unsupported value.', 400);
        }

        if (is_int($value) || is_float($value)) {
            if (
                (isset($schema['minimum']) && $value < $schema['minimum'])
                || (isset($schema['maximum']) && $value > $schema['maximum'])
            ) {
                throw new QUI\Exception($path . ' is outside the allowed range.', 400);
            }
        }

        if (is_string($value)) {
            $length = mb_strlen($value);

            if (
                (isset($schema['minLength']) && mb_strlen(trim($value)) < $schema['minLength'])
                || (isset($schema['maxLength']) && $length > $schema['maxLength'])
                || (isset($schema['format']) && filter_var($value, FILTER_VALIDATE_EMAIL) === false)
                || str_contains($value, "\0")
            ) {
                throw new QUI\Exception($path . ' contains invalid text.', 400);
            }
        }

        if (!is_array($value)) {
            return;
        }

        if (in_array('object', $types, true)) {
            if (count($value) < ($schema['minProperties'] ?? 0)) {
                throw new QUI\Exception($path . ' must contain at least one field.', 400);
            }

            foreach ($schema['required'] ?? [] as $required) {
                if (!array_key_exists($required, $value)) {
                    throw new QUI\Exception($path . ' is missing a required field.', 400);
                }
            }

            foreach ($value as $field => $entry) {
                if (!is_string($field) || !isset($schema['properties'][$field])) {
                    throw new QUI\Exception($path . ' contains an unknown field.', 400);
                }

                self::validate($entry, $schema['properties'][$field], $path . '.' . $field);
            }

            return;
        }

        if (count($value) > ($schema['maxItems'] ?? PHP_INT_MAX)) {
            throw new QUI\Exception($path . ' contains too many entries.', 400);
        }

        foreach ($value as $index => $entry) {
            self::validate($entry, $schema['items'], $path . '.' . $index);
        }
    }

    /** @param array<string, mixed> $data */
    public static function validateMasterData(array $data): void
    {
        if (isset($data['language']) && !in_array($data['language'], QUI\Translator::getAvailableLanguages() ?? [], true)) {
            throw new QUI\Exception('Select an available language from quiqqer_customer_options.', 400);
        }

        if (isset($data['website']) && $data['website'] !== '') {
            $website = $data['website'];
            $url = str_contains($website, '://') ? $website : 'https://' . $website;
            $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));

            if (
                !in_array($scheme, ['http', 'https'], true)
                || filter_var($url, FILTER_VALIDATE_URL) === false
                || parse_url($url, PHP_URL_USER) !== null
                || parse_url($url, PHP_URL_PASS) !== null
            ) {
                throw new QUI\Exception('Website must be an HTTP(S) URL or hostname without credentials.', 400);
            }
        }

        foreach (['address', 'deliveryAddress', 'contactPerson'] as $field) {
            $country = $data[$field]['country'] ?? '';

            if ($country !== '' && !QUI\Countries\Manager::existsCountryCode($country)) {
                throw new QUI\Exception('Select a valid country code from quiqqer_customer_options.', 400);
            }
        }

        SelectionOptions::validateSelections($data);
    }
}
