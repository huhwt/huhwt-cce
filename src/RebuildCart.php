<?php

declare(strict_types=1);

namespace HuHwt\WebtreesMods\ClippingsCartEnhanced;

use Fisharebest\Algorithm\ConnectedComponent;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Location;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\Menu;
use Fisharebest\Webtrees\Note;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Repository;
use Fisharebest\Webtrees\Source;
use Fisharebest\Webtrees\Submitter;
// use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Validator;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

// array functions
use function count;
use function array_keys;
use function strtolower;

class RebuildCart extends ClippingsCartEnhanced
{
    private Tree $tree;
    private array $cartAct;

    /**
     * constructor for this class
     *
     * @param Tree $tree
     */
    public function __construct(Tree $tree, $tcartAct)
    {
        $this->tree = $tree;
        $this->cartAct = $tcartAct;
    }

    public function rebuild()
    {
        // additional parameters may be required
        $bool_A1 = false;
        $bool_A2 = false;

        $cart = Session::get('cart', []);
        $cart[$this->tree->name()] = [];
        Session::put('cart', $cart);

        $tree = $this->tree;

        $cartActions = $this->cartAct;      // Session::get('cartAct', []);
        foreach ($cartActions as $cartact => $val)
        {
            $cartActs = explode('~', $cartact);
            $cAct = $cartActs[0];
            $xref = $cartActs[1];

            switch ($cAct) {
                case 'INDI':
                    $individual = Registry::individualFactory()->make($xref, $tree);
                    break;

                case 'INDI_PARENT_FAM':
                    $individual = Registry::individualFactory()->make($xref, $tree);
                    foreach ($individual->childFamilies() as $family) {
                        $this->addFamilyAndChildrenToCart($family);
                    }
                    break;

                case 'INDI_SPOUSE_FAM':
                    $individual = Registry::individualFactory()->make($xref, $tree);
                    foreach ($individual->spouseFamilies() as $family) {
                        $this->addFamilyAndChildrenToCart($family);
                    }
                    break;
    
                case 'INDI_ANCESTORS':
                    $caDo = $xref;
                    $caDos = explode('|', $caDo);
                    $xref = $caDos[0];
                    $levelAncestor = (int) $caDos[1];
                    $individual = Registry::individualFactory()->make($xref, $tree);
                    $this->addAncestorsToCart($individual, $levelAncestor);
                    break;
    
                case 'INDI_ANCESTOR_FAMILIES':
                    $caDo = $xref;
                    $caDos = explode('|', $caDo);
                    $xref = $caDos[0];
                    $levelAncestor = (int) $caDos[1];
                    $individual = Registry::individualFactory()->make($xref, $tree);
                    $this->addAncestorFamiliesToCart($individual, $levelAncestor);
                    break;
    
                case 'INDI_DESCENDANTS':
                    $caDo = $xref;
                    $caDos = explode('|', $caDo);
                    $xref = $caDos[0];
                    $levelDescendant = (int) $caDos[1];
                    $individual = Registry::individualFactory()->make($xref, $tree);
                    foreach ($individual->spouseFamilies() as $family) {
                        $this->addFamilyAndDescendantsToCart($family, $levelDescendant);
                    }
                    break;
    
                case 'INDI_PARTNER_CHAINS':
                    $individual = Registry::individualFactory()->make($xref, $tree);
                    $this->addPartnerChainsToCartIndividual($individual, $individual->spouseFamilies()[0]);
                    break;
    
                case 'INDI_LINKED_INDIVIDUALS':
                    $this->addAllLinked($tree, $xref);
                    break;

                case 'FAM':
                    $family = Registry::familyFactory()->make($xref, $tree);
                    $this->addFamilyToCart($family);
                    break;
    
                case 'FAM_AND_CHILDREN':
                    $family = Registry::familyFactory()->make($xref, $tree);
                    $this->addFamilyAndChildrenToCart($family);
                    break;
    
                case 'FAM_AND_DESCENDANTS':
                    $family = Registry::familyFactory()->make($xref, $tree);
                    $this->addFamilyAndDescendantsToCart($family);
                    break;
    
                case 'FAM_PARTNER_CHAINS':
                    $family = Registry::familyFactory()->make($xref, $tree);
                    $this->addPartnerChainsToCartFamily($family);
                    break;

                case 'ALL_PARTNER_CHAINS':
                    $this->addPartnerChainsGlobalToCart($tree);
                    break;
    
                case 'COMPLETE':
                    $this->addCompleteGEDtoCart($tree);
                    break;
    
                case 'ALL_CIRCLES':
                    $this->addAllCirclesToCart($tree);
                    break;

                case 'LOC':
                    $location = Registry::locationFactory()->make($xref, $tree);
                    $this->addLocationToCart($location);
                    break;

                case 'MEDIA':
                    $media = Registry::mediaFactory()->make($xref, $tree);
                    $this->addMediaToCart($media);
                    break;

                case 'NOTE':
                    $note = Registry::noteFactory()->make($xref, $tree);
                    $this->addNoteToCart($note);
                    break;

                case 'REPO':
                    $repository = Registry::repositoryFactory()->make($xref, $tree);
                    $this->addRepositoryToCart($repository);
                    foreach ($this->linked_record_service->linkedSources($repository) as $source) {
                        $this->addSourceToCart($source);
                    }
                    break;

                case 'SOUR':
                    $caDo = $xref;
                    $add_linked_indi = false;
                    if ( str_contains($caDo, '|') ) {
                        $caDos = explode('|', $caDo);
                        $xref = $caDos[0];
                        $add_linked_indi = true;
                    }
                    $source = Registry::sourceFactory()->make($xref, $tree);
                    $this->addSourceToCart($source);
                    if ($add_linked_indi) {
                        foreach ($this->linked_record_service->linkedIndividuals($source) as $individual) {
                            $this->addIndividualToCart($individual);
                        }
                        foreach ($this->linked_record_service->linkedFamilies($source) as $family) {
                            $this->addFamilyToCart($family);
                        }
                    }
                    break;

                case 'SUBM':
                    $submitter = Registry::submitterFactory()->make($xref, $tree);
                    $this->addSubmitterToCart($submitter);
                    break;

                case 'FAM-LISTwp':
                    // $this->cartAct($tree, 'FAM-LIST', $surname . ';' . $alpha . ';' . $show . ';' . $show_all . ';' . $show_marnm);
                    $bool_A1 = true;
                case 'FAM-LIST':
                    // $this->cartAct($tree, 'FAM-LIST', $surname . ';' . $alpha . ';' . $show . ';' . $show_all . ';' . $show_marnm);
                    $_FLparms = explode(';', $cartActs[1]);
                    $surname    = $_FLparms[0];
                    $alpha      = $_FLparms[1];
                    $show       = $_FLparms[2];
                    $show_all   = $_FLparms[3];
                    $show_marnm = $_FLparms[4];
                    $this->lPdone = false;
                    $this->CCEindiList = new CCEIndividualListModule($this);
                    $this->addFamilyList($tree, $surname, $alpha, $show_marnm, $show, $show_all, $bool_A1);
                    break;
                }
        }

        $url = route('module', [
            'module'      => parent::name(),
            'action'      => 'Show',
            'description' => parent::description(),
            'tree'        => $this->tree->name(),
        ]);

        return redirect($url);
    }
}
