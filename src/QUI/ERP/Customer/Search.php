<?php

/**
 * This file contains QUI\ERP\Customer\Search
 */

namespace QUI\ERP\Customer;

use IntlDateFormatter;
use PDO;
use QUI;
use QUI\Utils\Singleton;

use function array_flip;
use function array_map;
use function array_walk;
use function count;
use function explode;
use function implode;
use function in_array;
use function mb_strlen;
use function mb_strtoupper;
use function mb_substr;
use function sort;
use function str_replace;
use function strtotime;
use function trim;

/**
 * Class Search
 *
 * @package QUI\ERP\Customer
 */
class Search extends Singleton
{
    /**
     * @var string
     */
    protected string $order = 'user_id DESC';

    /**
     * @var array
     */
    protected array $filter = [];

    /**
     * search value
     *
     * @var string
     */
    protected string $search = '';

    /**
     * search flag for: search only in customer
     *
     * @var bool
     */
    protected bool $onlyCustomer = true;

    /**
     * @var array
     */
    protected array $limit = [0, 20];

    /**
     * Return the db table
     *
     * @return string
     */
    public function table(): string
    {
        return QUI::getDBTableName('users');
    }

    /**
     * Return the db table for the addresses
     *
     * @return string
     */
    public function tableAddress(): string
    {
        return QUI::getDBTableName('users_address');
    }

    /**
     * @return array
     */
    public function getAllowedFields(): array
    {
        return [
            'id',
            'uuid',
            'email',
            'username',
            'usergroup',
            'usertitle',
            'firstname',
            'lastname',
            'lang',
            'company',
            'birthday',
            'active',
            'deleted',
            'su',
            'customerId',

            'regdate',
            'lastvisit',
            'lastedit',
            'expire'
        ];
    }

    /**
     * Execute the search and return the invoice list
     *
     * @return array
     *
     * @throws QUI\Exception
     */
    public function search(): array
    {
        return $this->executeQueryParams($this->getQuery());
    }

    /**
     * @throws QUI\Exception
     */
    public function searchForGrid(): array
    {
        // select display invoices
        $users = $this->executeQueryParams($this->getQuery());

        // count
        $count = $this->executeQueryParams($this->getQueryCount());
        $count = (int)$count[0]['count'];

        // result
        $result = $this->parseListForGrid($users);
        $Grid = new QUI\Utils\Grid();
        $Grid->setAttribute('page', ($this->limit[0] / $this->limit[1]) + 1);

        return $Grid->parseResult($result, $count);
    }

