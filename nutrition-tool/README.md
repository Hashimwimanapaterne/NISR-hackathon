# Umurima Data — Nutrition-per-Cost Tool

**Phase 1 of the NISR Big Data Hackathon 2026 submission (Track 1: Agricultural Productivity).**
This is the first of the three planned modules — see the full project proposal for the complete
picture, including the Phase 2 Crop Advisor and Phase 3 Price-Gap View.

Ranks common Rwandan market foods by how much of a given nutrient — across 13 tracked
nutrients (energy, protein, fat, carbohydrates, fiber, iron, zinc, calcium, potassium,
vitamin A, vitamin C, folate, vitamin B12) — you get for every 100 RWF spent, using the
most recent recorded price for each food.

## Status

Fully working end-to-end: database schema, ranking logic, public-facing tool, JSON API, and an
admin panel for entering prices. It has been run and manually tested locally (schema load, seed
load, ranking math, CSRF protection, XSS escaping, auth guard).

**Not yet done / next steps:**
- Prices are **placeholder sample data**, not a live e-Soko feed. Replace `database/seed.sql`'s
  price rows with real e-Soko figures, or build a scheduled import job, before submission.
- Nutrient values are approximate reference figures for common food groups — worth
  double-checking against a validated regional food composition table if time allows.
- Phase 2 (Crop Advisor) and Phase 3 (Price-Gap View) are not built yet.

## Requirements

- PHP 8.1+ with the `pdo_mysql` extension
- MySQL 8+ or MariaDB 10.6+
- Any web server (Apache/Nginx) or PHP's built-in server for local development

## Setup

```bash
# 1. Create the database and load the schema
mysql -u root -p < database/schema.sql

# 2. Load sample data (replace with real e-Soko data before submission)
mysql -u root -p < database/seed.sql

# 3. Create a dedicated, least-privilege database user (don't run the app as root)
mysql -u root -p -e "
  CREATE USER 'umurima_app'@'localhost' IDENTIFIED BY 'choose-a-strong-password';
  GRANT SELECT, INSERT, UPDATE, DELETE ON umurima_nutrition.* TO 'umurima_app'@'localhost';
  FLUSH PRIVILEGES;
"

# 4. Configure environment variables (copy and edit)
cp .env.example .env
# config/database.php auto-loads this .env file, so this works even on
# hosts where you can't export real environment variables. If you deploy
# to a provider that requires SSL (e.g. Aiven, PlanetScale), set
# DB_SSL_ENABLED=true and DB_SSL_CA in .env — see the comments in
# .env.example. Leave it false for local MySQL/MariaDB.

# 5. Create your admin account
php bin/create_admin.php <username> <password>
# Re-run this any time you reload the schema from scratch — dropping
# and recreating the database wipes admin_users along with everything else.

# 6. Run locally
php -S localhost:8000 -t .
# Visit http://localhost:8000/public/index.php      (public tool)
# Visit http://localhost:8000/admin/login.php        (admin panel)
```

> **One connection file, on purpose.** `config/database.php` is the only place the app
> opens a database connection — every page reaches it through `includes/bootstrap.php`.
> If you're merging in a connection snippet from elsewhere (a different hosting setup, an
> older draft, etc.), fold its logic into this one file rather than adding a second,
> slightly different copy — two connection paths are two places to patch when credentials
> rotate or a security fix is needed, and they tend to quietly drift apart (e.g. one
> enforcing SSL, the other not).

## Project structure

```
config/database.php     PDO connection (reads credentials from environment)
database/schema.sql     Table definitions
database/seed.sql       Sample foods, nutrient profiles, and placeholder prices
includes/               Shared bootstrap, security helpers (CSRF, sessions), ranking logic
public/                 Public-facing tool (index.php + assets)
api/foods.php           Read-only JSON endpoint the public page calls
admin/                  Login-protected panel for managing foods and recording prices
bin/create_admin.php    CLI script to create/update admin accounts (never over HTTP)
```

To add a 14th nutrient later: add one line to `ALLOWED_NUTRIENTS` in `includes/functions.php`
and the matching column in `database/schema.sql`. The ranking query, the admin form, and the
public page's nutrient tabs all read from that one list — nothing else needs to change.

## Security notes

- All database queries use PDO prepared statements with real parameter binding
  (`PDO::ATTR_EMULATE_PREPARES => false`).
- Admin sessions use `httponly`, `samesite=Lax` cookies, periodic session ID regeneration,
  and a CSRF token (timing-safe comparison) on every state-changing form.
- Passwords are hashed with `password_hash()` / verified with `password_verify()`; login
  timing is equalized between valid and invalid usernames to avoid a user-enumeration
  side-channel.
- All dynamic output is passed through `h()` (an `htmlspecialchars` wrapper) before being
  echoed into HTML.
- The nutrient column used in ranking queries is matched against a hardcoded whitelist
  (`ALLOWED_NUTRIENTS`) before ever touching SQL — user input never reaches a raw column name.

## Data sources

| Data | Source |
|---|---|
| Market prices | [e-Soko](https://www.esoko.gov.rw) (MINAGRI) — replace sample prices with a real export |
| Nutrient composition | FAO/INFOODS regional food composition tables (approximate reference values) |

## AI tool usage disclosure

Per the hackathon rules, disclose in your final submission which parts of this codebase were
AI-assisted and which were written/reviewed by the team, and confirm the team is responsible
for the submitted work.
