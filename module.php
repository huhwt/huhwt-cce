<?php

namespace HuHwt\WebtreesMods\ClippingsCartEnhanced;

use Fisharebest\Webtrees\Webtrees;

use function app;

//webtrees major version switch
if (defined("WT_VERSION"))
    {
    //this is a webtrees 2.x module. it cannot be used with webtrees 1.x. See README.md.
    return;
    } else {
    $version = Webtrees::VERSION;
}

// Register our namespace
require_once __DIR__ . '/autoload.php';
  
require __DIR__ . '/ClippingsCartEnhanced.php';
// Create and return instance of the module

return app(ClippingsCartEnhanced::class);
