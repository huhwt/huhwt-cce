<?php

/**
 * HuH Extensions for webtrees - Treeview-Extended
 * Interactive Treeview with add-ons
 * Copyright (C) 2025 EW.Heinrich
 * 
 * Adapter to Vesta Extended Relationships
 */

declare(strict_types=1);

namespace HuHwt\WebtreesMods\ClippingsCartEnhanced\Module;

use Cissee\Webtrees\Module\ExtendedRelationships\ExtendedRelationshipController;
use Cissee\WebtreesExt\IndividualExt;
use Cissee\WebtreesExt\FamilyExt;
use Cissee\WebtreesExt\Functions\FunctionsPrintExtHelpLink;
use Cissee\WebtreesExt\Modules\RelationshipPath;
use Cissee\WebtreesExt\Modules\RelationshipUtils;
use Cissee\WebtreesExt\MoreI18N;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Module\ModuleInterface;
use Fisharebest\Webtrees\Module\RelationshipsChartModule;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\RelationshipService;
use Fisharebest\Webtrees\Tree;
use Psr\Http\Message\ResponseInterface;
use function asset;
use function response;
use function route;
use function view;

class VestaERadapter {

    private const TYPES_OF_RECORDS = [
        'Individual' => Individual::class,
        'Family'     => Family::class,
    ];

    private array $vERdata = [];

    private array $vER_struct = [];

    private Tree $tree;

    public function __construct() {
    }

    /**
     * derived from     Cissee\Webtrees\Module\ExtendedRelationships\ExtendedRelationshipsChartController->chart()
     * 
     * perform vesta's optimized dijkstra-algorithmus to find the relations
     * transform the vesta results for CCE - step 1
     * 
     * @param \Cissee\WebtreesExt\IndividualExt $individual1
     * @param \Cissee\WebtreesExt\IndividualExt $individual2
     * @param int       $find   -> 3: only relatives        -> 5: any relation
     * 
     * @return array
     */
    public function make_path( Individual $individual1, Individual $individual2, int $find) : array
    {

        $tree       = $individual1->tree();
        $this->tree = $tree;

        $showCa     = true;     // boolval($this->module->getPreference('CHART_SHOW_CAS', '1'));
        $beforeJD   = null;

        // $max_recursion  = (int) $tree->getPreference('RELATIONSHIP_RECURSION', RelationshipsChartModule::DEFAULT_RECURSION);

        $recursion = 99;    // min($recursion, $max_recursion);

        $controller = new ExtendedRelationshipController;
        $caCAP_ret  = $controller->calculateCaAndPaths_123456($individual1, $individual2, $find, $recursion, $beforeJD);

        $vERdata = [];
        // there may be no result from the controller - either the restriction ($find) was to hard or there is no relationship indeed
        if ($caCAP_ret) {
            // we want only the data from the first item
            $caCAP_Obj  = $caCAP_ret[0];

            $ca         = $caCAP_Obj->getCommonAncestor();  $F_ca = null;
            // we have a c-ommon a-ncestor defined in the data
            if ($ca) {
                $ca_rec = Registry::gedcomRecordFactory()->make($ca, $tree);
                if ($ca_rec instanceof Individual) {
                    $ca_indi = new Individual($ca, $ca_rec->gedcom(),'',$tree);
                    $ca_ispF = $ca_indi->spouseFamilies()[0];
                } else { $ca_ispF = $ca_rec; }
                if ($ca_ispF) {
                    $ca_fam = $ca_ispF;
                }
                // $ca_fam = Registry::gedcomRecordFactory()->make($ca, $tree);
                $F_ca   = new FamilyExt($ca_fam->xref(), $ca_fam->gedcom(), '',  $tree);
            }

            $_caPATH    = $caCAP_Obj->getPath();            // the path ...
            $ca_size    = $caCAP_Obj->getSize();            // ... and it's size
            // we get the records of the entities in the path ...
            $records = array_map(static function (string $xref) use ($tree): ?GedcomRecord {
                return Registry::gedcomRecordFactory()->make($xref, $tree);
            }, $_caPATH);
            $records = array_filter($records);
            $recordKeyTypes = [];                        // type => keys
            // ... and store them explicitely corresponding to the type of records - we take only Individual and Family
            foreach (self::TYPES_OF_RECORDS as $key => $class) {
                $recordKeyTypeXrefs = [];
                foreach ($records as $record) {
                    if ($record instanceof $class) {
                        $recordKeyTypeXrefs[] = $record;
                    }
                }
                if (count($recordKeyTypeXrefs) > 0) {
                    $recordKeyTypes[strval($key) ] = $recordKeyTypeXrefs;
                }
            }
            // we want the XREFs from start- and end-person
            $ca_from    = $records[0];
            $ca_to      = $records[$ca_size-1];
            // if defined, we store the informations regarding the c-ommon a-ncestor (if not, we optionally have to define them later)
            $ca_struct  = [];
            if ($ca) {
                if ($F_ca) {
                    $recordKeyTypes['Individual'][] = $F_ca->husband();
                    $recordKeyTypes['Individual'][] = $F_ca->wife();
                    $recordKeyTypes['Family'][] = $F_ca;
                    $ca_struct['fam'] = $F_ca;
                    $ca_struct['husb'] = $F_ca->husband();
                    $ca_struct['wife'] = $F_ca->wife();
                }
            }
            // we want the partners from I_to and I_from
            $partners   = $individual1->spouseFamilies();
            if ($partners) {
                foreach ($partners as $Tfamily) {
                    $recordKeyTypes['Family'][] = $Tfamily;
                }
            }
            $partners   = $individual2->spouseFamilies();
            if ($partners) {
                foreach ($partners as $Tfamily) {
                    $recordKeyTypes['Family'][] = $Tfamily;
                }
            }
            // store the structure
            $vERdata['ca'] = $caCAP_Obj->getCommonAncestor();
            $vERdata['path'] = $caCAP_Obj->getPath();
            $vERdata['size'] = $ca_size;
            $vERdata['shortestLeg'] = $caCAP_Obj->getShortestLeg();
            $vERdata['I_from'] = $ca_from;
            $vERdata['I_to'] = $ca_to;
            $vERdata['ca_struct'] = $ca_struct;
            $vERdata['INDIs'] = $recordKeyTypes['Individual'];
            $vERdata['FAMs'] = $recordKeyTypes['Family'];

            $vER_struct = vERstruct_make( $caCAP_ret, $tree);
            $vERdata['vERstruct'] = $vER_struct;
        }

        $this->vERdata = $vERdata;

        return $vERdata;
    }