    /**
     * @param $data
     * @return array
     */
    protected function parseListForGrid($data): array
    {
        $localeCode = QUI::getLocale()->getLocalesByLang(
            QUI::getLocale()->getCurrent()
        );

        $DateFormatterLong = new IntlDateFormatter(
            $localeCode[0],
            IntlDateFormatter::SHORT,
            IntlDateFormatter::SHORT
        );

        $NumberRange = new NumberRange();
        $prefix = $NumberRange->getCustomerNoPrefix();

        $result = [];
        $Groups = QUI::getGroups();
        $Users = QUI::getUsers();

        foreach ($data as $entry) {
            $entry['usergroup'] = trim($entry['usergroup'], ',');
            $entry['usergroup'] = explode(',', $entry['usergroup']);

            $groups = array_map(function ($groupId) use ($Groups) {
                try {
                    $Group = $Groups->get($groupId);

                    return $Group->getName();
                } catch (QUI\Exception) {
                }

                return '';
            }, $entry['usergroup']);

            sort($groups);
            $groups = implode(', ', $groups);
            $groups = str_replace(',,', '', $groups);
            $groups = trim($groups, ',');

            if (!isset($entry['customerId'])) {
                $entry['customerId'] = '';
            }

            if (empty($entry['customerId'])) {
                $entry['customerId'] = $entry['user_id'];
            } else {
                $entry['customerId'] = $prefix . $entry['customerId'];
            }

            $addressData = [];
            $Address = null;
            $uuid = '';

            if (!empty($entry['user_uuid'])) {
                $entry['uuid'] = $entry['user_uuid'];
                $entry['user_id'] = $entry['user_uuid'];
            }

            if (!empty($entry['uuid'])) {
                $entry['user_id'] = $entry['uuid'];
            }

            if (empty($entry['user_id']) && !empty($entry['id'])) {
                $entry['user_id'] = $entry['id'];
            }

            if (empty($entry['user_id'])) {
                continue;
            }

            try {
                $User = $Users->get($entry['user_id']);
                $uuid = $User->getUUID();
                $Address = $User->getStandardAddress();
            } catch (QUI\Exception) {
            }

            if ($Address && (empty($entry['firstname']) || empty($entry['lastname']))) {
                $name = [];

                if ($Address->getAttribute('firstname')) {
                    $entry['firstname'] = $Address->getAttribute('firstname');
                    $name[] = $Address->getAttribute('firstname');
                }

                if ($Address->getAttribute('lastname')) {
                    $entry['lastname'] = $Address->getAttribute('lastname');
                    $name[] = $Address->getAttribute('lastname');
                }

                if (!empty($name)) {
                    $addressData[] = implode(' ', $name);
                }
            }

            if ($Address) {
                $addressData[] = $Address->getText();

                if (empty($entry['email'])) {
                    $mails = $Address->getMailList();

                    if (count($mails)) {
                        $entry['email'] = $mails[0];
                    }
                }

                if (empty($entry['company'])) {
                    $entry['company'] = $Address->getAttribute('company');
                }
            }

            if (empty($entry['firstname'])) {
                $entry['firstname'] = $entry['user_firstname'];
            }

            if (empty($entry['lastname'])) {
                $entry['lastname'] = $entry['user_lastname'];
            }

            if (empty($entry['email'])) {
                $entry['email'] = $entry['user_email'];
            }

            $result[] = [
                'id' => (int)$entry['id'],
                'customerId' => $entry['customerId'],
                'status' => !!$entry['active'],
                'user_id' => $entry['user_id'],
                'user_uuid' => $uuid,
                'username' => $entry['username'],
                'firstname' => $entry['firstname'],
                'lastname' => $entry['lastname'],
                'company' => $entry['company'],
                'email' => $entry['email'],
                'regdate' => $DateFormatterLong->format($entry['regdate']),

                'usergroup_display' => $groups,
                'usergroup' => $entry['usergroup'],
                'address_display' => implode(' - ', $addressData)
            ];
        }

        return $result;
    }

    //region query stuff

    /**
     * @return array
     */
    protected function getQueryCount(): array
    {
        return $this->getQuery(true);
    }

