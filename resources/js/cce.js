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
    let elem = document.getElementById("prepInfo");
    elem.setAttribute("style", "display:none");;
}