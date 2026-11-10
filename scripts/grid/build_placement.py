#!/usr/bin/env python3
"""
Cloudberry grid: placement table + tiles.

Reads EXIF DateTimeOriginal from every GoPro JPEG, corrects the camera clock to
Newfoundland Standard Time (UTC-3:30), snaps each frame to its scheduled hourly
slot, keeps the first frame per (day, hour) cell, drops strays and the
2018-02-26 10:00 burst, and writes placement.csv. Optionally renders tiles for
one or more months.

Clock segments (derived from sun-elevation vs frame-brightness fit):
  < 2017-11-14            clock ~ NDT (UTC-2:30)  -> NST = clock - 1:00
  2017-12-01 .. 02-25     clock ~ UTC-6           -> NST = clock + 2:30
  >= 2018-02-26           assumed NDT             -> NST = clock - 1:00

Usage:
  python3 build_placement.py SRC_DIR OUT_DIR [--tiles 2017-10 ...] [--ppi 150]
                             [--tile-w 3.0] [--tile-h 2.25]
"""
import argparse
import csv
import datetime as dt
import os
import sys
from collections import defaultdict

from PIL import Image

ROW_FIRST, ROW_LAST = 6, 22  # NST hour rows kept on the panels

SEGMENTS = [
    # (start inclusive, end exclusive, shift, allowed clock-slot hours)
    (dt.datetime(2017, 1, 1), dt.datetime(2017, 11, 14), dt.timedelta(hours=-1), range(7, 21)),
    (dt.datetime(2017, 12, 1), dt.datetime(2018, 2, 26), dt.timedelta(hours=2, minutes=30), range(7, 21)),
    (dt.datetime(2018, 2, 26), dt.datetime(2019, 1, 1), dt.timedelta(hours=-1), range(7, 24)),
]

BURST_DATE = dt.date(2018, 2, 26)
BURST_CLOCK_HOUR = 10


def read_exif_time(path):
    with Image.open(path) as im:
        ex = im.getexif()
        sub = ex.get_ifd(0x8769)
        raw = sub.get(36867) or ex.get(306)
        size = im.size
    return dt.datetime.strptime(raw, "%Y:%m:%d %H:%M:%S"), size


def segment_for(t):
    for start, end, shift, allowed in SEGMENTS:
        if start <= t < end:
            return shift, allowed
    return None, None


def snap_slot(t):
    """Scheduled hourly slot on the camera clock: :50 or later belongs to the next hour."""
    base = t.replace(minute=0, second=0, microsecond=0)
    return base + dt.timedelta(hours=1) if t.minute >= 50 else base


def build(src):
    cells = {}
    log = defaultdict(int)
    for name in sorted(os.listdir(src)):
        if not name.upper().endswith(".JPG"):
            continue
        clock, size = read_exif_time(os.path.join(src, name))
        if clock.date() == BURST_DATE and clock.hour == BURST_CLOCK_HOUR:
            log["burst_skipped"] += 1
            continue
        shift, allowed = segment_for(clock)
        if shift is None:
            log["outside_segments"] += 1
            continue
        slot = snap_slot(clock)
        if slot.hour not in allowed:
            log["stray_skipped"] += 1
            continue
        nst = slot + shift
        cell_hour = nst.hour  # floor: a 12:30 frame sits in the 12:00 row
        if not (ROW_FIRST <= cell_hour <= ROW_LAST):
            log["outside_rows"] += 1
            continue
        key = (nst.date(), cell_hour)
        true_nst = clock + shift
        prev = cells.get(key)
        if prev is None or clock < prev["clock"]:
            if prev is not None:
                log["duplicate_dropped"] += 1
            cells[key] = dict(file=name, clock=clock, nst=true_nst, w=size[0], h=size[1])
        else:
            log["duplicate_dropped"] += 1
    return cells, log


def write_csv(cells, out_dir, name="placement.csv"):
    path = os.path.join(out_dir, name)
    with open(path, "w", newline="") as fh:
        w = csv.writer(fh)
        w.writerow(["month", "day", "hour_row", "file", "clock_exif", "nst_corrected", "src_w", "src_h"])
        for (d, h) in sorted(cells):
            c = cells[(d, h)]
            w.writerow([d.strftime("%Y-%m"), d.day, h, c["file"],
                        c["clock"].isoformat(sep=" "), c["nst"].isoformat(sep=" "), c["w"], c["h"]])
    return path


