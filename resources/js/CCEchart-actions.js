/**
 * webtrees - clippings cart enhanced
 *
 * Copyright (C) 2025 huhwt. All rights reserved.
 *
 */

function CCEprepEvents_chart(mElem, _wt_class_to_grep, _wt_data_to_grep) {
    let boolWp = "";
    let boolWs = "";
    let boolWc = "";
    let boolWa = "";
    let listType    = mElem.getAttribute('listType');
    let clipAction  = mElem.getAttribute('clipAction');
    let dt_id       = mElem.getAttribute('dt_id');
    let CCE_key     = mElem.getAttribute('action-key');
    if (CCE_key.endsWith('wp'))
        boolWp = "yes";
    if (CCE_key.endsWith('ws'))
        boolWs = "yes";
    if (CCE_key.endsWith('wc'))
        boolWc = "yes";
    if (CCE_key.endsWith('wa'))
        boolWa = "yes";
    mElem.addEventListener("click", (event) => {
        CCEexecRequest_chart(event, boolWp, listType, clipAction, boolWs, boolWc, boolWa, dt_id, CCE_key, _wt_class_to_grep, _wt_data_to_grep);
        });
}

function CCEexecRequest_chart(event, _boolWp, _listType, _clipAction, _boolWs, _boolWc, _boolWa, _dt_ind, _CCE_key, _wt_class_to_grep, _wt_data_to_grep) {
    let elem = event.target;
    let actURL = window.location.href;

    let fXREF = "_";                                                // first XREF in table-row
    let XREFs = [];                                                 // array of XREFs für update
    let dt = document.getElementsByClassName(_wt_class_to_grep);    // the top-level-elements carrying the information we want to get

    for (let i = 0; i < dt.length; i++) {
        let _Fe     = dtb_Fs[i].getAttribute(_wt_data_to_grep);
        let XREF    = decodeURIComponent(_Fe);
        if (XREF != fXREF) {
            if ( XREFs.indexOf(XREF) < 0 ) {
                fXREF   = XREF;
                XREFs.push(XREF);
            }
        }
    }
//    console.log("XREFs", XREFs.toString());
    let _url = decodeURIComponent(elem.dataset.url);
    if (_url.includes("&amp;")) {
        _url = _url.replace("&amp;","&");
    }
    _url = _url + "&action=" + _clipAction + "&boolWp=" + encodeURIComponent(_boolWp)
                + "&boolWs=" + encodeURIComponent(_boolWs) + "&boolWc=" + encodeURIComponent(_boolWc) + "&boolWa=" + encodeURIComponent(_boolWa) 
                + "&CCEkey=" + encodeURIComponent(_CCE_key);
    $.ajax({
        url: _url,
        dataType: "json",
        data: "xrefs=" + XREFs.join(";"),
        success: function (ret) {
            var _ret = ret;
            CCEupdateCount(_ret);
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
