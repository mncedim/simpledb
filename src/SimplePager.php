<?php
/**
 * Created by PhpStorm.
 * User: Mncedi M (mncedim at gmail.com)
 * Date: 16/04/10
 * Time: 1:57 PM
 */

namespace Mncedim;

/**
 * Class SimplePager
 * @package Mncedim
 */
class SimplePager
{
    private $tableName;
    private $pageSize = 5;
    private $range = 7;
    private $rowCount = 0;
    private $totalPages = 0;
    private $currentPage = 1;
    private $rawQuery;
    private $data;

    /**
     * @var SimpleDb
     */
    private $db;

    /**
     * @param $table
     * @param SimpleDb $db
     */
    public function __construct($table, SimpleDb &$db = null)
    {
        $this->tableName = $table;

        if (!is_null($db)) {
            $this->setDb($db);
        }
    }

    /**
     * @param SimpleDb $db
     */
    public function setDb(SimpleDb &$db){
        $this->db = $db;
        $this->table();
    }

    /**
     * @param null $name
     * @return SimpleDb
     */
    public function table($name = null)
    {
        if (!is_null($name)) {
            $this->tableName = $name;
        }
        return $this->db->table($this->tableName);
    }

    /**
     * @param $query
     * @return SimpleDb
     */
    public function query($query)
    {
        $this->rawQuery = $query;
        return $this->db->query($query);
    }

    /**
     * Current Page
     * @param int $page
     * @return $this
     */
    public function setCurrentPage($page)
    {
        $this->currentPage = (int)$page;
        return $this;
    }

    /**
     * @param $size
     * @return $this
     */
    public function setPageSize($size)
    {
        $this->pageSize = (int)$size;
        return $this;
    }

    /**
     * @param $range
     * @return $this
     */
    public function setRange($range)
    {
        $this->range = (int)$range;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @return int
     */
    public function getPageSize()
    {
        return $this->pageSize;
    }

    /**
     * @return int
     */
    public function getTotalRecords()
    {
        return $this->rowCount;
    }

    /**
     * @return float
     */
    public function getTotalPages()
    {
        return $this->totalPages;
    }

    /**
     * @param int $dataFormat
     * @param null $pdoArgs
     * @return object
     * @throws \Exception
     */
    public function build($dataFormat = \PDO::FETCH_ASSOC, $pdoArgs = null)
    {
        $pager = array(
            'pages'         => array(),
            'activePage'    => $this->currentPage
        );

        //get data and total records from db
        if ($this->rawQuery) {

            $this->rowCount = $this->db->execute()->rowCount();
            $this->data = $this->query(
                $this->rawQuery . ' LIMIT ' . ( ( $this->currentPage - 1 ) * $this->pageSize ) . ", $this->pageSize"
            )->execute()->getResultSet($dataFormat, $pdoArgs);

        } else {

            $query  = $this->db->getGeneratedQuery(false);
            $offset = ( $this->currentPage - 1 ) * $this->pageSize;

            $this->data = $this->db->limit($this->pageSize, $offset)->execute()
                ->getResultSet($dataFormat, $pdoArgs);

            $this->rowCount = $this->db->query($query)->execute()->rowCount();
        }

        //build paging data
        $last   = $this->totalPages = ceil($this->rowCount / $this->pageSize); //last page
        $start  = ( ( $this->currentPage - $this->range ) > 0 ) ? $this->currentPage - $this->range : 1;
        $end    = ( ( $this->currentPage + $this->range ) < $last ) ? $this->currentPage + $this->range : $last;

        //go to first page
        if ( $start > 1 ) {
            $pager['head'] = 1;
        }

        for ( $page = $start; $page <= $end; $page++ ) {
            $pager['activePage'] = ( $this->currentPage == $page  ? $page : $pager['activePage'] );
            $pager['pages'][] = $page;
        }

        //go to last page
        if ( $end < $last ) {
            $pager['tail'] = $last;
        }

        //prev page
        if ($this->currentPage > 1) {
            $pager['previous'] = $this->currentPage - 1;
        }

        //next page
        if ($this->currentPage < $last) {
            $pager['next'] = $this->currentPage + 1;
        }

        $pager['data'] = $this->data;
        $pager['totalPages'] = $this->getTotalPages();
        $pager['totalRecords'] = $this->getTotalRecords();

        return (object) $pager;
    }

    /**
     * @param $method
     * @param $args
     * @return mixed
     */
    public function __call($method, $args)
    {
        if (!method_exists($this, $method)) {
            return call_user_func_array( array( $this->db, $method ), $args );
        }
    }
} 