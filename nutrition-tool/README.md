# Umurima Data

**A district-level crop planning and food-access tool for Rwanda, built for the NISR Big Data Hackathon 2026 (Track 1: Agricultural Productivity).**

Pick a district and a growing season. Umurima Data ranks 17 crops using official NISR yields, recent WFP market prices, USDA nutrient profiles, and a seasonal weather forecast, then shows what each crop would deliver per hectare in energy and protein. Every result cites its inputs and shows which scoring dimensions were left out because the data does not exist yet.

> **Status: research prototype.** Rankings are decision-support, not validated agronomic advice. See [Limitations](#limitations).

**Live demo:** `hackathon.igtacademy.com` &nbsp;|&nbsp; **Demo video:** `<VIDEO URL>` &nbsp;|&nbsp; **Languages:** English, Kinyarwanda

<!-- Add 1-2 screenshots here: Crop Advisor ranking + farm-to-food table -->
<!-- ![Crop Advisor](docs/img/crop-advisor.png) -->

## Contents

- [Problem and impact](#problem-and-impact)
- [How this maps to the judging criteria](#how-this-maps-to-the-judging-criteria)
- [Features](#features)
- [Quick start](#quick-start)
- [Architecture](#architecture)
- [Data snapshot](#data-snapshot)
- [Evaluation](#evaluation)
- [Limitations](#limitations)
- [Security](#security)
- [Extending](#extending)
- [Documentation](#documentation)
- [AI tool disclosure](#ai-tool-disclosure)
- [Team, eligibility and IP](#team-eligibility-and-ip)

## Problem and impact

**The problem.** District agriculture officers, planners and farmers decide what to plant each season using information that lives in separate places: NISR survey reports, market price series, food-composition tables and weather forecasts. These sources use different units, geographies and seasons, so they are rarely combined at district level, and missing data is easy to hide behind a single number.

**National alignment.** Umurima Data supports the agriculture priority ("Agriculture for Wealth Creation") of Rwanda's second National Strategy for Transformation (NST2, 2024-2029), which aims to raise agricultural productivity by more than 50% and to grow a modernized agriculture sector by over 6% a year. It also supports PSTA 5, the sector plan that implements NST2, whose first priority area is *Modernization of Agriculture and Animal Resources Production for Climate Resilient Agri-Food Systems*, and NST2's focus on food and nutrition security and reduced stunting. At the Vision 2050 level, it serves the goal of a high-income Rwanda by 2050, in which agricultural productivity growth and eradicating chronic malnutrition play a central part. Crop Advisor links these at district level: it ranks crops using official NISR yields, price trends and seasonal climate fit (productivity and climate resilience) and shows the energy and protein each crop would deliver per hectare (food and nutrition security), so planners see productivity and nutrition in one view.

*Sources:* [PSTA 5 abridged (MINAGRI)](https://www.minagri.gov.rw/fileadmin/user_upload/Minagri/Publications/Policies_and_strategies/PSTA_5_Abridged_version_Final.pdf); [PwC Rwanda Development Digest](https://pwc.com/rw/en/assets/pdf/rwanda-development-digest-v2.pdf); [KT Press on NST2 agriculture growth](https://ktpress.rw/?p=106954); [NST2 summary, Rwanda in Tanzania](https://www.rwandaintanzania.gov.rw/actualites/info-details/rwanda-unveils-2nd-national-strategy-for-transformation-nst2); [Vision 2050 analysis, Sustainability Science (2022)](https://pure.iiasa.ac.at/id/eprint/18532/1/s11625-022-01266-0.pdf).

**Who uses it and how.**
- *District agriculture officer or planner:* before Season `A/B/C`, opens Crop Advisor for `<district>`, sees crops ranked with their NISR yield evidence, price trend, forecast fit and energy/protein per hectare, checks which dimensions were excluded, and uses the ranking as a starting point for extension discussions, not as a prescription.
- *Policymaker or analyst:* compares NISR Season A and B yields across 30 districts on the NISR data page.
- *Nutrition and food-access analyst:* uses the Nutrition-per-Cost Tool to see which foods give the most of a nutrient per 100 RWF.

**What makes it different.**
- **Evidence-first:** yields come from official NISR tables; every result cites its inputs.
- **Honest about gaps:** unavailable dimensions (currently soil and stewardship) are excluded and displayed as excluded, never estimated.
- **Farm-to-food link:** compares crops by expected nutrition per hectare, not just yield per hectare.


**data we used are located:**nutrition-tool/data


## Features

**Crop Advisor** (`nutrition-tool/public/crop_advisor.php`), the primary module.
- Ranks 17 crops for a chosen district and season (A, B or C).
- Scores only dimensions that have documented data: district yield, market price trend, harvest nutrition, and seasonal weather. Soil and stewardship join the score automatically once cited district records exist.
- Farm-to-food table: compares the top three crops by expected harvest, forecast fit, and estimated energy and protein per hectare.
- Each crop shows a source-and-limitations checklist, not a fake confidence score.
- A Data Sources page compares NISR Season A and B yields across 30 districts and leaves missing values explicitly unreported.

**Nutrition-per-Cost Tool** (`nutrition-tool/public/index.php`), the food-access lens.
- Ranks market foods by how much of a nutrient you get per 100 RWF, across 13 tracked nutrients.

**Admin workspace**: login-protected editing of foods, prices, and crop suitability, plus a PDF extraction assistant that produces review drafts only (nothing is written to the database without human review).

Both public modules share the same `foods`, `prices` and `nutrient_profiles` tables, so a crop's price and nutrition data are never duplicated.

## Quick start

**Requirements:** PHP 8.1+ with `pdo_mysql`, MySQL 8+ or MariaDB 10.6+, a C++17 compiler (`g++` or `clang++`).

Run everything from the `nutrition-tool/` directory.

```bash
# 1. Database: schema, then catalogs (districts, foods, crops)
mysql -u root -p < database/schema.sql
mysql -u root -p < database/seed.sql

# 2. Least-privilege app user (never run the app as root)
mysql -u root -p -e "
  CREATE USER 'umurima_app'@'localhost' IDENTIFIED BY 'choose-a-strong-password';
  GRANT SELECT, INSERT, UPDATE, DELETE ON umurima_nutrition.* TO 'umurima_app'@'localhost';
  FLUSH PRIVILEGES;"

# 3. Configuration (never commit .env)
cp .env.example .env        # then edit DB credentials and API keys

# 4. Import data
php bin/import_nisr_sas_observations.php data/nisr_sas_2026b_observations.csv
php bin/import_nisr_sas_2026a_yields.php
php bin/import_fooddata_central_nutrients.php import data/fdc_nutrient_approved.csv   # needs USDA_FDC_API_KEY
php bin/import_wfp_rwanda_prices.php

# 5. Admin account
php bin/create_admin.php <username> <password>

# 6. Build the scoring engine (use bin/crop_scorer.exe on Windows)
g++ -std=c++17 -O2 -Wall -o bin/crop_scorer cpp/crop_scorer.cpp

# 7. Run (development only, see Security before deploying)
php -S 127.0.0.1:8000 -t .
```

Then open:
- Nutrition tool: `http://127.0.0.1:8000/public/index.php`
- Crop Advisor: `http://127.0.0.1:8000/public/crop_advisor.php`
- Admin: `http://127.0.0.1:8000/admin/login.php`

Troubleshooting (blank page, HTTP 500, "Could not load data") is in [docs/TROUBLESHOOTING.md](docs/TROUBLESHOOTING.md). Upgrading an existing database is covered in [docs/IMPORTING.md](docs/IMPORTING.md).

## Architecture

```
public/crop_advisor.php -> api/crop_advisor.php -> includes/crop_scoring.php
                                                        |
                                                        |  gathers per-crop inputs:
                                                        |   - soil / stewardship (agro_suitability, when cited)
                                                        |   - climate (seasonal forecast vs crop ranges)
                                                        |   - market trend (district price history)
                                                        |   - observed yield (NISR first, then fallbacks)
                                                        |   - nutrition (harvest nutrients per hectare)
                                                        v
                                               bin/crop_scorer  (compiled C++17)
                                                        |  JSON {weights, candidates} on stdin
                                                        |  JSON {results} on stdout
                                                        v
                                               ranked scores back to the page
```

PHP owns data access, validation and the web layer. The multi-criteria scoring runs in a small C++ engine launched with `proc_open()` using an argument array (never a shell string), and all request data travels as JSON on stdin, so no user input reaches a command line.

The full scoring formulas are in [docs/METHODOLOGY.md](docs/METHODOLOGY.md).

## Data snapshot

Counts as of **2026-10-26**. Regenerate with the importers.

| Data | Source | License | Coverage in this snapshot |
|---|---|---|---|
| Crop yields | NISR *Seasonal Agricultural Survey* 2026, Seasons A and B | CC BY 4.0 | 30 districts; 309 mapped district/crop records (Season B) plus Season A mapped yields; 8,566 Season B reference observations |
| Market prices | WFP Rwanda Food Prices (HDX) | CC BY-IGO | 1,491 retail observations, 10 foods, 5 districts (Gatsibo, Gisagara, Karongi, Kirehe, Nyamagabe), 2025-11-15 to 2026-08-15 |
| Nutrient profiles | USDA FoodData Central | CC0 1.0 | 16 foods with all 13 nutrients; some are labeled proxies for local foods |
| Seasonal weather | Open-Meteo Seasonal Forecast (ECMWF SEAS5 ensemble mean) | CC BY 4.0, non-commercial free tier | Live, cached up to 3 hours |
| Soil and stewardship | None yet (RwaSIS preferred; SoilGrids gives properties, not suitability) | n/a | **Not available. Excluded from scoring.** |
| Context only | NISR Food Balance Sheet 2025, CPI 2026 | NISR terms | Displayed on the NISR data page; never loaded into price or nutrient tables |

Missing values stay missing. A dash or blank in a source table is never converted to zero.
## Evaluation

No impact is claimed beyond what is reported here.

**Baseline comparison.** For each district and season, the Crop Advisor top three are compared with a simple baseline: the three crops with the highest reported NISR yield. Differences are expected, because the advisor also uses price trend, forecast fit, nutrition per hectare and the production-priority factor. Disagreements are explained rather than hidden.

| Season | Districts compared | Top-3 overlap with baseline | Notable differences and why |
|---|---|---|---|
| `<A/B>` | `<n>` | `<x>%` | `<example>` |


Formal validation with agronomists and intended users, and evaluation against local trial data, remain future work.

## Limitations

Read these before relying on any ranking.

- **Not formally validated.** Recommendations have not been confirmed by agronomists or farmers and are not proof of agricultural impact. See [Evaluation](#evaluation) for what has been checked.
- **No soil or stewardship data.** Those dimensions are excluded; remaining weights are renormalized.
- **Climate fit is provisional.** Without a district climate baseline it is a weather-based score only. Seasonal forecasts are uncertain and update monthly.
- **Market coverage is partial.** Prices cover 5 districts and 10 foods. With fewer than two distinct observation dates the price score is neutral and flagged as unavailable. A price trend is not profitability.
- **Yield is historical.** It describes reported productivity, not future yield, profitability or agronomic suitability.
- **Nutrition estimates are simplified.** They assume the whole harvested mass has the linked food's nutrient profile. Edible share, cooking, storage and field losses are not modeled. Some USDA profiles are proxies.
- **Relative scoring.** Nutrition is normalized within the candidate set, so adding crops changes other crops' nutrition scores.
- **Production-priority factor is a planning preference**, not an agronomic finding (it down-ranks vegetables for large-scale planting).
- **No automated test suite yet.** The scoring engine and importers have only been smoke-tested manually.
- **Open-Meteo's free API** is non-commercial, rate-limited and has no uptime guarantee.

## Security

Implemented:
- PDO prepared statements with real parameter binding (`ATTR_EMULATE_PREPARES => false`).
- Output escaping through `h()` everywhere dynamic data reaches HTML.
- Nutrient column names matched against a hardcoded whitelist (`ALLOWED_NUTRIENTS`) before touching SQL.
- Admin: `password_hash()` / `password_verify()`, equalized login timing, `httponly` + `samesite=Lax` cookies, periodic session ID regeneration, CSRF token on every state-changing form.
- C++ engine invoked with an argument array; input only via stdin.
- Single database connection file (`config/database.php`).

Before deploying publicly:
- **Do not use the PHP built-in server in production, and do not serve the project root.** `.env`, `data/`, `database/`, `bin/` and source PDFs must not be reachable over HTTP. Deny dotfiles and non-public directories at the web server, for example (Nginx):
  ```nginx
  location ~ /\.            { deny all; }
  location ~ ^/(data|database|bin|cpp|includes|config)/ { deny all; }
  ```
- Serve over HTTPS and set `session.cookie_secure=1`.
- Add security headers (`Content-Security-Policy`, `X-Content-Type-Options`, `X-Frame-Options` or `frame-ancestors`, `Referrer-Policy`, `Strict-Transport-Security`).
- Build `bin/crop_scorer` on the target machine and treat committed binaries as untrusted; verify any prebuilt `.exe` against a published checksum.
- The PDF extraction tool sends report text to third parties **only when you select them** (LlamaParse, Gemini, Groq). Review their retention terms, keep API keys server-side in `.env`, and never commit them.
- Keep source PDFs outside the web root.

Known gaps (planned): login rate limiting or lockout, admin two-factor authentication, upload hardening review for the PDF tool (MIME and size checks, storage outside web root), size limits and fuzz testing for the C++ JSON parser.

## Extending

- **Add a nutrient:** add one entry to `ALLOWED_NUTRIENTS` in `includes/functions.php` and the matching column in `schema.sql`. Ranking queries, the admin form and nutrient tabs read from that single list.
- **Add a crop:** link it to a food and supply one `agro_suitability` row per district with a documented source.
- **Add a yield source:** register the dataset (publisher, citation, URL, reuse terms) and import district-level aggregates with `bin/import_district_crop_stats.php`. NISR is preferred automatically; verified fallbacks can be enabled in the admin crop-suitability page. 
- **Add soil or stewardship data:** add cited district records and the dimension joins the composite on its own;
- **Change scoring weights:** `available_crop_scoring_weights()` and `run_crop_scorer()` in `includes/crop_scoring.php` accept overrides.



## AI tool disclosure

AI tools were used as assistants in this project. The team has verified the entire project and is fully responsible for the submitted work.

claude , used for generating first draft of this readme
github copilot, used for generating code

## Team

- **Team :** `HASHIMWIMANA Paterne, University of Rwanda, BIT(Business Information Technology)` and `HABIMANA Jimmy, University of Rwanda, BIT(Business Information Technology)`.
- **Originality:** this submission is our own original work, has not been submitted to any other competition or entity, and all sources are acknowledged (see [Data snapshot](#data-snapshot).
- **Intellectual property:** as required by the NISR Big Data Hackathon 2026 rules, rights in this submission are assigned to the National Institute of Statistics of Rwanda (NISR). The team grants no separate open-source license. Third-party data and libraries keep their own licenses.
- **Data attribution:** NISR (CC BY 4.0), WFP via HDX (CC BY-IGO), USDA FoodData Central (CC0 1.0), Open-Meteo (CC BY 4.0).