/*
 * webtrees - clippings cart enhanced
 *
 * Copyright (C) 2025 huhwt. All rights reserved.
 *
 * webtrees: online genealogy / web based family history software
 * Copyright (C) 2025 webtrees development team.
 *
 * This is the client side of cart functions
 * 
 */

var s_wt                = window.webtrees;                              // grep the webtrees js standard object

function showgensPrep() {
    $('#showgensSubA').click(function () {
        showgensMinus('generationsA','.cce_genA');
    });
    $('#showgensAddA').click(function () {
        showgensPlus('generationsA', '.cce_genA');
    });
    $('#showgensSubD').click(function () {
        showgensMinus('generationsD','.cce_genD');
    });
    $('#showgensAddD').click(function () {
        showgensPlus('generationsD', '.cce_genD');
    });
}
function showgensMinus(forID, forClass) {
    var esgV = document.getElementById(forID);
    let vsgV = parseInt(esgV.value);
    let esgVmin = parseInt(esgV.getAttribute('min'));
    let esgVmax = parseInt(esgV.getAttribute('max'));
    vsgV -= 1;
    if (vsgV < esgVmin ) { vsgV = esgVmin; }
    if (vsgV > esgVmax ) { vsgV = esgVmax; }
    esgV.value = vsgV.toString();
    let elems = document.querySelectorAll(forClass);
    for (let i = 0; i < elems.length; i++) {
        elems[i].innerText = vsgV.toString();
    }
    return false;
}
function showgensPlus(forID, forClass) {
    var esgV = document.getElementById(forID);
    let vsgV = parseInt(esgV.value);
    let esgVmin = parseInt(esgV.getAttribute('min'));
    let esgVmax = parseInt(esgV.getAttribute('max'));
    vsgV += 1;
    if (vsgV < esgVmin ) { vsgV = esgVmin; }
    if (vsgV > esgVmax ) { vsgV = esgVmax; }
    esgV.value = vsgV.toString();
    let elems = document.querySelectorAll(forClass);
    for (let i = 0; i < elems.length; i++) {
        elems[i].innerText = vsgV.toString();
    }
    return false;
}

/**
 * For performance reasons, the table contents are initially hidden when the page is accessed.
 * Once the structure of the tables is complete, the contents are displayed.
 * The 'prepInfo' element is initially displayed while the build is in progress, when completed, it will get unvisible.
 * The 'Export' buttons are disabled until there is some content in the tables.
 */
function CCE_showTables(Pcart_empty) {
    let elems = document.getElementsByClassName('wt-facts-table');
    for ( const elem of elems ) {
        let hevis = elem.getAttribute('style');
        if (hevis == 'display:none')
            elem.setAttribute('style', 'display:visible');
    }
    let elem = document.getElementById('prepInfo');
    if ( elem )
        elem.setAttribute('style', 'display:none');;

    let cart_empty = Pcart_empty == 1 ? true : false;
    if ( !cart_empty ) {
        let btn_export = document.getElementById('CCE_btnExport');
        if (btn_export.hasAttribute('disabled'))
            btn_export.removeAttribute('disabled');
        btn_export = document.getElementById('CCE_btnExport_CSV');
        if (btn_export.hasAttribute('disabled'))
            btn_export.removeAttribute('disabled');
    }
}

/**
 * Some areas of the tables will have to be provided with 'click' events ...
 * - any of them will be enabled to toggle showing the rows
 * - the explicitely by their names identified ones will also get functions to show higlighted values and/or to delete depending rows
 */
function CCE_prepPevents() {
    let elems = document.getElementsByClassName('CCE_Theader');
    let elem_CA = null;
    let elem_CAF = null;
    for ( const elem of elems ) {
        let eName = elem.getAttribute('name');
        switch (eName) {
            case 'CCE-CartActions':
                elem_CA = elem;
                prepCA_events(elem);                // highlight - delete
                break;
            case 'CCE-CartActions Filter':
                elem_CAF = elem;
                prepCAF_events(elem);               // highlight - delete
                break;
            case 'CCE-CAfiles':
                prepCAfile_events(elem);            // delete
            default:
                prep_toggleCollapse(elem);          // toggle

        }
    }
    prep_triggerCollapse(elem_CA, 'CCE-CAFbody');   // toggle #1 vs. toggle #2

    prep_triggerCollapse(elem_CAF, 'CCE-CAbody');   // toggle #1 vs. toggle #2

    localStorage.setItem( 'CCE_ShowCart_href', location.href );     // save location.href for to use it as fallback when other ref is active

    let btn_Redo    = document.getElementById('btn_Redo');          // force reload
    btn_Redo.addEventListener( 'click', event => {
        location.reload();
    }); 
}

/**
 * prep toggling display style for complete table - double action: active element and other element
 */
