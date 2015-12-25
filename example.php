<?php
/**
 * Created by PhpStorm.
 * User: mncedim
 * Date: 15/12/21
 * Time: 5:06 PM
 */

require_once 'src/SimpleDb.php';

$db = new \Mncedim\SimpleDb('127.0.0.1', 'root', '', 'northwind');

$db->table('Customers')
    //->select('*')
    ->where('Customers.CompanyName', '=', 'Around the Horn')
    ->andWhere('Customers.ContactName', '=', 'Thomas Hardy')
    ->orWhere('Customers.CompanyName', '=', 'Laughing Bacchus Wine Cellars')
    ->orWhere('Customers.CompanyName', '=', 'Ernst Handel')
    ->orWhere('Customers.CompanyName', '=', "B's Beverages")
    //->where('Customers.CompanyName', 'LIKE', 'ri')
    ->limit(10);

echo '<pre>';
print($db->getGeneratedQuery(false) . '<br>');
print_r(
    $db->execute()->getResultSet()
);