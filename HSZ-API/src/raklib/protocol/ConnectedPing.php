<?php



namespace raklib\protocol;

#include <rules/RakLibPacket.h>

class ConnectedPing extends Packet{
	public static $ID = MessageIdentifiers::ID_CONNECTED_PING;

	/** @var int */
	public $sendPingTime;

	protected function encodePayload(){
		$this->putLong($this->sendPingTime);
	}

	protected function decodePayload(){
		$this->sendPingTime = $this->getLong();
	}
}