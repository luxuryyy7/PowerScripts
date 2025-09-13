<?php

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\server\ServerCommandEvent;
use pocketmine\command\ConsoleCommandSender;

class Main extends PluginBase implements Listener {

    /** @var array name => fullpath */
    private $scripts = array();

    public function onEnable(){
        @mkdir($this->getDataFolder(), 0777, true);
        @mkdir($this->getDataFolder() . "scripts/", 0777, true);
        @mkdir($this->getDataFolder() . "defaultScripts/", 0777, true);
        @mkdir($this->getDataFolder() . "tmp_scripts/", 0777, true);

        $this->extractDefaultResources();

        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        $this->getLogger()->info("[PowerScripts] Enabled. Use console commands starting with '!' (example: !setup, !list, !exe <script.php>, !install ...).");
    }

    public function onDisable(){
        $this->getLogger()->info("[PowerScripts] Disabled.");
    }

    private function extractDefaultResources(){
        $res = $this->getResource("default_index.json");
        if($res === null){
            $this->getLogger()->info("[PowerScripts] No default_index.json resource found. If you want built-in defaults, add resources/default_index.json and resources/defaultScripts/* to the phar.");
            return;
        }

        $json = stream_get_contents($res);
        @fclose($res);

        $list = @json_decode($json, true);
        if(!is_array($list)){
            $this->getLogger()->warning("[PowerScripts] default_index.json malformed or not an array.");
            return;
        }

        $outDir = $this->getDataFolder() . "defaultScripts/";
        @mkdir($outDir, 0777, true);

        foreach($list as $filename){
            $filename = basename($filename);
            $resourcePath = "defaultScripts/" . $filename;
            $r = $this->getResource($resourcePath);
            if($r === null){
                $r = $this->getResource($filename);
            }
            if($r === null){
                $this->getLogger()->warning("[PowerScripts] Embedded default script not found in resources: {$resourcePath}");
                continue;
            }
            $content = stream_get_contents($r);
            @fclose($r);

            if($content === false || $content === ""){
                $this->getLogger()->warning("[PowerScripts] Empty content for embedded default: {$filename}");
                continue;
            }

            $dest = $outDir . $filename;

            if(!file_exists($dest)){
                if(@file_put_contents($dest, $content) !== false){
                    $this->getLogger()->info("[PowerScripts] Extracted default script: {$filename} -> data/defaultScripts/{$filename}");
                } else {
                    $this->getLogger()->warning("[PowerScripts] Failed to write extracted default: {$dest}");
                }
            } else {
                $this->getLogger()->info("[PowerScripts] Default script already exists, skipping: {$filename}");
            }
        }
    }

    public function onServerCommand(ServerCommandEvent $event){
        $sender = $event->getSender();
        $commandLine = $event->getCommand();

        if(!($sender instanceof ConsoleCommandSender)) return;
        if(strlen($commandLine) === 0) return;
        if($commandLine[0] !== '!') return;

        $event->setCancelled(true);

        $line = trim(substr($commandLine, 1));
        if($line === "") {
            $sender->sendMessage("[PowerScripts] Usage: !setup | !list | !exe <script.php> | !install ...");
            return;
        }

        $parts = preg_split('/\s+/', $line);
        $sub = strtolower(array_shift($parts));

        switch($sub){
            case "setup":
                $found = $this->scanScripts();
                $count = count($found);
                $sender->sendMessage("[PowerScripts] Scanned scripts folder. Found {$count} valid .php file(s).");
                if($count > 0){
                    foreach($found as $s) $sender->sendMessage(" - " . $s);
                }
            break;

            case "list":
                $names = $this->getScriptNames();
                if(count($names) === 0){
                    $sender->sendMessage("[PowerScripts] No scripts loaded. Run !setup first.");
                } else {
                    $sender->sendMessage("[PowerScripts] Available scripts:");
                    foreach($names as $n) $sender->sendMessage(" - " . $n);
                }
            break;

            case "exe":
            case "execute":
                if(count($parts) === 0){
                    $sender->sendMessage("[PowerScripts] Usage: !exe <script.php>");
                    return;
                }
                $script = $parts[0];
                $ok = $this->executeScript($script, $sender);
                if(!$ok){
                    $sender->sendMessage("[PowerScripts] Failed to execute: " . $script);
                }
            break;

            case "install":
                $this->handleInstallCommand($sender, $parts);
            break;

            default:
                $sender->sendMessage("[PowerScripts] Unknown subcommand: " . $sub . " (allowed: setup, list, exe, install)");
            break;
        }
    }

