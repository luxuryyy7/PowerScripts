PowerScripts — README (Español)

> PowerScripts es un plugin para PocketMine / MCPE que permite a operadores y desarrolladores ejecutar scripts PHP (incluyendo scripts que usan la API de PocketMine) directamente desde la consola del servidor.
Está pensado para prototipado rápido, tareas de mantenimiento, automatizaciones y pruebas — no como un mecanismo para ejecutar código no verificado en producción sin revisión.

---

## 🎯 Propósito / Objetivo

PowerScripts permite:

Ejecutar scripts PHP rápido sin recompilar ni recargar el plugin.

Programar tareas ligeras o ejecutar scripts puntuales (snapshots, backups, análisis).

Soportar scripts puramente nativos (PHP) y scripts que usan la API de PocketMine.

Dar flexibilidad a desarrolladores para crear scripts namespaced reutilizables.


Es una herramienta para desarrolladores/ops — revisá los scripts antes de ejecutarlos.


---

## 🧩 Instalación (resumen)

1. Colocá el plugin PowerScripts en plugins/ (PHAR o carpeta).


2. Reiniciá el servidor para extraer defaults (si empaquetaste resources/defaultScripts/).


3. Asegurate que plugins/PowerScripts/ (carpeta data) tenga permisos de escritura.


4. Poné scripts en plugins/PowerScripts/data/scripts/ o usá !install default (script)



> Estructura mínima:

```plugins/PowerScripts/
  data/
    scripts/
    defaultScripts/
    tmp_scripts/
    ...
```

---

## ▶️ Cómo usar (básico)

Usá la consola del servidor (no como jugador). Comandos comienzan con !:

!setup — escanea la carpeta de scripts y registra scripts válidos.

!list — muestra scripts cargados.

!exe (script.php) — ejecuta el script.

!install list default — lista scripts por defecto incluidos.

!install default (script.php) — instala un default en data/scripts/.

!install default all — instala todos los defaults.



---

## 📜 Comandos (detalle)

!setup
Escanea data/scripts/ y carga scripts que pasen la validación.

!list
Lista scripts disponibles.

!exe (script.php)
Ejecuta un script. Dependiendo del retorno del script:

callable: se invoca con (Server, Plugin, ConsoleSender).

string (nombre de función): se llama con los mismos parámetros.

instancia de \pocketmine\scheduler\Task: PowerScripts la schedulea (intervalo desde metadata o por defecto).

array('task'=>'FQCN','interval'=>N): se intentará instanciar la clase y schedulearla.

Si no devuelve nada útil, el include se ejecuta y los echo se muestran en consola.


!install list default
Muestra defaults empaquetados.

!install default (script.php)
Copia un default a data/scripts/.

!install default all
Instala todos los defaults.



---

## ⚠️ Advertencias / Seguridad

Ejecutar código arbitrario es peligroso. Revisá scripts de terceros antes de ejecutarlos.

Probá primero en un entorno aislado.

No habilites instalaciones automáticas desde fuentes no verificadas (si lo añadís, usá firmas).

PowerScripts no sandboxes los scripts; corren con privilegios del proceso del servidor.



---

## 🧭 Reglas y convenciones para scripting

Metadatos obligatorios: Cada script debe declarar dependencia a PowerScripts y uso de PHP, por ejemplo:

Comentario:
```
// depend: PowerScripts
// uses: PHP
// interval: 100      (opcional)
```
PS:: style:
```
PS::depend(PowerScripts)
PS::uses(PHP)
PS::interval(100)
```
Dos modos

1. Modo simple (sin namespace)

Main envuelve el script en namespace { ... } para forzar definiciones en el scope global.

Soporta clases globales, clases anónimas y scripts que retornan callables.



2. Modo namespace custom (script declara namespace ...)

Main respeta el namespace (no envuelve).

En este caso el script debe retornar:

un callable que reciba ($server, $plugin, $sender), o

una instancia de \pocketmine\scheduler\Task, o

un array descriptor ['task'=>'Your\\Ns\\Class','interval'=>100].


---


## Buenas prácticas

Si usás namespace, preferí retornar un callable.

No retornes new MyTask($server,$plugin) desde el top-level si $server/$plugin no existen en ese scope; en su lugar, retorna un callable que cree la instancia al ejecutarse.

Usá PS::interval() o // interval: para sugerir intervalos.

Mantén los echo concisos.



---

## ✅ Ejemplos

1) Nativo simple (sin namespace)

plugins/PowerScripts/data/scripts/native_hello.php

```<?php
// native_hello.php
// depend: PowerScripts
// uses: PHP

echo "[native_hello] Hola desde script nativo PHP\n";
$out = __DIR__ . DIRECTORY_SEPARATOR . "native_hello_" . time() . ".txt";
file_put_contents($out, "native_hello ejecutado en " . date("c") . "\n");
echo "[native_hello] Archivo creado: " . basename($out) . "\n";
return true;
```
2) Namespaced callable + clase anónima

plugins/PowerScripts/data/scripts/hl_monitor_callable.php

```<?php
namespace HL\Monitor;

// PS::depend(PowerScripts)
// PS::uses(PHP)
// PS::interval(100)

return function($server, $plugin, $sender){
    $plugin->getLogger()->info("[HL\\Monitor] Callable activado.");

    $task = new class($server, $plugin) extends \pocketmine\scheduler\Task {
        private $server;
        private $plugin;
        private $i = 0;
        public function __construct($server, $plugin){
            $this->server = $server; $this->plugin = $plugin;
        }
        public function onRun($tick){
            $this->i++;
            $this->plugin->getLogger()->info("[HL\\Monitor anonTask] run#" . $this->i);
        }
    };

    $plugin->getScheduler()->scheduleRepeatingTask($task, 100);
    if($sender instanceof \pocketmine\command\ConsoleCommandSender) $sender->sendMessage("[HL\\Monitor] Task programada.");
};
```
3) Namespaced descriptor (FQCN)

plugins/PowerScripts/data/scripts/hl_heavy_descriptor.php

```<?php
namespace HL\Tasks;

// PS::depend(PowerScripts)
// PS::uses(PHP)
// PS::interval(120)

class HeavyMonitorTask extends \pocketmine\scheduler\Task {
    public function __construct($server=null,$plugin=null){}
    public function onRun($tick){}
}

return [
  'task' => 'HL\\Tasks\\HeavyMonitorTask',
  'interval' => 120
];
```

---

🧾 default_index.json ejemplo

```[
  "native_hello.php",
  "hl_monitor_callable.php",
  "hl_heavy_descriptor.php"
]
```


---

🔗 Enlaces


YouTube: [luxuryyy7](https://youtube.com/@luxuryyyyyyyyyyyyyyyyyy?si=4yFFcZgVDuAcEB6O)

Discord personal: **luxuryyy7**


GitHub: https://github.com/luxuryyy7

---

Licencia / Uso

Se provee tal cual. Revisá scripts antes de ejecutarlos. Reutilizá y adaptá libremente, pero con responsabilidad.



---

> “Quién busca la perfección tal vez sienta que nunca es suficiente.” — luxuryyy7