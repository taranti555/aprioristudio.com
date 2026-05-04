# aprioristudio.com

Source for the [Apriori Studio](https://aprioristudio.com) website — a UK software studio building web and mobile apps end-to-end.

## Stack

- [Astro 6](https://astro.build/) — static site generator
- [Tailwind CSS 4](https://tailwindcss.com/) — utility-first styling (Vite plugin)
- [@astrojs/sitemap](https://docs.astro.build/en/guides/integrations-guide/sitemap/) — automatic `sitemap.xml`
- PHP 8.2 (single file, FPM) — contact form endpoint
- MySQL 8 — submission storage
- nginx — static + PHP routing

## Local development

```bash
npm install
npm run dev          # serves at http://localhost:4321
```

For the contact form to work locally you need a running MySQL with the schema in `agent/PLAN.md` and the env vars in `.env.example`.

## Build

```bash
npm run build        # outputs to ./dist
```

The `dist/` folder is what gets deployed to the web server.

## Project layout

- `src/pages/` — file-based routing, 14 pages
- `src/layouts/` — `BaseLayout` (every page) and `ServiceLayout` (service subpages)
- `src/components/` — `Nav`, `Footer`, `Breadcrumbs`, `ServiceCard`, `ContactForm`, `JsonLd`
- `src/lib/site.ts` — sitewide config (name, nav, service catalogue)
- `src/styles/global.css` — Tailwind + design tokens
- `public/` — static assets (favicon, og-image, robots.txt, manifest)
- `api/contact.php` — form endpoint (deployed to `/var/www/.../api/contact.php`)
- `agent/PLAN.md` — architecture, deploy and content plan

## Deployment

Deployment is documented in `agent/PLAN.md`, section 8. Short version:

```bash
# On the production server
cd /var/www/aprioristudio.com
git pull
npm ci
npm run build
sudo systemctl reload nginx
```

nginx serves `dist/` as the document root, with `location /api/contact` mapped to `api/contact.php` via php-fpm.

## Licence

All rights reserved. This is the source of a working website, not an open-source project.
