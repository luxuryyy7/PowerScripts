# PowerScripts — README (English)

> **PowerScripts** is a development/operations plugin for PocketMine / MCPE that lets server operators and developers execute PHP scripts (including scripts that use PocketMine API) directly from the server console.  
> It’s intended for rapid prototyping, maintenance operations, scheduled tasks and testing — **not** as a way to run untrusted code in production without review.

---

## 🎯 Purpose / Goal
PowerScripts provides:
- Fast execution of PHP scripts without repackaging or reloading the plugin.
- A way to schedule lightweight tasks or run one-shot maintenance scripts.
- Compatibility with plain PHP scripts and scripts that use PocketMine APIs.
- Flexibility for advanced devs to author namespaced scripts that return callables or task descriptors.

This is a tool for server operators and developers — treat it with caution and review scripts before running them.

---

## 🧩 Installation (quick)
1. Put the PowerScripts plugin into `plugins/` (PHAR or folder).
2. Start/restart the server to extract any packaged default scripts (if included).
3. Make sure `plugins/PowerScripts/` (the plugin data folder) is writable.
4. Place scripts in `plugins/PowerScripts/data/scripts/` or use `!install default <script>`.

Minimal data structure:
```
plugins/PowerScripts/ data/ scripts/ defaultScripts/ tmp_scripts/ ...
```
---

## ▶️ How to use (basics)
Run commands from the **server console** (not as a player). Commands start with `!`:

- `!setup` — scan the scripts folder and register valid scripts.
- `!list` — show registered scripts.
- `!exe <script.php>` — execute a script.
- `!install list default` — list bundled default scripts.
- `!install default <script.php>` — install a bundled default script into `data/scripts/`.
- `!install default all` — install all bundled defaults.

---

## 📜 Commands (detailed)
- `!setup`  
  Scans `data/scripts/` and loads scripts that pass validation.

- `!list`  
  Lists available scripts after `!setup`.

- `!exe <script.php>`  
  Execute a script. Behavior depends on what the script returns:
  - `callable`: PowerScripts calls it with `(Server, Plugin, ConsoleSender)`.
  - `string` (function name): the function is called with the same parameters.
  - `\pocketmine\scheduler\Task` instance: PowerScripts schedules it (interval from metadata or default).
  - `array('task'=>'FQCN','interval'=>N)`: PowerScripts will try to instantiate the class and schedule it.
  - Otherwise: the script is included and any `echo` output is forwarded to console.

- `!install list default`  
  Show defaults included inside the plugin.

- `!install default <script.php>`  
  Copy a default script to `data/scripts/` and make it ready to run.

- `!install default all`  
  Install all default scripts.

---

## ⚠️ Warnings / Security
- **Running arbitrary code is dangerous.** Always review scripts you didn't author before running them.
- Test in an isolated environment (local or staging server) before production use.
- Do **not** enable any automatic remote install or auto-run of third-party scripts without verification (future features should use signatures).
- PowerScripts **does not sandbox** PHP code — it runs with the same privileges as the server process.

---

## 🧭 Scripting Rules & Conventions

**Mandatory metadata** — each script must declare it depends on PowerScripts and that it uses PHP. Two supported styles:

Comment style:
```php
// depend: PowerScripts
// uses: PHP
// interval: 100      (optional)
```
PS:: style:
```
PS::depend(PowerScripts)
PS::uses(PHP)
PS::interval(100)    // optional
```

 Two supported modes

1. Simple mode (no namespace)

The loader wraps the script in namespace { ... } so classes are declared in global scope.

Supports named classes in global, anonymous classes, and scripts returning callables or other simple values.



2. Custom namespace mode (script declares namespace ...)

Loader respects the namespace (no wrapping).

Script must return one of:

a callable that receives ($server, $plugin, $sender) (recommended), or

an instance of \pocketmine\scheduler\Task, or

an array descriptor: ['task'=>'Your\\Ns\\Class', 'interval'=>100]. Main will attempt to instantiate the FQCN and schedule it.


Best practices

If using namespace, prefer returning a callable that receives (Server, Plugin, ConsoleSender) and creates/schedules tasks inside that callable.

Avoid returning new MyTask($server, $plugin) directly from the top-level return unless you ensure $server and $plugin variables exist in the script scope. The callable pattern avoids this fragility.

Use PS::interval( N ) or // interval: N to suggest default tick intervals.

Keep echo output concise — it prints directly to console.



---

✅ Examples

1) Native simple (no namespace)

plugins/PowerScripts/data/scripts/native_hello.php

```<?php
// native_hello.php
// depend: PowerScripts
// uses: PHP

echo "[native_hello] Hello from native PHP script!\n";
$out = __DIR__ . DIRECTORY_SEPARATOR . "native_hello_" . time() . ".txt";
file_put_contents($out, "native_hello executed at " . date("c") . "\n");
echo "[native_hello] Wrote file: " . basename($out) . "\n";
return true;
```
2) Namespaced callable with anonymous Task

plugins/PowerScripts/data/scripts/hl_monitor_callable.php

```<?php
namespace HL\Monitor;

// PS::depend(PowerScripts)
// PS::uses(PHP)
// PS::interval(100)

return function($server, $plugin, $sender){
    $plugin->getLogger()->info("[HL\\Monitor] Callable activated.");

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
            $this->plugin->getLogger()->info("[HL\\Monitor anonTask] run#" . $this->i);
        }
    };

    $plugin->getScheduler()->scheduleRepeatingTask($task, 100);
    if($sender instanceof \pocketmine\command\ConsoleCommandSender) $sender->sendMessage("[HL\\Monitor] Task scheduled.");
};
```
3) Namespaced descriptor returning FQCN

plugins/PowerScripts/data/scripts/hl_heavy_descriptor.php

```<?php
namespace HL\Tasks;

// PS::depend(PowerScripts)
// PS::uses(PHP)
// PS::interval(120)

class HeavyMonitorTask extends \pocketmine\scheduler\Task {
    public function __construct($server = null, $plugin = null){
        $this->server = $server;
        $this->plugin = $plugin;
    }
    public function onRun($tick){
        // collect metrics and write a file / log
    }
}

return [
  'task' => 'HL\\Tasks\\HeavyMonitorTask',
  'interval' => 120
];
```

🧾 default_index.json sample

```[
  "native_hello.php",
  "hl_monitor_callable.php",
  "hl_heavy_descriptor.php"
]
```

---

🔗 Links

YouTube: [luxuryyy7](https://youtube.com/@luxuryyyyyyyyyyyyyyyyyy?si=4yFFcZgVDuAcEB6O)

Discord (personal): luxuryyy7

GitHub: https://github.com/luxuryyy7


---

License & Usage

Provided as-is. Review scripts carefully before running. Use at your own risk.

When distributing a bundle with PowerScripts and default scripts, inform users to verify scripts before use.



---

> “Those who seek perfection may feel that it is never enough.” — luxuryyy7