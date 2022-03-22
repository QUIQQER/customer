<?php

namespace QUI\ERP\Customer;

use QUI;
use QUI\BackendSearch\ProviderInterface;

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
    public function buildCache()
    {
        // customers are searched live
    }

    /**
     * @param int $id
     * @inheritdoc
     */
    public function getEntry($id)
    {
        return [
            'searchdata' => \json_encode([
                'require' => 'package/quiqqer/customer/bin/backend/controls/customer/Panel',
                'params'  => [
                    'userId' => (int)$id
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
    public function search($search, $params = [])
    {
        if (isset($params['filterGroups'])
            && !\in_array(self::TYPE, $params['filterGroups'])
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
        $prefix      = $NumberRange->getCustomerNoPrefix();

        foreach ($results as $row) {
            try {
                $userId     = $row['user_id'];
                $customerId = !empty($row['customerId']) ? $prefix.$row['customerId'] : $userId;

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

                    $name = \implode(' ', $nameParts);
                } else {
                    $name = QUI::getLocale()->get(
                        'quiqqer/customer',
                        'backendSearch.fallback_label.customer'
                    );
                }

                $name .= ' ('.$customerId.')';

                $result[] = [
                    'id'          => (int)$userId,
                    'title'       => $name,
                    'description' => '',
                    'icon'        => 'fa fa-user-o',
                    'group'       => self::TYPE,
                    'groupLabel'  => $groupLabel
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
    public function getFilterGroups()
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
