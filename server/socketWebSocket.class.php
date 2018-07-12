<?php
/**
 * WebSocket extension class of phpWebSockets
 *
 * @author Moritz Wutz <moritzwutz@gmail.com>
 * @version 0.1
 * @package phpWebSockets
 */

require_once("server/socket.class.php");


class socketWebSocket extends socket
{
  // private $clients = array();
  private $handshakes = array();
  private $wsUUID = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11";

  private $on_webSocketConnect = array();              // $socket_index
  private $on_webSocketmessageReceived = array();      // $socket_index, $message_string


  public function __construct() {

    $this->console("socketWebSocket contruct", "green");

    parent::on("clientConnect", array($this, "openConnection"));
    parent::on("clientDisconnect", array($this, "closeConnection"));
    parent::on("messageReceived", array($this, "messageReceived"));
  }

  public function on($event, $function) {
    $this->console("websocket".__FUNCTION__."/Binding event: " . $event, "green");

    if (strtoupper($event) == "WEBSOCKETCONNECT") {
      $this->on_webSocketConnect[] = $function;
    } elseif (strtoupper($event) == "MESSAGERECEIVED") {
      $this->on_webSocketmessageReceived[] = $function;
    } else {
      parent::on($event, $function);
    }

  }

  /**
   * Parse the handshake header from the client
   *
   * @param string $req
   * @return array resource,host,origin
   */
  private function getheaders($req) {
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
   * @param int $socket_index The socket index in the allsockets array
   */
  private function do_handshake($buffer, $socket_index) {
    list($resource, $host, $origin, $key) = $this->getheaders($buffer);

    $retkey = base64_encode(sha1($key.$this->wsUUID, true));

    $upgrade  = "HTTP/1.1 101 Switching Protocols\r\n".
                "Upgrade: websocket\r\n".
                "Connection: Upgrade\r\n".
                "Sec-WebSocket-Accept: {$retkey}\r\n\r\n";

    $this->handshakes[$socket_index] = true;

    parent::sendByIndex($socket_index, $upgrade);

    $this->console("Done handshaking...");
  }

  final protected function messageReceived($socket_index, $rawData) {
    // $this->console(__CLASS__."->".__FUNCTION__." socket: `{$socket_index}`", "yellow");

    // this is a new connection, no handshake yet
    if (!isset($this->handshakes[$socket_index])) {
      $this->do_handshake($rawData, $socket_index);

      foreach($this->on_webSocketConnect as $func) {
        call_user_func($func, $socket_index);
      }

      return true;
    }

    $unmaskedData = $this->unmask($rawData);
    // $this->console(__CLASS__."->".__FUNCTION__." -- unmaskedData: `{$unmaskedData}`", "yellow");

    //Browser refresh / close
    if ($unmaskedData == chr(3).chr(233)) {
      $this->console("Browser refresh / close on socket: `{$socket_index}`", "red");
      $this->closeConnection($socket_index);
      return true;
    }

    foreach($this->on_webSocketmessageReceived as $func) {
      // $this->console(__CLASS__."->".__FUNCTION__." vai executar on_webSocketmessageReceived", "yellow");
      call_user_func($func, $socket_index, $unmaskedData);
    }

    return true;
  }

  /**
   * Extends the socket class sendByIndex method to send WebSocket messages
   *
   * @param int $socket_index The socket to send the data
   * @param string $msg  The message we send
   */
  protected function sendByIndex($socket_index, $msg) {
    parent::sendByIndex($socket_index, $this->encode2($msg));
  }

  /**
   * Extends the socket class sendToOthers method to send WebSocket messages
   *
   * @param int $socket_index_me The index of the socket that will not receive the data
   * @param string $msg The message that will be send
   */
  protected function sendToOthers($socket_index_me, $msg) {
    parent::sendToOthers($socket_index_me, $this->encode2($msg));
  }

  /**
   * Extends the socket class sendToAll method to send WebSocket messages
   *
   * @param string $msg The message that will be send
   */
  protected function sendToAll($msg) {
    parent::sendToAll($this->encode2($msg));
  }


  protected function openConnection($socket_index) {
    $this->console("WebSocket++ [{$socket_index}]", "green");

    if ($socket_index >= 0) {
      unset($this->handshakes[$socket_index]);
    }
  }

  /**
   * Disconnects a socket an delete all related data
   *
   * @param socket $socket The socket to disconnect
   */
  protected function closeConnection($socket_index) {
    if (!isset($this->handshakes[$socket_index])) {
      return true;
    }

    $this->console("WebSocket-- [{$socket_index}]", "light_red");

    if ($socket_index >= 0) {
      unset($this->handshakes[$socket_index]);
    }
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
}
?>