    public function update_vERstruct(Tree $tree) : array 
    {
        $this->vER_struct = vERstruct_process($tree, $this->vERdata);
        return $this->vER_struct;
    }
}

/**
 * analyze the results from module vesta-extended-relationships
 * transform the vesta results for CCE - step 2:
 * 
 *      split the path in up- and down-parts
 * 
 * @param array     $caCAP_ret
 * @param Tree      $tree
 * 
 * @return array    $vER_struct
 */
function vERstruct_make( array $caCAP_ret, Tree $tree) : array
{
    $ER_data    = $caCAP_ret[0];
    $ER_path_ar = $ER_data->getPath();
    $vER_struct = [];   $ER_p = 0;     $ER_sdir = '';  $ER_sdir_o = '';
    $xrefI      = '';   $xrefF = '';   $ERline = '';   $ERfams = '';   $ERfamc = '';

    // the path starts with an Individual-xref and ends with an Individual-xref
    // alternating with Family-xref's
    // Family-xref points to a childfamily: store it as FAMc    - to a spousefamily: store it as FAMs
    // if it is a FAMc -> direction 'up'            if it is a FAMs: -> direction 'down'
    //       next Individual is ancestor                  next Individual is descendant
    foreach ($ER_path_ar as $n => $xref) {
        if ($n % 2 === 0) {
            $xrefI = $xref;
            $record = Registry::gedcomRecordFactory()->make($xref, $tree);
            $ERindi = new Individual($xref, $record->gedcom(),'', $tree);
            $ERfams = '___';            $ERfamc = '___';
            $ERspf  = $ERindi->spouseFamilies();
            if ($ERspf && count($ERspf) > 0)
                $ERfams = $ERspf->first()->xref() ?? '___';
            $ERchf  = $ERindi->childFamilies();
            if ($ERchf && count($ERchf) > 0)
                $ERfamc = $ERchf->first()->xref() ?? '___';
        } else {
            $xrefF = $xref;
            if ($xrefF == $ERfamc) { $ERfams = '___';} else { $ERfamc = '___';}
            if ($ERfams != '___') {
                if ($ERfams != $xrefF)       {   $ERfams = $xrefF; }          // there are more than 1 spouseFamilies! vER gives the right one!
            }
            $ERline = 'INDI:' . $xrefI . ' - FAMs:' .  $ERfams . ' - FAMc:' . $ERfamc;
            if ($ER_sdir == '') {
                $ER_sdir = $ERfams == '___' ? 'up' : 'down';    $ER_sdir_o = $ER_sdir;
                $vER_struct[$ER_p][0] = $ER_sdir;
            }
            $ER_sdir = $ERfams == '___' ? 'up' : 'down';
            if ($ER_sdir != $ER_sdir_o)     {   $ER_p++;        $vER_struct[$ER_p][0] = $ER_sdir;    $ER_sdir_o = $ER_sdir; }
            $vER_struct[$ER_p][] = $ERline;      $ERline = '';   $xrefI = '';
        }
    }
    if ($ERline > '')                   {   $vER_struct[$ER_p][] = $ERline;      $xrefI = ''; }
    if ($xrefI > '') {
        if ($xrefF == $ERfamc) { $ERfams = '___';} else { $ERfamc = '___';}
        if ($ERfams != '___') {
            if ($ERfams != $xrefF)       {   $ERfams = $xrefF; }          // there are more than 1 spouseFamilies! vER gives the right one!
        }
        $ERline = 'INDI:' . $xrefI . ' - FAMs:' .  $ERfams . ' - FAMc:' . $ERfamc;
        $vER_struct[$ER_p][] = $ERline;
    }

    return $vER_struct;
}

