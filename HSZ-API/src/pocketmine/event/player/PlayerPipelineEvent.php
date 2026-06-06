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

namespace pocketmine\event\player;

use pocketmine\event\Cancellable;
use pocketmine\event\Event;
use pocketmine\Player;
use pocketmine\math\Vector3;

class PlayerPipelineEvent extends Event implements Cancellable {
    
    public static $handlerList = null;
    
    /** @var Player */
    private $player;
    
    /** @var Vector3 */
    private $from;
    
    /** @var Vector3 */
    private $to;
    
    /** @var Vector3 */
    private $movement;
    
    /** @var float */
    private $delta;
    
    /** @var bool */
    private $onGround;
    
    /** @var Vector3|null */
    private $motion;
    
    /** @var bool */
    protected $cancelled = false;
    
    public function __construct(Player $player, Vector3 $from, Vector3 $to, $onGround = false, $motion = null) {
        $this->player = $player;
        $this->from = $from;
        $this->to = $to;
        $this->movement = $to->subtract($from);
        $this->delta = $this->movement->lengthSquared();
        $this->onGround = $onGround;
        $this->motion = $motion;
    }
    
    public function getPlayer() {
        return $this->player;
    }
    
    public function getFrom() {
        return $this->from;
    }
    
    public function getTo() {
        return $this->to;
    }
    
    public function getMovement() {
        return $this->movement;
    }
    
    public function getDelta() {
        return $this->delta;
    }
    
    public function isOnGround() {
        return $this->onGround;
    }
    
    public function setOnGround($onGround) {
        $this->onGround = $onGround;
    }
    
    public function getMotion() {
        return $this->motion;
    }
    
    public function setMotion(Vector3 $motion) {
        $this->motion = $motion;
    }
    
    public function setTo(Vector3 $to) {
        $this->to = $to;
        $this->movement = $to->subtract($this->from);
        $this->delta = $this->movement->lengthSquared();
    }
    
    public function isCancelled() {
        return $this->cancelled;
    }
    
    public function setCancelled($value = true) {
        $this->cancelled = (bool) $value;
    }
}