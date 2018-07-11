<?php
require_once("server/socketWebSocket.class.php");

$clientConnect = function ($var) {
  echo "Entrou clientConnect\n";
  var_dump($var);
  echo "Fim de clientConnect\n";
};

$tick = function () {
  echo "Tick\n";
};

// $clientDisconnect = function ($var) {
//   echo "Entrou clientDisconnect\n";
//   var_dump($var);
//   echo "Fim de clientDisconnect\n";
// };

// $messageReceived = function ($var, $var2) {
//   echo "Entrou messageReceived\n";
//   var_dump($var, $var2);
//   echo "Fim de messageReceived\n";
// };

$socket = new socketWebSocket();


$socket->on("clientConnect", $clientConnect);
$socket->on("tick", $tick);
// $socket->on("clientDisconnect", $clientDisconnect);
// $socket->on("messageReceived", $messageReceived);

$socket->listen("0.0.0.0", 8081);

?>