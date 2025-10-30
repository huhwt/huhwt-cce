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
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Tree;

use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Location;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\Note;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Repository;
use Fisharebest\Webtrees\Source;
use Fisharebest\Webtrees\Submitter;

/**
 * Trait CCEcartActions - bundling all actions regarding Session::cart
 */
trait CCEcartActions
{
    public string $cartAction;

    public array $cartXREFs;

    /**
     * cart_addActions_Keys - Definitions of known CartActions
     * @var array
     */
    private const CART_ACTIONS_KEYS = [];
    //     // called from ClippingsCartEnhanced
    //     'ALL_PARTNER_CHAINS'             => I18N::translate('cartAct-ALL_PARTNER_CHAINS'),
    //     'COMPLETE'                       => I18N::translate('cartAct-COMPLETE'),
    //     'ALL_LINKED'                     => I18N::translate('cartAct-ALL_LINKED'),
    //     'ALL_LINKED_WO'                  => I18N::translate('cartAct-ALL_LINKED_WO'),
    //     'ALL_CIRCLES'                    => I18N::translate('cartAct-ALL_CIRCLES'),
    //     // called from CCEaddActions
    //     'FAM'                            => I18N::translate('cartAct-FAM'),
    //     'FAM_AND_CHILDREN'               => I18N::translate('cartAct-FAM_AND_CHILDREN'),
    //     'FAM_AND_DESCENDANTS'            => I18N::translate('cartAct-FAM_AND_DESCENDANTS'),
    //     'INDI'                           => I18N::translate('cartAct-INDI'),
    //     'INDI_PARENT_FAM'                => I18N::translate('cartAct-INDI_PARENT_FAM'),
    //     'INDI_SPOUSE_FAM'                => I18N::translate('cartAct-INDI_SPOUSE_FAM'),
    //     'INDI_ANCESTORS'                 => I18N::translate('cartAct-INDI_ANCESTORS'),
    //     'INDI_ANCESTORS_FAMILIES'        => I18N::translate('cartAct-INDI_ANCESTORS_FAMILIES'),
    //     'INDI_DESCENDANTS'               => I18N::translate('cartAct-INDI_DESCENDANTS'),
    //     'INDI_PARTNER_CHAINS'            => I18N::translate('cartAct-INDI_PARTNER_CHAINS'),
    //     'INDI_LINKED_INDIVIDUALS'        => I18N::translate('cartAct-INDI_LINKED_INDIVIDUALS'),
    //     'NOTE'                           => I18N::translate('cartAct-NOTE'),
    //     'NOTE_PERSONS'                   => I18N::translate('cartAct-NOTE_PERSONS'),
    //     'NOTE_PERSONSwp'                 => I18N::translate('cartAct-NOTE_PERSONSwp'),
    //     'NOTE_FAMILIES'                  => I18N::translate('cartAct-NOTE_FAMILIES'),
    //     'SOUR'                           => I18N::translate('cartAct-SOUR'),
    //     'LOC'                            => I18N::translate('cartAct-LOC'),
    //     'MEDIA'                          => I18N::translate('cartAct-MEDIA'),
    //     'REPO'                           => I18N::translate('cartAct-REPO'),
    //     'SUBM'                           => I18N::translate('cartAct-SUBM'),
    //     // called from CCEcartActions
    //     'SHOW'                           => I18N::translate('cartAct-SHOW'),
    //     // called from FAMILY-LIST
    //     'clip_fam'                       => I18N::translate('cartAct-clip_fam'),
    //     // called from INDI-LIST - either from List-Modules or Search-Modules
    //     'clip_indi'                      => I18N::translate('cartAct-clip_indi'),
    //     'SEARCH_G'                       => I18N::translate('cartAct-SEARCH_G'),
    //     'SEARCH_A'                       => I18N::translate('cartAct-SEARCH_A'),
    // ];

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

