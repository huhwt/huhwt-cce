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


/**
 * Trait CCEcartActions - bundling all actions regarding Session::cart
 */
trait CCEcartActions
{
    public string $cartAction;

    public array $cartXREFs;

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
        $isEmpty  = ($contents === []);

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
        $cartAct    = Session::get('cartActs', []);
        $cacts      = $cartAct[$tree->name()] ?? [];
        return $cacts;
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
            $cartActs = $this->cartAction;
            $cart[$_tree][$xref] = $cartActs;
            Session::put('cart', $cart);
            return true;
        } else {
            $cartActs = $cart[$_tree][$xref];
            if (!is_bool($cartActs)) {
                if (!str_contains($cartActs, $this->cartAction)) {
                    $cartActs = $cartActs . ';' . $this->cartAction;
                    $cart[$_tree][$xref] = $cartActs;
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
        $cart = Session::get('cart', []);
        unset($cart[$tree->name()][$xref]);
        Session::put('cart', $cart);
   }

   private function clean_Cart(Tree $tree) : void
   {
        $cart = Session::get('cart', []);
        $cart[$tree->name()] = [];
        Session::put('cart', $cart);

        if ($this->TSMok) {
            $tags = Session::get('tags', []);
            $tags[$tree->name()] = [];
            Session::put('tags', $tags);
        }

   }

   private function get_CartActs(Tree $tree) : array
   {
       $cartAct     = Session::get('cartActs', []);
       $cartacts    = array_keys($cartAct[$tree->name()] ?? []);
       $cartacts    = array_map('strval', $cartacts);           // PHP converts numeric keys to integers.
       return $cartacts;
   }

   private function clean_CartActs(Tree $tree) : void
    {
        $_tree      = $tree->name();

        $cartAct    = Session::get('cartActs', []);
        $cartAct[$_tree] = [];
        Session::put('cartActs', $cartAct);

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
        $cartAct = Session::get('cartActs', []);
        unset($cartAct[$tree->name()][$cact]);
        Session::put('cartActs', $cartAct);
        return $cartAct;
    }

    private function put_CartActs(Tree $tree, string $action, string $Key, string $altKey = '', bool $doDiversity = true) : string
    {
        $cartAct = Session::get('cartActs', []);
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

        if (($cartAct[$tree->name()][$caction] ?? false) === false) {
            $cartAct[$tree->name()][$caction] = true;
            Session::put('cartActs', $cartAct);
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
    private function count_CartTreeXrefs(Tree $tree) : int
    {
        $cart = Session::get('cart', []);
        $xrefs = $cart[$tree->name()] ?? [];
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
        $cartAct    = Session::get('cartActs', []);
        $cacts      = $cartAct[$tree->name()] ?? [];
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