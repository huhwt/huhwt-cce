<?php

namespace HuHwt\WebtreesMods\ClippingsCartEnhanced;

use Composer\Autoload\ClassLoader;

$loader = new ClassLoader();
$loader->addPsr4('HuHwt\\WebtreesMods\\ClippingsCartEnhanced\\', __DIR__);
$loader->addPsr4('HuHwt\\WebtreesMods\\ClippingsCartEnhanced\\', __DIR__ . '/resources');
// $loader->addPsr4('HuHwt\\WebtreesMods\\ClippingsCartEnhanced\\modules\\', __DIR__ . '/views/modules');
$loader->addPsr4('HuHwt\\WebtreesMods\\ClippingsCartEnhanced\\', __DIR__ . '/src');
$loader->register();