/**
 * analyze the results from module vesta-extended-relationships
 * transform the vesta results for CCE - step 3:
 * 
 *      transform the raw vER_struct to be consumable for CCE
 * 
 * @param Tree      $tree
 * @param array     $erPrefix
 * @param array     $vERdata
 * 
 * @return array
 */
function vERstruct_process(Tree $tree, array $vERdata): array
{
    $recordKeyTypes                 = [];                        // type => keys
    $recordKeyTypes['Individual']   = $vERdata['INDIs'];
    $recordKeyTypes['Family']       = $vERdata['FAMs'];

    $marriage_symbol        = json_decode('"\u26AD"');       // ⚭

    $INDImap = [];
    foreach ($recordKeyTypes['Individual'] as $indi) {
        $i_xref = $indi->xref();
        $INDImap[$i_xref]   = $indi;
    }
    $FAMmap = [];
    foreach ($recordKeyTypes['Family'] as $fam) {
        $f_xref = $fam->xref();
        $FAMmap[$f_xref]    = $fam;
    }

    $_ER_struct = $vERdata['vERstruct'];
    $ER_parts = [];
    $vER_struct  = [];
    foreach ($_ER_struct as $n_p => $part) {
        $ER_part    = [];
        $direction  = $part[0];
        $ER_part[0] = $direction;

        $INDIs = array_slice($part, 1, count($part)-1);
        // it's the DOWN part of path: 1. item is the oldest person ... we have to revert the sequence
        // if ($direction == 'down') { 
        //     $INDIs = array_reverse($INDIs);
        // }

        foreach ($INDIs as $n => $Iref) {
            // if ($n == 0) { continue; }

            $Irefs = explode(' - ', $Iref);
            $Fams = '';     $Famc = '';
            $xrefI  = str_replace('INDI:', '', $Irefs[0]);
            $record = Registry::gedcomRecordFactory()->make($xrefI, $tree);
            $ERindi = new Individual($xrefI, $record->gedcom(),'', $tree);
            $xrefFs = str_replace('FAMs:', '', $Irefs[1]);
            $xrefFc = str_replace('FAMc:', '', $Irefs[2]);
            if ($xrefFc == '___') {
                $ERchf  = $ERindi->childFamilies();
                if ($ERchf && count($ERchf) > 0) {
                    $xrefFc = $ERchf->first()->xref() ?? '___';
                    if ($xrefFc != '___') {
                        if (!array_key_exists($xrefFc, $FAMmap)) {
                            $record = Registry::gedcomRecordFactory()->make($xrefFc, $tree);
                            $FAMmap[$xrefFc] = $record;
                            $vERdata['FAMs'][] = $record;
                        }
                    }
                }
            }
            if ($xrefFs == '___') {
                $ERspf  = $ERindi->spouseFamilies();
                if ($ERspf && count($ERspf) > 0) {
                    $xrefFs = $ERspf->first()->xref() ?? '___';
                    if ($xrefFs != '___') {
                        if (!array_key_exists($xrefFs, $FAMmap)) {
                            $record = Registry::gedcomRecordFactory()->make($xrefFc, $tree);
                            $FAMmap[$xrefFs] = $record;
                            $vERdata['FAMs'][] = $record;
                        }
                    }
                }
            }
            $ERline = 'INDI:' . $xrefI . ' - FAMs:' .  $xrefFs . ' - FAMc:' . $xrefFc;
            $ER_part[] = $ERline;
        }
        $ER_parts[$n_p] = $ER_part;
    }

    $vER_struct['INDIs']   = $vERdata['INDIs'];
    $vER_struct['FAMs']    = $vERdata['FAMs'];

    // $i_erP = 0;
    // $i_erC = 0;
    // $cnt_check = count($ER_parts);
    // while ($i_erC < $cnt_check) {
    //     $chart  = $erPrefix[$i_erP];
    //     $label  = $erPrefLabel[$i_erP];
    //     // we have a couple of paths, may be we can put some of them together in 1 chart
    //     if ($cnt_check-1 > $i_erC) {
    //         $i_erCn = $i_erC + 1;
    //         // we have 1 path from an individual up to an ancestor ... 
    //         // ... directly followed up by a path down to an other individual which is an descendant of this ancestor too ...
    //         // ... so we can merge this paths in 1 chart -> combine
    //         if (($ER_parts[$i_erC][0] == 'up') && ($ER_parts[$i_erCn][0] =='down')) {
    //             $ER_struct_px = vERstruct_process_combine($tree, $erPrefix, $ER_parts, $i_erC,  $i_erCn, $vERdata);
    //             $i_erC+=2;
    //         } else {
    //         // we have a path from an individual up to an ancestor ... or down to a descendant -> we have to execute a single chart -> extract
    //             $ER_struct_px = vERstruct_process_extract($tree, $erPrefix, $ER_parts, $i_erC);
    //             $i_erC++;
    //         }
    //         $i_erP++;
    //     } else {
    //     // we have only 1 path from an individual up to an ancestor or down to a descendant -> we have to execute a single chart -> extract
    //         $ER_struct_px = vERstruct_process_extract($tree, $erPrefix, $ER_parts, $i_erC);
    //         $i_erC++;
    //         $i_erP++;
    //     }
    //     // store the new values in the struct
    //     foreach ($ER_struct_px[0] as $key => $val) {
    //         $ER_parts[$chart][$key] = $val;
    //     }
    // }
    // // we have extended the struct - we want only the extended values - so we have to clean up the struct
    // for($i_erC=0; $i_erC<$cnt_check; $i_erC++) {
    //     unset($ER_parts[$i_erC]);
    // }
    // $cnt_check = count($ER_parts);
    // if ($cnt_check > 1) {
    //     for ($i_erP = 0; $i_erP < $cnt_check; $i_erP++) {
    //         $chart      = $erPrefix[$i_erP];
    //         $label      = $erPrefLabel[$i_erP];
    //         $part       = $ER_parts[$chart];
    //         $r_Ifrom    = $part['I_from'];
    //         $d_Ifrom    = $r_Ifrom->fullName() . ', ' . $r_Ifrom->lifespan();
    //         $r_Ito      = $part['I_to'];
    //         $d_Ito      = $r_Ito->fullName() . ', ' . $r_Ito->lifespan();
    //         $d_Ifrom_to = $d_Ifrom . ' -> ' . $d_Ito;
    //         if ( $i_erP < $cnt_check-1) {
    //             $cnt_p      = count($part['path'])-1;
    //             $p_Ito      = $part['path'][$cnt_p];
    //             $p_Ito_vals = explode(' - ', $p_Ito);
    //             $p_Ito_FAMs = str_replace('FAMs:', '', $p_Ito_vals[1]);
    //             $F_Ito_0    = Registry::gedcomRecordFactory()->make($p_Ito_FAMs, $tree);
    //             $F_Ito      = new FamilyExt($F_Ito_0->xref(), $F_Ito_0->gedcom(), '',  $tree);
    //             $F_Ito_s    = $F_Ito->spouse($r_Ito);
    //             $d_Ifrom_to .= ' ' . $marriage_symbol . ' ' . $F_Ito_s->fullName() . ', ' . $F_Ito_s->lifespan();
    //         }
    //         $ER_parts[$chart]['label'] = $label . ': ' . $d_Ifrom_to;
    //     }
    // }
    $vER_struct['parts'] = $ER_parts;
    return $vER_struct;
}

