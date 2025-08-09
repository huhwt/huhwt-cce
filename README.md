
# webtrees module huhwt-clippings_cart_enhanced

[![License: GPL v3](https://img.shields.io/badge/License-GPL%20v3-blue.svg)](http://www.gnu.org/licenses/gpl-3.0)
![webtrees major version](https://img.shields.io/badge/webtrees-v2.2.x-green)

![Latest Release](https://img.shields.io/github/v/release/huhwt/huhwt-cce)
[![Downloads](https://img.shields.io/github/downloads/huhwt/huhwt-cce/total)]()

This [webtrees](https://www.webtrees.net/) custom module replaces the original 'Clippings Cart' module.
It offers additional possibilities to add records to the clippings cart and owns beside the possibility
to export GEDCOM information for visualizing the records in the clippings cart using a diagram.

This custom module is primarily based on the hh_clippings_cart_enhanced by Hermann Hartenthaler
(https://github.com/hartenthaler/hh_clippings_cart_enhanced)
For features and background of this module please have a look at the above given link.

This custom module is only executable with webtrees-2.2.3 onwards.

For webtress-2.2.1/-2.2.2 use the release 2.2.1.2.

For webtrees-2.1 use the latest release of the huhwt-cce 2.1 Branch; for webtrees-2.0 use huhwt-cce20.


Attention:
~~~
  This module requires to be operated in a PHP 8.3-onward system, according to the webtrees-2.2 requirements.
~~~

## Contents
This Readme contains the following main sections

* [Description](#description)
* [Requirements](#requirements)
* [Installation](#installation)
* [Upgrade](#upgrade)
* [Translation](#translation)
* [Contact Support](#support)
* [Thank you](#thanks)
* [License](#license)

<a name="description"></a>
## Description

Functions and actions for records are described in detail in the [Hartenthaler module description](/README-CCE.md).

This module covers all clipping actions covered in the Webtrees standard. This has been supplemented by the adoption of entries from the 'Family List' and 'Individual List', both from the respective standard functions and from the 'Shared Notes List' as well as from 'General ...' and 'Advanced Search', which use these views for the output. In 'Individual List' mode, parents and descendants can also be adopted alternatively or completely as required.

[huhwt-xtv](https://github.com/huhwt/huhwt-xtv) (Tree view extended) and [huhwt-mtv](https://github.com/huhwt-mtv) (Multi-tree view for admin action "Check duplicates") can now also be adopted into the clipping basket.

The clipping actions carried out are displayed as an additional overview. Each clipping action done by CCE will be identified (example: INDI\~I1 -> Individual I1 Action:'Only this record' - INDI_ANCESTOR_FAMILIES\~I1 Action: 'This Person, his ancestors and their families'). You can filter the collected entries according to these actions. Each action can be undone individually.

The cart's content can be stored as files on Server-Side (file location is defined by Tree-Name and User-ID, you may choose your own name). Saved files may be reloaded and added to the actual cart's content.

An action initiated by the user then takes place on the records in the clippings cart, such as
* the export to a GEDCOM zip file, as in the actual clippings cart module
* the export to file in plain textual GEDCOM
* the display of the objects in list form with the options of sorting and filtering this list (tbd)
* the transfer of the records in the clippings cart to new functions that visualize this data or analyze it statistically.
  * Such a function could be for example a link-node-diagram like [TAM](https://github.com/huhwt/huhwt-wttam) (Topographic Attribute Map) or [LIN/Lineage](https://github.com/huhwt/huhwt-wtlin).
* the transfer of the records in the clippings cart to new function [TSM](https://github.com/huhwt/huhwt-tsm) (Tagging Service Manager) providing the capability to handle appropriately structured 'Shared Notes' at a high abstract level.
* the transfer of the records in the clippings cart to an other custom module:
  * [ExtendedImportExport](https://github.com/Jefferson49/ExtendedImportExport) with lots of options for filtering or modifying Gedcom content. 

The [TAM], [LIN] and [TSM] functions are provided as their own independent modules.

This module can be operated in addition to the other 'Clippings Cart' functions or replace them completely.

~~~
CAVEAT: Clippings of other 'Clippings Cart' functions can't be precisely identified because of missing references, they will get a generic identifier.
~~~

---

  Note: Since webtrees-2.1.18 the Family-/IndividualListModule are working properly again. Nevertheless there is a recommended modification in webtrees core module 'app/Module/IndividualListModule.php'. It's reasoned by a hard-coded overdefinition for the 'lists/surnames-table' in a vesta-Module ...

line 397 - old:
~~~
echo view('lists/surnames-table', [
~~~
line 397 - new ( this mod will give you an information on the count of names in the table in the table's header )
~~~
echo view('lists/surnames-tableCCE', [
~~~

---

<a name="requirements"></a>
## Requirements

This module requires **PHP 8.3** at least.
This module requires **webtrees** version 2.2.3 at least.
This module has the same general requirements as [webtrees#system-requirements](https://github.com/fisharebest/webtrees#system-requirements).

<a name="installation"></a>
## Installation

This section documents installation instructions for this module.

1. Download the [latest release](https://github.com/huhwt/huhwt-cce/releases/latest) respectively the Github Master branch.
3. Unzip the package into your `webtrees/modules_v4` directory of your web server.
4. Occasionally rename the folder to `huhwt-cce`. It's recommended to remove the respective directory if it already exists.

<a name="upgrade"></a>
## Upgrade

To update simply replace the huhwt-cce files with the new ones from the latest release.

<a name="translation"></a>
## Translation

You can help to translate this module.
It uses the po/mo system.
You can contribute via a pull request (if you know how) or by e-mail.
Updated translations will be included in the next release of this module.

There are now translations in English, German, Netherlands, Catalan, Spanish and Russian available.

* Netherlands - many thanks to TheDutchJewel
* Catalan, Spanish - many thanks to BernatBanyuls
* Russian - many thanks to ol810

<a name="support"></a>
## Support

<span style="font-weight: bold;">Issues: </span>you can report errors raising an issue
in this GitHub repository.

<span style="font-weight: bold;">Forum: </span>general webtrees support can be found 
at the [webtrees forum](http://www.webtrees.net/)

<a name="thanks"></a>
## Thank you

Special thanks to [hartenthaler](https://github.com/hartenthaler/) for providing this valuable module.

Special thanks to [Jefferson49](https://github.com/Jefferson49/ExtendedImportExport) for adding the support to export clippings cart to his extraordinary module.

Special thanks to both of them for adapting CCE to the wt-2.2.3 environment so quickly. 

<a name="license"></a>
## License

This module was originally derived from the [Vesta clippings cart](https://github.com/vesta-webtrees-2-custom-modules/vesta_clippings_cart) module.

* Copyright (C) 2022/2025 huhwt - EW.H
* Copyright (C) 2021 Hermann Hartenthaler
* Copyright (C) 2021 Richard Cissée. All rights reserved.
* Derived from **webtrees** - Copyright 2024 webtrees development team.

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.

* * *
