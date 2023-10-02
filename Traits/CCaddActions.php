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

use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Location;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\Note;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Repository;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Source;
use Fisharebest\Webtrees\Submitter;

/**
 * Trait CCaddActions - bundling all add-Actions of origin ClippingsCart
 */
trait CCaddActions
{
    /**
     * Recursive function to traverse the tree and add the ancestors and their families
     *
     * @param Individual $individual
     * @param int $level
     *
     * @return void
     */
    public function addAncestorFamiliesToCart(Individual $individual, int $level = PHP_INT_MAX): void
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
     * Recursive function to traverse the tree and add the ancestors
     *
     * @param Individual $individual
     * @param int $level
     *
     * @return void
     */
    public function addAncestorsToCart(Individual $individual, int $level = PHP_INT_MAX): void
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
     * @param Family $family
     *
     * @return void
     */
    public function addFamilyAndChildrenToCart(Family $family): void
    {
        $this->addFamilyToCart($family);

        foreach ($family->children() as $child) {
            $this->addIndividualToCart($child);
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
    public function addFamilyAndDescendantsToCart(Family $family, int $level = PHP_INT_MAX): void
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
     * @param Family $family
     */
    public function addFamilyToCart(Family $family): void
    {
        $cart = Session::get('cart');
        $cart = is_array($cart) ? $cart : [];

        $tree = $family->tree()->name();
        $xref = $family->xref();

        if (($cart[$tree][$xref] ?? false) === false) {
            $cart[$tree][$xref] = true;

            Session::put('cart', $cart);

            foreach ($family->spouses() as $spouse) {
                $this->addIndividualToCart($spouse);
            }

            $this->addLocationLinksToCart($family);
            $this->addMediaLinksToCart($family);
            $this->addNoteLinksToCart($family);
            $this->addSourceLinksToCart($family);
            $this->addSubmitterLinksToCart($family);
        }
    }

    /**
     * @param Individual $individual
     */
    public function addIndividualToCart(Individual $individual): void
    {
        $cart = Session::get('cart');
        $cart = is_array($cart) ? $cart : [];

        $tree = $individual->tree()->name();
        $xref = $individual->xref();

        if (($cart[$tree][$xref] ?? false) === false) {
            $cart[$tree][$xref] = true;

            Session::put('cart', $cart);

            $this->addLocationLinksToCart($individual);
            $this->addMediaLinksToCart($individual);
            $this->addNoteLinksToCart($individual);
            $this->addSourceLinksToCart($individual);
        }
    }

    /**
     * @param Location $location
     */
    public function addLocationToCart(Location $location): void
    {
        $cart = Session::get('cart');
        $cart = is_array($cart) ? $cart : [];

        $tree = $location->tree()->name();
        $xref = $location->xref();

        if (($cart[$tree][$xref] ?? false) === false) {
            $cart[$tree][$xref] = true;

            Session::put('cart', $cart);

            $this->addLocationLinksToCart($location);
            $this->addMediaLinksToCart($location);
            $this->addNoteLinksToCart($location);
            $this->addSourceLinksToCart($location);
        }
    }

    /**
     * @param GedcomRecord $record
     */
    public function addLocationLinksToCart(GedcomRecord $record): void
    {
        preg_match_all('/\n\d _LOC @(' . Gedcom::REGEX_XREF . ')@/', $record->gedcom(), $matches);

        foreach ($matches[1] as $xref) {
            $location = Registry::locationFactory()->make($xref, $record->tree());

            if ($location instanceof Location && $location->canShow()) {
                $this->addLocationToCart($location);
            }
        }
    }

    /**
     * @param Media $media
     */
    public function addMediaToCart(Media $media): void
    {
        $cart = Session::get('cart');
        $cart = is_array($cart) ? $cart : [];

        $tree = $media->tree()->name();
        $xref = $media->xref();

        if (($cart[$tree][$xref] ?? false) === false) {
            $cart[$tree][$xref] = true;

            Session::put('cart', $cart);

            $this->addNoteLinksToCart($media);
        }
    }

    /**
     * @param GedcomRecord $record
     */
    public function addMediaLinksToCart(GedcomRecord $record): void
    {
        preg_match_all('/\n\d OBJE @(' . Gedcom::REGEX_XREF . ')@/', $record->gedcom(), $matches);

        foreach ($matches[1] as $xref) {
            $media = Registry::mediaFactory()->make($xref, $record->tree());

            if ($media instanceof Media && $media->canShow()) {
                $this->addMediaToCart($media);
            }
        }
    }

    /**
     * @param Note $note
     */
    public function addNoteToCart(Note $note): void
    {
        $cart = Session::get('cart');
        $cart = is_array($cart) ? $cart : [];

        $tree = $note->tree()->name();
        $xref = $note->xref();

        if (($cart[$tree][$xref] ?? false) === false) {
            $cart[$tree][$xref] = true;

            Session::put('cart', $cart);
        }
    }

    /**
     * @param GedcomRecord $record
     */
    public function addNoteLinksToCart(GedcomRecord $record): void
    {
        preg_match_all('/\n\d NOTE @(' . Gedcom::REGEX_XREF . ')@/', $record->gedcom(), $matches);

        foreach ($matches[1] as $xref) {
            $note = Registry::noteFactory()->make($xref, $record->tree());

            if ($note instanceof Note && $note->canShow()) {
                $this->addNoteToCart($note);
            }
        }
    }

    /**
     * @param Source $source
     */
    public function addSourceToCart(Source $source): void
    {
        $cart = Session::get('cart');
        $cart = is_array($cart) ? $cart : [];

        $tree = $source->tree()->name();
        $xref = $source->xref();

        if (($cart[$tree][$xref] ?? false) === false) {
            $cart[$tree][$xref] = true;

            Session::put('cart', $cart);

            $this->addNoteLinksToCart($source);
            $this->addRepositoryLinksToCart($source);
        }
    }

    /**
     * @param GedcomRecord $record
     */
    public function addSourceLinksToCart(GedcomRecord $record): void
    {
        preg_match_all('/\n\d SOUR @(' . Gedcom::REGEX_XREF . ')@/', $record->gedcom(), $matches);

        foreach ($matches[1] as $xref) {
            $source = Registry::sourceFactory()->make($xref, $record->tree());

            if ($source instanceof Source && $source->canShow()) {
                $this->addSourceToCart($source);
            }
        }
    }

    /**
     * @param Repository $repository
     */
    public function addRepositoryToCart(Repository $repository): void
    {
        $cart = Session::get('cart');
        $cart = is_array($cart) ? $cart : [];

        $tree = $repository->tree()->name();
        $xref = $repository->xref();

        if (($cart[$tree][$xref] ?? false) === false) {
            $cart[$tree][$xref] = true;

            Session::put('cart', $cart);

            $this->addNoteLinksToCart($repository);
        }
    }

    /**
     * @param GedcomRecord $record
     */
    public function addRepositoryLinksToCart(GedcomRecord $record): void
    {
        preg_match_all('/\n\d REPO @(' . Gedcom::REGEX_XREF . '@)/', $record->gedcom(), $matches);

        foreach ($matches[1] as $xref) {
            $repository = Registry::repositoryFactory()->make($xref, $record->tree());

            if ($repository instanceof Repository && $repository->canShow()) {
                $this->addRepositoryToCart($repository);
            }
        }
    }

    /**
     * @param Submitter $submitter
     */
    public function addSubmitterToCart(Submitter $submitter): void
    {
        $cart = Session::get('cart');
        $cart = is_array($cart) ? $cart : [];
        $tree = $submitter->tree()->name();
        $xref = $submitter->xref();

        if (($cart[$tree][$xref] ?? false) === false) {
            $cart[$tree][$xref] = true;

            Session::put('cart', $cart);

            $this->addNoteLinksToCart($submitter);
        }
    }

    /**
     * @param GedcomRecord $record
     */
    public function addSubmitterLinksToCart(GedcomRecord $record): void
    {
        preg_match_all('/\n\d SUBM @(' . Gedcom::REGEX_XREF . ')@/', $record->gedcom(), $matches);

        foreach ($matches[1] as $xref) {
            $submitter = Registry::submitterFactory()->make($xref, $record->tree());

            if ($submitter instanceof Submitter && $submitter->canShow()) {
                $this->addSubmitterToCart($submitter);
            }
        }
    }
}
