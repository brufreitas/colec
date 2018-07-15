<?php

ob_implicit_flush(true);

require_once("server/main.class.php");

// $webSocket = new socketWebSocket("192.168.1.60", 12390);
// $webSocket = new socketWebSocket("localhost", 12390);
// $webSocket = new socketWebSocket("172.17.0.25", 12390);
$gameLoop = new main("0.0.0.0", 8081);
?>