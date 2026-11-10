// Cloudberry grid: build one month panel in InDesign from placement.csv.
// Portrait (CB_ORIENT="portrait", 60x80): rows = days 1..31, columns = NST hours 06..22.
// Landscape (CB_ORIENT="landscape", 105x48): columns = days, rows = hours.
// Two phases (fonts in "Document fonts" only activate on a fresh open):
//   var CB_PHASE=1; var CB_ORIENT="landscape"; $.evalFile(...)   then   CB_PHASE=2
// Run: InDesign > Scripts panel, or via AppleScript `do script` (language javascript).
// CB_ROOT is the working folder (placement CSVs, tiles/, proofs/, print/, seed .indd); InDesign does not
// see shell environment variables, so set it as a global. Default: ~/Desktop/Cloudberry-grid.
// Fonts (install before running): Inter 4.001 and Google Sans Flex 5.000, both SIL OFL 1.1; the 2026-10
// print used Inter Regular and SemiBold. Copies on Drive: 30 STUDIO/37 RESOURCES/FONTS. PT Mono (macOS
// system font) is the label fallback.

#target indesign

var __ROOT = (typeof CB_ROOT !== "undefined") ? CB_ROOT : Folder("~/Desktop/Cloudberry-grid").fsName;
var __LOG = File(__ROOT + "/build_log.txt");
function __log(m){ __LOG.open("a"); __LOG.writeln(new Date().toTimeString().substr(0,8)+" "+m); __LOG.close(); }
__log("start");
var __result = "";
try { __result = (function () {
    // ---------- configuration ----------
    var ROOT = __ROOT;
    var MONTH = (typeof CB_MONTH !== "undefined") ? CB_MONTH : "2017-10";   // yyyy-mm
    var MONTH_NAMES = ["JANUARY","FEBRUARY","MARCH","APRIL","MAY","JUNE","JULY","AUGUST","SEPTEMBER","OCTOBER","NOVEMBER","DECEMBER"];
    var MONTH_LABEL = MONTH_NAMES[parseInt(MONTH.split("-")[1], 10) - 1] + " " + MONTH.split("-")[0];
    var PPI_DIR = "150ppi";

    // orientation: "portrait" = days down, hours across (60 x 80)
    //              "landscape" = days across, hours down (105 x 48)
    var ORIENT = (typeof CB_ORIENT !== "undefined") ? CB_ORIENT : "portrait";
    var LANDSCAPE = (ORIENT === "landscape");
    var PAGE_W = LANDSCAPE ? 105 : 60;  // inches
    var PAGE_H = LANDSCAPE ? 48 : 80;
    var SUFFIX = LANDSCAPE ? "105x48" : "60x80";
    var TILE_W = 3.0, TILE_H = 2.25;    // inches, 4:3
    var GAP = 0.1875;                   // 3/16"
    var HOUR_FIRST = 6, HOUR_LAST = 22;
    var CSV_NAME = "placement.csv";
    // optional overrides set before $.evalFile
    if (typeof CB_PAGE !== "undefined") { PAGE_W = CB_PAGE[0]; PAGE_H = CB_PAGE[1]; }
    if (typeof CB_TILE !== "undefined") { TILE_W = CB_TILE[0]; TILE_H = CB_TILE[1]; }
    if (typeof CB_HOURS !== "undefined") { HOUR_FIRST = CB_HOURS[0]; HOUR_LAST = CB_HOURS[1]; }
    if (typeof CB_SUFFIX !== "undefined") SUFFIX = CB_SUFFIX;
    if (typeof CB_CSV !== "undefined") CSV_NAME = CB_CSV;
    if (typeof CB_TILEDIR !== "undefined") PPI_DIR = CB_TILEDIR;
    var DAYS = 31;                      // always 31; short months leave empty days

    var LABEL_PT = 12;
    var TITLE_PT = 24;
    var GAP_CMYK = [0, 2, 6, 6];        // faint warm grey
    var GAP_TINT = 100;                 // percent tint of the gap colour
    var LABEL_STYLE = "calendar";       // "calendar" (OCTOBER 2017 / 1 / 08) or "timestamp" (2017-10 / 01 / 08:00)
    var GRID_TOP = null;                // inches from page top to grid; null = centred
    if (typeof CB_GAP_TINT !== "undefined") GAP_TINT = CB_GAP_TINT;
    if (typeof CB_LABEL_STYLE !== "undefined") LABEL_STYLE = CB_LABEL_STYLE;
    if (typeof CB_GRID_TOP !== "undefined") GRID_TOP = CB_GRID_TOP;
    var TIMESTAMP = (LABEL_STYLE === "timestamp");
    // "balanced": one margin M (= side margin) above the grid, between grid and title, and below the title;
    // page height is derived. Hours flush-left to their column, days on the tile's bottom edge.
    var BALANCED = (typeof CB_LAYOUT !== "undefined" && CB_LAYOUT === "balanced");
    var CAP = 0.7275;                   // Inter cap height, em
    var LABEL_OFFSET = 0.25;            // label baseline/cap distance from the grid, inches
    if (typeof CB_LABEL_OFFSET !== "undefined") LABEL_OFFSET = CB_LABEL_OFFSET;
    if (typeof CB_LABEL_PT !== "undefined") LABEL_PT = CB_LABEL_PT;
    if (typeof CB_GAP !== "undefined") GAP = CB_GAP;
    if (BALANCED && typeof CB_TITLE_PT !== "undefined") TITLE_PT = CB_TITLE_PT;

    // ---------- derived geometry ----------
    var HOURS = HOUR_LAST - HOUR_FIRST + 1;
    var COLS = LANDSCAPE ? DAYS : HOURS;
    var ROWS = LANDSCAPE ? HOURS : DAYS;
    var GRID_W = COLS * TILE_W + (COLS - 1) * GAP;
    var GRID_H = ROWS * TILE_H + (ROWS - 1) * GAP;
    var X0 = (PAGE_W - GRID_W) / 2;
    var MARGIN = X0;
    var TITLE_CAP_IN = CAP * TITLE_PT / 72;
    if (BALANCED) {
        PAGE_H = Math.round((3 * MARGIN + GRID_H + TITLE_CAP_IN) * 1000) / 1000;
        GRID_TOP = MARGIN;
    }
    var Y0 = (GRID_TOP !== null) ? GRID_TOP : (PAGE_H - GRID_H) / 2;

    function cellBounds(day, hour) {
        var c = LANDSCAPE ? day - 1 : hour - HOUR_FIRST;
        var r = LANDSCAPE ? hour - HOUR_FIRST : day - 1;
        var x = X0 + c * (TILE_W + GAP);
        var y = Y0 + r * (TILE_H + GAP);
        return [y, x, y + TILE_H, x + TILE_W]; // [top, left, bottom, right]
    }

    // ---------- read placement ----------
    var csv = File(ROOT + "/" + CSV_NAME);
    if (!csv.open("r")) { return "ERROR: cannot open placement.csv"; }
    var header = csv.readln().split(",");
    var iMonth = 0, iDay = 1, iHour = 2;
    var filled = {};
    var count = 0;
    while (!csv.eof) {
        var line = csv.readln();
        if (!line) continue;
        var f = line.split(",");
        if (f[iMonth] !== MONTH) continue;
        var d = parseInt(f[iDay], 10), h = parseInt(f[iHour], 10);
        if (h < HOUR_FIRST || h > HOUR_LAST) continue;
        filled[d + "_" + h] = true;
        count++;
    }
    csv.close();

    function pad2(n) { return (n < 10 ? "0" : "") + n; }
    var daysInMonth = (function () {
        var p = MONTH.split("-");
        return new Date(parseInt(p[0], 10), parseInt(p[1], 10), 0).getDate();
    })();

    var PHASE = (typeof CB_PHASE !== "undefined") ? CB_PHASE : 1;
    var outIndd = File(ROOT + "/cloudberry-" + MONTH + "-" + SUFFIX + ".indd");
    function pad2b(n){ return (n < 10 ? "0" : "") + n; }
    if (PHASE === 1) {
    // ---------- document ----------
    app.scriptPreferences.userInteractionLevel = UserInteractionLevels.NEVER_INTERACT;
    app.scriptPreferences.enableRedraw = false;

    for (var di = app.documents.length - 1; di >= 0; di--) {
        try { if (String(app.documents[di].name).indexOf("cloudberry-" + MONTH + "-" + SUFFIX) === 0) app.documents[di].close(SaveOptions.NO); } catch (e) {}
    }
    var doc = app.documents.add(false);
    with (doc.viewPreferences) {
        horizontalMeasurementUnits = MeasurementUnits.INCHES;
        verticalMeasurementUnits = MeasurementUnits.INCHES;
        rulerOrigin = RulerOrigin.PAGE_ORIGIN;
    }
    with (doc.documentPreferences) {
        facingPages = false;
        pagesPerDocument = 1;
        pageWidth = PAGE_W;
        pageHeight = PAGE_H;
        documentBleedTopOffset = 0;
        documentBleedBottomOffset = 0;
        documentBleedInsideOrLeftOffset = 0;
        documentBleedOutsideOrRightOffset = 0;
    }
    var page = doc.pages[0];
    page.marginPreferences.properties = { top: 0, bottom: 0, left: 0, right: 0 };
    doc.zeroPoint = [0, 0];

    // layers (bottom to top): Gaps, Images, Labels
    var lyr0 = doc.layers[0];
    lyr0.name = "Labels";
    var lyrImages = doc.layers.add({ name: "Images" });
    var lyrGaps = doc.layers.add({ name: "Gaps" });
    lyrImages.move(LocationOptions.AFTER, lyr0);
    lyrGaps.move(LocationOptions.AFTER, lyrImages);
    var lyrLabels = lyr0;

    var gapSwatch = doc.colors.add({
        name: "Gap warm grey",
        model: ColorModel.PROCESS,
        space: ColorSpace.CMYK,
        colorValue: GAP_CMYK
    });

    // ---------- gaps + images ----------
    var placed = 0, gaps = 0, missing = [];
    for (var day = 1; day <= DAYS; day++) {
        for (var hour = HOUR_FIRST; hour <= HOUR_LAST; hour++) {
            var b = cellBounds(day, hour);
            if (day <= daysInMonth && filled[day + "_" + hour]) {
                var path = ROOT + "/tiles/" + PPI_DIR + "/" + MONTH + "/" + MONTH + "-" + pad2(day) + "_" + pad2(hour) + ".jpg";
                var img = File(path);
                if (!img.exists) { missing.push(path); continue; }
                var rect = page.rectangles.add(lyrImages, { geometricBounds: b, strokeWeight: 0, strokeColor: doc.swatches.itemByName("None"), fillColor: doc.swatches.itemByName("None") });
                rect.place(img);
                rect.fit(FitOptions.FILL_PROPORTIONALLY);
                rect.fit(FitOptions.CENTER_CONTENT);
                rect.name = MONTH + "-" + pad2(day) + " " + pad2(hour) + ":00";
                placed++;
            } else if (day <= daysInMonth) {
                page.rectangles.add(lyrGaps, { geometricBounds: b, fillColor: gapSwatch, fillTint: GAP_TINT, strokeWeight: 0, strokeColor: doc.swatches.itemByName("None") });
                gaps++;
            }
        }
    }

    // ---------- font seed: InDesign only activates Document fonts a document already references,
    // so copy one Inter text frame in from the reference panel (removed again in phase 2) ----------
    var seedSrc = File(ROOT + "/cloudberry-2017-10-60x80.indd");
    if (seedSrc.exists && seedSrc.fsName !== outIndd.fsName) {
        var sd = app.open(seedSrc, false);
        try {
            var sf = sd.pages[0].textFrames[0].duplicate(page);
            sf.label = "font-seed";
            sf.itemLayer = lyrLabels;
        } catch (eSeed) {}
        sd.close(SaveOptions.NO);
    }

    // ---------- phase 1 ends: save + close (fonts activate on a fresh open) ----------
    doc.save(outIndd);
    doc.close(SaveOptions.NO);
    app.scriptPreferences.enableRedraw = true;
    app.scriptPreferences.userInteractionLevel = UserInteractionLevels.INTERACT_WITH_ALL;
    return "phase1 " + MONTH + " | page " + PAGE_W + " x " + PAGE_H + " | cells in csv " + count + " | placed " + placed + " | gaps " + gaps +
        " | missing " + missing.length + " | grid " + GRID_W.toFixed(3) + " x " + GRID_H.toFixed(3) +
        " at " + X0.toFixed(4) + "," + Y0.toFixed(4);
    }

    // ---------- phase 2: labels + PDF ----------
    app.scriptPreferences.userInteractionLevel = UserInteractionLevels.NEVER_INTERACT;
    var doc = app.open(outIndd, false);
    var page = doc.pages[0];
    var lyrLabels = doc.layers.itemByName("Labels");
    // clear previous labels (keep a "font-seed" frame if present: it makes InDesign activate Inter from Document fonts)
    var oldTf = page.textFrames.everyItem().getElements();
    for (var oi = oldTf.length - 1; oi >= 0; oi--) { if (oldTf[oi].label !== "font-seed") oldTf[oi].remove(); }

    var LABEL_FONTS = ["Inter\tRegular", "PT Mono\tRegular"];
    var TITLE_FONTS = ["Google Sans Flex\tMedium", "Inter\tMedium", "Inter\tRegular"]; // fonts installed in ~/Library/Fonts
    var usedFonts = {};
    function applyFont(t, candidates) {
        for (var i = 0; i < candidates.length; i++) {
            try {
                t.appliedFont = candidates[i];
                // status on a just-edited text object is unreliable; check the document's font list
                var df = doc.fonts.itemByName(candidates[i]);
                if (df.isValid && df.status == FontStatus.INSTALLED) { usedFonts[candidates[i].replace("\t", " ")] = true; return; }
            } catch (e) {}
        }
    }
    // labels in RGB black so they stay true black in the RGB print files (K100 converts to dark grey)
    var black = doc.colors.itemByName("Label black");
    if (!black.isValid) black = doc.colors.add({ name: "Label black", model: ColorModel.PROCESS, space: ColorSpace.RGB, colorValue: [0, 0, 0] });

    function addText(bounds, contents, fonts, pt, just, vjust, tracking) {
        var tf = page.textFrames.add(lyrLabels, { geometricBounds: bounds, contents: contents });
        tf.textFramePreferences.verticalJustification = vjust;
        tf.textFramePreferences.insetSpacing = [0, 0, 0, 0];
        var t = tf.texts[0];
        applyFont(t, fonts);
        t.pointSize = pt;
        t.justification = just;
        t.fillColor = black;
        t.tracking = tracking || 0;
        try { t.otfFigureStyle = OTFFigureStyle.TABULAR_LINING; } catch (e) {}
        return tf;
    }

    // column labels above the grid, row labels left of the grid
    function topLabel(b, txt) {
        addText([Y0 - 0.55, b[1], Y0 - 0.18, b[3]], txt, LABEL_FONTS, LABEL_PT,
            Justification.CENTER_ALIGN, VerticalJustification.BOTTOM_ALIGN, 0);
    }
    function sideLabel(b, txt) {
        addText([b[0], X0 - 1.0, b[2], X0 - 0.25], txt, LABEL_FONTS, LABEL_PT,
            Justification.RIGHT_ALIGN, VerticalJustification.CENTER_ALIGN, 0);
    }
    // text whose cap height starts exactly at capTop (baseline = capTop + cap height)
    function addCapText(capTop, left, right, contents, fonts, pt, just) {
        var tf = addText([capTop, left, capTop + Math.max(1, pt / 36), right], contents, fonts, pt, just,
            VerticalJustification.TOP_ALIGN, 0);
        tf.textFramePreferences.firstBaselineOffset = FirstBaseline.CAP_HEIGHT;
        return tf;
    }
    var LABEL_CAP_IN = CAP * LABEL_PT / 72;

    if (BALANCED && !LANDSCAPE) {
        for (var bh = HOUR_FIRST; bh <= HOUR_LAST; bh++) {
            var bb = cellBounds(1, bh);
            var btxt = pad2(bh) + ":00";
            if (typeof CB_HOURS_TOP !== "undefined" && CB_HOURS_TOP) {
                // optional: also above the grid, baseline LABEL_OFFSET above the top row
                addCapText(Y0 - LABEL_OFFSET - LABEL_CAP_IN, bb[1], bb[3], btxt, LABEL_FONTS, LABEL_PT, Justification.LEFT_ALIGN);
            }
            // below the grid: cap top LABEL_OFFSET below the last row
            addCapText(Y0 + GRID_H + LABEL_OFFSET, bb[1], bb[3], btxt, LABEL_FONTS, LABEL_PT, Justification.LEFT_ALIGN);
        }
        for (var bd = 1; bd <= daysInMonth; bd++) {
            var rb2 = cellBounds(bd, HOUR_FIRST);
            // cap top on the tile's top edge (or baseline on the bottom edge with CB_DAYS_ALIGN="bottom")
            var dTop = (typeof CB_DAYS_ALIGN !== "undefined" && CB_DAYS_ALIGN === "bottom") ? rb2[2] - LABEL_CAP_IN : rb2[0];
            addCapText(dTop, X0 - 1.25, X0 - LABEL_OFFSET, pad2(bd), LABEL_FONTS, LABEL_PT, Justification.RIGHT_ALIGN);
        }
        // title: cap top one margin below the grid, flush left with the grid
        var TITLE_FACE = ["Inter\tSemiBold", "Inter\tSemi Bold", "Inter\tMedium", "Inter\tRegular"];
        var ttl = addCapText(Y0 + GRID_H + MARGIN, X0, X0 + 30, MONTH, TITLE_FACE, TITLE_PT, Justification.LEFT_ALIGN);
        ttl.texts[0].otfFigureStyle = OTFFigureStyle.PROPORTIONAL_LINING; // title reads as one word
    } else {
    for (var hh = HOUR_FIRST; hh <= HOUR_LAST; hh++) {
        var hb = cellBounds(1, hh);
        var htxt = TIMESTAMP ? pad2(hh) + ":00" : pad2(hh);
        if (LANDSCAPE) sideLabel(hb, htxt); else topLabel(hb, htxt);
        if (TIMESTAMP && !LANDSCAPE) {
            // repeat hours under the grid so the lower rows can be read
            addText([Y0 + GRID_H + 0.18, hb[1], Y0 + GRID_H + 0.55, hb[3]], htxt, LABEL_FONTS, LABEL_PT,
                Justification.CENTER_ALIGN, VerticalJustification.TOP_ALIGN, 0);
        }
    }
    for (var dd = 1; dd <= DAYS; dd++) {
        var db = cellBounds(dd, HOUR_FIRST);
        var dtxt = dd <= daysInMonth ? (TIMESTAMP ? pad2(dd) : String(dd)) : "";
        if (LANDSCAPE) topLabel(db, dtxt); else sideLabel(db, dtxt);
    }
    // month title, aligned to grid left edge
    if (TIMESTAMP) {
        // month as the camera writes it (2017-10), Inter, bottom-left under the grid
        addText([Y0 + GRID_H + 1.15, X0, Y0 + GRID_H + 1.75, X0 + 20], MONTH, LABEL_FONTS, TITLE_PT,
            Justification.LEFT_ALIGN, VerticalJustification.TOP_ALIGN, 0);
    } else {
        addText([Y0 - 1.45, X0, Y0 - 0.85, X0 + 20], MONTH_LABEL, TITLE_FONTS, TITLE_PT,
            Justification.LEFT_ALIGN, VerticalJustification.BOTTOM_ALIGN, 60);
    }
    } // end non-balanced labels

    var seeds = page.textFrames.everyItem().getElements();
    for (var si = seeds.length - 1; si >= 0; si--) { if (seeds[si].label === "font-seed") seeds[si].remove(); }
    doc.save(outIndd);

    // ---------- PDF proof ----------
    var proofDir = Folder(ROOT + "/proofs");
    if (!proofDir.exists) proofDir.create();
    var PRINT = (typeof CB_EXPORT !== "undefined" && CB_EXPORT === "print");
    var pdf, preset;
    if (PRINT) {
        var printDir = Folder(ROOT + "/print");
        if (!printDir.exists) printDir.create();
        pdf = File(ROOT + "/print/cloudberry-" + MONTH + "-" + SUFFIX + ".pdf");
        preset = app.pdfExportPresets.itemByName("Cloudberry print (no downsampling)");
        if (!preset.isValid) {
            preset = app.pdfExportPresets.itemByName("[PDF/X-4:2008]").duplicate();
            preset.name = "Cloudberry print (no downsampling)";
            preset.colorBitmapSampling = Sampling.NONE;
            preset.grayscaleBitmapSampling = Sampling.NONE;
            preset.colorBitmapCompression = BitmapCompression.ZIP;
            preset.grayscaleBitmapCompression = BitmapCompression.ZIP;
        }
    } else {
        pdf = File(ROOT + "/proofs/cloudberry-" + MONTH + "-" + SUFFIX + "-proof.pdf");
        preset = app.pdfExportPresets.itemByName("[Smallest File Size]");
    }
    doc.exportFile(ExportFormat.PDF_TYPE, pdf, false, preset);
    doc.close(SaveOptions.YES);

    app.scriptPreferences.enableRedraw = true;
    app.scriptPreferences.userInteractionLevel = UserInteractionLevels.INTERACT_WITH_ALL;

    return "phase2 " + MONTH + " | fonts: " + (function(){ var a=[]; for (var k in usedFonts) a.push(k); return a.join(", "); })();
})(); } catch (e) { __result = "ERR line " + e.line + ": " + e.message; try { app.scriptPreferences.enableRedraw = true; app.scriptPreferences.userInteractionLevel = UserInteractionLevels.INTERACT_WITH_ALL; } catch (e2) {} }
__log(__result);
__result;