function vERstruct_process_combine(Tree $tree, array $erPrefix, array $vER_struct, int $ERind_p1, int $ERind_p2, array $vERdata) : array
{
    $px     = [];
    $path_1 = $vER_struct[$ERind_p1];
    $path_2 = $vER_struct[$ERind_p2];
    $last_1 = explode(' - ', $path_1[count($path_1)-1])[2];
    $last_2 = explode(' - ', $path_2[count($path_2)-1])[2];
    $dir_1 = $path_1[0];            $dir_2 = $path_2[0];
    if ($dir_1 == 'up' && $dir_2 == 'down' && $last_1 == $last_2) {
        $path_2 = array_slice($path_2, 1);      // cut off 1. item ('direction')
        $path_2 = array_reverse($path_2);               // inverse the sequence from youngest to oldest 
        $path_12 = array_merge($path_1, $path_2);      // merge the pathes
        $path_12[0] = 'combined';                              // set the new mark ('direction')
        $len_path1 = count($path_1) - 1;                // get the length (-1 because of 'direction')
        $len_path2 = count($path_2);                    // ... dto.
        $path_2 = null;

        $px['path']         = $path_12;
        $px['shortestLeg']  = max($len_path1, $len_path2);  // set the longer one
        $px['size']         = $len_path1 + $len_path2;

        $ca = null;
        if (array_key_exists('ca', $vERdata)) { $ca = $vERdata['ca']; }

        if (!$ca) {
            $ca     = str_replace('FAMc:', '', $last_1);
            $ca_fam = Registry::gedcomRecordFactory()->make($ca, $tree);
            $F_ca   = new FamilyExt($ca_fam->xref(), $ca_fam->gedcom(), '',  $tree);
            if ($F_ca) {
                $ca_struct  = [];
                $vER_struct['INDIs'][] = $F_ca->husband();
                $vER_struct['INDIs'][] = $F_ca->wife();
                $vER_struct['FAMs'][]  = $F_ca;
                $ca_struct['fam']   = $F_ca;
                $ca_struct['husb']  = $F_ca->husband();
                $ca_struct['wife']  = $F_ca->wife();
                $px['ca']           = $F_ca->husband();
                $px['ca_struct']    = $ca_struct;
            }
        } else {
            $px['ca']           = $vERdata['ca'];
            $px['ca_struct']    = $vERdata['ca_struct'];
        }
        $p_last  = explode(' - ', $path_12[count($path_12)-1])[0];
        $p_first = explode(' - ', $path_12[1])[0];
        $I_from  = str_replace('INDI:', '', $p_first);
        $I_to    = str_replace('INDI:', '', $p_last);
 
        $_INDI = Registry::gedcomRecordFactory()->make($I_from, $tree);
        $px['I_from']       = $_INDI;
        $_INDI = Registry::gedcomRecordFactory()->make($I_to, $tree);
        $px['I_to']         = $_INDI;
        return [$px];
    }
    return [];
}