function prep_triggerCollapse(elem, other_elemID) {
    if ( elem ) {
        let other_elem = document.getElementById(other_elemID);
        elem.addEventListener( 'click', event => {
            let elemev = event.target;
            toggleCollapse(elemev);
            if ( other_elemID )
                switchStyleVis(other_elem);
        });
    }
}

/**
 * prep toggling display style for comlete table - single action
 */
function prep_toggleCollapse(elem) {
    elem.addEventListener( 'click', event => {
        let elemev = event.target;
        toggleCollapse(elemev);
    });
}

/**
 * toggle style display for complete table
 */
function toggleCollapse(helem) {
    let he_name = helem.getAttribute('name');
    let henames = document.getElementsByName(he_name);
    for ( const henelem of henames) {
        if ( henelem != helem) {
            switchStyleVis(henelem);
        }
    }
}
function switchStyleVis(henelem) {
    let hevis = henelem.getAttribute('style');
    if (hevis == 'display:none') {
        henelem.setAttribute('style', 'display:visible');
    } else {
        henelem.setAttribute('style', 'display:none');
    }
}

/**
 * thElem   the element carrying the name 'CCE-CartActions'
 */
function prepCA_events(thElem) {
    let he_name = thElem.getAttribute('name');
    let henames = document.getElementsByName(he_name);                      // we collect significant nodes ...
    for ( const henelem of henames) {
        if ( henelem != thElem) {                                           // ... but we don't want the thElem itself
            let belems = henelem.getElementsByClassName('cce-icon-basket');      // we collect significant nodes ...
            for ( const belem of belems ) {                                     // ... and grep for each:
                let trElem = belem.parentElement.parentElement;                 // -> the superior table-line
                let tbElem = trElem.parentElement;                              // -> the superior table-body
                let celem = belem.nextElementSibling;                           // -> the element to receive the event
                let celemt = celem.innerText;                                   // we grep the text ...
                celem.addEventListener( 'click', event => {
                    let elemev = event.target;
                    let elemevt = elemev.innerText;                                 // we grep the text ...
                    if (elemevt.includes('|'))                                      // ... extended text? ...
                        elemevt = elemevt.substring(0, elemevt.indexOf('|'));       // ... cut off extension
                    clickCAtoggler(thElem, tbElem, trElem, elemev, elemevt);        // ... to feed the handler
                });
                let pelem = belem.parentElement.parentElement;
                const aelem = pelem.lastElementChild;
                let delem = aelem.firstElementChild;
                delem.addEventListener( 'click', event => {
                    if (celemt.includes('|'))                                    // ... extended text? ...
                        celemt = celemt.substring(0, celemt.indexOf('|'));       // ... cut off extension
                    clickCA_delete(trElem, celem, celemt, delem);                        // ... to feed the handler
                });

            }
        }
    }
}

/**
 * thElem       -> the element carrying the name 'CCE-CartActions'
 * structure elements tbody 'CCE-CartActions'
 * tbElem       -> the tbody itself
 * trElem       -> the table-row of clicked 
 * elemev       -> the clicked cartAction
 * elemevt      -> the correspondig text
 */
function clickCAtoggler(thElem, tbElem, trElem, elemev, elemevt) {
    let he_name = thElem.getAttribute('name');

    let doneHighlight = elemev.classList.contains('CCEhighlighted');
    let doColor = doneHighlight ? 'OFF' : 'ON';
    [CCEcolor, colorsOnCnt] = getCCEcolor('colors', tbElem, trElem, doColor);

    elemev.classList.toggle('CCEhighlighted');
    elemev.classList.toggle(CCEcolor);

    let tbodies = document.querySelectorAll('table.CCE-facts-table > tbody');
    for ( const tbody of tbodies) {
        let ta_spans = tbody.querySelectorAll('span.CCEbadge');
        let trC = 0;                                                        // we want to count badged lines
        for ( const ta_span of ta_spans) {
            let ta_sptxt = ta_span.innerText;
            if (ta_sptxt.includes(elemevt)) {
                ta_span.classList.toggle('CCEhighlighted');
                ta_span.classList.toggle(CCEcolor);
            }
        }
        if (colorsOnCnt > 0) {                                              // we have active highlighting ...
            let tr_lines = tbody.querySelectorAll('tr.CCE_Rline');          // ... so we collect the table-lines carrying gedcom-records ...
            for ( const tr_line of tr_lines) {                              // ... and traverse over it ...
                let tr_text = tr_line.children[1].innerHTML;                    // ... the badges are in the middle child ...
                if (tr_text.includes('CCEhighlighted')) {                       // ... if one of them is highlighted ...
                    tr_vis(tr_line, true);                                          // ... the whole line is set visible ...
                    trC++;                                                          // add counter
                } else
                    tr_vis(tr_line, false);                                 // ... otherwise it's set to hidden
            }
        } else {                                                            // no active hightlighting ...
            let tr_lines = tbody.querySelectorAll('tr.CCE_Rline');              // ... so we collect the table-lines carrying gedcom-records ...
            for ( const tr_line of tr_lines) {                                  // ... and traverse over it ...
                tr_vis(tr_line, true);                                              // ... and set it visible anyway
                trC++;                                                              // add counter
            }
        }
        let tbHead = tbody.previousElementSibling;                          // update counter value in header
        let tbHBadge = tbHead.querySelector('span.badge.bg-secondary');
        tbHBadge.textContent = ' '  + trC.toString() + ' ';
    
    }
}


