<?php

/**
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link   http://www.pocketmine.net/
 *
 *
 */

namespace pocketmine\event\player;

use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\TextContainer;
use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\entity\Lightning;

class PlayerDeathEvent extends EntityDeathEvent{
	public static $handlerList = null;


	private $deathMessage;
	private $keepInventory = false;
	private $keepExperience = false;


	public function __construct(Player $entity, array $drops, $deathMessage){
		parent::__construct($entity, $drops);
		$this->deathMessage = $deathMessage;
		

		$this->spawnLightningOnDeath($entity);

	}
	

	private function spawnLightningOnDeath(Player $player){

		$level = $player->getLevel();
		if($level === null){
			return;
		}

		$pos = $player->getPosition();
		

		$chunk = $level->getChunk($pos->x >> 4, $pos->z >> 4, true);
		if($chunk === null){
			return;
		}
		

		$nbt = new \pocketmine\nbt\tag\CompoundTag("", [
			"Pos" => new \pocketmine\nbt\tag\ListTag("Pos", [
				new \pocketmine\nbt\tag\DoubleTag("", $pos->x),
				new \pocketmine\nbt\tag\DoubleTag("", $pos->y),
				new \pocketmine\nbt\tag\DoubleTag("", $pos->z)
			]),
			"Motion" => new \pocketmine\nbt\tag\ListTag("Motion", [
				new \pocketmine\nbt\tag\DoubleTag("", 0),
				new \pocketmine\nbt\tag\DoubleTag("", 0),
				new \pocketmine\nbt\tag\DoubleTag("", 0)
			]),
			"Rotation" => new \pocketmine\nbt\tag\ListTag("Rotation", [
				new \pocketmine\nbt\tag\FloatTag("", 0),
				new \pocketmine\nbt\tag\FloatTag("", 0)
			])
		]);
		

		$lightning = new Lightning($chunk, $nbt);
		
	
		if($lightning instanceof Lightning){
			$lightning->spawnToAll();
		}
	}
	

	public function getEntity(){
		return $this->entity;
	}


	public function getPlayer(){
		return $this->entity;
	}


	public function getDeathMessage(){
		return $this->deathMessage;
	}


	public function setDeathMessage($deathMessage){
		$this->deathMessage = $deathMessage;
	}

	public function getKeepInventory() : bool{
		return $this->keepInventory;
	}

	public function setKeepInventory(bool $keepInventory){
		$this->keepInventory = $keepInventory;
	}

	public function getKeepExperience() : bool{
		return $this->keepExperience;
	}

	public function setKeepExperience(bool $keepExperience){
		$this->keepExperience = $keepExperience;
	}
}