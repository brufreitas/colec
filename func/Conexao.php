<?php
/**
 * Interface de Conexão, Acesso e Manipulação de Dados direto com Banco de Dados
 *
 * @param boolean $status
 * @param string $errcod
 * @param string $errstr
 *
 * @param array $result Resultado de fetch_assoc da query
 * @param integer $count Quantidade de linhas encontradas
 * @param integer $rowsAffected Retorno das linhas afetadas nos comandos UPDATE / DELETE
 *
 * @param integer $errno Número do erro no SGBD
 * @param string $error Mensagem de erro no SGBD
 *
 * @param string $bd
 * @param string $host
 * @param string $pass
 * @param string $sgbd
 * @param string $user
 *
 * @param array $func
 *
 * @param boolean $is_connected Indicador de conexão estabelecida com o SGBD
 * @param boolean $close_connect Indicador para método destruct encerrar a conexão com o SGBD
 * @param resource $socket Recurso do link de conexão com o SGBD
 * @param resource $intquery Recurso de resultado da query
 * @param integer $index Índice do vetor de resultado
 *
 * @param string $query_desc
 * @param string $query_str
 * @param string $query_type
 *
 * @author David Chioqueti
 * @version 31/07/2014 - Eduardo Pereira
 */
class Conexao {
  public $status;
  public $errcod;
  public $errstr;
  public $result;
  public $count;
  public $rowsAffected;
  public $errno;
  public $error;

  protected $p_bd;
  protected $p_host;
  protected $p_pass;
  protected $p_sgbd;
  protected $p_user;

  protected $bd;
  protected $host;
  protected $pass;
  protected $sgbd;
  protected $user;

  protected $func = array();

  protected $is_connected = false;
  protected $close_connect = false;
  protected $socket;
  protected $intquery;
  public $index = 0;

  protected $query_desc;
  public $query_str;
  public $query_tempo;
  protected $query_type;

  private $_isEOF = true;