function clickCA_delete(trElem, celem, celemt, delem) {
    let doneHighlight = celem.classList.contains('CCEhighlighted');
    let doRefresh = false;                                                          // check if active color is involved
    let XREFs = [];
    let tbodies = document.querySelectorAll('table.CCE-facts-table > tbody');       // all tables with records
    for ( const tbody of tbodies) {
        let tbadge = tbody.parentNode.querySelector('table > thead > tr > th > span');  // we need the element that hosts the actual counter ...
        let tbadge_tc = tbadge.nextElementSibling;                                      // ... and also the total-counter
        let tcount = parseInt(tbadge.innerText);                                        // the actual counter
        let tcount_tc = parseInt(tbadge_tc.innerText.substring(1));                     // the total counter - caveat!: leading '/'

        let ta_spans = tbody.querySelectorAll('span.CCEbadge');                         // all cartAct-badges in table
        for ( const ta_span of ta_spans) {
            let ta_sptxt = ta_span.innerText;                                               // cartAct-defs in badge
            let do_del = ta_sptxt.includes(celemt);                                         // cartAct-toDel included?
            if (do_del) {                                                                   // yes!
                let span_td = ta_span.parentElement;                                            // badge's parent - contains the cartAct-defs
                let span_tr = span_td.parentElement;                                            // the record-line
                let xref = span_tr.getAttribute('xref');                                        // we get the XREF
                span_td.removeChild(ta_span);                                                   // remove the badge
                if (!span_td.firstElementChild) {                                               // any other badges remaining? ...
                    tbody.removeChild(span_tr);                                                     // ... no: remove the record-line
                    tcount--;
                    tcount_tc--;
                    if (doneHighlight)
                        doRefresh = true;                                                               // active color involved
                }
                if (!XREFs.includes(xref))                                                      // put xref in list
                    XREFs.push(xref);
            }
        }

        tbadge.innerText = tcount.toString();                                           // update the actual counter
        tbadge_tc.innerText = '/ ' + tcount_tc.toString();                              // ... and also the total counter
    }
    execCA_delete(delem, XREFs);                                                    // execute the delete

    let trElemp = trElem.parentNode;                                                // get the parent ...
    trElemp.removeChild(trElem);                                                    // ... and remove the deleted line in view

    let trElemo = trElemp.parentNode;
    let trElemoBadge = trElemo.querySelector('span.badge.bg-secondary');            // get the element hosting the elements counter ...
    let tcount = parseInt(trElemoBadge.textContent) - 1;                            // ... decrease the value ...
    trElemoBadge.textContent = tcount.toString();                                   // ... and update
    toggle_btnExport(tcount);

    if (doRefresh) {
        refreshTR();                                                                    // optionally change active color
    }
}

function execCA_delete(delem, XREFs) {
    let cartAct = delem.getAttribute('cartact');
    let action = delem.getAttribute('action');
    let route_ajax = delem.getAttribute('data-url');
    let _url = decodeURIComponent(route_ajax);
    if (_url.includes('&amp;')) {
        _url = _url.replace('&amp;','&');
    }
    _url = _url + '&action=' + encodeURIComponent(action) + '&cartact=' + encodeURIComponent(cartAct);
    jQuery.ajax({
        url: _url,
        dataType: 'json',
        data: 'xrefs=' + XREFs.join(';'),
        success: function (ret) {
            var _ret = ret;
            updateCCEcount(_ret);
            return true;
        },
        complete: function () {
//
        },
        timeout: function () {
            return false;
        }
    });
}

/**
 * thElem   the element carrying the name 'CCE-CartActions Filter' - there are 2 of them
 * belem(s) the element with basket icon
 *  dElem    the div containing belem
 *   tdElem   the td  containing dElem
 *    trElem   the tr  containing tdElem
 *     tbElem   the tbody containing trElem
 * celem    the next sibling to belem, carrying the filter value - it receives the click event toggling filter action
 */
