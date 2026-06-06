<?php



namespace raklib\server;


use pocketmine\sleeper\SleeperNotifier;
use raklib\RakLib;
use function array_reverse;
use function error_reporting;
use function function_exists;
use function gc_enable;
use function get_class;
use function getcwd;
use function gettype;
use function ini_set;
use function is_object;
use function method_exists;
use function mt_rand;
use function preg_replace;
use function realpath;
use function register_shutdown_function;
use function set_error_handler;
use function str_replace;
use function strval;
use function substr;
use function trim;
use function xdebug_get_function_stack;
use const DIRECTORY_SEPARATOR;
use const E_ALL;
use const E_COMPILE_ERROR;
use const E_COMPILE_WARNING;
use const E_CORE_ERROR;
use const E_CORE_WARNING;
use const E_DEPRECATED;
use const E_ERROR;
use const E_NOTICE;
use const E_PARSE;
use const E_RECOVERABLE_ERROR;
use const E_STRICT;
use const E_USER_DEPRECATED;
use const E_USER_ERROR;
use const E_USER_NOTICE;
use const E_USER_WARNING;
use const E_WARNING;
use const PHP_INT_MAX;

class RakLibServer extends \Thread{
	protected $port;
	protected $interface;
	/** @var \ThreadedLogger */
	protected $logger;

	/** @var string */
	protected $loaderPath;

	/** @var bool */
	protected $shutdown;

	/** @var \Threaded */
	protected $externalQueue;
	/** @var \Threaded */
	protected $internalQueue;

	protected $mainPath;

	/** @var int */
	protected $serverId = 0;

	/** @var int */
	private $protocolVersion;

	/** @var SleeperNotifier */
	private $sleeper;

	/**
	 * @param \ThreadedLogger $logger
	 * @param string          $autoloaderPath Path to Composer autoloader
	 * @param int             $port
	 * @param string          $interface
	 *
	 * @throws \Exception
	 */
	public function __construct(\ThreadedLogger $logger, $autoloaderPath, $port, $interface = "0.0.0.0", bool $autoStart = true, int $overrideProtocolVersion = null, SleeperNotifier $sleeper = null){
		$this->port = (int) $port;
		if($port < 1 or $port > 65536){
			throw new \Exception("Invalid port range");
		}

		$this->interface = $interface;

		$this->serverId = mt_rand(0, PHP_INT_MAX);

		$this->logger = $logger;
		$this->loaderPath = $autoloaderPath;
		$this->shutdown = false;

		$this->externalQueue = new \Threaded;
		$this->internalQueue = new \Threaded;

		$this->protocolVersion = $overrideProtocolVersion ?? RakLib::PROTOCOL;

		$this->mainThreadNotifier = $sleeper;

		if(\Phar::running(true) !== ""){
			$this->mainPath = \Phar::running(true);
		}else{
			$this->mainPath = \realpath(\getcwd()) . DIRECTORY_SEPARATOR;
		}
		
		if($autoStart){
			$this->start();
		}
	}

	public function isShutdown(){
		return $this->shutdown === true;
	}

	public function shutdown(){
		$this->shutdown = true;
	}

	public function getPort(){
		return $this->port;
	}

	public function getInterface(){
		return $this->interface;
	}

	/**
	 * Returns the RakNet server ID
	 * @return int
	 */
	public function getServerId() : int{
		return $this->serverId;
	}

	public function getProtocolVersion() : int{
		return $this->protocolVersion;
	}

	/**
	 * @return \ThreadedLogger
	 */
	public function getLogger(){
		return $this->logger;
	}

	/**
	 * @return \Threaded
	 */
	public function getExternalQueue(){
		return $this->externalQueue;
	}

	/**
	 * @return \Threaded
	 */
	public function getInternalQueue(){
		return $this->internalQueue;
	}

	public function pushMainToThreadPacket($str){
		$this->internalQueue[] = $str;
	}

	public function readMainToThreadPacket(){
		return $this->internalQueue->shift();
	}

	public function pushThreadToMainPacket($str){
		$this->externalQueue[] = $str;
		if($this->mainThreadNotifier !== null){
			$this->mainThreadNotifier->wakeupSleeper();
		}
	}

