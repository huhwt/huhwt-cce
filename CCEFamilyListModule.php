<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2023 webtrees development team
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace HuHwt\WebtreesMods\ClippingsCartEnhanced;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\View;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use HuHwt\WebtreesMods\ClippingsCartEnhanced\CCEIndividualListModule;
use HuHwt\WebtreesMods\ClippingsCartEnhanced\ClippingsCartEnhanced;
use HuHwt\WebtreesMods\ClippingsCartEnhanced\Traits\CCEmodulesTrait;

/**
 * Class CCEFamilyListModule
 */
class CCEFamilyListModule extends CCEIndividualListModule
                          implements ModuleCustomInterface
{
    use ModuleCustomTrait;

    use CCEmodulesTrait {
        CCEmodulesTrait::customModuleAuthorName insteadof ModuleCustomTrait;
        CCEmodulesTrait::customModuleLatestVersionUrl insteadof ModuleCustomTrait;
        CCEmodulesTrait::customModuleVersion insteadof ModuleCustomTrait;
        CCEmodulesTrait::customModuleSupportUrl insteadof ModuleCustomTrait;
        CCEmodulesTrait::title insteadof ModuleCustomTrait;
        CCEmodulesTrait::menuTitle insteadof ModuleCustomTrait;

        CCEmodulesTrait::resourcesFolder insteadof ModuleCustomTrait;
    }

    protected const ROUTE_URL = '/tree/{tree}/CCEfamily-list';

    // The individual list and family list use the same code/logic.
    // They just display different lists.
    protected bool $families = true;

    protected $master;

    public function __construct(
        ClippingsCartEnhanced $master)
    {
        $this->master = $master;
    }

    /**
     * Initialization.
     *
     * @return void
     */
    public function boot(): void
    {
        Registry::routeFactory()->routeMap()
            ->get(static::class, static::ROUTE_URL, $this);

        // Here is also a good place to register any views (templates) used by the module.
        // This command allows the module to use: view($this->name() . '::', 'fish')
        // to access the file ./resources/views/fish.phtml
        // View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');

        // View::registerCustomView('::lists/CCEfamily-list', $this->name() . '::lists/CCEfamily-list');

    }


    /**
     * How should this module be identified in the control panel, etc.?
     *
     * @return string
     */
    public function title(): string
    {
        /* I18N: Name of a module/list */
        return json_decode('"\u210D"') . "&" . json_decode('"\u210D"') . "wt " . I18N::translate('Families');
    }

    /**
     * A sentence describing what this module does.
     *
     * @return string
     */
    public function description(): string
    {
        /* I18N: Description of the “Families” module */
        return I18N::translate('A list of families.');
    }

    /**
     * CSS class for the URL.
     *
     * @return string
     */
    public function listMenuClass(): string
    {
        return 'menu-list-fam';
    }
}
