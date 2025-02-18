<?php

/**
 * webtrees - clippings cart enhanced
 *
 * Copyright (C) 2025 huhwt. All rights reserved.
 * Copyright (C) 2021 Hermann Hartenthaler. All rights reserved.
 * Copyright (C) 2021 Richard Cissée. All rights reserved.
 *
 * webtrees: online genealogy / web based family history software
 * Copyright (C) 2021-2025 webtrees development team.
 *
 */

declare(strict_types=1);

namespace HuHwt\WebtreesMods\ClippingsCartEnhanced\Traits;

use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Menu;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Tree;

use Jefferson49\Webtrees\Module\ExtendedImportExport\DownloadGedcomWithURL;
use Jefferson49\Webtrees\Module\ExtendedImportExport\ExportGedcomPage;

use Psr\Http\Message\ResponseInterface;

use function redirect;
use function route;
/**
 * Trait CCEcustomModuleConnect - bundling all actions regarding connecting to other custom modules
 */
trait CCEcustomModuleConnect
{
    /**
     * @param -none-
     *
     * @return bool
     * 
     * test if _extended_import_export_ is installed and appropriate version
     */
    private function test_XTE_ () : bool
    {
        $retval = false;
        $this->module_service = new ModuleService();
        $extended_export = $this->module_service->findByName('_extended_import_export_');
        if ($extended_export !== null ) {
            $retval = version_compare($extended_export->customModuleVersion(), '4.2.4', '>=');
        }
        return $retval;
    }
    /**
     * @param Tree $tree
     *
     * @return ResponseInterface
     * 
     * 
     */
    private function route_to_XTE_ (Tree $tree): ResponseInterface
    {
        $params['called_by'] = rawurldecode($_SERVER["REQUEST_URI"]);
        $url = route(ExportGedcomPage::class, [
            'tree_name'              => $tree->name(),
            'export_clippings_cart'  => true,
            'default_gedcom_filter1' => $this->getPreference(DownloadGedcomWithURL::PREF_DEFAULT_GEDCOM_FILTER1, ''),
            'default_gedcom_filter2' => $this->getPreference(DownloadGedcomWithURL::PREF_DEFAULT_GEDCOM_FILTER2, ''),
            'default_gedcom_filter3' => $this->getPreference(DownloadGedcomWithURL::PREF_DEFAULT_GEDCOM_FILTER3, ''),
            // 'target'                 => '_blank',    // EW.H - MOD ... it would be ideal if we could open the url in new tab - 'target' doesn't do it 
        ]);

        return redirect($url);
    }
}