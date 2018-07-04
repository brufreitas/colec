<?php
/**
 * WebSocket extension class of phpWebSockets
 *
 * @author Moritz Wutz <moritzwutz@gmail.com>
 * @version 0.1
 * @package phpWebSockets
 */

require_once("server/socket.class.php");
require_once("func/uuid.php");
require_once("func/ConexaoLocal.php");
require_once("classes/user.class.php");
require_once("classes/item.class.php");
require_once("classes/thing.class.php");
require_once("classes/offer.class.php");




class socketWebSocket extends socket
{
  private $clients = array();
  private $handshakes = array();
  private $users = array();
  private $offerToSend = array();

  //variables added by me
  private $counter = 0;
  private $lastTime = 0;

  private $con;

  private $usersChanged = false;

  protected $wsUUID = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11";

  public function __construct($host, $port) {
    parent::__construct($host, $port);
    $this->con = new ConexaoLocal();

    $this->run();
  }

  /**
   * Runs the while loop, wait for connections and handle them
   */
  private function run() {

    // echo "MSG_DONTWAIT [".MSG_DONTWAIT."], MSG_WAITALL [".MSG_WAITALL."]\n";
    // echo "MSG_OOB [".MSG_OOB."], MSG_PEEK [".MSG_PEEK."]\n";

    while (true) {
      $this->counter++;
      // because socket_select gets the sockets it should watch from $changed_sockets
      // and writes the changed sockets to that array we have to copy the allsocket array
      // to keep our connected sockets list
      $changed_sockets = $this->allsockets;

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
          $this->allsockets[] = $client;

          // using array key from allsockets array, is that ok?
          // I want to avoid the often array_search calls
          $socket_index = array_search($client, $this->allsockets);
          $this->clients[$socket_index] = new stdClass;
          $this->clients[$socket_index]->socket_id = $client;

          $this->console("Socket++ [{$client}]", "light_green");

          continue;
        }

        // client socket has sent data
        $socket_index = array_search($socket, $this->allsockets);
        $user = $this->users[$socket_index];

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

        // $this->console("Received: [{$bytes}] bytes from: [{$user->login}], socket: [{$socket}]");

        //  the client status changed, but theres no data ---> disconnect
        if ($bytes === 0) {
          $this->console("no data");
          $this->disconnected($socket);
          continue;
        }

        // this is a new connection, no handshake yet
        if (!isset($this->handshakes[$socket_index])) {
          $this->do_handshake($rawData, $socket, $socket_index);

          $output = array('sendUser' => true);
          $this->send($socket, json_encode($output));

          continue;
        }

        $action = $this->unmask($rawData);

        if ($action == "") {
          $this->console("Empty action");
          $this->disconnected($socket);
          continue;
        }

        //Browser refresh / close
        if ($action == chr(3).chr(233)) {
          $this->console("Browser refresh / close", "red");
          $this->disconnected($socket);
          continue;
        }

        // $this->console("Action: [{$action}]");

        if ($action == "ping") {
          $output = array();
          $output['ping'] = true;
          $this->send($socket, json_encode($output));

          // $this->console("Ping from: [{$user->login}]", "dark_gray");

          continue;
        }

        if (($pos = strpos($action, "user ")) === 0 && ($user || !$loggedon)) {
          $name = substr($action, $pos + 5);

          $output = array('sendPass' => true);
          $this->send($socket, json_encode($output));

          $this->addUser($socket_index, $name);

          continue;
        }

        if (($pos = strpos($action, "pass ")) === 0) {
          $name = substr($action, $pos + 7);

          $output = array("logonOk" => true);

          $output["refreshInventory"] = $this->getAllThingsFrom($user);

          $this->send($socket, json_encode($output));
          // $this->addUser($socket_index, $name);
          unset($output);

          continue;
        }

        if ((strpos($action, "offer ")) === 0) {
          $thingUUID = substr($action, 6);

          $offer = offer::createNew($thingUUID);

          // $this->send($socket, json_encode($output));
          if ($offer instanceof offer) {
            $this->console("newOffer >> `{$offer->itemName}` from `{$offer->owner->login}`", "cyan");
            $this->offerToSend[] = $offer;

            $output = array();
            $output["offerAccepted"] = $offer;
            $this->send($socket, json_encode($output));
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
          $this->send($sock, json_encode($output));
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
          $output["newOffer"][] = $offer;
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

          // @todo verificar o jeito mais eficiente de descobrir o usuário amarrado no token
          $socket_index = array_search($sock, $this->allsockets);
          $user = $this->users[$socket_index];

          if (!$user->uuid) {
            continue;
          }

          $uniqOutput = $output;
          if ($timeToPick && $item = $this->pickItem()) {
            $uniqOutput["now"] = date("Y-m-d H:i:s");
            $uniqOutput["newItem"][] = $item;
            $this->console("newItem >> {$item["nm"]} to {$user->login}", "cyan");
            $a = array(
              "thingUUID" => "(INT)0x".str_replace("-", "", $item["uuid"])."(INT)",
              "itemUUID" => "(INT)0x".str_replace("-", "", $item["id"])."(INT)",
              "creationDTHR" => "(INT)NOW()(INT)",
              "ownerUUID" => "(INT)0x".str_replace("-", "", $user->uuid)."(INT)",
              "ownerDTHR" => "(INT)NOW()(INT)",
            );
            $this->con->execInsert($a, "tb_thing");
            if ($this->con->status === false) {
              echo "Pau\n";
              echo $this->con->getDescRetorno()."\n";
              echo $this->con->errno."-".$this->con->error."\n";
            }
            unset($a);
          }

          $str = json_encode($uniqOutput);
          $this->send($sock, $str);
        }
      }
    }
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
   * Parse the handshake header from the client
   *
   * @param string $req
   * @return array resource,host,origin
   */
  private function getheaders($req) {
    // $req  = substr($req,4); /* RegEx kill babies */
    // $res  = substr($req,0,strpos($req," HTTP"));
    // $req  = substr($req,strpos($req,"Host:")+6);
    // $host = substr($req,0,strpos($req,"\r\n"));
    // $req  = substr($req,strpos($req,"Sec-WebSocket-Key: ")+19);
    // $key  = trim(substr($req,0,strpos($req,"\r\n")));
    // $req  = substr($req,strpos($req,"Origin:")+8);
    // $ori  = substr($req,0,strpos($req,"\r\n"));

    $res  = preg_match("/GET (.*) HTTP/i"              , $req, $match) ? $match[1] : "???";
    $host = preg_match("/Host: (.*)\r\n/i"             , $req, $match) ? $match[1] : "???";
    $ori  = preg_match("/Origin: (.*)\r\n/i"           , $req, $match) ? $match[1] : "???";
    $key  = preg_match("/Sec-WebSocket-Key: (.*)\r\n/i", $req, $match) ? $match[1] : "???";

    return array($res, $host, $ori, $key);
  }

