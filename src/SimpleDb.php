<?php
namespace Mncedim;

/**
 * Created by PhpStorm.
 * User: Mncedi M (mncedim at gmail.com)
 * Date: 15/12/21
 * Time: 4:05 PM
 */

/**
 * Class SimpleDb
 * @package Mncedim
 */
class SimpleDb {

    /**
     * Host
     * @var
     */
    private $_host;

    /**
     * Username
     * @var
     */
    private $_user;

    /**
     * Password
     * @var
     */
    private $_pass;

    /**
     * DB Name
     * @var
     */
    private $_dbName;

    /**
     * DB instance
     * @var \PDO
     */
    private $_dbh;

    /**
     * @var \PDOStatement
     */
    private $_stmt;

    /**
     * Error messages
     * @var
     */
    private $_error;

    /**
     * Used by the helper methods, ignore
     * @var string
     */
    private $_helperTable;
    private $_helperSelect = '*';
    private $_helperOrWhereFlag;
    private $_helperOrderBy = array();
    private $_helperGroupBy = array();
    private $_helperHaving;
    private $_helperLimit;
    private $_helperSqlAction = 'SELECT';
    private $_helperUpdate = array();
    private $_helperIdentifiers = array();
    private $_helperWhereConditions = array('where' => array(), 'orWhere' => array());

    /**
     * @param $host
     * @param $username
     * @param $password
     * @param $dbName
     * @param $pdoOptions
     */
    public function __construct($host, $username, $password, $dbName, array $pdoOptions = array())
    {
        $this->_host    = $host;
        $this->_user    = $username;
        $this->_pass    = $password;
        $this->_dbName  = $dbName;

        $this->_pdoOptions = $pdoOptions;

        $this->_setDbh();
    }

    /**
     * Init DB
     */
    private function _setDbh()
    {
        // Set DSN
        $dsn = sprintf('mysql:host=%s;dbname=%s', $this->_host, $this->_dbName);

        // Set default options
        if (!isset($this->_pdoOptions[\PDO::ATTR_PERSISTENT])) {
            $this->_pdoOptions[\PDO::ATTR_PERSISTENT] = true;
        }

        if (!isset($this->_pdoOptions[\PDO::ATTR_ERRMODE])) {
            $this->_pdoOptions[\PDO::ATTR_ERRMODE] = \PDO::ERRMODE_EXCEPTION;
        }

        try {
            // Create a new PDO instance
            $this->_dbh = new \PDO($dsn, $this->_user, $this->_pass, $this->_pdoOptions);

        } catch(\PDOException $e) {

            $this->_error = $e->getMessage();
            throw $e;
        }
    }

    /**
     * @param $query
     * @return $this
     */
    public function query($query)
    {
        $this->_stmt = $this->_dbh->prepare($query);
        return $this;
    }

    /**
     * @param $param
     * @param $value
     * @param null $type
     * @return $this
     */
    public function bind($param, $value, $type = null)
    {
        if (is_null($type)) {

            switch (true) {
                case is_int($value):
                    $type = \PDO::PARAM_INT;
                    break;
                case is_bool($value):
                    $type = \PDO::PARAM_BOOL;
                    break;
                case is_null($value):
                    $type = \PDO::PARAM_NULL;
                    break;
                default:
                    $type = \PDO::PARAM_STR;
            }
        }

        $this->_stmt->bindValue($param, $value, $type);

        return $this;
    }

    /**
     * @return $this
     * @throws \Exception
     */
    public function execute()
    {
        if (!is_null($this->_helperTable)) {
            //build query from helper methods
            $this->_buildHelperQuery();
            $this->_resetHelperQueryVars();
        }

        if ($this->_stmt->execute()) {
            return $this;
        }

        $this->_error = implode(',', $this->_dbh->errorInfo());
        throw new \Exception($this->_error);
    }

    /**
     * @param int $format
     * @param mixed $argument
     * @return mixed
     */
    public function getResultSet($format = \PDO::FETCH_ASSOC, $argument = null)
    {
        return is_null($argument) ? $this->_stmt->fetchAll($format) : $this->_stmt->fetchAll($format, $argument);
    }

    /**
     * @param int $format
     * @return mixed
     */
    public function getSingle($format = \PDO::FETCH_ASSOC)
    {
        return $this->_stmt->fetch($format);
    }

    /**
     * @return mixed
     */
    public function rowCount()
    {
        return $this->_stmt->rowCount();
    }

    /**
     * @return mixed
     */
    public function getLastInsertId()
    {
        return $this->_dbh->lastInsertId();
    }

