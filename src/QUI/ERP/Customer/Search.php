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
        $Users  = QUI::getUsers();

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

            if (empty($entry['customerId'])) {
                $entry['customerId'] = $entry['user_id'];
            }

            $addressData = [];
            $Address     = null;

            try {
                $User    = $Users->get((int)$entry['id']);
                $Address = $User->getStandardAddress();
            } catch (QUI\Exception $Exception) {
            }

            if ($Address && (empty($entry['firstname']) || empty($entry['lastname']))) {
                $name = [];

                if ($Address->getAttribute('firstname')) {
                    $entry['firstname'] = $Address->getAttribute('firstname');
                    $name[]             = $Address->getAttribute('firstname');
                }

                if ($Address->getAttribute('lastname')) {
                    $entry['lastname'] = $Address->getAttribute('lastname');
                    $name[]            = $Address->getAttribute('lastname');
                }

                if (!empty($name)) {
                    $addressData[] = \implode(' ', $name);
                }
            }

            if ($Address) {
                $addressData[] = $Address->getText();

                if (empty($entry['email'])) {
                    $mails = $Address->getMailList();

                    if (\count($mails)) {
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
                'id'         => (int)$entry['id'],
                'customerId' => $entry['customerId'],
                'status'     => !!$entry['active'],
                'user_id'    => (int)$entry['user_id'],
                'username'   => $entry['username'],
                'firstname'  => $entry['firstname'],
                'lastname'   => $entry['lastname'],
                'company'    => $entry['company'],
                'email'      => $entry['email'],
                'regdate'    => $DateFormatterLong->format($entry['regdate']),

                'usergroup_display' => $groups,
                'usergroup'         => $entry['usergroup'],
                'address_display'   => \implode(' - ', $addressData)
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
        $order = 'users.'.$this->order;

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
            'usergroup',
            'company',
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
                    $where         = " WHERE users.usergroup LIKE '%,{$customerGroup},%'";
                } catch (QUI\Exception $Exception) {
                    QUI\System\Log::addError($Exception->getMessage());
                }
            }

            if ($count) {
                return [
                    'query' => " SELECT COUNT(users.id) AS count FROM {$table} as users {$where}",
                    'binds' => []
                ];
            }

            return [
                'query' => "
                    SELECT *
                    FROM {$table} as users
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
                $where[]       = 'users.usergroup LIKE :customerId';

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
                $where[] = 'users.regdate >= :'.$bind;

                $binds[$bind] = [
                    'value' => (int)\strtotime($value),
                    'type'  => \PDO::PARAM_INT
                ];
                continue;
            }

            if ($filter === 'regdate_to') {
                $where[] = 'users.regdate <= :'.$bind;

                $binds[$bind] = [
                    'value' => (int)\strtotime($value),
                    'type'  => \PDO::PARAM_INT
                ];
                continue;
            }

            switch ($filter) {
                case 'lastvisit_from':
                    $where[] = 'users.lastvisit >= :'.$bind;
                    break;

                case 'lastvisit_to':
                    $where[] = 'vlastvisit <= :'.$bind;
                    break;

                case 'lastedit_from':
                    $where[] = 'users.lastedit >= :'.$bind;
                    break;

                case 'lastedit_to':
                    $where[] = 'users.lastedit <= :'.$bind;
                    break;

                case 'expire_from':
                    $where[] = 'users.expire >= :'.$bind;
                    break;

                case 'expire_to':
                    $where[] = 'users.expire <= :'.$bind;
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
                    $filter = 'users.id';
                }

                if ($filter === 'group') {
                    $filter = 'users.usergroup';
                }

                if ($filter === 'company') {
                    $filter = 'ad.company';
                }

                if (\strpos($filter, 'users.') === false && \strpos($filter, 'ad.') === false) {
                    $filter = 'users.'.$filter;
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
                    SELECT COUNT(users.id) AS count
                    FROM {$table} as users 
                        LEFT JOIN users_address AS ad ON users.id = ad.uid
                        AND users.address = ad.id
                    {$whereQuery}
                ",
                'binds' => $binds
            ];
        }

        return [
            "query" => "
                SELECT users.`id` as user_id,
                users.`firstname` as user_firstname,
                users.`lastname` as user_lastname,
                users.`email` as user_email,
                users.*, ad.*
                FROM {$table} as users
                     LEFT JOIN users_address AS ad ON users.id = ad.uid 
                     AND users.address = ad.id
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
