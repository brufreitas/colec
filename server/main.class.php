<?php

require_once("server/socketWebSocket.class.php");
require_once("func/uuid.php");
require_once("func/ConexaoLocal.php");
require_once("classes/user.class.php");
require_once("classes/item.class.php");
require_once("classes/thing.class.php");
require_once("classes/offer.class.php");
require_once("classes/trade.class.php");



class main extends socketWebSocket
{
  private $users = array();
  private $offerToSend = array();
  private $usersChanged = false;

  private $lastTime = 0;

  private $con;

  public function __construct($host, $port) {
    parent::__construct();
    $this->on("webSocketConnect", array($this, "webSocketConnection"));
    $this->on("clientDisconnect", array($this, "webSocketDisconnection"));
    $this->on("messageReceived", array($this, "readMessageReceived"));
    $this->on("tick", array($this, "gameLoop"));

    $this->con = new ConexaoLocal();

    $this->listen($host, $port);
  }

  protected function readMessageReceived ($socket_index, $message_string) {
    $user = $this->users[$socket_index];
    // $this->console(__CLASS__."->".__FUNCTION__." socket: `{$socket_index}`, user: `{$user->login}`, `{$message_string}`", "light_purple");

    if ($message_string == "ping") {
      $this->sendByIndex($socket_index, array("ping" => true));
      return true;
    }

    if (($pos = strpos($message_string, "user ")) === 0) {
      $name = substr($message_string, $pos + 5);

      $this->addUser($socket_index, $name);

      $this->sendByIndex($socket_index, array("sendPass" => true));
      return true;
    }

    if (($pos = strpos($message_string, "pass ")) === 0) {
      $pass = substr($message_string, $pos + 5);

      $output = array (
        "logonOk" => true,
        "refreshInventory" => $this->getAllThingsFrom($user),
        "refreshOffers" => $this->getAllOffers(),
        "refreshKarma" => $this->getKarmaFrom($user),
      );

      $this->sendByIndex($socket_index, $output);
      return true;
    }

    if ((strpos($message_string, "offer ")) === 0) {
      $thingUUID = substr($message_string, 6);

      $offer = offer::createNew($thingUUID);

      if ($offer instanceof offer) {
        $this->console("newOffer >> `{$offer->itemName}` from `{$offer->owner->login}`", "cyan");
        $this->offerToSend[] = $offer;

        $output = array(
          "offerAccepted" => $this->simplifyThing($offer),
        );

        $this->sendByIndex($socket_index, $output);
        return true;
      }

      return false;
    }

    if ((strpos($message_string, "takeOffer ")) === 0) {
      $item = new item(substr($message_string, 10));

      $trade = offer::take($item, $user);

      if ($trade instanceof trade) {
        $this->console("offerTaken >> `{$trade->itemName}` by `{$trade->owner->login}` from `{$trade->formerOwner->login}`", "light_blue");
        $this->offerToSend[] = $trade;

        $output = [
          "offerTaken" => $this->simplifyObj($trade),
        ];
        $this->sendByIndex($socket_index, $output);

        // @todo offerBlabla? Arrumar um nome decente!
        $output = [
          "offerBlabla" => $this->simplifyObj($trade),
        ];
        $this->sendToUser($trade->formerOwner, $output);

        return true;
      }

      return false;
    }


    if ((strpos($message_string, "chat ")) === 0) {
      $chat_msg = substr($message_string, $pos + 5);

      $this->sendToOthers($socket_index, array("message" => $user->login.': '.$chat_msg));
      return true;
    }

    $this->console("Invalid command `{$message_string}` from `{$user->login}`", "white", "red");
    return false;
  }

  protected function webSocketConnection ($socket_index) {
    // $this->console(__CLASS__."->".__FUNCTION__."------- {$socket_index}", "light_purple");

    $user = new stdclass();
    $user->login = "notLoggedYet";

    $this->users[$socket_index] = $user;

    $this->sendByIndex($socket_index, array("sendUser" => true));
    return true;
  }

