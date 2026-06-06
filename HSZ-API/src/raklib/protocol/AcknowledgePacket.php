<?php

declare(strict_types=1);

namespace raklib\protocol;

use function chr;
use function count;
use function sort;
use const SORT_NUMERIC;

#ifndef COMPILE
use pocketmine\utils\Binary;
#endif

#include <rules/RakLibPacket.h>

abstract class AcknowledgePacket extends Packet{
	const RECORD_TYPE_RANGE = 0;
	const RECORD_TYPE_SINGLE = 1;

	/** @var int[] */
	public $packets = [];

	protected function encodePayload(){
		$payload = '';
		sort($this->packets, SORT_NUMERIC);
		$count = count($this->packets);
		$records = 0;

		if($count > 0){
			$pointer = 1;
			$start = $this->packets[0];
			$last = $this->packets[0];

			while($pointer < $count){
				$current = $this->packets[$pointer++];
				$diff = $current - $last;
				if($diff === 1){
					$last = $current;
				}elseif($diff > 1){ //Forget about duplicated packets (bad queues?)
					if($start === $last){
						$payload .= chr(self::RECORD_TYPE_SINGLE);
						$payload .= Binary::writeLTriad($start);
						$start = $last = $current;
					}else{
						$payload .= chr(self::RECORD_TYPE_RANGE);
						$payload .= Binary::writeLTriad($start);
						$payload .= Binary::writeLTriad($last);
						$start = $last = $current;
					}
					++$records;
				}
			}

			if($start === $last){
				$payload .= chr(self::RECORD_TYPE_SINGLE);
				$payload .= Binary::writeLTriad($start);
			}else{
				$payload .= chr(self::RECORD_TYPE_RANGE);
				$payload .= Binary::writeLTriad($start);
				$payload .= Binary::writeLTriad($last);
			}
			++$records;
		}

		$this->putShort($records);
		$this->buffer .= $payload;
	}

	protected function decodePayload(){
		$count = $this->getShort();
		$this->packets = [];
		$cnt = 0;
		for($i = 0; $i < $count and !$this->feof() and $cnt < 4096; ++$i){
			if($this->getByte() === self::RECORD_TYPE_RANGE){
				$start = $this->getLTriad();
				$end = $this->getLTriad();
				if(($end - $start) > 512){
					$end = $start + 512;
				}
				for($c = $start; $c <= $end; ++$c){
					$this->packets[$cnt++] = $c;
				}
			}else{
				$this->packets[$cnt++] = $this->getLTriad();
			}
		}
	}

	public function clean(){
		$this->packets = [];
		return parent::clean();
	}
}