function prepCAF_events(thElem) {
    let he_name = thElem.getAttribute('name');
    let henames = document.getElementsByName(he_name);                      // we collect significant nodes ...
    for ( const henelem of henames) {
        if ( henelem != thElem) {                                           // ... but we don't want the thElem itself
            let belems = henelem.getElementsByClassName('cce-icon-basket');     // we collect significant nodes ...
            for ( const belem of belems ) {                                     // ... and grep for each:
                let dElem = belem.parentElement;                                // -> the div containing the element
                let tdElem = dElem.parentElement;                               // 
                let trElem = tdElem.parentElement;                              // -> the superior table-line
                let tbElem = trElem.parentElement;                              // -> the superior table-body
                let celem = belem.nextElementSibling;                           // -> the element to receive the event
                let celemt = celem.getAttribute('badges');                          // we grep the text ...
                celem.addEventListener( 'click', event => {
                    let elemev = event.target;
                    clickCAFtoggler(dElem, tbElem, trElem, elemev, celemt);         // ... to feed the handler
                });
                const aelem = tdElem.nextElementSibling;                        // -> the Action column
                let delem = aelem.firstElementChild;                            // -> the 'Delete' icon
                delem.addEventListener( 'click', event => {
                    // if (celemt.includes('|'))                                       // ... extended text? ...
                    //     celemt = celemt.substring(0, celemt.indexOf('|'));              // ... cut off extension
                    clickCAF_delete(trElem, celem, celemt, delem);                  // feed the handler
                    tbElem.removeChild(trElem);                                     // remove clicked table-line
                    let thElem = tbElem.previousElementSibling;                     // -> the thead containing the value badge
                    let spElemBadge = thElem.querySelector('span.badge.bg-secondary');
                    let tcount = parseInt(spElemBadge.textContent) - 1;
                    spElemBadge.textContent = ' ' + tcount.toString() + ' ';
                });
            }
        }
    }
}

/**
 * dElem        -> the div element in which badges are embedded
 * structure elements tbody 'CCE-CartActions Filter'
 * tbElem       -> the tbody itself
 * trElem       -> the table-row of clicked 
 * elemev       -> the clicked cartAction
 * elemevt      -> the correspondig text
 */
function clickCAFtoggler(dElem, tbElem, trElem, elemev, elemevt) {
    let doneHighlight = dElem.classList.contains('CCEhighlightedF');
    let doColor = doneHighlight ? 'OFF' : 'ON';
    [CCEcolor, colorsOnCnt] = getCCEcolor('colorsf', tbElem, dElem, doColor);

    dElem.classList.toggle('CCEhighlightedF');
    dElem.classList.toggle(CCEcolor);

    let td_spans = document.getElementsByName(elemevt);
    for ( const td_span of td_spans) {
        td_span.classList.toggle('CCEhighlightedF');
        // td_span.classList.toggle(CCEcolor);
    }

    let tbodies = document.querySelectorAll('table.CCE-facts-table > tbody');
    for ( const tbody of tbodies) {
        let trC = 0;                                                        // we want to count badged lines
        if (colorsOnCnt > 0) {                                              // we have active highlighting ...
            let tr_lines = tbody.querySelectorAll('tr.CCE_Rline');          // ... so we collect the table-lines carrying gedcom-records ...
            for ( const tr_line of tr_lines) {                              // ... and traverse over it ...
                let tdelem = tr_line.children[1];                           // ... the badges are in the middle child ...
                if (tdelem.classList.contains('CCEhighlightedF')) {            // ... if one of them is highlighted ...
                    tr_vis(tr_line, true);                                  // ... the whole line is set visible ...
                    trC++;                                                      // add counter
                } else
                    tr_vis(tr_line, false);                                 // ... otherwise it's set to hidden
            }
        } else {
            let tr_lines = tbody.querySelectorAll('tr.CCE_Rline');
            for ( const tr_line of tr_lines) {
                tr_vis(tr_line, true);
                trC++;
            }
        }
        let tbHead = tbody.previousElementSibling;
        let tbHBadge = tbHead.querySelector('span.badge.bg-secondary');
        tbHBadge.textContent = ' ' + trC.toString() + ' ';
    
    }
}

