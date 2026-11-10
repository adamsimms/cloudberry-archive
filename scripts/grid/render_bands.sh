#!/bin/bash
# Render a one-page PDF to horizontal TIFF bands with Poppler (a full page at print size is over
# Poppler's 2 GB single-bitmap limit). Bands are re-assembled in Photoshop by assemble_tiff.jsx.
# Usage: render_bands.sh <pdf> <ppi> <band_dir>
set -u
PDF="$1"; PPI="$2"; OUT="$3"
PDFTOPPM=/opt/homebrew/bin/pdftoppm
B=4000
mkdir -p "$OUT"
rm -f "$OUT"/band_*.tif "$OUT"/log.txt
# page width in px from pdfinfo (points -> px)
W=$(/opt/homebrew/bin/pdfinfo "$PDF" | awk '/Page size/ {printf "%d", ($3/72*'"$PPI"')+0.5}')
k=0; y=0
while :; do
    name=$(printf "band_%02d" $k)
    "$PDFTOPPM" -r "$PPI" -x 0 -y $y -W "$W" -H $B -tiff -tiffcompression lzw -singlefile \
        -aa yes -aaVector yes "$PDF" "$OUT/$name" >> "$OUT/log.txt" 2>&1
    [ -f "$OUT/$name.tif" ] || break
    h=$(sips -g pixelHeight "$OUT/$name.tif" | awk '/pixelHeight/ {print $2}')
    echo "$name y=$y h=$h w=$W" >> "$OUT/log.txt"
    [ "$h" -lt $B ] && break
    k=$((k+1)); y=$((y+B))
done
echo ALLDONE >> "$OUT/log.txt"
