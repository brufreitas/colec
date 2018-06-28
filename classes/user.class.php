<?php
class user
{
  // @todo: Não podem ser publics
  public $login;
  public $uuid;

  public function __construct($login, $pass) {
    // @todo ajustar o modo de fazer o login
    $q = "SELECT HEX(userUUID) AS userUUID FROM tb_user WHERE login = '{$login}' AND pass = '{$pass}'";
    $con = new ConexaoLocal();
    $con->query($q);
    if ($con->count == 0) {
      echo "Não reconheço {$login}\n";
      unset($con);
      return false;
    }
    $this->login = $login;
    $this->uuid = $con->result["userUUID"];
    unset($con);
    return true;
  }
}
?>