    private function handleInstallCommand(ConsoleCommandSender $sender, array $args){
        if(count($args) === 0){
            $sender->sendMessage("[PowerScripts] !install usage: install list/default/help");
            return;
        }

        $mode = strtolower(array_shift($args));
        switch($mode){
            case "help":
                $sender->sendMessage("[PowerScripts] Install commands:");
                $sender->sendMessage(" - !install list default");
                $sender->sendMessage(" - !install default <script.php>");
                $sender->sendMessage(" - !install default all");
            break;

            case "list":
                if(count($args) === 0 || strtolower($args[0]) !== "default"){
                    $sender->sendMessage("[PowerScripts] Usage: !install list default");
                    return;
                }
                $files = $this->listDefaultScripts();
                if(count($files) === 0){
                    $sender->sendMessage("[PowerScripts] No default scripts found in data/defaultScripts/. If you packaged defaults inside the plugin, ensure default_index.json and resources/defaultScripts/* exist.");
                    return;
                }
                $sender->sendMessage("[PowerScripts] Default scripts available:");
                foreach($files as $f) $sender->sendMessage(" - " . $f);
            break;

            case "default":
                if(count($args) === 0){
                    $sender->sendMessage("[PowerScripts] Usage: !install default <script.php>  OR  !install default all");
                    return;
                }
                $what = strtolower(array_shift($args));
                if($what === 'all'){
                    $files = $this->listDefaultScripts();
                    if(count($files) === 0){
                        $sender->sendMessage("[PowerScripts] No default scripts to install.");
                        return;
                    }
                    foreach($files as $fn){
                        $this->installDefaultScriptByName($sender, $fn);
                    }
                    $sender->sendMessage("[PowerScripts] Installed all default scripts (" . count($files) . ").");
                    return;
                } else {
                    $name = basename($what);
                    $this->installDefaultScriptByName($sender, $name);
                }
            break;

            default:
                $sender->sendMessage("[PowerScripts] Unknown install mode: " . $mode);
            break;
        }
    }

    private function installDefaultScriptByName(ConsoleCommandSender $sender, $name){
        $srcPath = $this->getDataFolder() . "defaultScripts/" . $name;
        if(!is_file($srcPath)){
            $sender->sendMessage("[PowerScripts] Default script not found in data/defaultScripts/: " . $name);
            return;
        }
        $content = @file_get_contents($srcPath);
        if($content === false){
            $sender->sendMessage("[PowerScripts] Failed to read default script: " . $srcPath);
            return;
        }
        $meta = $this->validateScriptContent($content);
        if($meta === false){
            $sender->sendMessage("[PowerScripts] Script {$name} rejected: missing required metadata (depend/use).");
            return;
        }
        $destDir = $this->getDataFolder() . "scripts/";
        @mkdir($destDir, 0777, true);
        $dest = $destDir . $name;
        if(@file_put_contents($dest, $content) === false){
            $sender->sendMessage("[PowerScripts] Failed to write script to: " . $dest);
            return;
        }
        $this->getLogger()->info("[PowerScripts] Installed default script: " . $name);
        $this->scripts[$name] = $dest;
        $sender->sendMessage("[PowerScripts] Installed default script: " . $name);
    }

    private function listDefaultScripts(){
        $dir = $this->getDataFolder() . "defaultScripts/";
        $result = array();
        if(!is_dir($dir)) return $result;
        $files = scandir($dir);
        foreach($files as $f){
            if($f === "." || $f === "..") continue;
            $full = $dir . $f;
            if(is_file($full) && strtolower(pathinfo($f, PATHINFO_EXTENSION)) === "php"){
                $result[] = $f;
            }
        }
        return $result;
    }

    public function scanScripts(){
        $dir = $this->getDataFolder() . "scripts/";
        $found = array();
        if(!is_dir($dir)){
            @mkdir($dir, 0777, true);
            return $found;
        }

        $files = scandir($dir);
        foreach($files as $f){
            if($f === "." || $f === "..") continue;
            $full = $dir . $f;
            if(is_file($full) && strtolower(pathinfo($f, PATHINFO_EXTENSION)) === "php"){
                $content = @file_get_contents($full);
                if($content === false) continue;
                $meta = $this->validateScriptContent($content);
                if($meta === false){
                    $this->getLogger()->warning("[PowerScripts] Skipping invalid script (missing metadata): {$f}");
                    continue;
                }
                $name = basename($f);
                $this->scripts[$name] = $full;
                $found[] = $name;
            }
        }
        return $found;
    }