function clickCAF_delete(trElem, celem, celemt, delem) {
    let doneHighlight = celem.classList.contains('CCEhighlighted');
    let doRefresh = false;
    let XREFs = [];
    let td_spans = document.getElementsByName(celemt);                          // all cartActFilter-locations in table
    let td_spans_a = Array.from(td_spans);
    let tbodies = document.querySelectorAll('table.CCE-facts-table > tbody');       // all tables with records
    let tcount_all = 0;
    for ( const tbody of tbodies) {
        let tbadge = tbody.parentNode.querySelector('table > thead > tr > th > span');  // we need the element that hosts the actual counter ...
        let tbadge_tc = tbadge.nextElementSibling;                                      // ... and also the total-counter
        let tcount = parseInt(tbadge.innerText);                                    // the actual counter
        let tcount_tc = parseInt(tbadge_tc.innerText.substring(1));                 // the total counter - caveat!: leading '/'

        for ( const td_span of td_spans_a) {
            let span_tr = td_span.parentElement;                                    // the record-line
            let span_tb = span_tr.parentElement;                                    // the body the record-line is within
            if ( span_tb == tbody ) {                                               // are we in the actual tbody? ... then
                let xref = span_tr.getAttribute('xref');                                // we get the XREF
                if (!XREFs.includes(xref))                                              // put xref in list
                    XREFs.push(xref);
                tbody.removeChild(span_tr);                                             // remove the record-line
                tcount--;
                tcount_tc--;
                // if (doneHighlight)
                    doRefresh = true;
            }
        }
        tcount_all += tcount;
        tbadge.innerText = tcount.toString();                                       // update the actual counter
        tbadge_tc.innerText = '/ ' + tcount_tc.toString();                             // ... and also the total counter
    }
    execCAF_delete(delem, XREFs);

    // let trElemp = trElem.parentNode;
    // trElemp.removeChild(trElem);
    // let trElemo = trElemp.parentNode;
    // let trElemoBadge = trElemo.querySelector('span.badge.bg-secondary');
    // let tcount = parseInt(trElemoBadge.textContent) - 1;
    // trElemoBadge.textContent = tcount.toString();

    if (doRefresh) {
        refreshTR();
    }

    toggle_btnExport(tcount_all);
}
function execCAF_delete(delem, XREFs) {
    let cartAct = delem.getAttribute('cartactfilter');
    let action = delem.getAttribute('action');
    let route_ajax = delem.getAttribute('data-url');
    let _url = decodeURIComponent(route_ajax);
    if (_url.includes('&amp;')) {
        _url = _url.replace('&amp;','&');
    }
    _url = _url + '&action=' + encodeURIComponent(action) + '&cartactfilter=' + encodeURIComponent(cartAct);
    jQuery.ajax({
        url: _url,
        dataType: 'json',
        data: 'xrefs=' + XREFs.join(';'),
        success: function (ret) {
            var _ret = ret;
            updateCCEcount(_ret);
            return true;
        },
        complete: function () {
//
        },
        timeout: function () {
            return false;
        }
    });
}

/**
 * thElem   the element carrying the name 'CCE-CAfiles'
 */
function prepCAfile_events(thElem) {
    let he_name = thElem.getAttribute('name');
    let henames = document.getElementsByName(he_name);                      // we collect significant nodes ...
    for ( const henelem of henames) {
        if ( henelem != thElem) {                                           // ... but we don't want the thElem itself
            let belems = henelem.getElementsByClassName('cce-icon-file');       // we collect significant nodes ...
            for ( const belem of belems ) {                                     // ... and grep for each:
                let trElem = belem.parentElement.parentElement;                 // -> the superior table-line
                let tbElem = trElem.parentElement;                              // -> the superior table-body
                let celem = belem.nextElementSibling;                           // -> the element to receive the event
                let celemt = celem.innerText;                                   // we grep the text ...
                let pelem = belem.parentElement.parentElement;
                const aelem = pelem.lastElementChild;
                let delem = aelem.firstElementChild;
                delem.addEventListener( 'click', event => {
                    if (celemt.includes('|'))                                    // ... extended text? ...
                        celemt = celemt.substring(0, celemt.indexOf('|'));       // ... cut off extension
                    clickCAfile_delete(trElem, celem, celemt, delem);                        // ... to feed the handler
                });

            }
        }
    }
}
function clickCAfile_delete(trElem, celem, celemt, delem) {
    let tbodies = document.querySelectorAll('table.CCE-files-table > tbody');       // all tables with records
    for ( const tbody of tbodies) {
        let ta_spans = tbody.querySelectorAll('span.cce-icon-file');                    // all file-badges in table
        for ( const ta_span of ta_spans) {
            let ta_sptxt = ta_span.nextElementSibling.innerText;                    // file-def in badge
            let do_del = ta_sptxt.includes(celemt);                                 // file-toDel included?
            if (do_del) {
                let span_td = ta_span.parentElement;                                // badge's parent - contains the cartAct-defs
                let span_tr = span_td.parentElement;                                // the record-line
                span_td.removeChild(ta_span);                                       // remove the badge
                tbody.removeChild(span_tr);                                     // ... no: remove the record-line
                // if (!span_td.firstElementChild) {                                   // any other badges remaining? ...
                //     tbody.removeChild(span_tr);                                     // ... no: remove the record-line
                //     if (doneHighlight)
                //         doRefresh = true;
                // }
            }
        }
    }

    let cafkey = delem.getAttribute('cafkey');
    let action = delem.getAttribute('action');
    let route_ajax = delem.getAttribute('data-url');
    let _url = decodeURIComponent(route_ajax);
    if (_url.includes('&amp;')) {
        _url = _url.replace('&amp;','&');
    }
    _url = _url + '&action=' + encodeURIComponent(action) + '&cafkey=' + encodeURIComponent(cafkey);
    document.body.classList.add('waiting');
    s_wt.httpGet(_url)
        .then((function(e) {
            let _url    = e.url;
            location.assign(_url);
                return e.url; // .json();
            }
        ))
        .then((function(t) {
                document.body.classList.remove('waiting');
                location.reload();
            }
        ))
        .catch((function(e) {
            document.body.classList.remove('waiting');
            location.reload();
            }
        ))
}

