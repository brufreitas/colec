<?php
/**
 * Main Script of phpWebSockets
 *
 * Run this file in a shell or windows cmd to start the socket server.
 * Sorry for calling this daemon but the goal is that this server run
 * as daemon in near future.
 *
 * @author Moritz Wutz <moritzwutz@gmail.com>
 * @version 0.1
 * @package phpWebSockets
 */

ob_implicit_flush(true);

require_once("server/socketWebSocket.class.php");

// $webSocket = new socketWebSocket("192.168.1.60", 12390);
// $webSocket = new socketWebSocket("localhost", 12390);
// $webSocket = new socketWebSocket("172.17.0.25", 12390);
$webSocket = new socketWebSocket("0.0.0.0", 8081);
?>