    /**
     * @return mixed
     */
    public function beginTransaction()
    {
        return $this->_dbh->beginTransaction();
    }

    /**
     * @return mixed
     */
    public function endTransaction()
    {
        return $this->_dbh->commit();
    }

    /**
     * @return mixed
     */
    public function cancelTransaction()
    {
        return $this->_dbh->rollBack();
    }

    /**
     * @return mixed
     */
    public function debugDumpParams()
    {
        if (!is_null($this->_helperTable)) {

            $this->_buildHelperQuery();
            $this->_resetHelperQueryVars();
        }
        return $this->_stmt->debugDumpParams();
    }

    /**
     * @return mixed
     */
    public function getConnection()
    {
        return $this->_dbh;
    }

    /**
     * Get error message
     *
     * @return string
     */
    public function getError()
    {
        return $this->_error;
    }

    /**
     * Helper method for writing your queries
     *
     * Helper methods
     * //dump($this->_app->db->table('users')->find(1));die;
     * //dump($this->_app->db->table('list_sports')->findAll());die;
     * //dump($this->_app->db->table('list_sports')->where('title', '=', 'Running')->execute()->getResultSet());die;
     * //dump($this->_app->db->table('list_sports')->where('title', '<>', 'Running')->execute()->getResultSet());die;
     * //dump($this->_app->db->table('list_sports')->where('title', 'NOT IN', array('Running', 'Rugby'))->execute()->getResultSet());die;
     * //dump($this->_app->db->table('list_sports')->where('id', 'NOT IN', array(1,2,3,4))->execute()->getResultSet());die;
     * //dump($this->_app->db->table('list_sports')->where('title', 'NOT LIKE', 'Foot')->execute()->getResultSet());die;
     * //dump($this->_app->db->table('list_sports')->where('title', 'LIKE', 'n')->andWhere('type', '=', 'Performance')->execute()->getResultSet());die;
     * //$this->_app->db->table('list_sports')->where('id', 'BETWEEN', array(1,3))->execute()->getResultSet()
     *
     * $this->_app->db->table('list_sports l')
     * //->select('l.title')
     * ->select(array('l.title', 'l.id'))
     * ->where('l.title', 'LIKE', 'n')
     * ->andWhere('l.type', '=', 'Performance')
     * ->orWhere('l.id', '=', 7)
     * ->orWhereAnd('l.title', '=', 'Other')
     * ->groupBy('title')
     * ->orderBy(array('id' => 'desc', 'title' => 'asc'))
     * ->execute()
     * ->getResultSet()
     *
     * dump(
     * $this->_app->db->table('list_sports')
     * ->select(array('type', "COUNT(title) as 'total'"))
     * ->where('id', '<>', 0)
     * ->groupBy('type')
     * ->having('COUNT(title) > 0')
     * ->execute()
     * ->getResultSet()
     * );die;
     *
     * Transactions:
     * $this->_app->db?>beginTransaction();
     * $this->_app->db?>query(...)->bind(...)->execute(); //1st query
     * ...
     * $this->_app->db?>query(...)->bind(...)->execute(); //nth query
     * $this->_app->db?>endTransaction();
     *
     *
     * @param $name - table name to query, this can also be a relation ie. 'tbl inner join tbl2 on tbl.id = tbl2.tbl_id'
     * @return $this
     */
    public function table($name)
    {
        $this->_resetHelperQueryVars();
        $this->_helperTable = $name;
        return $this;
    }

    /**
     * Specify fields to select from given table
     * $eg->select('id, name, [...]') or $eg->select(array('id', 'name', 'surname'))
     *
     * @param $columns - column name of array of column name
     *
     * @return SimpleDb
     */
    public function select($columns)
    {
        $this->_helperSelect = is_array($columns) ? implode(', ', $columns) : $columns;
        return $this;
    }

    /**
     * @return $this
     */
    public function delete()
    {
        $this->_helperSqlAction = 'DELETE';
        return $this;
    }

    /**
     * @param array $data - array('column' => 'value')
     * @param bool $ignoreOnDuplicate - ignore this insert silently if the record already exists
     * @return $this
     */
    public function insert(array $data = array(), $ignoreOnDuplicate = false)
    {
        $this->update($data); //same as update, just change the action
        $this->_helperSqlAction = 'INSERT' . ($ignoreOnDuplicate ? ' IGNORE' : '');
        return $this;
    }

