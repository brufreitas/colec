<?php
require_once("func/ConexaoLocal.php");

$con = new ConexaoLocal();

$con->query("SELECT * FROM tb_user");
if ($con->status === false) {
  echo "Pau\n";
  echo $con->getDescRetorno()."\n";
  echo $con->errno."-".$con->error."\n";
  exit;
}

while (!$con->isEof()) {
  var_dump($con->result);
  $con->getFetchAssoc();
}


?>