  protected function webSocketDisconnection ($socket_index) {
    // $this->console(__CLASS__."->".__FUNCTION__."------- {$socket_index}", "light_purple");

    $this->removeUser($socket_index);
  }

  private function prepareArrayToSend($arrSend) {
    $arrSend["now"] = date("Y-m-d H:i:s");
    $msg = json_encode($arrSend);

    if (json_last_error() != JSON_ERROR_NONE) {
      $this->console("json_encode error >> ".json_last_error_msg(), "white", "red");
      return false;
    }

    return $msg;
  }

  /**
   * Convert a array to a JSON string and send to the $socket_index
   *
   * @param int $socket_index The index of the socket to send the data
   * @param array $arrSend An array that will be converted to JSON and send
   */
  protected function sendByIndex($socket_index, $arrSend) {
    parent::sendByIndex($socket_index, $this->prepareArraytoSend($arrSend));
  }

  /**
   * Convert a array to a JSON string and send to the $user if logged
   *
   * @param int $socket_index The index of the socket to send the data
   * @param array $arrSend An array that will be converted to JSON and send
   */
  protected function sendToUser($user, $arrSend) {
    $socket_index = -1;
    foreach ($this->users as $socketAux => $userAux) {
      if ($user->uuid == $userAux->uuid) {
        $socket_index = $socketAux;
        break;
      }
    }

    if ($socket_index == -1) {
      $this->console("Cannot sendToUser. User `{$user->login}` is not logged", "red");
      return false;
    }

    parent::sendByIndex($socket_index, $this->prepareArraytoSend($arrSend));
  }


  /**
   * Convert a array to a JSON string and send to everyone else but the $socket_index
   *
   * @param int $socket_index_me The index of the socket that will not receive the data
   * @param array $arrSend An array that will be converted to JSON and send
   */
   protected function sendToOthers($socket_index_me, $arrSend) {
    // @todo: send only to logged people
    parent::sendToOthers($socket_index_me, $this->prepareArraytoSend($arrSend));
  }

  /**
   * Convert a array to a JSON string and send to every client
   *
   * @param array $arrSend An array that will be converted to JSON and sent
   */
  protected function sendToAll($arrSend) {
    // @todo: send only to logged people
    parent::sendToAll($this->prepareArraytoSend($arrSend));
  }

  protected function gameLoop($loopId) {
    $output = array();

    if ($this->usersChanged) {

      $output["users"] = array();
      foreach($this->users as $user) {
        if ($user->login == "notLoggedYet") {
          continue;
        }
        $output["users"][] = $user->login;
      }

      $this->console(__CLASS__."->".__FUNCTION__." Sending users [".count($output["users"])."]", "brown");

      $this->usersChanged = false;
    }

    if (count($this->offerToSend) > 0) {
      $output["newOffer"] = array();
      $output["removeOffer"] = array();
      foreach($this->offerToSend as $obj) {

        if ($obj instanceof trade){
          $output["removeOffer"][] = $this->simplifyOffer($obj);
        } else {
          $output["newOffer"][] = $this->simplifyOffer($obj);
        }
      }
      $this->offerToSend = array();
    }

    $timeToPick = false;
    $timeDiff = (microtime(true) - $this->lastTime) * 1000;
    if ($timeDiff >= 5000) {
      $timeToPick = true;
      $broadMsg = "Still alive, loop id: {$loopId}";
      $this->console("Broadcast >> ".$broadMsg, "brown");

      // $output["srvmsg"] = $broadMsg;
      $this->lastTime = microtime(true);
    }

    if ($timeToPick || count($output) > 0) {

      foreach ($this->users as $socket_index => $user) {

        if ($user->login == "notLoggedYet") {
          continue;
        }

        $uniqOutput = $output;
        if ($timeToPick && ($thing = $this->pickItem())) {
          $uniqOutput["newItem"][] = $thing;
          $this->console("newItem >> {$thing["iNM"]} to {$user->login}", "cyan");
          $a = array(
            "thingUUID" => "(INT)0x{$thing["uuid"]}(INT)",
            "itemID" => $thing["iID"],
            "creationDTHR" => "(INT)NOW(6)(INT)",
            "ownerUUID" => "(INT)0x{$user->uuid}(INT)",
            "ownerDTHR" => "(INT)creationDTHR(INT)",
          );
          $this->con->execInsert($a, "tb_thing");
          if ($this->con->status === false) {
            echo "Pau\n";
            echo $this->con->getDescRetorno()."\n";
            echo $this->con->errno."-".$this->con->error."\n";
          }
          unset($a);
          unset($thing);
        }

        if (count($uniqOutput) > 0) {
          $this->sendByIndex($socket_index, $uniqOutput);
        }
      }
    }
  }