    /**
     * @param Tree $tree
     *
     * @return bool
     */
    private function isCartEmpty(Tree $tree): bool
    {
        $cart     = Session::get('cart', []);
        $cart     = is_array($cart) ? $cart : [];
        $contents = $cart[$tree->name()] ?? [];
        $isEmpty  = $contents === [];

        if ( $isEmpty ) {
            $this->clean_CartActs($tree);
        }

        return $isEmpty;
    }

    /**
     * Get the Xrefs in the clippings cart -> we want the complete XREF-structure.
     *
     * @param Tree $tree
     *
     * @return array
     * 
     * There might be Xrefs collected by other ClippingCart-Module. In those cases
     * there is no actions-structure, but only a boolean value. We mock an action ...
     */
    private function getXREFstruct(Tree $tree): array
    {
        $act_added  = false;
        $ts_request = $_SERVER['REQUEST_TIME'];
        $new_action = '_Other_CC_' . date('Y-m-d_H-i-s', $ts_request);
        $new_actKey = 'SHOW~' . $new_action;

        $cart       = Session::get('cart', []);
        $xrefs      = $cart[$tree->name()] ?? [];
        $_xrefs     = [];

        foreach ($xrefs as $xref => $actions) {
            $_xref = (string)$xref;
            if (is_bool($actions) === false) {
                $_xrefs[$_xref] = $actions;
            } else {
                $_xrefs[$_xref] = $new_actKey;
                $cart[$tree->name()][$xref] = $new_actKey;
                $act_added = true;
            }
        }
        if ($act_added) {
            $this->put_CartActs($tree, 'SHOW', $new_action);
            Session::put('cart', $cart);
        }
        return $_xrefs;
    }

    /**
     * Get the Xrefs in the clippings cart -> we want solely the XREF-ids in an array.
     *
     * @param Tree $tree
     *
     * @return array
     */
    private function get_CartXrefs(Tree $tree): array
    {
        $cart = Session::get('cart', []);
        $xrefs = array_keys($cart[$tree->name()] ?? []);
        $_xrefs = array_map('strval', $xrefs);           // PHP converts numeric keys to integers.
        return $_xrefs;
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
        $cart  = Session::get('cart', []);
        $xrefs = $cart[$tree->name()] ?? [];
        return $xrefs;
    }
    /**
     * Get the CartActs in the clippings cart.
     *
     * @param Tree $tree
     *
     * @return array
     */
    private function getCactsInCart(Tree $tree): array
    {
        $cartActs   = Session::get('cartActs', []);
        $cacts      = $cartActs[$tree->name()] ?? [];
        return $cacts;
    }

