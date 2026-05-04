# Apriori Studio — Plan & Architecture

Working brief for the aprioristudio.com website. UK Ltd-fronted software studio (web + mobile apps), built as a real, indexable site with a working contact form. Lives at `/www/aprioristudio.com` on ru128 during development; deploys later to a UK-based VPS via GitHub.

## 1. Goals

- Real working presentation site for **Apriori Studio** (UK Ltd, owned by sister) offering web and mobile app development services.
- SEO-indexable by all major search engines (Google, Bing, Yandex, etc.) — pre-rendered HTML, structured data, sitemap.
- Working contact form: stores submissions in MySQL on ru128, emails copies to `np@aprioristudio.com` and `mp@aprioristudio.com` via mailcow.
- No `.php` extensions in URLs.
- All public-facing assets served from a UK IP (no Russian-hosted subdomains visible to the public).
- Codebase in GitHub: `taranti555/aprioristudio.com`.

## 2. Tech stack

| Layer | Choice | Why |
|---|---|---|
| Frontend framework | **Astro 5+** | Static site generation. Fully indexable HTML at build time. Tiny client JS. Per-page islands if interactivity needed. |
| Styling | **Tailwind CSS** | Fast iteration, no separate CSS files, design system via config. |
| Content | `.astro` pages + Markdown for long-form (services pages) | Mixed prose + components. |
| Backend (form only) | **PHP 8.2 + FPM** (1 file) | Smallest possible runtime. ~80 MB RAM total on the VPS. |
| Database | **MySQL** on ru128 (private) | Single `contact_submissions` table. App user pinned to VPS IP. |
| Web server | **nginx** | Static `dist/` + `location /api/` → php-fpm via fastcgi. URL rewrite hides `.php`. |
| TLS | **Let's Encrypt** (certbot) | Standard. |
| Mail | **mail.pogosov.com** (mailcow on ru48), via SMTP | Existing infra. SMTP from VPS over public internet (port 587 STARTTLS). |
| Deploy | GitHub → `git pull` on VPS + `npm ci && npm run build` | Simple. No CI/CD service. Optional webhook later. |

### Why not Next.js / SSR / Laravel?
The site is informational. SSG gives the best SEO + lowest hosting cost + smallest attack surface. Single PHP file for the form is the minimum-friction backend; pulling in a framework would be overkill.

## 3. Pages

URL structure (no trailing `.html`/`.php` anywhere — Astro handles this; nginx rewrite for `/api/contact`):

```
/                        Home — overview, services, what we do, CTA
/web                     Web development — overview + 3 service categories
/web/ecommerce           E-commerce sites
/web/corporate           Corporate / marketing sites
/web/saas                SaaS & web applications
/apps                    Mobile apps — overview + 4 categories
/apps/marketplace        Marketplace / e-commerce apps
/apps/on-demand          On-demand services (delivery, taxi, courier)
/apps/booking            Booking & reservation
/apps/fitness            Fitness & health
/about                   About Apriori Studio
/contact                 Contact form + details
/privacy                 Privacy policy (UK / GDPR)
/terms                   Terms of use
```

**Total: 14 pages.**

Each page MUST have:
- Unique `<title>` (≤ 60 chars)
- Unique `<meta name="description">` (≤ 160 chars)
- Open Graph + Twitter Card tags
- Minimum **1000 characters** of meaningful body text
- Internal links to related pages
- Breadcrumbs on subpages
- JSON-LD structured data (Organization on every page; Service on service pages; ContactPoint on contact)

### Top services rationale

**Web — top 3 most commonly ordered (UK market 2026):**
1. **E-commerce** — Shopify/WooCommerce/custom storefronts. Highest demand category; UK retail is heavily online.
2. **Corporate / Marketing sites** — small-business sites, presentations, landings. Volume leader by request count.
3. **SaaS / Web Apps** — custom dashboards, B2B tools, internal portals. Higher ticket value.