    /**
     * Execute a script.
     * Supports:
     *  - scripts with their own namespace (the script must return callable/Task/descriptor)
     *  - scripts without namespace (wrap them into namespace { ... } so classes go to global scope)
     */
    public function executeScript($name, ConsoleCommandSender $sender){
        $name = basename($name); // sanitize
        if(!isset($this->scripts[$name])){
            $sender->sendMessage("[PowerScripts] Script not found: " . $name . ". Run !setup first.");
            return false;
        }

        $path = $this->scripts[$name];

        $content = @file_get_contents($path);
        if($content === false){
            $sender->sendMessage("[PowerScripts] Failed to read script: " . $name);
            return false;
        }
        $meta = $this->validateScriptContent($content);
        if($meta === false){
            $sender->sendMessage("[PowerScripts] Script validation failed for: " . $name . " (missing metadata).");
            return false;
        }

        $this->getLogger()->info("[PowerScripts] Executing script: " . $name . " (requested by console)");

        $hasNamespace = preg_match('/\bnamespace\s+[A-Za-z0-9_\\\\]+/i', $content) === 1;

        $tmpFile = null;
        if($hasNamespace){
            $includePath = $path;
        } else {
            $tmpDir = $this->getDataFolder() . "tmp_scripts/";
            @mkdir($tmpDir, 0777, true);
            $tmpFile = $tmpDir . "ps_exec_" . uniqid('', true) . ".php";
            $wrapped = $this->prepareScriptForInclude($content);
            if(@file_put_contents($tmpFile, $wrapped) === false){
                $sender->sendMessage("[PowerScripts] Failed to write temporary script file.");
                return false;
            }
            $includePath = $tmpFile;
        }

        // capture output (echo, print, etc.)
        ob_start();
        try {
            $ret = include($includePath);
            $output = ob_get_clean();
        } catch (\Throwable $ex){
            if(ob_get_length() !== false) @ob_end_clean();
            if($tmpFile !== null) @unlink($tmpFile);
            $this->getLogger()->critical("[PowerScripts] Script error in " . $name . ": " . $ex->getMessage());
            $sender->sendMessage("[PowerScripts] Script error: " . $ex->getMessage());
            return false;
        }

        if($tmpFile !== null) @unlink($tmpFile);

        if(isset($output) && $output !== ""){
            $lines = explode("\n", trim($output));
            foreach($lines as $line){
                $sender->sendMessage("[PowerScripts:out] " . $line);
            }
        }

        // If returned callable: call it with ($server, $plugin, $sender)
        // If callable returns a Task/array, handle it too.
        if(is_callable($ret)){
            try {
                $maybe = call_user_func($ret, $this->getServer(), $this, $sender);
                if($maybe !== null){
                    $ret = $maybe;
                } else {
                    $sender->sendMessage("[PowerScripts] Script executed (callable): " . $name);
                    return true;
                }
            } catch (\Throwable $ex){
                $this->getLogger()->critical("[PowerScripts] Callable error in " . $name . ": " . $ex->getMessage());
                $sender->sendMessage("[PowerScripts] Callable error: " . $ex->getMessage());
                return false;
            }
        }

        if(is_string($ret) && function_exists($ret)){
            try {
                call_user_func($ret, $this->getServer(), $this, $sender);
                $sender->sendMessage("[PowerScripts] Script executed (function): " . $name);
                return true;
            } catch (\Throwable $ex){
                $this->getLogger()->critical("[PowerScripts] Function error in " . $name . ": " . $ex->getMessage());
                $sender->sendMessage("[PowerScripts] Function error: " . $ex->getMessage());
                return false;
            }
        }

        if(is_object($ret) && ($ret instanceof \pocketmine\scheduler\Task)){
            $interval = isset($meta['interval']) ? intval($meta['interval']) : 100;
            try {
                $this->getServer()->getScheduler()->scheduleRepeatingTask($ret, $interval);
                $sender->sendMessage("[PowerScripts] Task instance scheduled every {$interval} ticks.");
                return true;
            } catch (\Throwable $ex){
                $this->getLogger()->critical("[PowerScripts] Failed to schedule returned Task: " . $ex->getMessage());
                $sender->sendMessage("[PowerScripts] Failed to schedule returned Task: " . $ex->getMessage());
                return false;
            }
        }

        // ['task'=>'FQCN','interval'=>100]
        if(is_array($ret) && isset($ret['task'])){
            $fqcn = $ret['task'];
            $interval = isset($ret['interval']) ? intval($ret['interval']) : (isset($meta['interval']) ? intval($meta['interval']) : 100);
            if(class_exists($fqcn)){
                try {
                    $inst = null;
                    try {
                        $inst = new $fqcn($this->getServer(), $this);
                    } catch (\Throwable $_){
                        $inst = new $fqcn();
                    }
                    if($inst instanceof \pocketmine\scheduler\Task){
                        $this->getServer()->getScheduler()->scheduleRepeatingTask($inst, $interval);
                        $sender->sendMessage("[PowerScripts] Task {$fqcn} scheduled every {$interval} ticks.");
                        return true;
                    } else {
                        $sender->sendMessage("[PowerScripts] Error: class {$fqcn} is not a Task instance.");
                        return false;
                    }
                } catch (\Throwable $ex){
                    $this->getLogger()->critical("[PowerScripts] Error instantiating task {$fqcn}: " . $ex->getMessage());
                    $sender->sendMessage("[PowerScripts] Error instantiating task {$fqcn}: " . $ex->getMessage());
                    return false;
                }
            } else {
                $sender->sendMessage("[PowerScripts] Error: task class {$fqcn} not found.");
                return false;
            }
        }

        $sender->sendMessage("[PowerScripts] Script included (no callable/Task returned): " . $name);
        return true;
    }

