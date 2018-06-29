<?php
require_once("classes/thing.class.php");
require_once("func/ConexaoLocal.php");

class offer extends thing
{
  public $uuid;
  public $name;

  public function __construct($uuid) {
    echo $q = "SELECT itemName FROM tb_item WHERE itemUUID = 0x{$uuid}";
    $con = new ConexaoLocal();
    $con->query($q);

    if ($con->count == 0) {
      echo "Não reconheço {$uuid}\n";
      unset($con);
      return false;
    }

    $this->uuid = $uuid;
    $this->name = $con->result["itemName"];
    unset($con);
    return true;
  }
}
?>