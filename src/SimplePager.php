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
     * @var \stdClass
     */
    private $pager;

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
            $this->table($table);
        }
    }

    /**
     * @param SimpleDb $db
     * @return $this
     */
    public function setDb(SimpleDb &$db){
        $this->db = $db;
        return $this;
    }

    /**
     * @param null $name
     * @return SimpleDb
     */
    public function table($name = null)
    {
        if ($this->db && !is_null($name)) {
            $this->db->table($name);
        }
        return $this->db;
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
        $offset = ( $this->currentPage - 1 ) * $this->pageSize;

        if ($this->rawQuery) {

            $query = "{$this->rawQuery} LIMIT $offset, $this->pageSize";
            $this->rowCount = $this->db->execute()->rowCount();
            $this->data = $this->query($query)->execute()->getResultSet($dataFormat, $pdoArgs);
        } else {

            $query  = $this->db->getGeneratedQuery(false);
            $this->data = $this->db->limit($this->pageSize, $offset)->execute()->getResultSet($dataFormat, $pdoArgs);
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

        //pages in-between
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

        $this->pager = (object)$pager;

        return $this->pager;
    }

    /**
     * @param string $firstPageLabel
     * @param string $prevPageLabel
     * @param string $nextPageLabel
     * @param string $lastPageLabel
     */
    public function render(
        $firstPageLabel = 'First Page',
        $prevPageLabel = 'Prev',
        $nextPageLabel = 'Next',
        $lastPageLabel = 'Last Page'
    )
    {
        if (!$this->pager) {
            return;
        }

        // keep everything in the query string
        $queryString = $_GET;

        ?>
        <ul>
            <?php if (isset($this->pager->head)) : ?>
                <li><a href="<?php echo $this->pagingUrl($queryString, $this->pager->head); ?>"><?php echo $firstPageLabel; ?></a></li>
            <?php endif ?>

            <?php if (isset($this->pager->previous)) : ?>
                <li><a href="<?php echo $this->pagingUrl($queryString, $this->pager->previous) ?>"><?php echo $prevPageLabel; ?></a></li>
            <?php endif ?>

            <?php foreach($this->pager->pages as $page) :?>
                <li <?php if ($page == $this->pager->activePage) : ?>class="active"<?php endif ?>>
                    <a href="<?php echo $this->pagingUrl($queryString, $page) ?>"><?php echo $page ?></a>
                </li>
            <?php endforeach ?>

            <?php if (isset($this->pager->next)) : ?>
                <li><a href="<?php echo $this->pagingUrl($queryString, $this->pager->next) ?>"><?php echo $nextPageLabel; ?></a></li>
            <?php endif ?>

            <?php if (isset($this->pager->tail)) : ?>
                <li><a href="<?php echo $this->pagingUrl($queryString, $this->pager->tail) ?>"><?php echo $lastPageLabel; ?></a></li>
            <?php endif ?>
        </ul>
        <?php
    }

    /**
     * @param $get
     * @param $page
     * @return string
     */
    private function pagingUrl($get, $page)
    {
        $get['page'] = $page;
        return '?'.http_build_query($get);
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