    /**
     * If this record is new, it's inserted.
     * If it's a duplicate, this will replace the old one
     *
     * @param array $data - array('column' => 'value')
     * @return $this
     */
    public function replace(array $data = array())
    {
        $this->update($data); //same as update, just change the action
        $this->_helperSqlAction = 'REPLACE';
        return $this;
    }

    /**
     * @param array $data - array('column' => 'value')
     * @return $this
     */
    public function update(array $data = array())
    {
        $this->_helperSqlAction = 'UPDATE';
        if (!empty($data)) {
            $this->_helperUpdate = array_merge($this->_helperUpdate, $data);
        }
        return $this;
    }

    /**
     * @param $column
     * @param $value
     * @return $this
     */
    public function set($column, $value)
    {
        if (in_array($this->_helperSqlAction, array('UPDATE', 'INSERT', 'INSERT IGNORE', 'REPLACE'))) {
            $this->_helperUpdate[$column] = $value;
        }
        return $this;
    }

    /**
     * Find a record from the given table.
     * Helper method for writing your queries
     *
     * @param $id           - id to find
     * @param $column       - column to search in (primary_key field)
     * @param int $format   - format to return the results in
     *
     * @return mixed
     * @throws \Exception
     */
    public function find($id, $column = 'id', $format = \PDO::FETCH_ASSOC){

        return $this->where($column, '=', $id)->execute()->getSingle($format);
    }

    /**
     * Get all records in given table.
     * Helper method for writing your queries
     *
     * @param int $format
     * @param mixed $argument
     * @return mixed
     * @throws \Exception
     */
    public function findAll($format = \PDO::FETCH_ASSOC, $argument = null)
    {
        return $this->execute()->getResultSet($format, $argument);
    }

    /**
     * Find records from given table based on given column and value.
     * Helper method for writing your queries
     *
     * @param $column         - column to search in
     * @param $value          - value to find by
     * @param int $limit      - no of records to return
     * @param int $format     - format to return that results in
     * @param mixed $argument - PDO argument
     *
     * @return mixed
     * @throws \Exception
     */
    public function findBy($column, $value, $limit = -1, $format = \PDO::FETCH_ASSOC, $argument = null)
    {
        if ($limit > 0) {
            $this->limit($limit);
        }
        return $this->where($column, '=', $value)->execute()->getResultSet($format, $argument);
    }

    /**
     * Internal Helper method, ignore
     *
     * @param $column
     * @param $condition
     * @param $value
     * @param $identifier
     * @return array
     * @throws \Exception
     */
    private function _helperQueryConditions($column, $condition, $value, $identifier)
    {
        switch (strtoupper($condition)) {
            case '=':
            case '<>':
            case '>':
            case '<':
            case '>=':
            case '<=':
            case 'REGEXP':
            case 'NOT REGEXP':
            case 'RLIKE':
            case 'NOT RLIKE':
                //convert table.column to table_column as parameter name
                $query = sprintf('%s %s :%s', $column, $condition, ($identifier . str_replace('.', '_', $column)));
                break;

            case 'IN':
            case 'NOT IN':
                foreach ($value as $k => $val) {
                    $value[$k] = $this->_dbh->quote($val);
                }
                $query = sprintf('%s %s(%s)', $column, $condition, implode(",", $value));
                $value = null;
                break;

            case 'LIKE':
            case 'NOT LIKE':
                $query = sprintf("%s %s %s", $column, $condition, $this->_dbh->quote("%$value%"));
                $value = null;
                break;

            case 'BETWEEN':
            case 'NOT BETWEEN':
                $query = sprintf(
                    "%s %s %s AND %s",
                    $column,
                    $condition,
                    $this->_dbh->quote($value[0]),
                    $this->_dbh->quote($value[1])
                );
                $value = null;
                break;

            case 'IS':
            case 'IS NOT':
                if ($value === true  || strtolower($value) === 'true' || (bool)$value == true) {
                    $value = 'true';
                } else if ($value === false || strtolower($value) === 'false' || (bool)$value == false) {
                    $value = 'false';
                } else {
                    $value = 'null';
                }
                $query = sprintf('%s %s %s', $column, $condition, $value);
                $value = null;
                break;

            default:
                throw new \Exception("ooops! unknown condition: $condition");
        }

        return array('value' => $value, 'query' => $query);
    }