**Mobile — top 4:**
1. **Marketplace / E-commerce apps** — Amazon-like, Etsy-like, niche.
2. **On-demand services** — delivery, taxi, couriers, professional services.
3. **Booking & reservation** — restaurants, salons, hotels, fitness studios.
4. **Fitness & health** — workouts, tracking, nutrition, telemedicine.

For each: full-cycle pitch — domain advice, hosting setup, UX/UI design, development, third-party integrations (Stripe, analytics, push), App Store / Play Store submission, post-launch support.

## 4. Brand

| Aspect | Value |
|---|---|
| Name | **Apriori Studio** |
| Tagline | "Web & mobile, built from first principles." |
| Origin | UK Ltd (Apriori Ltd), London-based |
| Tone | Professional, warm, plain English. No corporate jargon. Confidence without buzzwords. |
| Languages | English only (this is a UK-fronted site for global clients) |

### Visual identity

- **Colors:**
  - Background: `#FAFAFA` (near-white)
  - Surface: `#FFFFFF`
  - Primary text: `#0F172A` (slate-900)
  - Secondary text: `#475569` (slate-600)
  - Accent / CTA: `#2563EB` (blue-600) — clean, trustworthy, tech
  - Subtle accent: `#F59E0B` (amber-500) for highlights / dividers
  - Borders: `#E2E8F0` (slate-200)
- **Typography:**
  - Headings: `Inter` (or system sans), tight tracking, semibold
  - Body: `Inter`, regular, 16-18 px
  - Code/mono: `JetBrains Mono` if needed
- **Logo:** SVG. Stylized geometric **A** (triangular, derived from "a priori" = first principles) + wordmark "Apriori Studio".
- **Favicon:** 32, 192, 512 px PNGs from the SVG. `apple-touch-icon` 180×180.
- **Photography:** none in v1 (avoids stock-photo clutter; geometric SVG illustrations only).

## 5. Contact form

**Frontend (Astro):**
- Fields: Name (required), Email (required, validated), Subject (optional), Message (required, ≥ 20 chars), Honeypot field (hidden, must stay empty), CSRF-style token (timestamp + HMAC).
- Client-side validation, no JS framework required.
- POST to `/api/contact` (rewritten to `/api/contact.php` by nginx).
- Success → thank-you state inline (no redirect, keeps URL clean).

**Backend (`api/contact.php`):**
- Validate all inputs server-side (re-validate, never trust client).
- Reject if honeypot filled or token expired (anti-spam).
- INSERT into `contact_submissions` table.
- Send email via SMTP (PHPMailer or just `mail()` configured to relay through `mail.pogosov.com:587`):
  - From: `noreply@aprioristudio.com`
  - To: `mp@aprioristudio.com`, `np@aprioristudio.com`
  - Reply-To: submitter's email
- Return JSON `{ok: true}` or `{ok: false, error: "..."}`.

