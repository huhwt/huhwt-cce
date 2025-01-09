<?php

/**
 * webtrees - clippings cart enhanced
 *
 * Copyright (C) 2023 huhwt. All rights reserved.
 * Copyright (C) 2021 Hermann Hartenthaler. All rights reserved.
 * Copyright (C) 2021 Richard Cissée. All rights reserved.
 *
 * webtrees: online genealogy / web based family history software
 * Copyright (C) 2021 webtrees development team.
 *
 */

declare(strict_types=1);

namespace HuHwt\WebtreesMods\ClippingsCartEnhanced\Traits;

use Fisharebest\Webtrees\I18N;

/**
 * Trait CCEmodulesTrait - bundling all declarations for CCE-modules
 */
trait CCEmodulesTrait
{

    // List of const for module administration
    public const CUSTOM_TITLE       = 'Clippings cart enhanced';
    public const CUSTOM_DESCRIPTION = 'Add records from your family tree to the clippings cart and execute an action on them.';
    public const CUSTOM_MODULE      = 'huhwt-cce';
    public const CUSTOM_AUTHOR      = 'EW.H / Hermann Hartenthaler';
    public const CUSTOM_WEBSITE     = 'https://github.com/huhwt/' . self::CUSTOM_MODULE . '/';
    public const CUSTOM_VERSION     = '2.2.1.1';
    public const CUSTOM_LAST        = 'https://github.com/huhwt/' .
                                        self::CUSTOM_MODULE. '/blob/master/latest-version.txt';

    /**
     * The person or organisation who created this module.
     *
     * @return string
     */
    public function customModuleAuthorName(): string {
        return self::CUSTOM_AUTHOR;
    }

    /**
     * A URL that will provide the latest version of this module.
     *
     * @return string
     */
    public function customModuleLatestVersionUrl(): string
    {
        return self::CUSTOM_LAST;
    }

    /**
     * The version of this module.
     *
     * @return string
     */
    public function customModuleVersion(): string
    {
        return self::CUSTOM_VERSION;
    }

    /**
     * Where to get support for this module?  Perhaps a GitHub repository?
     *
     * @return string
     */
    public function customModuleSupportUrl(): string
    {
        return self::CUSTOM_WEBSITE;
    }

    /**
     * How should this module be identified in the control panel, etc.?
     *
     * @return string
     */
    public function title(): string
    {
        /* I18N: Name of a module */
        return json_decode('"\u210D"') . ' ' . I18N::translate(self::CUSTOM_TITLE);
    }

    /**
     * How should this module be identified in the menu list?
     *
     * @return string
     */
    protected function menuTitle(): string
    {
        return $this->huh . ' ' . I18N::translate(self::CUSTOM_TITLE);
    }

    /**
     * Where does this module store its resources?
     *
     * @return string
     */
    public function resourcesFolder(): string
    {
        return __DIR__ . DIRECTORY_SEPARATOR .'resources' . DIRECTORY_SEPARATOR;
    }


}