<?php



declare(strict_types=1);

namespace raklib\protocol;

use raklib\Binary;
use function assert;
use function count;
use function explode;
use function inet_ntop;
use function inet_pton;
use function strlen;
use const AF_INET6;

#ifndef COMPILE

#endif

#include <rules/RakLibPacket.h>

abstract class Packet{
	public static $ID = -1;

	protected $offset = 0;
	public $buffer;
	public $sendTime;

	public function __construct($buffer = "", $offset = 0){
		$this->buffer = $buffer;
		$this->offset = $offset;
	}

	protected function get($len){
		if($len < 0){
			$this->offset = strlen($this->buffer) - 1;

			return "";
		}elseif($len === true){
			return substr($this->buffer, $this->offset);
		}

		return $len === 1 ? $this->buffer{$this->offset++} : substr($this->buffer, ($this->offset += $len) - $len, $len);
	}

	protected function getLong(){
		return Binary::readLong($this->get(8));
	}

	protected function getInt(){
		return Binary::readInt($this->get(4));
	}

	protected function getShort($signed = true){
		return $signed ? Binary::readSignedShort($this->get(2)) : Binary::readShort($this->get(2));
	}

	protected function getTriad(){
		return Binary::readTriad($this->get(3));
	}

	protected function getLTriad(){
		return Binary::readLTriad($this->get(3));
	}

	protected function getByte(){
		return ord($this->buffer{$this->offset++});
	}

	protected function getString(){
		return $this->get($this->getShort());
	}

	protected function getAddress(&$addr, &$port, &$version = null){
		$version = $this->getByte();
		if($version === 4){
			$addr = ((~$this->getByte()) & 0xff) .".". ((~$this->getByte()) & 0xff) .".". ((~$this->getByte()) & 0xff) .".". ((~$this->getByte()) & 0xff);
			$port = $this->getShort(false);
		}else{
			//no
		}
	}

	protected function feof(){
		return !isset($this->buffer{$this->offset});
	}

	protected function put($str){
		$this->buffer .= $str;
	}

	protected function putLong($v){
		$this->buffer .= Binary::writeLong($v);
	}

	protected function putInt($v){
		$this->buffer .= Binary::writeInt($v);
	}

	protected function putShort($v){
		$this->buffer .= Binary::writeShort($v);
	}

	protected function putTriad($v){
		$this->buffer .= Binary::writeTriad($v);
	}

	protected function putLTriad($v){
		$this->buffer .= Binary::writeLTriad($v);
	}

	protected function putByte($v){
		$this->buffer .= chr($v);
	}

	protected function putString($v){
		$this->putShort(strlen($v));
		$this->put($v);
	}
	
	protected function putAddress($addr, $port, $version = 4){
		$this->putByte($version);
		if($version === 4){
			foreach(explode(".", $addr) as $b){
				$this->putByte((~((int) $b)) & 0xff);
			}
			$this->putShort($port);
		}elseif($version === 6){
			$this->put(Binary::writeLShort(AF_INET6));
			$this->putShort($port);
			$this->putInt(0);
			$this->put(inet_pton($addr));
			$this->putInt(0);
		}else{
			throw new \InvalidArgumentException("IP version $version is not supported");
		}
	}

	public function encode(){
		$this->reset();
		$this->encodeHeader();
		$this->encodePayload();
	}

	protected function encodeHeader(){
		$this->putByte(static::$ID);
	}

	abstract protected function encodePayload();

	public function decode(){
		$this->offset = 0;
		$this->decodeHeader();
		$this->decodePayload();
	}

	protected function decodeHeader(){
		$this->getByte(); //PID
	}

	abstract protected function decodePayload();

	public function reset(){
		$this->buffer = "";
		$this->offset = 0;
	}

	public function clean(){
		$this->buffer = null;
		$this->offset = 0;
		$this->sendTime = null;
		return $this;
	}
}