function updateCCEcount(XREFcnt) {
    let CCEmen = document.querySelector('.CCE_Menue');
    let CCEmenBadge = CCEmen.querySelector('span.badge.bg-secondary');
    CCEmenBadge.textContent = ' '  + XREFcnt.toString() + ' ';
}


/**
 * tr_line      a table row element <tr...>
 * doState      boolean     -> true: set it visible    false: unvisible
 */
function tr_vis(tr_line, doState) {
    let trvis = (doState ? 'visible' : 'none');
    let trstyle = 'display:' + trvis;
    tr_line.setAttribute('style', trstyle);
}

function getCCEcolor(color_pref, tbElem, trElem, colorDo) {
    let colorsOn = tbElem.getAttribute(color_pref + 'On');
    colOn = [];
    if (colorsOn>'')
        colOn = colorsOn.split(';');
    let colorsOff = tbElem.getAttribute(color_pref + 'Off');
    let colOff = colorsOff.split(';');
    let colOff_act = colOff.filter((colX) => colX != '_');

    let trColor = trElem.getAttribute('color') ?? '';
    if (colorDo == 'ON') {
        if (colOff_act.length == 0) {
            alert('No colors available');
            return '';
        }
        trColor = colOff_act.shift();
        let iCol = parseInt(trColor.substring(trColor.length-1));
        colOff[iCol] = '_';
        colOn.push(trColor);
        trElem.setAttribute('color', trColor);
    } else {
        if (colOn.length == 0) {
            alert('No colors defined');
            return '';
        }
        let itrCol = colOn.indexOf(trColor);
        if (itrCol >= 0) {
            colOn.splice(itrCol, 1);
            let iCol = parseInt(trColor.substring(trColor.length-1));
            colOff[iCol] = trColor;
            trElem.setAttribute('color', '');
        }
    }
    colorsOn = colOn.join(';');
    tbElem.setAttribute(color_pref + 'On', colorsOn);
    colorsOff = colOff.join(';');
    tbElem.setAttribute(color_pref + 'Off', colorsOff);
    return ['CCE'+trColor, colorsOn.length];
}

function refreshTR() {
    let tbodies = document.querySelectorAll('table.CCE-facts-table > tbody');
    for ( const tbody of tbodies) {
        let trC = 0;                                                    // we want to count badged lines
        let tr_lines = tbody.querySelectorAll('tr.CCE_Rline');          // ... so we collect the table-lines carrying gedcom-records ...
        for ( const tr_line of tr_lines) {                              // ... and traverse over it ...
            tr_vis(tr_line, true);                                      // ... the whole line is set visible ...
            trC++;                                                      // add counter
        }
        let tbHead = tbody.previousElementSibling;
        let tbHBadge = tbHead.querySelector('span.badge.bg-secondary');
        tbHBadge.textContent = ' '  + trC.toString() + ' ';
     }
}

function toggle_btnExport(tcount) {
    let btn_export = document.getElementById('CCE_btnExport');
    let btn_export_csv = document.getElementById('CCE_btnExport_CSV');
    if (tcount === 0) {                                                             // we have no more cartActs ...
        if (!btn_export.hasAttribute('disabled'))
            btn_export.setAttribute('disabled','');                                     // ... so there is no reason to support export
        if (!btn_export_csv.hasAttribute('disabled'))
            btn_export_csv.setAttribute('disabled','');                                 // ... so there is no reason to support export
    } else {                                                                        // we have at least 1 cartAct ,,,
        if (btn_export.hasAttribute('disabled'))
            btn_export.removeAttribute('disabled');                                     // ... so we want to be able to export
        if (btn_export_csv.hasAttribute('disabled'))
            btn_export_csv.removeAttribute('disabled');                                 // ... so we want to be able to export
    }
}

