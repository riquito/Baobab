<?php

// change this with the path where you put the library
require_once("../baobab.php");

/* either create the conf_database.php or create here the following variable
  
$DB_CONFIG=array(
    "host"     => ...
    "username" => ...
    "password" => ...
    "dbname"   => ...
    "port"     => ...
    "charset"  => "utf8" // remember mysql charsets have no dash
                         // [http://dev.mysql.com/doc/refman/5.1/en/charset-charsets.html]
*/
require_once("../test/conf_database.php");
if (!isset($DB_CONFIG)) throw new Exception("Missing or misconfigured conf_database.php");

$conn=mysqli_connect(
    $DB_CONFIG["host"],
    $DB_CONFIG["username"],
    $DB_CONFIG["password"],
    $DB_CONFIG["dbname"],
    $DB_CONFIG["port"]
);

try {
    mysqli_set_charset($conn,$DB_CONFIG["charset"]);
    
    if (count($argv)!=2 || FALSE===array_search($argv[1],
                                    array('animals','forum'))) {
        print_r("Usage: launcher.php [animals|forum]\n");
    } else {
        include($argv[1].".php");
        main($conn);
    }
    
    $conn->close();
}
catch (Exception $e) {
  $conn->close();
  throw $e;
}