    /**
     * Get the different combinations of actions in the xrefs.
     *
     * @param array $xrefs
     *
     * @return array
     * 
     */
    private function getCactsFilter(array $xrefs): array
    {
        $_caActions  = [];

        foreach ($xrefs as $xref => $actions) {
            if (($_caActions[$actions] ?? '_NIX_') === '_NIX_') {
                $_caActions[$actions] = $actions;
            }
        }
        return $_caActions;
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
     * @param Tree $tree
     * @param string $xref
     * 
     * @return bool
     */

    public function put_Cart(Tree $tree, string $xref): bool
    {
        $ts_request = $_SERVER['REQUEST_TIME'];
        $new_action = '_Other_CC_' . date('Y-m-d_H-i-s', $ts_request);
        $new_actKey = 'SHOW~' . $new_action;

        $cart = Session::get('cart');
        $cart = is_array($cart) ? $cart : [];

        $_tree = $tree->name();

        if (($cart[$_tree][$xref] ?? '_NIX_') === '_NIX_') {
            $cartAct = $this->cartAction;
            $cart[$_tree][$xref] = $cartAct;
            Session::put('cart', $cart);
            return true;
        } else {
            $cartAct = $cart[$_tree][$xref];
            if (!is_bool($cartAct)) {
                if (!str_contains($cartAct, $this->cartAction)) {
                    $cartAct = $cartAct . ';' . $this->cartAction;
                    $cart[$_tree][$xref] = $cartAct;
                    Session::put('cart', $cart);
                }
            } else {
                $cart[$_tree][$xref] = $new_actKey . ';' . $this->cartAction;
                Session::put('cart', $cart);
                $this->put_CartActs($tree, 'SHOW', $new_action);
            }
            return false;

        }
    }

    private function get_Cart() : array
    {
        // clippings cart is an array in the session specific for each tree
        $cart  = Session::get('cart', []);
        if ( !is_array($cart) ) {
            $cart = [];
            Session::put('cart', $cart);
        }
        return $cart;
   }

   private function remove_Cart(Tree $tree, $xref) : void
   {
        $S_cart = Session::get('cart', []);
        unset($S_cart[$tree->name()][$xref]);
        Session::put('cart', $S_cart);
   }

   private function clean_Cart(Tree $tree) : void
   {
        $S_cart = Session::get('cart', []);
        $S_cart[$tree->name()] = [];
        Session::put('cart', $S_cart);

        if ($this->TSMok) {
            $S_tags = Session::get('tags', []);
            $S_tags[$tree->name()] = [];
            Session::put('tags', $S_tags);
        }
        
        $this->clean_CartActs($tree);

   }

   private function get_CartActs(Tree $tree) : array
   {
       $S_cartActs    = Session::get('cartActs', []);
       $S_cartActs    = array_keys($S_cartActs[$tree->name()] ?? []);
       $S_cartActs    = array_map('strval', $S_cartActs);           // PHP converts numeric keys to integers.
       return $S_cartActs;
   }

   private function clean_CartActs(Tree $tree) : void
    {
        $_tree        = $tree->name();

        $S_cartActs   = Session::get('cartActs', []);
        $S_cartActs[$_tree] = [];
        Session::put('cartActs', $S_cartActs);

        $cartActsDiversity = Session::get('cartActsDiversity', []);
        $cartActsDiversity[$_tree] = [];
        $cartActsDiversity[$_tree][0] = 0;
        Session::put('cartActsDiversity', $cartActsDiversity);

        $cartActsVariants  = Session::get('cartActsVariants', []);
        $cartActsVariants[$_tree] = [];
        Session::put('cartActsVariants', $cartActsVariants);

        $cartActsFiles  = Session::get('cartActsFiles', []);
        $cartActsFiles[$_tree] = [];
        Session::put('cartActsFiles', $cartActsFiles);

        if ($this->TSMok) {
            $tagsAct = Session::get('tagsActs', []);
            $tagsAct[$_tree] = [];
            Session::put('tagsActs', $tagsAct);
        }
    }

    private function clean_CartActs_cact(Tree $tree, string $cact) : array
    {
        $S_cartActs = Session::get('cartActs', []);
        unset($S_cartActs[$tree->name()][$cact]);
        Session::put('cartActs', $S_cartActs);
        return $S_cartActs;
    }

    private function put_CartActs(Tree $tree, string $action, string $Key, string $altKey = '', bool $doDiversity = true) : string
    {
        $S_cartActs = Session::get('cartActs', []);
        $retval = $action;
        if ($altKey == '') {
            $caction = $action . '~' . $Key;
            $this->cartAction = $caction;
        } else {
            if ($doDiversity) {
                [$retval, $caction] = $this->put_CartActs_alt($tree, $action, $Key, $altKey);
            } else {
                $caction = $action . '~' . $altKey;
                $this->cartAction = $caction;
                $retval = $this->cartAction;
                $caction = $caction . '|' . $Key;
            }
        }

        if (($S_cartActs[$tree->name()][$caction] ?? false) === false) {
            $S_cartActs[$tree->name()][$caction] = true;
            Session::put('cartActs', $S_cartActs);
        }
        return $retval;
    }
    private function put_CartActs_alt(Tree $tree, string $action, string $Key, string $altKey): array
    {
        $caction = $action . '~' . $altKey;                                         // this combination may not be significant ...

        $cartActsDiversity = Session::get('cartActsDiversity', []);                 // ... so we generate a identifying prefix ...
        if (($cartActsDiversity[$tree->name()][0] ?? false) === false)
            $cartActsDiversity[$tree->name()] [0] = 0;                              // ... quite simply - a serial number ...
        $cALc = $cartActsDiversity[$tree->name()] [0];
        $cALc += 1;                                                                 // ... each entry gets a consecutive number ...
        $caction = '('.(string) $cALc .')'. $caction;
        $cartActsDiversity[$tree->name()][0] = $cALc;                               // ... that is stored ...
        $cartActsDiversity[$tree->name()][$caction] = true;                         // ... and the entry is filed
        Session::put('cartActsDiversity', $cartActsDiversity);

        $this->cartAction = $caction;                                               // this will be set as marker in xrefs ...
        $retval = $this->cartAction;
        $caction = $caction . '|' . $Key;                                           // ... and this will be shown in cartAct list
        return [$retval, $caction];
    }

    /**
     * @param Tree              $tree
     */
    private function getCactfilesInCart(Tree $tree) : array
    {
        $S_CAfiles          = Session::get('cartActsFiles', []);
        $T_CAfiles          = $S_CAfiles[$tree->name()] ?? [];

        return $T_CAfiles;
    }

    /**
     * @param Tree              $tree
     */
    private function put_CartActTreeFiles(Tree $tree, array $T_CAfiles) : bool
    {
        $S_CAfiles          = Session::get('cartActsFiles', []);
        $S_CAfiles[$tree->name()]   = $T_CAfiles;

        Session::put('cartActsFiles', $S_CAfiles);

        return true;
    }
    /**
     * @param Tree              $tree
     */
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



    /**
     * @param Tree              $tree
     */
    private function count_CartTreeXrefs(Tree $tree) : int
    {
        $S_cart = Session::get('cart', []);
        $xrefs  = $S_cart[$tree->name()] ?? [];
        return count($xrefs);
    }

    /**
     * @param Tree              $tree
     * @param int               $xrefsCold
     */
    private function count_CartTreeXrefsReport(Tree $tree, int $xrefsCold) : string
    {
        $SinfoCstock = $this->count_CartTreeXrefs($tree);                // Count of xrefs actual in stock - updated
        $SinfoCadded = $SinfoCstock - $xrefsCold;
        $Sinfo = [];
        $Sinfo[] = $SinfoCstock;
        $Sinfo[] = $SinfoCadded;
        $Sinfo[] = I18N::translate('Total number of entries: %s', (string) $SinfoCstock);
        $Sinfo[] = I18N::translate('of which new entries: %s', (string) $SinfoCadded);
        $SinfoJson = json_encode($Sinfo);
        return $SinfoJson;
    }

    /**
     * Get the CartActs in the clippings cart.
     *
     * @param Tree $tree
     *
     * @return int
     */
    private function count_CartTreeCacts(Tree $tree): int
    {
        $S_cartActs = Session::get('cartActs', []);
        $cacts      = $S_cartActs[$tree->name()] ?? [];
        return count($cacts);
    }

    /**
     * @param Tree              $tree
     */
    private function count_CartTreeDataReport(Tree $tree) : string
    {
        $SinfoCstock = $this->count_CartTreeXrefs($tree);
        $SinfoAstock = $this->count_CartTreeCacts($tree);
        $Sinfo = [];
        $Sinfo[] = $SinfoCstock;
        $Sinfo[] = $SinfoAstock;
        $Sinfo[] = I18N::translate('Number of CartXrefs:');
        $Sinfo[] = I18N::translate('Number of CartActs:');
        $SinfoJson = json_encode($Sinfo);
        return $SinfoJson;
    }

}