function setOwnFname() {
    let ownFname = document.getElementById('CAfname_own');
    if (ownFname.value == '') { return; }
    let CAfname = ownFname.value;
    let fnameCA = document.getElementById('CAfname');
    fnameCA.textContent = CAfname;
    ownFname.value = '';

    let route_ajax = ownFname.getAttribute('data-url');
    let _url = decodeURIComponent(route_ajax);
    if (_url.includes('&amp;')) {
        _url = _url.replace('&amp;','&');
    }
    let action = ownFname.getAttribute('action');
    _url = _url + '&action=' + action + '&CAfname=' + encodeURIComponent(CAfname);
    jQuery.ajax({
        dataType: 'json',
        url: _url,
        success: function (ret) {
            var _ret = ret;
            return true;
            },
        complete: function () {
// 
        },
        timeout: function () {
// 
        }
    });
 
}

function clickCSVexportExec() {
    let felem = document.getElementById('CCE_btnEXPORT').parentNode.parentNode;
    let route_ajax = felem.getAttribute('action');
    let formData = new FormData();

    exec_wtHTTPpost(route_ajax, formData);
}

function clickCSVexport(event) {
    function collectXREF(tbe, XREFs) {                              // we want the visible records
        let tr_lines = tbe.querySelectorAll('tr.CCE_Rline');          // ... so we collect the table-lines carrying gedcom-records ...
        for ( const tr_line of tr_lines) {                              // ... and traverse over it ...
            let astyle = tr_line.getAttribute('style');
            if (astyle != 'display:none') {
                let _xref = tr_line.getAttribute('xref');
                XREFs.push(_xref);
            }
        }
    }

    let belem = event.target;
    let felem = belem.parentNode.parentNode;

    let XREFs = [];
    let tbodies = document.querySelectorAll('table.CCE-facts-table > tbody');   // collect the relevant parts
    for ( const tbody of tbodies) {
        let aname = tbody.getAttribute('name');
        if (aname == 'CCE-Individual' || aname == 'CCE-Family') {               // ... but only INDI and FAM
            collectXREF(tbody, XREFs);
        } else
            break;
    }
    if (XREFs.length > 0) {
        execCSVexport(felem, XREFs);
    }
}

function execCSVexport(felem, XREFs) {
    let route_ajax = felem.getAttribute('action');
    let formData = new FormData();
    formData.append('xrefs', XREFs.join(';'));

    exec_wtHTTPpost(route_ajax, formData, 'click_CCE_btnEXPORT');
}

function clickCSVimport() {
    let felem = document.getElementById('btn_CCEsubmit').parentNode.parentNode;
    let file_input = document.getElementById('import-client-file');

    let messages = felem.getElementsByClassName('alert');
    let msg = null;
    for ( msg of messages ) {
        if (!msg.classList.contains('is-hidden')) {
            msg.classList.toggle('is-hidden')
        }
    }

    let files = file_input.files;
    if (files.length == 1) {
        let route_ajax = felem.getAttribute('action');
        let formData = new FormData(felem);
        exec_wtHTTPpost(route_ajax, formData, 'click_CCE_btnOK');
    } else {
        if (files.length == 0) {
            msg = document.getElementById('none_file');
        }
        if (files.length > 1) {
            msg = document.getElementById('none_file');
        }
        msg.classList.toggle('is-hidden');
    }
}


function exec_wtHTTPpost(url, FData, callback=null) {
    const modal = document.getElementById('wt-ajax-modal')
    const modal_content = modal.querySelector('.modal-content');
    const select = document.getElementById(modal_content.dataset.wtSelectId);

    s_wt.httpPost(url, FData)
        .then(response => response.json())
        .then(json => {
            if (select && json.value !== '') {
                // This modal was activated by the "create new" button in a select edit control.
                s_wt.resetTomSelect(select.tomselect, json.value, json.text);
                bootstrap.Modal.getInstance(modal).hide();
            } else {
                // Show the success message in the existing modal.
                if (json.html) {
                    modal_content.innerHTML = json.html;
                    if (callback) {
                        switch (callback) {
                            case 'click_CCE_btnEXPORT':
                                click_CCE_btnEXPORT();
                                break;
                            case 'click_CCE_btnOK':
                                click_CCE_btnOK();
                                break;
                            default:
                        }
                    }
                }
                if (json.csv) {
                    execCSVdownload(json.csv);
                    return true;
                }
            }
        })
        .catch(error => {
            modal_content.innerHTML = error;
        });
};
function execCSVdownload(_csv) {
    let $c_type = _csv['content-type'];
    let $c_file = _csv['content-filename'];
    let $c_data = _csv['content-data'];
    let $c_data64 = atob(_csv['content-data64']);
    // Create a temporary URL for the Blob
    const $cblob = new Blob([$c_data], {type: $c_type});  
    const $url = window.URL.createObjectURL( $cblob );
    
    // Create a link and set its href to the temporary URL
    const link = document.createElement('a');
    link.setAttribute('href', $url);
    
    // Set the link attributes for downloading
    link.setAttribute('download', $c_file);
    
    // Programmatically click the link to initiate the download
    link.click();
    
    // Clean up the temporary URL
    URL.revokeObjectURL($url);
}
function click_CCE_btnOK() {
    let btnOK = document.getElementById('CCE_btnOK');
    btnOK.addEventListener('click', event => {
        doCCEloaded();
    });
}
function click_CCE_btnEXPORT() {
    let btnEXPORT = document.getElementById('CCE_btnEXPORT');
    btnEXPORT.addEventListener('click', event => {
        clickCSVexportExec();
    });
}

