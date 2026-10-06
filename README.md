# Run On Console (ROC)

Source for [runonconsole.com](https://runonconsole.com), a gaming hardware and
game-compatibility site: product discovery, "can I run it" compatibility checks,
blogs, user accounts, and the ROC Agent assistant.

## Project layout

| Path | What it is |
| :--- | :--- |
| `src/` | React 18 frontend (Vite + Tailwind CSS) |
| `public/` | Static assets copied into the build, plus the PHP API (`public/api/v1/`), CMS (`public/cms/`), SQL migrations and sitemaps |
| `scripts/` | Build-time generators (prerender, sitemap, `llms.txt`, release manifest), verification scripts and release-ZIP helpers |
| `django_backend/` | Parallel Django backend for `/api/v2/` (see its `docs/`) |
| `ROC-Production/` | Production CMS attachment package (see `ROC-Production/DEPLOY.md`) |
| `runonconsole-account-repair/` | Account-flow repair source overlay (see its README) |

## Local development

Requires Node.js and npm.

```bash
npm ci          # install dependencies from package-lock.json
npm run dev     # start the Vite dev server on http://localhost:3000
```

### npm scripts

| Script | Purpose |
| :--- | :--- |
| `npm run dev` | Vite dev server (port 3000) |
| `npm run build` | Production build into `dist/`, then generates `llms.txt`, prerendered pages, sitemaps and the release manifest |
| `npm run preview` | Serve the built `dist/` locally |
| `npm run verify-seo` | Check the build's SEO output |

## Configuration

Never commit real credentials. Example files show the expected variables:

- `.env.example` / `env.example.php`: PHP API database and SMTP settings. Real
  values belong in a private file **outside** `public_html` on the server.
- `django_backend/.env.example`: Django backend settings.

## Further documentation

- [`DEPLOYMENT-README.txt`](DEPLOYMENT-README.txt): production deployment, schema migration and rollback
- [`ROC_AGENT_README.md`](ROC_AGENT_README.md): ROC Agent architecture and CLI commands
- [`ACCOUNT-REPAIR-README.md`](ACCOUNT-REPAIR-README.md): account repair overlay
- [`ROC-Production/DEPLOY.md`](ROC-Production/DEPLOY.md): attaching the CMS to production
- [`django_backend/docs/`](django_backend/docs/): Django backend deployment, data migration and cutover