**Database (`contact_submissions`):**
```sql
CREATE TABLE contact_submissions (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  name         VARCHAR(120) NOT NULL,
  email        VARCHAR(190) NOT NULL,
  subject      VARCHAR(200) DEFAULT NULL,
  message      TEXT NOT NULL,
  ip           VARCHAR(45) DEFAULT NULL,
  user_agent   VARCHAR(255) DEFAULT NULL,
  referer      VARCHAR(255) DEFAULT NULL,
  email_sent   TINYINT(1) NOT NULL DEFAULT 0,
  KEY idx_created (created_at),
  KEY idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

MySQL user: `aprioristudio_app` with `SELECT, INSERT, UPDATE` only on this DB. Pinned to the VPS IP.

## 6. SEO

- `sitemap.xml` — generated by `@astrojs/sitemap` (every page auto-listed, `lastmod` from build time).
- `robots.txt` — explicitly allow the top-10 search engine bots, disallow everything else (cuts AI scraper noise, leaves SEO untouched):
  - Allowed user-agents: `Googlebot`, `Bingbot`, `YandexBot`, `DuckDuckBot`, `Slurp` (Yahoo), `Baiduspider`, `Sogou web spider`, `Applebot`, `PetalBot` (Huawei), `Yeti` (Naver).
  - Plus `facebookexternalhit` and `Twitterbot` (so social previews work).
  - Everyone else: `User-agent: * / Disallow: /`.
- JSON-LD on every page:
  - **Organization** (sitewide): name, URL, logo, address (UK), `contactPoint`, `sameAs` (any social later).
  - **Service** (on each service page).
  - **BreadcrumbList** (on subpages).
- Open Graph + Twitter Card on every page.
- Internal linking: nav, footer, related-services blocks at the bottom of each service page.
- HTTPS (Let's Encrypt) with HTTP→HTTPS redirect.
- Canonical URLs.

## 7. Mailcow integration (already done)

| Address | Purpose | Forward |
|---|---|---|
| `mp@aprioristudio.com` | Mikhail | → `mpogossov@gmail.com` (BCC copy) |
| `np@aprioristudio.com` | Nina | → `npogossova@gmail.com` (BCC copy) |
| `noreply@aprioristudio.com` | Outgoing system mail | none |

DNS: MX → `mail.pogosov.com`, SPF/DKIM/DMARC set on GoDaddy.

## 8. Deployment (deferred — pending VPS decision)

Once a VPS is chosen (likely fresh UK VPS, 2–4 GB RAM):

1. `apt install nginx php8.2-fpm php8.2-mysql php8.2-curl certbot python3-certbot-nginx git`
2. Clone repo: `git clone git@github.com:taranti555/aprioristudio.com.git /var/www/aprioristudio.com`
3. `npm ci && npm run build` (or build on ru128 and rsync `dist/`)
4. nginx vhost (root = `dist/`; `location /api/contact` → `try_files $uri /api/contact.php`)
5. `certbot --nginx -d aprioristudio.com -d www.aprioristudio.com`
6. MySQL: open port 3306 on ru128 to the VPS public IP only (nft/ufw), or set up SSH tunnel / Tailscale.
7. `deploy.sh` = `cd /var/www/aprioristudio.com && git pull && npm ci && npm run build && sudo systemctl reload nginx`

Local development on ru128: `npm run dev` (Astro dev server on :4321). PHP form is wired up via `npm run preview` + a tiny test PHP-CLI runner OR via the existing nginx + php-fpm on ru128 with a temp vhost.

## 9. Repository layout

```
/www/aprioristudio.com/
├── agent/                    # planning + docs (git-ignored OR included)
│   └── PLAN.md               # this file
├── public/                   # static assets
│   ├── favicon.svg
│   ├── favicon-32.png
│   ├── favicon-192.png
│   ├── favicon-512.png
│   ├── apple-touch-icon.png
│   ├── og-default.jpg
│   ├── robots.txt
│   └── logo.svg
├── src/
│   ├── components/           # Layout, Nav, Footer, Hero, Card, JsonLd, ContactForm
│   ├── layouts/              # BaseLayout.astro, ServiceLayout.astro
│   ├── pages/                # 14 .astro pages (file-based routing)
│   ├── styles/               # global.css (Tailwind directives)
│   └── lib/                  # site config, structured data helpers
├── api/
│   └── contact.php           # form endpoint
├── astro.config.mjs
├── tailwind.config.mjs
├── package.json
├── tsconfig.json
├── deploy.sh                 # runs on VPS
├── .gitignore
├── .env.example              # DB/SMTP placeholders
└── README.md
```

## 10. Status / TODO

Tracked in TaskList — see live state. Summary at time of plan creation:

- [x] Mailcow domain + 3 mailboxes + BCC forwards
- [x] DNS: A/MX/SPF/DMARC/DKIM via GoDaddy API
- [x] uk215 reconnaissance (port 443 conflict surfaced)
- [ ] PLAN.md ← *you are here*
- [ ] GitHub repo
- [ ] MySQL DB + user
- [ ] Astro skeleton
- [ ] Logo + favicon
- [ ] All 14 pages with content
- [ ] Contact form (frontend + PHP)
- [ ] SEO (sitemap, robots, JSON-LD)
- [ ] Push to GitHub
- [ ] Local preview on ru128
- [ ] *(deferred)* VPS deploy
