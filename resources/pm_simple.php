<?php
// pm_simple.php
// PS::depend(PowerScripts)
// PS::uses(PHP)

/**
 * PocketMine simple script
 */

return function($server, $plugin, $sender){
    $who = "Console";
    if($sender instanceof \pocketmine\command\ConsoleCommandSender){
        $who = $sender->getName();
    }
    $plugin->getLogger()->info("[pm_simple] Triggered by " . $who);
    $server->broadcastMessage("§cHighLights§f §8» §7[pm_simple] Script executed by " . $who);
    $players = $server->getOnlinePlayers();
    $count = count($players);
    if($sender instanceof \pocketmine\command\ConsoleCommandSender){
        $sender->sendMessage("[pm_simple] Players online: " . $count);
    }
};