  /**
   * Manage the handshake procedure
   *
   * @param string $buffer The received stream to init the handshake
   * @param socket $socket The socket from which the data came
   * @param int $socket_index The socket index in the allsockets array
   */
  private function do_handshake($buffer, $socket, $socket_index) {
    list($resource, $host, $origin, $key) = $this->getheaders($buffer);

    $retkey = base64_encode(sha1($key.$this->wsUUID, true));

    $upgrade  = "HTTP/1.1 101 Switching Protocols\r\n".
                "Upgrade: websocket\r\n".
                "Connection: Upgrade\r\n".
                "Sec-WebSocket-Accept: {$retkey}\r\n\r\n";

    $this->handshakes[$socket_index] = true;

    socket_write($socket, $upgrade, strlen($upgrade));

    $this->console("Done handshaking...");
  }

  /**
   * Extends the socket class send method to send WebSocket messages
   *
   * @param socket $client The socket to which we send data
   * @param string $msg  The message we send
   */
  protected function send($client, $msg) {
    $msg = $this->encode2($msg);
    parent::send($client, $msg);
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

  private function unmask($payload) {
		$length = ord($payload[1]) & 127;
	 
		if($length == 126) {
			$masks = substr($payload, 4, 4);
			$data = substr($payload, 8);
			$len = (ord($payload[2]) << 8) + ord($payload[3]);
		}
		elseif($length == 127) {
			$masks = substr($payload, 10, 4);
			$data = substr($payload, 14);
			$len = (ord($payload[2]) << 56) + (ord($payload[3]) << 48) + (ord($payload[4]) << 40) + (ord($payload[5]) << 32) + (ord($payload[6]) << 24) + (ord($payload[7]) << 16) + (ord($payload[8]) << 8) + ord($payload[9]);
		}
		else {
			$masks = substr($payload, 2, 4);
			$data = substr($payload, 6);
			$len = $length;
		}
	 
		$text = '';
		for ($i = 0; $i < $len; ++$i) {
			$text .= $data[$i] ^ $masks[$i%4];
		}
		return $text;
	}
	
	/**
	 * Encode a text for sending to clients via ws://
	 * @param $text
	 */
	private function encode($text) {
		// 0x1 text frame (FIN + opcode)
		$b1 = 0x80 | (0x1 & 0x0f);
		$length = strlen($text);
		
		if ($length <= 125) {
      $header = pack('CC', $b1, $length);
      // echo "pack CC\n";
    } elseif ($length > 125 && $length < 65536) {
			$header = pack('CCS', $b1, 126, $length); //original
      // echo "pack CCS\n";
    } elseif($length >= 65536) {
      $header = pack('CCN', $b1, 127, $length);
      // echo "pack CCN\n";
    }
	 
		return $header.$text;
  }
  
	private function encode2($text) {
		$b = 129; // FIN + text frame
		$len = strlen($text);
		if ($len < 126) {
			return pack('CC', $b, $len) . $text;
		} elseif ($len < 65536) {
			return pack('CCn', $b, 126, $len) . $text;
		} else {
			return pack('CCNN', $b, 127, 0, $len) . $text;
		}
	}


  /**
   * Extends the parent console method.
   * For now we just set another type.
   *
   * @param string $msg
   * @param string $type
   */
  protected function console($msg, $fg = null, $bg = null) {
    parent::console($msg, "WebSocket", $fg, $bg);
  }

  protected function addUser($socket, $userName) {
    $user = new user($userName);
    if (!$user) {
      $this->console("User not recognized {$userName}", "white", "red");
      return false;
    }

    $this->users[$socket] = $user;
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
    $ret["i"] = array();
    $ret["t"] = array();

    $q = "SELECT HEX(a.thingUUID) AS uuid, HEX(a.itemUUID) AS id, b.itemName AS nm FROM tb_thing a LEFT JOIN tb_item b ON (a.itemUUID = b.itemUUID) WHERE a.ownerUUID = 0x{$user->uuid} ORDER BY nm";
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
      $this->con->result;
      $this->con->getFetchAssoc();
    }

    return $ret;
  }
}
?>