  protected static $_arr_bds;
  /**
   * Incrementa o contador de conexões ativas
   *
   * @since 27/06/2014
   * @author Eduardo Pereira
   * @version 27/06/2014 - Eduardo Pereira
   */
  protected function _incConn() {
    // if ($this->func["check_connect"]($this->socket) === true) {
    //   $GLOBALS["_arr_bds"][intval($this->socket)]["inst"]++;
    //   Conexao::$_arr_bds[intval($this->socket)]["inst"] = $GLOBALS["_arr_bds"][intval($this->socket)]["inst"];
    // }
  }
  /**
   * Decrementa o contador de conexões ativas
   *
   * @since 27/06/2014
   * @author Eduardo Pereira
   * @version 30/07/2014 - Eduardo Pereira
   */
  protected function _decConn() {
    // if ($this->func["check_connect"]($this->socket) === true) {
    //   if ($GLOBALS["_arr_bds"][intval($this->socket)]["inst"] > 0) {
    //     $GLOBALS["_arr_bds"][intval($this->socket)]["inst"]--;
    //   }
    //   Conexao::$_arr_bds[intval($this->socket)]["inst"] = $GLOBALS["_arr_bds"][intval($this->socket)]["inst"];
    // }
  }
  /**
   * @param mixed $host
   * @param string $bd
   * @param string $user
   * @param string $pass
   * @param string $sgbd
   * @return boolean
   *
   * @version 28/08/2014 - Bruno Freitas
   */
  public function __construct($host, $bd = "", $user = "", $pass = "", $sgbd = "MYSQL") {
    if ((is_resource($host) === true) && (get_resource_type($host) == "mysql link")) {

      if (!isset($GLOBALS["_arr_bds"][intval($host)]["host"])) {
        $this->errcod = "NO|15|ERCBD";
        $this->status = false;
        return false;
      }

      $this->p_sgbd = $GLOBALS["_arr_bds"][intval($host)]["server"];
      $this->p_host = $GLOBALS["_arr_bds"][intval($host)]["host"];
      $this->p_bd   = $GLOBALS["_arr_bds"][intval($host)]["name"];
      $this->p_pass = null;
      $this->p_user = $GLOBALS["_arr_bds"][intval($host)]["user"];

      $this->socket = $host;

      $this->is_connected = true;
    } else {
      $this->p_sgbd = $sgbd;
      $this->p_host = $host;
      $this->p_bd   = $bd;
      $this->p_pass = $pass;
      $this->p_user = $user;

      if (empty($this->p_bd) === true) {
        $this->errcod = "NO|01|ERCBD";
        $this->status = false;
        return false;
      }
      if (empty($this->p_user) === true) {
        $this->errcod = "NO|02|ERCBD";
        $this->status = false;
        return false;
      }
      if (empty($this->p_pass) === true) {
        $this->errcod = "NO|03|ERCBD";
        $this->status = false;
        return false;
      }
      if (empty($this->p_host) === true) {
        $this->p_host = DefineAmbiente::getIPBD();
        if (empty($this->p_host) === true) {
          $this->errcod = "NO|00|ERCBD";
          $this->status = false;
          return false;
        }
      }

      $this->is_connected = false;
    }

    if (strtoupper($this->p_sgbd) == "MYSQL") {
      if (function_exists("mysqli_connect") === true) {
        $this->func["connect"] = "mysqli_connect";
        $this->func["close"] = "mysqli_close";
        $this->func["select_db"] = "mysqli_select_db";
        $this->func["connect_errno"] = "mysqli_connect_errno";
        $this->func["connect_error"] = "mysqli_connect_error";
        $this->func["errno"] = "mysqli_errno";
        $this->func["error"] = "mysqli_error";
        $this->func["query"] = "mysqli_query";
        $this->func["fetch_assoc"] = "mysqli_fetch_assoc";
        $this->func["num_rows"] = "mysqli_num_rows";
        $this->func["affected_rows"] = "mysqli_affected_rows";
        $this->func["data_seek"] = "mysqli_data_seek";
        $this->func["insert_id"] = "mysqli_insert_id";

        $this->func["check_connect"] = "is_mysqli";
      } else {
        $this->errcod = "NO|11|ERCBD";
        $this->status = false;
        return false;
      }
      /*
      //06/01/2009 - (EDUARDO) - IMPOSSIBILIDADE DE REALIZAR TESTES
      } else if ($this->sgbd == "MSSQL") {
      if (function_exists("mssql_connect") === true) {
      $this->func["connect"] = "mssql_connect";
      $this->func["select_db"] = "mssql_select_db";
      //$this->func["errno"] = "mysql_errno";
      $this->func["error"] = "mssql_get_last_message";
      $this->func["query"] = "mssql_query";
      $this->func["fetch_assoc"] = "mssql_fetch_assoc";
      $this->func["num_rows"] = "mssql_num_rows";
      $this->func["affected_rows"] = "mssql_rows_affected";
      $this->func["data_seek"] = "mssql_data_seek";
      //$this->func["insert_id"] = "mysql_insert_id";

      $this->func["check_connect"] = "is_int";
      } else {
      $this->errcod = "NO|11|ERCBD";
      $this->status = false;
      return false;
      }
      */
    } else {
      $this->errcod = "NO|10|ERCBD";
      $this->status = false;
      return false;
    }

    $this->sgbd = $this->p_sgbd;
    $this->host = $this->p_host;
    $this->bd   = $this->p_bd;
    $this->user = $this->p_user;
    $this->pass = $this->p_pass;

    if ($this->is_connected === false) {
      $this->errcod = "OK|03|ERCBD";
    } else {
      $this->errcod = "OK|00|ERCBD";

      $this->_incConn();
    }
    $this->status = true;
    return true;
  }
  /**
   * Fecha a conexão com o banco de dados quando o objeto for destruído (somente se a conexão foi criada pela própria instância)
   *
   * @since 20/06/2014
   * @author Eduardo Pereira
   * @version 27/06/2014 - Eduardo Pereira
   */
  public function __destruct() {
    $this->close();
  }
  /**
   * Cria conexão com o banco
   *
   * @return boolean
   *
   * @version 27/06/2014 - Eduardo Pereira
   */
  public function connect() {
    $this->_isEOF = true;
    try {
      $this->socket = @$this->func["connect"]($this->host, $this->user, $this->pass, $this->bd);

      if ($this->socket === false) {
        if ($this->func["connect_error"]() == "") {
          throw new Exception("Não foi possível conectar com o BD porém ele não retornou a descrição do erro. Provavelmente o usuário/senha são inválidos.");
        } else {
          throw new Exception($this->func["connect_error"]());
        }
      }
    } catch (Exception $e) {
      if ($this->func["connect_errno"]()) {
        $this->errno = $this->func["connect_errno"]();
      } elseif (strpos($e->getMessage(), "Lost connection to MySQL server at ") !== false) {
        $this->errno = 2055;
      } else {
        $this->errno = 99999;
      }
      $this->error = $e->getMessage();

      $this->errcod = "NO|04|ERCBD";
      $this->status = false;
      return false;
    }

    // var_dump($this->socket);

    if ($this->func["check_connect"]($this->socket)) {
    //  echo "sim\n";

      // $sel_bd = @$this->func["select_db"]($this->bd, $this->socket);
      $sel_bd = true;


      if ($sel_bd === true) {
        $this->close_connect = true;
        // if (is_array($GLOBALS["_arr_bds"][intval($this->socket)]) === false) {
        //   $this->close_connect = true;

        //   $GLOBALS["_arr_bds"][intval($this->socket)] = array(
        //     "server" => $this->sgbd,
        //     "host" => $this->host,
        //     "name" => $this->bd,
        //     "user" => $this->user,
        //   );
        // }

        $this->_incConn();

        $this->is_connected = true;

        $this->errcod = "OK|00|ERCBD";
        $this->status = true;
        return true;
      } else {
        // printf("Connect failed: %s\n", mysqli_connect_error());
        // echo "Não2....";

        $this->errno = $this->func["connect_errno"]($this->socket);
        $this->error = $this->func["connect_error"]($this->socket);

        $this->errcod = "NO|05|ERCBD";
        $this->status = false;
        return false;
      }
    } else {
      // echo "Não....";
      $this->errcod = "NO|04|ERCBD";
      $this->status = false;
      return false;
    }
  }

