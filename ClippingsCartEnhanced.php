<?php
/*
 * webtrees - clippings cart enhanced
 *
 * based on Vesta module "clippings cart extended"
 *
 * 
 *
 * Copyright (C) 2022 huhwt. All rights reserved.
 * Copyright (C) 2021 Hermann Hartenthaler. All rights reserved.
 * Copyright (C) 2021 Richard Cissée. All rights reserved.
 *
 * webtrees: online genealogy / web based family history software
 * Copyright (C) 2021 webtrees development team.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; If not, see <https://www.gnu.org/licenses/>.
 */

/*
 * tbd
 * ---
 * code: empty cart: show block with "type delete" only if second option is selected
 * code: empty cart: button should be labeled "continue" and not "delete" if third option is selected
 * code: add specific TRAIT module (?)
 * code: show add options only if they will add new elements to the clippings cart otherwise grey them out
 * code: when adding global sets: instead of using radio buttons use select buttons?
 * translation: translate all new strings to German using po/mo
 * issue: new global add function to add longest descendant-ancestor connection in a tree:
 *           calculate for all individuals in the tree the most distant ancestor (this is maybe a list of individuals),
 *           select a pair of two individuals with the greatest distance,
 *           add all their ancestors and descendants (???), remove all the leaves(???)
 * issue: new add function for an individual: add chain to most distant ancestor
 * issue: new global add function to add all records of a tree
 * issue: use GVExport (GraphViz) code for visualization (?)
 * issue: implement webtrees 1 module "branch export" with a starting person and several stop persons/families (stored as profile)
 * issue: new function to add all circles for an individual or a family
 * issue: new action: enhanced list using webtrees standard lists for each type of records
 * idea: use TAM to visualize the hierarchy of location records
 * test: access rights for members and visitors
 * other module - test with all other themes: Rural, Argon, ...
 * other module - admin/control panel module "unconnected individuals": add button to each group "send to clippings cart"
 * other module - custom modul extended family: send filtered INDI and FAM records to clippings cart
 * other module - search: send search results to clippings cart
 * other module - list of persons with one surname: send them to clippings cart
 */
declare(strict_types=1);

namespace HuHwt\WebtreesMods\ClippingsCartEnhanced;

use Aura\Router\Route;
use Aura\Router\RouterContainer;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Http\RequestHandlers\FamilyPage;
use Fisharebest\Webtrees\Http\RequestHandlers\IndividualPage;
use Fisharebest\Webtrees\Http\RequestHandlers\LocationPage;
use Fisharebest\Webtrees\Http\RequestHandlers\MediaPage;
use Fisharebest\Webtrees\Http\RequestHandlers\NotePage;
use Fisharebest\Webtrees\Http\RequestHandlers\RepositoryPage;
use Fisharebest\Webtrees\Http\RequestHandlers\SourcePage;
use Fisharebest\Webtrees\Http\RequestHandlers\SubmitterPage;
use Fisharebest\Localization\Translation;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Location;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\Menu;
use Fisharebest\Webtrees\Note;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Repository;
use Fisharebest\Webtrees\Services\GedcomExportService;
use Fisharebest\Webtrees\Services\LinkedRecordService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Source;
use Fisharebest\Webtrees\Submitter;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\Webtrees;
use Illuminate\Support\Collection;
use Illuminate\Database\Capsule\Manager as DB;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemInterface;
use League\Flysystem\ZipArchive\ZipArchiveAdapter;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;

use Fisharebest\Webtrees\Module\ModuleMenuTrait;
use Fisharebest\Webtrees\Module\ClippingsCartModule;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleMenuInterface;
use Fisharebest\Webtrees\View;
use SebastianBergmann\Type\VoidType;
// use HuHwt\WebtreesMods\TAMchart\TAMaction;

// control functions
use function app;
use function array_filter;
use function array_keys;
use function array_map;
use function array_search;
use function count;
use function array_key_exists;
use function assert;
use function fopen;
use function file_put_contents;
use function in_array;
use function is_string;
use function json_encode;
use function preg_match_all;
use function redirect;
use function rewind;
use function route;
use function str_replace;
use function str_starts_with;
use function stream_get_meta_data;
use function tmpfile;
use function uasort;
use function view;

// string functions
use function strtolower;
use function addcslashes;
use const PREG_SET_ORDER;
/**
 * Class ClippingsCartEnhanced
 */