  private function pickItem() {
    static $bag;
    $chance = 0.025; 
    // $chance = 0.1; 

    if (!$bag) {
      $bag = array();
    }

    echo "Bag: ".count($bag)."\n";

    if (count($bag) <= 500) {
      echo "Enchendo o saco! \n";
      $chance = round($chance * 1000);

      // echo "Chance: ".($chance)."\n";

      $bag = 
        array_fill(0, $chance, true) +
        array_fill($chance + 1, 1000 - $chance, false);

      shuffle($bag);
    }

    // var_dump($bag);

    $pickKey = array_rand($bag);

    $pick = array_splice($bag, $pickKey, 1);

    echo "pickKey: ".($pickKey)."\n";
    var_dump($pick[0]);

    // array_shift($bag)
    $aux = array();
    foreach($bag as $bool) {
      @$aux[$bool]++;
    }

    printf("Trues: %d (%0.3f), falses: %d (%0.3f)\n", $aux[true], (($aux[true] / count($bag)) * 100), $aux[false], (($aux[false] / count($bag)) * 100));
    // printf("Trues: %d (%.01f), falses: %d (%.01f)\n", $aux[true], ($aux[true] / count($aux) * 100), $aux[false], ($aux[false] / count($aux) * 100));
    // var_dump($aux);

    // echo "Próximo true: ".(array_search(true, $bag))."\n";

    if (!$pick[0]) {
      return false;
    }

    // $q = "SELECT itemID AS iID, itemName AS iNM FROM tb_item i LEFT JOIN tb_collec c ON () ORDER BY RAND() LIMIT 1";
    $q = 
    "SELECT ".
      "i.itemID AS iID, ".
      "i.itemName AS iNM ".
    "FROM tb_collec c ".
    "LEFT JOIN tb_col_slot      cs   ON (c .collecID = cs .collecID) ".
    "LEFT JOIN tb_col_slot_item csi  ON (cs.slotID   = csi.slotID  ) ".
    "LEFT JOIN tb_item          i    ON (csi.itemID  = i  .itemID  ) ".
    "WHERE c.collecID = 'f623q3c2' ".
    "ORDER BY RAND() ".
    "LIMIT 1";
    $this->con->query($q);

    if ($this->con->status === false) {
      $this->console($this->con->getDescRetorno(), "white", "red");
      $this->console($this->con->errno."-".$this->con->error, "white", "red");
      return false;
    }

    $ret = $this->con->result;
    $ret["uuid"] = strtoupper(uuidv4());

    return $ret;
  }


  /**
   * Disconnects a socket an delete all related data
   *
   * @param socket $socket The socket to disconnect
   */
  private function disconnected($socket) {
    $index = array_search($socket, $this->allsockets);

    if ($index >= 0) {
      unset($this->allsockets[$index]);
      unset($this->clients[$index]);
      unset($this->handshakes[$index]);

      $this->removeUser($index);
    }

    socket_close($socket);
    $this->console("Socket-- [{$socket}]", "light_red");
  }