    /**
     * Build our final query to execute
     * Internal Helper method, ignore
     *
     * @param bool $returnQuery
     * @throws \Exception
     * @return mixed
     */
    private function _buildHelperQuery($returnQuery = false)
    {
        $binds = array(); //parameters to bind to the query
        $query = $this->_buildHelperQueryHead($binds);

        //where
        if (!empty($this->_helperWhereConditions['where'])) {

            $where = $this->_buildHelperQueryWhere('where', $this->_helperWhereConditions['where']);
            $query .= $where['conditions'];
            $binds = array_merge($binds, $where['binds']);

            //or where - cannot have OR without having a primary WHERE
            if (!empty($this->_helperWhereConditions['orWhere'])) {

                foreach ($this->_helperWhereConditions['orWhere'] as $orWhere) {

                    $where = $this->_buildHelperQueryWhere('orWhere', $orWhere);
                    $query .= $where['conditions'];
                    $binds = array_merge($binds, $where['binds']);
                }
            }
        }

        //add GROUP BY if any
        if (!empty($this->_helperGroupBy)) {

            $groupBy = '';
            foreach ($this->_helperGroupBy as $column) {
                $groupBy .= "$column, ";
            }

            if ($groupBy != '') {
                $query .= ' GROUP BY ' . rtrim($groupBy, ', ');
            }
        }

        //add HAVING if any
        if (!is_null($this->_helperHaving)) {
            $query .= " HAVING $this->_helperHaving ";
        }

        //add ORDER BY if any
        if (!empty($this->_helperOrderBy)) {

            $order = '';
            foreach ($this->_helperOrderBy as $column => $ascDesc) {

                if (!in_array(strtolower($ascDesc), array('asc', 'desc'))) {
                    throw new \Exception('Invalid order by detected: ' . $order);
                }
                $order .= "$column $ascDesc, ";
            }

            if ($order != '') {
                $query .= ' ORDER BY ' . rtrim($order, ', ');
            }
        }

        if (is_int($this->_helperLimit)) {
            $query .= " LIMIT {$this->_helperLimit} ";
        }

        //return the raw query if requested so
        if ($returnQuery) {
            return $query;
        }

        //query built and ready for action
        $this->query( $query );

        //bind all parameters we have
        foreach ($binds as $value) {
            $this->bind( ":{$value['column']}", $value['value'] );
        }
    }

    /**
     * Internal Helper method, ignore
     * @return string
     */
    private function _getQuerySectionIdentifier()
    {
        if (empty($this->_helperIdentifiers)) {

            $this->_helperIdentifiers = range('a', 'z');
        } else if (sizeof($this->_helperIdentifiers) < 3) {

            //if it runs out of letters
            $this->_helperIdentifiers = array_merge(
                $this->_helperIdentifiers,
                range(
                    (is_numeric(end($this->_helperIdentifiers)) ? (end($this->_helperIdentifiers)+1) : 0), 1000, 1
                )
            );
        }

        return array_shift($this->_helperIdentifiers) . '__';
    }

    /**
     * @param array $binds
     * @return string
     */
    private function _buildHelperQueryHead(array &$binds)
    {
        $identifier = $this->_getQuerySectionIdentifier();

        //query
        switch ($this->_helperSqlAction) {

            case 'UPDATE':
            case 'INSERT':
            case 'INSERT IGNORE':
            case 'REPLACE':
                $query = "{$this->_helperSqlAction} "
                    . ($this->_helperSqlAction != 'UPDATE' ? 'INTO' : '')
                    . " $this->_helperTable SET ";

                foreach ($this->_helperUpdate as $column => $newValue) {
                    $columnPlaceholder = $identifier . str_replace('.', '_', $column);
                    $query .= "$column = :{$columnPlaceholder}, ";
                    $binds[] = array('column' => $columnPlaceholder, 'value' => $newValue);
                }
                $query = rtrim($query, ', ') . ' ';
                break;

            case 'DELETE':
                $query = "DELETE FROM $this->_helperTable ";
                break;

            default:
                $query = "SELECT $this->_helperSelect FROM $this->_helperTable ";
                break;
        }

        return $query;
    }

    /**
     * @param $type - where/orWhere
     * @param $conditions
     * @return array
     * @throws \Exception
     */
    private function _buildHelperQueryWhere($type, $conditions)
    {
        $where = ( $type == 'where' ? 'WHERE (' : ' OR (' );
        $binds = array();
        foreach ($conditions as $c) {

            $identifier = $this->_getQuerySectionIdentifier();
            $response = $this->_helperQueryConditions( $c['column'], $c['condition'], $c['value'], $identifier );
            $where .= $response['query'] . ' AND ';

            if (!is_null($response['value'])) {

                $binds[] = array(
                    //convert table.column to table_column as parameter name
                    'column' => $identifier . str_replace('.', '_', $c['column']),
                    'value' => $response['value'] //$c['value']
                );
            }
        }

        return array(
            'conditions' => rtrim($where, 'AND ') . ')',
            'binds' => $binds
        );
    }

