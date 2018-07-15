<?php
require_once("classes/thing.class.php");

// require_once("func/ConexaoLocal.php");

class trade extends thing
{
  public $karma;

  public function __construct($thingUUID, $karma) {
    parent::__construct($thingUUID);

    $this->karma = $karma;
  }

}
?>