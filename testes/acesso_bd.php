<?php
require_once("func/ConexaoLocal.php");

$con = new ConexaoLocal();

$q = "SELECT * FROM tb_user";

$con->query($q);
if ($con->status === false) {
  echo "Pau\n";
  echo "***************************\n";
  echo $con->getDescRetorno()."\n";
  echo "***************************\n";
  echo $con->errno."-".$con->error."\n";
  echo "***************************\n";
  exit;
}

printf("Registros encontrados: [%d]\n", $con->count);
printf("Tempo: [%f]\n", $con->query_tempo);

// while (!$con->isEof()) {
//   var_dump($con->result);
//   $con->getFetchAssoc();
// }

  var_dump($con->result);
  $con->getFetchAssoc();

  var_dump($con->result);
  $con->getFetchAssoc();


?>