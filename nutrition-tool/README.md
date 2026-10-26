# Umurima Data — NISR Big Data Hackathon 2026 Submission

**Track 1: Agricultural Productivity.** The **Crop Advisor** is the primary module; a
food-price and nutrition comparison is a supporting food-access lens. The prototype is
designed to help farmers and planners compare district-level crop options, seasonal weather,
soil stewardship, expected harvest nutrition, and local price history.

**Data-integrity status:** the checked-in seed populates district, food, and crop lookup
catalogs only. NISR Seasonal Agricultural Survey (SAS) Season B 2026 data is available in the
repository and imports 8,566 source observations plus 309 mapped district/crop yield records.
Season A 2026 yields are also imported from the NISR report. The current local database has
16 complete USDA FoodData Central nutrient profiles and, as of 2026-10-26, 1,491 recent WFP
Rwanda retail price observations across ten foods and five districts; scripts and approved
mappings reproduce these imports. Soil suitability and stewardship scores remain unavailable, so the app excludes those
dimensions rather than filling them with estimates. See
[Submission readiness](#submission-readiness) for the remaining gates.

## What's here

1. **Nutrition-per-Cost Tool** (`public/index.php`) — ranks market foods by how much of a
   given nutrient (13 tracked: energy, protein, fat, carbs, fiber, iron, zinc, calcium,
   potassium, vitamin A, vitamin C, folate, B12) you get per 100 RWF spent. The public
   nutrition and crop-advisor pages support English and Kinyarwanda, with the selected
   language remembered for the session.
2. **Crop Advisor** (`public/crop_advisor.php`) — for a chosen district, ranks 17 crops
   (potato, rice, maize, cassava, sorghum, common beans, soybean, sweet potato, avocado,
    cabbage, carrot, amaranth, plantain, groundnut, onion, banana, and tomato) using only
   available, documented dimensions: district yield, local market-price trend, harvest nutrition,
   and public seasonal weather. Soil and stewardship dimensions enter the score only when complete
   cited district-specific records are present. A farm-to-food table compares the top three
   crops by expected harvest, forecast fit, and estimated energy/protein per hectare; each crop
   exposes a source-and-limitations checklist (not a statistical confidence score). The Data
   Sources page compares NISR Season A/B yields across districts and leaves missing values
   explicitly unreported.
   Results cite their yield, market, and nutrient inputs and show which score dimensions are
   excluded. Scoring runs in a compiled **C++ engine**
   (`cpp/crop_scorer.cpp`) that the PHP layer calls as a subprocess — see "Crop Advisor
   architecture" below.

Both modules read from the same `foods` / `prices` / `nutrient_profiles` tables — a "crop"
in the advisor is linked to its corresponding food, so its market price and
nutrition data are never duplicated.

## Current prototype status

The repository contains a PHP/MySQL web application, a PHP-to-C++ crop scoring engine, a
district crop advisor, a food price/nutrient-value view, an admin workspace, and a seasonal
weather integration. These demonstrate a possible decision-support workflow; they are not
validated recommendations or proof of agricultural impact.

**Data and validation gaps to resolve before submission:**
- NISR's [Seasonal Agricultural Survey, Season B 2026](https://www.statistics.gov.rw/) is now
  included as 8,566 normalized reference observations from report Tables 6-33. The report
  identifies NISR as publisher, September 2026 as the report date, and CC BY 4.0 as its license.
  Values retain their table and PDF page, units, subgroup, and whether a cell was numeric, a
  dash, or blank. These sowing, seed, input, irrigation, and erosion statistics are descriptive
  evidence only: they are not yield estimates and do not affect suitability rankings. See
  [Importing NISR SAS 2026B reference observations](#importing-nisr-sas-2026b-reference-observations)
  for extraction and import steps.
- NISR Table 11 district/crop yields are imported as scoring evidence for mapped crops in
  Season B 2026. Other NISR area, production, and yield tables (Tables
  6 and 9-13) remain reference observations unless separately mapped and validated; do not
  treat land area or sampled farmer counts as crop performance.
- The WFP Rwanda price importer adds recent, cited retail observations for ten compatible food
  categories. Its current data snapshot spans 2025-11-15 through 2026-08-15, covers Gatsibo,
  Gisagara, Karongi, Kirehe, and Nyamagabe, and contains 1,491 observations. WFP coverage is not
  national or complete; prices are only mapped where the commodity, unit, currency, and app unit
  match. Other prices still require a dated, cited observation.
- Sixteen food-composition profiles have complete USDA FoodData Central values for all 13
  tracked nutrients. Some are explicitly labeled proxies for local foods. Foods without a
  defensible complete match remain without a profile; unreported nutrients are never converted
  to zero. Validate local food varieties, edible portions, and preparation states before using
  nutrition comparisons.
- No cited soil suitability or stewardship scores are currently available. The advisor omits
  those dimensions from the composite until complete cited district-specific records exist.
  Climate fit uses the public seasonal weather forecast compared with indicative crop ranges;
  without a district climate baseline, it is a provisional weather-based score, not a measured
  local suitability assessment.
  When records are present, stewardship is
  40% erosion-control potential, 35% nutrient balance/lower depletion potential, and 25% soil
  structure potential. With every dimension present, the base composite weights soil fit
  25%, climate fit 20%, market momentum 20%, nutrition 15%, and stewardship 20%; missing
  dimensions receive zero weight and the remaining dimensions are renormalized. In the current
  data snapshot, climate fit, market momentum, and nutrition are included; their base weights are
  renormalized across available dimensions. A
  food-production-priority factor contributes 20% of the final score: grains, tubers, and
  legumes receive 100/100, fruits & starch 75/100, vegetables 20/100, and other categories
  50/100. This planning preference down-ranks vegetables for large-scale planting without
  altering their nutrient values or reported yield. With matched yield evidence, observed yield
  contributes 40% before this factor (32% of the final score), and the base composite contributes
  the remaining 48%. Without matched yield evidence, the base composite contributes 80%.
  Matching NISR yields are always
  preferred; when NISR has no usable observation, an enabled and verified public dataset may
  supply the yield. Validate ratings and rotations locally.
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
- NISR district yields are available for mapped crops in 30 districts for Seasons A and B 2026;
  crops without a usable season/district yield or a documented typical yield are not scored.
  The nutrition subscore is normalised within the current candidate set, so adding crops changes
  relative nutrition scores.
- Market price trends now use observations from the selected district in the past year. They
  are averaged by date, and fewer than two distinct observation dates produce a neutral score
  and an explicit "unavailable" warning;
  this is not evidence of a stable price or a profitable crop. Even with multiple observations,
  the trend is exploratory and does not account for costs, seasonality, market access, or
  forecast uncertainty.
- NISR defines Season A as September–February, Season B as March–June, and Season C as
  July–September. The advisor uses these periods when aggregating the Open-Meteo forecast.
  Season C is shorter and is described by NISR as focused on vegetables and sweet potatoes in
  swamps and Irish potatoes in the volcanic agro-ecological zone; confirm crop coverage before
  applying any district yield observations from a report to that season.
- The advisor retrieves the Open-Meteo [Seasonal Forecast API](https://open-meteo.com/en/docs/seasonal-forecast-api)
  using the ECMWF SEAS5 ensemble-mean model and resolves Rwanda districts with the
  [Open-Meteo Geocoding API](https://open-meteo.com/en/docs/geocoding-api). Daily forecast values
  are aggregated over Rwanda's growing seasons (Season A: September–February; Season B:
  March–June; Season C: July–September). Coverage is measured against remaining calendar days
  from today through the end of each season; past days are not required forecast coverage, and
  the UI only offers seasons with at least 70% coverage of that remaining window. The displayed
  covered dates and percentage make partial coverage explicit. Provider days missing either
  temperature or rainfall are excluded from both the seasonal totals and coverage calculation;
  incomplete days are logged. PHP caches
  the district outlook for up to 3 hours. The selected season's forecast is used in scoring even
  when a cited climate baseline is unavailable; in that case, climate fit equals the weather fit.
  If a baseline is available, climate combines 70% baseline and 30% weather fit.
  `weather_fit = 0.60 x temperature_fit + 0.40 x rainfall_fit`, using season-mean temperature
  and seasonal rainfall converted to an average weekly equivalent to match the existing
  indicative crop ranges. Fit declines linearly outside those ranges (8 °C temperature and
  50 mm weekly rainfall tolerance). With no baseline, `climate = weather_fit`; when a cited
  baseline exists, `climate = 0.70 x district_climate_baseline + 0.30 x weather_fit`. This climate
  score feeds the composite's 20% base climate weight, renormalized with other available
  dimensions. Seasonal forecasts are uncertain and updated
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

# 2. Load district, food, and crop lookup catalogs (no illustrative measurements)
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

# 5. Import the available NISR SAS observations and district crop yields
php bin/import_nisr_sas_observations.php data/nisr_sas_2026b_observations.csv
php bin/import_nisr_sas_2026a_yields.php

# 6. Add a USDA FoodData Central API key to .env and import reviewed mappings
php bin/import_fooddata_central_nutrients.php import data/fdc_nutrient_approved.csv
# Optional: discover candidates for catalog foods not covered by the approved mappings
php bin/import_fooddata_central_nutrients.php discover

# 7. Fetch and import recent WFP Rwanda food prices
php bin/import_wfp_rwanda_prices.php

# 8. Create your admin account
php bin/create_admin.php <username> <password>
# Re-run this any time you reload the schema from scratch — dropping
# and recreating the database wipes admin_users along with everything else.

# 9. Build the Crop Advisor's scoring engine
g++ -std=c++17 -O2 -Wall -o bin/crop_scorer cpp/crop_scorer.cpp
# The Crop Advisor page shows a clear error (not a crash) if you skip this step.
# On Windows, use: g++ -std=c++17 -O2 -Wall -o bin/crop_scorer.exe cpp/crop_scorer.cpp

# 10. Run locally
php -S localhost:8000 -t .
# Visit http://localhost:8000/public/index.php         (Nutrition tool)
# Visit http://localhost:8000/public/crop_advisor.php   (Crop Advisor)
# Visit http://localhost:8000/admin/login.php           (admin panel)
```

The authenticated **Foods & prices** admin page lets you edit a food and its nutrient profile,
add a new price observation, or edit the latest price for a food. Price edits retain their
original source type and citation; correct imported-source mistakes by recording a verified
replacement observation rather than changing their provenance.

For an existing database, apply `database/crop_expansion_food_provenance.sql` once to add
food preparation/source fields, price citation fields, and the aggregated crop-statistics source
tables, and to update the latest-price view.
Fresh databases created from the current schema already include these changes. Back up the
database first; do not run this one-time migration after a fresh schema setup.

For an existing database populated by the former demo seed, back it up and apply
`database/remove_illustrative_seed_data.sql` after the migrations that create the current crop,
provenance, and suitability columns. This removes only the known demo rows and clears their
catalog yield/guidance values. Separately sourced/admin-entered data is retained.

> **One connection file, on purpose.** `config/database.php` is the only place the app
> opens a database connection — every page reaches it through `includes/bootstrap.php`.
> If you're merging in a connection snippet from elsewhere, fold its logic into this one
> file rather than adding a second, slightly different copy — two connection paths are two
> places to patch when credentials rotate, and they tend to quietly drift apart.

For an existing Crop Advisor database, apply `database/crop_expansion_soilgrids.sql` if its
`agro_suitability` table does not yet have the `source_reference` column, then apply
`database/crop_expansion_plant_foods.sql` to add the nine remaining plant-food crops. The
plant-food migration is safe to rerun. Fresh databases created from the current schema and seed
already contain all 17 crop catalog entries; do not run the migrations after a fresh schema/seed
setup. Suitability rows must be added with documented inputs.

For the soil-stewardship fields, apply `database/crop_soil_stewardship_rotation.sql` once to an
existing database, after backing it up, and rebuild the C++ scoring engine. Fresh setups receive
the fields, but no assumed scores or crop-specific guidance.

### Importing verified aggregated crop statistics

The importer accepts only district-level, season/crop/year aggregates; it does not accept
household records or microdata. After obtaining access and confirming reuse terms, prepare a
CSV file with the district, season, crop, and reported aggregate values. Legacy CSV files remain
supported with these exact headers:

```text
district,season,survey_year,crop_name,cultivated_area_ha,production_tonnes
```

An extended format supports survey-derived data that distinguishes cultivated and harvested
areas and reports yield directly:

```text
district,season,survey_year,crop_name,cultivated_area_ha,harvested_area_ha,production_tonnes,yield_kg_per_hectare
```

Use district and crop names already in the application, season `A`, `B`, or `C`, area in
hectares, production in metric tonnes, and yield in kg/ha. Leave an unavailable optional
harvested area or published yield cell empty; do not convert missing values to zero. The current
import contract still requires cultivated area and production. Review any mapping from the
publisher's variables and units with a statistician. When the survey uses a sample design,
calculate district estimates using its documented weights and estimation method first; never
treat raw sample totals as population totals. Both CSV shapes are validated, cited, and imported
transactionally.

```bash
php bin/import_district_crop_stats.php <aggregated.csv> "<dataset title>" <release-year> \
  "<publisher>" "<citation>" "<catalog/document URL>" "<verified reuse terms>"
```

### Importing NISR SAS 2026B observations and yield evidence

The normalized [observation CSV](data/nisr_sas_2026b_observations.csv) contains Tables 6-33
from NISR's *Seasonal Agricultural Survey, Season B 2026* (PDF pages 31 and 35-61). It preserves
district, national, crop, farmer-type, and fertilizer observations; indicators; units; subgroups;
the source PDF page; and whether each cell was numeric, a dash, blank, or marked not available.
The report states CC BY 4.0; the importer records NISR attribution and marks the NISR dataset
for automatic priority in crop-yield scoring without disabling public fallbacks. District yields
in Table 11 are copied to `district_crop_statistics` as
published values (kg/ha), without recomputing or averaging them with catalog yields. Only
mapped district/crop pairs with a reported numeric value are imported; missing, dash, and
unmapped crop groups are not converted into estimates.
Other report observations remain available in `agricultural_survey_observations`. Missing cells
remain missing and never become zero. Sowing dates describe observed practice, not recommended
planting dates, so they are retained as reference observations rather than treated as agronomic
recommendations.

For each district/crop/season, the scorer uses matching NISR Table 11 yield evidence first.
If no usable NISR value exists, it can use the most recent enabled, verified public dataset
record for that crop. Within the selected dataset, multiple survey-year records are weighted by
harvested area (or cultivated area where harvested area is not reported); distinct datasets are
never averaged together. Selected observed yields are normalized across the district's candidate
crops and contribute 40% before the 20% food-production-priority factor is applied (32% of the
final score); supporting indicators contribute 48%. Without observed yield evidence, supporting
indicators contribute 80%. The production-priority factor favors grains, tubers, and legumes and
deprioritizes vegetables for large-scale planting without changing agronomic fit or nutrient data.
Crops with no usable public observation need a separately cited yield estimate to be scored;
the seed does not supply one. The yield score describes reported historical productivity only;
it is not a measure of profitability,
agronomic suitability, or future yield and must not be used alone as a planting recommendation.

For a fresh database, the observation table is included in `database/schema.sql`. For an
existing database, first apply `database/crop_expansion_food_provenance.sql` (if needed),
`database/crop_statistics_multisource.sql`, `database/nisr_sas_observations.sql`, and then
`database/nisr_crop_advisor.sql`, each only if its changes have not already been applied. Back
up the database first. The latter migration permits official published yields without inventing
area or production values, and adds informational planting-to-harvest ranges. To regenerate the CSV from the report, install
Poppler (`pdftotext`) and compile the C++17 preparation tool:

```bash
g++ -std=c++17 -O2 -Wall -o bin/prepare_nisr_sas_2026b bin/prepare_nisr_sas_2026b.cpp
bin/prepare_nisr_sas_2026b <path-to-report.pdf> data/nisr_sas_2026b_observations.csv
```

On Windows with MinGW-w64, build and run the `.exe`:

```powershell
g++ -std=c++17 -O2 -Wall -o bin/prepare_nisr_sas_2026b.exe bin/prepare_nisr_sas_2026b.cpp
bin\prepare_nisr_sas_2026b.exe "C:\path\to\report.pdf" "data\nisr_sas_2026b_observations.csv"
```

The utility invokes Poppler `pdftotext` (or the executable configured by `PDFTOTEXT_BIN`),
validates report pages and table layout, and writes the same UTF-8-BOM normalized CSV format.

Then import it transactionally:

```bash
php bin/import_nisr_sas_observations.php data/nisr_sas_2026b_observations.csv
```

The importer is idempotent and stores the numeric value separately from its original marker;
dash and blank cells remain missing, never zero. It imports district/crop yields from Table 11
as official yield evidence and selects this NISR dataset for scoring. Re-importing replaces that
dataset's Season B 2026 yield records without deleting other sources. Keep the source PDF outside
the public web directory if the deployed site serves the project root.

The Crop Advisor can display a time-to-first-harvest range when a sourced range has been entered.
The NISR report contains sowing dates but does not report crop maturity duration; do not infer
maturity from those dates. Any displayed duration should cite a Rwanda/RAB crop production guide
and account for cultivar, altitude, planting material, and management.

For example, use a dataset title only if that is the source actually obtained; replace all
placeholders with its actual citation and verified reuse terms. The source registry is generic: NISR, RAB, other government sources, and eligible public/open
datasets can be recorded and retained. NISR district yields are prioritized automatically.
Review citation, reuse terms, units, mappings, and aggregation methods before enabling any
non-NISR public dataset as a fallback in the admin crop-suitability page. Multiple verified
fallbacks can be enabled. For each district/crop/season, the advisor uses matching NISR evidence
first; otherwise it uses the usable record from the newest enabled fallback by dataset release
year (then import time). It never averages overlapping datasets. If published yield is present,
the advisor uses it; otherwise it derives yield from production divided by harvested area,
falling back to cultivated area only when harvested area is absent. Existing six-column imports
keep working. Crops without a usable public observation or a cited catalog yield are omitted
from scoring. Never import restricted, identifiable, or household-level records.

For an existing database, first apply `database/crop_expansion_food_provenance.sql` if its
agricultural source/statistics tables have not yet been created, then apply
`database/crop_statistics_multisource.sql` once after making a verified backup. This additive
migration preserves prior observations, marks existing datasets active to retain their current
behavior, and allows separate datasets to report the same district/crop/season/year. Fresh
databases use the current `schema.sql` and do not need either migration.

## Extracting data from NISR PDFs

An authenticated admin can open **PDF extraction** from the admin navigation. The tool accepts
PDFs up to 10 MB and produces review drafts only; it does not write transcribed values to the
database. LlamaParse and other hosted providers receive data only when selected; review their
current privacy, retention, pricing, and free-tier terms before uploading reports.

- **LlamaParse** parses the selected PDF pages directly, including scanned pages and complex
  tables, and returns Markdown. Poppler is not required for this AI extraction path. Add
  `LLAMA_CLOUD_API_KEY=<your key>` to the ignored local `nutrition-tool/.env` file or the server
  environment, then select LlamaParse in the admin form. The app uploads the PDF to LlamaCloud,
  starts a parse job, polls for the result, and requests deletion of the uploaded file afterward.
  A deletion failure is reported rather than hidden; also check LlamaCloud's current retention
  terms. The resulting Markdown is an unverified parser output, not approved data.
- **Optional local extraction:** install Poppler (`pdftotext`) and set `PDFTOTEXT_BIN` only if
  you want a local text copy or need to use Gemini without first running LlamaParse. Local
  extraction works on selectable text; scanned/image-only PDFs need an OCR provider such as
  LlamaParse.
- **Optional Gemini transcription:** add `GEMINI_API_KEY=<your key>` server-side and select
  Gemini to turn extracted text into draft JSON. When LlamaParse is selected, Gemini uses its
  Markdown output; otherwise Gemini uses Poppler-extracted text. The draft is instructed to
  preserve units, keep cultivated/harvested areas, production and yield separate, and use `null`
  for missing values. This is not guaranteed: compare every field, footnote, and page against the
  original report. Never put a real key in `.env.example`, source control, or browser code.
- Enter PDF viewer page numbers (such as `37-42`) to limit AI processing to at most 100 pages.
  Local-only extraction allows up to 20 pages. PDF viewer indices may differ from printed page
  labels. Larger AI ranges can take longer and use more provider quota. Gemini has a separate
  text-size limit, so a very large LlamaParse result may need to be split into smaller ranges
  before requesting Gemini transcription.
- Optional Groq fallback: configure `GROQ_API_KEY` server-side (and optionally `GROQ_MODEL` for
  an active model enabled for your account), then select the fallback checkbox. It runs only if
  Gemini returns a transient capacity, rate-limit, or server error. Groq receives extracted text
  from LlamaParse or Poppler, not the PDF itself. Its result is separately labeled and remains an
  unverified draft.
- Restart Apache/PHP after changing server environment configuration, then refresh the admin
  page. Only human-reviewed aggregates should be imported. Public external APIs, catalog URLs,
  and their provenance remain available; selecting an agricultural yield dataset only changes
  which stored crop statistics are used for yield scoring.

### Curated PDF-derived datasets

The following machine-readable datasets were transcribed from the NISR PDFs in
`public/assets/`. Each row retains report/table/page provenance and the source's missing-value
markers. The public **NISR data** page (`public/nisr_data.php`) provides district filtering for
Season A yields, nutrient selection for FBS values, and CPI-geography filtering across the monthly
trend. It also displays both urban category snapshots with their original units and source-report
links.

- [`data/nisr_sas_2026a_district_yields.csv`](data/nisr_sas_2026a_district_yields.csv) contains
  Table 13 of the *SAS 2026 Season A Final Report* (PDF page 35): 30 districts by 23 crop
  categories, reported in kg/ha. A dash remains a blank numeric value with
  `value_status=not_reported`. `crop_for_app` is populated only for crop categories that map
  directly to the app catalog; an empty mapping does not imply zero yield. This Season A
  dataset is separate from the existing Season B importer. To register its mapped, reported
  yields as Season A scoring evidence, run:

  ```bash
  php bin/import_nisr_sas_2026a_yields.php
  ```

  The import is transactional and idempotent for this source/year/season. It stores published
  yield values only; it does not invent area or production. Unmapped categories and missing
  cells are skipped. The Crop Advisor prefers matching NISR yield observations for the
  selected season; the Season B data remains available for Season B.
- [`data/nisr_fbs_2025_nutrient_supply.csv`](data/nisr_fbs_2025_nutrient_supply.csv) contains
  the 107 product/group rows and 23 nutrient columns from Food Balance Sheet 2025 Annexes 2-3
  (PDF pages 35-42), in long format. Values are national food-supply contributions per capita
  per day: minerals are mg/person/day; vitamin units follow the source table headers. They are
  not nutrient composition per 100 g of a particular food and must not be loaded into
  `nutrient_profiles`.
- [`data/nisr_cpi_2026_monthly_changes.csv`](data/nisr_cpi_2026_monthly_changes.csv) contains
  84 Urban, Rural, and Rwanda monthly/year-over-year CPI percentage changes for
  August 2025-September 2026 (NISR CPI report Table 1, PDF page 4). The September report is
  used for overlapping months; the August report supplies August 2025.
- [`data/nisr_cpi_2026_urban_category_changes.csv`](data/nisr_cpi_2026_urban_category_changes.csv)
  contains the 18 urban CPI summary categories for August and September 2026, including
  expenditure weights and monthly/year-over-year percentage changes (Table 2, PDF page 5).
  CPI changes and weights are aggregate context, not observed food prices in RWF per unit;
  they must not be imported into `prices`.

The August CPI report is used only where it adds data not present in the later report, avoiding
duplicate monthly observations. The source PDFs remain the authoritative references; compare
  transcribed values and labels against them before relying on these datasets. The FBS and CPI
  CSVs are displayed as contextual reference data only; neither is inserted into the food
  composition or market-price database tables.

## Crop Advisor architecture

```
public/crop_advisor.php  ->  api/crop_advisor.php  ->  includes/crop_scoring.php
                                                            |
                                                            +- soil_score, climate_score,
                                                            |  erosion control, nutrient balance,
                                                            |  soil structure (agro_suitability)
                                                            +- market_score (selected district's
                                                            |  observed price history)
                                                            +- yield evidence (aggregated
                                                            |  district/season crop statistics)
                                                            +- NISR yield score (relative
                                                            |  district yield where reported)
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
— see `available_crop_scoring_weights()` and `run_crop_scorer()` in `includes/crop_scoring.php`).
Dimensions without complete, cited district-specific evidence are assigned zero weight and the
remaining base weights are renormalized; the UI reports the included and omitted dimensions.
Climate fit uses the selected-season forecast: without a cited baseline, `climate = weather fit`;
with one, `climate = 0.70 x district climate baseline + 0.30 x weather fit`. Weather fit is
`0.60 x season-mean temperature fit + 0.40 x weekly-equivalent rainfall fit`.

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
database/crop_expansion_food_provenance.sql  Existing-database nutrition and price provenance migration
bin/import_district_crop_stats.php  CLI importer for cited district-level crop aggregates
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

> **Note on `bin/crop_scorer`:** the repository includes a Windows `.exe`; other operating
> systems must build the C++ source locally. Rebuild the executable after changing its source
> or when the binary is incompatible with the target machine.

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
| NISR data | SAS Season A and Season B 2026 mapped district/crop yield evidence and Season B reference observations are included; review the import and citation details above. |
| Market prices | [WFP Rwanda Food Prices](https://data.humdata.org/dataset/a4a84c1c-81d1-491b-9fbe-1955ae736508), sourced from the WFP Price Database and licensed CC BY-IGO. The importer accepts recent RWF retail values with compatible units and known districts. |
| Nutrient composition | Sixteen reviewed USDA [FoodData Central](https://fdc.nal.usda.gov/) records are mapped to catalog foods; their data are public domain under CC0 1.0. Some matches are explicitly labeled proxies. A cited Rwanda/regional food-composition table remains preferable when available. |
| Soil/climate suitability | No cited district-specific soil suitability or stewardship scores are currently available. The scorer excludes those dimensions until validated inputs are added. Preferred local source: RwaSIS (RAB). [ISRIC SoilGrids](https://soilgrids.org/) supplies soil properties, not crop-fit scores. |
| Expected weather forecast | [Open-Meteo Seasonal Forecast API](https://open-meteo.com/en/docs/seasonal-forecast-api), ECMWF SEAS5 ensemble mean, plus [Geocoding API](https://open-meteo.com/en/docs/geocoding-api); selected-season temperature and rainfall are scored against indicative crop ranges and cached up to 3 hours. Without a cited district climate baseline, this is provisional weather fit, not measured local suitability. |

### Importing reviewed USDA nutrient profiles

USDA FoodData Central provides food search and food-detail API endpoints. A free API key is
required; request one at the official [FDC API key signup](https://fdc.nal.usda.gov/api-key-signup.html),
then keep it in `nutrition-tool/.env` as `USDA_FDC_API_KEY`. Do not put the key in PHP source,
CSV files, browser code, or version control. The API's demo key is rate-limited and is not
configured as a production fallback.

From the `nutrition-tool` directory, run `php bin/import_fooddata_central_nutrients.php discover`
to create or refresh `data/fdc_nutrient_candidates.csv`. This command requires `USDA_FDC_API_KEY` to be set
in `nutrition-tool/.env`; without a valid key, no candidate file is created. Review the returned
descriptions and data types against the catalog food and its as-purchased/preparation state.
Set `approved` to `1` for at most one
appropriate FDC record per app food and enter its preparation state; leave uncertain or
unmatched foods unapproved. Then run
`php bin/import_fooddata_central_nutrients.php import data/fdc_nutrient_candidates.csv`.

The importer verifies the reviewed FDC ID and description, requires all 13 nutrient values and
expected units, and only updates an existing profile if it is already from FDC or a known
illustrative seed. Other cited/admin-entered profiles are preserved. FDC records are U.S.-focused
and can differ by variety, fortification, edible portion, and preparation; do not approve a
nearby-looking result without checking it. Missing API nutrients are not converted to zero.
Some FDC nutrient values may be calculated, imputed, or explicitly assumed zero rather than
analytically measured; inspect the selected record's derivation details before approving it.
The reviewed mappings currently used by the app are recorded in
`data/fdc_nutrient_approved.csv`; re-import them with
`php bin/import_fooddata_central_nutrients.php import data/fdc_nutrient_approved.csv`.
USDA requests attribution even though its data are public domain under CC0 1.0; the stored
profile records its FDC ID and source URL.

### Importing WFP Rwanda retail prices

Run `php bin/import_wfp_rwanda_prices.php` from the `nutrition-tool` directory to download the
public WFP Rwanda file from HDX and import observations from the trailing 365 days. For an
offline or repeatable import, pass a downloaded CSV path instead. The importer only accepts
RWF retail records with recognized WFP price flags, known Rwanda districts, positive prices,
and exact app-unit matches (KG to kg, L to litre); unsupported commodities and incompatible
rows are skipped. The current mapping deliberately excludes ambiguous items such as generic
bananas and dried fish. Repeated imports update changed observations and do not duplicate
existing WFP rows. Each imported row stores the HDX dataset citation and CC BY-IGO attribution;
manual observations and other cited sources are preserved.

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

AI assistance has been used in this repository, including assistance with implementation and
review of code and documentation changes. The team must accurately disclose every AI tool used
and the specific parts it assisted with in the final submission, verify the entire project,
replace this general statement with the complete disclosure, and accept responsibility for the
submitted work. Do not claim that AI-assisted work was written solely by the team.

## Submission readiness

The application code cannot establish participant eligibility, originality, IP assignment, or
team consent. Before the deadline, the team must:

1. Integrate at least one real, cited NISR dataset and explain its effect on a crop-planning
   result; verify terms of use and protect any restricted or sensitive data.
2. Add only cited, district-relevant crop, yield, soil, nutrient,
   and price data. Check units, food preparation state, dates, geographic coverage, missingness,
   and outliers; document every transformation. For survey data, use the publisher's sampling
   weights and estimation instructions before creating district aggregates.
3. Validate assumptions and recommendations with local agronomic experts and intended users.
   Evaluate rankings against a baseline and report limitations; do not claim impact without
   evidence.
4. Run the project from a clean setup, smoke-test all public pages and APIs, and resolve runtime
   or data errors. The repository has no checked-in automated test suite.
5. Deploy the app to an evaluator-accessible URL and confirm the repository is public. Neither
   a deployment URL nor proof of repository visibility is provided in this checkout.
6. Submit the required two-person eligible team declaration, confirm no member is disqualified
   by prior wins, acknowledge NISR's IP terms, and complete the AI-use/originality disclosure.
