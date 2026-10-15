# Shipping — archive → art.adamsimms.xyz

This repo does **not** publish a live document root. Production is Cloudflare Pages on **art.adamsimms.xyz**.

## Path

1. Change catalog assets / viewer / builders in this repo.
2. Push to `main` (or open a PR; CI lints).
3. `.github/workflows/trigger-art-rebuild.yml` pushes a commit to `adamsimms/art.adamsimms.xyz` with the `ART_DEPLOY_KEY` deploy key.
4. That push starts the art workflow: checkout → build static archive → assemble → `wrangler pages deploy`.

Manual art rebuild:

```bash
gh workflow run deploy.yml -R adamsimms/art.adamsimms.xyz
```

## Local verify

From a sibling checkout of art:

```bash
PINCHARDS_REPO_PATH=/path/to/pinchards.is npm run build:full
```

## Retired

Emergency-only `workflow_dispatch` under `.github/workflows/deploy.yml` is unused for production. Do not re-enable automatic publish to a shared host.
