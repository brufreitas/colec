<?php
require_once("classes/user.class.php");
require_once("classes/thing.class.php");
require_once("classes/trade.class.php");
require_once("func/uuid.php");
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

  public static function take($item, $user) {

    $q = "SELECT ".
           "HEX(o.offerUUID) AS offerUUID, ".
           "HEX(t.thingUUID) AS thingUUID, ".
           "HEX(t.ownerUUID) AS ownerUUID, ".
           "(t.ownerUUID = 0x{$user->uuid}) AS alreadyOwned ".
         "FROM tb_offer o ".
           "LEFT JOIN tb_thing t ON (o.thingUUID = t.thingUUID) ".
         "WHERE ".
           "t.itemUUID = 0x{$item->itemUUID} ".
         "ORDER BY alreadyOwned DESC, o.offerStartDTHR ".
         "LIMIT 1";

    $con = new ConexaoLocal();
    $con->query($q);

    if ($con->count == 0) {
      return false;
    }

    $offerUUID = $con->result["offerUUID"];
    $thingUUID = $con->result["thingUUID"];
    $fromUUID  = $con->result["ownerUUID"];

    if ($con->result["alreadyOwned"] == 0) {
      $karma = 1;
      $a = array (
        "tradeUUID" => "(INT)0x".uuidv4()."(INT)",
        "tradeDTHR" => "(INT)NOW(6)(INT)",
        "thingUUID" => "(INT)0x".$thingUUID."(INT)",
        "fromUUID" => "(INT)0x".$fromUUID."(INT)",
        "toUUID" => "(INT)0x".$user->uuid."(INT)",
        "karma" => "(INT)$karma(INT)",
      );

      $con->execInsert($a, "tb_trade");

      if ($con->status === false) {
        echo "Pau\n";
        echo $con->getDescRetorno()."\n";
        echo $con->errno."-".$con->error."\n";
        return false;
      }

      $a = array (
        "ownerUUID" => "(INT)0x".$user->uuid."(INT)",
        "ownerDTHR" => "(INT)NOW(6)(INT)",
      );

      $con->execUpdate($a, "tb_thing", "WHERE thingUUID = 0x{$thingUUID}");

      if ($con->status === false) {
        echo "Pau\n";
        echo $con->getDescRetorno()."\n";
        echo $con->errno."-".$con->error."\n";
        return false;
      }
    } else {
      $karma = 0;
    }

    $q = "DELETE FROM tb_offer WHERE offerUUID = 0x{$offerUUID}";
    $con->query($q);
    if ($con->status === false) {
      echo "Pau\n";
      echo $con->getDescRetorno()."\n";
      echo $con->errno."-".$con->error."\n";
      return false;
    }

    return new trade($thingUUID, $karma);
  }

  public static function createNew($thingUUID) {
    $uuid = uuidv4();
    $a = array (
      "offerUUID" => "(INT)0x{$uuid}(INT)",
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