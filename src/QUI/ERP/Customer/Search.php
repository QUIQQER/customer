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

        foreach ($data as $entry) {
            $result[] = [
                'id'        => (int)$entry['id'],
                'status'    => !!$entry['active'],
                'username'  => $entry['username'],
                'firstname' => $entry['firstname'],
                'lastname'  => $entry['lastname'],
                'email'     => $entry['email'],
                'usergroup' => $entry['usergroup'],
                'regdate'   => $DateFormatterLong->format($entry['regdate'])
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


        if (empty($this->filter) && empty($this->search)) {
            if ($count) {
                return [
                    'query' => " SELECT COUNT(id) AS count FROM {$table}",
                    'binds' => []
                ];
            }

            return [
                'query' => "
                    SELECT *
                    FROM {$table}
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

        foreach ($this->filter as $filter) {
            $bind = ':filter'.$fc;
            $flr  = $filter['filter'];

            switch ($flr) {
                case 'regdate_from':
                    $where[] = 'regdate >= '.$bind;
                    break;

                case 'regdate_to':
                    $where[] = 'regdate <= '.$bind;
                    break;

                case 'lastvisit_from':
                    $where[] = 'lastvisit >= '.$bind;
                    break;

                case 'lastvisit_to':
                    $where[] = 'lastvisit <= '.$bind;
                    break;

                case 'lastedit_from':
                    $where[] = 'lastedit >= '.$bind;
                    break;

                case 'lastedit_to':
                    $where[] = 'lastedit <= '.$bind;
                    break;

                case 'expire_from':
                    $where[] = 'expire >= '.$bind;
                    break;

                case 'expire_to':
                    $where[] = 'expire <= '.$bind;
                    break;
            }

            $binds[$bind] = [
                'value' => $filter['value'],
                'type'  => \PDO::PARAM_STR
            ];

            $fc++;
        }

        if (!empty($this->search)) {
            $where[] = '(
                id LIKE :search OR
                username LIKE :search OR
                email LIKE :search OR
                firstname LIKE :search OR
                lastname LIKE :search OR
                company LIKE :search
            )';

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
                    SELECT COUNT(*) AS count
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

        $keys = \array_flip($this->getAllowedFields());

        if (!\is_array($value)) {
            $value = [$value];
        }
    }

    /**
     * Clear all filters
     */
    public function clearFilter()
    {
        $this->filter = [];
    }

    //endregion
}
