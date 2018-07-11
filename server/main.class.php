<?php

require_once("server/socketWebSocket.class.php");
require_once("func/uuid.php");
require_once("func/ConexaoLocal.php");
require_once("classes/user.class.php");
require_once("classes/item.class.php");
require_once("classes/thing.class.php");
require_once("classes/offer.class.php");




class main extends socketWebSocket
{
  // private $clients = array();
  private $users = array();
  private $offerToSend = array();

  //variables added by me
  private $lastTime = 0;

  private $con;

  private $usersChanged = false;


  public function __construct($host, $port) {
    parent::__construct();
    $this->on("webSocketConnect", array($this, "webSocketConnection"));
    // $this->on("tick", array($this, "gameLoop"));
    // $this->on("clientDisconnect", array($this, "webSocketConnection"));
    $this->on("messageReceived", array($this, "readMessageReceived"));

    $this->con = new ConexaoLocal();

    $this->listen($host, $port);
  }

  protected function readMessageReceived ($socket_index, $message_string) {
    $user = $this->users[$socket_index];
    $this->console(__CLASS__."->".__FUNCTION__." socket: `{$socket_index}`, user: `{$user->login}`, `{$message_string}`", "light_purple");

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

      $output = array(
        "logonOk" => true,
        "refreshInventory" => $this->getAllThingsFrom($user),
        "refreshOffers" => $this->getAllOffers(),
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
          "offerAccepted" => $offer,
        );
        $this->sendByIndex($socket_index, $output);
        return true;
      }

