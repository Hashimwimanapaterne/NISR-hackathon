# Umurima Data — NISR Big Data Hackathon 2026 Submission

**Track 1: Agricultural Productivity.** Two modules share one database:
a **Nutrition-per-Cost Tool** (Phase 1) and a **Crop Advisor** (Phase 2). A Phase 3
Price-Gap View is planned but not started — see the full project proposal for the complete
three-module picture.

## What's here

1. **Nutrition-per-Cost Tool** (`public/index.php`) — ranks market foods by how much of a
   given nutrient (13 tracked: energy, protein, fat, carbs, fiber, iron, zinc, calcium,
   potassium, vitamin A, vitamin C, folate, B12) you get per 100 RWF spent.
2. **Crop Advisor** (`public/crop_advisor.php`) — for a chosen district, ranks 17 crops
   (potato, rice, maize, cassava, sorghum, common beans, soybean, sweet potato, avocado,
   cabbage, carrot, amaranth, plantain, groundnut, onion, banana, and tomato) by soil fit,
   climate fit, market price momentum, balanced nutrition in the full expected harvest per
   hectare, and soil stewardship (erosion protection, nutrient balance, and soil structure).
   Results also provide crop-specific management notes and rotation suggestions. Scoring runs in a compiled **C++ engine**
   (`cpp/crop_scorer.cpp`) that the PHP layer calls as a subprocess — see "Crop Advisor
   architecture" below.

Both modules read from the same `foods` / `prices` / `nutrient_profiles` tables — a "crop"
in the advisor is linked to its corresponding food, so its market price and
nutrition data are never duplicated.

## Status

Both modules are fully working end-to-end and have been run and manually tested locally:
schema/seed load, ranking math (by hand, both modules), CSRF protection, XSS escaping,
auth guards, and — for the Crop Advisor specifically — the PHP↔C++ subprocess boundary
(valid input, malformed input, missing binary, out-of-range scores).

**Not yet done / next steps:**
- Prices are **placeholder sample data**, not a live e-Soko feed.
- Nutrient values are approximate reference figures — worth validating before submission.
- Soil/climate suitability figures in `agro_suitability` are **illustrative estimates**
  and neutral placeholders, not a RwaSIS or SoilGrids import. Added crops start with
  neutral 50/100 soil and climate scores. Replace these through the admin panel before using
  rankings for farm decisions.
- Soil stewardship scores and crop rotation guidance are illustrative, management-dependent
  starting points, not measured soil outcomes or guaranteed crop properties. Stewardship is
  40% erosion-control potential, 35% nutrient balance/lower depletion potential, and 25% soil
  structure potential. The total crop composite weights soil fit 25%, climate fit 20%, market
  momentum 20%, nutrition 15%, and stewardship 20%. Validate ratings and rotations locally.
- Nutrition-per-hectare totals use `nutrient per 100 g x 10 x expected harvest (kg/ha)`.
  Higher-yield crops are compared at their full expected harvest (for example, 12 tonnes
  against 1.5 tonnes), not at equal sample weights. The score min-max normalizes each of the
  13 nutrients across the district's candidate crops, averages nutrients within three groups
  (energy/macros, minerals, vitamins), then averages the available groups equally. Nutrients
  constant across candidates (such as zero B12 for every crop) are omitted from the comparison.
  Totals are displayed per hectare in million kcal for energy, kg for macros/minerals, and
  g/kg for vitamins as appropriate. The estimate assumes all
  expected harvested mass has the linked food's nutrient profile; edible-yield share, cooking,
  processing, storage, and field losses are not modeled. Validate yields and nutrient profiles
  locally before relying on the score.
