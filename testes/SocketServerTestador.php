<?php
require_once("server/socket.class.php");

$clientConnect = function ($var) {
  echo "Entrou clientConnect\n";
  var_dump($var);
  echo "Fim de clientConnect\n";
};

$clientDisconnect = function ($var) {
  echo "Entrou clientDisconnect\n";
  var_dump($var);
  echo "Fim de clientDisconnect\n";
};

$messageReceived = function ($var, $var2) {
  echo "Entrou messageReceived\n";
  var_dump($var, $var2);
  echo "Fim de messageReceived\n";
};

$socket = new socket();


$socket->on("clientConnect", $clientConnect);
$socket->on("clientDisconnect", $clientDisconnect);
$socket->on("messageReceived", $messageReceived);


$socket->listen("0.0.0.0", 8081);
?>