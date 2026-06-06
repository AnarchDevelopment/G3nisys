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

namespace pocketmine;

use pocketmine\event\player\PlayerPipelineEvent;
use pocketmine\math\Vector3;

class PlayerPipeline {
    /** @var array */
    private $pipelines = [];
    /** @var int */
    private $pipelineId = 0;
    
    public function register(callable $callback, $priority = 0) {
        $id = $this->pipelineId++;
        $this->pipelines[$id] = [
            'callback' => $callback,
            'priority' => $priority
        ];
        
        uasort($this->pipelines, function($a, $b) {
            return $b['priority'] - $a['priority'];
        });
        
        return $id;
    }
    
    public function unregister($id) {
        unset($this->pipelines[$id]);
    }
    
    public function processMovement(Player $player, Vector3 $from, Vector3 $to, $onGround = false, Vector3 $motion = null) {
        $event = new PlayerPipelineEvent($player, $from, $to, $onGround, $motion);
        
        foreach ($this->pipelines as $pipeline) {
            try {
                $result = call_user_func($pipeline['callback'], $event);
                
                if ($result === false) {
                    $event->setCancelled(true); 
                    break;
                }
                
                if ($event->isCancelled()) {
                    break;
                }
            } catch (\Exception $e) {
                if ($player->getServer()->getLogger() !== null) {
                    $player->getServer()->getLogger()->logException($e);
                }
            }
        }
        
        return [
            'cancelled' => $event->isCancelled(),
            'to' => $event->getTo(),
            'onGround' => $event->isOnGround(),
            'motion' => $event->getMotion()
        ];
    }
}