    /**
     * @param bool $count - Use count select, or not
     * @return array
     */
    protected function getQuery(bool $count = false): array
    {
        $table = $this->table();
        $order = $this->order;

        // limit
        $limit = '';

        if ($this->limit && isset($this->limit[0]) && isset($this->limit[1])) {
            $start = $this->limit[0];
            $end = $this->limit[1];
            $limit = " LIMIT $start,$end";
        }


        // filter checks
        $filterList = [
            'userId',
            'username',
            'firstname',
            'lastname',
            'email',
            'usergroup',
            'company',
            'customerId',
            'ad.firstname',
            'ad.lastname'
        ];

        $searchFilters = [];

        $filterReset = function () use ($filterList) {
            foreach ($filterList as $filter) {
                if (isset($this->filter[$filter]) && $this->filter[$filter]) {
                    return false;
                }
            }

            return true;
        };

        if ($filterReset()) {
            foreach ($filterList as $filter) {
                if (isset($this->filter[$filter])) {
                    unset($this->filter[$filter]);
                }
            }

            $searchFilters = $filterList;
        } else {
            foreach ($filterList as $filter) {
                if (isset($this->filter[$filter]) && $this->filter[$filter]) {
                    $searchFilters[] = $filter;
                }
            }
        }


        // create query
        if (empty($this->filter) && empty($this->search)) {
            $where = '';

            if ($this->onlyCustomer) {
                try {
                    $customerGroup = Customers::getInstance()->getCustomerGroupId();
                    $where = " WHERE users.usergroup LIKE '%,$customerGroup,%'";
                } catch (QUI\Exception $Exception) {
                    QUI\System\Log::addError($Exception->getMessage());
                }
            }

            if ($count) {
                return [
                    'query' => " SELECT COUNT(users.id) AS count FROM $table as users $where",
                    'binds' => []
                ];
            }

            return [
                'query' => "
                    SELECT *
                    FROM $table as users
                    {$where}
                    ORDER BY {$order}
                    {$limit}
                ",
                'binds' => []
            ];
        }

        $where = [];
        $binds = [];

        // filter
        $fc = 0;

        if ($this->onlyCustomer) {
            try {
                $customerGroup = Customers::getInstance()->getCustomerGroupId();
                $where[] = 'users.usergroup LIKE :customerGroupId';

                $binds['customerGroupId'] = [
                    'value' => '%,' . $customerGroup . ',%',
                    'type' => PDO::PARAM_STR
                ];
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::addError($Exception->getMessage());
            }
        }


        // create filter mysql
        foreach ($this->filter as $filter => $value) {
            if (empty($value)) {
                continue;
            }

            $bind = 'filter' . $fc;

            if ($filter === 'regdate_from') {
                $where[] = 'users.regdate >= :' . $bind;

                $binds[$bind] = [
                    'value' => (int)strtotime($value),
                    'type' => PDO::PARAM_INT
                ];
                continue;
            }

            if ($filter === 'regdate_to') {
                $where[] = 'users.regdate <= :' . $bind;

                $binds[$bind] = [
                    'value' => (int)strtotime($value),
                    'type' => PDO::PARAM_INT
                ];
                continue;
            }

            switch ($filter) {
                case 'lastvisit_from':
                    $where[] = 'users.lastvisit >= :' . $bind;
                    break;

                case 'lastvisit_to':
                    $where[] = 'vlastvisit <= :' . $bind;
                    break;

                case 'lastedit_from':
                    $where[] = 'users.lastedit >= :' . $bind;
                    break;

                case 'lastedit_to':
                    $where[] = 'users.lastedit <= :' . $bind;
                    break;

                case 'expire_from':
                    $where[] = 'users.expire >= :' . $bind;
                    break;

                case 'expire_to':
                    $where[] = 'users.expire <= :' . $bind;
                    break;

                default:
                    continue 2;
            }

            $binds[$bind] = [
                'value' => $value,
                'type' => PDO::PARAM_STR
            ];

            $fc++;
        }

        $NumberRange = new NumberRange();
        $prefixLength = mb_strlen($NumberRange->getCustomerNoPrefix());

        if (!empty($this->search)) {
            $searchWhere = [];

            // Prepare searched columns
            array_walk($searchFilters, function (&$filter) {
                switch ($filter) {
                    case 'userId':
                        $filter = 'users.id';
                        break;

                    case 'group':
                        $filter = 'users.usergroup';
                        break;

                    case 'company':
                        $filter = 'ad.company';
                        break;

                    default:
                        if (!str_contains($filter, 'users.') && !str_contains($filter, 'ad.')) {
                            $filter = 'users.' . $filter;
                        }
                }
            });

            foreach ($searchFilters as $column) {
                if ($column === 'users.customerId') {
                    $searchWhere[] = $column . ' LIKE :customer_id_no_prefix';
                    $customerIdNoPrefix = mb_substr($this->search, $prefixLength);

                    $binds['customer_id_no_prefix'] = [
                        'value' => '%' . $customerIdNoPrefix . '%',
                        'type' => PDO::PARAM_STR
                    ];
                }

                $searchWhere[] = $column . ' LIKE :search';
            }

            $binds['search'] = [
                'value' => '%' . $this->search . '%',
                'type' => PDO::PARAM_STR
            ];

            // Split search
            $searchTermsSplit = explode(" ", $this->search);

            if (count($searchTermsSplit) > 1) {
                $searchWhereSplit = [];

                foreach ($searchTermsSplit as $k => $searchTerm) {
                    $searchWhereSplitTerm = [];

                    foreach ($searchFilters as $column) {
                        $searchWhereSplitTerm[] = $column . ' LIKE :searchSplit' . $k;
                    }

                    $searchWhereSplit[] = '(' . implode(' OR ', $searchWhereSplitTerm) . ')';
                }

                $searchWhere[] = '(' . implode(' AND ', $searchWhereSplit) . ')';

                foreach ($searchTermsSplit as $k => $searchTerm) {
                    $binds['searchSplit' . $k] = [
                        'value' => '%' . $searchTerm . '%',
                        'type' => PDO::PARAM_STR
                    ];
                }
            }

            $where[] = '(' . implode(' OR ', $searchWhere) . ')';
        }

        $whereQuery = 'WHERE ' . implode(' AND ', $where);

        if (!count($where)) {
            $whereQuery = '';
        }

        if ($count) {
            return [
                "query" => "
                    SELECT COUNT(search_query.`user_id`) AS count
                    FROM (
                        SELECT users.`id` as user_id,
                        users.`firstname` as user_firstname,
                        users.`lastname` as user_lastname,
                        users.`email` as user_email
                        FROM $table as users
                             LEFT JOIN users_address AS ad ON users.id = ad.uid 
                             AND users.address = ad.uuid
                        {$whereQuery}
                    ) as search_query
                ",
                'binds' => $binds
            ];
        }

        return [
            "query" => "
                SELECT 
                    users.`id` as user_id,
                    users.`firstname` as user_firstname,
                    users.`lastname` as user_lastname,
                    users.`email` as user_email,
                    users.`uuid` as user_uuid,
                    users.*, 
                    ad.*
                FROM $table as users
                     LEFT JOIN users_address AS ad ON users.id = ad.uid 
                     AND users.address = ad.uuid
                {$whereQuery}
                ORDER BY {$order}
                {$limit}
            ",
            'binds' => $binds
        ];
    }

