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
    let esgVmin = parseInt(esgV.getAttribute("min"));
    let esgVmax = parseInt(esgV.getAttribute("max"));
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
    let esgVmin = parseInt(esgV.getAttribute("min"));
    let esgVmax = parseInt(esgV.getAttribute("max"));
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

function prepCollapse() {
    let elems = document.getElementsByClassName('CCE_Theader');
    for ( const elem of elems ) {
        elem.addEventListener( 'click', event => {
            let elemev = event.target;
            togglevis(elemev);
        });
    }
}

function togglevis(helem) {
    let he_name = helem.getAttribute("name");
    let henames = document.getElementsByName(he_name);
    for ( const henelem of henames) {
        if ( henelem != helem) {
            let hevis = henelem.getAttribute("style");
            if (hevis == "display: none") {
                henelem.setAttribute("style", "display: visible");
            } else {
                henelem.setAttribute("style", "display: none");
            }
        }
    }
}

function showTables() {
    let elems = document.getElementsByClassName("wt-facts-table");
    for ( const elem of elems ) {
        let hevis = elem.getAttribute("style");
        if (hevis == "display:none")
            elem.setAttribute("style", "display:visible");
    }
<<<<<<< Updated upstream
    let elem = document.getElementById("prepInfo");
    elem.setAttribute("style", "display:none");;
}
=======
}
function clickCAdelete(trElem, celem, celemt, delem) {
    let doneHighlight = celem.classList.contains("CCEhighlighted");
    let doRefresh = false;
    let XREFs = [];
    let tbodies = document.querySelectorAll("table.CCE-facts-table > tbody");       // all tables with records
    for ( const tbody of tbodies) {
        let tbadge = tbody.parentNode.querySelector('table > thead > tr > th > span');  // we need the element that hosts the actual counter ...
        let tbadge_tc = tbadge.nextElementSibling;                                      // ... and also the total-counter
        let tcount = parseInt(tbadge.innerText);                                    // the actual counter
        let tcount_tc = parseInt(tbadge_tc.innerText.substring(1));                 // the total counter - caveat!: leading '/'
        let ta_spans = tbody.querySelectorAll("span.CCEbadge");                     // all cartAct-badges in table
        for ( const ta_span of ta_spans) {
            let ta_sptxt = ta_span.innerText;                                       // cartAct-defs in badge
            let do_del = ta_sptxt.includes(celemt);                                 // cartAct-toDel included?
            if (do_del) {
                let span_td = ta_span.parentElement;                                // badge's parent - contains the cartAct-defs
                let span_tr = span_td.parentElement;                                // the record-line
                let xref = span_tr.getAttribute('xref');                            // we get the XREF
                span_td.removeChild(ta_span);                                       // remove the badge
                if (!span_td.firstElementChild) {                                   // any other badges remaining? ...
                    tbody.removeChild(span_tr);                                     // ... no: remove the record-line
                    tcount--;
                    tcount_tc--;
                    if (doneHighlight)
                        doRefresh = true;
                }
                if (!XREFs.includes(xref))                                          // put xref in list
                    XREFs.push(xref);
            }
        }
        tbadge.innerText = tcount.toString();                                       // update the actual counter
        tbadge_tc.innerText = '/ ' + tcount_tc.toString();                             // ... and also the total counter
    }
    execCAdelete(delem, XREFs);

    let trElemp = trElem.parentNode;
    trElemp.removeChild(trElem);
    let trElemo = trElemp.parentNode;
    let trElemoBadge = trElemo.querySelector("span.badge.bg-secondary");
    let tcount = parseInt(trElemoBadge.textContent) - 1;
    trElemoBadge.textContent = tcount.toString();

    if (doRefresh) {
        refreshTR();
    }
}
function execCAdelete(delem, XREFs) {
    let cartAct = delem.getAttribute('cartact');
    let action = delem.getAttribute('action');
    let route_ajax = delem.getAttribute('data-url');
    let _url = decodeURIComponent(route_ajax);
    if (_url.includes("&amp;")) {
        _url = _url.replace("&amp;","&");
    }
    _url = _url + '&action=' + encodeURIComponent(action) + '&cartact=' + encodeURIComponent(cartAct);
    $.ajax({
        url: _url,
        dataType: "json",
        data: "xrefs=" + XREFs.join(";"),
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
function updateCCEcount(XREFcnt) {
    let CCEmen = document.querySelector(".CCE_Menue");
    let CCEmenBadge = CCEmen.querySelector("span.badge.bg-secondary");
    CCEmenBadge.textContent = " "  + XREFcnt.toString() + " ";
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
    let he_name = thElem.getAttribute("name");

    let doneHighlight = elemev.classList.contains("CCEhighlighted");
    let doColor = doneHighlight ? "OFF" : "ON";
    [CCEcolor, colorsOnCnt] = getCCEcolor(tbElem, trElem, doColor);

    elemev.classList.toggle("CCEhighlighted");
    elemev.classList.toggle(CCEcolor);

    let tbodies = document.querySelectorAll("table.CCE-facts-table > tbody");
    for ( const tbody of tbodies) {
        let ta_spans = tbody.querySelectorAll("span.CCEbadge");
        let trC = 0;                                                        // we want to count badged lines
        for ( const ta_span of ta_spans) {
            let ta_sptxt = ta_span.innerText;
            if (ta_sptxt.includes(elemevt)) {
                ta_span.classList.toggle("CCEhighlighted");
                ta_span.classList.toggle(CCEcolor);
            }
        }
        if (colorsOnCnt > 0) {                                              // we have active highlighting ...
            let tr_lines = tbody.querySelectorAll("tr.CCE_Rline");          // ... so we collect the table-lines carrying gedcom-records ...
            for ( const tr_line of tr_lines) {                              // ... and traverse over it ...
                let tr_text = tr_line.children[1].innerHTML;                // ... the badges are in the middle child ...
                if (tr_text.includes("CCEhighlighted")) {                   // ... if one of them is highlighted ...
                    tr_vis(tr_line, true);                                  // ... the whole line is set visible ...
                    trC++;                                                      // add counter
                } else
                    tr_vis(tr_line, false);                                 // ... otherwise it's set to hidden
            }
        } else {
            let tr_lines = tbody.querySelectorAll("tr.CCE_Rline");
            for ( const tr_line of tr_lines) {
                tr_vis(tr_line, true);
                trC++;
            }
        }
        let tbHead = tbody.previousElementSibling;
        let tbHBadge = tbHead.querySelector("span.badge.bg-secondary");
        tbHBadge.textContent = " "  + trC.toString() + " ";
    
    }
}

/**
 * tr_line      a table row element <tr...>
 * doState      boolean     -> true: set it visible    false: unvisible
 */
function tr_vis(tr_line, doState) {
    let trvis = (doState ? "visible" : "none");
    let trstyle = "display:" + trvis;
    tr_line.setAttribute("style", trstyle);
}

function getCCEcolor(tbElem, trElem, colorDo) {
    let colorsOn = tbElem.getAttribute("colorsOn");
    colOn = [];
    if (colorsOn>"")
        colOn = colorsOn.split(';');
    let colorsOff = tbElem.getAttribute("colorsOff");
    let colOff = colorsOff.split(';');
    let colOff_act = colOff.filter((colX) => colX != "_");

    let trColor = trElem.getAttribute("color") ?? "";
    if (colorDo == "ON") {
        if (colOff_act.length == 0) {
            alert("No colors available");
            return "";
        }
        trColor = colOff_act.shift();
        let iCol = parseInt(trColor.substring(trColor.length-1));
        colOff[iCol] = "_";
        colOn.push(trColor);
        trElem.setAttribute("color", trColor);
    } else {
        if (colOn.length == 0) {
            alert("No colors defined");
            return "";
        }
        let itrCol = colOn.indexOf(trColor);
        if (itrCol >= 0) {
            colOn.splice(itrCol, 1);
            let iCol = parseInt(trColor.substring(trColor.length-1));
            colOff[iCol] = trColor;
            trElem.setAttribute("color", "");
        }
    }
    colorsOn = colOn.join(';');
    tbElem.setAttribute("colorsOn", colorsOn);
    colorsOff = colOff.join(';');
    tbElem.setAttribute("colorsOff", colorsOff);
    return ["CCE"+trColor, colorsOn.length];
}

function refreshTR() {
    let tbodies = document.querySelectorAll("table.CCE-facts-table > tbody");
    for ( const tbody of tbodies) {
        let trC = 0;                                                    // we want to count badged lines
        let tr_lines = tbody.querySelectorAll("tr.CCE_Rline");          // ... so we collect the table-lines carrying gedcom-records ...
        for ( const tr_line of tr_lines) {                              // ... and traverse over it ...
            tr_vis(tr_line, true);                                      // ... the whole line is set visible ...
            trC++;                                                      // add counter
        }
        let tbHead = tbody.previousElementSibling;
        let tbHBadge = tbHead.querySelector("span.badge.bg-secondary");
        tbHBadge.textContent = " "  + trC.toString() + " ";
     }
}
>>>>>>> Stashed changes
