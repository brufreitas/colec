<?php
/**
 * Socket class of phpWebSockets
 *
 * @author Moritz Wutz <moritzwutz@gmail.com>
 * @version 0.1
 * @package phpWebSockets
 */

/**
 * This is the main socket class
 */
class socket
{
  /**
   *
   * @master socket Holds the master socket
   */
  protected $master;
  /**
   *
   * @allsockets array Holds all connected sockets
   */
  protected $allsockets = array();
  protected $consoleType = "bash";

  public function __construct($host, $port) {
    $this->createSocket($host, $port);
  }

  /**
   * Create a socket on given host/port
   * @param string $host The host/bind address to use
   * @param int $port The actual port to bind on
   */
  private function createSocket($host, $port) {
    if (!$this->master = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) {
      die("socket_create() failed, reason: ".socket_strerror($this->master));
    }

    self::console("Socket [{$this->master}] created.");

    socket_set_option($this->master, SOL_SOCKET, SO_REUSEADDR, 1);

    if (!@socket_bind($this->master, $host, $port)) {
      self::console("socket_bind() failed, reason: [".socket_strerror(socket_last_error($this->master))."]", "System", "white", "red");
      exit;
    }

    self::console("Socket bound to [{$host}:{$port}].");

    if ( ($ret = socket_listen($this->master,5)) < 0 ) {
      die("socket_listen() failed, reason: ".socket_strerror($ret));
    }

    self::console('Start listening on Socket.');

    $this->allsockets[] = $this->master;
  }

  /**
   * Log a message
   * @param string $msg The message
   * @param string $type The type of the message
   */
  protected function console($msg, $type = 'System', $fg = null, $bg = null) {
    if ($consoleType = "bash") {

      $fgCode = bash_fgColorCode($fg);
      $bgCode = bash_bgColorCode($bg);

      $msg = 
        "\033[{$fgCode}m".
        "\033[{$bgCode}m".
        $msg.
        "\033[0m";
    }
    print date("Y-m-d H:i:s") . " {$type}: {$msg}\n";
    // $msg = explode("\n", $msg);
    // foreach( $msg as $line ) {
    // print date('Y-m-d H:i:s') . " {$type}: {$msg}\n";
    // }
  }

	/**
	 * Sends a message over the socket
	 * @param socket $client The destination socket
	 * @param string $msg The message
	 */
  protected function send($client, $msg) {
    socket_write($client, $msg, strlen($msg));
  }
}

function bash_fgColorCode($colorName) {
  if     (!$colorName                 ) {return "0;37";}
  elseif ($colorName == "dark_gray"   ) {return "1;30";}
  elseif ($colorName == "blue"        ) {return "0;34";}
  elseif ($colorName == "light_blue"  ) {return "1;34";}
  elseif ($colorName == "green"       ) {return "0;32";}
  elseif ($colorName == "light_green" ) {return "1;32";}
  elseif ($colorName == "cyan"        ) {return "0;36";}
  elseif ($colorName == "light_cyan"  ) {return "1;36";}
  elseif ($colorName == "red"         ) {return "0;31";}
  elseif ($colorName == "light_red"   ) {return "1;31";}
  elseif ($colorName == "purple"      ) {return "0;35";}
  elseif ($colorName == "light_purple") {return "1;35";}
  elseif ($colorName == "brown"       ) {return "0;33";}
  elseif ($colorName == "yellow"      ) {return "1;33";}
  elseif ($colorName == "light_gray"  ) {return "0;37";}
  elseif ($colorName == "black"       ) {return "0;30";}
  elseif ($colorName == "white"       ) {return "1;37";}
  else                                  {return "0;37";}
}

function bash_bgColorCode($colorName) {
  if     (!$colorName                 ) {return "40";}
  elseif ($colorName == "black"       ) {return "40";}
  elseif ($colorName == "red"         ) {return "41";}
  elseif ($colorName == "green"       ) {return "42";}
  elseif ($colorName == "yellow"      ) {return "43";}
  elseif ($colorName == "blue"        ) {return "44";}
  elseif ($colorName == "magenta"     ) {return "45";}
  elseif ($colorName == "cyan"        ) {return "46";}
  elseif ($colorName == "light_gray"  ) {return "47";}
  else                                  {return "40";}
}
?>