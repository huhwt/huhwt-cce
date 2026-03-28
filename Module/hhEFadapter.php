<?php

/**
 * HuH Extensions for webtrees - clippings cart enhanced
 * 
 * Copyright (C) 2026 EW.Heinrich
 * 
 * Adapter to Extended Family
 */

declare(strict_types=1);

namespace HuHwt\WebtreesMods\ClippingsCartEnhanced\Module;

use Cissee\WebtreesExt\IndividualExt;
use Cissee\WebtreesExt\FamilyExt;
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

use Hartenthaler\Webtrees\Module\ExtendedFamily\ExtendedFamilyTabModule;

use function asset;
use function response;
use function route;
use function view;
use Throwable;

class hhEFadapter {

    public function __construct() {
    }

    public function make_efObject(Individual $individual): object
    {

        $controller = new ExtendedFamilyTabModule;

        $efObject   = $controller->getExtendedFamily($individual);

        return $efObject;

    }

    public function serializer($EFobject): array
    {
        function serialize_INDI($indi): array {
            $Indi_vals  = [];
            $Indi_vals['xref']      = $indi->xref();
            $fullName               = $indi->getAllNames();
            $Indi_vals['fullName']  = $fullName[0]['fullNN'];
            $Indi_vals['sex']       = $indi->sex();
            return $Indi_vals;
        }
        function serialize_prop($prop, $p_key): array {
            $prop_vals = [];
            if (gettype($prop) == 'object') {
                if ($prop) {
                    foreach ($prop as $pr_key => $pr_val) {
                        if (gettype($pr_val) == 'object') {
                            $prop_vals[$p_key][$pr_key] = serialize_prop($pr_val, $pr_key);
                        } elseif (gettype($pr_val) == 'array') {
                            if(count($pr_val) == 0) {
                                $prop_vals[$p_key][$pr_key] = [];
                            } else {
                                foreach ($pr_val as $pr_vaInd => $pr_vaVal) {
                                    if (gettype($pr_vaVal) == 'object') {
                                        $rec_type = '_NIX_';
                                        try {
                                            $rec_type = $pr_vaVal->tag();
                                        } catch (Throwable $th) {}
                                        if ($rec_type == 'INDI') {
                                            $prop_vals[$p_key][$pr_key][$pr_vaInd]['INDI'] = serialize_INDI($pr_vaVal);
                                        } elseif ($rec_type == 'FAM') {
                                            $prop_vals[$p_key][$pr_key][$pr_vaInd]['FAM'] = $pr_vaVal->xref();
                                        }
                                    }
                                }
                            }
                        } else {
                            $prop_vals[$p_key][$pr_key] = $pr_val;
                        }
                    }
                }
            } elseif (gettype($prop) == 'array') {
                if (count($prop) == 0) {
                    $prop_vals[] = [];
                } else {
                    foreach ($prop as $pV_vaInd => $pV_vaVal) {
                        if (gettype($pV_vaVal) == 'object') {
                            $prop_vals[$pV_vaInd] = serialize_prop($pV_vaVal, $pV_vaInd);
                        } else {
                            $prop_vals[$pV_vaInd] = $pV_vaVal;
                        }
                    }
                }
            } else {
                $prop_vals[] = $prop;
            }
            return $prop_vals;
        }
        $EFobj_ar       = [];
        $EFobj_ar['config']     = get_object_vars($EFobject->config);
        $EFobj_ar['proband']    = get_object_vars($EFobject->proband);
        $indi                   = $EFobject->proband->indi;
        $EFobj_ar['proband']['indi']    = serialize_INDI($indi);

        $filters        = [];
        foreach ($EFobject->filters as $filter => $f_vals) {
            $filters[$filter]['efp']  = [];
            $EFpart    = $f_vals->efp;
            foreach ($EFpart as $propName => $propValue) {
                if ($propName == 'summary') {
                    $filters[$filter]['efp'][$propName] = get_object_vars($propValue);
                } else {
                    $p_vals = [];
                    foreach($propValue as $pV_key => $pV_val) {
                        if (gettype($pV_val) == 'object') {
                            if ($pV_val) {
                                $p_vals[$pV_key] = get_object_vars($pV_val);
                            }
                        } elseif (gettype($pV_val) == 'array') {
                            if (count($pV_val) == 0) {
                                $p_vals[$pV_key] = [];
                            } else {
                                foreach ($pV_val as $pV_vaInd => $pV_vaVal) {
                                    if (gettype($pV_vaVal) == 'object') {
                                        $p_vals[$pV_key][$pV_vaInd] = serialize_prop($pV_vaVal, $pV_vaInd);
                                    } else {
                                        $p_vals[$pV_key][$pV_vaInd] = $pV_vaVal;
                                    }
                                }
                            }
                        } else {
                            $p_vals[$pV_key] = $pV_val;
                        }
                    }
                    $filters[$filter]['efp'][$propName] = $p_vals;
                }
            }
        }
        $EFobj_ar['filters']    = $filters;

        return  $EFobj_ar;

    }

}