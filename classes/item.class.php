<?php
require_once("func/ConexaoLocal.php");

class item
{
  public $itemID;
  public $itemName;

  public function __construct($iId) {
    $q = "SELECT itemName FROM tb_item WHERE itemID = '{$iId}'";
    $con = new ConexaoLocal();
    $con->query($q);

    if ($con->count == 0) {
      echo "Não reconheço {$iId}\n";
      unset($con);
      return false;
    }

    $this->itemID = $iId;
    $this->itemName = $con->result["itemName"];
    unset($con);
    return true;
  }
}
?>