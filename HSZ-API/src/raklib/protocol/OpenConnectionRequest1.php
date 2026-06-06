<?php



namespace raklib\protocol;

#include <rules/RakLibPacket.h>

use raklib\RakLib;
use function str_pad;
use function strlen;

class OpenConnectionRequest1 extends OfflineMessage{
	public static $ID = MessageIdentifiers::ID_OPEN_CONNECTION_REQUEST_1;

	public $protocol = RakLib::PROTOCOL;
	public $mtuSize;

	protected function encodePayload(){
		$this->writeMagic();
		$this->putByte($this->protocol);
		$this->buffer = str_pad($this->buffer, "\x00", $this->mtuSize);
	}

	protected function decodePayload(){
		$this->readMagic();
		$this->protocol = $this->getByte();
		$this->mtuSize = strlen($this->buffer);
	}
}