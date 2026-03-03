<?php

/**
 * webtrees - clippings cart enhanced
 *
 * Copyright (C) 2026 huhwt. All rights reserved.
 * 
 * Functions to be used in webtrees custom modules
 *
 */

declare(strict_types=1);

namespace HuHwt\WebtreesMods\ClippingsCartEnhanced\Module;

use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Webtrees;

/**
 * Functions to be used in webtrees custom modules
 */

class Helpers
{
    /**
     * Some initial letters have a special meaning
     */
    public static function displaySurnameInitial(string $initial): string
    {
        if ($initial === '@') {
            return I18N::translateContext('Unknown surname', '…');
        }

        if ($initial === ',') {
            return I18N::translate('No surname');
        }

        return e($initial);
    }

}