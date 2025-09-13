<?php
// ejemplo_task_anon.php
// depend: PowerScripts
// uses: PHP

return function($server, $plugin, $sender){
    $task = new class($server, $plugin) extends \pocketmine\scheduler\Task {
        private $server;
        private $plugin;
        private $i = 0;
        public function __construct($server, $plugin){
            $this->server = $server;
            $this->plugin = $plugin;
        }
        public function onRun($tick){
            $this->i++;
            $this->plugin->getLogger()->info("[anonTask] run#{$this->i}");
            if($this->i >= 5){
            }
        }
    };

    // schedule
    $plugin->getServer()->getScheduler()->scheduleRepeatingTask($task, 20);
    if($sender instanceof \pocketmine\command\ConsoleCommandSender) $sender->sendMessage("[PowerScripts] anon task scheduled.");
};