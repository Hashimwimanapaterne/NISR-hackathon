/**
 * Fetches ranked crop suggestions from /api/crop_advisor.php for the
 * chosen district and renders them as cards.
 */
(function () {
    'use strict';

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

    function soilSourceLabel(source) {
        if (source === 'rwasis_import') return 'RwaSIS (RAB)';
        if (source === 'soilgrids_isric') return 'SoilGrids (ISRIC)';
        return 'Illustrative estimate';
    }

    function nutritionBreakdownHtml(crop) {
        const groups = crop.nutrition_group_scores || {};
        const nutrients = crop.nutrition_per_hectare || {};
        const groupMarkup = ['Energy & macros', 'Minerals', 'Vitamins']
            .filter((group) => Object.prototype.hasOwnProperty.call(groups, group))
            .map((group) => subscoreHtml(group, groups[group]))
            .join('');
        const nutrientRows = Object.keys(nutrients)
            .map((key) => {
                const nutrient = nutrients[key];
                return '<tr><th scope="row">' + escapeHtml(nutrient.label) + '</th><td>' +
                    Number(nutrient.value).toLocaleString(undefined, { maximumFractionDigits: 3 }) +
                    ' ' + escapeHtml(nutrient.unit) + '/ha</td></tr>';
            })
            .join('');

        return (
            '<details class="nutrition-breakdown">' +
            '<summary>Nutrition from expected harvest (' +
            Number(crop.harvest_tonnes_per_hectare).toLocaleString(undefined, { maximumFractionDigits: 2 }) +
            ' tonnes/ha)</summary>' +
            '<div class="nutrition-group-grid">' + groupMarkup + '</div>' +
            '<p>Each amount below uses this crop’s full expected harvest and its linked food-composition profile. Score groups are balanced equally; nutrients with identical values across the compared crops do not affect the score.</p>' +
            '<div class="nutrition-table-wrap"><table><thead><tr><th>Nutrient</th><th>Estimated total in harvest</th></tr></thead><tbody>' +
            nutrientRows +
            '</tbody></table></div>' +
            '<small>Estimates assume the full expected yield has the linked profile’s edible composition. They do not account for edible-yield share, cooking, storage, processing, or field losses.</small>' +
            '</details>'
        );
    }

    async function loadResults(districtId, forecastSeason) {
        resultsEl.innerHTML = '<p class="empty-state">Loading…</p>';

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
                    '<p class="empty-state">' + escapeHtml(data.error || 'Could not load suggestions right now.') + '</p>';
                return;
            }

            renderResults(data);
        } catch (err) {
            resultsEl.innerHTML = '<p class="empty-state">Could not load suggestions right now. Please try again.</p>';
            console.error(err);
        }
    }

    function renderResults(data) {
        if (!data.results || data.results.length === 0) {
            resultsEl.innerHTML =
                '<p class="empty-state">' +
                escapeHtml(data.note || ('No suitability data yet for ' + (data.district ? data.district.name : 'this district') + '.')) +
                '</p>';
            return;
        }

        const districtName = data.district ? escapeHtml(data.district.name) : '';
        const weather = data.weather;
        const selectedSeason = weather.selected_season;
        const forecastSeasonOptions = Object.keys(weather.seasonal_forecast)
            .map((season) => {
                const forecast = weather.seasonal_forecast[season];
                return '<option value="' + escapeHtml(season) + '"' +
                    (season === weather.selected_forecast_season ? ' selected' : '') + '>' +
                    escapeHtml(forecast.label) + '</option>';
            })
            .join('');
        const weatherPanel = weather
            ? '<aside class="weather-summary">' +
                '<strong>Expected growing-season weather</strong>' +
                '<label class="forecast-season-control" for="forecast-season-select">Score crops for</label>' +
                '<select id="forecast-season-select">' + forecastSeasonOptions + '</select>' +
                '<p>' + escapeHtml(weather.district) + ' · ' + escapeHtml(selectedSeason.label) +
                ': average temperature ' + Number(selectedSeason.temperature_mean_c).toFixed(1) +
                ' °C; expected rainfall ' + Number(selectedSeason.precipitation_total_mm).toFixed(1) +
                ' mm across ' + Number(selectedSeason.forecast_days) + ' forecast days.</p>' +
                '<p>Season window: ' + escapeHtml(selectedSeason.season_start) + ' to ' +
                escapeHtml(selectedSeason.season_end) + '. Model covers ' +
                Number(selectedSeason.coverage_percent).toFixed(0) + '% (' +
                escapeHtml(selectedSeason.forecast_start) + ' to ' +
                escapeHtml(selectedSeason.forecast_end) + ').</p>' +
                '<p>Seasonal weather fit is 30% of each crop’s climate subscore; the district climate baseline remains 70%. Fit compares season-average temperature (60%) and average weekly-equivalent rainfall (40%) with indicative crop ranges.</p>' +
                '<small>ECMWF SEAS5 ensemble-mean outlook from <a href="https://open-meteo.com/en/docs/seasonal-forecast-api" target="_blank" rel="noopener noreferrer">Open-Meteo</a>. Seasonal forecasts are uncertain and updated monthly; use as planning guidance, not a planting guarantee. · CC BY 4.0</small>' +
                '</aside>'
            : '';

        resultsEl.innerHTML =
            '<p class="result-note">Ranked suggestions for ' + districtName + '.</p>' +
            weatherPanel +
            data.results
                .map((crop, i) => {
                    const rank = i + 1;
                    return (
                        '<div class="crop-card rank-' + rank + '">' +
                        '<div class="crop-card-head">' +
                        '<h2>' + escapeHtml(crop.crop_name) + '</h2>' +
                        '<div class="composite-score">' + Number(crop.composite_score).toFixed(1) + '<span class="unit"> / 100</span></div>' +
                        '</div>' +
                        '<div class="crop-card-rank">Rank #' + rank + ' for ' + districtName + '</div>' +
                        '<div class="subscore-grid">' +
                        subscoreHtml('Soil fit', crop.soil_score) +
                        subscoreHtml('Climate fit', crop.climate_score) +
                        subscoreHtml('Seasonal weather fit', crop.weather_score) +
                        subscoreHtml('Market momentum', crop.market_score) +
                        subscoreHtml('Balanced nutrition/ha', crop.nutrition_score) +
                        subscoreHtml('Soil stewardship', crop.soil_stewardship_score) +
                        '</div>' +
                        '<div class="crop-meta-row">' +
                        '<span class="erosion-badge ' + erosionClass(crop.erosion_risk) + '">' + escapeHtml(crop.erosion_risk) + ' erosion risk</span>' +
                        '<span>Expected harvest: ' + Number(crop.harvest_tonnes_per_hectare).toLocaleString(undefined, { maximumFractionDigits: 2 }) + ' tonnes/ha</span>' +
                        '<span class="soil-source">Soil data: ' + escapeHtml(soilSourceLabel(crop.soil_data_source)) + '</span>' +
                        (crop.source_reference ? '<span class="source-reference">' + escapeHtml(crop.source_reference) + '</span>' : '') +
                        (crop.fertilizer_note ? '<span>' + escapeHtml(crop.fertilizer_note) + '</span>' : '') +
                        '</div>' +
                        nutritionBreakdownHtml(crop) +
                        '<div class="soil-stewardship">' +
                        '<h3>Soil protection and recovery indicators</h3>' +
                        '<ul>' +
                        '<li>Erosion protection: ' + Number(crop.erosion_control_score).toFixed(1) + ' / 100</li>' +
                        '<li>Nutrient balance / lower depletion potential: ' + Number(crop.nutrient_balance_score).toFixed(1) + ' / 100</li>' +
                        '<li>Soil structure and organic matter potential: ' + Number(crop.soil_structure_score).toFixed(1) + ' / 100</li>' +
                        '</ul>' +
                        '<p>' + escapeHtml(crop.soil_stewardship_guidance || 'Protect soil with locally suitable ground cover, balanced nutrient management, and practices that maintain soil structure.') + '</p>' +
                        '<h4>Suggested rotation</h4>' +
                        '<p>' + escapeHtml(crop.rotation_guidance || 'Alternate crop families and include a locally suitable legume or cover crop where agronomically appropriate.') + '</p>' +
                        '<span class="guidance-note">These scores and rotations are provisional guidance; soil effects depend on local conditions and management.</span>' +
                        '</div>' +
                        '</div>'
                    );
                })
                .join('');
        const forecastSeasonSelect = document.getElementById('forecast-season-select');
        forecastSeasonSelect.addEventListener('change', () => {
            loadResults(districtSelect.value, forecastSeasonSelect.value);
        });
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
            resultsEl.innerHTML = '<p class="empty-state">Choose a district above to see ranked crop suggestions.</p>';
        }
    });
})();