    /**
     * Reset helper vars
     * Internal Helper method, ignore
     *
     * @return null
     */
    private function _resetHelperQueryVars()
    {
        $this->_helperTable = null;
        $this->_helperSelect = '*';
        $this->_helperHaving = null;
        $this->_helperOrWhereFlag = null;
        $this->_helperGroupBy = array();
        $this->_helperOrderBy = array();
        $this->_helperLimit = null;
        $this->_helperSqlAction = 'SELECT';
        $this->_helperUpdate = array();
        $this->_helperIdentifiers = array();
        $this->_helperWhereConditions['where'] = array();
        $this->_helperWhereConditions['orWhere'] = array();
    }

    /**
     * Helper method for writing your queries
     *
     * @param $column
     * @param $condition [ =, >, <, <>, IN, NOT IN, LIKE, NOT LIKE ]
     * @param $value
     * @return $this
     * @throws \Exception
     */
    public function where($column, $condition, $value)
    {
        //keep a static list of all the conditions
        if (is_null($this->_helperOrWhereFlag)) {

            $this->_helperWhereConditions['where'][] = array(
                'column' => $column, 'condition' => $condition, 'value' => $value
            );
        } else if(!is_null($this->_helperOrWhereFlag)) {

            $this->_helperOrWhereFlag = null;
            $this->_helperWhereConditions['orWhere'][sizeof($this->_helperWhereConditions['orWhere'])-1][] = array(
                'column' => $column, 'condition' => $condition, 'value' => $value
            );
        }

        return $this;
    }

    /**
     * Adds another AND condition to existing WHERE conditions
     * - Call this after calling where(...)
     * Helper method for writing your queries
     *
     * @param $column
     * @param $condition [ =, >, <, <>, IN, NOT IN, LIKE, NOT LIKE ]
     * @param $value
     * @return SimpleDb
     */
    public function andWhere($column, $condition, $value)
    {
        return $this->where($column, $condition, $value);
    }

    /**
     * Adds 'OR WHERE' to existing query
     * - Call this after calling where(...)
     * Helper method for writing your queries
     *
     * @param $column
     * @param $condition [ =, >, <, <>, IN, NOT IN, LIKE, NOT LIKE ]
     * @param $value
     * @return SimpleDb
     */
    public function orWhere($column, $condition, $value)
    {
        $this->_helperOrWhereFlag = 1;

        //create an array for a new OR [...] block
        $this->_helperWhereConditions['orWhere'][] = array();
        return $this->where($column, $condition, $value);
    }

    /**
     * Adds another AND condition to existing 'OR WHERE' conditions
     * - Call this after calling where(...)
     * Helper method for writing your queries
     *
     * @param $column
     * @param $condition [ =, >, <, <>, IN, NOT IN, LIKE, NOT LIKE ]
     * @param $value
     * @return SimpleDb
     */
    public function orWhereAnd($column, $condition, $value)
    {
        $this->_helperOrWhereFlag = 1;
        return $this->where($column, $condition, $value);
    }

    /**
     * Helper method for writing your queries
     *
     * @param $columns - title / array('title', 'date')
     * @return $this
     */
    public function groupBy($columns)
    {
        if (!is_array($columns)) {
            $columns = array($columns);
        }

        $this->_helperGroupBy = $columns;
        return $this;
    }

    /**
     * Helper method for writing your queries
     *
     * @param $conditions
     * @return $this
     */
    public function having($conditions)
    {
        $this->_helperHaving = $conditions;
        return $this;
    }

    /**
     * Helper method for writing your queries
     *
     * @param array $columns - array('id' => 'asc', 'name' => 'desc')
     *
     * @return $this
     */
    public function orderBy(array $columns)
    {
        $this->_helperOrderBy = $columns;
        return $this;
    }

    /**
     * Helper method for writing your queries
     *
     * @param int $limit
     * @return $this
     */
    public function limit($limit)
    {
        $this->_helperLimit = $limit;
        return $this;
    }

    /**
     * Get the query generated by the helper methods for execution.
     * Helper method for writing your queries
     *
     * @param $reset
     * @return string
     * @throws \Exception
     */
    public function getGeneratedQuery($reset = true)
    {
        $sql = $this->_buildHelperQuery(true);
        if ($reset) {
            $this->_resetHelperQueryVars();
        }

        return $sql;
    }
} 