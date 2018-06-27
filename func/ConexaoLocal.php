<?php
require_once("func/Conexao.php");

class ConexaoLocal extends Conexao {
  public function __construct() {
    $server_name = strtolower(trim(shell_exec("hostname -s")));
    // parent::__construct("localhost:/mysql/mysql.sock", "colec", "colec", "colec");
    parent::__construct("localhost", "colec", "colec", "!C0ole3c");
  }
}
?>