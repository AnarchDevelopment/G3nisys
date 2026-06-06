<?php

namespace raklib\protocol;

#include <rules/RakLibPacket.h>

class UnconnectedPing extends OfflineMessage{
	public static $ID = MessageIdentifiers::ID_UNCONNECTED_PING;

	public $pingID;

	protected function encodePayload(){
		$this->putLong($this->pingID);
		$this->writeMagic();
	}

	protected function decodePayload(){
		$this->pingID = $this->getLong();
		$this->readMagic();
	}
}