- When RwaSIS has no district data, [ISRIC SoilGrids](https://soilgrids.org/) is an alternative
  source of mapped soil properties (250 m grids with depth intervals, including pH, organic
  carbon, texture, and cation exchange capacity). SoilGrids does **not** provide crop
  suitability scores: interpret its properties using locally appropriate crop requirements,
  and record the dataset, depth, property, statistic, and method in the admin source reference.
- Crop yield estimates are illustrative planning values and should be replaced with RAB or
  other locally validated data. The nutrition subscore is normalised within the current
  candidate set, so adding crops changes relative nutrition scores.
- The advisor retrieves the Open-Meteo
  [Seasonal Forecast API](https://open-meteo.com/en/docs/seasonal-forecast-api) using the ECMWF
  SEAS5 ensemble-mean model and resolves Rwanda districts with the
  [Open-Meteo Geocoding API](https://open-meteo.com/en/docs/geocoding-api). Daily forecast values
  are aggregated over Rwanda's growing seasons (Season A: September–January; Season B:
  February–June; Season C: July–August). The UI selects among seasons with at least 70% of
  calendar days covered by the model; the displayed covered dates and percentage make partial
  coverage explicit. PHP caches the district outlook for up to 3 hours.
  `weather_fit = 0.60 x temperature_fit + 0.40 x rainfall_fit`, using season-mean temperature
  and seasonal rainfall converted to an average weekly equivalent to match the existing
  indicative crop ranges. Fit declines linearly outside those ranges (8 °C temperature and
  50 mm weekly rainfall tolerance). `climate = 0.70 x district_climate_baseline + 0.30 x
  weather_fit`; this adjusted climate feeds the existing weighted composite, so seasonal
  weather fit contributes 6% of the total score. Seasonal forecasts are uncertain and updated
  monthly; treat them as planning guidance, not a planting guarantee. Local crop calendars and
  crop ranges require validation. Provider errors are reported rather than replaced with
  pretend live weather.
- Open-Meteo's free API is non-commercial, rate-limited, and has no uptime guarantee. Attribution
  under CC BY 4.0 is displayed in the advisor. Review
  [Open-Meteo pricing/licensing](https://open-meteo.com/en/pricing) before commercial use.
- Phase 3 (Price-Gap View) is not built yet.

## Requirements

- PHP 8.1+ with the `pdo_mysql` extension
- MySQL 8+ or MariaDB 10.6+
- A C++17 compiler (`g++` or `clang++`) to build the Crop Advisor's scoring engine
- Any web server (Apache/Nginx) or PHP's built-in server for local development

## Setup

```bash
# 1. Create the database and load the schema
mysql -u root -p < database/schema.sql

# 2. Load sample data (replace with real e-Soko/RwaSIS data before submission)
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

# 6. Build the Crop Advisor's scoring engine
g++ -std=c++17 -O2 -Wall -o bin/crop_scorer cpp/crop_scorer.cpp
# The Crop Advisor page shows a clear error (not a crash) if you skip this step.
# On Windows, use: g++ -std=c++17 -O2 -Wall -o bin/crop_scorer.exe cpp/crop_scorer.cpp

# 7. Run locally
php -S localhost:8000 -t .
# Visit http://localhost:8000/public/index.php         (Nutrition tool)
# Visit http://localhost:8000/public/crop_advisor.php   (Crop Advisor)
# Visit http://localhost:8000/admin/login.php           (admin panel)
```

> **One connection file, on purpose.** `config/database.php` is the only place the app
> opens a database connection — every page reaches it through `includes/bootstrap.php`.
> If you're merging in a connection snippet from elsewhere, fold its logic into this one
> file rather than adding a second, slightly different copy — two connection paths are two
> places to patch when credentials rotate, and they tend to quietly drift apart.

For an existing Crop Advisor database, apply `database/crop_expansion_soilgrids.sql` if its
`agro_suitability` table does not yet have the `source_reference` column, then apply
`database/crop_expansion_plant_foods.sql` to add the nine remaining plant-food crops. The
plant-food migration is safe to rerun. Fresh databases created from the current schema and seed
already contain all 17 crops; do not run the migrations after a fresh schema/seed setup. New
crop suitability rows intentionally start with neutral placeholders.

For the soil-stewardship formula and rotation guidance, apply
`database/crop_soil_stewardship_rotation.sql` once to an existing database, after backing it up,
and rebuild the C++ scoring engine. Fresh setups receive the fields and initial guidance from
the current schema/seed.

## Crop Advisor architecture

```
public/crop_advisor.php  ->  api/crop_advisor.php  ->  includes/crop_scoring.php
                                                            |
                                                            +- soil_score, climate_score,
                                                            |  erosion control, nutrient balance,
                                                            |  soil structure (agro_suitability)
                                                            +- market_score
                                                            |  (price momentum, computed from
                                                            |   the `prices` table's history)
                                                            +- nutrition_score
                                                            |  (protein+iron per hectare, from
                                                            |   nutrient_profiles x crop yield)
                                                            |
                                                            v
                                                   bin/crop_scorer  (compiled C++)
                                                            |  reads {weights, candidates} as
                                                            |  JSON on stdin, writes ranked
                                                            |  {results} as JSON on stdout
                                                            v
                                                   ranked composite scores back to the page
```

**Why a subprocess, not a PHP function:** this is the deliberate hybrid-architecture piece —
PHP handles data access and the web layer; the actual multi-criteria weighting is real,
tested C++. `includes/crop_scoring.php` invokes the compiled binary via `proc_open()` with
the command passed as an array (never a shell string), so there's no shell interpolation of
any data — the only thing sent to the process is a JSON payload on stdin.

**Why a hand-rolled JSON parser in the C++ side:** the interchange format is small and fully
under this project's control (one caller, one callee), so a compact recursive-descent parser
(`cpp/crop_scorer.cpp`) keeps the build to a single `g++` command with nothing to vendor or
fetch. It's a real JSON parser (objects, arrays, strings, numbers, escapes), just scoped to
what this project needs rather than a general-purpose library.

**Scoring formula:** `composite = 0.25 x soil + 0.20 x climate + 0.20 x market + 0.15 x nutrition + 0.20 x stewardship`
(stewardship = `0.40 x erosion control + 0.35 x nutrient balance + 0.25 x soil structure`)
(weights are normalised automatically if they don't sum to 1, and can be overridden per call
— see `run_crop_scorer()` in `includes/crop_scoring.php`).
The climate input is `0.70 x district climate baseline + 0.30 x selected-season weather fit`;
weather fit is `0.60 x season-mean temperature fit + 0.40 x weekly-equivalent rainfall fit`.

## Project structure

```
config/database.php       PDO connection (auto-loads .env, optional SSL)
database/schema.sql        Table definitions (both modules)
database/seed.sql          Sample foods/nutrients/prices + districts/crops/suitability
includes/                  Bootstrap, security helpers, nutrition ranking, crop scoring
public/                    Nutrition tool + Crop Advisor pages and assets
api/foods.php               Nutrition tool's JSON endpoint
api/crop_advisor.php        Crop Advisor's JSON endpoint
database/crop_expansion_soilgrids.sql  Existing-database crop/source migration
database/crop_expansion_plant_foods.sql  Existing-database migration for nine more crops
database/crop_soil_stewardship_rotation.sql  Existing-database stewardship/rotation migration
admin/                      Login-protected panel: foods/prices AND crop suitability
cpp/crop_scorer.cpp         Crop Advisor scoring engine (build before first use)
bin/create_admin.php        CLI script to create/update admin accounts (never over HTTP)
bin/crop_scorer              Compiled scoring engine binary (build target, not shipped —
                             see note below)
```

To add a 14th nutrient: add one line to `ALLOWED_NUTRIENTS` in `includes/functions.php` plus
the matching column in `schema.sql` — the ranking query, admin form, and nutrient tabs all
read from that one list. To add a crop to the advisor, link it to a food and supply one
`agro_suitability` row per district with a documented data source.

> **Note on `bin/crop_scorer`:** this repo ships the C++ *source*, not a precompiled binary —
> a binary built in one environment (e.g. this project's Linux dev container) may not run on
> yours. Build step 6 above takes a few seconds.

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
- The Crop Advisor's PHP-to-C++ call passes the binary path as an array to `proc_open()`
  (never a shell string), and all request data travels over stdin as JSON — no user input
  ever reaches a command line or shell.

## Data sources

| Data | Source |
|---|---|
| Market prices | [e-Soko](https://www.esoko.gov.rw) (MINAGRI) — replace sample prices with a real export |
| Nutrient composition | FAO/INFOODS regional food composition tables (approximate reference values) |
| Soil/climate suitability | Preferred: RwaSIS (RAB). Alternative soil properties: [ISRIC SoilGrids](https://soilgrids.org/) — must be interpreted into local crop-suitability scores. Current demo scores are placeholders. |
| Expected weather forecast | [Open-Meteo Seasonal Forecast API](https://open-meteo.com/en/docs/seasonal-forecast-api), ECMWF SEAS5 ensemble mean, plus [Geocoding API](https://open-meteo.com/en/docs/geocoding-api); selectable Rwanda growing seasons (A/B/C), cached up to 3 hours |

## Troubleshooting

**Blank white page / "This page isn't working" / HTTP ERROR 500, with no error text at
all** (common on XAMPP): this almost always means PHP hit a fatal error *before* reaching
this app's own error handling, which happens when the PHP version is older than what a
piece of syntax needs.
- Check your version: `php -v` (Windows: Command Prompt, or the version shown in the XAMPP
  control panel). This project targets PHP 8.1+; the codebase avoids PHP 8-only syntax
  where practical, but confirm you're on 8.0+ at minimum.
- Check the real error message in XAMPP's logs — usually
  `xampp/apache/logs/error.log` (Windows) — even though the browser shows nothing, PHP
  still writes the actual error there (`log_errors` stays on even with `display_errors` off).
- Confirm the `pdo_mysql` PHP extension is enabled (`php -m` should list `pdo_mysql`).

**Page loads, but the ranking table just says "Could not load data"**: open the browser's
DevTools → Network tab and check the failing request's URL. If the app is deployed in a
subdirectory (e.g. `localhost/some-folder/nutrition-tool/public/`), make sure you're on a
build where `public/assets/js/app.js` and `crop_advisor.js` call `../api/...` (relative),
not `/api/...` (root-absolute) — the latter breaks under subdirectory hosting because it
resolves from the server's root, not the app's folder.

## AI tool usage disclosure

Per the hackathon rules, disclose in your final submission which parts of this codebase were
AI-assisted and which were written/reviewed by the team, and confirm the team is responsible
for the submitted work.
