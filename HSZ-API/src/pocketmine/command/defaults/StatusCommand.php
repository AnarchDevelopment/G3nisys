<?php

/*
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
 * @link http://www.pocketmine.net/
 *
 *
*/

namespace pocketmine\command\defaults;

use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Utils;

class StatusCommand extends VanillaCommand{

	public function __construct($name){
		parent::__construct(
			$name,
			"%pocketmine.command.status.description",
			"%pocketmine.command.status.usage"
		);
		$this->setPermission("pocketmine.command.status");
	}

	public function execute(CommandSender $sender, $currentAlias, array $args){
		if(!$this->testPermission($sender)){
			return true;
		}

		$mUsage = Utils::getMemoryUsage(true);
		$rUsage = Utils::getRealMemoryUsage();

		$server = $sender->getServer();
		$onlineCount = 0;
		foreach($sender->getServer()->getOnlinePlayers() as $player){
			if($player->isOnline() and (!($sender instanceof Player) or $sender->canSee($player))){
				++$onlineCount;
			}
		}
		$sender->sendMessage(TextFormat::LIGHT_PURPLE . "----==( " . TextFormat::WHITE . "%pocketmine.command.status.title" . TextFormat::LIGHT_PURPLE . " )==----");
		$sender->sendMessage(TextFormat::LIGHT_PURPLE . "%pocketmine.command.status.player" . TextFormat::GRAY ." ". $onlineCount . "/" . $sender->getServer()->getMaxPlayers());

		$sender->sendMessage(TextFormat::LIGHT_PURPLE . "%pocketmine.command.status.uptime " . TextFormat::GRAY . $sender->getServer()->getUptime());

		$tpsColor = TextFormat::GRAY;
		if($server->getTicksPerSecondAverage() < 10){
			$tpsColor = TextFormat::LIGHT_PURPLE;
		}elseif($server->getTicksPerSecondAverage() < 1){
			$tpsColor = TextFormat::GRAY;
		}

		$tpsColour = TextFormat::GRAY;
		if($server->getTicksPerSecond() < 10){
			$tpsColour = TextFormat::LIGHT_PURPLE;
		}elseif($server->getTicksPerSecond() < 1){
			$tpsColour = TextFormat::GRAY;
		}

		$sender->sendMessage(TextFormat::LIGHT_PURPLE . "%pocketmine.command.status.AverageTPS " . $tpsColor . $server->getTicksPerSecondAverage() . " (" . $server->getTickUsageAverage() . "%)");
		$sender->sendMessage(TextFormat::LIGHT_PURPLE . "%pocketmine.command.status.CurrentTPS " . $tpsColour . $server->getTicksPerSecond() . " (" . $server->getTickUsage() . "%)");

		$sender->sendMessage(TextFormat::LIGHT_PURPLE . "%pocketmine.command.status.Networkupload " . TextFormat::GRAY . \round($server->getNetwork()->getUpload() / 1024, 2) . " kB/s");
		$sender->sendMessage(TextFormat::LIGHT_PURPLE . "%pocketmine.command.status.Networkdownload " . TextFormat::LIGHT_PURPLE . \round($server->getNetwork()->getDownload() / 1024, 2) . " kB/s");

		$sender->sendMessage(TextFormat::LIGHT_PURPLE . "%pocketmine.command.status.Threadcount " . TextFormat::GRAY . Utils::getThreadCount());

		$sender->sendMessage(TextFormat::LIGHT_PURPLE . "%pocketmine.command.status.Mainmemory " . TextFormat::GRAY . number_format(round(($mUsage[0] / 1024) / 1024, 2)) . " MB.");
		$sender->sendMessage(TextFormat::LIGHT_PURPLE . "%pocketmine.command.status.Totalmemory " . TextFormat::GRAY . number_format(round(($mUsage[1] / 1024) / 1024, 2)) . " MB.");
		$sender->sendMessage(TextFormat::LIGHT_PURPLE . "%pocketmine.command.status.Totalvirtualmemory " . TextFormat::GRAY . number_format(round(($mUsage[2] / 1024) / 1024, 2)) . " MB.");
		$sender->sendMessage(TextFormat::LIGHT_PURPLE . "%pocketmine.command.status.Heapmemory " . TextFormat::GRAY . number_format(round(($rUsage[0] / 1024) / 1024, 2)) . " MB.");
		$sender->sendMessage(TextFormat::LIGHT_PURPLE . "%pocketmine.command.status.Maxmemorysystem " . TextFormat::GRAY . number_format(round(($mUsage[2] / 1024) / 1024, 2)) . " MB.");

		if($server->getProperty("memory.global-limit") > 0){
			$sender->sendMessage(TextFormat::LIGHT_PURPLE . "%pocketmine.command.status.Maxmemorymanager " . TextFormat::GRAY . number_format(round($server->getProperty("memory.global-limit"), 2)) . " MB.");
		}
		foreach($server->getLevels() as $level){
			$sender->sendMessage(TextFormat::LIGHT_PURPLE . "%pocketmine.command.status.World \"" . $level->getFolderName() . "\"" . ($level->getFolderName() !== $level->getName() ? " (" . $level->getName() . ")" : "") . ": " .
				TextFormat::WHITE . number_format(count($level->getChunks())) . TextFormat::GRAY . " %pocketmine.command.status.chunks " .
				TextFormat::WHITE . number_format(count($level->getEntities())) . TextFormat::GRAY . " %pocketmine.command.status.entities " .
				TextFormat::WHITE . number_format(count($level->getTiles())) . TextFormat::GRAY . " %pocketmine.command.status.tiles " .
				"%pocketmine.command.status.Time " . (($level->getTickRate() > 1 or $level->getTickRateTime() > 40) ? TextFormat::RED : TextFormat::WHITE) . round($level->getTickRateTime(), 2) . "%pocketmine.command.status.ms" . ($level->getTickRate() > 1 ? " (tick rate " . $level->getTickRate() . ")" : "")
			);
		}

		return true;
	}
}
