<?php
/*
 * webtrees - clippings cart enhanced
 *
 * based on Vesta module "clippings cart extended"
 *
 * 
 *
 * Copyright (C) 2022-2024 huhwt. All rights reserved.
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

use Aura\Router\Map;
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
use Fisharebest\Webtrees\Module\FamilyListModule;
use Fisharebest\Webtrees\Module\IndividualListModule;
use Fisharebest\Webtrees\Http\RequestHandlers\SearchGeneralPage;
use Fisharebest\Webtrees\Http\RequestHandlers\SearchAdvancedPage;

use Fisharebest\Localization\Translation;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Location;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\Menu;
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;
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
use Nyholm\Psr7\Uri;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Fig\Http\Message\RequestMethodInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;

use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Module\ModuleMenuTrait;
use Fisharebest\Webtrees\Module\ClippingsCartModule;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleMenuInterface;
use Fisharebest\Webtrees\View;
use SebastianBergmann\Type\VoidType;

use Fisharebest\Webtrees\Module\ModuleInterface;
use Fisharebest\Webtrees\Module\ModuleListInterface;
use HuHwt\WebtreesMods\ClippingsCartEnhanced\ListProcessor;
use HuHwt\WebtreesMods\ClippingsCartEnhanced\ClippingsCartEnhancedModule;

use HuHwt\WebtreesMods\ClippingsCartEnhanced\Traits\CCEmodulesTrait;
use HuHwt\WebtreesMods\ClippingsCartEnhanced\Traits\CCEconfigTrait;
use HuHwt\WebtreesMods\ClippingsCartEnhanced\Traits\CC_addActions;
use HuHwt\WebtreesMods\ClippingsCartEnhanced\Traits\CCEaddActions;
use HuHwt\WebtreesMods\ClippingsCartEnhanced\Traits\CCEcartActions;
use HuHwt\WebtreesMods\ClippingsCartEnhanced\Traits\CCEdatabaseActions;
use HuHwt\WebtreesMods\ClippingsCartEnhanced\Traits\CCEtagsActions;
use HuHwt\WebtreesMods\ClippingsCartEnhanced\Traits\CCEvizActions;

use HuHwt\WebtreesMods\TaggingServiceManager\TaggingServiceManager;
use HuHwt\WebtreesMods\TaggingServiceManager\TaggingServiceManagerAdapter;

// control functions
use stdClass;
use function app;
use function array_filter;
use function array_keys;
use function array_map;
use function array_search;
use function assert;
use function count;
use function array_key_exists;
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
                         implements ModuleGlobalInterface, ModuleCustomInterface, ModuleConfigInterface // , ModuleMenuInterface
{   // use ModuleMenuTrait;

    use ModuleConfigTrait;
    use CCEconfigTrait;

    use ModuleCustomTrait;
    /** All constants and functions according to ModuleCustomTrait */
    use CCEmodulesTrait {
        CCEmodulesTrait::customModuleAuthorName insteadof ModuleCustomTrait;
        CCEmodulesTrait::customModuleLatestVersionUrl insteadof ModuleCustomTrait;
        CCEmodulesTrait::customModuleVersion insteadof ModuleCustomTrait;
        CCEmodulesTrait::customModuleSupportUrl insteadof ModuleCustomTrait;
        CCEmodulesTrait::title insteadof ModuleCustomTrait;
        CCEmodulesTrait::menuTitle insteadof ModuleCustomTrait;

        CCEmodulesTrait::resourcesFolder insteadof ModuleCustomTrait;
    }
    /** All constants and functions related to default ClippingsCartModule  */
    use CC_addActions;
    /** All constants and functions related to enhancements  */
    use CCEaddActions {
        CCEaddActions::addFamilyToCart insteadof CC_addActions;
        CCEaddActions::addIndividualToCart insteadof CC_addActions;
    }
    /** All constants and functions related to handling the Cart  */
    use CCEcartActions;

    use CCEdatabaseActions;
    /** All constants and functions related to handling the Tags  */
    use CCEtagsActions;
    /** All constants and functions related to connecting vizualizations  */
    use CCEvizActions;

    protected const ROUTE_URL = '/tree/{tree}/CCE';

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleGlobalInterface::headContent()
     * CSS class for the URL.
     *
     * EW.H - MOD ... we need our Script too, so we do a double injection
     * @return string
     */
    public function headContent(): string
    {
        $_name   = $this->name();

        $html_CSS = view("{$_name}::style", [
            'path' => $this->assetUrl('css/CCEtable-actions.css'),
        ]);
        $html_JSx = view("{$_name}::script", [
            'path' => $this->assetUrl('js/CCEtable-actions.js'),
        ]);
        $html_ = $html_CSS . " " . $html_JSx;

        return $html_;
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleGlobalInterface::bodyContent()
     * EW.H - MOD ... - ( see headConten() )
     * @return string
     */
    public function bodyContent(): string
    {
        return '';
    }
    public const SHOW_RECORDS       = 'Records in clippings cart - Execute an action on them.';
    public const SHOW_ACTIONS       = 'Performed actions fo fill the cart.';

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
    private const EMPTY_FORCE   = 'Deleta all records';
    private const EMPTY_ALL     = 'all records';
    private const EMPTY_SET     = 'set of records by type';
    private const EMPTY_CREATED = 'records created by action';

    // Routes that have a record which can be added to the clipboard
    private const ROUTES_WITH_RECORDS = [
        'Family'     => FamilyPage::class,
        'Individual' => IndividualPage::class,
        'Media'      => MediaPage::class,
        'Location'   => LocationPage::class,
        'Note'       => NotePage::class,
        'Repository' => RepositoryPage::class,
        'Source'     => SourcePage::class,
        'Submitter'  => SubmitterPage::class,
        'FamilyListModule'      => FamilyListModule::class,
        'IndividualListModule'  => IndividualListModule::class,
        'Search-General'        => SearchGeneralPage::class,
        'SearchGeneralPage'     => SearchGeneralPage::class,
        'Search-Advanced'       => SearchAdvancedPage::class,
    ];

    // Modules with lists: which DataTable to grep  - what kind of list
    //      works combined with OTHER_MENU_TYPES
    //          'X'  -> 'DataTables_Table_X_wrapper'
    //          'action-type'   -> primary key for clipping action - defines number of menu entries
    //          'action-pref'   -> optional prefix for clipping action
    //          'action-suff'   -> optional suffix for clipping action
    //          'action-text'   -> menu label
    //          'grep-id'       -> will be used as variable name to grep the datatable functions
    //          'listType'      -> basic type of information
    //          'clipAction'    -> will be performed in ClippingsCartEnhancedModule
    private const OTHER_MENUES = [
        'FamilyListModule'      => [
                'FL' => [ 'action-type' => 'FAM-LIST', 'action-pref' => '', 'grep-id' => 'dtFLjq'
                        , 'view' => 'families-table', 'table' => '.wt-table-family' ]
                ],
        'IndividualListModule'  => [
                'IL' => [ 'action-type' => 'INDI-LIST', 'action-pref' => '', 'grep-id' => 'dtILjq'
                        , 'view' => 'individuals-table', 'table' => '.wt-table-individual' ]
                ],
        'Search-Advanced'        => [
                'IL' => [ 'action-type' => 'INDI-LIST', 'action-pref' => 'SEARCH_A-', 'grep-id' => 'dtILjq'
                        , 'view' => 'individuals-table', 'table' => '.wt-table-individual' ]
                ],
        'Search-General'       => [
                'IL' => [ 'action-type' => 'INDI-LIST', 'action-pref' => 'SEARCH_G-', 'grep-id' => 'dtILjq'
                        , 'view' => 'individuals-table', 'table' => '.wt-table-individual' ],
                'FL' => [ 'action-type' => 'FAM-LIST', 'action-pref' => 'SEARCH_G-', 'grep-id' => 'dtFLjq' 
                        , 'view' => 'families-table', 'table' => '.wt-table-family' ],
                // '2'  => [ 'action-type' => 'SOURCE', 'action-pref' => 'SEARCH_G-', 'grep-id' => 'dt2jq' ],
                // '3'  => [ 'action-type' => 'NOTE', 'action-pref' => 'SEARCH_G-', 'grep-id' => 'dt3jq' ]
                ],
        'SearchGeneralPage'    => [
            'IL' => [ 'action-type' => 'INDI-LIST', 'action-pref' => 'SEARCH_G-', 'grep-id' => 'dtILjq'
                    , 'view' => 'individuals-table', 'table' => '.wt-table-individual' ],
            'FL' => [ 'action-type' => 'FAM-LIST', 'action-pref' => 'SEARCH_G-', 'grep-id' => 'dtFLjq' 
                    , 'view' => 'families-table', 'table' => '.wt-table-family' ],
            ],
        ];

    // 
    private const OTHER_MENUES_TYPES = [
        'FAM-LIST'            => [
                '0'  => [ 'listType' => 'family', 'clipAction' => 'clipFamilies'],
                '1'  => [ 'action-suff' => '', 'action-text' => 'add families and individuals to the clippings cart' ],
                '2'  => [ 'action-suff' => 'wp', 'action-text' => 'add families and individuals with parents to the clippings cart'],
        ],
        'INDI-LIST'           => [
                '0'  => [ 'listType' => 'individual', 'clipAction' => 'clipIndividuals'],
                '1'  => [ 'action-suff' => '', 'action-text' => 'add individuals to the clippings cart'],
                '2'  => [ 'action-suff' => 'wp', 'action-text' => 'add individuals with parents to the clippings cart'],
                '3'  => [ 'action-suff' => 'ws', 'action-text' => 'add individuals and spouses to the clippings cart'],
                '4'  => [ 'action-suff' => 'wc', 'action-text' => 'add individuals and children to the clippings cart'],
                '5'  => [ 'action-suff' => 'wa', 'action-text' => 'add individuals and all relations to the clippings cart'],
        ]
    ];

    // Types of records
    // The order of the Xrefs in the Clippings Cart results from the order 
    // of the calls during insertion and in this respect is not separated 
    // according to their origin.
    // This can cause problems when passing to interfaces and functions that 
    // expect a defined sequence of tags.
    // This structure determines the order of the categories in which the 
    // records are displayed or output for further actions ( function getEmptyAction() and showCart.phtml )
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
        'ONLY_IFN' => [
                'Individual' => Individual::class,
                'Family'     => Family::class,
                'Note'       => Note::class,
            ],
        'ONLY_IFS' => [
                'Individual' => Individual::class,
                'Family'     => Family::class,
                'Source'     => Source::class,
                ],
        'ONLY_IFL' => [
                'Individual' => Individual::class,
                'Family'     => Family::class,
                'Location'   => Location::class,
                ],
    ];

    /** @var int The default access level for this module.  It can be changed in the control panel. */
    protected int $access_level = Auth::PRIV_USER;

    /** @var GedcomExportService */
    private GedcomExportService $gedcom_export_service;

    /** @var LinkedRecordService */
    private LinkedRecordService $linked_record_service;

    /** @var UserService */
    private $user_service;

    /** @var Tree */
    private Tree $tree;

    /** @var int The number of ancestor generations to be added (0 = proband) */
    private int $levelAncestor;

    /** @var int The number of descendant generations to add (0 = proband) */
    private int $levelDescendant;

    // Output to Vizualisation tools
    private const VIZdir           = Webtrees::DATA_DIR . DIRECTORY_SEPARATOR . '_toVIZ';

    // directory for storing CART-structure
    private string $userDir;

    /**
     * @var bool We want to have the GEDCOM-Objects exported as array
     */
    private const DO_DUMP_Ritems   = true;

    /**
     * Store the cart
     */
    private const CARTdir          = Webtrees::DATA_DIR . DIRECTORY_SEPARATOR . '_CART';

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
     * Retrieve all Record-Types - casually we want only a subset of R-T
     * @var boolean
     */
    private bool $all_RecTypes;

    /**
     * Retrieve shared notes associated with actual item
     * @var boolean
     */
    private bool $add_sNOTE;

    /**
     * stub for repeating Webtrees-List-Actions in CCE-compliant manner
     * @var ListProcessor $listProcessor
     */
    private ListProcessor $listProcessor;

    /**
     * where 'this' is not $this ...
     * @var ClippingsCartEnhanced $instance
     */
    private ClippingsCartEnhanced $instance;

    /**
     * check if this->instance is set
     * @var bool $lPdone
     */
    private bool $lPdone = false;

    /**
     * if call is coming from lists we need the origin uri
     * @var string $callingURI
     */
    private string $callingURI = '';

    /**
     * the active tag descriptor
     * @var string $activeTAG
     */
    private string $activeTAG = '';

    private ModuleService $module_service;

    private bool $TSMok = false;

    /** 
     * ClippingsCartModule constructor.
     *
     * @param GedcomExportService $gedcom_export_service
     * @param LinkedRecordService $linked_record_service
     */
    public function __construct(
        GedcomExportService $gedcom_export_service,
        LinkedRecordService $linked_record_service)
    {
        $this->gedcom_export_service = $gedcom_export_service;
        $this->linked_record_service = $linked_record_service;

        $this->levelAncestor        = PHP_INT_MAX;
        $this->levelDescendant      = PHP_INT_MAX;
        $this->exportFilenameDOWNL  = self::FILENAME_DOWNL;
        $this->exportFilenameVIZ    = self::FILENAME_VIZ;
        $this->huh = json_decode('"\u210D"') . "&" . json_decode('"\u210D"') . "wt";
        $this->huhwttam_checked     = false;
        $this->huhwtlin_checked     = false;
        $this->all_RecTypes         = true;
        $this->add_sNOTE            = false;
        $tagOptions                 = $this->TAGconfigOptions();
        $this->activeTAG            = $tagOptions[(int) $this->getPreference('TAG_Option', '0')];

        // EW.H mod ... read TAM-Filename from Session, otherwise: Initialize
        if (Session::has('FILENAME_VIZ')) {
            $this->exportFilenameVIZ = Session::get('FILENAME_VIZ');
        } else {
            $this->exportFilenameVIZ        = 'wt2VIZ';
            Session::put('FILENAME_VIZ', $this->exportFilenameVIZ);
        }

        // EW.H mod ... we want a subdir of Webtrees::DATA_DIR for storing dumps and so on
        // - test for and create it if it not exists
        if(!is_dir(self::VIZdir)){
            //Directory does not exist, so lets create it.
            mkdir(self::VIZdir, 0755);
        }

        // A subdir of Webtrees::DATA_DIR for storing Session::cart-structure
        if(!is_dir(self::CARTdir)){
            //Directory does not exist, so lets create it.
            mkdir(self::CARTdir, 0755);
        }

    }


    /**
     * A menu, to be added to the main application menu.
     *
     * Show:            show records in clippings cart and allow deleting some of them
     *                  as defined in ROUTES_WITH_RECORDS
     * AddRecord - according to actual class displayed on screen        unless otherwise stated -> CC_addActions.php
     *      AddIndividual:   add individual (this record, parents, children, ancestors, descendants, ...)   -> CCEaddActions.php
     *      AddFamily:       add family record                                                              -> CCEaddActions.php
     *      AddMedia:        add media record
     *      AddLocation:     add location record
     *      AddNote:         add shared note record
     *      AddRepository:   add repository record
     *      AddSource:       add source record
     *      AddSubmitter:    add submitter record
     *      FamilyList:      add collected XREFs                                              -> ClippingsCartEnhancedModule.php
     *      IndividualList:  add collected XREFs                                              -> ClippingsCartEnhancedModule.php
     *      Search-Advanced: add collected XREFs                                              -> ClippingsCartEnhancedModule.php
     *      Search-General:  add collected XREFs                                              -> ClippingsCartEnhancedModule.php
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
        assert($request instanceof ServerRequestInterface);

        $route = Validator::attributes($request)->route();
        $params = $_GET;

        $this->tree = $tree;

        // we need a subdir for each tree ...
        $treeDir = self::CARTdir . DIRECTORY_SEPARATOR . $tree->name();
        if(!is_dir($treeDir)){
            mkdir($treeDir, 0755);
        }
        // ... and also for each user
        $user = Validator::attributes($request)->user();
        $userDir = $treeDir . DIRECTORY_SEPARATOR . $user->userName();
        if(!is_dir($userDir)){
            mkdir($userDir, 0755);
        }
        Session::put('userDir', $userDir);

        // clippings cart is an array in the session specific for each tree
        $cart  = $this->get_Cart();
        $count = count($cart[$tree->name()] ?? []);

        $submenus = [$this->addMenuClippingsCart($tree, $cart)];

        $REQ_URI = rawurldecode($_SERVER['REQUEST_URI']);                   // we need to do so because some server might have that encoded ...
        $TSMok = ($this->TSMok && str_contains($REQ_URI, '/ShowCart/'));        // ... and we want to show this entrance only in certain case
        if ($TSMok && $count > 0)
            $submenus[] = $this->addMenuTaggingService($tree);

        $action = array_search($route->name, self::ROUTES_WITH_RECORDS, true);

        if ($action !== false) {
            $actmenus = $this->addMenuAddThisRecord($tree, $route, $action, $params);
            if ($actmenus) {
                $actmenus_subs = $actmenus->getSubmenus();
                $submenus[] = $actmenus;
                if (count($actmenus_subs) > 0) {
                    foreach($actmenus_subs as $actm_s) {
                        $submenus[] = $actm_s;
                    }
                }

            }
        }

        $submenus[] = $this->addMenuAddGlobalRecordSets($tree);

        if (!$this->isCartEmpty($tree)) {
            $submenus[] = $this->addMenuEmptyForce($tree);
            $submenus[] = $this->addMenuDeleteRecords($tree);
            $submenus[] = $this->addMenuExecuteAction($tree);
        }

        return new Menu($this->title(), '#', 'menu-clippings CCE_Menue', ['rel' => 'nofollow'], $submenus);
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

        return new Menu(I18N::translate('Records in clippings cart') . $badge,
            route('module', [
                'module'      => $this->name(),
                'action'      => 'ShowCart',
                'tree'        => $tree->name(),
            ]), 'menu-clippings-cart', ['rel' => 'nofollow', 'id' => 'CCEbadge']);
    }

    /**
     * @param Tree $tree
     * @param array $cart
     *
     * @return Menu
     */
    private function addMenuTaggingService (Tree $tree): Menu
    {
        $TSMname = "_huhwt-tsm_"; // app(TaggingServiceManagerModule::class)->name();

        return new Menu(I18N::translate('Transfer to tagging service'),
            route('module', [
                'module'      => $TSMname,
                'action'      => 'TaggingService',
                'tree'        => $tree->name(),
            ]), 'menu-clippings-cart', ['rel' => 'nofollow']);
    }

    /**
     * @param Tree $tree
     * @param Route $route
     * @param string $action
     *
     * @return Menu|null
     */
    private function addMenuAddThisRecord (Tree $tree, Route $route, string $action, array $params): ?Menu    {
        $attributes = $route->attributes;
        if (array_key_exists('xref', $attributes)) {
            $xref = $attributes['xref'];
            assert(is_string($xref));

            return new Menu(I18N::translate('Add this record to the clippings cart'),
                route('module', [
                    'module' => $this->name(),
                    'action' => 'Add' . $action,
                    'xref'   => $xref,
                    'tree'   => $tree->name(),
                ]), 'menu-clippings-add', ['rel' => 'nofollow']);
        } elseif ($params) {
            return $this->addMenuAddOthers($tree, $route, $action, $params);
        } else {
            return null;
        }
    }

    /**
     * @param Tree $tree
     * @param Route $route
     * @param string $action
     *
     * @return Menu|null
     */
    private function addMenuAddOthers (Tree $tree, Route $route, string $action, array $params): ?Menu    {
        if ($action === 'FamilyListModule') {
            if (array_key_exists('show', $params)) {
                // if ($params['show'] == 'indi') {
                if ($params['show'] > ' ') {
                    $_menu = $this->addMenuAddOthersList($tree, $route, $action);
                    return $_menu;
                }
            }
        }
        if ($action === 'IndividualListModule') {
            if (array_key_exists('show', $params)) {
                // if ($params['show'] == 'indi') {
                if ($params['show'] > ' ') {
                    $_menu = $this->addMenuAddOthersList($tree, $route, $action);
                    return $_menu;
                }
            }
        }
        if ($action === 'Search-General') {
            if (array_key_exists('search_individuals', $params)) {
                if ($params['search_individuals'] == '1') {
                    $_menu = $this->addMenuAddOthersList($tree, $route, $action);
                    return $_menu;
                }
            } else if (array_key_exists('query', $params)) {
                $_menu = $this->addMenuAddOthersList($tree, $route, $action);
                return $_menu;
            }

        }
        if ($action === 'Search-Advanced') {
            if (array_key_exists('fields', $params)) {
                $param_fields = $params['fields'];
                $sa_add_cce = false;
                foreach($param_fields as $pfk => $pfv) {
                    if ($pfv > '') {
                        $sa_add_cce = true;
                        break;
                    }
                }
                if ($sa_add_cce) {
                    $_menu = $this->addMenuAddOthersList($tree, $route, $action);
                    return $_menu;
                }
            }
        }
        if ($action  === 'SearchGeneralPage') {
        }
        return null;
    }

    private function addMenuAddOthersList(Tree $tree, Route $route, string $action): ?Menu {
        $m_views   = [];
        $first_opt  = true;
        $menu_type = self::OTHER_MENUES[$action];
        // '0'  => [ 'action-type' => 'FAM-LIST', 'action-pref' => '', 'grep-id' => 'dt0jq', 'view' => 'families-table', 'table' => '.wt-table-family' ]
        foreach( $menu_type as $dt_id => $mparms) {
            // '0'  => [ 'listType' => 'family', 'clipAction' => 'clipFamilies'],
            // '1'  => [ 'action-suff' => '', 'action-text' => I18N::translate('add families and individuals to the clippings cart') ],
            $a_type     = $mparms['action-type'];
            $a_pref     = $mparms['action-pref'];
            $dt_grep    = $mparms['grep-id'];
            $list_type  = self::OTHER_MENUES_TYPES[$a_type];
            foreach($list_type as $lopt => $loparms) {
                if ($lopt == '0') {
                    $listType   = $loparms['listType'];
                    $clipAction = $loparms['clipAction'];
                } else {
                    $a_suff = $loparms['action-suff'];
                    $a_text = $loparms['action-text'];
                    $action_key = $a_pref . $a_type . $a_suff;
                    $menu_opt = 'CCE-Mopt-' . $dt_id . '-' . $lopt;
                    if ($first_opt) {
                        $route_ajax = e(route(ClippingsCartEnhancedModule::class, ['module' => $this->name(), 'tree' => $tree->name()]), false);
                        $_menu = new Menu(I18N::translate($a_text),
                        '#',
                        'menu-clippings-add', ['rel' => 'nofollow', 'id' => $menu_opt, 'data-url' => $route_ajax,
                                               'listType' => $listType, 'clipAction' => $clipAction, 'action-key' => $action_key,
                                               'dt_id' => $dt_id, 'dt_grep' => $dt_grep]);
                        $first_opt = false;
                    } else {
                        $route_ajax = e(route(ClippingsCartEnhancedModule::class, ['module' => $this->name(), 'tree' => $tree->name()]));
                        $_menu_sm = new Menu(I18N::translate($a_text),
                            '#',
                            'menu-clippings-add', ['rel' => 'nofollow', 'id' => $menu_opt, 'data-url' => $route_ajax, 
                                                   'listType' => $listType, 'clipAction' => $clipAction, 'action-key' => $action_key,
                                                   'dt_id' => $dt_id, 'dt_grep' => $dt_grep]);
                            $_menu = $_menu->addSubmenu($_menu_sm);
    
                    }
                }
            }
        }
        return $_menu;
    }

    /**
     * @param Tree $tree
     * @param Route $route
     *
     * @return Menu|null
     */
    private function addMenuEmptyForce (Tree $tree): ?Menu    {
        $params['called_by'] = rawurldecode($_SERVER["REQUEST_URI"]);
        return new Menu(I18N::translate('Delete records in the clippings cart entirely'),
        route('module', [
            'module' => $this->name(),
            'action' => 'EmptyForce',
            'tree'   => $tree->name(),
            'params' => $params,
        ]), 'menu-clippings-empty', ['rel' => 'nofollow']);
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
        return new Menu(I18N::translate('Delete records in the clippings cart with selection option'),
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
    public function getShowCartAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();
        assert($tree instanceof Tree);

        $recordTypes      = $this->collectRecordsInCart($tree, self::TYPES_OF_RECORDS);

        $this->cartXREFs  = $this->getXREFstruct($tree);

        $cartActs         = $this->get_CartActs($tree);

        $cAroute_ajax     = e(route(ClippingsCartEnhancedModule::class, ['module' => $this->name(), 'tree' => $tree->name()]));

        return $this->viewResponse($this->name() . '::' . 'showCart/showCart', [
            'module'      => $this->name(),
            'types'       => self::TYPES_OF_RECORDS,
            'recordTypes' => $recordTypes,
            'title'       => I18N::translate('Family tree clippings cart'),
            'header_recs' => I18N::translate(self::SHOW_RECORDS),
            'header_acts' => I18N::translate(self::SHOW_ACTIONS),
            'cartActions' => $cartActs,
            'cArouteAjax' => $cAroute_ajax,
            'cartXREFs'   => $this->cartXREFs,
            'tree'        => $tree,
            'stylesheet'  => $this->assetUrl('css/cce.css'),
            'javascript'  => $this->assetUrl('js/cce.js'),
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
	 *
	 * HH.mod - additional action
     */
    public function getGlobalAction(ServerRequestInterface $request): ResponseInterface
    {
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
        $tree = Validator::attributes($request)->tree();

        $option = Validator::parsedBody($request)->string('option');

        switch ($option) {
            case self::ADD_ALL_PARTNER_CHAINS:
                $this->put_CartActs($tree, 'ALL_PARTNER_CHAINS', 'all');
                $_dname = 'wtVIZ-DATA~all partner chains';
                $this->putVIZdname($_dname);
                $this->addPartnerChainsGlobalToCart($tree);
                break;

            case self::ADD_COMPLETE_GED:
                $this->put_CartActs($tree, 'COMPLETE', 'GED');
                $_dname = 'wtVIZ-DATA~complete GED';
                $this->putVIZdname($_dname);
                $this->addCompleteGEDtoCart($tree);
                break;

            // case self::ADD_ALL_LINKED_PERSONS:
            //     $this->addAllLinked($tree);
            //     break;
    
            default;
            case self::ADD_ALL_CIRCLES:
                $this->put_CartActs($tree, 'ALL_CIRCLES', 'all');
                $_dname = 'wtVIZ-DATA~all circles';
                $this->putVIZdname($_dname);
                $this->addAllCirclesToCart($tree);
                break;
        }

        $url = route('module', [
            'module'      => $this->name(),
            'action'      => 'ShowCart',
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

        $tree = Validator::attributes($request)->tree();
        $user = Validator::attributes($request)->user();

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
        $tree = Validator::attributes($request)->tree();

        $option = Validator::parsedBody($request)->string('option', 'none');

        $viz_filter = 'ONLY_IF';

        switch (true) {
            case $this->add_sNOTE:
                $viz_filter = 'ONLY_IFN';
                break;
            default:
        }

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

        // From hereon we are dealing with plain textual gedcom 

            // all kinds of records in CCE - download as file
            case 'EXECUTE_DOWNLOAD_PLAIN':
                return $this->cceDownloadAction($request, 'PLAIN', 'DOWNLOAD');

            // only INDI and FAM records from CCE - download as file
            case 'EXECUTE_DOWNLOAD_IF':
                return $this->cceDownloadAction($request, 'ONLY_IF', 'DOWNLOAD');

            // only INDI and FAM records - postprocessing in TAM
            case 'EXECUTE_VISUALIZE_TAM':
                return $this->cceDownloadAction($request, $viz_filter, 'VIZ=TAM');

            // only INDI and FAM records - postprocessing in LINEAGE
            case 'EXECUTE_VISUALIZE_LINEAGE':
                return $this->cceDownloadAction($request, $viz_filter, 'VIZ=LINEAGE');

            default;
                break;

        }
        $url = route('module', [
            'module' => $this->name(),
            'action' => 'ShowCart',
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
    //  * @throws \League\Flysystem\FileExistsException
    //  * @throws \League\Flysystem\FileNotFoundException
     */
    public function cceDownloadAction(ServerRequestInterface $request, string $todo, string $action): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();

        $privatizeExport = Validator::parsedBody($request)->string('privatize_export', 'none');
        $accessLevel = $this->getAccessLevel($privatizeExport, $tree);
        $encoding = 'UTF-8';
        $line_endings = Validator::parsedBody($request)->isInArray(['CRLF', 'LF'])->string('line_endings');

        // there may be the situation that cart is emptied in meanwhile
        // due to session timeout and setting up a new session or because of
        // browser go-back after a empty-all action
        if ($this->isCartEmpty($tree)) {
            $url = route('module', [
                'module' => $this->name(),
                'action' => 'ShowCart',
                'tree'   => $tree->name(),
            ]);
            return redirect($url);
        }

        $recordTypes = $this->collectRecordKeysInCart($tree, self::TYPES_OF_RECORDS);
        // keep only XREFs used by Individual or Family records
        if ( str_starts_with($todo,'ONLY_IF') ) {
            $recordFilter = self::FILTER_RECORDS[$todo];
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

        /**
         *  We want to download the plain gedcom ...
         */

        if ( $action == 'DOWNLOAD' ) {
            $records = $this->getRecordsForDownload($tree, $xrefs, $accessLevel);

            $download_filename = $this->exportFilenameDOWNL;
            if ( str_starts_with($todo,'ONLY_IF') ) {
                $download_filename .= '_IF_';
            }
            $download_filename .= '(' . $xrefs[0] . ')';

            return $this->gedcom_export_service->downloadResponse($tree, false, $encoding, 'none', $line_endings, $download_filename, 'gedcom', $records);

        }

        /**
         * We want to postprocess the gedcom in a Vizualising-Tool ...
         * 
         * ... and there we want to have additional information in the gedcom ...
         * ... which has to be protected from filtering 
         */

        $v_xrefs = [];                                      // xrefs for additional information we want to keep in gedcom

         // we have tagged xrefs 
        // - so we have to add the regarding note-xref
        $tags = $this->get_TreeTags($tree);
        if (count($tags)>0) {
            $TSMadapter = app(TaggingServiceManagerAdapter::class);
            $INFOdata = $TSMadapter->getNotes_All($tree);
        } else {
            $INFOdata = $this->getNotes_All($tree);
        }
        if ($INFOdata) {
            $_nxrefsk   = array_keys($INFOdata['Nxrefs']);
            $_nxrefs    = array_map('strval', $_nxrefsk);
            $v_xrefs    = array_merge($v_xrefs, $_nxrefs);
        } else {
            $INFOdata = [];
        }

        Session::put('INFOdata', json_encode($INFOdata));

        $records = $this->getRecordsForVizualisation($tree, $xrefs, $v_xrefs, $accessLevel);

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

        /**
         *  ... the record objects must be transformed to json
         */

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
                    // Switch over to LINaction-Module
                    // TODO : 'module' is hardcoded - how to get the name from foreign PHP-class 'LINaction'?
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
        $tree = Validator::attributes($request)->tree();

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
            $options[self::EMPTY_CREATED] = I18N::translate(self::EMPTY_CREATED);
            $i = 0;
            foreach ($recordTypes as $type => $count) {
                $selectedTypes[$i] = 0;
                $i++;
            }
        } else {
            $headingTypes = '';
        }

        $selectedActions = $this->get_CartActs($tree);

        return $this->viewResponse($this->name() . '::' . 'empty', [
            'module'         => $this->name(),
            'options'        => $options,
            'title'          => $title,
            'label'          => $label,
            'labelType'      => $labelType,
            'recordTypes'    => $recordTypes,
            'selectedTypes'  => $selectedTypes,
            'selectedActions'=> $selectedActions,
            'tree'           => $tree,
        ]);
    }

    /**
     * save the cart-xrefs to file
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    private function getCartSave(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();

        $t_cartAct      = $this->get_CartActs($tree);

        $t_xrefs        = $this->get_CartXrefs($tree);

        $title = I18N::translate('Save Cart');
        $label = I18N::translate('File name');

        $CartDname      = Session::get('userDir');
        $CartFname      = date("Ymd His") . '-Cart.txt';

        $url = route('module', [
            'module'      => $this->name(),
            'action'      => 'ShowCart',
            'description' => $this->description(),
            'tree'        => $tree->name(),
        ]);

        return redirect($url);
    }

    /**
     * delete all records in the clippings cart or delete a set grouped by type of records
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getEmptyForceAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();
        assert($tree instanceof Tree);

        $params = $_GET['params'];
        $retRoute = $params['called_by'];

        $this->clean_Cart($tree);

        return redirect($retRoute);
    }
    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postEmptyAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();

        $option = Validator::parsedBody($request)->string('option');

        switch ($option) {
            case self::EMPTY_ALL: 
                $this->clean_Cart($tree);
                $url = route('module', [
                    'module'      => $this->name(),
                    'action'      => 'ShowCart',
                    'description' => $this->description(),
                    'tree'        => $tree->name(),
                ]);
                break;

            case self::EMPTY_SET:
                $this->doEmpty_SetAction($tree, $request);
                $url = route('module', [
                    'module'      => $this->name(),
                    'action'      => 'ShowCart',
                    'description' => $this->description(),
                    'tree'        => $tree->name(),
                ]);
                break;

            case self::EMPTY_CREATED:
                $this->doEmpty_CreatedAction($tree, $request);
                $url = route('module', [
                    'module'      => $this->name(),
                    'action'      => 'ShowCart',
                    'description' => $this->description(),
                    'tree'        => $tree->name(),
                ]);
                break;
    
            default;
                $txt_option = I18N::translate($option);
                FlashMessages::addMessage(I18N::translate("You have tried '%s' - it is not implemented yet.", e($txt_option)));
                return redirect((string) $request->getUri());
        }

        return redirect($url);
    }

    /**
     * delete selected types of record from the clippings cart
     *
     * @param Tree  $tree
     * @param ServerRequestInterface $request
     *
     */
    public function doEmpty_SetAction(Tree $tree, ServerRequestInterface $request): void
    {
        $recordTypes = $this->collectRecordKeysInCart($tree, self::TYPES_OF_RECORDS);
        foreach ($recordTypes as $key => $class) {              // test if record types ...
            $delKey = Validator::parsedBody($request)->string($key, 'none');  // ... are listed in request
            if ($delKey !== 'none') {
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
     * delete selected types of record from the clippings cart
     *
     * @param ServerRequestInterface $request
     *
     */
    private function doEmpty_CreatedAction(Tree $tree, ServerRequestInterface $request): string
    {
        $_tree = $tree->name();

        // the actual cartActs
        $cartAct_s = Session::get('cartActs', []);
        if (empty($cartAct_s)) 
            return (string) $this->count_CartTreeXrefs($tree);
        $cartAct_T = $cartAct_s[$_tree] ?? [];
        if (empty($cartAct_T)) 
            return (string) $this->count_CartTreeXrefs($tree);

        $cart = Session::get('cart', []);
        $cartT = $cart[$_tree] ?? [];
        if (!empty($cartT)) {
            foreach ($cartAct_T as $cartAct => $val) {                                        // test if any cartAct ...
                $delKey = Validator::parsedBody($request)->string($cartAct, 'none');  // ... is listed in request
                if ($delKey !== 'none') {
                    $cAct = str_contains($cartAct,'|') ? substr($cartAct,0,stripos($cartAct,'|')) : $cartAct;
                    foreach ($cartT as $xref => $xref_action) {
                        $xref_actions = explode(';', $xref_action);
                        $ica = array_search($cAct, $xref_actions);
                        if (!is_bool($ica)) {
                            array_splice($xref_actions, $ica,1);
                            if (count($xref_actions) > 0) {
                                $xref_action = $xref_actions[0];
                                if (count($xref_actions) > 1)
                                    $xref_action = implode(';', $xref_actions);
                                $cart[$_tree][$xref] = $xref_action;
                            } else {
                                unset($cart[$_tree][$xref]);
                            }
                        }
                    }
                    Session::put('cart', $cart);
                    $this->clean_CartActs_cact($tree, $cartAct);
                }
            }
        }
        return (string) $this->count_CartTreeXrefs($tree);
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
        $tree = Validator::attributes($request)->tree();

        $xref = Validator::queryParams($request)->isXref()->string('xref');

        $cart = Session::get('cart', []);
        unset($cart[$tree->name()][$xref]);
        Session::put('cart', $cart);

        $url = route('module', [
            'module'      => $this->name(),
            'action'      => 'ShowCart',
            'description' => $this->description(),
            'tree'        => $tree->name(),
        ]);

        return redirect($url);
    }

    /**
     * get access level based on selected option and user level
     *
     * @param string $privatizeExport
     * @param Tree $tree
     * @return int
     */
    private function getAccessLevel(string $privatizeExport, Tree $tree): int
    {

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
        $xrefs = $this->get_CartXrefs($tree);
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
     *
     * @return Collection
     */
    private function getRecordsForDownload(Tree $tree, array $xrefs, int $access_level): Collection
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
     * - the resulting gedcom shall not contain any references that are not also included in the cart
     *   so that the gedcom is well formed as a continuum including all referenced informations
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
     * get GEDCOM records from array with XREFs ready to export them
     * to Vizualisation-Modules - we want some references to be kept as 
     * informations in graphs
     *
     * @param Tree $tree
     * @param array $xrefs
     * @param array $v_xrefs
     * @param int $access_level
     *
     * @return Collection
     */
    private function getRecordsForVizualisation(Tree $tree, array $xrefs, array $v_xrefs, int $access_level): Collection
    {
        $records = new Collection();
        foreach ($xrefs as $xref) {
            $object = Registry::gedcomRecordFactory()->make($xref, $tree);
            // The object may have been deleted since we added it to the cart ...
            if ($object instanceof GedcomRecord) {
                $record = $object->privatizeGedcom($access_level);
                $record = $this->removeLinksToUnusedObjectsL23($record, $xrefs, $v_xrefs);
                $records->add($record);
            }
        }
        return $records;
    }

    /**
     * remove links to objects that aren't in the cart
     * - the resulting gedcom shall not contain any references that are not also included in the cart
     *   so that the gedcom is well formed as a continuum including all referenced informations
     * - BUT we want some informations to be holded so we have added the regarding xrefs to a second array
     *   we have to check for too
     *
     * @param string $record
     * @param array $xrefs
     * @param array $v_xrefs
     *
     * @return string
     */
    private function removeLinksToUnusedObjectsL23(string $record, array $xrefs, array $v_xrefs): string
    {
        preg_match_all('/\n1 ' . Gedcom::REGEX_TAG . ' @(' . Gedcom::REGEX_XREF . ')@(\n[2-9].*)*/', $record, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if (!in_array($match[1], $xrefs, true)) {
                if( !in_array($match[1], $v_xrefs, true)) {
                    $record = str_replace($match[0], '', $record);
                }
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

        $router = Registry::routeFactory()->routeMap();

        $router->attach('', '/tree/{tree}', static function (Map $router) {

            $router->get(ClippingsCartEnhancedModule::class, '/CCE')
                ->allows(RequestMethodInterface::METHOD_POST);

            $router->get(ClippingsCartEnhanced::class, '');

        });

        // Here is also a good place to register any views (templates) used by the module.
        // This command allows the module to use: view($this->name() . '::', 'fish')
        // to access the file ./resources/views/fish.phtml
        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');

        View::registerCustomView('::lists/surnames-tableCCE', $this->name() . '::lists/CCEsurnames-table');

        View::registerCustomView('::components/CCEbadgedText', $this->name() . '::components/CCEbadgedText');
        View::registerCustomView('::components/CCEbadge', $this->name() . '::components/CCEbadge');

        View::registerCustomView('::lists/families-table', $this->name() . '::lists/CCEfamilies-table');
        // View::registerCustomView('::lists/CCEfamilies-table-js', $this->name() . '::lists/CCEfamilies-table-js');

        View::registerCustomView('::lists/individuals-table', $this->name() . '::lists/CCEindividuals-table');
        // View::registerCustomView('::lists/CCEindividuals-table-js', $this->name() . '::lists/CCEindividuals-table-js');

        View::registerCustomView('::lists/CCEtable-IL-js', $this->name() . '::lists/CCEtable-IL-js');
        View::registerCustomView('::lists/CCEtable-FL-js', $this->name() . '::lists/CCEtable-FL-js');

        View::registerCustomView('::save-cart', $this->name() . '::save-cart');
        View::registerCustomView('::modals/saveCart', $this->name() . '::modals/CCEsaveCart');
        View::registerCustomView('::modals/footer-save-cancelCCE', $this->name() . '::modals/CCEfooter-save-cancel');
        View::registerCustomView('::modals/footer-checkedCCE', $this->name(). '::modals/CCEfooter-checked');
        View::registerCustomView('::icons/file-import', $this->name(). '::icons/file-import');
        View::registerCustomView('::icons/file-export', $this->name(). '::icons/file-export');
        View::registerCustomView('::icons/redoCCE', $this->name(). '::icons/redo');
        View::registerCustomView('::modals/CartSavedCCE', $this->name() . '::modals/CCE-CartSaved');
        View::registerCustomView('::modals/loadCart', $this->name() . '::modals/CCEloadCart');
        View::registerCustomView('::modals/CartLoadedCCE', $this->name() . '::modals/CCE-CartLoaded');
        View::registerCustomView('::modals/noneCartFile', $this->name() . '::modals/CCEnoneCartFile');
        View::registerCustomView('::modals/footer-backCCE', $this->name(). '::modals/CCEfooter-back');

        $CCEjs = $this->resourcesFolder() . 'js/CCEtable-actions.js';
        Session::put('CCEtable-actions.js', $CCEjs);
        $CCEcss = $this->resourcesFolder() . 'css/CCEtable-actions.css';
        Session::put('CCEtable-actions.css', $CCEcss);

        $this->TSMok = class_exists(TaggingServiceManager::class, true);

        foreach (self::OTHER_MENUES as $lkey => $actions) {
            $sess_key = 'CCElist-' . $lkey;
            Session::put($sess_key, $actions);
        }
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