    /**
     * @param array $queryData
     * @return array
     * @throws QUI\Exception
     */
    protected function executeQueryParams(array $queryData = []): array
    {
        $PDO = QUI::getDataBase()->getPDO();
        $binds = $queryData['binds'];
        $query = $queryData['query'];

        $Statement = $PDO->prepare($query);

        foreach ($binds as $var => $bind) {
            $Statement->bindValue($var, $bind['value'], $bind['type']);
        }

        try {
            $Statement->execute();

            return $Statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            QUI\System\Log::writeRecursive($query);
            QUI\System\Log::writeRecursive($binds);
            throw new QUI\Exception('Something went wrong');
        }
    }

    /**
     * Set the order
     *
     * @param string $col - Order column
     * @param string $direction (optional) - Order direction [default: ASC]
     * @return void
     */
    public function order(string $col, string $direction = 'ASC'): void
    {
        if (!in_array($col, $this->getAllowedFields())) {
            return;
        }

        $direction = mb_strtoupper($direction);

        if ($direction !== 'ASC') {
            $direction = 'DESC';
        }

        switch ($col) {
            case 'id':
                $this->order = 'user_id ' . $direction;
                break;

            case 'email':
            case 'username':
            case 'usergroup':
            case 'usertitle':
            case 'lang':
            case 'birthday':
            case 'active':
            case 'deleted':
            case 'su':
            case 'customerId':
                $this->order = 'users.`' . $col . '` ' . $direction;
                break;

            case 'firstname':
            case 'lastname':
                $this->order = 'users.`' . $col . '` ' . $direction . ', ad.`' . $col . '` ' . $direction;
                break;

            case 'company':
                $this->order = 'ad.`' . $col . '` ' . $direction;
                break;
        }
    }

    /**
     * Set the limit
     *
     * @param integer|string $from - start
     * @param integer|string $count - count of entries
     */
    public function limit(int|string $from, int|string $count): void
    {
        $this->limit = [(int)$from, (int)$count];
    }

    //endregion

    //region filter

    /**
     * Set a filter
     *
     * @param string $filter
     * @param array|string $value
     */
    public function setFilter(string $filter, array|string $value): void
    {
        if ($filter === 'search') {
            $this->search = $value;

            return;
        }

        if ($filter === 'userId') {
            $this->filter['userId'] = $value;

            return;
        }

        if ($filter === 'group') {
            $this->filter['usergroup'] = $value;

            return;
        }

        if ($filter === 'regdate_from' || $filter === 'regdate_to') {
            $this->filter[$filter] = $value;

            return;
        }

        $keys = array_flip($this->getAllowedFields());

        if (isset($keys[$filter])) {
            $this->filter[$filter] = $value;
        }
    }

    /**
     * Clear all filters
     */
    public function clearFilter(): void
    {
        $this->filter = [];
    }

    /**
     * set the flag for searching only in the customer group
     */
    public function searchOnlyInCustomer(): void
    {
        $this->onlyCustomer = true;
    }

    /**
     * set the flag for searching in all groups
     */
    public function searchInAllGroups(): void
    {
        $this->onlyCustomer = false;
    }

    //endregion
}
