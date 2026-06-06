<?php

namespace raklib\server;

use raklib\Binary;
use raklib\protocol\ACK;
use raklib\protocol\AdvertiseSystem;
use raklib\protocol\Datagram;
use raklib\protocol\EncapsulatedPacket;
use raklib\protocol\NACK;
use raklib\protocol\OfflineMessage;
use raklib\protocol\OpenConnectionReply1;
use raklib\protocol\OpenConnectionReply2;
use raklib\protocol\OpenConnectionRequest1;
use raklib\protocol\OpenConnectionRequest2;
use raklib\protocol\Packet;
use raklib\protocol\UnconnectedPing;
use raklib\protocol\UnconnectedPingOpenConnections;
use raklib\protocol\UnconnectedPong;
use raklib\RakLib;

use function asort;
use function bin2hex;
use function chr;
use function count;
use function dechex;
use function get_class;
use function max;
use function microtime;
use function ord;
use function serialize;
use function socket_strerror;
use function strlen;
use function substr;
use function time;
use function time_sleep_until;
use function trim;
use const PHP_INT_MAX;
use const SOCKET_ECONNRESET;
use const SOCKET_EWOULDBLOCK;

class SessionManager{

	const RAKLIB_TPS = 100;
	const RAKLIB_TIME_PER_TICK = 1 / self::RAKLIB_TPS;
	
	/** @var \SplFixedArray<Packet|null> */
	protected $packetPool;

	/** @var RakLibServer */
	protected $server;

	protected $socket;

	protected $receiveBytes = 0;
	protected $sendBytes = 0;

	/** @var Session[] */
	protected $sessions = [];

	/** @var OfflineMessageHandler */
	protected $offlineMessageHandler;

	protected $name = "";

	protected $packetLimit = 200;

	protected $shutdown = false;

	protected $ticks = 0;
	protected $lastMeasure;

	protected $block = [];
	protected $ipSec = [];

	public $portChecking = false;

	/** @var int */
	protected $startTimeMS;

	public function __construct(RakLibServer $server, UDPServerSocket $socket){
		$this->server = $server;
		$this->socket = $socket;

		$this->startTimeMS = (int) (microtime(true) * 1000);

		$this->offlineMessageHandler = new OfflineMessageHandler($this);

		$this->registerPackets();

		$this->run();
	}

	/**
	 * Returns the time in milliseconds since server start.
	 * @return int
	 */
	public function getRakNetTimeMS() : int{
		return ((int) (microtime(true) * 1000)) - $this->startTimeMS;
	}

	public function getPort(){
		return $this->server->getPort();
	}

	public function getProtocolVersion() : int{
		return $this->server->getProtocolVersion();
	}

	public function getLogger(){
		return $this->server->getLogger();
	}

	public function run(){
		$this->tickProcessor();
	}

	private function tickProcessor(){
		$this->lastMeasure = microtime(true);
		while(!$this->shutdown){
			$start = microtime(true);
			
			do{
				$stream = true;
				for($i = 0; $i < 100 && $stream && !$this->shutdown; ++$i){
					$stream = $this->receiveStream();
				}

				$socket = true;
				for($i = 0; $i < 100 && $socket && !$this->shutdown; ++$i){
					$socket = $this->receivePacket();
				}
			}while(!$this->shutdown && ($stream || $socket));

			$this->tick();

			$time = microtime(true) - $start;
			if($time < self::RAKLIB_TIME_PER_TICK){
				@time_sleep_until(microtime(true) + self::RAKLIB_TIME_PER_TICK - $time);
			}
		}
	}

	private function tick(){
		$time = microtime(true);
		foreach($this->sessions as $session){
			$session->update($time);
		}

		$this->ipSec = [];

		if(($this->ticks % self::RAKLIB_TPS * 10) === 0){
			$diff = max(0.005, $time - $this->lastMeasure);
			$this->streamOption("bandwidth", serialize([
				"up" => $this->sendBytes / $diff,
				"down" => $this->receiveBytes / $diff
			]));
			$this->lastMeasure = $time;
			$this->sendBytes = 0;
			$this->receiveBytes = 0;

			if(count($this->block) > 0){
				asort($this->block);
				$now = microtime(true);
				foreach($this->block as $address => $timeout){
					if($timeout <= $now){
						unset($this->block[$address]);
					}else{
						break;
					}
				}
			}
		}

		++$this->ticks;
	}

