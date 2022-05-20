<?php

declare(strict_types=1);

namespace HuHwt\WebtreesMods\ClippingsCartEnhanced;

use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Individual;

// array functions
use function in_array;

class PartnerChains
{
    /**
     * @var object $chainRootNode
     */
    private object $chainRootNode;

    /**
     * constructor for this class
     *
     * @param Individual $individual
     * @param Family $family
     */
    public function __construct(Individual $individual, Family $family)
    {
        $this->chainRootNode = (object)[];
        $this->chainRootNode->indi = $individual;
        $this->chainRootNode->fam = $family;

        $stop = (object)[];                                 // avoid endless loops
        $stop->indiList = [];
        $stop->indiList[] = $family->husband()->xref();
        $stop->familyList = [];

        $this->chainRootNode->chains = $this->getPartnerChainsRecursive($this->chainRootNode, $stop);
    }

    /**
     * return the partner chain root node
     *
     * @return object
     */
    public function getChainRootNode(): object
    {
        return $this->chainRootNode;
    }

    /**
     * get chains of partners recursive
     *
     * @param object $node
     * @param object $stop stoplist with arrays of indi-xref and fam-xref (modified by this function)
     * @return array
     */
    private function getPartnerChainsRecursive(object $node, object &$stop): array
    {
        $new_nodes = [];            // array of object ($node->indi; $node->chains)
        $i = 0;
        foreach ($node->indi->spouseFamilies() as $family) {
            if (!in_array($family->xref(), $stop->familyList)) {
                foreach ($family->spouses() as $spouse) {
                    if ($spouse->xref() !== $node->indi->xref()) {
                        if (!in_array($spouse->xref(), $stop->indiList)) {
                            $new_node = (object)[];
                            $new_node->chains = [];
                            $new_node->indi = $spouse;
                            $new_node->fam = $family;
                            $stop->indiList[] = $spouse->xref();
                            $stop->familyList[] = $family->xref();
                            $new_node->chains = $this->getPartnerChainsRecursive($new_node, $stop);
                            $new_nodes[$i] = $new_node;
                            $i++;
                        } else {
                            break;
                        }
                    }
                }
            }
        }
        return $new_nodes;
    }

    /**
     * count individuals in partner chains
     *
     * @param array of partner chain nodes
     * @return int
     */
    public function countPartnerChains(array $chains): int
    {
        $allcount = 0;
        $counter = 0;
        foreach ($chains as $chain) {
            $this->countPartnerChainsRecursive($chain, $counter);
            $allcount += $counter;
        }
        if ($allcount <= 2) {           // ignore chains with only one couple
            $allcount = 0;
        }
        return $allcount;
    }

    /**
     * count individuals in partner chains recursively
     *
     * @param object $node partner chain node
     * @param int $counter counter for sex of individuals (modified by function)
     */
    private function countPartnerChainsRecursive(object $node, int &$counter)
    {
        if ($node && $node->indi instanceof Individual) {
            $counter++;
            foreach ($node->chains as $chain) {
                $this->countPartnerChainsRecursive($chain, $counter);
            }
        }
    }
}