  /**
   * Fecha a conexão com o Banco de dados, se não houver outra instância ativa
   *
   * @since 30/07/2014
   * @author Eduardo Pereira
   * @version 30/07/2014 - Eduardo Pereira
   */
  public function close() {
    if ($this->is_connected === true) {
      $this->_decConn();

      if ($this->close_connect === true) {//QG
        if ($this->func["check_connect"]($this->socket) === true) {
          // if (Conexao::$_arr_bds[intval($this->socket)]["inst"] == 0) {
            if ($this->func["close"]($this->socket)) {
              $this->is_connected = false;

              // unset($GLOBALS["_arr_bds"][intval($this->socket)]);
            }
          // }
        }
      }
    }
  }
  /**
   * Executa um Comando no Banco de Dados
   *
   * @param string $q
   * @return boolean
   *
   * @version 20/08/2014 - Eduardo Pereira
   */
  public function query($q, $arg2 = null, $arg3 = null, $arg4 = null) {
    if (($this->status === false) && (count($this->func) == 0)) {
      return false;
    }

    $this->_isEOF = true;
    $this->index = 0;
    $this->count = 0;
    $this->rowsAffected = 0;

    $this->errno = null;
    $this->error = null;

    $this->intquery = null;
    $this->result = null;

    $this->query_desc = null;
    $this->query_str = null;
    $this->query_type = null;
    $this->query_tempo = null;

    $q = trim($q);
    if (substr($q, 0, 2) == "/*") {
      $q = trim(substr($q, (strpos($q, "*/") + 2)));
    }

    if (empty($q) === true) {
      $this->errcod = "NO|06|ERCBD";
      $this->status = false;
      return false;
    }

    // //TRATAMENTO PARA DEBUG SQL
    // $arr_debug = debug_backtrace(FALSE);
    // // var_dump($arr_debug);

    // //TRATAMENTO PARA MÉTODOS FACILITADORES DA EXECUÇÃO DO COMANDO SQL
    // $nivel = 1;

    // if (is_array($arr_debug[$nivel]) === true) {
    //   if (($arr_debug[$nivel]["class"] == __CLASS__) && ($arr_debug[$nivel]["type"] == "->") && (preg_match("/^(execInsert|execUpdate|execInsDupUp)$/", $arr_debug[$nivel]["function"]))) {
    //     $nivel++;
    //   }
    // }

    // //TRATAMENTO PARA USO DE FUNÇÕES
    // if (is_array($arr_debug[$nivel]) === true) {
    //   if (empty($arr_debug[$nivel]["class"]) === false) {
    //     $funcao = $arr_debug[$nivel]["class"].$arr_debug[$nivel]["type"].$arr_debug[$nivel]["function"];
    //   } else {
    //     $funcao = $arr_debug[$nivel]["function"];
    //   }
    // }
    // if (preg_match("/^(require|include|require_once|include_once)$/", strtolower($funcao))) {
    //   $funcao = "";
    // }

    // //PROGRAMA QUE EFETUOU A CHAMADA DO MÉTODO QUERY
    // $nivel--;

    // $include = str_replace($_SERVER['DOCUMENT_ROOT'], "", $arr_debug[$nivel]["file"]);
    // $linha_include = $arr_debug[$nivel]["line"];

    // //PROGRAMA QUE EFETUOU A CHAMADA DA FUNÇÃO
    // $nivel = count($arr_debug) - 1;
    // while (is_array($arr_debug[$nivel]) === true) {
    //   if (preg_match("/^(require|include|require_once|include_once)$/", strtolower($arr_debug[$nivel]["function"]))) {
    //     $nivel--;
    //   } else {
    //     break;
    //   }
    // }

    // //PROGRAMA PRINCIPAL
    // $programa = str_replace($_SERVER['DOCUMENT_ROOT'], "", $arr_debug[$nivel]["file"]);
    // $linha = $arr_debug[$nivel]["line"];

    // unset($nivel);
    // unset($arr_debug);

    // if (($programa == $include) && ($linha == $linha_include)) {
    //   $include = "";
    //   $linha_include = 0;
    // }

    // //USUÁRIO
    // if (isset($_SESSION["session"])) {
    //   $usuario = $_SESSION["session"]["operador"];
    // } else {
    //   $usuario = "NoSession(".$_SERVER['REMOTE_ADDR'].")";
    // }

    // //TIPO DO COMANDO
    // $tipo_query = strtoupper(substr($q, 0, strpos($q, " ")));

    // //IDENTIFICADOR
    // $q_desc = "/*".$tipo_query." Prg=".$programa.":".$linha." Login=".$usuario." BD=".$this->bd;
    // if (empty($funcao) === false) {
    //   $q_desc .= " Funcao=".$funcao;
    // }
    // if (empty($include) === false) {
    //   $q_desc .= " Inc=".$include.":".$linha_include;
    // }
    // $q_desc .= "*/";

    if ($this->is_connected === false) {
      $this->connect();

      if ($this->status === false) {
        return false;
      }
    }

    if ($this->func["check_connect"]($this->socket) === false) {
      $this->errcod = "NO|04|ERCBD";
      return false;
    }

    $this->query_str = $q;
    // $this->query_type = $tipo_query;
    // $this->query_desc = $q_desc;

    //DEBUG - Insira TEMPORARIAMENTE abaixo código específico para visualizar todos comandos SQL executados por uma programa

    //EXECUÇÃO DO COMANADO SQL
    $mt_ini = microtime(true);
    // $this->intquery = $this->func["query"]($this->query_desc." ".$this->query_str, $this->socket);
    $this->intquery = $this->func["query"]($this->socket, $this->query_str);
    $mt_fim = microtime(true);
    $this->query_tempo = ($mt_fim - $mt_ini) * 1000;

    if (in_array("debug-sql", $GLOBALS["argv"])) {
      print "SQL: {$this->query_str}\n";
      printf("Duração: %.3fms\n", $this->query_tempo);
    }

    $this->errno = $this->func["errno"]($this->socket);
    $this->error = $this->func["error"]($this->socket);

    if ($this->intquery === false) {
      if (in_array("debug-sql", $GLOBALS["argv"])) {
        print "***SQL ERROR: {$this->errno}-{$this->error}\n";
      }

      if ((isset($_SESSION["session"]) === true) && ($_SESSION["session"]["print_sql"] != "N") && ($tipo_query != "EXPLAIN")) {
        $GLOBALS["_arr_querys"][] = array(
          "conexao" => get_class($this),
          "programa" => $programa,
          "linha" => $linha,
          "include" => $include,
          "linha_include" => $linha_include,
          "funcao" => $funcao,
          "tipo" => $tipo_query,
          "query" => $q,
          "tempo" => $tempo,
          "rows" => 0,
          "errno" => $this->errno,
          "error" => $this->error
        );
      }

      $this->errcod = "NO|07|ERCBD";
      $this->status = false;
      return false;
    }

    if ($this->intquery instanceof mysqli_result) {
      $this->count = $this->intquery->num_rows;
      if ($this->count > 0) {
        $this->_isEOF = false;
        $this->result = $this->func["fetch_assoc"]($this->intquery);
        $this->index = 0;
      } else {
        $this->_isEOF = true;
      }

      if (in_array("debug-sql", $GLOBALS["argv"])) {
        print "Encontrou {$this->count} regs\n";
      }

      if ((isset($_SESSION["session"]) === true) && ($_SESSION["session"]["print_sql"] != "N") && ($tipo_query != "EXPLAIN")) {
        $GLOBALS["_arr_querys"][] = array(
          "conexao" => get_class($this),
          "programa" => $programa,
          "linha" => $linha,
          "include" => $include,
          "linha_include" => $linha_include,
          "funcao" => $funcao,
          "tipo" => $tipo_query,
          "query" => $q,
          "tempo" => $this->tempo,
          "rows" => $this->count,
          "errno" => $this->errno,
          "error" => $this->error
        );
      }
    } else {
      $this->rowsAffected = $this->func["affected_rows"]($this->socket);
      if (in_array("debug-sql", $GLOBALS["argv"])) {
        print "Afetou {$this->rowsAffected} regs\n";
      }

      if ((isset($_SESSION["session"]) === true) && ($_SESSION["session"]["print_sql"] != "N") && ($tipo_query != "EXPLAIN")) {
        $GLOBALS["_arr_querys"][] = array(
          "conexao" => get_class($this),
          "programa" => $programa,
          "linha" => $linha,
          "include" => $include,
          "linha_include" => $linha_include,
          "funcao" => $funcao,
          "tipo" => $tipo_query,
          "query" => $q,
          "tempo" => $tempo,
          "rows" => $this->rowsAffected,
          "errno" => $this->errno,
          "error" => $this->error
        );
      }
    }

    if (isset($GLOBALS["debug_is_included"]) === true) {
      if ($GLOBALS["debug_is_included"] === true) {
        $GLOBALS["debug_qtd_query"  ]++;
        $GLOBALS["debug_tempo_query"] = $GLOBALS["debug_tempo_query"] + $tempo;
        if ($tempo > $GLOBALS["debug_tempo_query_maior"]) {
          $GLOBALS["debug_tempo_query_maior"] = $tempo;
          $GLOBALS["debug_query_query_maior"] = $this->query_str;
        }
      }
    }

    $this->errcod = "OK|01|ERCBD";
    $this->status = true;
    return true;
  }
  /**
   * GERA um comando INSERT no SGBD
   *
   * @param array $arr_campos Para Campos Inteiros ou Operações, No Valor Informar (INT)Operação(INT)
   * @param string $tabela
   * @return string
   *
   * @since 18/09/2013
   * @author Bruno Freitas
   * @version 18/09/2013 - Bruno Freitas
   */
  public function getInsertFromArray($arr_campos, $tabela) {
    $q = "";
    foreach ($arr_campos as $valor) {
      if (strtoupper(substr($valor, 0, 5)) == "(INT)" && strtoupper(substr($valor, -5)) == "(INT)") {
        $q .= substr($valor, 5, -5).", ";
      } else {
        $q .= "'".addslashes($valor)."', ";
      }
    }

    return "INSERT INTO ".$tabela." (".implode(", ", array_keys($arr_campos)).") VALUES (".substr($q, 0, -2).")";
  }
  /**
   * GERA um comando REPLACE no SGBD
   *
   * @param array $arr_campos Para Campos Inteiros ou Operações, No Valor Informar (INT)Operação(INT)
   * @param string $tabela
   * @return string
   *
   * @since 12/06/2015
   * @author Eduardo Pereira
   * @version 12/06/2015 - Eduardo Pereira
   */
  public function getReplaceFromArray($arr_campos, $tabela) {
    $q = "";
    foreach ($arr_campos as $valor) {
      if (strtoupper(substr($valor, 0, 5)) == "(INT)" && strtoupper(substr($valor, -5)) == "(INT)") {
        $q .= substr($valor, 5, -5).", ";
      } else {
        $q .= "'".addslashes($valor)."', ";
      }
    }

    return "REPLACE INTO ".$tabela." (".implode(", ", array_keys($arr_campos)).") VALUES (".substr($q, 0, -2).")";
  }
  /**
   * GERA um comando INSERT ON DUPLICATE KET UPDATE para o SGBD
   *
   * @param array $arr_campos Para Campos Inteiros ou Operações, No Valor Informar (INT)Operação(INT)
   * @param array $arr_campos_update Para Campos Inteiros ou Operações, No Valor Informar (INT)Operação(INT)
   * @param string $tabela
   * @return string
   *
   * @since 16/06/2014
   * @author Eduardo Pereira
   * @version 23/07/2014 - Eduardo Pereira
   */
  public function getInsertUpdateFromArray($arr_campos, $arr_campos_update, $tabela) {
    $campos = "";
    foreach ($arr_campos as $campo => $valor) {
      $campos .= $campo." = ";
      if (strtoupper(substr($valor, 0, 5)) == "(INT)" && strtoupper(substr($valor, -5)) == "(INT)") {
        $campos .= substr($valor, 5, -5).", ";
      } else {
        $campos .= "'".addslashes($valor)."', ";
      }
    }
    $campos = substr($campos, 0, -2);

    if ((is_array($arr_campos_update) === true) && (count($arr_campos_update) > 0)) {
      $campos_update = "";
      foreach ($arr_campos_update as $campo => $valor) {
        $campos_update .= $campo." = ";
        if (strtoupper(substr($valor, 0, 5)) == "(INT)" && strtoupper(substr($valor, -5)) == "(INT)") {
          $campos_update .= substr($valor, 5, -5).", ";
        } else {
          $campos_update .= "'".addslashes($valor)."', ";
        }
      }
      $campos_update = substr($campos_update, 0, -2);
    } else {
      $campos_update = $campos;
    }

    return "INSERT INTO ".$tabela." SET ".$campos. " ON DUPLICATE KEY UPDATE ".$campos_update;
  }
  /**
   * GERA um comando UPDATE no SGBD
   *
   * @param array $arr_campos Para Campos Inteiros ou Operações, No Valor Informar (INT)Operação(INT)
   * @param string $tabela
   * @param string $where Não informar cláusula WHERE
   * @return string
   *
   * @since 18/09/2013
   * @author Bruno Freitas
   * @version 18/09/2013 - Bruno Freitas
   */
  public function getUpdateFromArray($arr_campos, $tabela, $where) {
    $where = trim($where);
    if ($where != "") {
      if (strtoupper(substr($where, 0, 5)) != "WHERE") {
        $where = " WHERE ".$where;
      }
    }

    $q = "";
    foreach ($arr_campos as $campo => $valor) {
      $q .= $campo." = ";
      if (strtoupper(substr($valor, 0, 5)) == "(INT)" && strtoupper(substr($valor, -5)) == "(INT)") {
        $q .= substr($valor, 5, -5).", ";
      } else {
        $q .= "'".addslashes($valor)."', ";
      }
    }

    return "UPDATE ".$tabela." SET ".substr($q, 0, -2)." ".$where;
  }
  /**
   * EXECUTA um comando INSERT no SGBD
   *
   * @param array $arr_campos Para Campos Inteiros ou Operações, No Valor Informar (INT)Operação(INT)
   * @param string $tabela
   * @return boolean
   *
   * @since 06/01/2009
   * @author Eduardo Pereira
   * @version 27/06/2014 - Eduardo Pereira
   */
  public function execInsert($arr_campos, $tabela, $arg3 = null, $arg4 = null, $arg5 = null) {
    if (is_array($arr_campos) === false) {
      $this->errcod = "NO|13|ERCBD";
      $this->status = false;
      return false;
    }
    if (count($arr_campos) == 0) {
      $this->errcod = "NO|13|ERCBD";
      $this->status = false;
      return false;
    }

    $tabela = trim($tabela);
    if (empty($tabela) === true) {
      $this->errcod = "NO|12|ERCBD";
      $this->status = false;
      return false;
    }

    return $this->query($this->getInsertFromArray($arr_campos, $tabela));
  }
  /**
   * EXECUTA um comando REPLACE no SGBD
   *
   * @param array $arr_campos Para Campos Inteiros ou Operações, No Valor Informar (INT)Operação(INT)
   * @param string $tabela
   * @return boolean
   *
   * @since 12/06/2015
   * @author Eduardo Pereira
   * @version 12/06/2015 - Eduardo Pereira
   */
  public function execReplace($arr_campos, $tabela) {
    if (is_array($arr_campos) === false) {
      $this->errcod = "NO|13|ERCBD";
      $this->status = false;
      return false;
    }
    if (count($arr_campos) == 0) {
      $this->errcod = "NO|13|ERCBD";
      $this->status = false;
      return false;
    }

    $tabela = trim($tabela);
    if (empty($tabela) === true) {
      $this->errcod = "NO|12|ERCBD";
      $this->status = false;
      return false;
    }

    return $this->query($this->getReplaceFromArray($arr_campos, $tabela));
  }
  /**
   * Executa um Comando INSERT ON DUPLICATE KEY UPDATE no SGBD
   *
   * @param array $arr_campos Para Campos Inteiros ou Operações, No Valor Informar (INT)Operação(INT)
   * @param string $tabela
   * @param array $arr_campos_update Para Campos Inteiros ou Operações, No Valor Informar (INT)Operação(INT)
   * @return boolean
   *
   * @since 04/11/2010
   * @author Bruno Freitas
   * @version 27/06/2014 - Eduardo Pereira
   */
  public function execInsDupUp($arr_campos, $tabela, $arr_campos_update = "", $arg4 = null, $arg5 = null, $arg6 = null) {
    //QG-IMPLANTAÇÃO
    if (is_array($arr_campos_update) === false) {
      $arr_campos_update = "";
    }

    if (is_array($arr_campos) === false) {
      $this->errcod = "NO|13|ERCBD";
      $this->status = false;
      return false;
    }
    if (count($arr_campos) == 0) {
      $this->errcod = "NO|13|ERCBD";
      $this->status = false;
      return false;
    }

    $tabela = trim($tabela);
    if (empty($tabela) === true) {
      $this->errcod = "NO|12|ERCBD";
      $this->status = false;
      return false;
    }

    return $this->query($this->getInsertUpdateFromArray($arr_campos, $arr_campos_update, $tabela));
  }
  /**
   * Executa um Comando UPDATE no SGBD
   *
   * @param array $arr_campos Para Campos Inteiros ou Operações, No Valor Informar (INT)Operação(INT)
   * @param string $tabela
   * @param string $where Não informar cláusula WHERE
   * @return boolean
   *
   * @since 06/01/2009
   * @author Eduardo Pereira
   * @version 27/06/2014 - Eduardo Pereira
   */
  public function execUpdate($arr_campos, $tabela, $where, $arg4 = null, $arg5 = null, $arg6 = null) {
    if (is_array($arr_campos) === false) {
      $this->errcod = "NO|13|ERCBD";
      $this->status = false;
      return false;
    }
    if (count($arr_campos) == 0) {
      $this->errcod = "NO|13|ERCBD";
      $this->status = false;
      return false;
    }

    $tabela = trim($tabela);
    if (empty($tabela) === true) {
      $this->errcod = "NO|12|ERCBD";
      $this->status = false;
      return false;
    }

    $where = trim($where);
    if (empty($where) === true) {
      $this->errcod = "NO|14|ERCBD";
      $this->status = false;
      return false;
    }

    return $this->query($this->getUpdateFromArray($arr_campos, $tabela, $where));
  }