  private function addUser($socket_index, $userName) {
    $user = new user($userName);
    if (!$user) {
      $this->console("User not recognized {$userName}", "white", "red");
      return false;
    }

    $this->users[$socket_index] = $user;
    $this->console("User++ [{$socket_index}]=>[{$user->login}]", "green");

    $this->usersChanged = true;
  }

  protected function removeUser($socket_index) {
    $user = $this->users[$socket_index];
    $this->console("User-- [{$socket_index}]=>[{$user->login}]", "light_red");

    unset($this->users[$socket_index]);

    $this->usersChanged = true;
  }

  private function getAllThingsFrom($user) {
    $ret["t"] = array();

    $q = "SELECT ".
           "HEX(t.thingUUID) AS uuid, ".
           "t.itemID AS iID, ".
           "i.itemName AS iNM ".
         "FROM tb_thing t ".
         "LEFT JOIN tb_item i ON (t.itemID = i.itemID) ".
         "LEFT JOIN tb_offer o ON (t.thingUUID = o.thingUUID) ".
         "WHERE ".
           "t.ownerUUID = 0x{$user->uuid} ".
           "AND ISNULL(o.offerUUID) ".
         "ORDER BY i.itemName";
    $this->con->query($q);

    if ($this->con->status === false) {
      $this->console($this->con->getDescRetorno(), "white", "red");
      $this->console($this->con->errno."-".$this->con->error, "white", "red");
      return false;
    }

    while (!$this->con->isEof()) {
      $ret["t"][] = $this->con->result;
      $this->con->getFetchAssoc();
    }

    return $ret;
  }

  private function getAllOffers() {
    $ret["o"] = array();

    $q = "SELECT ".
           "COUNT(*) AS qt, ".
           "i.itemID AS iID, ".
           "i.itemName AS iNM ".
         "FROM tb_offer o ".
         "LEFT JOIN tb_thing t ON (o.thingUUID = t.thingUUID) ".
         "LEFT JOIN tb_item i ON (t.itemID = i.itemID) ".
         "GROUP BY i.itemID ".
         "ORDER BY i.itemName";
    $this->con->query($q);

    if ($this->con->status === false) {
      $this->console($this->con->getDescRetorno(), "white", "red");
      $this->console($this->con->errno."-".$this->con->error, "white", "red");
      return false;
    }

    while (!$this->con->isEof()) {
      $ret["o"][] = $this->con->result;
      $this->con->getFetchAssoc();
    }

    return $ret;
  }

  private function getKarmaFrom($user) {

    $q = "SELECT ".
           "IFNULL((SELECT SUM(karma) FROM tb_trade WHERE fromUUID = 0x{$user->uuid}), 0) - ".
           "IFNULL((SELECT SUM(karma) FROM tb_trade WHERE toUUID = 0x{$user->uuid}), 0) AS karma";
    $this->con->query($q);

    if ($this->con->status === false) {
      $this->console($this->con->getDescRetorno(), "white", "red");
      $this->console($this->con->errno."-".$this->con->error, "white", "red");
      return false;
    }

    return [
      "k" => $this->con->result["karma"]
    ];
  }

  private function simplifyObj($obj) {
    if ($obj instanceof offer) {
      $ret = $this->simplifyOffer($obj);
    } elseif ($obj instanceof trade) {
      $ret = (object) [
        "uuid" => $obj->thingUUID,
        "iID" => $obj->itemID,
        "iNM" => $obj->itemName,
        "k" => $obj->karma,
      ];
    } elseif ($obj instanceof thing) {
      $ret = $this->simplifyThing($obj);
    }

    return $ret;
  }

  private function simplifyThing($thing) {
    return (object) [
      "uuid" => $thing->thingUUID,
      "iID" => $thing->itemID,
      "iNM" => $thing->itemName,
    ];
  }

  private function simplifyOffer($offer) {
    return (object) [
      "iID" => $offer->itemID,
      "iNM" => $offer->itemName,
      "qt" => 1,
    ];
  }
}
?>