	public function readThreadToMainPacket(){
		return $this->externalQueue->shift();
	}

	public function shutdownHandler(){
		if($this->shutdown !== true){
			$this->getLogger()->emergency("RakLib crashed!");
		}
	}

	public function errorHandler($errno, $errstr, $errfile, $errline){
		if(error_reporting() === 0){
			return false;
		}
        
        if(strpos($errstr, 'unpack()') !== false && strpos($errstr, 'not enough input') !== false){
            return true; 
        }
        
        if(strpos($errstr, 'Uninitialized string offset') !== false && strpos($errfile, 'EncapsulatedPacket') !== false){
            return true; 
        }
		
		$errorConversion = [
			E_ERROR => "E_ERROR",
			E_WARNING => "E_WARNING",
			E_PARSE => "E_PARSE",
			E_NOTICE => "E_NOTICE",
			E_CORE_ERROR => "E_CORE_ERROR",
			E_CORE_WARNING => "E_CORE_WARNING",
			E_COMPILE_ERROR => "E_COMPILE_ERROR",
			E_COMPILE_WARNING => "E_COMPILE_WARNING",
			E_USER_ERROR => "E_USER_ERROR",
			E_USER_WARNING => "E_USER_WARNING",
			E_USER_NOTICE => "E_USER_NOTICE",
			E_STRICT => "E_STRICT",
			E_RECOVERABLE_ERROR => "E_RECOVERABLE_ERROR",
			E_DEPRECATED => "E_DEPRECATED",
			E_USER_DEPRECATED => "E_USER_DEPRECATED"
		];
		$errno = $errorConversion[$errno] ?? $errno;

		$errstr = preg_replace('/\s+/', ' ', trim($errstr));
		$errfile = $this->cleanPath($errfile);

		$this->getLogger()->debug("An $errno error happened: \"$errstr\" in \"$errfile\" at line $errline");

		foreach(($trace = $this->getTrace(2)) as $i => $line){
			$this->getLogger()->debug($line);
		}

		return true;
	}

	public function getTrace($start = 0, $trace = null){
		if($trace === null){
			if(function_exists("xdebug_get_function_stack")){
				$trace = array_reverse(xdebug_get_function_stack());
			}else{
				$e = new \Exception();
				$trace = $e->getTrace();
			}
		}

		$messages = [];
		$j = 0;
		for($i = (int) $start; isset($trace[$i]); ++$i, ++$j){
			$params = "";
			if(isset($trace[$i]["args"]) or isset($trace[$i]["params"])){
				if(isset($trace[$i]["args"])){
					$args = $trace[$i]["args"];
				}else{
					$args = $trace[$i]["params"];
				}
				foreach($args as $name => $value){
					$params .= (is_object($value) ? get_class($value) . " " . (method_exists($value, "__toString") ? $value->__toString() : "object") : gettype($value) . " " . @strval($value)) . ", ";
				}
			}
			$messages[] = "#$j " . (isset($trace[$i]["file"]) ? $this->cleanPath($trace[$i]["file"]) : "") . "(" . (isset($trace[$i]["line"]) ? $trace[$i]["line"] : "") . "): " . (isset($trace[$i]["class"]) ? $trace[$i]["class"] . (($trace[$i]["type"] === "dynamic" or $trace[$i]["type"] === "->") ? "->" : "::") : "") . $trace[$i]["function"] . "(" . substr($params, 0, -2) . ")";
		}

		return $messages;
	}

	public function cleanPath($path){
		return str_replace(["\\", ".php", "phar://", str_replace(["\\", "phar://"], ["/", ""], $this->mainPath)], ["/", "", "", ""], $path);
	}

	public function run(){
		try{
			$this->loaderPath->register(true);

			gc_enable();
			error_reporting(-1);
			ini_set("display_errors", 1);
			ini_set("display_startup_errors", 1);

			set_error_handler([$this, "errorHandler"], E_ALL);
			register_shutdown_function([$this, "shutdownHandler"]);


			$socket = new UDPServerSocket($this->getLogger(), $this->port, $this->interface);
			new SessionManager($this, $socket);
		}catch(\Throwable $e){
			$this->logger->logException($e);
		}
	}

}
