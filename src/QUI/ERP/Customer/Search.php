<?php

/**
 * This file contains QUI\ERP\Customer\Search
 */

namespace QUI\ERP\Customer;

use QUI;
use QUI\Utils\Singleton;

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
    protected $order = 'id DESC';

    /**
     * @var array
     */
    protected $filter = [];

    /**
     * search value
     *
     * @var null
     */
    protected $search = null;

    /**
     * search flag for: search only in customer
     *
     * @var bool
     */
    protected $onlyCustomer = true;

    /**
     * @var array
     */
    protected $limit = [0, 20];

    /**
     * Return the db table
     *
     * @return string
     */
    public function table()
    {
        return QUI::getDBTableName('users');
    }

    /**
     * Return the db table for the addresses
     *
     * @return string
     */
    public function tableAddress()
    {
        return QUI::getDBTableName('users_address');
    }

    /**
     * @return array
     */
    public function getAllowedFields()
    {
        return [
            'id',
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
    public function search()
    {
        return $this->executeQueryParams($this->getQuery());
    }

    /**
     * @throws QUI\Exception
     */
    public function searchForGrid()
    {
        // select display invoices
        $users = $this->executeQueryParams($this->getQuery());

        // count
        $count = $this->executeQueryParams($this->getQueryCount());
        $count = (int)$count[0]['count'];

        // result
        $result = $this->parseListForGrid($users);
        $Grid   = new QUI\Utils\Grid();
        $Grid->setAttribute('page', ($this->limit[0] / $this->limit[1]) + 1);

        return $Grid->parseResult($result, $count);
    }

    /**
     * @param $data
     * @return array
     */
    protected function parseListForGrid($data)
    {
        $localeCode = QUI::getLocale()->getLocalesByLang(
            QUI::getLocale()->getCurrent()
        );

        $DateFormatter = new \IntlDateFormatter(
            $localeCode[0],
            \IntlDateFormatter::SHORT,
            \IntlDateFormatter::NONE
        );

        $DateFormatterLong = new \IntlDateFormatter(
            $localeCode[0],
            \IntlDateFormatter::SHORT,
            \IntlDateFormatter::SHORT
        );

        $result = [];
        $Groups = QUI::getGroups();

        foreach ($data as $entry) {
            $entry['usergroup'] = \trim($entry['usergroup'], ',');
            $entry['usergroup'] = \explode(',', $entry['usergroup']);
            $entry['usergroup'] = \array_map(function ($groupId) {
                return (int)$groupId;
            }, $entry['usergroup']);

            $groups = \array_map(function ($groupId) use ($Groups) {
                try {
                    $Group = $Groups->get($groupId);

                    return $Group->getName();
                } catch (QUI\Exception $Exception) {
                }

                return '';
            }, $entry['usergroup']);

            \sort($groups);
            $groups = \implode(', ', $groups);
            $groups = \str_replace(',,', '', $groups);
            $groups = \trim($groups, ',');

            if (!isset($entry['customerId'])) {
                $entry['customerId'] = '';
            }

            $result[] = [
                'id'         => (int)$entry['id'],
                'customerId' => $entry['customerId'],
                'status'     => !!$entry['active'],
                'username'   => $entry['username'],
                'firstname'  => $entry['firstname'],
                'lastname'   => $entry['lastname'],
                'email'      => $entry['email'],
                'regdate'    => $DateFormatterLong->format($entry['regdate']),

                'usergroup_display' => $groups,
                'usergroup'         => $entry['usergroup']
            ];
        }

        return $result;
    }

    //region query stuff

    /**
     * @return array
     */
    protected function getQueryCount()
    {
        return $this->getQuery(true);
    }

    /**
     * @param bool $count - Use count select, or not
     * @return array
     */
    protected function getQuery($count = false)
    {
        $table = $this->table();
        $order = $this->order;

        // limit
        $limit = '';

        if ($this->limit && isset($this->limit[0]) && isset($this->limit[1])) {
            $start = $this->limit[0];
            $end   = $this->limit[1];
            $limit = " LIMIT {$start},{$end}";
        }


        // filter checks
        $filterList = [
            'userId',
            'username',
            'firstname',
            'lastname',
            'email',
            'usergroup'
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
                    $where         = " WHERE usergroup LIKE '%,{$customerGroup},%'";
                } catch (QUI\Exception $Exception) {
                    QUI\System\Log::addError($Exception->getMessage());
                }
            }

            if ($count) {
                return [
                    'query' => " SELECT COUNT(id) AS count FROM {$table} {$where}",
                    'binds' => []
                ];
            }

            return [
                'query' => "
                    SELECT *
                    FROM {$table}
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
                $where[]       = 'usergroup LIKE :customerId';

                $binds['customerId'] = [
                    'value' => '%,'.$customerGroup.',%',
                    'type'  => \PDO::PARAM_STR
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

            $bind = 'filter'.$fc;

            if ($filter === 'regdate_from') {
                $where[] = 'regdate >= :'.$bind;

                $binds[$bind] = [
                    'value' => (int)\strtotime($value),
                    'type'  => \PDO::PARAM_INT
                ];
                continue;
            }

            if ($filter === 'regdate_to') {
                $where[] = 'regdate <= :'.$bind;

                $binds[$bind] = [
                    'value' => (int)\strtotime($value),
                    'type'  => \PDO::PARAM_INT
                ];
                continue;
            }

            switch ($filter) {
                case 'lastvisit_from':
                    $where[] = 'lastvisit >= :'.$bind;
                    break;

                case 'lastvisit_to':
                    $where[] = 'lastvisit <= :'.$bind;
                    break;

                case 'lastedit_from':
                    $where[] = 'lastedit >= :'.$bind;
                    break;

                case 'lastedit_to':
                    $where[] = 'lastedit <= :'.$bind;
                    break;

                case 'expire_from':
                    $where[] = 'expire >= :'.$bind;
                    break;

                case 'expire_to':
                    $where[] = 'expire <= :'.$bind;
                    break;

                default:
                    continue 2;
            }

            $binds[$bind] = [
                'value' => $value,
                'type'  => \PDO::PARAM_STR
            ];

            $fc++;
        }


        if (!empty($this->search)) {
            $searchWhere = [];

            foreach ($searchFilters as $filter) {
                if ($filter === 'userId') {
                    $filter = 'id';
                }

                if ($filter === 'group') {
                    $filter = 'usergroup';
                }

                $searchWhere[] = $filter.' LIKE :search';
            }

            $where[] = '('.\implode(' OR ', $searchWhere).')';

            $binds['search'] = [
                'value' => '%'.$this->search.'%',
                'type'  => \PDO::PARAM_STR
            ];
        }

        $whereQuery = 'WHERE '.\implode(' AND ', $where);

        if (!\count($where)) {
            $whereQuery = '';
        }

        if ($count) {
            return [
                "query" => "
                    SELECT COUNT(id) AS count
                    FROM {$table}
                    {$whereQuery}
                ",
                'binds' => $binds
            ];
        }

        return [
            "query" => "
                SELECT *
                FROM {$table}
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
    protected function executeQueryParams($queryData = [])
    {
        $PDO   = QUI::getDataBase()->getPDO();
        $binds = $queryData['binds'];
        $query = $queryData['query'];

        $Statement = $PDO->prepare($query);

        foreach ($binds as $var => $bind) {
            $Statement->bindValue($var, $bind['value'], $bind['type']);
        }

        try {
            $Statement->execute();

            return $Statement->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeRecursive($Exception);
            QUI\System\Log::writeRecursive($query);
            QUI\System\Log::writeRecursive($binds);
            throw new QUI\Exception('Something went wrong');
        }
    }

    /**
     * Set the order
     *
     * @param $order
     */
    public function order($order)
    {
        $allowed = [];

        foreach ($this->getAllowedFields() as $field) {
            $allowed[] = $field;
            $allowed[] = $field.' ASC';
            $allowed[] = $field.' asc';
            $allowed[] = $field.' DESC';
            $allowed[] = $field.' desc';
        }

        $order   = \trim($order);
        $allowed = \array_flip($allowed);

        if (isset($allowed[$order])) {
            $this->order = $order;
        }
    }

    /**
     * Set the limit
     *
     * @param string|integer $from - start
     * @param string|integer $count - count of entries
     */
    public function limit($from, $count)
    {
        $this->limit = [(int)$from, (int)$count];
    }

    //endregion

    //region filter

    /**
     * Set a filter
     *
     * @param string $filter
     * @param string|array $value
     */
    public function setFilter($filter, $value)
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

        $keys = \array_flip($this->getAllowedFields());

        if (isset($keys[$filter])) {
            $this->filter[$filter] = $value;
        }
    }

    /**
     * Clear all filters
     */
    public function clearFilter()
    {
        $this->filter = [];
    }

    /**
     * set the flag for searching only in the customer group
     */
    public function searchOnlyInCustomer()
    {
        $this->onlyCustomer = true;
    }

    /**
     * set the flag for searching in all groups
     */
    public function searchInAllGroups()
    {
        $this->onlyCustomer = false;
    }

    //endregion
}