    private function prepareScriptForInclude($content){
        $content = preg_replace('#^\s*<\?php\s*#i', '', $content);
        $content = preg_replace('#\?>\s*$#', '', $content);

        $content = preg_replace('/\bnamespace\s+([A-Za-z0-9_\\\\]+)\s*(;|\{)/i', '', $content);

        $wrapped = "<?php\nnamespace {\n" . $content . "\n}\n";

        return $wrapped;
    }

    public function getScriptNames(){
        return array_keys($this->scripts);
    }

    /**
     * Validate & parse script metadata.
     * Returns associative array with parsed metadata or false if required metadata missing.
     * Supported: // depend: PowerScripts  | // uses: PHP
     * Optional: // interval: 100  or PS::interval(100)
     */
    private function validateScriptContent($content){
        if(!is_string($content) || trim($content) === "") return false;

        $meta = array('depend' => array(), 'uses' => array(), 'interval' => null);

        $lines = preg_split('/\r\n|\r|\n/', $content);
        foreach($lines as $line){
            $line = trim($line);
            if(stripos($line, '//') === 0){
                $after = trim(substr($line, 2));
                if(preg_match('/^([\w\-]+)\s*:\s*(.+)$/i', $after, $m)){
                    $k = strtolower($m[1]);
                    $v = trim($m[2]);
                    if($k === 'depend' || $k === 'depends' || $k === 'dependency'){
                        $meta['depend'][] = $v;
                    } elseif($k === 'uses' || $k === 'use'){
                        $meta['uses'][] = $v;
                    } elseif($k === 'interval'){
                        $meta['interval'] = intval($v);
                    }
                }
            }
            if(preg_match('/PS::depend\(\s*([A-Za-z0-9_\-]+)\s*\)/i', $line, $md)){
                $meta['depend'][] = $md[1];
            }
            if(preg_match('/PS::uses\(\s*([A-Za-z0-9_\-]+)\s*\)/i', $line, $mu)){
                $meta['uses'][] = $mu[1];
            }
            if(preg_match('/PS::interval\(\s*([0-9]+)\s*\)/i', $line, $mi)){
                $meta['interval'] = intval($mi[1]);
            }
        }

        $dependsLower = array_map('strtolower', $meta['depend']);
        $usesLower = array_map('strtolower', $meta['uses']);

        $hasDepend = in_array('powerscripts', $dependsLower);
        $hasUsesPHP = in_array('php', $usesLower);

        if($hasDepend && $hasUsesPHP){
            return array(
                'depend' => array_values(array_unique($dependsLower)),
                'uses' => array_values(array_unique($usesLower)),
                'interval' => $meta['interval']
            );
        }

        return false;
    }
    
    
}