	private function receivePacket(){
		$len = $this->socket->readPacket($buffer, $source, $port);
		    if($len === false){
				$error = $this->socket->getLastError();
				if($error === SOCKET_EWOULDBLOCK){
					return false;
				}elseif($error === SOCKET_ECONNRESET){
					return true;
				}
	
				$this->getLogger()->debug('Socket error occurred while trying to recv ('.$error.'): ' . trim(socket_strerror($error)));
				return false;
			}	
			
			$this->receiveBytes += $len;
			if(isset($this->block[$source])){
				return true;
			}
	
			if(isset($this->ipSec[$source])){
				if(++$this->ipSec[$source] >= $this->packetLimit){
					$this->blockAddress($source, 300, 'packet-limit');
					return true;
				}
			}else{
				$this->ipSec[$source] = 1;
			}

			if($len < 1){
				return true;
			}

			try{
				$pid = ord($buffer{0});

				$session = $this->getSession($source, $port);
				if($session !== null){
					if(($pid & Datagram::BITFLAG_VALID) !== 0){
						if($pid & Datagram::BITFLAG_ACK){
							$session->handlePacket(new ACK($buffer));
						}elseif($pid & Datagram::BITFLAG_NAK){
							$session->handlePacket(new NACK($buffer));
						}else{
							$session->handlePacket(new Datagram($buffer));
						}
					}else{
						$this->server->getLogger()->debug("Ignored unconnected packet from $source $port due to session already opened (0x" . dechex($pid) . ")");
					}
				}elseif(($pk = $this->getPacketFromPool($pid, $buffer)) instanceof OfflineMessage){
					/** @var OfflineMessage $pk */
					$pk->decode();
					if($pk->isValid()){
						if(!$this->offlineMessageHandler->handle($pk, $source, $port)){
							$this->server->getLogger()->debug("Unhandled unconnected packet " . get_class($pk) . " received from $source $port");
						}
					}else{
						$this->server->getLogger()->debug("Received garbage message from $source $port: " . bin2hex($pk->buffer));
					}
				}elseif(($pid & Datagram::BITFLAG_VALID) !== 0 and ($pid & 0x03) === 0){
					// Loose datagram, don't relay it as a raw packet
					// RakNet does not currently use the 0x02 or 0x01 bitflags on any datagram header, so we can use
					// this to identify the difference between loose datagrams and packets like Query.
					//$this->server->getLogger()->debug("Ignored connected packet from $source $port due to no session opened (0x" . dechex($pid) . ")");
				}else{
					$this->streamRaw($source, $port, $buffer);
				}
			}catch(\Throwable $e){
				$logger = $this->getLogger();
				$logger->debug("Packet from $source $port (" . strlen($buffer) . " bytes): 0x" . bin2hex($buffer));
				$logger->logException($e);
				$this->blockAddress($source, 5);
			}

		return true;
	}

	public function sendPacket(Packet $packet, $dest, $port){
		$packet->encode();
		$this->sendBytes += $this->socket->writePacket($packet->buffer, $dest, $port);
	}

	public function streamEncapsulated(Session $session, EncapsulatedPacket $packet, $flags = RakLib::PRIORITY_NORMAL){
		$id = $session->getAddress() . ":" . $session->getPort();
		$buffer = chr(RakLib::PACKET_ENCAPSULATED) . chr(strlen($id)) . $id . chr($flags) . $packet->toBinary(true);
		$this->server->pushThreadToMainPacket($buffer);
	}

	public function streamRaw($address, $port, $payload){
		$buffer = chr(RakLib::PACKET_RAW) . chr(strlen($address)) . $address . Binary::writeShort($port) . $payload;
		$this->server->pushThreadToMainPacket($buffer);
	}

	protected function streamClose($identifier, $reason){
		$buffer = chr(RakLib::PACKET_CLOSE_SESSION) . chr(strlen($identifier)) . $identifier . chr(strlen($reason)) . $reason;
		$this->server->pushThreadToMainPacket($buffer);
	}

