
# webtrees module hh_clippings_cart_enhanced

This [webtrees](https://www.webtrees.net/) custom module replaces the original 'Clippings Cart' module.
It offers additional possibilities to add records to the clippings cart
and adds beside the possibility to export a GEDCOM zip-file the possibility
to visualize the records in the clippings cart using a diagram.

## Contents
This Readme contains the following main sections

* [Description](#description)
* [Screenshots](#screenshots)
* [Thank you](#thanks)

<a name="description"></a>
## Description

This custom module replaces the original 'Clippings Cart' module.
The design concept for the enhanced clippings cart is shown in the following diagram:
<p align="center"><img src="_ASSETTS/ClippingsCartEnhanced.png" alt="new concept for enhanced clippings cart" align="center" width="100%"></p>

Various functions can collect records in the clippings cart and these records could then be passed on to various evaluation / export / visualization functions.
The user of the module can decide which records should be sent to the clippings cart
and which action should be executed on these records.

As input functions there are
* the functions of the current module "clippings cart" or the existing custom [Vesta clippings cart](https://github.com/vesta-webtrees-2-custom-modules/vesta_clippings_cart) module, which offer the possibility to put records in the clippings cart for a person or another GEDCOM object like a source or a note.
This includes for a person for example their ancestors or descendants,
possibly with their families. For a location it includes for example all persons with reference to this place.
* the search in the control panel for unconnected persons in a tree (with a new button "send to clippings cart") (tbd)
* the normal webtrees search (also with a button "send to clippings cart"), so that you can search for anything you want and with the option of using all the filter functions that are currently offered (tbd)
* the list display modules "Families" and "Family branches", so that you can send all persons with the same family name or all persons from a clan to the clippings cart, this can be optionally done with including they parent families. 
** Feature is realized for module "List/Families".
* new functions in this module searching for marriage chains or ancestor circles in the tree.

The user can then delete selected records or groups of records of the same type
in the clippings cart or delete the clippings cart completely.

An action initiated by the user then takes place on the records in the clippings cart, such as
* the export to a GEDCOM zip file, as in the actual clippings cart module
* the display of the objects in list form with the possibility of sorting and filtering this list (tbd)
* the transfer of the records in the clippings cart to new functions that visualize this data or analyze it statistically.
Such a function could be for example a link-node-diagram like
[TAM](https://github.com/rpreiner/tam) (Topographic Attribute Map) or [Lineage](https://github.com/huhwt/lineage).

<a name="screenshots"></a>
## Screenshots

### Screenshots of menus
#### Screenshot of the new menu
<p align="center"><img src="_ASSETTS/Screenshot_Menu.png" alt="Screenshot of main menu" align="center" width="80%"></p>

#### Screenshot of menu to add global sets of records for a tree
<p align="center"><img src="_ASSETTS/Screenshot_ViewAddGlobal.png" alt="Screenshot of menu to add global sets of records" align="center" width="80%"></p>

#### Screenshot of new menu to delete records in the clippings cart
<p align="center"><img src="_ASSETTS/Screenshot_ViewEmpty.png" alt="Screenshot of menu to delete records" align="center" width="80%"></p>

#### Screenshot of new menu to execute actions on the records in the clippings cart
<p align="center"><img src="_ASSETTS/Screenshot_ExecuteActions.png" alt="Screenshot of menu to execute actions" align="center" width="80%"></p>

### Screenshots of new visualisation possibilities
The following charts are produced by an external application TAM.
It is planned to integrate TAM into webtrees
so that export/import is not any longer necessary.

#### Screenshot using TAM for a tree with more than 10.000 persons
<p align="center"><img src="_ASSETTS/Screenshot_Tree.png" alt="Screenshot of large tree" align="center" width="80%"></p>
This image was produced by exporting a complete tree from webtrees as GEDCOM file
using the administrator menu. Then this file was imported to TAM.
It is planned to add a new option "add all records to the clippings cart"
to the "global add menu" of this module.

#### Screenshot using TAM for a visualisation of all ancestor circles in this tree
<p align="center"><img src="_ASSETTS/Screenshot_Circles.png" alt="Screenshot of circles" align="center" width="80%"></p>
<p align="center"><img src="_ASSETTS/Screenshot_Circles_Background.png" alt="Screenshot of circles" align="center" width="80%"></p>
This chart was produced using the new "global add menu"
selecting "add all circles".
This function removes all leaves in a tree recursively.
A family with two parents and her child is a trivial circle
(child -> mother -> husband -> child) and is therefore not included.
Circles are a result for example when cousins are married together.
Such circles are responsible for pedigree collapse
(in German: Ahnenschwund or Implex). 
Following the connections of marriage or partnership, circles can be found 
if two families are interconnected by more than one marriage.
Maybe such circles can have a length of 30, 40, or 50 steps.
They can connect several families together and it is not easy to find them.
Such long distant connections in a tree are interconnecting different parts
of a family.
I call them therefore sometimes "ancestor Autobahn".
The first Autobahn I built in my tree, many years ago,
was connection to Johann Wolfgang von Goethe in more than 50 steps.

For example my parents are connected together by several such circles
(beside the trivial connection by their own marriage).
In webtrees you can search for such connections by using
Charts / Relationships / Find all possible relationships.
But up to now there was no possibility to show all such circles in a tree.

### Screenshot using TAM to show a H diagram
<p align="center"><img src="_ASSETTS/Screenshot_H_diagram.png" alt="Screenshot of H diagram" align="center" width="80%"></p>
A H diagramm is a very compact ancestor diagram showing the proband and
a few generations of his ancestors.
One example is the webtrees chart "compact tree".
To produce such a diagram using TAM, you have to select the proband,
add 3, 4, or 5 generations of his ancestors to the clippings cart. Then use the action menu
and export these records using the TAM option.
Then import the produced GEDCOM file to your TAM application.
Now you have to place the persons and families
manually to their right position by drag and drop.

#### Screenshot using TAM for a partner chain with 30 partners of partners of partners ...
<p align="center"><img src="_ASSETTS/Screenshot_PartnerChains.png" alt="Screenshot of partner chains" align="center" width="80%"></p>
<p align="center"><img src="_ASSETTS/Screenshot_PartnerChains_Background.png" alt="Screenshot of partner chains" align="center" width="80%"></p>
A partner chain is a chain of partners of partners of partners of ...
If one partner of marriage was married before or later to another partner,
this is a chain of three partners.
Trivial chains consisting only of two partners (husband/wife) are ignored.
Maybe one of the partners was married three or more times, then the chain becomes
a tree of chains.
There was up to now no possibility in webtrees to visualize such
trees of partner chains.

<a name="thanks"></a>
## Thank you

Special thanks to [huhwt](https://github.com/huhwt/) for testing, suggestions, and contributions.

This module is derived from the [Vesta clippings cart](https://github.com/vesta-webtrees-2-custom-modules/vesta_clippings_cart) module.

* Copyright (C) 2021 Hermann Hartenthaler
* Copyright (C) 2021 Richard Cissée. All rights reserved.
* Derived from **webtrees** - Copyright 2021 webtrees development team.

* * *