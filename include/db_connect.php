<?php
$db_host = ""; //при размещении на хостинге оставить так
$db_user = "";
$db_pass = "";
$db_database = "";

//$link = mysqli_connect($db_host, $db_user, $db_pass, $db_database) or die("Some error occurred during connection " . mysqli_error($slink));
//mysqli_query($link, "SET NAMES UTF8");

try {
    $link = new PDO("mysql:host=$db_host;dbname=$db_database", $db_user, $db_pass);
    $link->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $link->exec("SET NAMES UTF8");
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}