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
use Nyholm\Psr7\Uri;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;

use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Module\ModuleMenuTrait;
use Fisharebest\Webtrees\Module\ClippingsCartModule;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleMenuInterface;
use Fisharebest\Webtrees\View;
use SebastianBergmann\Type\VoidType;

use HuHwt\WebtreesMods\ClippingsCartEnhanced\ListProcessor;

/**
 * Trait CCElistModulesTrait - bundling mocking list modules
 */
trait CCElistModulesTrait
{

    /**
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
 
     public function getAddFamilyListModuleAction(ServerRequestInterface $request) : ResponseInterface
     {
        $retroute = $this->getAddFamilyListModule_Exec($request);
        return redirect($retroute);
     }

    /**
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
 
     public function getAddFamilyListModule_wpAction(ServerRequestInterface $request) : ResponseInterface
     {
        $retroute = $this->getAddFamilyListModule_Exec($request, true);
        return redirect($retroute);
     }

     /**
     *
     * @param ServerRequestInterface $request
     *
     * @return string
     */
 
     protected function getAddFamilyListModule_Exec(ServerRequestInterface $request, $bool_wp = false) : string
     {
        $tree = Validator::attributes($request)->tree();
        assert($tree instanceof Tree);

        // Get the params of origin request
        $params = $_GET['params'];

        // die möglichen Parameter - werden per Validator abgefragt -> ist substituiert mit $this->testParm
        // $params = [
        //     'alpha'               => $alpha,
        //     'falpha'              => $falpha,
        //     'show'                => $show,
        //     'show_all'            => $show_all,
        //     'show_all_firstnames' => $show_all_firstnames,
        //     'show_marnm'          => $show_marnm,
        //     'surname'             => $surname,
        // ];

        // All individuals with this surname
        $surname_param = $this->testParams($params, 'surname', '');
        $surname       = I18N::strtoupper(I18N::language()->normalize($surname_param));

        // All surnames beginning with this letter, where "@" is unknown and "," is none
        $alpha = $this->testParams($params, 'alpha', '');

        // All first names beginning with this letter where "@" is unknown
        $falpha = $this->testParams($params, 'falpha', '');

        // What type of list to display, if any
        $show = $this->testParams($params, 'show', 'surn');

        // All individuals
        $show_all = $this->testParams($params, 'show_all', '');

        // Include/exclude married names
        $show_marnm = $this->testParams($params, 'show_marnm', '');
        $show_marnm = $show_marnm === '1' ? 'yes' : '';

        // Break long lists down by given name
        $show_all_firstnames = $this->testParams($params, 'show_all_firstnames', '');

        $params_call = [
            'alpha'               => $alpha,
            'falpha'              => $falpha,
            'show'                => $show,
            'show_all'            => $show_all,
            'show_all_firstnames' => $show_all_firstnames,
            'show_marnm'          => $show_marnm,
            'surname'             => $surname,
        ];
        // we want to set only the parameters as given in original call
        $params_call = array_intersect_key($params_call, $params);
        $options = [
            self::ADD_RECORD_ONLY        => I18N::translate('individuals and families in list.'),
        ];

        $this->addFamilyList($tree, $surname, $alpha, $show_marnm, $show, $show_all, $bool_wp);

        $callingURI = self::ROUTES_WITH_RECORDS['FamilyListModule'];
        $trKey = $tree->name();
        $retRoute = route($callingURI, $params_call);
        $retRoute = str_replace('{tree}', $trKey, $retRoute);
        return $retRoute;
    }

    public function addFamilyList(Tree $tree, $surname, $alpha, $show_marnm, $show, $show_all, $bool_wp) : void
    {
        $all_surnames     = $this->CCEindiList->allSurnames($tree, $show_marnm === 'yes', true);

        $surns = $this->make_surns($alpha, $show, $show_all, $all_surnames, $surname);

        // $surname_initials = $this->surnameInitials($all_surnames);

        if (!$this->lPdone) {
            $this->instance = &$this;
            $listProcessor = new ListProcessor($this->instance);
            $this->listProcessor = $listProcessor;
            $this->lPdone = true;
        }
        $caKey = $bool_wp ? 'FAM-LISTwp' : 'FAM-LIST';
        $this->cartAct($tree, $caKey, $surname . ';' . $alpha . ';' . $show . ';' . $show_all . ';' . $show_marnm);
        $_dname = 'wtVIZ-DATA~FAM-LIST|' . $alpha;
        $this->putVIZdname($_dname);

        $this->listProcessor->clip_families($tree, $surname, $surns, $alpha, $show_marnm === 'yes', $bool_wp);
    }

    protected function make_surns($alpha, $show, $show_all, $all_surnames, $surname) : array
    {
        if ($show === 'indi' || $show === 'surn') {
            switch ($alpha) {
                case '@':
                    $surns = array_filter($all_surnames, static fn (string $x): bool => $x === Individual::NOMEN_NESCIO, ARRAY_FILTER_USE_KEY);
                    break;
                case ',':
                    $surns = array_filter($all_surnames, static fn (string $x): bool => $x === '', ARRAY_FILTER_USE_KEY);
                    break;
                case '':
                    if ($show_all === 'yes') {
                        $surns = array_filter($all_surnames, static fn (string $x): bool => $x !== '' && $x !== Individual::NOMEN_NESCIO, ARRAY_FILTER_USE_KEY);
                    } else {
                        $surns = array_filter($all_surnames, static fn (string $x): bool => $x === $surname, ARRAY_FILTER_USE_KEY);
                    }
                    break;
                default:
                    if ($surname === '') {
                        $surns = array_filter($all_surnames, static fn (string $x): bool => I18N::language()->initialLetter($x) === $alpha, ARRAY_FILTER_USE_KEY);
                    } else {
                        $surns = array_filter($all_surnames, static fn (string $x): bool => $x === $surname, ARRAY_FILTER_USE_KEY);
                    }
                    break;
            }
        }
        $surns         = array_keys($surns);
        // $surns         = array_merge(...$surns);

        return $surns;
    }

}
