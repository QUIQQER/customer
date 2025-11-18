<?php

namespace QUI\ERP\Customer;

use QUI;
use QUI\BackendSearch\ProviderInterface;

use function implode;
use function in_array;
use function json_encode;

/**
 * Class BackendSearchProvider
 *
 * Serves customer search result to the QUIQQER backend search.
 */
class BackendSearchProvider implements ProviderInterface
{
    const TYPE = 'customers';

    /**
     * @inheritdoc
     */
    public function buildCache(): void
    {
        // customers are searched live
    }

    /**
     * @param string|int $id
     * @inheritdoc
     */
    public function getEntry(string | int $id): array
    {
        return [
            'searchdata' => json_encode([
                'require' => 'package/quiqqer/customer/bin/backend/controls/customer/Panel',
                'params' => [
                    'userId' => $id
                ]
            ])
        ];
    }

    /**
     * Execute a search
     *
     * @param string $search
     * @param array $params
     * @return array
     */
    public function search(string $search, array $params = []): array
    {
        if (
            isset($params['filterGroups'])
            && !in_array(self::TYPE, $params['filterGroups'])
        ) {
            return [];
        }

        $result = [];
        $Search = Search::getInstance();

        try {
            $Search->setFilter('search', $search);
            $results = $Search->search();
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return [];
        }

        $groupLabel = QUI::getLocale()->get(
            'quiqqer/customer',
            'backendSearch.group.customers.label'
        );

        $NumberRange = new NumberRange();
        $prefix = $NumberRange->getCustomerNoPrefix();

        foreach ($results as $row) {
            try {
                $userId = $row['user_id'];
                $customerId = !empty($row['customerId']) ? $prefix . $row['customerId'] : $userId;

                if (!empty($row['company'])) {
                    $name = $row['company'];
                } elseif (!empty($row['firstname']) || !empty($row['lastname'])) {
                    $nameParts = [];

                    if (!empty($row['firstname'])) {
                        $nameParts[] = $row['firstname'];
                    }

                    if (!empty($row['lastname'])) {
                        $nameParts[] = $row['lastname'];
                    }

                    $name = implode(' ', $nameParts);
                } else {
                    $name = QUI::getLocale()->get(
                        'quiqqer/customer',
                        'backendSearch.fallback_label.customer'
                    );
                }

                $name .= ' (' . $customerId . ')';

                $result[] = [
                    'id' => $userId,
                    'title' => $name,
                    'description' => '',
                    'icon' => 'fa fa-user-o',
                    'group' => self::TYPE,
                    'groupLabel' => $groupLabel
                ];
            } catch (\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }

        return $result;
    }

    /**
     * Get all available search groups of this provider.
     * Search results can be filtered by these search groups.
     *
     * @return array
     */
    public function getFilterGroups(): array
    {
        return [
            [
                'group' => self::TYPE,
                'label' => [
                    'quiqqer/customer',
                    'backendSearch.group.customers.label'
                ]
            ]
        ];
    }
}
