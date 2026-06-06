<?php



namespace raklib\protocol;

#include <rules/RakLibPacket.h>

class OpenConnectionRequest2 extends OfflineMessage{
	public static $ID = MessageIdentifiers::ID_OPEN_CONNECTION_REQUEST_2;

	public $clientID;
	public $serverAddress;
	/** @var int */
	public $serverPort;
	/** @var int */
	public $serverAddressVersion;
	/** @var int */
	public $mtuSize;

	public function encodePayload(){
		$this->writeMagic();
		$this->putAddress($this->serverAddress, $this->serverPort, $this->serverAddressVersion);
		$this->putShort($this->mtuSize);
		$this->putLong($this->clientID);
	}

	public function decodePayload(){
		$this->readMagic();
		$this->getAddress($this->serverAddress, $this->serverPort, $this->serverAddressVersion);
		$this->mtuSize = $this->getShort();
		$this->clientID = $this->getLong();
	}
}
