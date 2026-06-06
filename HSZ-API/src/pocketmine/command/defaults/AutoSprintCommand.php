<?php

namespace pocketmine\command\defaults;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class AutoSprintCommand extends VanillaCommand {
    
    private static $autoSprintPlayers = [];
    
    public function __construct($name){
        parent::__construct($name, "Sprint toggle gameplay", "/ts (on|off)");
    }
    
    public function execute(CommandSender $sender, $currentAlias, array $args){

        
        if(!($sender instanceof Player)){
            $sender->sendMessage(TextFormat::RED . "Only in-game.");
            return true;
        }
        
        if(count($args) === 0){
            $sender->sendMessage(TextFormat::LIGHT_PURPLE . "Uso: /ts (on|off)");
            return false;
        }
        
        $cmd = strtolower($args[0]);
        $name = $sender->getName();
        
        if($cmd === "on"){
            self::$autoSprintPlayers[$name] = true;
            $sender->sendMessage(TextFormat::GRAY . "ToggleSprint on!");
            

            if($sender->isOnGround() && !$sender->isSprinting()){
                $sender->setSprinting(true);
            }
            
        }elseif($cmd === "off"){
            unset(self::$autoSprintPlayers[$name]);
            $sender->sendMessage(TextFormat::GRAY . "ToggleSprint off!");
        }else{
            $sender->sendMessage(TextFormat::LIGHT_PURPLE . "Uso: /ts (on|off)");
        }
        
        return true;
    }
    
    public static function hasAutoSprint(Player $player): bool {
        return isset(self::$autoSprintPlayers[$player->getName()]);
    }
    
    public static function removePlayer(Player $player){
        $name = $player->getName();
        if(isset(self::$autoSprintPlayers[$name])){
            unset(self::$autoSprintPlayers[$name]);
        }
    }
}