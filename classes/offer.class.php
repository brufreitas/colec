<?php
require_once("classes/thing.class.php");
require_once("func/ConexaoLocal.php");

class offer extends thing
{
  public $offerUUID;

  public function __construct($uuid) {
    $q = "SELECT HEX(thingUUID) AS thingUUID FROM tb_offer WHERE offerUUID = 0x{$uuid}";

    $con = new ConexaoLocal();
    $con->query($q);

    if ($con->count == 0) {
      echo "Não reconheço offer {$uuid}\n";
      unset($con);
      return false;
    }

    parent::__construct($con->result["thingUUID"]);

    $this->offerUUID = $uuid;
    unset($con);
  }

  public static function createNew($thingUUID) {
    $uuid = strtoupper(str_replace("-", "", uuidv4()));
    $a = array (
      "offerUUID" => "(INT)0x".$uuid."(INT)",
      "thingUUID" => "(INT)0x".$thingUUID."(INT)",
      "offerStartDTHR" => "(INT)NOW(6)(INT)",
    );

    $con = new ConexaoLocal();
    $con->execInsert($a, "tb_offer");
    if ($con->status === false) {
      echo "Pau\n";
      echo $con->getDescRetorno()."\n";
      echo $con->errno."-".$con->error."\n";
      return false;
    }

    return new offer($uuid);
  }
}
?>