	protected function streamInvalid($identifier){
		$buffer = chr(RakLib::PACKET_INVALID_SESSION) . chr(strlen($identifier)) . $identifier;
		$this->server->pushThreadToMainPacket($buffer);
	}

	protected function streamOpen(Session $session){
		$identifier = $session->getAddress() . ":" . $session->getPort();
		$buffer = chr(RakLib::PACKET_OPEN_SESSION) . chr(strlen($identifier)) . $identifier . chr(strlen($session->getAddress())) . $session->getAddress() . Binary::writeShort($session->getPort()) . Binary::writeLong($session->getID());
		$this->server->pushThreadToMainPacket($buffer);
	}

	protected function streamACK($identifier, $identifierACK){
		$buffer = chr(RakLib::PACKET_ACK_NOTIFICATION) . chr(strlen($identifier)) . $identifier . Binary::writeInt($identifierACK);
		$this->server->pushThreadToMainPacket($buffer);
	}

	protected function streamOption($name, $value){
		$buffer = chr(RakLib::PACKET_SET_OPTION) . chr(strlen($name)) . $name . $value;
		$this->server->pushThreadToMainPacket($buffer);
	}

	public function streamPingMeasure(Session $session, $pingMS){
		$identifier = $session->getAddress() . ":" . $session->getPort();
		$buffer = chr(RakLib::PACKET_REPORT_PING) . chr(strlen($identifier)) . $identifier . Binary::writeInt($pingMS);
		$this->server->pushThreadToMainPacket($buffer);
	}

	public function receiveStream(){
		if(strlen($packet = $this->server->readMainToThreadPacket()) > 0){
			$id = ord($packet{0});
			$offset = 1;
			if($id === RakLib::PACKET_ENCAPSULATED){
				$len = ord($packet{$offset++});
				$identifier = substr($packet, $offset, $len);
				$offset += $len;
				$session = $this->sessions[$identifier] ?? null;
				if($session !== null and $session->isConnected()){
					$flags = ord($packet{$offset++});
					$buffer = substr($packet, $offset);
					$session->addEncapsulatedToQueue(EncapsulatedPacket::fromBinary($buffer, true), $flags);
				}else{
					$this->streamInvalid($identifier);
				}
			}elseif($id === RakLib::PACKET_RAW){
				$len = ord($packet{$offset++});
				$address = substr($packet, $offset, $len);
				$offset += $len;
				$port = Binary::readShort(substr($packet, $offset, 2));
				$offset += 2;
				$payload = substr($packet, $offset);
				$this->socket->writePacket($payload, $address, $port);
			}elseif($id === RakLib::PACKET_CLOSE_SESSION){
				$len = ord($packet{$offset++});
				$identifier = substr($packet, $offset, $len);
				if(isset($this->sessions[$identifier])){
					$this->sessions[$identifier]->flagForDisconnection();
				}else{
					$this->streamInvalid($identifier);
				}
			}elseif($id === RakLib::PACKET_INVALID_SESSION){
				$len = ord($packet{$offset++});
				$identifier = substr($packet, $offset, $len);
				if(isset($this->sessions[$identifier])){
					$this->removeSession($this->sessions[$identifier]);
				}
			}elseif($id === RakLib::PACKET_SET_OPTION){
				$len = ord($packet{$offset++});
				$name = substr($packet, $offset, $len);
				$offset += $len;
				$value = substr($packet, $offset);
				switch($name){
					case "name":
						$this->name = $value;
						break;
					case "portChecking":
						$this->portChecking = (bool) $value;
						break;
					case "packetLimit":
						$this->packetLimit = (int) $value;
						break;
				}
			}elseif($id === RakLib::PACKET_BLOCK_ADDRESS){
				$len = ord($packet{$offset++});
				$address = substr($packet, $offset, $len);
				$offset += $len;
				$timeout = Binary::readInt(substr($packet, $offset, 4));
				$this->blockAddress($address, $timeout);
			}elseif($id === RakLib::PACKET_UNBLOCK_ADDRESS){
				$len = ord($packet{$offset++});
				$address = substr($packet, $offset, $len);
				$this->unblockAddress($address);
			}elseif($id === RakLib::PACKET_SHUTDOWN){
				foreach($this->sessions as $session){
					$this->removeSession($session);
				}

				$this->socket->close();
				$this->shutdown = true;
			}elseif($id === RakLib::PACKET_EMERGENCY_SHUTDOWN){
				$this->shutdown = true;
			}else{
				$this->getLogger()->debug('Unknown RakLib internal packet (ID 0x' . dechex($id) . ') received from main thread');
			}

			return true;
		}

		return false;
	}

