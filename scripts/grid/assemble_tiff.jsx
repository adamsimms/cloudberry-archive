// Photoshop: stack the TIFF bands from render_bands.sh into one image, convert to Adobe RGB (1998),
// and save an 8-bit LZW TIFF at the given resolution. Set $.global.CB_BANDS (folder), CB_OUT (tif path)
// and CB_PPI before running. Opens and closes only its own documents.
#target photoshop

(function () {
    var log = File(((typeof CB_ROOT !== "undefined") ? CB_ROOT : Folder("~/Desktop/Cloudberry-grid").fsName) + "/build_log.txt");
    function L(m) { log.open("a"); log.writeln("PS " + new Date().toTimeString().substr(0, 8) + " " + m); log.close(); }
    var prevD = app.displayDialogs, prevU = app.preferences.rulerUnits;
    app.displayDialogs = DialogModes.NO;
    app.preferences.rulerUnits = Units.PIXELS;
    var res = "";
    try {
        var files = Folder(CB_BANDS).getFiles("band_*.tif");
        files.sort(function (a, b) { return a.name < b.name ? -1 : 1; });
        // first pass: sizes
        var W = 0, H = 0, hs = [];
        for (var i = 0; i < files.length; i++) {
            var b = app.open(files[i]);
            W = b.width.as("px"); hs.push(b.height.as("px")); H += hs[i];
            b.close(SaveOptions.DONOTSAVECHANGES);
        }
        var doc = app.documents.add(W, H, CB_PPI, "assembled", NewDocumentMode.RGB, DocumentFill.WHITE, 1,
            BitsPerChannelType.EIGHT, "sRGB IEC61966-2.1");
        var y = 0;
        for (var k = 0; k < files.length; k++) {
            var bd = app.open(files[k]);
            var lay = bd.artLayers[0].duplicate(doc, ElementPlacement.PLACEATBEGINNING);
            bd.close(SaveOptions.DONOTSAVECHANGES);
            app.activeDocument = doc;
            var bb = lay.bounds;
            lay.translate(0 - bb[0].as("px"), y - bb[1].as("px"));
            y += hs[k];
        }
        doc.flatten();
        doc.convertProfile("Adobe RGB (1998)", Intent.RELATIVECOLORIMETRIC, true, false);
        var t = new TiffSaveOptions();
        t.imageCompression = TIFFEncoding.TIFFLZW;
        t.layers = false;
        t.embedColorProfile = true;
        t.byteOrder = ByteOrder.MACOS;
        doc.saveAs(File(CB_OUT), t, true);
        res = "ASSEMBLED " + W + "x" + H + " @" + CB_PPI + " " + doc.colorProfileName + " -> " + File(CB_OUT).name;
        // quick-look JPEG for checking
        doc.resizeImage(UnitValue(1200, "px"), null, null, ResampleMethod.BICUBIC);
        var j = new JPEGSaveOptions(); j.quality = 8;
        doc.saveAs(File(String(CB_OUT).replace(/\.tif$/, "_preview.jpg")), j, true);
        doc.close(SaveOptions.DONOTSAVECHANGES);
    } catch (e) {
        res = "ERR " + e.message + " line " + e.line;
    }
    app.displayDialogs = prevD;
    app.preferences.rulerUnits = prevU;
    L(res);
    return res;
})();