  public function execDelete($tabela, $where) {
    $tabela = trim($tabela);
    if (empty($tabela) === true) {
      $this->errcod = "NO|12|ERCBD";
      $this->status = false;
      return false;
    }

    $where = trim($where);
    if (empty($where) === true) {
      $this->errcod = "NO|14|ERCBD";
      $this->status = false;
      return false;
    }

    if (strtoupper(substr($where, 0, 5)) != "WHERE") {
      $where = " WHERE ".$where;
    }

    return $this->query("DELETE FROM {$tabela} {$where}");
  }

  /**
   * Efetua Movimentação do ponteiro do resultado
   *
   * @param integer $id
   * @return boolean
   *
   * @version 06/01/2009 - Eduardo Pereira
   */
  public function seek($id) {
    $this->_isEOF = true;

    if ($this->status === false) {
      return false;
    }

    if (!$this->func["check_connect"]($this->socket)) {
      $this->errcod = "NO|08|ERCBD";
      $this->status = false;
      return false;
    }

    if (!($this->intquery instanceof mysqli_result)) {
      $this->errcod = "NO|07|ERCBD";
      $this->status = false;
      return false;
    }

    if (!is_numeric($id)) {
      $id = 0;
    }

    $data_seek = @$this->func["data_seek"]($this->intquery, $id);
    $this->errno = $this->func["errno"]($this->socket);
    $this->error = $this->func["error"]($this->socket);

    if ($data_seek === true) {
      $this->result = $this->func["fetch_assoc"]($this->intquery);
      $this->index = $id;

      $this->errcod = "OK|02|ERCBD";
      $this->_isEOF = false;
      $this->status = true;
      return true;
    } else {
      $this->errcod = "NO|09|ERCBD";
      $this->status = false;
      return false;
    }
  }
  /**
   * Move o ponteiro do resultado para o primeiro registro
   *
   * @author David Chioqueti
   * @version 06/01/2009 - Eduardo Pereira
   */
  public function first() {
    //if ($this->index != 0) {
    //  O if foi comentado pois em situações que tem apenas 1 registro
    //não estava limpando o _isEOF
      $this->seek(0);
    //}
  }
  /**
   * Move o ponteiro do resultado para o último registro
   *
   * @version 05/01/2009 - Eduardo Pereira
   */
  public function last() {
    if ($this->index != ($this->count - 1)) {
      $this->seek($this->count - 1);
    }
  }
  /**
   * Move o ponteiro do resultado para o registro anterior
   *
   * @version 06/01/2009 - Eduardo Pereira
   */
  public function previous() {
    if (($this->index - 1) >= 0) {
      $this->seek($this->index - 1);
    }
  }
  /**
   * Move o ponteiro do resultado para o próximo registro
   *
   * @version 06/01/2009 - Eduardo Pereira
   */
  public function next() {
    if (($this->index + 1) < $this->count) {
      $this->seek($this->index + 1);
    }
  }
  /**
   * Retorna uma Matriz associativa do registro corrente, e move o cursor de registros para o próximo registro
   * Se o ponteio chegar ao fim dos registros retorna false ou um array equivalente a função fetch assoc
   *
   * @return mixed
   *
   * @author Eduardo Pereira
   * @since 06/01/2009
   */
  public function getFetchAssoc() {
    if ($this->status === false) {
      return false;
    }

    if ($this->func["check_connect"]($this->socket) === false) {
      $this->errcod = "NO|08|ERCBD";
      $this->status = false;
      return false;
    }

    if (!($this->intquery instanceof mysqli_result)) {
      $this->errcod = "NO|07|ERCBD";
      $this->status = false;
      return false;
    }

    if ($this->index < ($this->count - 1)) {
      $this->next();
      return $this->result;
    } else {
      $this->_isEOF = true;
      return false;
    }

  }
  /**
   * Retorna true se o recordset acabou ou false caso ainda existam registros
   *
   * @return boolean
   *
   * @author Bruno Freitas
   * @since 19/04/2012
   * @version 19/04/2012 - Bruno Freitas
   */
  public function isEOF() {
    return $this->_isEOF;
  }
  /**
   * Retorna o ID do último registro inserido
   *
   * @return integer
   *
   * @author Eduardo Pereira
   * @version 12/01/2009 - Eduardo Pereira
   */
  public function last_id() {
    if (($this->status === false) && (count($this->func) == 0)) {
      return 0;
    }
    if ($this->func["check_connect"]($this->socket) === true) {
      if ($this->query_type == "INSERT") {
        return $this->func["insert_id"]($this->socket);
      } else {
        return 0;
      }
    } else {
      return 0;
    }
  }
  /**
   * Retorna o índice atual do resultado obtido na consulta SQL
   *
   * @return mixed
   *
   * @author Eduardo Pereira
   * @since 23/10/2008
   */
  public function getIndex() {
    return $this->index;
  }
  /**
   * Retorna Informações sobre a conexão corrente
   *
   * @param boolean $as_array
   * @param boolean $as_html
   * @return mixed
   *
   * @author Eduardo Pereira
   * @since 05/11/2008
   *
   * @version 28/08/2014 - Bruno Freitas
   */
  public function getInfo($as_array = false, $as_html = false) {
    if ($as_array === true) {
      if ($this->sgbd == "" && $this->host == "" && $this->bd == "" && $this->user == "") {
        return array("server" => $this->p_sgbd, "host" => $this->p_host, "name" => $this->p_bd, "user" => $this->p_user);
      } else {
        return array("server" => $this->sgbd, "host" => $this->host, "name" => $this->bd, "user" => $this->user);
      }
    } else {
      if ($this->sgbd == "" && $this->host == "" && $this->bd == "" && $this->user == "") {
        if ($as_html === true) {
          return "<strong>Servidor:</strong> ".$this->p_host."&nbsp;&nbsp;<strong>Banco de dados:</strong> ".$this->p_bd."&nbsp;&nbsp;<strong>Usuário:</strong> ".$this->p_user;
        } else {
          return "SGBD: ".$this->p_sgbd.", Servidor: ".$this->p_host.", BD: ".$this->p_bd.", Usuário: ".$this->p_user;
        }
      } else {
        if ($as_html === true) {
          return "<strong>Servidor:</strong> ".$this->host."&nbsp;&nbsp;<strong>Banco de dados:</strong> ".$this->bd."&nbsp;&nbsp;<strong>Usuário:</strong> ".$this->user;
        } else {
          return "SGBD: ".$this->sgbd.", Servidor: ".$this->host.", BD: ".$this->bd.", Usuário: ".$this->user;
        }
      }
    }
  }
  /**
   * Retorna o código de retorno
   *
   * @return string
   *
   * @author Eduardo Pereira
   * @since 06/01/2009
   */
  public function getCodRetorno() {
    return $this->errcod;
  }
  /**
   * Retorna a descrição do código de retorno
   *
   * @return string
   *
   * @author Eduardo Pereira
   * @since 06/01/2009
   */
  public function getDescRetorno() {
    $arr = explode("|", $this->errcod);

    if ($arr[0] == "NO") {
      if ($arr[1] == "00") {
        return "Servidor inválido";
      } else if ($arr[1] == "01") {
        return "O nome da base de dados é nulo ou inválido";
      } else if ($arr[1] == "02") {
        return "Usuário inválido";
      } else if ($arr[1] == "03") {
        return "Senha inválida";
      } else if ($arr[1] == "04") {
        return "Ocorreu um erro ao tentar conectar (".$this->bd."-".$this->host.")";
      } else if ($arr[1] == "05") {
        return "Não foi possível acessar a base de dados (".$this->bd.")";
      } else if ($arr[1] == "06") {
        return "Comando SQL é vazio ou inválido";
      } else if ($arr[1] == "07") {
        return "Erro na execução do comando SQL (".$this->errno.") ".$this->error;
      } else if ($arr[1] == "08") {
        return "O recurso de conexão com a base de dados (".$this->bd.") não está disponível";
      } else if ($arr[1] == "09") {
        return "Ocorreu um erro na movimentação de registros (índice indicado está fora do range de resultado)";
      } else if ($arr[1] == "10") {
        return "Gerenciador de Banco de Dados É Nulo / Não Parametrizado (".$this->sgbd.")";
      } else if ($arr[1] == "11") {
        return "Funções do Gerenciador de Banco de Dados (".$this->sgbd.") não estão disponíveis no servidor (".trim(shell_exec("hostname -s")).")";
      } else if ($arr[1] == "12") {
        return "Não é possível executar comando, Tabela não informada";
      } else if ($arr[1] == "13") {
        return "Não é possível executar comando, Nenhum campo informado";
      } else if ($arr[1] == "14") {
        return "Não é possível executar comando, Nenhuma condição informada";
      } else if ($arr[1] == "15") {
        return "Não é possível criar objeto pois resource passado como parâmetro não foi criado por uma função padrão do IntergrAll";
      }
    } else {
      if ($arr[1] == "00") {
        return "Conexão estabelecida";
      } else if ($arr[1] == "01") {
        return "Comando SQL executado com sucesso";
      } else if ($arr[1] == "02") {
        return "Movimentação de resultado efetuada";
      } else if ($arr[1] == "03") {
        return "Objeto Iniciado";
      }
    }
  }
  /**
   * Retorna Hora do Banco de Dados Principal (DAC)
   *
   * @param string $msg
   * @return mixed
   *
   * @author Eduardo Pereira
   * @since 10/06/2014
   */
  public static function getDateTimeBD(&$msg = null) {
    static $arr;

    if (is_null($arr) === true) {
      require_once($_SERVER["DOCUMENT_ROOT"]."/libs/bd/ConexaoRamal.php");

      $conn = new ConexaoRamal();
      $conn->query("SELECT NOW()");
      if ($conn->status === false) {
        $msg = "[".__METHOD__."] Não foi possível obter a hora ´".$conn->getDescRetorno()."´";
        unset($conn);

        return false;
      }
      $arr["timestamp"] = strtotime($conn->result["NOW()"]);
      $arr["data_hora"] = date("Y-m-d H:i:s", $arr["timestamp"]);
      $arr["data"] = date("Y-m-d", $arr["timestamp"]);
      $arr["hora"] = date("H:i:s", $arr["timestamp"]);
      $arr["milisegundos"] = intval(microtime() * 1000);

      unset($conn);
    }

    return $arr;
  }
}

function is_mysqli($link) {
  return ($link instanceof mysqli);
}
?>