      return false;
    }

    if ((strpos($message_string, "chat ")) === 0) {
      $chat_msg = substr($message_string, $pos + 5);

      $this->sendToOthers($socket_index, array("message" => $user->login.': '.$chat_msg));
      return true;
    }

    $this->console("Invalid command {$message_string}", "white", "red");
    return false;
  }

  protected function webSocketConnection ($socket_index) {
    $this->console(__CLASS__."->".__FUNCTION__."------- {$socket_index}", "light_purple");

    $user = new stdclass();
    $user->login = "notLoggedYet";

    $this->users[$socket_index] = $user;

    $this->sendByIndex($socket_index, array("sendUser" => true));
    return true;
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
    parent::sendToAll($socket_index_me, $this->prepareArraytoSend($arrSend));
  }

  private function prepareArraytoSend($arrSend) {
    $arrSend["now"] = date("Y-m-d H:i:s");
    $msg = json_encode($arrSend);

    if (json_last_error() != JSON_ERROR_NONE) {
      $this->console("json_encode error >> ".json_last_error_msg(), "white", "red");
      return false;
    }

    return $msg;
  }


  /**
   * Runs the while loop, wait for connections and handle them
   */
  private function run() {

    while (true) {
      $this->counter++;
      // because socket_select gets the sockets it should watch from $changed_sockets
      // and writes the changed sockets to that array we have to copy the allsocket array
      // to keep our connected sockets list
      $changed_sockets = $this->allsockets;

      var_dump($this->allsockets);
      sleep(3);

      $write  = array();
      $except = array();
      //blocks execution until data is received from any socket
      //OR will wait 10ms(10000us) - should theoretically put less pressure on the cpu
      $num_sockets = socket_select($changed_sockets, $write, $except, 0, 10000);

      foreach ($changed_sockets as $socket) {
        // master socket changed means there is a new socket request
        if ($socket == $this->master) {

          // if accepting new socket fails
          if (($client = socket_accept($this->master)) < 0) {
            $this->console("socket_accept() failed: reason: " . socket_strerror(socket_last_error($client)), "white", "red");
            continue;
          }

          // if it is successful push the client to the allsockets array
          // $this->allsockets[] = $client;

          // using array key from allsockets array, is that ok?
          // I want to avoid the often array_search calls
          $socket_index = array_search($client, $this->allsockets);
          $this->clients[$socket_index] = new stdClass;
          $this->clients[$socket_index]->socket_id = $client;


          $this->console("Socket++ [{$client}]", "light_green");

          // var_dump($this->clients);
          // sleep(3);

          continue;
        }

        // client socket has sent data
        // $socket_index = array_search($socket, $this->allsockets);
        // $user = $this->users[$socket_index];

        $user = $this->getUserBySocket($socket);

        if(!$user) {
          $user = new stdclass();
          $user->login = "unknow";
        }

        // $bytes = @socket_recv($socket, $buffer, 2048, MSG_DONTWAIT);

        $rawData = "";
        while ($bytes = @socket_recv($socket, $buffer, 2048, MSG_DONTWAIT)) {
          $rawData .= $buffer;
          if ($bytes < 2048) break;

          $this->console("Reading...");
          usleep(1000);
        }

        if ($bytes === false) {
          $this->console("socket_recv() failed, reason: [".socket_strerror(socket_last_error($socket))."]", "white", "red");
          continue;
        }

        $this->console("Received: [{$bytes}] bytes from: [{$user->login}], socket: [{$socket}]");

        //  the client status changed, but theres no data ---> disconnect
        if ($bytes === 0) {
          $this->console("no data");
          $this->disconnected($socket);
          continue;
        }

        // echo "Handshakes / antes\n===================\n";
        // var_dump($this->handshakes);
        // echo "===================\nfim Handshakes / antes\n===================\n";

        // this is a new connection, no handshake yet
        if (!isset($this->handshakes[$socket_index])) {
          $this->do_handshake($rawData, $socket, $socket_index);

          // echo "Handshakes / depois\n===================\n";
          // var_dump($this->handshakes);
          // echo "===================\nfim Handshakes / depois\n===================\n";

          $output = array("sendUser" => true);
          $this->send($socket, $output);

          continue;
        }

        $action = $this->unmask($rawData);

        if ($action == "") {
          $this->console("Empty action");
          $this->disconnect($socket);
          continue;
        }

        //Browser refresh / close
        if ($action == chr(3).chr(233)) {
          $this->console("Browser refresh / close", "red");
          $this->disconnect($socket);
          continue;
        }

        $this->console("Action: [{$action}]");

        if ($action == "ping") {
          $output = array();
          $output['ping'] = true;
          $this->send($socket, $output);

          // $this->console("Ping from: [{$user->login}]", "dark_gray");

          continue;
        }

        if (($pos = strpos($action, "user ")) === 0 && ($user || !$loggedon)) {
          $name = substr($action, $pos + 5);

          $output = array('sendPass' => true);
          $this->send($socket, $output);

          $this->addUser($socket_index, $name);

          continue;
        }

        if (($pos = strpos($action, "pass ")) === 0) {
          $name = substr($action, $pos + 7);

          $output = array("logonOk" => true);

          $output["refreshInventory"] = $this->getAllThingsFrom($user);
          $output["refreshOffers"] = $this->getAllOffers();

          $this->send($socket, $output);

          unset($output);

          continue;
        }

        if ((strpos($action, "offer ")) === 0) {
          $thingUUID = substr($action, 6);

          $offer = offer::createNew($thingUUID);

          if ($offer instanceof offer) {
            $this->console("newOffer >> `{$offer->itemName}` from `{$offer->owner->login}`", "cyan");
            $this->offerToSend[] = $offer;

            $output = array();
            $output["offerAccepted"] = $offer;
            $this->send($socket, $output);
            unset($output);
          }

          unset($offer);

          continue;
        }

        $this->console("Message from: [{$user->login}]: {$action}", "cyan");

        //Mensagem recebida
        $skipSockets = array($this->master, $socket);
        $them = array_diff($this->allsockets, $skipSockets);
        $output = array();
        $output['message'] = $user->login.': '.$action;
        foreach ($them as $sock) {
          $this->send($sock, $output);
        }
      } //foreach socket_select


      //server messages


      $output = array();

      if ($this->usersChanged) {
        $this->console("Sending users [".count($this->users)."]", "brown");

        $output["users"] = array();
        foreach($this->users as $user) {
          $output["users"][] = $user->login;
        }

        // $output["users"] = $this->users;
        $this->usersChanged = false;
      }

      if (count($this->offerToSend) > 0) {
        $output["newOffer"] = array();
        foreach($this->offerToSend as $offer) {

          $obj = (object) [
            'iId' => $offer->itemUUID,
            'nm' => $offer->itemName,
            'qt' => 1,
          ];

          $output["newOffer"][] = $obj;
        }
        $this->offerToSend = array();
      }

      $timeToPick = false;
      $timeDiff = (microtime(true) - $this->lastTime) * 1000;
      if ($timeDiff >= 5000) {
        $timeToPick = true;
        $broadMsg = "Still alive, loop id: {$this->counter}";
        $this->console("Broadcast >> ".$broadMsg, "brown");

        // $output["srvmsg"] = $broadMsg;
        $this->lastTime = microtime(true);
      }

      if ($timeToPick || count($output) > 0) {

        $destinSockets = array_diff($this->allsockets, array($this->master));
        foreach ($destinSockets as $sock) {

          // @todo verificar o jeito mais eficiente de descobrir o usuário amarrado no socket
          $user = $this->getUserBySocket($sock);
          if (!$user || !$user->uuid) {
            continue;
          }

          $uniqOutput = $output;
          if ($timeToPick && $item = $this->pickItem()) {
            $uniqOutput["newItem"][] = $item;
            $this->console("newItem >> {$item["nm"]} to {$user->login}", "cyan");
            $a = array(
              "thingUUID" => "(INT)0x".str_replace("-", "", $item["uuid"])."(INT)",
              "itemUUID" => "(INT)0x".str_replace("-", "", $item["id"])."(INT)",
              "creationDTHR" => "(INT)NOW(6)(INT)",
              "ownerUUID" => "(INT)0x".str_replace("-", "", $user->uuid)."(INT)",
              "ownerDTHR" => "(INT)creationDTHR(INT)",
            );
            $this->con->execInsert($a, "tb_thing");
            if ($this->con->status === false) {
              echo "Pau\n";
              echo $this->con->getDescRetorno()."\n";
              echo $this->con->errno."-".$this->con->error."\n";
            }
            unset($a);
          }

          if (count($uniqOutput) > 0) {
            $this->send($sock, $uniqOutput);
          }
        }
      }
    }
  }

  private function getUserBySocket($sock) {
    $socket_index = array_search($sock, $this->allsockets);

    if (!array_key_exists($socket_index, $this->users)) {
      return false;
    }

    return $this->users[$socket_index];

  }

  private function pickItem() {
    $chances = array(true, false, false, false, false, false, false, false, false, false, false, false, false, false, false, false, false, false, false, false, false); //4,7%
    // $chances = array(true, false, false, false, false, false, false, false, false, false, false, false, false, false, false, false, false, false, false, false); //5,0%
    // $chances = array(true, false, false, false, false, false, false, false, false, false, false, false, false, false, false, false, false, false, false); //5,2%
    // $chances = array(true, false, false, false, false, false, false, false, false, false, false, false, false, false, false, false, false, false); //5,5%
    // $chances = array(true, false, false, false, false, false, false, false, false, false, false, false, false, false, false, false, false); //5,8%
    // $chances = array(true, false, false, false, false, false, false, false, false, false, false, false, false, false, false, false); //6,2%
    // $chances = array(true, false, false, false, false, false, false, false, false, false, false, false, false, false, false); //6,6%
    // $chances = array(true, false, false, false, false, false, false, false, false, false, false, false, false, false); //7,1%
    // $chances = array(true, false, false, false, false, false, false, false, false, false, false, false, false); //7,6%
    // $chances = array(true, false, false, false, false, false, false, false, false, false, false, false); //8%
    // $chances = array(true, false, false, false, false, false, false, false, false, false, false); //9%
    // $chances = array(true, false, false, false, false, false, false, false, false, false); //10%
    // $chances = array(true, false, false, false, false, false, false, false, false); //11,1%
    // $chances = array(true, false, false, false, false, false, false, false); //12,5%
    // $chances = array(true, false, false, false, false, false, false); //14,3%
    // $chances = array(true, false, false, false, false, false); //16,7%
    // $chances = array(true, false, false, false, false); //20%
    // $chances = array(true, false, false, false); //25%
    // $chances = array(true, false, false); //33,3%
    // $chances = array(true, false); //50%
    // $chances = array(true, true, true, false); //75%
    $chance = $chances[mt_rand(0, count($chances) - 1)];

    if (!$chance) {
      return false;
    }
  
    $str = file_get_contents("/var/www/html/colec/collections/worldcup2018.json");
    $arr = json_decode($str, true);

    $ret = $arr[mt_rand(0, count($arr) - 1)];
    $ret["uuid"] = strtoupper(str_replace("-", "", uuidv4()));
    $ret["id"  ] = strtoupper(str_replace("-", "", $ret["id"]));

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
    $this->console("User  ++ [{$user->login}]", "green");

    $this->usersChanged = true;
  }

  protected function removeUser($socket) {
    $user = $this->users[$socket];
    $this->console("User  -- [{$user->login}]", "red");

    unset($this->users[$socket]);

    $this->usersChanged = true;
  }

  private function getAllThingsFrom($user) {
    $ret["t"] = array();

    $q = "SELECT ".
           "HEX(t.thingUUID) AS uuid, ".
           "HEX(t.itemUUID) AS id, ".
           "i.itemName AS nm ".
         "FROM tb_thing t ".
         "LEFT JOIN tb_item i ON (t.itemUUID = i.itemUUID) ".
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
      $ret["t"][] = array(
        "uuid" => $this->con->result["uuid"],
        "id" => $this->con->result["id"],
        "nm" => $this->con->result["nm"],
      );
      $this->con->getFetchAssoc();
    }

    return $ret;
  }

  private function getAllOffers() {
    $ret["o"] = array();

    $q = "SELECT ".
           "COUNT(*) AS qt, ".
           "HEX(i.itemUUID) AS iId, ".
           "i.itemName AS nm ".
         "FROM tb_offer o ".
         "LEFT JOIN tb_thing t ON (o.thingUUID = t.thingUUID) ".
         "LEFT JOIN tb_item i ON (t.itemUUID = i.itemUUID) ".
         "GROUP BY i.itemUUID ".
         "ORDER BY i.itemName";
    $this->con->query($q);

    if ($this->con->status === false) {
      $this->console($this->con->getDescRetorno(), "white", "red");
      $this->console($this->con->errno."-".$this->con->error, "white", "red");
      return false;
    }

    while (!$this->con->isEof()) {
      $ret["o"][] = array(
        "iId" => $this->con->result["iId"],
        "nm" => $this->con->result["nm"],
        "qt" => $this->con->result["qt"],
      );
      $this->con->getFetchAssoc();
    }

    return $ret;
  }
}
?>