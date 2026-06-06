<?php



namespace raklib\protocol;

#include <rules/RakLibPacket.h>

class ConnectionRequest extends Packet{
	public static $ID = MessageIdentifiers::ID_CONNECTION_REQUEST;

	/** @var int */
	public $clientID;
	/** @var int */
	public $sendPingTime;
	/** @var bool */
	public $useSecurity = false;

	protected function encodePayload(){
		$this->putLong($this->clientID);
		$this->putLong($this->sendPingTime);
		$this->putByte($this->useSecurity ? 1 : 0);
	}

	protected function decodePayload(){
		$this->clientID = $this->getLong();
		$this->sendPingTime = $this->getLong();
		$this->useSecurity = $this->getByte() !== 0;
	}
}