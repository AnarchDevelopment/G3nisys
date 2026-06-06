<?php



namespace raklib\protocol;

class IncompatibleProtocolVersion extends OfflineMessage{
	public static $ID = MessageIdentifiers::ID_INCOMPATIBLE_PROTOCOL_VERSION;

	/** @var int */
	public $protocolVersion;
	/** @var int */
	public $serverId;

	protected function encodePayload(){
		$this->putByte($this->protocolVersion);
		$this->writeMagic();
		$this->putLong($this->serverId);
	}

	protected function decodePayload(){
		$this->protocolVersion = $this->getByte();
		$this->readMagic();
		$this->serverId = $this->getLong();
	}
}