<?php

namespace raklib\server;

use raklib\protocol\OfflineMessage;
use raklib\protocol\OpenConnectionReply1;
use raklib\protocol\OpenConnectionReply2;
use raklib\protocol\OpenConnectionRequest1;
use raklib\protocol\OpenConnectionRequest2;
use raklib\protocol\UnconnectedPing;
use raklib\protocol\UnconnectedPong;
use raklib\protocol\IncompatibleProtocolVersion;
use function abs;
use function min;

class OfflineMessageHandler{
	/** @var SessionManager */
	private $sessionManager;

	public function __construct(SessionManager $manager){
		$this->sessionManager = $manager;
	}

	public function handle(OfflineMessage $packet, string $source, int $port) : bool{
		switch($packet::$ID){
			case UnconnectedPing::$ID:
				/** @var UnconnectedPing $packet */
				$pk = new UnconnectedPong();
				$pk->serverID = $this->sessionManager->getID();
				$pk->pingID = $packet->pingID;
				$pk->serverName = $this->sessionManager->getName();
				$this->sessionManager->sendPacket($pk, $source, $port);
				return true;
			case OpenConnectionRequest1::$ID:
				/** @var OpenConnectionRequest1 $packet */
                $serverProtocol = $this->sessionManager->getProtocolVersion();
				if($packet->protocol !== 7 && $packet->protocol !== 8){
                        $pk = new IncompatibleProtocolVersion();
                        $pk->protocolVersion = $serverProtocol;
                        $pk->serverId = $this->sessionManager->getID();
                        $this->sessionManager->sendPacket($pk, $source, $port);
                        $this->sessionManager->getLogger()->debug("Refused connection from $source:$port due to incompatible RakNet protocol version (expected $serverProtocol got $packet->protocol)");
                    }else{
                        $pk = new OpenConnectionReply1();
                        $pk->mtuSize = $packet->mtuSize; // IP header size (20 bytes) + UDP header size (8 bytes)
                        $pk->serverID = $this->sessionManager->getID();
                        $this->sessionManager->sendPacket($pk, $source, $port);
                    }
                    return true;
			case OpenConnectionRequest2::$ID:
				/** @var OpenConnectionRequest2 $packet */

				if($packet->serverPort === $this->sessionManager->getPort() or !$this->sessionManager->portChecking){
					$mtuSize = min(abs($packet->mtuSize), 1432); //Max size, do not allow creating large buffers to fill server memory
					$pk = new OpenConnectionReply2();
					$pk->mtuSize = $mtuSize;
					$pk->serverID = $this->sessionManager->getID();
					$pk->clientAddress = $source;
					$pk->clientPort = $port;
					$this->sessionManager->sendPacket($pk, $source, $port);
					$this->sessionManager->createSession($source, $port, $packet->clientID, $mtuSize);
				}else{
					$this->sessionManager->getLogger()->debug("Not creating session for $source $port due to mismatched port, expected " . $this->sessionManager->getPort() . ", got " . $packet->serverPort);
				}

				return true;
		}

		return false;
	}

}