function prepCCEload(modElem) {
    let _inputs = modElem.querySelectorAll('input');
    for ( const inpe of _inputs ) {
        inpe.addEventListener('change', (ev) => {
            let _cb = ev.target;
            let _cbs = _cb.nextElementSibling;
            if (_cb.checked) {
                _cbs.classList.add('cce-cb-lblch');
            } else {
                _cbs.classList.remove('cce-cb-lblch');
            }
        });
    }
    var emc = modElem.querySelector(".modal-content");
    let _buttons = modElem.querySelectorAll('button.btn-link');
    for ( const btne of _buttons ) {
        btne.addEventListener('click', (ev) => {
            let telem    = ev.target;
            ev.stopImmediatePropagation();
            do {
                if (telem.classList.contains('btn'))
                    break;
                telem   = telem.parentNode;
            } while (!telem.classList.contains('btn'));
            let belem   = telem.parentNode;

            let fname   = belem.getAttribute('fname');
            let calledBy  = belem.getAttribute('calledby');
            let route_ajax  = belem.getAttribute('data-url');
            let _url = decodeURIComponent(route_ajax);
            if (_url.includes('&amp;')) {
                _url = _url.replace('&amp;','&');
            }
            _url = _url + '&fname=' + encodeURIComponent(fname) + '&calledby=' + encodeURIComponent(calledBy);
            document.body.classList.add('waiting');
            s_wt.httpGet(_url)
                .then((function(e) {
                    let _url    = e.url;
                    location.assign(_url);
                        return e.url; // .json();
                    }
                ))
                .then((function(t) {
                        document.body.classList.remove('waiting');
                        location.reload();
                    }
                ))
                .catch((function(e) {
                    document.body.classList.remove('waiting');
                    location.reload();
                 }
                ))
        });
    }
}

function doCCEloaded() {
    let CCE_ShowCart_href = localStorage.getItem('CCE_ShowCart_href');
    location.replace(CCE_ShowCart_href);
}

function actionReport(XREFcnt) {
    let pto = typeof XREFcnt;
    switch (pto) {
        case 'object':
            vcnt = XREFcnt[0];
            actionReportPop(XREFcnt);
            return true;
            break;
        default:
            return false;
    }
}

function actionReportPop(XREFcnt) {
    let vCntS = XREFcnt[0];
    let vCntN = XREFcnt[1];
    let vCntStxt = XREFcnt[2];
    let vCntNtxt = XREFcnt[3];
    let elem_pop = document.getElementById('CCEpopUp');
    if (!elem_pop) {
        let elem_main = document.getElementsByClassName('CCE_Menue')[0];
        let elem_dpop = document.createElement('div');
        elem_dpop.id = 'CCEpopUp';
        elem_dpop.classList = 'CCEpopup hidden';

        let elem_dlineS = document.createElement('div');
        elem_dlineS.className = 'pop-line lineS';
        elem_dpop.appendChild(elem_dlineS);
        let elem_dlineN = document.createElement('div');
        elem_dlineN.className = 'pop-line lineN';
        elem_dpop.appendChild(elem_dlineN);

        elem_main.appendChild(elem_dpop);

        elem_pop = document.getElementById('CCEpopUp');
    }
    let elem_dlineS = elem_pop.firstElementChild;
    elem_dlineS.textContent = vCntStxt;
    let elem_dlineN = elem_pop.lastElementChild;
    elem_dlineN.textContent = vCntNtxt;
    if (elem_pop.classList.contains('hidden'))
        elem_pop.classList.remove('hidden');
    elem_pop.style.opacity = 1;
    setTimeout(fadeOut,2400);
}

function fadeOut() {
    let elem_pop = document.getElementById('CCEpopUp');
    var op = 1;  // initial opacity
    var timer = setInterval(function () {
        if (op <= 0.1){
            clearInterval(timer);
            elem_pop.classList.add('hidden');
            let elem_par = elem_pop.parentNode;
            // elem_par.removeChild(elem_pop);
        }
        elem_pop.style.opacity = op;
        elem_pop.style.filter = 'alpha(opacity=' + op * 100 + ")";
        op -= op * 0.2;
    }, 100);
}
