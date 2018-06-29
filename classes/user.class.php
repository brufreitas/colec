<?php
class user
{
  // @todo: Não podem ser publics
  public $login;
  public $uuid;

  public function __construct($login_uuid) {
    if (ctype_xdigit($login_uuid)) {
      $w = "userUUID = 0x{$login_uuid}";
    } else {
      $w = "login = '{$login_uuid}'";
    }

    // @todo ajustar o modo de fazer o login
    $q = "SELECT HEX(userUUID) AS userUUID, login FROM tb_user WHERE {$w}";

    $con = new ConexaoLocal();
    $con->query($q);
    if ($con->count == 0) {
      echo "Não reconheço login {$login_uuid}\n";
      unset($con);
      return false;
    }

    $this->login = $con->result["login"];
    $this->uuid = $con->result["userUUID"];
    unset($con);
    return true;
  }
}
?>