	public function blockAddress($address, $timeout = 300, $reason = null){
		$final = microtime(true) + $timeout;
		if(!isset($this->block[$address]) or $timeout === -1){
			if($timeout === -1){
				$final = PHP_INT_MAX;
				//$logMessage = "Blocked $address";
			}else{
				$logMessage = "Blocked $address for $timeout seconds";
			}
			if($reason !== null){
				$logMessage .= ", reason: " . $reason;
			}
			$this->getLogger()->info($logMessage);
			$this->block[$address] = $final;
		}elseif($this->block[$address] < $final){
			$this->block[$address] = $final;
		}
	}

	public function unblockAddress($address){
		unset($this->block[$address]);
		$this->getLogger()->debug("Unblocked $address");
	}

	/**
	 * @param string $ip
	 * @param int    $port
	 *
	 * @return Session|null
	 */
	public function getSession($ip, $port){
		$id = $ip . ":" . $port;
		return $this->sessions[$id] ?? null;
	}

	public function createSession($ip, $port, $clientId, $mtuSize){
		$this->checkSessions();

		$this->sessions[$ip . ":" . $port] = $session = new Session($this, $ip, $port, $clientId, $mtuSize);
		$this->getLogger()->debug("Created session for $ip $port with MTU size $mtuSize");

		return $session;
	}

	public function removeSession(Session $session, $reason = "unknown"){
		$id = $session->getAddress() . ":" . $session->getPort();
		if(isset($this->sessions[$id])){
			$this->sessions[$id]->close();
			$this->removeSessionInternal($session);
			$this->streamClose($id, $reason);
		}
	}

	public function removeSessionInternal(Session $session){
		unset($this->sessions[$session->getAddress() . ":" . $session->getPort()]);
	}

	public function openSession(Session $session){
		$this->streamOpen($session);
	}

	private function checkSessions(){
		if(count($this->sessions) > 128){
			foreach($this->sessions as $i => $s){
				if($s->isTemporal()){
					unset($this->sessions[$i]);
					if(count($this->sessions) <= 128){
						break;
					}
				}
			}
		}
	}

	public function notifyACK(Session $session, $identifierACK){
		$this->streamACK($session->getAddress() . ":" . $session->getPort(), $identifierACK);
	}

	public function getName(){
		return $this->name;
	}

	public function getID(){
		return $this->server->getServerId();
	}

	/**
	 * @param int    $id
	 * @param string $class
	 */
	private function registerPacket($id, $class){
		$this->packetPool[$id] = new $class;
	}

	/**
	 * @param int    $id
	 * @param string $buffer
	 *
	 * @return Packet|null
	 */
	public function getPacketFromPool($id, $buffer = ""){
		$pk = $this->packetPool[$id];
		if($pk !== null){
			$pk = clone $pk;
			$pk->buffer = $buffer;
			return $pk;
		}

		return null;
	}

	private function registerPackets(){
		$this->packetPool = new \SplFixedArray(256);

		$this->registerPacket(UnconnectedPing::$ID, UnconnectedPing::class);
		$this->registerPacket(UnconnectedPingOpenConnections::$ID, UnconnectedPingOpenConnections::class);
		$this->registerPacket(OpenConnectionRequest1::$ID, OpenConnectionRequest1::class);
		$this->registerPacket(OpenConnectionReply1::$ID, OpenConnectionReply1::class);
		$this->registerPacket(OpenConnectionRequest2::$ID, OpenConnectionRequest2::class);
		$this->registerPacket(OpenConnectionReply2::$ID, OpenConnectionReply2::class);
		$this->registerPacket(UnconnectedPong::$ID, UnconnectedPong::class);
		$this->registerPacket(AdvertiseSystem::$ID, AdvertiseSystem::class);
	}
}
