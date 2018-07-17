<?php
require_once("classes/thing.class.php");

// require_once("func/ConexaoLocal.php");

class trade extends thing
{
  public $karma;

  public function __construct($thingUUID, $karma, $formerOwner) {
    parent::__construct($thingUUID);

    $this->karma = $karma;
    $this->formerOwner = $formerOwner;
  }

}
?>