# cloudberry-archive

[![CI](https://github.com/adamsimms/cloudberry-archive/actions/workflows/ci.yml/badge.svg)](https://github.com/adamsimms/cloudberry-archive/actions/workflows/ci.yml)

**Canonical site:** [art.adamsimms.xyz/cloudberry/archive](https://art.adamsimms.xyz/cloudberry/archive/)

This repository is the **Cloudberry archive source** — catalog, static HTML/JS/CSS assets, and builders that assemble into the art Pages project. Legacy `pinchards.is` / `www` hostnames still 301 to art until that domain expires.

Cloudberry is a long-running photographic archive of off-the-grid life on Pinchard's Island, Newfoundland.

## Artist statement

The simplicity of the Cloudberry photographs belies the complexity of the solar-powered camera system that makes them possible. A camera affixed to Adam’s family’s cabin on Pinchard’s Island takes the photographs continuously throughout the day and uploads them to the internet via cellular network. Able to control the camera and access the images from anywhere, Adam uses technology to maintain a constant presence in a place that is inaccessible most of the year. In capturing a view that recalls the experience of looking out the cabin’s window, the camera stands in for the photographer. Thus, these photographs constitute a political act: they allow an uninterrupted foothold on a place that was effectively erased when the Canadian government resettled the area shortly after Newfoundland became the country’s 10th province.

Comprised of thousands of photographs, the enormity of the Cloudberry project has inspired Adam to explore a new aspect of the sublime in his work. While other projects have focused on the pursuit of a single majestic photograph, Cloudberry inspires awe through its abundance of quotidian images. The title itself parallels this shift: while it may at first evoke the vast Newfoundland sky, Cloudberry in fact references the indigenous wild berry—colloquially known as a “bakeapple”—that flourishes on the seemingly inhospitable land.

The Cloudberry photographs are at once static and dynamic: though the frame remains exactly the same, the landscape is constantly changing. The enormous variety in the images recalls series of Impressionist paintings that show the same scene in different types of light, in all seasons, and throughout the day. The sky, the ocean, and the terrain in the photographs seem to change more readily than the immutable rocks, but even their appearance eventually transforms as the seasons change. Though Cloudberry highlights the specificity of this particular landscape, it also manifests the familiar desire to connect to one’s homeland not as a relic of the past but as a place that is alive.

## How it ships

1. Builders under `scripts/` emit `dist-archive/` (catalog + static pages).
2. [art.adamsimms.xyz](https://github.com/adamsimms/art.adamsimms.xyz) assembles that tree into `/cloudberry/archive/` on Cloudflare Pages.
3. JPEGs are served from R2 (`cloudberry-images.adamsimms.xyz` / `cloudberry-thumbs.adamsimms.xyz`).
4. A push that changes the archive pushes a commit to the art repo, which starts the Pages deploy.

See art [docs/CLOUDBERRY-ASSEMBLE.md](https://github.com/adamsimms/art.adamsimms.xyz/blob/main/docs/CLOUDBERRY-ASSEMBLE.md) and [docs/PHASE5-CUTOVER.md](https://github.com/adamsimms/art.adamsimms.xyz/blob/main/docs/PHASE5-CUTOVER.md).

## Layout

| Area | Role |
|------|------|
| `js/`, `css/`, `images/` | Viewer, gallery, info, jam assets copied into the static archive |
| `data/` / catalog builders | Photo catalog for the static viewer |
| `scripts/` | Catalog + static archive builders; frontend vendor copy |
| `maps/` | Source for island maps (also published under art `/maps`) |
| `docs/` | Architecture and product notes |

## Local

Node.js 20+ for frontend vendor copies. CI builds the static archive used in production; prefer verifying via an art `build:full` / Pages preview.

```bash
git clone https://github.com/adamsimms/cloudberry-archive.git
cd cloudberry-archive
npm install && npm run vendor:frontend
```

Maps and local tooling may need tokens in a local secrets example file at the repo root — do not commit secrets.

## Docs

- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) — data flow (catalog, R2, art assemble)
- [docs/PRODUCT.md](docs/PRODUCT.md) — closed-archive product boundary
- [docs/SHIPPING.md](docs/SHIPPING.md) — how content reaches art Pages

## Contribute

See [CONTRIBUTING.md](CONTRIBUTING.md).
