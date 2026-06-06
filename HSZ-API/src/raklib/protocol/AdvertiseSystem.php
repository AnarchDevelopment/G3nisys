<?php



namespace raklib\protocol;

class AdvertiseSystem extends Packet{
	public static $ID = MessageIdentifiers::ID_ADVERTISE_SYSTEM;

	/** @var string */
	public $serverName;

	protected function encodePayload(){
		$this->putString($this->serverName);
	}

	protected function decodePayload(){
		$this->serverName = $this->getString();
	}
}