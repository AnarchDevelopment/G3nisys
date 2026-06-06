<?php



namespace raklib\protocol;

#include <rules/RakLibPacket.h>

use raklib\RakLib;

use function strlen;

class ConnectionRequestAccepted extends Packet{
	public static $ID = MessageIdentifiers::ID_CONNECTION_REQUEST_ACCEPTED;

	/** @var string */
	public $address;
	/** @var int */
	public $port;
	/** @var int */
	public $addressVersion = 4;
	/** @var array */
	public $systemAddresses = [
		["127.0.0.1", 0, 4]
	];

	/** @var int */
	public $sendPingTime;
	/** @var int */
	public $sendPongTime;

	protected function encodePayload(){
		$this->putAddress($this->address, $this->port, $this->addressVersion);
		$this->putShort(0);
		for($i = 0; $i < RakLib::$SYSTEM_ADDRESS_COUNT; ++$i){
			$addr = $this->systemAddresses[$i] ?? ["0.0.0.0", 0, 4];
			$this->putAddress($addr[0], $addr[1], $addr[2]);
		}

		$this->putLong($this->sendPingTime);
		$this->putLong($this->sendPongTime);
	}

	protected function decodePayload(){
		//TODO, not needed yet
		$this->address = $this->getAddress();
		$this->getShort(); //TODO: check this

		$len = strlen($this->buffer);
		$dummy = new InternetAddress("0.0.0.0", 0, 4);

		for($i = 0; $i < RakLib::$SYSTEM_ADDRESS_COUNT; ++$i){
			$this->systemAddresses[$i] = $this->offset + 16 < $len ? $this->getAddress() : $dummy; //HACK: avoids trying to read too many addresses on bad data
		}

		$this->sendPingTime = $this->getLong();
		$this->sendPongTime = $this->getLong();
	}
}