function vERstruct_process_extract(Tree $tree, array $erPrefix, array $vER_struct, int $ERind_p) : array
{
    $px     = [];
    $path_p = $vER_struct[$ERind_p];
    $dir_p = $path_p[0];    
    if ($dir_p == 'down') {
        $path_p = array_slice($path_p, 1);     // cut off 1. item ('direction')
        $path_p = array_reverse($path_p);              // inverse the sequence from oldest to youngest
        $path_2[0] = $dir_p;                                  // restore 'direction'
        $path_2 = array_merge($path_2, $path_p);      // merge the pathes
        $len_path2 = count($path_2) - 1;               // get the length

        $px['path']         = $path_2;
        $px['shortestLeg']  = $len_path2;
        $px['size']         = $len_path2;

        $p_last  = explode(' - ', $path_2[count($path_2)-1])[0];
        $p_first = explode(' - ', $path_2[1])[0];
        $I_from  = str_replace('INDI:', '', $p_first);
        $I_to    = str_replace('INDI:', '', $p_last);
 
        $_INDI = Registry::gedcomRecordFactory()->make($I_from, $tree);
        $px['I_from']       = $_INDI;
        $_INDI = Registry::gedcomRecordFactory()->make($I_to, $tree);
        $px['I_to']         = $_INDI;
        return [$px];
    } else if ($dir_p == 'up') {
        $path_2    = $path_p;
        $len_path2 = count($path_2) - 1;               // get the length

        $px['path']         = $path_2;
        $px['shortestLeg']  = $len_path2;
        $px['size']         = $len_path2;

        $p_last_vals        = explode(' - ', $path_2[count($path_2)-1]);
        $p_last             = $p_last_vals[0];
        $p_first_vals       = explode(' - ', $path_2[1]);
        $p_first            = $p_first_vals[0];
        $I_from             = str_replace('INDI:', '', $p_first);
        $I_to               = str_replace('INDI:', '', $p_last);
 
        $_INDI = Registry::gedcomRecordFactory()->make($I_from, $tree);
        $px['I_from']       = $_INDI;
        $_INDI = Registry::gedcomRecordFactory()->make($I_to, $tree);
        $px['I_to']         = $_INDI;
        // check: if I_to and its predecessor are siblings -> we have to get the ancestor
        $p_last_vals2       = $p_last_vals[2];
        $p_last_famc        = str_replace('FAMc:', '', $p_last_vals2);
        $p_plast_vals       = explode(' - ', $path_2[count($path_2)-2]);
        $p_plast_vals2      = $p_plast_vals[2];
        $p_plast_famc       = str_replace('FAMc:', '', $p_plast_vals2);
        if ($p_last_famc == $p_plast_famc) {
            $ca_fam = Registry::gedcomRecordFactory()->make($p_last_famc, $tree);
            $F_ca   = new FamilyExt($ca_fam->xref(), $ca_fam->gedcom(), '',  $tree);
            if ($F_ca) {
                $ca_struct  = [];
                $vER_struct['INDIs'][]   = $F_ca->husband();
                $vER_struct['INDIs'][]   = $F_ca->wife();
                $vER_struct['FAMs'][]    = $F_ca;
                $ca_struct['fam']       = $F_ca;
                $ca_struct['husb']      = $F_ca->husband();
                $ca_struct['wife']      = $F_ca->wife();
                $px['ca']               = $F_ca->husband();
                $px['ca_struct']        = $ca_struct;
                $px['path'][0]          = 'combined';                              // set the new mark ('direction')
            }
         }
        return [$px];
    }
    return [];
}
