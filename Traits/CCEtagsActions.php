<?php

/**
 * webtrees - clippings tags enhanced
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
 * Trait CCEtagsActions - bundling all actions regarding Session::tags
 */
trait CCEtagsActions
{
    public string $tagsAction;

    public array $tagsXREFs;

    /**
     * @param Tree $tree
     * @param string $xref
     * 
     * @return bool
     */

    public function put_Tags(Tree $tree, string $xref): bool
    {
        $ts_request = $_SERVER['REQUEST_TIME'];
        $new_action = '_Other_CC_' . date('Y-m-d_H-i-s', $ts_request);
        $new_actKey = 'SHOW~' . $new_action;

        $tags = Session::get('tags');
        $tags = is_array($tags) ? $tags : [];

        $_tree = $tree->name();

        if (($tags[$_tree][$xref] ?? '_NIX_') === '_NIX_') {
            $tagsActs = $this->tagsAction;
            $tags[$_tree][$xref] = $tagsActs;
            Session::put('tags', $tags);
            return true;
        } else {
            $tagsActs = $tags[$_tree][$xref];
            if (!is_bool($tagsActs)) {
                if (!str_contains($tagsActs, $this->tagsAction)) {
                    $tagsActs = $tagsActs . ';' . $this->tagsAction;
                    $tags[$_tree][$xref] = $tagsActs;
                    Session::put('tags', $tags);
                }
            } else {
                $tags[$_tree][$xref] = $new_actKey . ';' . $this->tagsAction;
                Session::put('tags', $tags);
                $this->put_TagsActs($tree, 'SHOW', $new_action);
            }
            return false;

        }
    }

    private function get_Tags() : array
    {
        // clippings tags is an array in the session
        $tags  = Session::get('tags', []);
        if ( !is_array($tags) ) {
            $tags = [];
            Session::put('tags', $tags);
        }
        return $tags;
   }

   private function get_TreeTags(Tree $tree) : array
   {
       // clippings tags is an array in the session specific for each tree
       $tags    = $this->get_Tags();
       if (!array_key_exists($tree->name(), $tags))
           $t_tags = [];
       else
           $t_tags = $tags[$tree->name()];
       return $t_tags;
   }

    private function remove_Tags(Tree $tree, $xref) : void
   {
        $tags = Session::get('tags', []);
        unset($tags[$tree->name()][$xref]);
        Session::put('tags', $tags);
   }

   private function clean_Tags(Tree $tree) : array
   {
       $tags = Session::get('tags', []);
       $tags[$tree->name()] = [];
       Session::put('tags', $tags);
       return $tags;
   }

   private function get_TagsActs(Tree $tree) : array
   {
       $tagsAct = Session::get('tagsActs', []);
       $tagsacts = array_keys($tagsAct[$tree->name()] ?? []);
       $tagsacts = array_map('strval', $tagsacts);           // PHP converts numeric keys to integers.
       return $tagsacts;
   }

//    private function clean_TagsActs(Tree $tree) : array
//     {
//         $tagsAct = Session::get('tagsActs', []);
//         $tagsAct[$tree->name()] = [];
//         Session::put('tagsActs', $tagsAct);

//         $tagsActsDiversity = Session::get('tagsActsDiversity', []);
//         $tagsActsDiversity[$tree->name()] = [];
//         $tagsActsDiversity[$tree->name()][0] = 0;
//         Session::put('tagsActsDiversity', $tagsActsDiversity);

//         return $tagsAct;
//     }

    // private function clean_TagsActs_cact(Tree $tree, string $cact) : array
    // {
    //     $tagsAct = Session::get('tagsActs', []);
    //     unset($tagsAct[$tree->name()][$cact]);
    //     Session::put('tagsActs', $tagsAct);
    //     return $tagsAct;
    // }

    private function put_TagsActs(Tree $tree, string $action, string $Key, string $altKey = '', bool $doDiversity = true) : string
    {
        $tagsAct = Session::get('tagsActs', []);
        $retval = $action;
        if ($altKey == '') {
            $caction = $action . '~' . $Key;
            $this->tagsAction = $caction;
        } else {
            if ($doDiversity) {
                [$retval, $caction] = $this->put_TagsActs_altkey($tree, $action, $Key, $altKey);
            } else {
                $caction = $action . '~' . $altKey;
                $this->tagsAction = $caction;
                $retval = $this->tagsAction;
                $caction = $caction . '|' . $Key;
            }
        }

        if (($tagsAct[$tree->name()][$caction] ?? false) === false) {
            $tagsAct[$tree->name()][$caction] = true;
            Session::put('tagsActs', $tagsAct);
        }
        return $retval;
    }
    private function put_TagsActs_altkey(Tree $tree, string $action, string $Key, string $altKey): array
    {
        $caction = $action . '~' . $altKey;                                         // this combination may not be significant ...

        $tagsActsDiversity = Session::get('tagsActsDiversity', []);                 // ... so we generate a identifying prefix ...
        if (($tagsActsDiversity[$tree->name()][0] ?? false) === false)
            $tagsActsDiversity[$tree->name()] [0] = 0;                              // ... quite simply - a serial number ...
        $cALc = $tagsActsDiversity[$tree->name()] [0];
        $cALc += 1;                                                                 // ... each entry gets a consecutive number ...
        $caction = '('.(string) $cALc .')'. $caction;
        $tagsActsDiversity[$tree->name()][0] = $cALc;                               // ... that is stored ...
        $tagsActsDiversity[$tree->name()][$caction] = true;                         // ... and the entry is filed
        Session::put('tagsActsDiversity', $tagsActsDiversity);

        $this->tagsAction = $caction;
        $retval = $this->tagsAction;
        $caction = $caction . '|' . $Key;
        return [$retval, $caction];
    }

    /**
     * @param Tree              $tree
     */
    private function count_TagsRecords(Tree $tree) : int
    {
        $tags = Session::get('tags', []);
        $xrefs = $tags[$tree->name()] ?? [];
        return count($xrefs);
    }

    /**
     * @param Tree              $tree
     * @param int               $xrefsCold
     */
    private function count_TagsRecordsStruct(Tree $tree, int $xrefsCold) : string
    {
        $xrefsCstock = $this->count_TagsRecords($tree);                // Count of xrefs actual in stock - updated
        $xrefsCadded = $xrefsCstock - $xrefsCold;
        $xrefsC = [];
        $xrefsC[] = $xrefsCstock;
        $xrefsC[] = $xrefsCadded;
        $xrefsC[] = I18N::translate('Total number of entries: %s', (string) $xrefsCstock);
        $xrefsC[] = I18N::translate('of which new entries: %s', (string) $xrefsCadded);
        $xrefsCjson = json_encode($xrefsC);
        return $xrefsCjson;
    }
}