def render_tiles(cells, src, out_dir, months, ppi, tile_w, tile_h, tile_dir=None, crop_tr=(0, 0), crop_box=None):
    """crop_tr: (right, top) pixels removed in 4000-px-wide units, applied identically to every frame
    so the horizon stays at the same height across the grid. Left and bottom edges are kept."""
    px_w, px_h = round(tile_w * ppi), round(tile_h * ppi)
    n = 0
    for (d, h), c in sorted(cells.items()):
        month = d.strftime("%Y-%m")
        if month not in months:
            continue
        tdir = os.path.join(out_dir, "tiles", tile_dir or f"{ppi}ppi", month)
        os.makedirs(tdir, exist_ok=True)
        dst = os.path.join(tdir, f"{d.isoformat()}_{h:02d}.jpg")
        with Image.open(os.path.join(src, c["file"])) as im:
            im.draft("RGB", (px_w * 2, px_h * 2))
            im = im.convert("RGB")
            if crop_box:
                # fixed box in 4000 x 3000 units, identical for every frame (keeps the horizon put)
                s = im.size[0] / 4000.0
                im = im.crop(tuple(round(v * s) for v in crop_box))
            elif crop_tr[0] or crop_tr[1]:
                s = im.size[0] / 4000.0
                im = im.crop((0, round(crop_tr[1] * s), im.size[0] - round(crop_tr[0] * s), im.size[1]))
            # centre-crop to exact 4:3 in case of odd sources, then resize
            target = tile_w / tile_h
            iw, ih = im.size
            if abs(iw / ih - target) > 1e-3:
                if iw / ih > target:
                    nw = round(ih * target); x0 = (iw - nw) // 2; im = im.crop((x0, 0, x0 + nw, ih))
                else:
                    nh = round(iw / target); y0 = (ih - nh) // 2; im = im.crop((0, y0, iw, y0 + nh))
            im = im.resize((px_w, px_h), Image.LANCZOS)
            im.save(dst, "JPEG", quality=92, dpi=(ppi, ppi), subsampling=0)
        n += 1
    return n


def main():
    global SEGMENTS, ROW_FIRST, ROW_LAST
    ap = argparse.ArgumentParser()
    ap.add_argument("src")
    ap.add_argument("out")
    ap.add_argument("--tiles", nargs="*", default=[])
    ap.add_argument("--ppi", type=int, default=150)
    ap.add_argument("--tile-w", type=float, default=3.0)
    ap.add_argument("--tile-h", type=float, default=2.25)
    ap.add_argument("--clock", action="store_true",
                    help="use the camera clock as recorded (no time-zone correction)")
    ap.add_argument("--rows", type=int, nargs=2, metavar=("FIRST", "LAST"),
                    help="hour rows to keep, inclusive (default 6 22)")
    ap.add_argument("--name", default="placement.csv", help="output CSV file name")
    ap.add_argument("--tile-dir", default=None, help="tile subfolder name (default '<ppi>ppi')")
    ap.add_argument("--crop-box", type=int, nargs=4, default=None, metavar=("X0", "Y0", "X1", "Y1"),
                    help="fixed crop box in 4000x3000 units applied to every frame (overrides --crop-tr); keep 4:3")
    ap.add_argument("--crop-tr", type=int, nargs=2, default=[0, 0], metavar=("RIGHT", "TOP"),
                    help="pixels (4000-px units) cropped from the right and top of every frame; keep 4:3 (top = 0.75 x right)")
    a = ap.parse_args()

    if a.clock:
        SEGMENTS = [(s, e, dt.timedelta(0), allowed) for s, e, _shift, allowed in SEGMENTS]
    if a.rows:
        ROW_FIRST, ROW_LAST = a.rows

    os.makedirs(a.out, exist_ok=True)
    cells, log = build(a.src)
    path = write_csv(cells, a.out, a.name)
    per_month = defaultdict(int)
    for (d, _h) in cells:
        per_month[d.strftime("%Y-%m")] += 1
    print("placement:", path, "cells:", len(cells))
    print("per month:", dict(sorted(per_month.items())))
    print("log:", dict(log))
    if a.tiles:
        n = render_tiles(cells, a.src, a.out, set(a.tiles), a.ppi, a.tile_w, a.tile_h, a.tile_dir, tuple(a.crop_tr), a.crop_box)
        print("tiles written:", n)


if __name__ == "__main__":
    sys.exit(main())
