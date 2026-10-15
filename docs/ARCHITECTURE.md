# Architecture — Cloudberry (art assemble)

Canonical presentation: [art.adamsimms.xyz/cloudberry/archive](https://art.adamsimms.xyz/cloudberry/archive/).

```
Browser → art.adamsimms.xyz (Cloudflare Pages)
            ├── /cloudberry/archive/   ← static HTML/JS/CSS + catalog.json (from this repo)
            ├── R2 cloudberry-images|thumbs
            └── /maps/*               ← also assembled / authored on art
```

`pinchards.is` is a **redirect Worker** onto those art paths (citations / bookmarks).

## Media

Photos are **not** in git. Full and thumbnail JPEGs live in R2, public hostnames:

- `cloudberry-images.adamsimms.xyz`
- `cloudberry-thumbs.adamsimms.xyz`

## Catalog and viewer

1. **Catalog** — builders under `scripts/` produce the catalog JSON / nest used by the static viewer.
2. **Static archive** — builders under `scripts/` write `dist-archive/` for art assemble.
3. **Viewer** — `js/viewer.js` reads the catalog (`catalogUrl` / `basePath`) on the art host; deep links use `?filename=`.

Capture-date and weather caches under `images/photo/.cache/` feed the builder where still used.

## Surfaces (as published on art)

| Surface | Path on art |
|---------|-------------|
| Viewer | `/cloudberry/archive/` |
| Gallery | `/cloudberry/archive/gallery/` |
| About | `/cloudberry/archive/info/` |
| Jam / kiosk | `/cloudberry/archive/jam/` |
| Maps | `/maps`, `/maps/trees`, `/maps/resettled` |

## Shipping

Art CI checks out this repo, builds the static archive, copies into Pages `dist`, deploys. A push that changes the archive also pushes a commit to the art repo, and that starts the same workflow. Details: art [CLOUDBERRY-ASSEMBLE.md](https://github.com/adamsimms/art.adamsimms.xyz/blob/main/docs/CLOUDBERRY-ASSEMBLE.md).
