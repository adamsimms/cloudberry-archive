// Cloudberry grid: build every month panel in the current format.
// Portrait, camera clock 08-20h, 4 x 3" tiles (cropped 240 px right / 180 px top to remove the lens-hood
// arc), balanced margins (2.875" above grid, between grid and title, below title -> 60 x ~108"),
// hours under the grid only, days top-aligned, 48 pt Inter SemiBold "YYYY-MM" title, gap tint 40%.
// Fonts are installed system-wide, so both phases run in one session. Progress goes to build_log.txt.
#target indesign

(function () {
    var ROOT = (typeof CB_ROOT !== "undefined") ? CB_ROOT : Folder("~/Desktop/Cloudberry-grid").fsName;
    var HERE = File($.fileName).parent.fsName;
    var MONTHS = (typeof CB_MONTHS !== "undefined") ? CB_MONTHS :
        ["2017-08", "2017-09", "2017-10", "2017-11", "2017-12", "2018-01", "2018-02", "2018-03"];
    var g = $.global;
    g.CB_ROOT = ROOT;
    g.CB_ORIENT = "portrait";
    g.CB_PAGE = [60, 108];              // height is recomputed by the balanced layout
    g.CB_TILE = [4.0, 3.0];
    g.CB_HOURS = [8, 20];
    g.CB_SUFFIX = "60x108-balanced";
    g.CB_CSV = "placement-clock-08-20.csv";
    g.CB_TILEDIR = "150ppi-clock-4.0-crop";
    g.CB_GAP_TINT = 40;
    g.CB_LABEL_STYLE = "timestamp";
    g.CB_LAYOUT = "balanced";
    g.CB_TITLE_PT = 48;

    var out = [];
    for (var i = 0; i < MONTHS.length; i++) {
        g.CB_MONTH = MONTHS[i];
        g.CB_PHASE = 1;
        out.push($.evalFile(File(HERE + "/build_month.jsx")));
        g.CB_PHASE = 2;
        out.push($.evalFile(File(HERE + "/build_month.jsx")));
    }
    var log = File(ROOT + "/build_log.txt");
    log.open("a"); log.writeln("RUN_ALL DONE " + MONTHS.join(",")); log.close();
    return out.join("\n");
})();
