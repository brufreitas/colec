<?php
require_once("classes/item.class.php");
require_once("classes/user.class.php");

require_once("func/ConexaoLocal.php");

class thing extends item
{
  public $thingUUID;
  public $owner;

  public function __construct($uuid) {
    $q = "SELECT HEX(ownerUUID) AS ownerUUID, HEX(itemUUID) AS itemUUID FROM tb_thing WHERE thingUUID = 0x{$uuid}";

    $con = new ConexaoLocal();
    $con->query($q);

    if ($con->count == 0) {
      echo "Não reconheço {$uuid}\n";
      unset($con);
      return false;
    }


    parent::__construct($con->result["itemUUID"]);
    $this->owner = new user($con->result["ownerUUID"]);

    $this->thingUUID = $uuid;

    unset($con);
    return true;
  }
}
?>