class ClippingsCartEnhanced extends ClippingsCartModule
                                  implements ModuleCustomInterface, ModuleMenuInterface
{   use ModuleMenuTrait;
    use ModuleCustomTrait;

    // List of const for module administration
    public const CUSTOM_TITLE       = 'Clippings cart enhanced';
    public const CUSTOM_DESCRIPTION = 'Add records from your family tree to the clippings cart and execute an action on them.';
    public const CUSTOM_MODULE      = 'huhwt-cce';
    public const CUSTOM_AUTHOR      = 'EW.H / Hermann Hartenthaler';
    public const CUSTOM_WEBSITE     = 'https://github.com/huhwt/' . self::CUSTOM_MODULE . '/';
    public const CUSTOM_VERSION     = '2.1.7';
    public const CUSTOM_LAST        = 'https://github.com/huhwt/' .
                                      self::CUSTOM_MODULE. '/raw/main/latest-version.txt';

    public const SHOW_RECORDS       = 'Records in clippings cart - Execute an action on them.';
    public const SHOW_ACTIONS       = 'Performed actions fo fill the cart.';

                                      // What to add to the cart?
    private const ADD_RECORD_ONLY        = 'add only this record';
    private const ADD_CHILDREN           = 'add children';
    private const ADD_DESCENDANTS        = 'add descendants';
    private const ADD_PARENT_FAMILIES    = 'add parents';
    private const ADD_SPOUSE_FAMILIES    = 'add spouses';
    private const ADD_ANCESTORS          = 'add ancestors';
    // private const ADD_ANCESTORS_HT       = 'add ancestors, 4 generations for H-Tree';  // EW.H mod ... 
    private const ADD_ANCESTOR_FAMILIES  = 'add families';
    private const ADD_LINKED_INDIVIDUALS = 'add linked individuals';
    // HH.mod - additional actions --
    private const ADD_PARTNER_CHAINS     = 'add partner chains for this individual or a family';
    private const ADD_ALL_PARTNER_CHAINS = 'all partner chains in this tree';
    private const ADD_ALL_CIRCLES        = 'all circles - clean version';
    private const ADD_ALL_LINKED_PERSONS = 'all connected persons in this family tree - Caution: probably very high number of persons!';
    private const ADD_COMPLETE_GED       = 'all persons in this family tree - Caution: probably very high number of persons!';

    // What to execute on records in the clippings cart?
    // EW.H mod ... the second-level-keys are tested for actions in function postExecuteAction()
    private const EXECUTE_ACTIONS = [
        'Download records ...' => [
            'EXECUTE_DOWNLOAD_ZIP'      => '... as GEDCOM zip-file (including media files)',
            'EXECUTE_DOWNLOAD_PLAIN'    => '... as GEDCOM file (all Tags, no media files)',
            'EXECUTE_DOWNLOAD_IF'       => '... as GEDCOM file (only INDI and FAM)',
        ],
        'Visualize records in a diagram ...' => [
            'EXECUTE_VISUALIZE_TAM'     => '... using TAM',
            'EXECUTE_VISUALIZE_LINEAGE' => '... using Lineage',
        ],
    ];

    // What are the options to delete records in the clippings cart?
    private const EMPTY_ALL      = 'all records';
    private const EMPTY_SET      = 'set of records by type';
    private const EMPTY_SELECTED = 'select records to be deleted';

    // Routes that have a record which can be added to the clipboard
    private const ROUTES_WITH_RECORDS = [
        'Individual' => IndividualPage::class,
        'Family'     => FamilyPage::class,
        'Media'      => MediaPage::class,
        'Location'   => LocationPage::class,
        'Note'       => NotePage::class,
        'Repository' => RepositoryPage::class,
        'Source'     => SourcePage::class,
        'Submitter'  => SubmitterPage::class,
    ];

    // Types of records
    // The order of the Xrefs in the Clippings Cart results from the order 
    // of the calls during insertion and in this respect is not separated 
    // according to their origin.
    // This can cause problems when passing to interfaces and functions that 
    // expect a defined sequence of tags.
    // This structure determines the order of the categories in which the 
    // records are displayed or output for further actions ( function getEmptyAction() and showTypes.phtml )
    private const TYPES_OF_RECORDS = [
        'Individual' => Individual::class,
        'Family'     => Family::class,
        'Media'      => Media::class,
        'Location'   => Location::class,
        'Note'       => Note::class,
        'Repository' => Repository::class,
        'Source'     => Source::class,
        'Submitter'  => Submitter::class,
    ];

    // Types of records for further visualizing actions
    // This structure defines the categories which will be 
    // relevant in visualizing tools.
    private const FILTER_RECORDS = [
        'TAM' => [
                'Individual' => Individual::class,
                'Family'     => Family::class,
                ],
        'ONLY_IF' => [
                'Individual' => Individual::class,
                'Family'     => Family::class,
                ],
        ];

    private const FILENAME_DOWNL = 'wtcce';
    private const FILENAME_VIZ = 'wt2VIZ.ged';

    private const VIZ_DSNAME = '';

    /** @var string */
    private string $exportFilenameDOWNLOAD;

    /** @var string */
    private string $exportFilenameVIZ;

    /** @var int The default access level for this module.  It can be changed in the control panel. */
    protected int $access_level = Auth::PRIV_USER;

    /** @var GedcomExportService */
    private GedcomExportService $gedcomExportService;

    /** @var LinkedRecordService */
    private LinkedRecordService $linkedRecordService;

    /** @var UserService */
    private $user_service;

    // /** @var Int */
    // private int $ADD_MAX_GEN;       // EW.H mod ... get only part of tree for TAM-H-Tree

    /** @var int The number of ancestor generations to be added (0 = proband) */
    private int $levelAncestor;

    /** @var int The number of descendant generations to add (0 = proband) */
    private int $levelDescendant;

    // EW.H mod ... Output to TAM
    private const VIZdir           = Webtrees::DATA_DIR . DIRECTORY_SEPARATOR . '_toVIZ';

    /**
     * @var bool We want to have the GEDCOM-Objects exported as array
     */
    private const DO_DUMP_Ritems   = true;
    /**
     * The label ...
     * @var string
     */
    private string $huh;

    /**
     * Check for huhwt/huhwt-wttam done?
     * @var boolean
     */
    private bool $huhwttam_checked;

    /**
     * Check for huhwt/huhwt-wttam done?
     * @var boolean
     */
    private bool $huhwtlin_checked;

    /**
     * Retrieve all Record-Types
     * @var boolean
     */
    private $all_RecTypes;

    /** 
     * ClippingsCartModule constructor.
     *
     * @param GedcomExportService $gedcomExportService
     * @param LinkedRecordService $linkedRecordService
     */
    public function __construct(
        GedcomExportService $gedcomExportService,
        LinkedRecordService $linkedRecordService)
    {
        $this->gedcomExportService = $gedcomExportService;
        $this->levelAncestor       = PHP_INT_MAX;
        $this->levelDescendant     = PHP_INT_MAX;
        $this->exportFilenameDOWNL = self::FILENAME_DOWNL;
        $this->exportFilenameVIZ   = self::FILENAME_VIZ;
        $this->linkedRecordService = $linkedRecordService;
        $this->huh = json_decode('"\u210D"') . "&" . json_decode('"\u210D"') . "wt";
        $this->huhwttam_checked    = false;
        $this->huhwtlin_checked    = false;
        $this->all_RecTypes        = true;
        // EW.H mod ... read TAM-Filename from Session, otherwise: Initialize
        if (Session::has('FILENAME_VIZ')) {
            $this->FILENAME_VIZ = Session::get('FILENAME_VIZ');
        } else {
            $this->FILENAME_VIZ        = 'wt2VIZ';
            Session::put('FILENAME_VIZ', $this->FILENAME_VIZ);
        }
        // // EW.H mod ... read TAM-H-Tree's max Gen from Session, otherwise: Initialize
        // if (Session::has('TAM_HmaxGen')) {
        //     $this->ADD_MAX_GEN = Session::get('TAM_HmaxGen');
        // } else {
        //     $this->ADD_MAX_GEN        = 4;
        //     Session::put('TAM_HmaxGen', $this->ADD_MAX_GEN);
        // }
        parent::__construct($gedcomExportService, $linkedRecordService);

        // EW.H mod ... we want a subdir of Webtrees::DATA_DIR for storing dumps and so on
        // - test for and create it if it not exists
        if(!is_dir(self::VIZdir)){
            //Directory does not exist, so lets create it.
            mkdir(self::VIZdir, 0755);
        }    
    }


    /**
     * A menu, to be added to the main application menu.
     *
     * Show:            show records in clippings cart and allow deleting some of them
     * AddRecord:
     *      AddIndividual:   add individual (this record, parents, children, ancestors, descendants, ...)
     *      AddFamily:       add family record
     *      AddMedia:        add media record
     *      AddLocation:     add location record
     *      AddNote:         add shared note record
     *      AddRepository:   add repository record
     *      AddSource:       add source record
     *      AddSubmitter:    add submitter record
     * Global:          add global sets of records (partner chains, circles)
     * Empty:           delete records in clippings cart
     * Execute:         execute an action on records in the clippings cart (export to GEDCOM file, visualize)
     *
     * @param Tree $tree
     *
     * @return Menu|null
     */
    public function getMenu(Tree $tree): ?Menu
    {
        /** @var ServerRequestInterface $request */
        $request = app(ServerRequestInterface::class);

        $route = $request->getAttribute('route');
        assert($route instanceof Route);

        // clippings cart is an array in the session specific for each tree
        $cart  = Session::get('cart', []);
        $cartAct  = Session::get('cartAct', []);

        $submenus = [$this->addMenuClippingsCart($tree, $cart)];

        $action = array_search($route->name, self::ROUTES_WITH_RECORDS, true);
        if ($action !== false) {
            $submenus[] = $this->addMenuAddThisRecord($tree, $route, $action);
        }

        $submenus[] = $this->addMenuAddGlobalRecordSets($tree);

        if (!$this->isCartEmpty($tree)) {
            $submenus[] = $this->addMenuDeleteRecords($tree);
            $submenus[] = $this->addMenuExecuteAction($tree);
        }

        return new Menu($this->title(), '#', 'menu-clippings', ['rel' => 'nofollow'], $submenus);
    }

    /**
     * @param Tree $tree
     * @param array $cart
     *
     * @return Menu
     */
    private function addMenuClippingsCart (Tree $tree, array $cart): Menu
    {
        $count = count($cart[$tree->name()] ?? []);
        $badge = view('components/badge', ['count' => $count]);

        // return new Menu(I18N::translate('Records in %s ', $this->title()) . $badge,
        return new Menu(I18N::translate('Records in clippings cart') . $badge,
            route('module', [
                'module'      => $this->name(),
                'action'      => 'Show',
                'description' => $this->description(),
                'tree'        => $tree->name(),
            ]), 'menu-clippings-cart', ['rel' => 'nofollow']);
    }

    /**
     * @param Tree $tree
     * @param Route $route
     * @param string $action
     *
     * @return Menu
     */
    private function addMenuAddThisRecord (Tree $tree, Route $route, string $action): Menu
    {
        $xref = $route->attributes['xref'];
        assert(is_string($xref));

        return new Menu(I18N::translate('Add this record to the clippings cart'),
            route('module', [
                'module' => $this->name(),
                'action' => 'Add' . $action,
                'xref'   => $xref,
                'tree'   => $tree->name(),
            ]), 'menu-clippings-add', ['rel' => 'nofollow']);
    }

    /**
     * @param Tree $tree
     *
     * @return Menu
     */
    private function addMenuAddGlobalRecordSets (Tree $tree): Menu
    {
        return new Menu(I18N::translate('Add global record sets to the clippings cart'),
            route('module', [
                'module' => $this->name(),
                'action' => 'Global',
                'tree' => $tree->name(),
            ]), 'menu-clippings-add', ['rel' => 'nofollow']);
    }

    /**
     * @param Tree $tree
     *
     * @return Menu
     */
    private function addMenuDeleteRecords (Tree $tree): Menu
    {
        return new Menu(I18N::translate('Delete records in the clippings cart'),
            route('module', [
            'module' => $this->name(),
            'action' => 'Empty',
            'tree'   => $tree->name(),
            ]), 'menu-clippings-empty', ['rel' => 'nofollow']);
    }

    /**
     * @param Tree $tree
     *
     * @return Menu
     */
    private function addMenuExecuteAction (Tree $tree): Menu
    {
        return new Menu(I18N::translate('Execute an action on records in the clippings cart'),
            route('module', [
                'module' => $this->name(),
                'action' => 'Execute',
                'tree' => $tree->name(),
            ]), 'menu-clippings-download', ['rel' => 'nofollow']);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getShowAction(ServerRequestInterface $request): ResponseInterface
    {
        // $tree = $request->getAttribute('tree');
        $tree = Validator::attributes($request)->tree();
        assert($tree instanceof Tree);

        $recordTypes = $this->collectRecordsInCart($tree, self::TYPES_OF_RECORDS);

        $cartActs = $this->getCartActs($tree);

        return $this->viewResponse($this->name() . '::' . 'showTypes', [
            'module'      => $this->name(),
            'types'       => self::TYPES_OF_RECORDS,
            'recordTypes' => $recordTypes,
            'title'       => I18N::translate('Family tree clippings cart'),
            'header_recs' => I18N::translate(self::SHOW_RECORDS),
            'header_acts' => I18N::translate(self::SHOW_ACTIONS),
            'cartActions' => $cartActs,
            'tree'        => $tree,
            'stylesheet'  => $this->assetUrl('css/cce.css'),
            'javascript'  => $this->assetUrl('js/cce.js'),
        ]);
    }
 
    /**
     * tbd: show options only if they will add new elements to the clippings cart otherwise grey them out
     * tbd: indicate the number of records which will be added by a button
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getAddIndividualAction(ServerRequestInterface $request): ResponseInterface
    {
        // $tree = $request->getAttribute('tree');
        $tree = Validator::attributes($request)->tree();
        assert($tree instanceof Tree);

        // $xref = $request->getQueryParams()['xref'] ?? '';
        $xref = Validator::queryParams($request)->isXref()->string('xref');
        $individual = Registry::individualFactory()->make($xref, $tree);
        $individual = Auth::checkIndividualAccess($individual);
        $name       = $individual->fullName();

        $generationsParms = $request->getQueryParams()['generations'] ?? null;
        // generationsParms must be fetched by explicit extraction because it is optional ...   // EW.H
        // -> (there has been an cce-action with predefined generationParms - it has been abandoned)
        //    (but it might be useful in the future, therefore this option will be kept)
        // Validator makes the queried value mandatory.                                         // EW.H 
        // $generationsParms = Validator::queryParams($request)->integer('generations', null);
        if ($generationsParms) {
            $generations = $generationsParms;
        } else {
            $generations['A'] = $this->countAncestorGenerations($individual);
            $generations['D'] = $this->countDescendantGenerations($individual);
            $generations['Amax'] = $generations['A'];
            $generations['Amin'] = 0;
            $generations['Dmax'] = $generations['D'];
            $generations['Dmin'] = 0;
        }

        if ($individual->sex() === 'F') {
            $txt_ANC = $this->substText('%1$s and her ancestors (up to %2$s generations)', $name, '~~', '~~', 'cce_genA', $generations['A']);
            $txt_ANCF = $this->substText('%1$s, her ancestors and their families (up to %2$s generations)', $name, '~~', '~~', 'cce_genA', $generations['A']);
            $txt_DES = $this->substText('%1$s, her spouses and descendants (up to %2$s generations)', $name, '~~', '~~', 'cce_genD', $generations['D']);
            $options = [
                self::ADD_RECORD_ONLY       => $name,
                self::ADD_PARENT_FAMILIES   => I18N::translate('%s, her parents and siblings', $name),
                self::ADD_SPOUSE_FAMILIES   => I18N::translate('%s, her spouses and children', $name),
                self::ADD_ANCESTORS         => $txt_ANC,
                // self::ADD_ANCESTORS_HT      => I18N::translate('%s and her ancestors, up to 4 generations, for H-Tree', $name),
                self::ADD_ANCESTOR_FAMILIES => $txt_ANCF,
                self::ADD_DESCENDANTS       => $txt_DES,
                self::ADD_LINKED_INDIVIDUALS => I18N::translate('%s and all linked individuals', $name),
            ];
        } else {
            $txt_ANC = $this->substText('%1$s and his ancestors (up to %2$s generations)', $name, '~~', '~~', 'cce_genA', $generations['A']);
            $txt_ANCF = $this->substText('%1$s, his ancestors and their families (up to %2$s generations)', $name, '~~', '~~', 'cce_genA', $generations['A']);
            $txt_DES = $this->substText('%1$s, his spouses and descendants (up to %2$s generations)', $name, '~~', '~~', 'cce_genD', $generations['D']);
            $options = [
                self::ADD_RECORD_ONLY       => $name,
                self::ADD_PARENT_FAMILIES   => I18N::translate('%s, his parents and siblings', $name),
                self::ADD_SPOUSE_FAMILIES   => I18N::translate('%s, his spouses and children', $name),
                self::ADD_ANCESTORS         => $txt_ANC,
                // self::ADD_ANCESTORS_HT      => I18N::translate('%s and his ancestors, up to 4 generations, for H-Tree', $name),
                self::ADD_ANCESTOR_FAMILIES => $txt_ANCF,
                self::ADD_DESCENDANTS       => $txt_DES,
                self::ADD_LINKED_INDIVIDUALS => I18N::translate('%s and all linked individuals', $name),
            ];
        }
        $sp_families = $individual->spouseFamilies();
        if ( count($sp_families) > 0) {
            $options[self::ADD_PARTNER_CHAINS] = I18N::translate('the partner chains %s belongs to', $name);
        }

        $title = I18N::translate('Add %s to the clippings cart', $name);

        return $this->viewResponse($this->name() . '::' . 'add-options', [
            'options'     => $options,
            'record'      => $individual,
            'generations' => $generations,
            'title'       => $title,
            'tree'        => $tree,
            'stylesheet'    => $this->assetUrl('css/cce.css'),
            'javascript'    => $this->assetUrl('js/cce.js'),
    ]);
    }

    /**
     * Put class-name into options-text for later on increment/decrement value in view
     * 
     * @param string        $text
     * @param string        $subst1
     * @param string        $subst2
     * @param string        $tosubst
     * @param string        $cname
     * @param int           $value
     * 
     * @return string
     */
    private function substText($text, $subst1, $subst2, $tosubst, $cname, $value)
    {
        $txt_t = I18N::translate($text, $subst1, $subst2);
        $txt_c = '<span class="' . $cname . '">' . $value . '</span>';
        $txt_r = str_replace($tosubst, $txt_c, $txt_t);
        return $txt_r;
    }
    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postAddIndividualAction(ServerRequestInterface $request): ResponseInterface
    {
        // $tree = $request->getAttribute('tree');
        $tree = Validator::attributes($request)->tree();
        assert($tree instanceof Tree);

        $params = (array) $request->getParsedBody();

        $xref        = $params['xref'] ?? '';
        $option      = $params['option'] ?? '';
        $generationsA = $params['generationsA'] ?? '';
        $generationsD = $params['generationsD'] ?? '';
        if ($generationsA !== '') {
            $this->levelAncestor = (int)$generationsA;
        }
        if ($generationsD !== '') {
            $this->levelDescendant = (int)$generationsD;
        }

        $individual = Registry::individualFactory()->make($xref, $tree);
        $individual = Auth::checkIndividualAccess($individual);

        $_dname = 'wtVIZ-DATA~INDI_' . $xref;
        $this->putVIZdname($_dname);

        switch ($option) {
            case self::ADD_RECORD_ONLY:
                $this->cartAct($tree, 'INDI', $xref);
                $this->addIndividualToCart($individual);
                break;

            case self::ADD_PARENT_FAMILIES:
                $this->cartAct($tree, 'INDI_PARENT_FAM', $xref);
                foreach ($individual->childFamilies() as $family) {
                    $this->addFamilyAndChildrenToCart($family);
                }
                break;

            case self::ADD_SPOUSE_FAMILIES:
                $this->cartAct($tree, 'INDI_SPOUSE_FAM', $xref);
                foreach ($individual->spouseFamilies() as $family) {
                    $this->addFamilyAndChildrenToCart($family);
                }
                break;

            case self::ADD_ANCESTORS:
                $caDo = $xref . '|' . $this->levelAncestor;
                $this->cartAct($tree, 'INDI_ANCESTORS', $caDo);
                $this->addAncestorsToCart($individual, $this->levelAncestor);
                break;

            case self::ADD_ANCESTOR_FAMILIES:
                $caDo = $xref . '|' . $this->levelAncestor;
                $this->cartAct($tree, 'INDI_ANCESTOR_FAMILIES', $caDo);
                $this->addAncestorFamiliesToCart($individual, $this->levelAncestor);
                break;

            case self::ADD_DESCENDANTS:
                $caDo = $xref . '|' . $this->levelDescendant;
                $this->cartAct($tree, 'INDI_DESCENDANTS', $caDo);
                foreach ($individual->spouseFamilies() as $family) {
                    $this->addFamilyAndDescendantsToCart($family, $this->levelDescendant);
                }
                break;

            case self::ADD_PARTNER_CHAINS:
                $this->cartAct($tree, 'INDI_PARTNER_CHAINS', $xref);
                $this->addPartnerChainsToCartIndividual($individual, $individual->spouseFamilies()[0]);
                break;

            case self::ADD_LINKED_INDIVIDUALS:
                $this->cartAct($tree, 'INDI_LINKED_INDIVIDUALS', $xref);
                $_dname = 'wtVIZ-DATA~all linked_' . $xref;
                $this->putVIZdname($_dname);
                $this->addAllLinked($tree, $xref);
                break;
        
    
        }

        return redirect($individual->url());
    }

    private function cartAct(Tree $tree, $action, $xref)
    {
        $cartAct = Session::get('cartAct', []);
        $caction = $action . "~" . $xref;
        if (($cartAct[$tree->name()][$caction] ?? false) === false) {
            $cartAct[$tree->name()][$caction] = true;
            Session::put('cartAct', $cartAct);
        }

    }

    private function getCartActs(Tree $tree)
    {
        $cartAct = Session::get('cartAct', []);
        $cartacts = array_keys($cartAct[$tree->name()] ?? []);
        $cartacts = array_map('strval', $cartacts);           // PHP converts numeric keys to integers.
        return $cartacts;

    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getAddFamilyAction(ServerRequestInterface $request): ResponseInterface
    {
        // $tree = $request->getAttribute('tree');
        $tree = Validator::attributes($request)->tree();
        assert($tree instanceof Tree);

        // $xref = $request->getQueryParams()['xref'] ?? '';
        $xref = Validator::queryParams($request)->isXref()->string('xref');

        $family = Registry::familyFactory()->make($xref, $tree);
        $family = Auth::checkFamilyAccess($family);
        $name   = $family->fullName();

        $options = [
            self::ADD_RECORD_ONLY => $name,
            /* I18N: %s is a family (husband + wife) */
            self::ADD_CHILDREN    => I18N::translate('%s and their children', $name),
            /* I18N: %s is a family (husband + wife) */
            self::ADD_DESCENDANTS => I18N::translate('%s and their descendants', $name),
            /* I18N: %s is a family (husband + wife) */
            self::ADD_PARTNER_CHAINS => I18N::translate('%s and the partner chains they belong to', $name),
        ];

        /* I18N: %s is a family (husband + wife) */
        $title = I18N::translate('Add %s to the clippings cart', $name);

        return $this->viewResponse($this->name() . '::' . 'add-options', [
            'options'       => $options,
            'record'        => $family,
            'title'         => $title,
            'tree'          => $tree,
            'stylesheet'    => $this->assetUrl('css/cce.css'),
            'javascript'    => $this->assetUrl('js/cce.js'),
        ]);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postAddFamilyAction(ServerRequestInterface $request): ResponseInterface
    {
        // $tree = $request->getAttribute('tree');
        $tree = Validator::attributes($request)->tree();
        assert($tree instanceof Tree);

        $params = (array) $request->getParsedBody();

        $xref   = $params['xref'] ?? '';
        $option = $params['option'] ?? '';

        $family = Registry::familyFactory()->make($xref, $tree);
        $family = Auth::checkFamilyAccess($family);

        $_dname = 'wtVIZ-DATA~FAM_' . $xref;
        $this->putVIZdname($_dname);

        switch ($option) {
            case self::ADD_RECORD_ONLY:
                $this->cartAct($tree, 'FAM', $xref);
                $this->addFamilyToCart($family);
                break;

            case self::ADD_CHILDREN:
                $this->cartAct($tree, 'FAM_AND_CHILDREN', $xref);
                $this->addFamilyAndChildrenToCart($family);
                break;

            case self::ADD_DESCENDANTS:
                $this->cartAct($tree, 'FAM_AND_DESCENDANTS', $xref);
                $this->addFamilyAndDescendantsToCart($family);
                break;

            case self::ADD_PARTNER_CHAINS:
                $this->cartAct($tree, 'FAM_PARTNER_CHAINS', $xref);
                $this->addPartnerChainsToCartFamily($family);
                break;
        }

        return redirect($family->url());
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postAddLocationAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();

        $xref = $request->getQueryParams()['xref'] ?? '';

        $location = Registry::locationFactory()->make($xref, $tree);
        $location = Auth::checkLocationAccess($location);

        $this->cartAct($tree, 'LOC', $xref);

        $this->addLocationToCart($location);

        return redirect($location->url());
    }


    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postAddMediaAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();

        $xref = $request->getQueryParams()['xref'] ?? '';

        $media = Registry::mediaFactory()->make($xref, $tree);
        $media = Auth::checkMediaAccess($media);

        $this->cartAct($tree, 'MEDIA', $xref);

        $this->addMediaToCart($media);

        return redirect($media->url());
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postAddNoteAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();

        $xref = $request->getQueryParams()['xref'] ?? '';

        $note = Registry::noteFactory()->make($xref, $tree);
        $note = Auth::checkNoteAccess($note);

        $this->cartAct($tree, 'NOTE', $xref);

        $this->addNoteToCart($note);

        return redirect($note->url());
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postAddRepositoryAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();

        $xref = $request->getQueryParams()['xref'] ?? '';

        $repository = Registry::repositoryFactory()->make($xref, $tree);
        $repository = Auth::checkRepositoryAccess($repository);

        $this->cartAct($tree, 'REPO', $xref);

        $this->addRepositoryToCart($repository);

        foreach ($this->linked_record_service->linkedSources($repository) as $source) {
            $this->addSourceToCart($source);
        }

        return redirect($repository->url());
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postAddSourceAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();

        $params = (array) $request->getParsedBody();

        $xref   = $params['xref'] ?? '';
        $option = $params['option'] ?? '';

        $source = Registry::sourceFactory()->make($xref, $tree);
        $source = Auth::checkSourceAccess($source);

        $caDo = $xref;
        if ($option === self::ADD_LINKED_INDIVIDUALS) {
            $caDo = $caDo . '|ali';
        }
        $this->cartAct($tree, 'SOUR', $caDo);

        $this->addSourceToCart($source);

        if ($option === self::ADD_LINKED_INDIVIDUALS) {
            foreach ($this->linked_record_service->linkedIndividuals($source) as $individual) {
                $this->addIndividualToCart($individual);
            }
            foreach ($this->linked_record_service->linkedFamilies($source) as $family) {
                $this->addFamilyToCart($family);
            }
        }

        return redirect($source->url());
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postAddSubmitterAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();

        $xref = $request->getQueryParams()['xref'] ?? '';

        $submitter = Registry::submitterFactory()->make($xref, $tree);
        $submitter = Auth::checkSubmitterAccess($submitter);

        $this->cartAct($tree, 'SUBM', $xref);

        $this->addSubmitterToCart($submitter);

        return redirect($submitter->url());
    }
    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
	 *
	 * HH.mod - additional action
     */
    public function getGlobalAction(ServerRequestInterface $request): ResponseInterface
    {
        // $tree = $request->getAttribute('tree');
        $tree = Validator::attributes($request)->tree();
        assert($tree instanceof Tree);

        $options[self::ADD_ALL_PARTNER_CHAINS] = I18N::translate('all partner chains in this tree');
        $options[self::ADD_ALL_CIRCLES]        = I18N::translate('all circles of individuals in this tree');
        // $options[self::ADD_ALL_LINKED_PERSONS] = I18N::translate('all connected persons in this family tree - Caution: probably very high number of persons!');
        $options[self::ADD_COMPLETE_GED]       = I18N::translate('all persons/families in this family tree - Caution: probably very high number of persons!');

        $title = I18N::translate('Add global record sets to the clippings cart');
        $label = I18N::translate('Add to the clippings cart');

        return $this->viewResponse($this->name() . '::' . 'global', [
            'module'        => $this->name(),
            'options'       => $options,
            'title'         => $title,
            'label'         => $label,
            'tree'          => $tree,
        ]);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     * HH.mod - additional action
     */

    public function postGlobalAction(ServerRequestInterface $request): ResponseInterface
    {
        // $tree = $request->getAttribute('tree');
        $tree = Validator::attributes($request)->tree();
        assert($tree instanceof Tree);

        $params = (array) $request->getParsedBody();
        $option = $params['option'] ?? '';

        switch ($option) {
            case self::ADD_ALL_PARTNER_CHAINS:
                $this->cartAct($tree, 'ALL_PARTNER_CHAINS', 'all');
                $_dname = 'wtVIZ-DATA~all partner chains';
                $this->putVIZdname($_dname);
                $this->addPartnerChainsGlobalToCart($tree);
                break;

            case self::ADD_COMPLETE_GED:
                $this->cartAct($tree, 'COMPLETE', 'GED');
                $_dname = 'wtVIZ-DATA~complete GED';
                $this->putVIZdname($_dname);
                $this->addCompleteGEDtoCart($tree);
                break;

            // case self::ADD_ALL_LINKED_PERSONS:
            //     $this->addAllLinked($tree);
            //     break;
    
            default;
            case self::ADD_ALL_CIRCLES:
                $this->cartAct($tree, 'ALL_CIRCLES', 'all');
                $_dname = 'wtVIZ-DATA~all circles';
                $this->putVIZdname($_dname);
                $this->addAllCirclesToCart($tree);
                break;
        }

        $url = route('module', [
            'module'      => $this->name(),
            'action'      => 'Show',
            'description' => $this->description(),
            'tree'        => $tree->name(),
        ]);

        return redirect($url);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getExecuteAction(ServerRequestInterface $request): ResponseInterface
    {
        //dependency check
        if (!$this->huhwttam_checked) {
            $ok = class_exists("HuHwt\WebtreesMods\TAMchart\TAMaction", true);
            if (!$ok) {
                $wttam_link = '(https://github.com/huhwt/huhwt-wttam)';
                $wttam_missing = I18N::translate('Missing dependency - Install %s!', 'TAM'); // EW.H - Mod ... make warning
                $theMessage = $wttam_missing . ' -> ' . $wttam_link;
                FlashMessages::addMessage($theMessage);
            }
            $this->huhwttam_checked = true;
        }

        if (!$this->huhwtlin_checked) {
            $ok = class_exists("HuHwt\WebtreesMods\LINchart\LINaction", true);
            if (!$ok) {
                $wtlin_link = '(https://github.com/huhwt/huhwt-wtlin)';
                $wtlin_missing = I18N::translate('Missing dependency - Install %s!', 'LIN'); // EW.H - Mod ... make warning
                $theMessage = $wtlin_missing . ' -> ' . $wtlin_link;
                FlashMessages::addMessage($theMessage);
            }
            $this->huhwtlin_checked = true;
        }

        // $tree = $request->getAttribute('tree');
        $tree = Validator::attributes($request)->tree();
        assert($tree instanceof Tree);
        $user  = $request->getAttribute('user');

        $first = ' -> Webtrees Standard action';
        $options_arr = array();
        foreach (self::EXECUTE_ACTIONS as $opt => $actions) {
            $actions_arr = array();
            foreach ($actions as $action => $text) {
                $atxt = I18N::translate($text);
                $atxt = $atxt . $first;
                $first = '';
                $actions_arr[$action] = $atxt;
            }
            $options_arr[$opt] = $actions_arr;
        }

        $title = I18N::translate('Execute an action on records in the clippings cart');
        $label = I18N::translate('Privatize options');

        return $this->viewResponse($this->name() . '::' . 'execute', [
            'options'    => $options_arr,
            'title'      => $title,
            'label'      => $label,
            'is_manager' => Auth::isManager($tree, $user),
            'is_member'  => Auth::isMember($tree, $user),
            'module'     => $this->name(),
            'tree'       => $tree,
        ]);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postExecuteAction(ServerRequestInterface $request): ResponseInterface
    {
        // $tree = $request->getAttribute('tree');
        $tree = Validator::attributes($request)->tree();
        assert($tree instanceof Tree);

        $params = (array) $request->getParsedBody();
        $option = $params['option'] ?? '';
        $privatizeExport = $params['privatize_export'] ?? '';

        switch ($option) {
        // We want to download gedcom as zip ...
            // ... we use the default download-action
            case 'EXECUTE_DOWNLOAD_ZIP':
                $url = route('module', [
                    'module' => parent::name(),
                    'action' => 'DownloadForm',
                    'tree'   => $tree->name(),
                ]);
                $redobj = redirect($url);
                return redirect($url);
                break;

        // From hereon we are dealing with plain textual gedcom 

            // all kinds of records in CCE - download as file
            case 'EXECUTE_DOWNLOAD_PLAIN':
                return $this->cceDownloadAction($request, 'PLAIN', 'DOWNLOAD');
                break;

            // only INDI and FAM records from CCE - download as file
            case 'EXECUTE_DOWNLOAD_IF':
                return $this->cceDownloadAction($request, 'ONLY_IF', 'DOWNLOAD');
                break;

            // only INDI and FAM records - postprocessing in TAM
            case 'EXECUTE_VISUALIZE_TAM':
                return $this->cceDownloadAction($request, 'ONLY_IF', 'VIZ=TAM');
                break;

            // only INDI and FAM records - postprocessing in LINEAGE
            case 'EXECUTE_VISUALIZE_LINEAGE':
                return $this->cceDownloadAction($request, 'ONLY_IF', 'VIZ=LINEAGE');
                break;
            
            default;
                break;

        }
        $url = route('module', [
            'module' => $this->name(),
            'action' => 'Show',
            'tree'   => $tree->name(),
        ]);
        return redirect($url);
    }

    /**
     * postprocessing GEDCOM:
     * - download as plain file 
     *   - complete as is from ClippingsCart
     *   - reduced, only INDI and FAM
     * - preparation for and call of VIZ=TAM
     * - preparation for and call of VIZ=LINEAGE
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     *
     * @throws \League\Flysystem\FileExistsException
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function cceDownloadAction(ServerRequestInterface $request, string $todo, string $action): ResponseInterface
    {
        // $tree = $request->getAttribute('tree');
        $tree = Validator::attributes($request)->tree();
        assert($tree instanceof Tree);

        $params = (array) $request->getParsedBody();
        $accessLevel = $this->getAccessLevel($params, $tree);
        $encoding = 'UTF-8';
        $line_endings = Validator::parsedBody($request)->isInArray(['CRLF', 'LF'])->string('line_endings');

        // there may be the situation that cart is emptied in meanwhile
        // due to session timeout and setting up a new session or because of
        // browser go-back after a empty-all action
        if ($this->isCartEmpty($tree)) {
            $url = route('module', [
                'module' => $this->name(),
                'action' => 'Show',
                'tree'   => $tree->name(),
            ]);
            return redirect($url);
        }

        $recordTypes = $this->collectRecordKeysInCart($tree, self::TYPES_OF_RECORDS);
        // keep only XREFs used by Individual or Family records
        if ( $todo == 'ONLY_IF' ) {
            $recordFilter = self::FILTER_RECORDS['ONLY_IF'];
            $recordTexecs = array_intersect_key($recordTypes, $recordFilter);
            $recordTypes = $recordTexecs;
        }
        // prepare list of remaining xrefs - unordered but separated by types
        $xrefs = [];
        foreach ($recordTypes as $key => $Txrefs) {
            foreach ($Txrefs as $xref) {
                $xrefs[] = $xref;
            }
        }

        $records = $this->getRecordsForExport($tree, $xrefs, $accessLevel);

        $tmp_stream = fopen('php://temp', 'wb+');

        if ($tmp_stream === false) {
            throw new RuntimeException('Failed to create temporary stream');
        }

        $this->gedcomExportService->export($tree, false, $encoding, $accessLevel, '', $records);
        rewind($tmp_stream);

        // Use a stream, so that we do not have to load the entire file into memory.
        $stream_factory = app(StreamFactoryInterface::class);
        assert($stream_factory instanceof StreamFactoryInterface);

        /**
         *  We want to download the plain gedcom ...
         */

        if ( $action == 'DOWNLOAD' ) {
            $http_stream = $stream_factory->createStreamFromResource($tmp_stream);

            $download_filename = $this->exportFilenameDOWNL;
            if ( $todo == 'ONLY_IF' ) {
                $download_filename .= '_IF_';
            }
            $download_filename .= '(' . $xrefs[0] . ')';

            return $this->gedcomExportService->downloadResponse($tree, false, $encoding, 'none', $line_endings, $download_filename, 'gedcom', $records);

        }

        /**
         *  We want to postprocess the gedcom in a Vizualising-Tool ...
         * 
         *  ... the record objects must be transformed to json
         */

        // Transform record-Objects to simple php-Array-Items
        $o_items = [];
        foreach ($records as $ritem) {
            $o_items[] = $ritem;
        }
        $r_items = (array)$o_items;

        if ( self::DO_DUMP_Ritems ) {
            // We want the php-Array-Items dumped
            $arr_items = array();
            $ie = count($r_items);
            for ( $i = 0; $i < $ie; $i++) {
                $xrefi = $xrefs[$i];
                $arr_items[$xrefi] = $r_items[$i];
            }
            $this->dumpArray($arr_items, $action . 'records');
        }

        $r_string = implode("\n", $r_items);
        $arr_string = array();
        $arr_string["gedcom"] = $r_string;

        // We want to have the gedcom as external file too
        $this->dumpArray($arr_string,  $action . 'gedcom');

        // Encode the array into a JSON string.
        $encodedString = json_encode($arr_string);

        switch ($action) {

            case 'VIZ=TAM':
                $ok = class_exists("HuHwt\WebtreesMods\TAMchart\TAMaction", true);
                if ( $ok ) {
                    // Save the JSON string to SessionStorage.
                    Session::put('wt2TAMgedcom', $encodedString);
                    Session::put('wt2TAMaction', 'wt2TAMgedcom');
                    // Switch over to TAMaction-Module
                    // TODO : 'module' is hardcoded - how to get the name from foreign PHP-class 'TAMaction'?
                    $url = route('module', [
                        'module' => '_huhwt-wttam_',
                        'action' => 'TAM',
                        'actKey' => 'wt2TAMaction',
                        'tree'   => $tree->name(),
                    ]);
                    return redirect($url);
                }
                break;

            case 'VIZ=LINEAGE':
                $ok = class_exists("HuHwt\WebtreesMods\LINchart\LINaction", true);
                if ( $ok ) {
                    Session::put('wt2LINgedcom', $encodedString);
                    Session::put('wt2LINaction', 'wt2LINgedcom');
                    Session::put('wt2LINxrefsI', $recordTypes['Individual']);
                    // Switch over to TAMaction-Module
                    // TODO : 'module' is hardcoded - how to get the name from foreign PHP-class 'TAMaction'?
                    $url = route('module', [
                        'module' => '_huhwt-wtlin_',
                        'action' => 'LIN',
                        'actKey' => 'wt2LINaction',
                        'tree'   => $tree->name(),
                    ]);
                    return redirect($url);
                }
                break;

        }
        // We try to execute something that is not known by now ...
        FlashMessages::addMessage(I18N::translate("You have tried '%s' - it is not implemented yet.", e($action)));
        return redirect((string) $request->getUri());
    }

    /**
     * delete all records in the clippings cart or delete a set grouped by type of records
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getEmptyAction(ServerRequestInterface $request): ResponseInterface
    {
        // $tree = $request->getAttribute('tree');
        $tree = Validator::attributes($request)->tree();
        assert($tree instanceof Tree);

        $title = I18N::translate('Delete all records, a set of records of the same type, or selected records');
        $label = I18N::translate('Delete');
        $labelType = I18N::translate('Delete all records of a type');
        $recordTypes = $this->countRecordTypesInCart($tree, self::TYPES_OF_RECORDS);

        $plural = I18N::translate(self::EMPTY_ALL) . ' ' . $badge = view('components/badge', ['count' => $recordTypes['all']]);
        $options[self::EMPTY_ALL] = I18N::plural('this record', $plural, $recordTypes['all']);
        unset($recordTypes['all']);

        $selectedTypes = [];
        if (count($recordTypes) > 1) {
            // $recordTypesList = implode(', ', array_keys($recordTypes));
            $options[self::EMPTY_SET] = I18N::translate(self::EMPTY_SET) . ':'; // . $recordTypesList;
            $options[self::EMPTY_SELECTED] = I18N::translate(self::EMPTY_SELECTED);
            $i = 0;
            foreach ($recordTypes as $type => $count) {
                $selectedTypes[$i] = 0;
                $i++;
            }
        } else {
            $headingTypes = '';
        }

        return $this->viewResponse($this->name() . '::' . 'empty', [
            'module'         => $this->name(),
            'options'        => $options,
            'title'          => $title,
            'label'          => $label,
            'labelType'      => $labelType,
            'recordTypes'    => $recordTypes,
            'selectedTypes'  => $selectedTypes,
            'tree'           => $tree,
        ]);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postEmptyAction(ServerRequestInterface $request): ResponseInterface
    {
        // $tree = $request->getAttribute('tree');
        $tree = Validator::attributes($request)->tree();
        assert($tree instanceof Tree);

        $params = (array) $request->getParsedBody();
        $option = $params['option'] ?? '';

        switch ($option) {
            case self::EMPTY_ALL:
                $cart = Session::get('cart', []);
                $cart[$tree->name()] = [];
                Session::put('cart', $cart);
                $cartAct = Session::get('cartAct', []);
                $cartAct[$tree->name()] = [];
                Session::put('cartAct', $cartAct);
                $url = route('module', [
                    'module'      => $this->name(),
                    'action'      => 'Show',
                    'description' => $this->description(),
                    'tree'        => $tree->name(),
                ]);
                break;

            case self::EMPTY_SET:
                // tbd
                $this->doEmpty_SetAction($tree, $params);
                $url = route('module', [
                    'module'      => $this->name(),
                    'action'      => 'Show',
                    'description' => $this->description(),
                    'tree'        => $tree->name(),
                ]);
                break;

            default;
            case self::EMPTY_SELECTED:
                $txt_option = I18N::translate($option);
                FlashMessages::addMessage(I18N::translate("You have tried '%s' - it is not implemented yet.", e($txt_option)));
                return redirect((string) $request->getUri());
                break;
        }

        return redirect($url);
    }

    /**
     * delete selected types of record from the clippings cart
     *
     * @param Tree  $tree
     * @param array $params
     *
     */
    public function doEmpty_SetAction(Tree $tree, array $params): void
    {
        $recordTypes = $this->collectRecordKeysInCart($tree, self::TYPES_OF_RECORDS);
        $p_len = count($params) - 1;                            // params[0] is 'options'
        $records_Empty = array_slice($params, 1, $p_len, true); // we need remaining part of params
        foreach ($recordTypes as $key => $class) {              // test if record types have do been deleted ...
            if (array_key_exists($key, $records_Empty)) {
                unset($recordTypes[$key]);                      // remove xrefs-chain from actual known xrefs
            }
        }
        $newCart = [];
        foreach ($recordTypes as $key => $xrefs) {              // prepare list of remaining xrefs
            foreach ($xrefs as $xref) {
                $newCart[] = $xref;
            }
        }
        $cart = Session::get('cart', []);
        $xrefs = array_keys($cart[$tree->name()] ?? []);
        $xrefs = array_map('strval', $xrefs);           // PHP converts numeric keys to integers.
        $_tree = $tree->name();
        foreach ($xrefs as $xref) {
            if (!in_array($xref, $newCart)) {                   // test if xref is already wanted
                unset($cart[$_tree][$xref]);
            }
        }
        Session::put('cart', $cart);
    }

    /**
     * delete one record from the clippings cart
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postRemoveAction(ServerRequestInterface $request): ResponseInterface
    {
        // $tree = $request->getAttribute('tree');
        $tree = Validator::attributes($request)->tree();
        assert($tree instanceof Tree);

        // $xref = $request->getQueryParams()['xref'] ?? '';
        $xref = Validator::queryParams($request)->isXref()->string('xref');

        $cart = Session::get('cart', []);
        unset($cart[$tree->name()][$xref]);
        Session::put('cart', $cart);

        $url = route('module', [
            'module'      => $this->name(),
            'action'      => 'Show',
            'description' => $this->description(),
            'tree'        => $tree->name(),
        ]);

        return redirect($url);
    }

    /**
     * delete one record from the clippings cart
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postCartActRemoveAction(ServerRequestInterface $request): ResponseInterface
    {
        // $tree = $request->getAttribute('tree');
        $tree = Validator::attributes($request)->tree();
        assert($tree instanceof Tree);

        $cact = $request->getQueryParams()['cartact'] ?? '';

        $cartAct = Session::get('cartAct', []);
        unset($cartAct[$tree->name()][$cact]);
        Session::put('cartAct', $cartAct);

        $doRebuild = new RebuildCart($tree, $cartAct[$tree->name()]);
        $doRebuild->rebuild();
        
        $url = route('module', [
            'module'      => $this->name(),
            'action'      => 'Show',
            'description' => $this->description(),
            'tree'        => $tree->name(),
        ]);

        return redirect($url);
    }

    /**
     * @param Tree $tree
     *
     * @return bool
     */
    private function isCartEmpty(Tree $tree): bool
    {
        $cart     = Session::get('cart', []);
        $contents = $cart[$tree->name()] ?? [];
        $isEmpty  = ($contents === []);

        if ( $isEmpty ) {
            $cartAct = Session::get('cartAct', []);
            $cartAct[$tree->name()] = [];
            Session::put('cartAct', $cartAct);
        }

        return $isEmpty;
    }

    /**
     * get access level based on selected option and user level
     *
     * @param array $params
     * @param Tree $tree
     * @return int
     */
    private function getAccessLevel(array $params, Tree $tree): int
    {
        $privatizeExport = $params['privatize_export'] ?? 'none';

        if ($privatizeExport === 'none' && !Auth::isManager($tree)) {
            $privatizeExport = 'member';
        } elseif ($privatizeExport === 'gedadmin' && !Auth::isManager($tree)) {
            $privatizeExport = 'member';
        } elseif ($privatizeExport === 'user' && !Auth::isMember($tree)) {
            $privatizeExport = 'visitor';
        }

        switch ($privatizeExport) {
            case 'gedadmin':
                return Auth::PRIV_NONE;
            case 'user':
                return Auth::PRIV_USER;
            case 'visitor':
                return Auth::PRIV_PRIVATE;
            case 'none':
            default:
                return Auth::PRIV_HIDE;
        }
    }

    /**
     * add all members of partner chains in a tree to the clippings cart (spouses or partners of partners)
     *
     * @param Tree $tree
     */
    protected function addPartnerChainsGlobalToCart(Tree $tree): void
    {
        $partnerChains = new PartnerChainsGlobal($tree, ['HUSB', 'WIFE']);
        // ignore all standard families (chains have at least 3 partners)
        foreach ($partnerChains->getFamilyCount() as $family => $count) {
            if ($count > 2) {
                $familyObject = Registry::familyFactory()->make($family, $tree);
                if ($familyObject instanceof Family && $familyObject->canShow()) {
                    $this->addFamilyToCart($familyObject);
                }
            }
        }
    }

    /**
     * add all persons and families in this family tree
     *
     * @param Tree $tree
     */
    protected function addCompleteGEDtoCart(Tree $tree): void
    {
        $xrefsIF = new CompleteGED($tree);

        // we want only INDI - switch all_RecTypes temporarly

        $all_RT = $this->all_RecTypes;
        $this->all_RecTypes = false;

        // put collected xrefs to cart

        foreach ($xrefsIF->getXrefsI() as $xref) {
            $object = Registry::individualFactory()->make($xref, $tree);
            if ($object instanceof Individual) {
                if ($object->canShow()) {
                    $this->addIndividualToCart($object);
                }
            }
        }
        foreach ($xrefsIF->getXrefsF() as $xref) {
            $object = Registry::familyFactory()->make($xref, $tree);
            if ($object instanceof Family && $object->canShow()) {
                $this->addFamilyWithoutSpousesToCart($object);
            }
        }

        $this->all_RecTypes = $all_RT;
    }

    /**
     * @param Individual $indi
     * @param Family $family
     * @return void
     */
    protected function addPartnerChainsToCartIndividual(Individual $indi, Family $family): void
    {
        $partnerChains = new PartnerChains($indi, $family);
        $root = $partnerChains->getChainRootNode();

        if ($partnerChains->countPartnerChains($root->chains) > 0) {
            $this->addIndividualToCart($root->indi);
            $this->addFamilyToCart($root->fam);
            foreach ($root->chains as $chain) {
                $this->addPartnerChainsToCartRecursive($chain);
            }
        }
    }

    /**
     * @param Family $family
     * @return void
     */
    protected function addPartnerChainsToCartFamily(Family $family): void
    {
        if ($family->husband() instanceof Individual) {
            $this->addPartnerChainsToCartIndividual($family->husband(), $family);
        } elseif ($family->wife() instanceof Individual) {
            $this->addPartnerChainsToCartIndividual($family->wife(), $family);
        }
    }

    /**
     * @param object $node partner chain node
     * @return void
     */
    protected function addPartnerChainsToCartRecursive(object $node): void
    {
        if ($node && $node->indi instanceof Individual) {
            $this->addIndividualToCart($node->indi);
            $this->addFamilyToCart($node->fam);
            foreach ($node->chains as $chain) {
                $this->addPartnerChainsToCartRecursive($chain);
            }
        }
    }

    /**
     * add all circles (closed loops) of individuals in a tree to the clippings cart
     * by adding individuals and their families without spouses to the clippings cart
     *
     * @param Tree $tree
     */
    protected function addAllCirclesToCart(Tree $tree): void
    {
        $circles = new AncestorCircles($tree, ['FAMS', 'FAMC','ALIA']);
        foreach ($circles->getXrefs() as $xref) {
            $object = Registry::individualFactory()->make($xref, $tree);
            if ($object instanceof Individual) {
                if ($object->canShow()) {
                    $this->addIndividualToCart($object);
                }
            } else {
                $object = Registry::familyFactory()->make($xref, $tree);
                if ($object instanceof Family && $object->canShow()) {
                    $this->addFamilyWithoutSpousesToCart($object);
                }
            }
        }
    }

    /**
     * add all linked individuals in a tree to the clippings cart
     * by adding individuals and their families without spouses to the clippings cart
     *
     * @param Tree $tree
     */
    protected function addAllLinked(Tree $tree, $xref = null): void
    {
        $allconns = new AllConnected($tree, ['FAMS', 'FAMC', 'ALIA', 'ASSO', '_ASSO'], $xref);
        foreach ($allconns->getXrefs() as $xref) {
            $object = Registry::individualFactory()->make($xref, $tree);
            if ($object instanceof Individual) {
                if ($object->canShow()) {
                    $this->addIndividualToCart($object);
                }
            } else {
                $object = Registry::familyFactory()->make($xref, $tree);
                if ($object instanceof Family && $object->canShow()) {
                    $this->addFamilyWithoutSpousesToCart($object);
                }
            }
        }
    }

    /**
     * @param Family $family
     */
    protected function addFamilyToCart(Family $family): void
    {
        // if ($addAct) 
            // $this-cartAct($family->tree(),"ADD_FAM~",  $family->xref());

        foreach ($family->spouses() as $spouse) {
            $this->addIndividualToCart($spouse);
        }
        $this->addFamilyWithoutSpousesToCart($family);

        $this->addMediaLinksToCart($family);

        if ( $this->all_RecTypes) {                                // EW.H mod ...
            $this->addFamilyOtherRecordsToCart($family);
        }
    }

    /**
     * @param Individual $individual
     */
    protected function addIndividualToCart(Individual $individual): void
    {
        $cart = Session::get('cart', []);
        $tree = $individual->tree()->name();
        $xref = $individual->xref();

        if (($cart[$tree][$xref] ?? false) === false) {
            $cart[$tree][$xref] = true;

            Session::put('cart', $cart);

            $this->addMediaLinksToCart($individual);

            if ( $this->all_RecTypes) {                                // EW.H mod ...
                $this->addLocationLinksToCart($individual);
                $this->addNoteLinksToCart($individual);
                $this->addSourceLinksToCart($individual);
            }
        }
    }

    /**
     * @param Family $family
     */
    protected function addFamilyWithoutSpousesToCart(Family $family): void
    {
        $cart = Session::get('cart', []);
        $tree = $family->tree()->name();
        $xref = $family->xref();

        if (($cart[$tree][$xref] ?? false) === false) {
            $cart[$tree][$xref] = true;
            Session::put('cart', $cart);
        }
    }

    /**
     * @param Family $family
     */
    protected function addFamilyOtherRecordsToCart(Family $family): void
    {
        $this->addLocationLinksToCart($family);
        $this->addNoteLinksToCart($family);
        $this->addSourceLinksToCart($family);
        $this->addSubmitterLinksToCart($family);
    }

    /**
     * Count the records of each type in the clippings cart.
     *
     * @param Tree $tree
     * @param array $recordTypes
     *
     * @return int[]
     */
    private function countRecordTypesInCart(Tree $tree, array $recordTypes): array
    {
        $records = $this->getRecordsInCart($tree);
        $recordTypesCount = [];                  // type => count
        $recordTypesCount['all'] = count($records);
        foreach ($recordTypes as $key => $class) {
            foreach ($records as $record) {
                if ($record instanceof $class) {
                    if (array_key_exists($key, $recordTypesCount)) {
                        $recordTypesCount[$key]++;
                    } else {
                        $recordTypesCount[$key] = 1;
                    }
                }
            }
        }
        return $recordTypesCount;
    }

    /**
     * Collect the keys of the records of each type in the clippings cart.
     * The order of the Xrefs in the cart results from the order of
     * the calls during insertion and is not further separated according to
     * their origin.
     * This function distributes the Xrefs according to their origin to a predefined structure.
     *
     * @param Tree $tree
     * @param array $recordTypes
     *
     * @return array    // string[] string[]
     */
    private function collectRecordKeysInCart(Tree $tree, array $recordTypes): array
    {
        $records = $this->getRecordsInCart($tree);
        $recordKeyTypes = array();                  // type => keys
        foreach ($recordTypes as $key => $class) {
            $recordKeyTypeXrefs = [];
            foreach ($records as $record) {
                if ($record instanceof $class) {
                    $xref = $this->getXref_fromRecord($record);
                    $recordKeyTypeXrefs[] = $xref;
                }
            }
            if ( count($recordKeyTypeXrefs) > 0) {
                $recordKeyTypes[strval($key) ] = $recordKeyTypeXrefs;
            }
        }
        return $recordKeyTypes;
    }

    /**
     * Collect the records of each type in the clippings cart.
     * The order of the Xrefs in the cart results from the sequence of the calls
     * during insertion and may be relevant for subsequent actions.
     * On the other hand, the records must also be separated according to their
     * origin and put in a defined order in this respect.
     * For this reason, the records are not output directly, but are inserted
     * into a structure that is predefined and specifies the sequence.
     *
     * @param Tree $tree
     * @param array $recordTypes
     *
     * @return array    // string[] GedcomRecord []
     */
    private function collectRecordsInCart(Tree $tree, array $recordTypes): array
    {
        $records = $this->getRecordsInCart($tree);
        $recordKeyTypes = array();                  // type => keys
        foreach ($recordTypes as $key => $class) {
            $recordKeyTypeXrefs = [];
            foreach ($records as $record) {
                if ($record instanceof $class) {
                    $recordKeyTypeXrefs[] = $record;
                }
            }
            if ( count($recordKeyTypeXrefs) > 0) {
                $recordKeyTypes[strval($key) ] = $recordKeyTypeXrefs;
            }
        }
        return $recordKeyTypes;
    }

    /**
     * Get the Xrefs in the clippings cart.
     *
     * @param Tree $tree
     *
     * @return array
     */
    private function getXrefsInCart(Tree $tree): array
    {
        $cart = Session::get('cart', []);
        $xrefs = array_keys($cart[$tree->name()] ?? []);
        $xrefs = array_map('strval', $xrefs);           // PHP converts numeric keys to integers.
        return $xrefs;
    }

    /**
     * Get the records in the clippings cart. 
     * There may be use cases where it makes sense to output the records sorted
     * by their Xrefs, but for our purposes it is rather disadvantageous,
     * so sorting is optional and disabled by default.
     *
     * @param Tree $tree
     * @param bool $do_sort
     *
     * @return array
     */
    private function getRecordsInCart(Tree $tree, bool $do_sort=false): array
    {
        $xrefs = $this->getXrefsInCart($tree);
        $records = array_map(static function (string $xref) use ($tree): ?GedcomRecord {
            return Registry::gedcomRecordFactory()->make($xref, $tree);
        }, $xrefs);

        // some records may have been deleted after they were added to the cart, remove them
        $records = array_filter($records);

        if ($do_sort) {
            // group and sort the records
            uasort($records, static function (GedcomRecord $x, GedcomRecord $y): int {
                return $x->tag() <=> $y->tag() ?: GedcomRecord::nameComparator()($x, $y);
            });
        }

        return $records;
    }

    /**
     * Get the XREF for the record in the clippings cart.
     *
     * @param GedcomRecord $record
     *
     * @return string 
     */
    private function getXref_fromRecord(GedcomRecord $record): string
    {
        $xref = $record->xref();
        return $xref;
    }

    /**
     * get GEDCOM records from array with XREFs ready to write them to a file
     * and export media files to zip file
     *
     * @param Tree $tree
     * @param array $xrefs
     * @param int $access_level
     * @param Filesystem|null $zip_filesystem
     * @param FilesystemInterface|null $media_filesystem
     *
     * @return Collection
     * @throws FileExistsException
     * @throws FileNotFoundException
     */
    private function getRecordsForExport(Tree $tree, array $xrefs, int $access_level): Collection
    {
        $records = new Collection();
        foreach ($xrefs as $xref) {
            $object = Registry::gedcomRecordFactory()->make($xref, $tree);
            // The object may have been deleted since we added it to the cart ...
            if ($object instanceof GedcomRecord) {
                $record = $object->privatizeGedcom($access_level);
                $record = $this->removeLinksToUnusedObjects($record, $xrefs);
                $records->add($record);
            }
        }
        return $records;
    }

    /**
     * remove links to objects that aren't in the cart
     *
     * @param string $record
     * @param array $xrefs
     *
     * @return string
     */
    private function removeLinksToUnusedObjects(string $record, array $xrefs): string
    {
        preg_match_all('/\n1 ' . Gedcom::REGEX_TAG . ' @(' . Gedcom::REGEX_XREF . ')@(\n[2-9].*)*/', $record, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if (!in_array($match[1], $xrefs, true)) {
                $record = str_replace($match[0], '', $record);
            }
        }
        preg_match_all('/\n2 ' . Gedcom::REGEX_TAG . ' @(' . Gedcom::REGEX_XREF . ')@(\n[3-9].*)*/', $record, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if (!in_array($match[1], $xrefs, true)) {
                $record = str_replace($match[0], '', $record);
            }
        }
        preg_match_all('/\n3 ' . Gedcom::REGEX_TAG . ' @(' . Gedcom::REGEX_XREF . ')@(\n[4-9].*)*/', $record, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if (!in_array($match[1], $xrefs, true)) {
                $record = str_replace($match[0], '', $record);
            }
        }
        return $record;
    }

    /**
     * Recursive function to traverse the tree and add the ancestors
     *
     * @param Individual $individual
     * @param int $level
     *
     * @return void
     */
    protected function addAncestorsToCart(Individual $individual, int $level = PHP_INT_MAX): void
    {
        $this->addIndividualToCart($individual);

        foreach ($individual->childFamilies() as $family) {
            $this->addFamilyToCart($family);

            foreach ($family->spouses() as $parent) {
                if ($level > 1) {
                    $this->addAncestorsToCart($parent, $level - 1);
                }
            }
        }
    }

    /**
     * Recursive function to traverse the tree and add the ancestors and their families
     *
     * @param Individual $individual
     * @param int $level
     *
     * @return void
     */
    protected function addAncestorFamiliesToCart(Individual $individual, int $level = PHP_INT_MAX): void
    {
        foreach ($individual->childFamilies() as $family) {
            $this->addFamilyAndChildrenToCart($family);

            foreach ($family->spouses() as $parent) {
                if ($level > 1) {
                    $this->addAncestorFamiliesToCart($parent, $level - 1);
                }
            }
        }
    }

    /**
     * Recursive function to traverse the tree and add the descendant families
     *
     * @param Family $family
     * @param int $level
     *
     * @return void
     */
    protected function addFamilyAndDescendantsToCart(Family $family, int $level = PHP_INT_MAX): void
    {
        $this->addFamilyAndChildrenToCart($family);

        foreach ($family->children() as $child) {
            foreach ($child->spouseFamilies() as $child_family) {
                if ($level > 1) {
                    $this->addFamilyAndDescendantsToCart($child_family, $level - 1);
                }
            }
        }
    }

    /**
     * Recursive function to traverse the tree and count the maximum ancestor generation
     *
     * @param Individual $individual
     *
     * @return int
     */
    protected function countAncestorGenerations(Individual $individual): int
    {
        $leave = true;
        $countMax = -1;
        foreach ($individual->childFamilies() as $family) {
            foreach ($family->spouses() as $parent) {
                // there are some parent nodes/trees; get the maximum height of parent trees
                $leave = false;
                $countSubtree = $this->countAncestorGenerations($parent);
                if ($countSubtree > $countMax) {
                    $countMax = $countSubtree;
                }
            }
        }
        If ($leave) {
            return 1;               // leave is reached
        } else {
            return $countMax + 1;
        }
    }

    /**
     * Recursive function to traverse the tree and count the maximum descendant generation
     *
     * @param Individual $individual
     *
     * @return int
     */
    protected function countDescendantGenerations(Individual $individual): int
    {
        $leave = true;
        $countMax = -1;
        foreach ($individual->spouseFamilies() as $family) {
            foreach ($family->children() as $child) {
                // there are some child nodes/trees; get the maximum height of child trees
                $leave = false;
                $countSubtree = $this->countDescendantGenerations($child);
                if ($countSubtree > $countMax) {
                    $countMax = $countSubtree;
                }
            }
        }
        If ($leave) {
            return 1;               // leave is reached
        } else {
            return $countMax + 1;
        }
    }

    /**
     * The person or organisation who created this module.
     *
     * @return string
     */
    public function customModuleAuthorName(): string {
        return self::CUSTOM_AUTHOR;
    }

    /**
     * How should this module be identified in the control panel, etc.?
     *
     * @return string
     */
    public function title(): string
    {
        /* I18N: Name of a module */
        return $this->huh . ' ' . I18N::translate(self::CUSTOM_TITLE);
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
     * A sentence describing what this module does.
     *
     * @return string
     */
    public function description(): string
    {
        /* I18N: Description of the module */
        return I18N::translate(self::CUSTOM_DESCRIPTION);
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
     * A URL that will provide the latest version of this module.
     *
     * @return string
     */
    public function customModuleLatestVersionUrl(): string
    {
        return self::CUSTOM_LAST;
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
     * Where does this module store its resources?
     *
     * @return string
     */
    public function resourcesFolder(): string
    {
        return __DIR__ . DIRECTORY_SEPARATOR .'resources' . DIRECTORY_SEPARATOR;
    }

    /**
     * Additional/updated translations.
     *
     * @param string $language
     *
     * @return array<string,string>
     */
    public function customTranslations(string $language): array
    {
        // no differentiation according to language variants
        $_language = substr($language, 0, 2);
        $ret = [];
        $languageFile = $this->resourcesFolder() . 'lang' . DIRECTORY_SEPARATOR . $_language . '.po';
        if (file_exists($languageFile)) {
            $ret = (new Translation($languageFile))->asArray();
        }
        return $ret;
    }

    /**
     *  bootstrap
     */
    public function boot(): void
    {
        // Here is also a good place to register any views (templates) used by the module.
        // This command allows the module to use: view($this->name() . '::', 'fish')
        // to access the file ./resources/views/fish.phtml
        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');

    }

    /**
     * The name of Output to VIZ-Extension
     *
     * @return string
     */
    private function getVIZfname(): string
    {
        return $this->FILENAME_VIZ;
    }

    /**
     * The name of Output to VIZ-Extension
     *
     * @return string
     */
    private function putVIZfname(String $_fname): string
    {
        $this->FILENAME_VIZ = $_fname;
        Session::put('FILENAME_VIZ', $this->FILENAME_VIZ);          // EW.H mod ... save it to Session
        return $this->FILENAME_VIZ;
    }

    /**
     * The name of Output to VIZ-Extension
     *
     * @return string
     */
    private function getVIZdname(): string
    {
        return $this->VIZ_DSNAME;
    }

    /**
     * The name of Output to VIZ-Extension
     *
     * @return string
     */
    private function putVIZdname(String $_dname): string
    {
        $this->VIZ_DSNAME = $_dname;
        Session::put('VIZ_DSname', $this->VIZ_DSNAME);          // EW.H mod ... save it to Session
        return $this->VIZ_DSNAME;
    }

    /**
     * dump array as json to text-file
     * 
     * @param array $theArray
     */
    public function dumpArray(array &$theArray, string $fileName)
    {
        //Encode the array into a JSON string.
        $encodedString = json_encode($theArray);

        //Save the JSON string to a text file.
        $_fName = SELF::VIZdir . DIRECTORY_SEPARATOR . $fileName;
        file_put_contents($_fName, $encodedString, LOCK_EX);

    }

    /**
     * store array as json to session
     * 
     * @param array $theArray
     */
    public function saveArrayToSession(array &$theArray, string $storeName)
    {
        //Encode the array into a JSON string.
        $encodedString = json_encode($theArray);

        //Save the JSON string to SessionStorage.
        Session::put($storeName, $encodedString);

    }

}
