<?php
/**
 * Created by PhpStorm.
 * User: mncedim
 * Date: 16/04/10
 * Time: 2:08 PM
 */

require_once 'src/SimpleDb.php';
require_once 'src/SimplePager.php';

$db = new \Mncedim\SimpleDb('127.0.0.1', 'root', 'mysql', 'northwind');

//using the helper methods
$pager = new \Mncedim\SimplePager('Customers c', $db);
$pager->table()->select('c.CustomerID, c.ContactName, c.ContactTitle');

//using a query string
//$pager = new \Mncedim\SimplePager(null, $db);
//$pager->query('select c.CustomerID, c.ContactName, c.ContactTitle from Customers c');

$page = (isset($_GET['page']) ? $_GET['page'] : 1);

$response = $pager
    ->setPageSize(10)
    ->setRange(7)
    ->setCurrentPage($page)
    ->build();
?>


<!--FOR DEMO PURPOSES ONLY-->
<style type="text/css">
    ul li { display: inline-block; margin-right: 5px;}
</style>

<!--display retrieved data-->
<table border="1" style="width: 650px;">
    <tr>
        <th>CustomerID</th>
        <th>ContactName</th>
        <th>ContactTitle</th>
    </tr>
    <?php foreach($response->data as $record) :?>
        <tr>
            <td><?php echo $record['CustomerID'] ?></td>
            <td><?php echo $record['ContactName'] ?></td>
            <td><?php echo $record['ContactTitle'] ?></td>
        </tr>
    <?php endforeach ?>
</table>

<!--build paging view-->
<ul>
    <?php if (isset($response->head)) : ?>
        <li><a href="?page=<?php echo $response->head ?>">First Page</a></li>
    <?php endif ?>
    <?php if (isset($response->previous)) : ?>
        <li><a href="?page=<?php echo $response->previous ?>">Prev</a></li>
    <?php endif ?>
    <?php foreach($response->pages as $page) :?>
        <li>
            <?php if ($page == $response->activePage) : ?>
                <span><?php echo $page ?></span>
            <?php else : ?>
                <a href="?page=<?php echo $page ?>"><?php echo $page ?></a>
            <?php endif ?>
        </li>
    <?php endforeach ?>
    <?php if (isset($response->next)) : ?>
        <li><a href="?page=<?php echo $response->next ?>">Next</a></li>
    <?php endif ?>
    <?php if (isset($response->tail)) : ?>
        <li><a href="?page=<?php echo $response->tail ?>">Last Page</a></li>
    <?php endif ?>
</ul>
<?php

//echo '<pre>';
//print_r($response);