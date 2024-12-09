<?php

/*
 * webtrees - clippings cart enhanced
 *
 * Copyright (C) 2023-2024 huhwt. All rights reserved.
 *
 * webtrees: online genealogy / web based family history software
 * Copyright (C) 2023 webtrees development team.
 *
 * This module handles the Ajax-Requests of injected CCE-function in ListModules.
 * It takes the reported XREF's and performs the collecting operations.
 * 
 */

declare(strict_types=1);

namespace HuHwt\WebtreesMods\ClippingsCartEnhanced;

use Fisharebest\Webtrees\Module\IndividualListModule;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Tree;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;

use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use HuHwt\WebtreesMods\ClippingsCartEnhanced\Traits\CC_addActions;
use HuHwt\WebtreesMods\ClippingsCartEnhanced\Traits\CCEaddActions;
use HuHwt\WebtreesMods\ClippingsCartEnhanced\Traits\CCEcartActions;
use HuHwt\WebtreesMods\ClippingsCartEnhanced\Traits\CCEvizActions;


/**
 * Class ClippingsCartEnhancedModule
 * 
 * @author  EW.H <GIT@HuHnetz.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/huhwt/huhwt-cce/
 */

 class ClippingsCartEnhancedModule extends AbstractModule implements RequestHandlerInterface
 {
    /** All constants and functions related to default ClippingsCartModule  */
    use CC_addActions;
    /** All constants and functions related to enhancements  */
    use CCEaddActions {
        CCEaddActions::addFamilyToCart insteadof CC_addActions;
        CCEaddActions::addIndividualToCart insteadof CC_addActions;
    }
    /** All constants and functions related to handling the Cart  */
    use CCEcartActions;
    /** All constants and functions related to connecting vizualizations  */
    use CCEvizActions;

     private $huh;

    /**
     * Retrieve all Record-Types
     * @var boolean
     */
    private bool $all_RecTypes;

     /**
     * @var array $cart
     */
    private array $cart;

    /**
     * @var array $CIfileData
     */
    private array $CIfileData;

    public function __construct() {
         $this->huh = json_decode('"\u210D"');

         $this->cart = $this->get_Cart();

         $this->all_RecTypes        = true;

         $this->CIfileData          = [];
    }

    /**
     * Catch the different ClippingsCart-Actions - called by listing-modules
    *
    * @param ServerRequestInterface $request
    *
    * @return ResponseInterface
    *
    */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $action = Validator::queryParams($request)->string('action');

        if ( $action == 'clipFamilies' ) {
            return response($this->clip_families($request));
        }

        if ( $action == 'clipIndividuals' ) {
            return response($this->clip_individuals($request));
        }

        if ( $action == 'RemoveCartAct' ) {
            return response($this->doRemoveCartAct($request));
        }

        if ( $action == 'RemoveCAfile' ) {
            return response($this->doRemoveCAfile($request));
        }

        if ( $action == 'CAsave' ) {
            return $this->doCartSave($request);
        }

        if ( $action == 'doCartSaveAction' ) {
            return $this->doCartSaveAction($request);
        }

        if ( $action == 'CAload' ) {
            return $this->doCartLoad($request);
        }

        if ( $action == 'doCartLoadAction' ) {
            return $this->doCartLoadAction($request);
        }

        if ( $action == 'doKillFileAction' ) {
            return $this->doKillFileAction($request);
        }

        if ( $action == 'CAfname' ) {
            return $this->doSetCAfname($request);
        }

        return response('_NIX_');
    }

    /**
     * save the cart-xrefs to file
     *
     * @param ServerRequestInterface $request
     *
     * @return string
     */
    private function doCartSave(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();

        $t_xrefs        = $this->getXrefsInCart($tree);
        if ( $t_xrefs == [] ) { return response('_NIX_'); }

        $title = I18N::translate('Save Cart');
        $label = I18N::translate('File name');

        $timestamp      = time();
        Session::put('CAsave_ts',(string) $timestamp);
        $fnameCA        = 'Cart(' . date("Ymd_His", $timestamp) . ')';
        Session::put('CAsave_fname', $fnameCA);

        $cAroute_ajax     = e(route(ClippingsCartEnhancedModule::class, ['module' => $this->name(), 'tree' => $tree->name()]));

        return response(view(name: 'modals/saveCart', data: [
            'tree'       => $tree,
            'title'      => $title,
            'label'      => $label,
            'fnameCA'    => $fnameCA,
            'cArouteAjax' => $cAroute_ajax,
        ]));
    }

    /**
     * save the cart-xrefs to file
     *
     * @param ServerRequestInterface $request
     *
     * @return string
     */
    private function doCartSaveAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();

        $CFuserDir      = Session::get('userDir');
        $timestamp      = (int) Session::get('CAsave_ts');
        $fnameCA        = Session::get('CAsave_fname');
        $fpathCA        = $CFuserDir . '/' . $fnameCA . '.txt';

        $CIfileData     = [];
        $fnameCI        = 'Cart-Index.txt';
        $fpathCI        = $CFuserDir . '/' . $fnameCI;

        $files_array    = scandir($CFuserDir);

        if( in_array($fnameCI, $files_array)) {
            $CIfiles        = json_decode(file_get_contents($fpathCI));
            foreach( $CIfiles as $fi => $fc) {
                $CIfileData[$fi] = json_decode(json_encode($fc), true);
            }
        }

        $t_cartActs     = $this->getCactsInCart($tree);
        $t_xrefs        = $this->getXrefsInCart($tree);

        $CIentry        = [];
        $CIentry['timestamp']   = date("Y-m-d_H:i:s", $timestamp);
        $CIentry['cartActs']    = $t_cartActs;

        $CIfileData[$fnameCA]   = $CIentry;

        file_put_contents($fpathCI, json_encode($CIfileData));

        $CAfileData     = [];
        $CAfileData['cartActs'] = $t_cartActs;
        $CAfileData['CAxrefs']  = $t_xrefs;

        file_put_contents($fpathCA, json_encode($CAfileData));

        $SinfoJson      = $this->count_CartTreeDataReport($tree);

        $_html = view('modals/CartSavedCCE', [
            'title'     => I18N::translate('Cart is saved to file'),
            'filename'  => $fnameCA,
            'SinfoJson' => $SinfoJson,
        ]);

        $_response = response([
            'value' => '_',
            'text'  => '_',
            'html'  => $_html,
        ]);
        return $_response;
        ;

    }

    /**
     * load cart-xrefs from file
     *
     * @param ServerRequestInterface $request
     *
     * @return string
     */
    private function doCartLoad(ServerRequestInterface $request): ResponseInterface
    {

        $called_by      = rawurldecode($_SERVER["REQUEST_URI"]);

        $act_uri        = (string) $request->getUri();

        $tree           = Validator::attributes($request)->tree();

        $title          = I18N::translate('Load Cart');
        $legend         = I18N::translate('Choose saved carts');

        $CFuserDir      = Session::get('userDir');

        $CIfileData     = [];
        $fnameCI        = 'Cart-Index.txt';
        $fpathCI        = $CFuserDir . '/' . $fnameCI;

        $files_array    = scandir($CFuserDir);

        if( in_array($fnameCI, $files_array)) {
            $CIfiles        = json_decode(file_get_contents($fpathCI));
            foreach( $CIfiles as $fi => $fc) {
                $CIfileData[$fi] = json_decode(json_encode($fc), true);
            }
            $this->CIfileData = $CIfileData;
            Session::put('CIfileData', $CIfileData);

            return response(view('modals/loadCart', [
                'tree'        => $tree,
                'title'       => $title,
                'legend'      => $legend,
                'CIfiData'    => $CIfileData,
                'calledBy'    => $called_by,
            ]));
        } else {
            return response(view('modals/noneCartFile', [
                'tree'        => $tree,
                'title'       => $title,
            ]));

        }

    }

    /**
     * save the cart-xrefs to file
     *
     * @param ServerRequestInterface $request
     *
     * @return string
     */
    private function doCartLoadAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();

        $CFuserDir      = Session::get('userDir');

        $CAfiKeys       = Validator::parsedBody($request)->array('CAfiKey');

        $SinfoCstold    = $this->count_CartTreeXrefs($tree);
        $SinfoAstold    = $this->count_CartTreeCacts($tree);

        foreach( $CAfiKeys as $fi => $fk) {
            $fiName         = $CFuserDir . DIRECTORY_SEPARATOR . $fk . '.txt';
            $CAfiData       = file_get_contents($fiName);
            $CAfiData_      = json_decode($CAfiData);
            $CAfiData_      = json_decode(json_encode($CAfiData_), true);
            $CAfiActs       = $CAfiData_['cartActs'];
            $CAfixrefs      = $CAfiData_['CAxrefs'];
            $this->execCartLoad($tree, $fk, $CAfiActs, $CAfixrefs);
        }

        $SinfoCstnew    = $this->count_CartTreeXrefs($tree);
        $SinfoAstnew    = $this->count_CartTreeCacts($tree);

        $Sinfo          = [];
        $Sinfo[]        = I18N::translate('Number of CartActs:');
        $Sinfo[]        = $SinfoAstnew;
        $Sinfo[]        = I18N::translate('of which are new:');
        $Sinfo[]        = $SinfoAstnew - $SinfoAstold;
        $Sinfo[]        = I18N::translate('Number of CartXrefs:');
        $Sinfo[]        = $SinfoCstnew;
        $Sinfo[]        = I18N::translate('of which are new:');
        $Sinfo[]        = $SinfoCstnew - $SinfoCstold;
        $SinfoJson = json_encode($Sinfo);

        $_html = view('modals/CartLoadedCCE', [
            'title'     => I18N::translate('Load Cart from file done'),
            'filenames' => $CAfiKeys,
            'SinfoJson' => $SinfoJson,
            'tree'      => $tree,
        ]);

        $_response = response([
            'value' => '_',
            'text'  => '_',
            'html'  => $_html,
        ]);
        return $_response;
    }
    private function execCartLoad(Tree $tree, string $fKey, array $fActs, array $fxrefs) : void
    {
        $CAsubst = [];
        $CAfiles = [];
        $cartAct = [];
        foreach( $fActs as $cAkey => $cAval) {
            $caV    = $this->put_CartActs_var($tree, $fKey);
            $this->put_CIfileData($tree, $fKey, $caV);
            $cAct   = $caV . $cAkey;
            if ( !array_key_exists($cAct, $CAsubst)) {
                $CAsubst[$cAkey]    = $cAct;
                $cartAct[$cAct]     = true;
            }
        }
        foreach( $CAsubst as $cactO => $cactN ) {
            $_cActO = stripos($cactO, '|')
                    ? substr($cactO,0,stripos($cactO,'|'))
                    : $cactO; 
            $_cActN = stripos($cactN, '|')
                    ? substr($cactN,0,stripos($cactN,'|'))
                    : $cactN;
            foreach ( $fxrefs as $xref => $xref_action ) {
                $_xref_action = $xref_action;
                if (str_contains($_xref_action, $_cActO)) {
                    $_xref_action = str_replace($_cActO, $_cActN, $_xref_action);
                    $fxrefs[$xref] = $_xref_action;
                }
            }
        }

        $_tree      = $tree->name();

        $S_cartAct  = Session::get('cartActs', []);
        $T_cacts    = $S_cartAct[$_tree] ?? [];

        foreach ( $cartAct as $CAkey => $CAbool) {
            if (($T_cacts[$CAkey] ?? '_NIX_') === '_NIX_') {
                $T_cacts[$CAkey] = true;
            }
        }
        $S_cartAct[$_tree] = $T_cacts;
        Session::put('cartActs', $S_cartAct);

        $S_cart     = Session::get('cart', []);
        $T_cart     = $S_cart[$_tree] ?? [];

        foreach ( $fxrefs as $fxref => $fcartActs) {
            if (($T_cart[$fxref] ?? '_NIX_') === '_NIX_') {
                $T_cart[$fxref] = $fcartActs;
            } else {
                $cartActs = $T_cart[$fxref];
                if (!is_bool($cartActs)) {
                    if (!str_contains($cartActs, $fcartActs)) {
                        $cartActs = $cartActs . ';' . $fcartActs;
                        $T_cart[$fxref] = $cartActs;
                    }
                } else {
                    $T_cart[$fxref] = $fcartActs;
                }

            }
        }
        $S_cart[$_tree] = $T_cart;
        Session::put('cart', $S_cart);

    }
    private function put_CartActs_var(Tree $tree, string $fKey): string
    {
        $V_keys = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'];

        $cartActsVariants  = Session::get('cartActsVariants', []);
        $cactVar           = $cartActsVariants[$tree->name()] ?? [];
        $iV                = count($cactVar);
        $caV               = $V_keys[$iV];
        if ( in_array($fKey, $cactVar) ) {
            $iV                 = array_search($fKey, $cactVar);
            $caV                = $V_keys[$iV];
        } else {
            $cactVar[]          = $fKey;
        }
        $caV = '::'. $caV .'::_';
        $cartActsVariants[$tree->name()]    = $cactVar;
        Session::put('cartActsVariants', $cartActsVariants);

        return $caV;
    }

    private function put_CIfileData(Tree $tree, string $fKey, string $caV): bool
    {
        $this->CIfileData   = Session::get('CIfileData');
        $CIfileData         = $this->CIfileData[$fKey];

        $T_CAfiles          = $this->getCactfilesInCart($tree);

        $CIfileValues       = I18N::translate('Filekey') . ': ' . $fKey . ' - ' . I18N::translate('Timestamp') . ': ' . $CIfileData['timestamp'];

        $T_CAfiles[$caV]    = $CIfileValues;

        $this->put_CartActTreeFiles($tree, $T_CAfiles);

        return true;
    }


    /**
     * kill saved cart
     *
     * @param ServerRequestInterface $request
     *
     * @return string
     */
    private function doKillFileAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree           = Validator::attributes($request)->tree();

        $KFname         = Validator::queryParams($request)->string('fname');

        $calledBy       = Validator::queryParams($request)->string('calledby');

        $server         = $_SERVER['SERVER_NAME'];
        $request_scheme = $_SERVER['REQUEST_SCHEME'];
        $redUri         = $request_scheme . '://' . $server . $calledBy;

        $CFuserDir      = Session::get('userDir');
        if ( $CFuserDir === null) {
            return redirect($redUri);
        }

        $CIfileData     = [];
        $fnameCI        = 'Cart-Index.txt';
        $fpathCI        = $CFuserDir . '/' . $fnameCI;

        $files_array    = scandir($CFuserDir);

        if( in_array($fnameCI, $files_array)) {
            $CIfiles        = json_decode(file_get_contents($fpathCI));
            foreach( $CIfiles as $fi => $fc) {
                $CIfileData[$fi] = json_decode(json_encode($fc), true);
            }
        }

        $CIfileData_upd = [];
        foreach ( $CIfileData as $fname => $fdata) {
            if( $fname != $KFname) {
                $CIfileData_upd[$fname] = $fdata;
            }
        }
        $fpathKF        = $CFuserDir . '/' . $KFname . '.txt';
        unlink( $fpathKF );

        if (count($CIfileData_upd) > 0) {
            file_put_contents($fpathCI, json_encode($CIfileData_upd));
        } else {
            unlink( $fpathCI );
        }

        return redirect($redUri);

    }

    /**
     * put the individual fname to session
     *
     * @param ServerRequestInterface $request
     *
     * @return string
     */
    private function doSetCAfname(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();

        $CAfname = Validator::queryParams($request)->string('CAfname');

        Session::put('CAsave_fname', $CAfname);

        return response('CAfname_Done');
    }

    /**
     * Fetch a list of families with specified names
     * To search for unknown names, use $surn="@N.N.", $salpha="@" or $galpha="@"
     * To search for names with no surnames, use $salpha=","
     *
     * @param ServerRequestInterface $request
     * 
     * @return string           number of records in cart
     */
    public function clip_families(ServerRequestInterface $request): string
    {
        $tree = Validator::attributes($request)->tree();

        // with parents?
        $boolWp = (Validator::queryParams($request)->string('boolWp', 'no') == 'yes');

        $CCEkey = (Validator::queryParams($request)->string('CCEkey', 'clip_fam'));

        // the actual search parameters of origin request
        $actSEARCH = $this->cleanSearch(Validator::queryParams($request)->string('actSEARCH', ''));
        $actSEARCH_p = $this->getSearch($actSEARCH);

        // the actual page in DataTable
        $actPage_ = Validator::queryParams($request)->string('actPage','');
        if (array_key_exists('surname',$actSEARCH_p)) {
            $actPage = $actSEARCH_p['surname'] . '=' . $actPage_ . '=';
        } else if (array_key_exists('alpha',$actSEARCH_p)) {
            $actPage = $actSEARCH_p['alpha'] . '=' . $actPage_ . '=';
        } else {
            $actPage = '_' . '=' . $actPage_ . '=';
        }

        // the XREFs
        $xrefs = Validator::queryParams($request)->string('xrefs', '');
        $xrefsCold = $this->count_CartTreeXrefs($tree);                // Count of xrefs actual in stock
        if ($xrefs == '')
            return (string) $xrefsCold;

        $XREFs = explode(';', $xrefs);

        $families = $this->make_GedcomRecords($tree, $XREFs);

        $caKey = $boolWp ? $CCEkey . 'wp' : $CCEkey;
        $caKey = $this->put_CartActs($tree, $caKey, $actSEARCH, $actPage);
        $_dname = 'wtVIZ-DATA~' . $caKey . '|' . $actSEARCH;
        $this->putVIZdname($_dname);

        foreach($families  as $family) {
            $this->addFamilyToCart($family);
        }
        if ($boolWp) {
            foreach($families  as $family) {
                $indi_wife = $family->wife();
                if ($indi_wife)
                    $this->toCartParents($indi_wife);
                $indi_husb = $family->husband();
                if ($indi_husb)
                    $this->toCartParents($indi_husb);
            }
        }

        return $this->count_CartTreeXrefsReport($tree, $xrefsCold);
    }

    /**
     * Fetch a list of individuals with specified names
     * To search for unknown names, use $surn="@N.N.", $salpha="@" or $galpha="@"
     * To search for names with no surnames, use $salpha=","
     *
     * @param ServerRequestInterface $request
     * 
     * @return string           number of records in cart
     */
    public function clip_individuals(ServerRequestInterface $request): string
    {
        $tree = Validator::attributes($request)->tree();

        // with parents?
        $boolWp = (Validator::queryParams($request)->string('boolWp', 'no') == 'yes');
        // with spouses?
        $boolWs = (Validator::queryParams($request)->string('boolWs', 'no') == 'yes');
        // with children?
        $boolWc = (Validator::queryParams($request)->string('boolWc', 'no') == 'yes');
        // with all relations?
        $boolWa = (Validator::queryParams($request)->string('boolWa', 'no') == 'yes');

        $CCEkey = (Validator::queryParams($request)->string('CCEkey', 'clip_indi'));

        $actSEARCH = $this->cleanSearch(Validator::queryParams($request)->string('actSEARCH', ''));
        $actSEARCH_ = $actSEARCH;
        $is_search = false;
        if (str_starts_with($CCEkey, 'SEARCH')) {
            $is_search = true;
            if (str_starts_with($CCEkey, 'SEARCH_G')) {
                $actSEARCH_p = $this->getSearch_G($actSEARCH);
            } else if (str_starts_with($CCEkey, 'SEARCH_A')) {
                $actSEARCH_p = $this->getSearch_A($actSEARCH);
            }
        } else {
            // the actual search parameters of origin request
            $actSEARCH_p = $this->getSearch($actSEARCH);
        }

        // the actual page in DataTable
        $actPage_ = (Validator::queryParams($request)->string('actPage',''));
        $actPage  = '_';
        if ($is_search) {
            if (array_key_exists('query',$actSEARCH_p)) {
                $actPage = $actSEARCH_p['query'];
                $actSEARCH_ = '';
                foreach($actSEARCH_p as $s_key => $s_val) {
                    if ($s_val != '0') {
                        $actSEARCH_ .= '&' . $s_key . '=' . $s_val;
                    }
                }
            } else {
                $actPage = '';
                $actSEARCH_ = '';
                foreach($actSEARCH_p as $s_key => $s_val) {
                    $actSEARCH_ .= '&' . $s_key . '=' . $s_val;
                    if(str_starts_with($s_key, 'fields')) {
                        if ($actPage == '')
                            $actPage = $s_val;
                        else
                            $actPage .= '&' . $s_val;
                    }
                }
            }
            // $actPage  = implode('&', $actSEARCH_p);
        } else {
            if (array_key_exists('surname',$actSEARCH_p)) {
                $actPage = $actSEARCH_p['surname'];
            } else if (array_key_exists('alpha',$actSEARCH_p)) {
                $actPage = $actSEARCH_p['alpha'];
            }
        }
        $actPage = $actPage . ' [' . $actPage_ . ']';

        // the XREFs
        $xrefs = Validator::queryParams($request)->string('xrefs', '');
        $xrefsCold = $this->count_CartTreeXrefs($tree);                // Count of xrefs actual in stock
        if ($xrefs == '')
            return (string) $xrefsCold;

        $XREFs = explode(';', $xrefs);

        $individuals = $this->make_GedcomRecords($tree, $XREFs);

        $caKey = $CCEkey;
        // if ($boolWp)
        //     $caKey = $caKey . 'wp';
        // if ($boolWs)
        //     $caKey = $caKey . 'ws';
        $caKey = $this->put_CartActs($tree, $caKey, $actSEARCH_, $actPage);
        $_dname = 'wtVIZ-DATA~' . $caKey . '|' . $actSEARCH_;
        $this->putVIZdname($_dname);

        foreach($individuals  as $individual) {
            $this->addIndividualToCart($individual);
        }
        if ($boolWp) {
            foreach($individuals  as $individual) {
                $this->toCartParents($individual);
            }
        }
        if ($boolWs) {
            foreach($individuals  as $individual) {
                foreach ($individual->spouseFamilies() as $family) {
                    $this->addFamilyToCart($family);
                }
            }
        }
        if ($boolWc) {
            foreach($individuals  as $individual) {
                foreach ($individual->spouseFamilies() as $family) {
                    $this->addFamilyAndChildrenToCart($family);
                }
            }
        }
        if ($boolWa) {
            foreach($individuals  as $individual) {
                $this->toCartParents($individual);
                foreach ($individual->spouseFamilies() as $family) {
                    $this->addFamilyAndChildrenToCart($family);
                }
            }
        }

        return $this->count_CartTreeXrefsReport($tree, $xrefsCold);
    }

    /**
     * Fetch a list of individuals with specified names - called by chart-modules
     *
     *
     * @param ServerRequestInterface $request
     * 
     * @return string           number of records in cart
     */
    public function clip_mtv(ServerRequestInterface $request): string
    {
        $tree = Validator::attributes($request)->tree();

        $XREFindi   = Validator::queryParams($request)->string('XREFindi', '');

        // the XREFs
        $xrefs = Validator::queryParams($request)->string('xrefs', '');
        $xrefsCold = $this->count_CartTreeXrefs($tree);                // Count of xrefs actual in stock
        if ($xrefs == '')
            return (string) $xrefsCold;

        $XREFs = explode(';', $xrefs);

        $individuals = $this->make_GedcomRecords($tree, $XREFs);

        $caKey = 'MTV';
        $caKey = $this->put_CartActs($tree, $caKey, $XREFindi);
        $_dname = 'wtVIZ-DATA~' . $caKey;
        $this->putVIZdname($_dname);

        foreach($individuals  as $individual) {
            $this->addIndividualToCart($individual);

            $this->toCartParents($individual);

            foreach ($individual->spouseFamilies() as $family) {
                $indi_wife = $family->wife();
                if ($indi_wife)
                    $this->addIndividualToCart($indi_wife);
                $indi_husb = $family->husband();
                if ($indi_husb)
                    $this->addIndividualToCart($indi_husb);

                $this->addFamilyWithoutSpousesToCart($family);

                $this->addMediaLinksToCart($family);
    
                }
        }

        return $this->count_CartTreeXrefsReport($tree, $xrefsCold);
    }


    /**
     * Fetch a list of individuals with specified names - called by chart-modules
     *
     *
     * @param ServerRequestInterface $request
     * 
     * @return string           number of records in cart
     */
    public function clip_xtv(ServerRequestInterface $request): string
    {
        $tree = Validator::attributes($request)->tree();

        $XREFindi   = Validator::queryParams($request)->string('XREFindi', '');

        // the XREFs
        $XREFs = json_decode((Validator::parsedBody($request)->string('xrefs','')),true);
        $xrefsCold = $this->count_CartTreeXrefs($tree);                // Count of xrefs actual in stock
        if ($XREFs == [])
            return (string) $xrefsCold;

        // $XREFs = explode(';', $xrefs);

        $records = array_map(static function (string $xref) use ($tree): ?GedcomRecord {
            return Registry::gedcomRecordFactory()->make($xref, $tree);
        }, $XREFs);

        $caKey = 'XTV';
        $caKey = $this->put_CartActs($tree, $caKey, $XREFindi);
        $_dname = 'wtVIZ-DATA~' . $caKey;
        $this->putVIZdname($_dname);

        $all_RT = $this->all_RecTypes;
        $this->all_RecTypes = false;
        
        foreach ($records as $record) {
            if ($record instanceof Individual) {
                $this->addIndividualToCart($record);
            } else if ($record instanceof Family) {
                $this->addFamilyToCart($record);
            }
        }

        $this->all_RecTypes = $all_RT;

        return $this->count_CartTreeXrefsReport($tree, $xrefsCold);
    }

    /**
     * get the webtrees entities corresponding to xref-ids
     *
     * @param Tree            $tree
     * @param array<string>   $XREFs
     * 
     * @return array
     */
    public function make_GedcomRecords(Tree $tree, array $XREFs): array
    {
        $records = array_map(static function (string $xref) use ($tree): ?GedcomRecord {
            return Registry::gedcomRecordFactory()->make($xref, $tree);
        }, $XREFs);


        return $records;
    }

    /**
     * there is a bunch of search declarations, some of them are empty -> not set ...
     * ... eliminate them
     * 
     * @param string            $p_actSearch
     * 
     * @return string           remaining significant declarations
     */
    private function cleanSearch($p_actSearch) : string
    {
        if ($p_actSearch == '')
            return '';

        $actSearch_x = explode('&', $p_actSearch);

        $actSearch_ = [];
        foreach($actSearch_x  as $search) {
            if ($search > '') {
                $search_x = explode('=', $search);
                if ($search_x[1] > '') {
                    $search_ = $search_x[0] . '=' . $search_x[1];
                    $actSearch_ [] = $search_;
                }
            }
        }

        $actSearch = '&' . implode('&', $actSearch_);

        return $actSearch;
    }

    /**
     * there is a bunch of search declarations, we want them as keyed array
     * 
     * @param string            $p_actSearch
     * 
     * @return array<string,string>         key     search term         value   search parm
     */
    private function getSearch($p_actSearch) : array
    {
        if ($p_actSearch == '')
            return [''];

        $actSearch_x = explode('&', $p_actSearch);

        $actSearch_ = [];
        foreach($actSearch_x  as $search) {
            if ($search > '') {
                $search_x = explode('=', $search);
                if ($search_x[1] > '') {
                    $actSearch_ [$search_x[0]] = $search_x[1];
                }

            }
        }

        return $actSearch_;
    }

    /**
     * there is a bunch of search declarations, we want them as keyed array
     * 'SEARCH_G' is the parameters of 'search-general'
     * We want only 'query', all other parameters are irrelevant
     * 
     * @param string            $p_actSearch
     * 
     * @return array<string,string>         key     search term         value   search parm
     */
    private function getSearch_G($p_actSearch) : array
    {
        if ($p_actSearch == '')
            return [''];

        $actSearch_x = explode('&', $p_actSearch);

        $actSearch_ = [];
        foreach($actSearch_x  as $search) {
            if ($search > '') {
                if (str_starts_with($search, 'query')) {
                    $search_x = explode('=', $search);
                    if ($search_x[1] > '') {
                        $actSearch_ [$search_x[0]] = $search_x[1];
                    }
                } else {
                    $search_x = explode('=', $search);
                    if ($search_x[1] == '1') {
                        $actSearch_ [$search_x[0]] = $search_x[1];
                    }
                }
            }
        }

        return $actSearch_;
    }

    /**
     * there is a bunch of search declarations, we want them as keyed array
     * 'SEARCH_A' is the parameters of 'search-advanced'
     * We have a set of fields and a second set of modifiers.
     * Modifiers are explicitly defined but have no sense when the corresponding
     * field isn't set. 
     * So we have to check, if a modifier is valid because the referred field is valid too.
     * 
     * @param string            $p_actSearch
     * 
     * @return array<string,string>         key     search term         value   search parm
     */
    private function getSearch_A($p_actSearch) : array
    {
        if ($p_actSearch == '')
            return [''];

        $actSearch_x = explode('&', $p_actSearch);

        $actSearch_fields = [];
        $actSearch_modifiers = [];
        foreach($actSearch_x  as $search) {
            if ($search > '') {
                $search_x = explode('=', $search);
                if ($search_x[1] > '') {
                    $s_0    = $search_x[0];
                    if (str_starts_with($s_0, 'fields')) {
                        $actSearch_fields [$s_0] = $search_x[1];
                    } else {
                        $s_key = 'fields' . str_replace('modifier','',$s_0);
                        if (array_key_exists($s_key, $actSearch_fields)) {
                            $actSearch_modifiers [$s_0] = $search_x[1];
                        }
                    }
                }
            }
        }
        $actSearch_ = [];

        foreach($actSearch_fields as $s_key => $s_val) {
            $actSearch_ [$s_key] = $s_val;
        }
        foreach($actSearch_modifiers as $s_key => $s_val) {
            $actSearch_ [$s_key] = $s_val;
        }

        return $actSearch_;
    }

    private function doRemoveCartAct(ServerRequestInterface $request): string
    {
        $tree = Validator::attributes($request)->tree();

        // the actual page in DataTable
        $cartAct = (Validator::queryParams($request)->string('cartact',''));
        // $cAct = $cartAct.str_contains($cartAct,'|') ? substr($cartAct,0,stripos($cartAct,'|')) : $cartAct;
        if (str_contains($cartAct,'|')) {
            $cAct = substr($cartAct,0,stripos($cartAct,'|'));
        } else {
            $cAct = $cartAct;
        }
        // the XREFs
        $xrefs = Validator::queryParams($request)->string('xrefs', '');

        if ($xrefs > '') {
            $XREFs = explode(';', $xrefs);
            $cart = Session::get('cart', []);
            $_tree = $tree->name();
            foreach ($XREFs as $xref) {
                if (($cart[$_tree][$xref] ?? '_NIX_') != '_NIX_') {
                    $xref_action = $cart[$_tree][$xref];
                    $xref_actions = explode(';', $xref_action);
                    $ica = array_search($cAct, $xref_actions);
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
        }

        if ($cartAct > '') {
            $this->clean_CartActs_cact($tree, $cartAct);
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
    public function doRemoveCAfile(ServerRequestInterface $request): ResponseInterface
    {
        $tree   = Validator::attributes($request)->tree();
        $_tree  = $tree->name();

        $caV    = Validator::queryParams($request)->string('cafkey');

        // let's get the pieces - we need the raw data
        $cartActFiles   = Session::get('cartActsFiles', []);

        $cartActS       = Session::get('cartActs', []);
        $cartActT       = $cartActS[$_tree] ?? [];

        $cart           = Session::get('cart', []);
        $cartT          = $cart[$_tree] ?? [];

        // now starts the cleaning

        $T_cacts = [];
        foreach ( $cartActT as $cartAct => $val) {
            if (str_starts_with($cartAct, $caV)) {
                // we have to remove the cartAct from the XREFs

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

            } else {
                $T_cacts[$cartAct] = $val;
            }
        }
        Session::put('cart', $cart);

        unset($cartActS[$_tree]);
        $cartActS[$_tree]    = $T_cacts;
        Session::put('cartActs', $cartActS);

        unset($cartActFiles[$_tree][$caV]);
        Session::put('cartActsFiles', $cartActFiles);

        $url = route('module', [
            'module'      => $this->name(),
            'action'      => 'ShowCart',
            'description' => $this->description(),
            'tree'        => $tree->name(),
        ]);

        return redirect($url);
    }

}