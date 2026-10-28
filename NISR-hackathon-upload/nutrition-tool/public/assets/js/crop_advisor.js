/**
 * Fetches ranked crop suggestions from /api/crop_advisor.php for the
 * chosen district and renders them as cards.
 */
(function () {
    'use strict';

    const i18nElement = document.querySelector('meta[name="crop-advisor-i18n"]');
    const pageI18n = i18nElement ? JSON.parse(i18nElement.content) : {};
    const language = pageI18n.language || 'en';
    const messages = pageI18n.messages || {};
    const tr = (key, fallback) => messages[key] || fallback || key;
    const districtSelect = document.getElementById('district-select');
    const resultsEl = document.getElementById('crop-results');
    const apiUrl = new URL('../api/crop_advisor.php', window.location.href);

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML;
    }

    function erosionClass(risk) {
        if (risk === 'low') return 'erosion-low';
        if (risk === 'high') return 'erosion-high';
        return 'erosion-medium';
    }

    function harvestTime(crop) {
        const minimum = Number(crop.harvest_days_min);
        const maximum = Number(crop.harvest_days_max);
        if (!Number.isFinite(minimum) || !Number.isFinite(maximum) || minimum < 1 || maximum < minimum) {
            return '<span>' + tr('harvest_time', 'Planting to first harvest') + ': ' +
                tr('harvest_time_unknown', 'Harvest time not recorded') + '</span>';
        }
        return '<span>' + tr('harvest_time', 'Planting to first harvest') + ': ' +
            minimum.toLocaleString() + '–' + maximum.toLocaleString() + ' ' + tr('days', 'days') + '</span>' +
            '<small class="harvest-time-note">' +
            escapeHtml(tr('harvest_time_note', 'Indicative general range, not an NISR measurement; variety, altitude, planting material, and management affect timing. This information does not change the ranking.')) +
            '</small>';
    }

    function soilSourceLabel(source) {
        if (source === 'rwasis_import') return tr('source_rwasis', 'RwaSIS (RAB)');
        if (source === 'soilgrids_isric') return tr('source_soilgrids', 'SoilGrids (ISRIC)');
        if (source === 'nisr_import') return tr('source_nisr', 'NISR dataset');
        if (source === 'other_public_dataset') return tr('source_public_dataset', 'Other public dataset');
        return tr('source_illustrative', 'Illustrative estimate');
    }

    function marketEvidence(crop) {
        const count = Number(crop.market_observation_count || 0);
        const dates = Number(crop.market_observation_dates || 0);
        const sources = Array.isArray(crop.market_source_references)
            ? crop.market_source_references
            : [];
        const sourceMarkup = sources
            .map((source) => '<span class="source-reference">' + escapeHtml(source) + '</span>')
            .join('');
        if (dates < 2) {
            return '<span class="market-evidence is-limited">' + tr('market_unavailable', 'Local price trend unavailable') + ' (' +
                dates + ' ' + tr('observation_dates', 'observation dates') + '; ' + tr('neutral_score', 'score shown as neutral') + ')</span>' +
                sourceMarkup;
        }
        return '<span class="market-evidence">' + count + ' ' + tr('local_observations_across', 'local observations across') + ' ' +
            dates + ' ' + tr('dates_past_year', 'dates in the past year') + ' · ' +
            escapeHtml(crop.market_first_date || tr('date_unavailable', 'date unavailable')) + ' – ' +
            escapeHtml(crop.market_latest_date || tr('date_unavailable', 'date unavailable')) + '</span>' +
            sourceMarkup;
    }

    function nutritionBreakdownHtml(crop) {
        const groups = crop.nutrition_group_scores || {};
        const nutrients = crop.nutrition_per_hectare || {};
        const groupLabels = {
            'Energy & macros': tr('energy_macros', 'Energy & macros'),
            Minerals: tr('minerals', 'Minerals'),
            Vitamins: tr('vitamins', 'Vitamins'),
        };
        const groupMarkup = ['Energy & macros', 'Minerals', 'Vitamins']
            .filter((group) => Object.prototype.hasOwnProperty.call(groups, group))
            .map((group) => subscoreHtml(groupLabels[group], groups[group]))
            .join('');
        const nutrientRows = Object.keys(nutrients)
            .map((key) => {
                const nutrient = nutrients[key];
                return '<tr><th scope="row">' + escapeHtml(tr('nutrient_' + key, nutrient.label)) + '</th><td>' +
                    Number(nutrient.value).toLocaleString(undefined, { maximumFractionDigits: 3 }) +
                    ' ' + escapeHtml(nutrient.unit) + '/ha</td></tr>';
            })
            .join('');

        return (
            '<details class="nutrition-breakdown">' +
            '<summary>' + tr('nutrition_expected_harvest', 'Nutrition from expected harvest') + ' (' +
            Number(crop.harvest_tonnes_per_hectare).toLocaleString(undefined, { maximumFractionDigits: 2 }) +
            ' ' + tr('tonnes_per_hectare', 'tonnes/ha') + ')</summary>' +
            '<div class="nutrition-group-grid">' + groupMarkup + '</div>' +
            '<p>' + tr('harvest_explanation', 'Each amount below uses this crop’s full expected harvest and its linked food-composition profile. Score groups are balanced equally; nutrients with identical values across the compared crops do not affect the score.') + '</p>' +
            '<div class="nutrition-table-wrap"><table><thead><tr><th>' + tr('nutrient', 'Nutrient') + '</th><th>' + tr('estimated_harvest_total', 'Estimated total in harvest') + '</th></tr></thead><tbody>' +
            nutrientRows +
            '</tbody></table></div>' +
            (crop.nutrition_source_note
                ? '<small class="source-reference">' + escapeHtml(crop.nutrition_source_note) + '</small>'
                : '') +
            '<small>' + tr('yield_assumption', 'Estimates assume the full expected yield has the linked profile’s edible composition. They do not account for edible-yield share, cooking, storage, processing, or field losses.') + '</small>' +
            '</details>'
        );
    }

    function farmToFoodComparisonHtml(crops) {
        const comparedCrops = crops.slice(0, 3);
        if (comparedCrops.length === 0) return '';
        const rows = comparedCrops.map((crop) => {
            const nutrients = crop.nutrition_per_hectare || {};
            const energy = nutrients.calories_kcal;
            const protein = nutrients.protein_g;
            const yieldBasis = crop.has_observed_yield_evidence
                ? (crop.yield_source_publisher || tr('public_yield_source', 'Public source')) + ' · ' +
                    (crop.yield_first_survey_year === crop.yield_last_survey_year
                        ? String(crop.yield_first_survey_year)
                        : crop.yield_first_survey_year + '–' + crop.yield_last_survey_year)
                : tr('yield_assumption_source', 'Yield assumption; not an observed district result.');
            const foodBasis = String(crop.food_name || '').toLocaleLowerCase() !== String(crop.crop_name || '').toLocaleLowerCase()
                ? tr('food_profile_proxy', 'Mapped food-profile proxy: {food}; verify this local food form.')
                    .replace('{food}', escapeHtml(crop.food_name || ''))
                : tr('food_label_match', 'Catalog food label matches; local variety and preparation are not verified.');
            return '<tr>' +
                '<th scope="row">' + escapeHtml(crop.crop_name) + '</th>' +
                '<td>' + Number(crop.harvest_tonnes_per_hectare).toLocaleString(undefined, { maximumFractionDigits: 2 }) + ' ' +
                    escapeHtml(tr('tonnes_per_hectare', 'tonnes/ha')) + '</td>' +
                '<td>' + Number(crop.weather_score).toFixed(1) + ' / 100</td>' +
                '<td>' + (energy ? Number(energy.value).toLocaleString(undefined, { maximumFractionDigits: 2 }) + ' ' + escapeHtml(energy.unit) : '—') + '</td>' +
                '<td>' + (protein ? Number(protein.value).toLocaleString(undefined, { maximumFractionDigits: 2 }) + ' ' + escapeHtml(protein.unit) : '—') + '</td>' +
                '<td><small>' + escapeHtml(yieldBasis) + '<br>' + foodBasis + '</small></td>' +
                '</tr>';
        }).join('');
        return '<section class="farm-food-plan" aria-labelledby="farm-food-plan-title">' +
            '<h2 id="farm-food-plan-title">' + tr('farm_plan_heading', 'Farm-to-food comparison') + '</h2>' +
            '<p>' + tr('farm_plan_intro', 'Compare the three highest-ranked options using expected harvest, forecast fit, and estimated energy and protein per hectare. These are planning comparisons, not food-availability forecasts.') + '</p>' +
            '<div class="plan-table-wrap"><table><thead><tr>' +
            '<th scope="col">' + tr('crop_advisor', 'Crop') + '</th>' +
            '<th scope="col">' + tr('plan_expected_harvest', 'Expected harvest') + '</th>' +
            '<th scope="col">' + tr('plan_weather_fit', 'Weather fit') + '</th>' +
            '<th scope="col">' + tr('plan_energy', 'Energy / ha') + '</th>' +
            '<th scope="col">' + tr('plan_protein', 'Protein / ha') + '</th>' +
            '<th scope="col">' + tr('plan_evidence', 'Yield and food-profile basis') + '</th>' +
            '</tr></thead><tbody>' + rows + '</tbody></table></div>' +
            '<small>' + tr('plan_evidence_limit', 'Nutrition totals assume all harvested mass has the linked food profile; edible share, losses, storage, processing, and preparation are not modeled.') + '</small>' +
            '</section>';
    }

    function evidenceQualityHtml(crop, weather) {
        const yieldEvidence = crop.has_observed_yield_evidence
            ? tr('yield_record_source', 'Observed district yield from {publisher}, {years}. Historical, not a forecast.')
                .replace('{publisher}', escapeHtml(crop.yield_source_publisher || tr('public_yield_source', 'Public source')))
                .replace('{years}', escapeHtml(crop.yield_first_survey_year === crop.yield_last_survey_year
                    ? String(crop.yield_first_survey_year)
                    : crop.yield_first_survey_year + '–' + crop.yield_last_survey_year))
            : tr('yield_assumption_source', 'No matching observed district yield; ranking uses the crop yield assumption. Do not interpret it as an observed district result.');
        const foodName = String(crop.food_name || '');
        const cropName = String(crop.crop_name || '');
        const fdcRecordUrl = (String(crop.nutrition_source_note || '').match(/https:\/\/fdc\.nal\.usda\.gov\/food-details\/\d+\/nutrients/) || [])[0];
        const nutritionEvidence = crop.nutrition_source_note
            ? escapeHtml(crop.nutrition_source_note) +
                '<br>' + (foodName.toLocaleLowerCase() !== cropName.toLocaleLowerCase()
                    ? tr('food_profile_proxy', 'Mapped food-profile proxy: {food}; verify this local food form.')
                        .replace('{food}', escapeHtml(foodName))
                    : tr('food_label_match', 'Catalog food label matches; local variety and preparation are not verified.')) +
                (fdcRecordUrl
                    ? '<br><a href="' + escapeHtml(fdcRecordUrl) + '" target="_blank" rel="noopener noreferrer">' +
                        tr('open_fdc_record', 'Open USDA FoodData Central record') + '</a>'
                    : '')
            : tr('food_source_missing', 'No traceable food-composition citation is available.');
        const marketDates = Number(crop.market_observation_dates || 0);
        const marketEvidenceNote = marketDates >= 2
            ? tr('market_quality_note', '{dates} dated observations from {first} to {latest}. This is a price trend, not profitability; cost of production and market access are not included.')
                .replace('{dates}', String(marketDates))
                .replace('{first}', escapeHtml(crop.market_first_date || tr('date_unavailable', 'date unavailable')))
                .replace('{latest}', escapeHtml(crop.market_latest_date || tr('date_unavailable', 'date unavailable')))
            : tr('market_quality_missing', 'Fewer than two observation dates; market trend is unavailable and its score is neutral.');
        const coverage = weather && weather.selected_season
            ? Number(weather.selected_season.coverage_percent).toFixed(0)
            : '0';
        const forecastRetrieved = weather && weather.fetched_at
            ? String(weather.fetched_at).slice(0, 10)
            : tr('date_unavailable', 'date unavailable');
        const weatherEvidence = tr('weather_quality_note', 'Open-Meteo seasonal forecast covers {coverage}% of the selected season window. Weather fit uses indicative crop ranges, not a measured district suitability baseline.')
            .replace('{coverage}', coverage)
            .replace('{date}', escapeHtml(forecastRetrieved));

        return '<details class="evidence-quality">' +
            '<summary>' + tr('evidence_quality_heading', 'Evidence and limitations') + '</summary>' +
            '<p class="evidence-quality-intro">' + tr('evidence_quality_intro', 'Evidence checklist—not a statistical confidence score.') + '</p>' +
            '<ul>' +
            '<li><strong>' + tr('evidence_yield', 'Yield') + ':</strong> ' + yieldEvidence +
                (crop.yield_source_reference ? '<br><span class="source-reference">' + escapeHtml(crop.yield_source_reference) + '</span>' : '') + '</li>' +
            '<li><strong>' + tr('evidence_nutrition', 'Food composition') + ':</strong> ' + nutritionEvidence + '</li>' +
            '<li><strong>' + tr('evidence_market', 'Market history') + ':</strong> ' + marketEvidenceNote + '</li>' +
            '<li><strong>' + tr('evidence_weather', 'Weather') + ':</strong> ' + weatherEvidence + '</li>' +
            '<li><strong>' + tr('evidence_soil', 'Soil and stewardship') + ':</strong> ' +
                tr('soil_quality_missing', 'No cited district-specific soil suitability or stewardship scores; these dimensions are excluded.') + '</li>' +
            '</ul></details>';
    }

    async function loadResults(districtId, forecastSeason) {
        resultsEl.innerHTML = '<p class="empty-state">' + tr('loading', 'Loading…') + '</p>';

        try {
            const requestUrl = new URL(apiUrl.toString());
            requestUrl.searchParams.set('district_id', districtId);
            if (forecastSeason) {
                requestUrl.searchParams.set('forecast_season', forecastSeason);
            }
            const res = await fetch(requestUrl.toString());
            const data = await res.json();

            if (!res.ok) {
                resultsEl.innerHTML =
                    '<p class="empty-state">' + escapeHtml(language === 'rw'
                        ? tr('api_error', 'Could not load crop suggestions right now. Please try again.')
                        : (data.error || tr('load_error', 'Could not load suggestions right now.'))) + '</p>';
                return;
            }

            renderResults(data);
        } catch (err) {
            resultsEl.innerHTML = '<p class="empty-state">' + tr('load_error_retry', 'Could not load suggestions right now. Please try again.') + '</p>';
            console.error(err);
        }
    }

    function renderResults(data) {
        if (!data.results || data.results.length === 0) {
            resultsEl.innerHTML =
                '<p class="empty-state">' +
                escapeHtml(language === 'rw'
                    ? tr('no_suitability', 'No suitability data is available for this district yet.')
                    : (data.note || (tr('no_suitability_for', 'No suitability data yet for') + ' ' + (data.district ? data.district.name : tr('this_district', 'this district')) + '.'))) +
                '</p>';
            return;
        }

        const districtName = data.district ? escapeHtml(data.district.name) : '';
        const scoringWeights = data.results[0].scoring_weights || {};
        const dimensionIncluded = (name) => Number(scoringWeights[name] || 0) > 0;
        const dimensionLabels = {
            soil: tr('dimension_soil', 'Soil fit'),
            climate: tr('dimension_climate', 'Climate fit'),
            stewardship: tr('dimension_stewardship', 'Soil stewardship'),
        };
        const excludedDimensions = Object.keys(dimensionLabels)
            .filter((name) => !dimensionIncluded(name))
            .map((name) => dimensionLabels[name]);
        const includedWeights = [
            ['soil', tr('dimension_soil', 'Soil fit')],
            ['climate', tr('dimension_climate', 'Climate fit')],
            ['market', tr('local_price_trend', 'Local market price trend')],
            ['nutrition', tr('balanced_nutrition_ha', 'Balanced nutrition/ha')],
            ['stewardship', tr('dimension_stewardship', 'Soil stewardship')],
        ]
            .filter(([name]) => dimensionIncluded(name))
            .map(([name, label]) => label + ' ' + (Number(scoringWeights[name]) * 100).toFixed(0) + '%')
            .join(', ');
        const scoreWeightingNote = '<p class="result-note">' +
            tr('score_weighting_note', 'Base composite weights: {weights}. Weights are renormalized across included dimensions only. Observed yield and food-production priority adjustments are applied separately.')
                .replace('{weights}', includedWeights) +
            '</p>';
        const excludedDimensionsNote = excludedDimensions.length
            ? '<p class="result-note">' + tr('score_excluded_dimensions', 'Not scored because cited, complete district-specific data are unavailable:') +
                ' ' + excludedDimensions.map(escapeHtml).join(', ') + '.</p>'
            : '';
        const yieldSeasonNote = data.yield_season
            ? '<p class="result-note">' + tr('yield_season_note', 'District yield evidence is selected for {season}, where available; it is historical and not a planting forecast.')
                .replace('{season}', escapeHtml(data.yield_season)) + '</p>'
            : '';
        const yieldEvidenceCount = data.results.filter((crop) => crop.has_observed_yield_evidence).length;
        const yieldScoringNote = yieldEvidenceCount > 0
            ? '<p class="result-note">' + tr('observed_yield_scoring_note', 'NISR is preferred where it has a matching district/crop/season yield. Otherwise, the newest enabled public dataset with a matching observation is used. Selected observed yield contributes 40% before the 20% production-priority factor (32% of the final score when present). This is historical evidence, not a forecast or planting recommendation.') + '</p>'
            : '';
        const productionPriorityNote = '<p class="result-note">' +
            tr('production_priority_note', 'Production priority contributes 20% of the final score: grains, tubers, and legumes receive high priority; vegetables are down-ranked for large-scale planting. This planning preference does not change their nutrient values or soil/climate fit.') +
            '</p>';
        const weather = data.weather;
        const selectedSeason = weather ? weather.selected_season : null;
        const forecastSeasonOptions = weather ? Object.keys(weather.seasonal_forecast)
            .map((season) => {
                const forecast = weather.seasonal_forecast[season];
                return '<option value="' + escapeHtml(season) + '"' +
                    (season === weather.selected_forecast_season ? ' selected' : '') + '>' +
                    escapeHtml(forecast.label) + '</option>';
            })
            .join('') : '';
        const weatherPanel = weather
            ? '<aside class="weather-summary">' +
                '<strong>' + tr('expected_weather', 'Expected growing-season weather') + '</strong>' +
                '<label class="forecast-season-control" for="forecast-season-select">' + tr('score_crops_for', 'Score crops for') + '</label>' +
                '<select id="forecast-season-select">' + forecastSeasonOptions + '</select>' +
                '<p>' + escapeHtml(weather.district) + ' · ' + escapeHtml(selectedSeason.label) +
                ': ' + tr('average_temperature', 'average temperature') + ' ' + Number(selectedSeason.temperature_mean_c).toFixed(1) +
                ' °C; ' + tr('expected_rainfall', 'expected rainfall') + ' ' + Number(selectedSeason.precipitation_total_mm).toFixed(1) +
                ' mm ' + tr('across_days', 'across') + ' ' + Number(selectedSeason.forecast_days) + ' ' + tr('forecast_days', 'forecast days') + '.</p>' +
                '<p>' + tr('season_window', 'Season window') + ': ' + escapeHtml(selectedSeason.season_start) + ' ' + tr('to', 'to') + ' ' +
                escapeHtml(selectedSeason.season_end) + '. ' + tr('model_covers', 'Model covers') + ' ' +
                Number(selectedSeason.coverage_percent).toFixed(0) + '% (' +
                escapeHtml(selectedSeason.forecast_start) + ' to ' +
                escapeHtml(selectedSeason.forecast_end) + ').</p>' +
                '<p>' + tr('weather_scoring_explanation', 'Climate fit uses the public seasonal forecast compared with indicative crop ranges; without a cited district climate baseline, it is a provisional weather-based fit, not measured local suitability. Temperature contributes 60% and average weekly-equivalent rainfall 40% of the climate score. Local price trend compares date-averaged observations from the selected district over the past year; at least two distinct observation dates are required.') + '</p>' +
                '<small>' + tr('forecast_source', 'ECMWF SEAS5 ensemble-mean outlook from') + ' <a href="https://open-meteo.com/en/docs/seasonal-forecast-api" target="_blank" rel="noopener noreferrer">Open-Meteo</a>. ' + tr('forecast_caution', 'Seasonal forecasts are uncertain and updated monthly; use as planning guidance, not a planting guarantee.') + ' · CC BY 4.0</small>' +
                '</aside>'
            : '';

        resultsEl.innerHTML =
            '<p class="result-note">' + tr('exploratory_comparison', 'Exploratory comparison for') + ' ' + districtName + '. ' + tr('ranking_caution', 'Treat rankings as provisional; verify every input with local data and agronomic guidance before making planting decisions.') + '</p>' +
            scoreWeightingNote +
            excludedDimensionsNote +
            yieldSeasonNote +
            yieldScoringNote +
            productionPriorityNote +
            weatherPanel +
            farmToFoodComparisonHtml(data.results) +
            data.results
                .map((crop, i) => {
                    const rank = i + 1;
                    return (
                        '<div class="crop-card rank-' + rank + '">' +
                        '<div class="crop-card-head">' +
                        '<h2>' + escapeHtml(crop.crop_name) + '</h2>' +
                        '<div class="composite-score">' + Number(crop.composite_score).toFixed(1) + '<span class="unit"> / 100</span></div>' +
                        '</div>' +
                        '<div class="crop-card-rank">' + tr('rank', 'Rank') + ' #' + rank + ' · ' + districtName + '</div>' +
                        '<div class="subscore-grid">' +
                        (dimensionIncluded('soil')
                            ? subscoreHtml(tr('soil_fit', 'Soil fit'), crop.soil_score)
                            : '') +
                        (dimensionIncluded('climate')
                            ? subscoreHtml(tr('climate_fit', 'Climate fit'), crop.climate_score) +
                                subscoreHtml(tr('seasonal_weather_fit', 'Seasonal weather fit'), crop.weather_score)
                            : '') +
                        (dimensionIncluded('market')
                            ? subscoreHtml(tr('local_price_trend', 'Local market price trend'), crop.market_score)
                            : '') +
                        (dimensionIncluded('nutrition')
                            ? subscoreHtml(tr('balanced_nutrition_ha', 'Balanced nutrition/ha'), crop.nutrition_score)
                            : '') +
                        subscoreHtml(tr('production_priority', 'Food production priority'), crop.production_priority_score) +
                        (crop.has_observed_yield_evidence
                            ? subscoreHtml(tr('observed_yield_score', 'Observed district yield'), crop.observed_yield_score)
                            : '') +
                        (dimensionIncluded('stewardship')
                            ? subscoreHtml(tr('soil_stewardship', 'Soil stewardship'), crop.soil_stewardship_score)
                            : '') +
                        '</div>' +
                        '<div class="crop-meta-row">' +
                        (crop.erosion_risk
                            ? '<span class="erosion-badge ' + erosionClass(crop.erosion_risk) + '">' + escapeHtml(tr('risk_' + crop.erosion_risk, crop.erosion_risk)) + ' ' + tr('erosion_risk', 'erosion risk') + '</span>'
                            : '') +
                        harvestTime(crop) +
                        '<span>' + tr('expected_harvest', 'Expected harvest') + ': ' + Number(crop.harvest_tonnes_per_hectare).toLocaleString(undefined, { maximumFractionDigits: 2 }) + ' ' + tr('tonnes_per_hectare', 'tonnes/ha') + '</span>' +
                        (crop.yield_data_source
                            ? '<span>' + tr('yield_evidence', 'Yield evidence') + ': ' +
                                escapeHtml(crop.yield_source_publisher || tr('public_yield_source', 'Public source')) +
                                ' · ' + escapeHtml(crop.yield_data_source) + ' (' +
                                escapeHtml(String(crop.yield_first_survey_year)) + '–' +
                                escapeHtml(String(crop.yield_last_survey_year)) + ', ' +
                                Number(crop.yield_observation_years) + ' survey years)</span>' +
                                '<span class="source-reference">' + escapeHtml(crop.yield_source_reference) + '</span>'
                            : '<span class="crop-yield-note">' + escapeHtml(crop.crop_notes || tr('yield_illustrative', 'Yield estimate source is not recorded.')) + '</span>') +
                        (crop.soil_data_source
                            ? '<span class="soil-source">' + tr('soil_data', 'Soil data') + ': ' + escapeHtml(soilSourceLabel(crop.soil_data_source)) + '</span>'
                            : '') +
                        (crop.suitability_updated_at
                            ? '<span>' + tr('record_updated', 'Suitability record updated') + ': ' + escapeHtml(String(crop.suitability_updated_at).slice(0, 10)) + '</span>'
                            : '') +
                        marketEvidence(crop) +
                        (crop.source_reference ? '<span class="source-reference">' + escapeHtml(crop.source_reference) + '</span>' : '') +
                        (crop.fertilizer_note ? '<span>' + escapeHtml(crop.fertilizer_note) + '</span>' : '') +
                        '</div>' +
                        evidenceQualityHtml(crop, weather) +
                        nutritionBreakdownHtml(crop) +
                        (dimensionIncluded('stewardship') ? '<div class="soil-stewardship">' +
                        '<h3>' + tr('soil_indicators', 'Soil protection and recovery indicators') + '</h3>' +
                        '<ul>' +
                        '<li>' + tr('erosion_protection', 'Erosion protection') + ': ' + Number(crop.erosion_control_score).toFixed(1) + ' / 100</li>' +
                        '<li>' + tr('nutrient_balance', 'Nutrient balance / lower depletion potential') + ': ' + Number(crop.nutrient_balance_score).toFixed(1) + ' / 100</li>' +
                        '<li>' + tr('soil_structure', 'Soil structure and organic matter potential') + ': ' + Number(crop.soil_structure_score).toFixed(1) + ' / 100</li>' +
                        '</ul>' +
                        '<p>' + escapeHtml(crop.soil_stewardship_guidance || tr('stewardship_guidance', 'Protect soil with locally suitable ground cover, balanced nutrient management, and practices that maintain soil structure.')) + '</p>' +
                        '<h4>' + tr('suggested_rotation', 'Suggested rotation') + '</h4>' +
                        '<p>' + escapeHtml(crop.rotation_guidance || tr('rotation_guidance', 'Alternate crop families and include a locally suitable legume or cover crop where agronomically appropriate.')) + '</p>' +
                        '<span class="guidance-note">' + tr('provisional_guidance', 'These scores and rotations are provisional guidance; soil effects depend on local conditions and management.') + '</span>' +
                        '</div>' : '') +
                        '</div>'
                    );
                })
                .join('');
        const forecastSeasonSelect = document.getElementById('forecast-season-select');
        if (forecastSeasonSelect) {
            forecastSeasonSelect.addEventListener('change', () => {
                loadResults(districtSelect.value, forecastSeasonSelect.value);
            });
        }
    }

    function subscoreHtml(label, value) {
        return (
            '<div class="subscore">' +
            '<span class="label">' + escapeHtml(label) + '</span>' +
            '<span class="value">' + Number(value).toFixed(1) + '</span>' +
            '</div>'
        );
    }

    districtSelect.addEventListener('change', () => {
        if (districtSelect.value) {
            loadResults(districtSelect.value);
        } else {
            resultsEl.innerHTML = '<p class="empty-state">' + tr('district_prompt', 'Choose a district above to see ranked crop suggestions.') + '</p>';
        }
    });
})();
