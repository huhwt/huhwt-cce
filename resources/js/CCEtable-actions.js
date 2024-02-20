function exec_Request(event, _boolWp, _listType, _clipAction, _boolWs = "") {
    let elem = event.target;
    let actURL = window.location.href;
    let indTypeInd = 5;                                         // Index of List-Indicator - assumed PrettyUrl ...
    if (actURL.includes('index.php?route='))                    // ... but may be UglyUrl
        indTypeInd = 6;
    let actSEARCH = decodeURIComponent(window.location.search);
    actSEARCH = actSEARCH.substring(actSEARCH.indexOf("&"));

    let dt_pag = get_dt_length();

    let fXREF = "_";                                            // first XREF in table-row
    let XREFs = [];                                             // array of XREFs für update
    let dt = document.querySelector("#DataTables_Table_0");
    let dtb = dt.querySelector("tbody");
    let dtb_Fs = dtb.querySelectorAll("a");
    for (let i = 0; i < dtb_Fs.length; i++) {
        let _Fe = dtb_Fs[i].href;
        let _Fed = decodeURIComponent(_Fe);
        let _Fes = _Fed.split("/");
        if (_Fes[indTypeInd] == _listType) {                     // if it is this type of list ...
            let XREF = _Fes[indTypeInd+1];                      // ... we'll find the Xref in the next slot
            if (XREF != fXREF) {
                if ( XREFs.indexOf(XREF) < 0 ) {
                    fXREF = XREF;
                    XREFs.push(XREF);
                }
            }
        }
    }
//    console.log("XREFs", XREFs.toString());
    let _xrefs = JSON.stringify(XREFs);
    let _url = decodeURIComponent(elem.dataset.url);
    if (_url.includes("&amp;")) {
        _url = _url.replace("&amp;","&");
    }
    _url = _url + "&action=" + _clipAction + "&boolWp=" + encodeURIComponent(_boolWp) + "&actSEARCH=" + encodeURIComponent(actSEARCH) + "&actPage=" + encodeURIComponent(dt_pag) + "&boolWs=" + encodeURIComponent(_boolWs);
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
    let pto = typeof XREFcnt;
    let cnt = CCEmenBadge.textContent;
    cnt = cnt.substring(2).trim();
    let vcnt = parseInt(cnt);
    switch (pto) {
        case 'object':
            vcnt = XREFcnt[0];
            showCountPop(XREFcnt);
            break;
        case 'number':
        default:
            vcnt = XREFcnt;
            break;
    }
    CCEmenBadge.textContent = " "  + vcnt.toString() + " ";
}

function showCountPop(XREFcnt) {
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
            elem_par = elem_pop.parentNode;
            elem_par.removeChild(elem_pop);
        }
        elem_pop.style.opacity = op;
        elem_pop.style.filter = 'alpha(opacity=' + op * 100 + ")";
        op -= op * 0.2;
    }, 100);
}

function get_dt_length() {
    let dt_0 = FLjqdt[0];
    let dt_0_keys = Object.keys(dt_0);
    let dt_0_id = dt_0_keys[0];
    let dt_id = dt_0[dt_0_id];
    let dt_lenMenu = dt_id.lengthMenu;
    let dt_lenMenu_vals = dt_lenMenu[0];
    let dt_lenMenu_texts = dt_lenMenu[1];

    let dTapi = FLjqdt.api();
    let dTinfo = dTapi.page.info();
    let dt_pag_ = [];
    dt_pag_.push((dTinfo['page']+1).toString());
    dt_pag_.push(dTinfo['pages'].toString());

    let dt_len = dTinfo['length'];
    let dt_len_texti = dt_lenMenu_vals.indexOf(dt_len);
    dt_pag_.push(dt_lenMenu_texts[dt_len_texti]);

    let dt_pag = dt_pag_.toString().replaceAll(',','-');
    return dt_pag;
}
