/**
 * Loads ranked foods from the local read-only API and renders the results.
 * No framework or external runtime dependencies.
 */
(function () {
    'use strict';

    const i18n = window.nutritionI18n || { messages: {} };
    const tr = (key, fallback) => i18n.messages[key] || fallback || key;
    const state = { nutrient: 'protein_g', category: '', search: '' };
    const controlsBlock = document.getElementById('controls-block');
    const categorySelect = document.getElementById('category-select');
    const foodSearch = document.getElementById('food-search');
    const resetButton = document.getElementById('reset-filters');
    const resultsBody = document.getElementById('results-body');
    const resultNote = document.getElementById('result-note');
    const nutrientHeadLabel = document.getElementById('nutrient-head-label');
    const resultsCount = document.getElementById('results-count');
    const statusDot = document.querySelector('.status-dot');
    const mealFoodSelect = document.getElementById('meal-food-select');
    const addMealFoodButton = document.getElementById('add-meal-food');
    const mealStatus = document.getElementById('meal-status');
    const mealItems = document.getElementById('meal-items');
    const mealNutrition = document.getElementById('meal-nutrition');
    const apiUrl = new URL('../api/foods.php', window.location.href);
    const mealApiUrl = new URL('../api/food_nutrition.php', window.location.href);
    const dailyLogStorageKey = 'umurima-daily-food-log';
    const dailyValues = {
        calories_kcal: 2000,
        protein_g: 50,
        fat_g: 78,
        carbohydrates_g: 275,
        fiber_g: 28,
        iron_mg: 18,
        zinc_mg: 11,
        calcium_mg: 1300,
        potassium_mg: 4700,
        vitamin_a_ug: 900,
        vitamin_c_mg: 90,
        folate_ug: 400,
        vitamin_b12_ug: 2.4,
    };
    const upperLimits = {
        iron_mg: 45,
        zinc_mg: 40,
        calcium_mg: 2000,
        vitamin_c_mg: 2000,
    };
    const macronutrientRanges = {
        protein_g: { min: 10, max: 35, severeMin: 5, severeMax: 50, kcalPerGram: 4 },
        fat_g: { min: 20, max: 35, severeMin: 10, severeMax: 50, kcalPerGram: 9 },
        carbohydrates_g: { min: 45, max: 65, severeMin: 35, severeMax: 75, kcalPerGram: 4 },
    };
    const intakeNotes = {
        calories_kcal: 'A sustained energy shortfall can cause fatigue and unintended weight loss; a sustained surplus can contribute to weight gain.',
        protein_g: 'Persistently low protein can affect muscle maintenance and tissue repair. No upper limit is established for healthy adults.',
        fat_g: 'Persistently very low fat can affect essential-fat intake and absorption of vitamins A, D, E and K. Total fat has no established upper limit.',
        carbohydrates_g: 'A carbohydrate share outside the typical range is not a diagnosis; food quality and overall needs also matter.',
        fiber_g: 'Low fiber over time can contribute to constipation. Increase fiber gradually and drink fluids.',
        iron_mg: 'Low iron over time can lead to iron-deficiency anemia. High intake can be harmful; needs and limits vary by age and pregnancy.',
        zinc_mg: 'Low zinc can affect immune function and wound healing; too much over time can interfere with copper absorption.',
        calcium_mg: 'Persistently low calcium can affect bone health; high intake, especially from supplements, may increase kidney-stone risk.',
        potassium_mg: 'High potassium can be dangerous for people with kidney disease or certain medicines; food needs are individual.',
        vitamin_a_ug: 'Persistently low vitamin A can affect vision and immune function. The food data does not distinguish vitamin A forms for assessing excess.',
        vitamin_c_mg: 'Very low vitamin C over time can cause scurvy; excessive intake can cause digestive symptoms.',
        folate_ug: 'Low folate is a concern for blood health and pregnancy; high supplemental folic acid can mask vitamin B12 deficiency.',
        vitamin_b12_ug: 'Persistently low vitamin B12 can cause anemia and nerve problems. No upper limit is established.',
    };
    const meal = { foods: [], nutrients: [], portions: new Map() };
    let activeLogDate = getLocalDateString();
    let requestNumber = 0;
    let searchTimer;

    function getLocalDateString() {
        const date = new Date();
        return date.getFullYear() + '-' +
            String(date.getMonth() + 1).padStart(2, '0') + '-' +
            String(date.getDate()).padStart(2, '0');
    }

    function escapeHtml(value) {
        const node = document.createElement('span');
        node.textContent = value == null ? '' : String(value);
        return node.innerHTML;
    }

    function formatNumber(value, decimals) {
        const number = Number(value);
        if (value === null || value === undefined || !Number.isFinite(number)) return '—';
        return number.toLocaleString('en-US', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        });
    }

    async function loadMealFoods() {
        try {
            const response = await fetch(mealApiUrl.toString(), {
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) throw new Error('Meal food request failed with status ' + response.status);
            const data = await response.json();
            if (!Array.isArray(data.foods) || !Array.isArray(data.nutrients)) {
                throw new Error('Meal food response has an invalid format');
            }

            meal.foods = data.foods;
            meal.nutrients = data.nutrients;
            const restored = restoreDailyLog();
            if (meal.foods.length === 0) {
                mealFoodSelect.innerHTML = '<option value="">' + tr('no_foods', 'No foods available') + '</option>';
                mealStatus.textContent = tr('no_profiles', 'No food nutrition profiles are available yet.');
                return;
            }

            mealFoodSelect.innerHTML = '<option value="">' + tr('choose_food_option', 'Choose a food…') + '</option>' +
                meal.foods.map((food) =>
                    '<option value="' + Number(food.id) + '">' +
                    escapeHtml(food.name) + ' — ' + escapeHtml(food.category) +
                    '</option>'
                ).join('');
            mealFoodSelect.disabled = false;
            addMealFoodButton.disabled = false;
            if (restored) {
                mealStatus.textContent = meal.portions.size > 0
                    ? tr('restored_log', 'Today’s food log restored. Adjust portions or add more foods; it resets tomorrow.')
                    : tr('start_logging', 'Log foods as you eat them today. Default portions are 100 g and can be adjusted.');
            }
            renderMeal();
        } catch (error) {
            mealFoodSelect.innerHTML = '<option value="">' + tr('food_unavailable', 'Food data unavailable') + '</option>';
            mealStatus.textContent = tr('load_food_error', 'Could not load food nutrition data. Please refresh to try again.');
            console.error(error);
        }
    }

    function restoreDailyLog() {
        try {
            const saved = localStorage.getItem(dailyLogStorageKey);
            if (!saved) return true;
            const log = JSON.parse(saved);
            if (!log || typeof log !== 'object' || !Array.isArray(log.portions)) {
                throw new Error('Saved daily food log has an invalid format');
            }
            if (log.date !== activeLogDate) return true;

            const availableFoodIds = new Set(meal.foods.map((food) => Number(food.id)));
            log.portions.forEach((entry) => {
                if (!Array.isArray(entry) || entry.length !== 2) {
                    throw new Error('Saved daily food log contains an invalid portion');
                }
                const foodId = Number(entry[0]);
                const portion = Number(entry[1]);
                if (!Number.isInteger(foodId) || !availableFoodIds.has(foodId) ||
                    !Number.isFinite(portion) || portion <= 0 || portion > 10000) {
                    throw new Error('Saved daily food log contains an invalid food or portion');
                }
                meal.portions.set(foodId, portion);
            });
            return true;
        } catch (error) {
            meal.portions.clear();
            mealStatus.textContent = tr('restore_error', 'Could not restore today’s saved food log. You can start a new log.');
            console.error(error);
            return false;
        }
    }

    function saveDailyLog() {
        try {
            localStorage.setItem(dailyLogStorageKey, JSON.stringify({
                date: activeLogDate,
                portions: Array.from(meal.portions.entries()),
            }));
            return true;
        } catch (error) {
            mealStatus.textContent = tr('save_error', 'Today’s log could not be saved in this browser; it will be lost when you leave this page.');
            console.error(error);
            return false;
        }
    }

    function resetLogIfNewDay() {
        const today = getLocalDateString();
        if (today === activeLogDate) return false;
        activeLogDate = today;
        meal.portions.clear();
        if (saveDailyLog()) {
            mealStatus.textContent = tr('new_day', 'A new day has started. The daily food log has been reset.');
        }
        return true;
    }

    function renderMeal() {
        resetLogIfNewDay();
        const selectedFoods = meal.foods.filter((food) => meal.portions.has(Number(food.id)));
        Array.from(mealFoodSelect.options).forEach((option) => {
            if (option.value) option.disabled = meal.portions.has(Number(option.value));
        });
        mealItems.innerHTML = selectedFoods.map((food) => {
            const foodId = Number(food.id);
            return (
                '<div class="meal-item" data-food-id="' + foodId + '">' +
                '<div class="meal-item-name"><strong>' + escapeHtml(food.name) + '</strong>' +
                '<span>' + escapeHtml(food.category) + '</span></div>' +
                '<label>' + tr('portion', 'Portion') + ' <span class="sr-only">for ' + escapeHtml(food.name) + '</span>' +
                '<input type="number" class="meal-portion" min="1" max="10000" step="1" inputmode="numeric" value="' + meal.portions.get(foodId) + '">' +
                '<span>g</span></label>' +
                '<button type="button" class="remove-food-button" data-remove-food="' + foodId + '" aria-label="' + escapeHtml(tr('remove', 'Remove')) + ' ' + escapeHtml(food.name) + '">' + tr('remove', 'Remove') + '</button>' +
                '</div>'
            );
        }).join('');
        renderMealNutrition(selectedFoods);
    }

    function renderMealNutrition(selectedFoods) {
        if (selectedFoods.length === 0) {
            mealNutrition.innerHTML = '<p class="meal-empty">' + tr('meal_empty', 'Log foods above to see today’s nutrient totals.') + '</p>';
            return;
        }

        mealNutrition.innerHTML =
            '<h3>' + tr('today_nutrition', 'Today’s nutrition') + '</h3>' +
            '<p class="meal-total-note">' + tr('daily_total', 'Daily total from') + ' ' + selectedFoods.length + ' ' + (selectedFoods.length === 1 ? tr('logged_food', 'logged food') : tr('logged_foods', 'logged foods')) + '</p>' +
            '<div class="daily-value-grid">' +
            meal.nutrients.map((nutrient) => {
                const amount = selectedFoods.reduce((sum, food) => {
                    return sum + Number(food.nutrients[nutrient.key] || 0) *
                        (meal.portions.get(Number(food.id)) / 100);
                }, 0);
                const dailyValue = dailyValues[nutrient.key];
                if (!dailyValue) return '';
                const percent = amount / dailyValue * 100;
                const assessment = assessDailyIntake(nutrient.key, percent, amount, selectedFoods);
                const decimals = nutrient.unit === 'kcal' ? 0 : 1;
                const detail = macronutrientRanges[nutrient.key]
                    ? ' ' + formatNumber(assessment.energyShare, 0) + tr('logged_calorie_share', '% of logged calories')
                    : '';
                const nutrientLabel = tr('nutrient_' + nutrient.key, nutrient.label);
                return (
                    '<article class="daily-value-card daily-value-' + assessment.status + '">' +
                    '<div class="daily-value-card-top"><h4>' + escapeHtml(nutrientLabel) + '</h4>' +
                    '<span>' + formatNumber(amount, decimals) + ' ' + escapeHtml(nutrient.unit) + '</span></div>' +
                    '<div class="daily-value-track" role="progressbar" aria-label="' + escapeHtml(nutrientLabel) + ' ' + tr('progress_label', 'daily value') + '" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + Math.min(100, percent).toFixed(0) + '">' +
                    '<span style="width:' + Math.min(100, Math.max(0, percent)).toFixed(1) + '%"></span></div>' +
                    '<p><strong>' + assessment.label + '</strong> — ' + formatNumber(percent, 0) + '% ' + tr('of_reference', 'of') + ' ' + formatNumber(dailyValue, dailyValue < 10 ? 1 : 0) + ' ' + escapeHtml(nutrient.unit) + ' ' + tr('unit_portion', 'daily value') + detail + '</p>' +
                    (assessment.status !== 'on-target' && assessment.status !== 'neutral'
                        ? '<p class="daily-value-impact">' + escapeHtml(tr('intake_note_' + nutrient.key, intakeNotes[nutrient.key])) + '</p>'
                        : '') +
                    '</article>'
                );
            }).join('') +
            '</div>';
    }

    function assessDailyIntake(nutrientKey, percent, amount, selectedFoods) {
        const calories = selectedFoods.reduce((sum, food) => {
            return sum + Number(food.nutrients.calories_kcal || 0) *
                (meal.portions.get(Number(food.id)) / 100);
        }, 0);
        const macroRange = macronutrientRanges[nutrientKey];

        if (macroRange && calories > 0) {
            const energyShare = amount * macroRange.kcalPerGram / calories * 100;
            if (energyShare < macroRange.severeMin) return { status: 'low', label: tr('very_low_calorie_share', 'Very low calorie share'), energyShare };
            if (energyShare > macroRange.severeMax) return { status: 'high', label: tr('very_high_calorie_share', 'Very high calorie share'), energyShare };
            if (energyShare < macroRange.min) return { status: 'watch', label: tr('below_typical_range', 'Below typical range'), energyShare };
            if (energyShare > macroRange.max) return { status: 'watch', label: tr('above_typical_range', 'Above typical range'), energyShare };
            return { status: 'on-target', label: tr('within_typical_range', 'Within typical range'), energyShare };
        }

        if (macroRange && calories <= 0) {
            return { status: 'neutral', label: tr('log_calories', 'Log calories to assess share'), energyShare: 0 };
        }
        if (nutrientKey === 'calories_kcal') {
            if (percent < 50) return { status: 'low', label: tr('very_low_energy', 'Very low daily energy total'), energyShare: 0 };
            if (percent < 80) return { status: 'watch', label: tr('below_energy_reference', 'Below daily energy reference'), energyShare: 0 };
            if (percent > 125) return { status: 'watch', label: tr('above_energy_reference', 'Above daily energy reference'), energyShare: 0 };
            return { status: 'on-target', label: tr('near_energy_reference', 'Near daily energy reference'), energyShare: 0 };
        }

        const upperLimit = upperLimits[nutrientKey];
        if (upperLimit && amount >= upperLimit) {
            const label = nutrientKey === 'calcium_mg'
                ? tr('upper_limit_reached', 'Conservative adult upper limit reached')
                : tr('at_upper_limit', 'At or above adult upper limit');
            return { status: 'high', label, energyShare: 0 };
        }
        if (percent < 50) return { status: 'low', label: tr('low_daily_total', 'Low daily total'), energyShare: 0 };
        if (percent < 80) return { status: 'watch', label: tr('below_daily_reference', 'Below daily reference'), energyShare: 0 };
        return { status: 'on-target', label: tr('daily_reference_met', 'Daily reference met'), energyShare: 0 };
    }

    function setStatus(message, status) {
        resultsCount.textContent = message;
        statusDot.classList.toggle('is-loading', status === 'loading');
        statusDot.classList.toggle('is-error', status === 'error');
    }

    function renderEmpty(title, message, retry) {
        const retryButton = retry
            ? '<button type="button" class="retry-button" id="retry-load">' + tr('try_again', 'Try again') + '</button>'
            : '';
        resultsBody.innerHTML =
            '<tr><td colspan="5"><div class="empty-state">' +
            '<strong>' + escapeHtml(title) + '</strong>' +
            escapeHtml(message) + retryButton +
            '</div></td></tr>';
        if (retry) {
            document.getElementById('retry-load').addEventListener('click', loadResults);
        }
    }

    async function loadResults() {
        const currentRequest = ++requestNumber;
        resultsBody.setAttribute('aria-busy', 'true');
        resultsBody.innerHTML =
            '<tr><td colspan="5"><div class="loading-state"><span class="loader" aria-hidden="true"></span>' + tr('finding_foods', 'Finding the best value foods…') + '</div></td></tr>';
        setStatus(tr('loading_foods', 'Loading foods…'), 'loading');

        const params = new URLSearchParams({ nutrient: state.nutrient, limit: '100' });
        if (state.category) params.set('category', state.category);
        if (state.search) params.set('q', state.search);

        try {
            const response = await fetch(apiUrl.toString() + '?' + params.toString(), {
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) throw new Error('Food data request failed with status ' + response.status);

            const data = await response.json();
            if (currentRequest !== requestNumber) return;
            renderResults(data);
        } catch (error) {
            if (currentRequest !== requestNumber) return;
            setStatus(tr('could_not_load', 'Could not load foods'), 'error');
            resultNote.textContent = tr('temporarily_unavailable', 'The food list is temporarily unavailable.');
            renderEmpty(tr('data_error', 'We could not reach the food data.'), tr('connection_error', 'Check your connection and try again.'), true);
            console.error(error);
        } finally {
            if (currentRequest === requestNumber) {
                resultsBody.setAttribute('aria-busy', 'false');
            }
        }
    }

    function renderResults(data) {
        const nutrientLabel = tr('nutrient_' + state.nutrient, data.label);
        nutrientHeadLabel.textContent = nutrientLabel + ' (' + data.unit + ') / 100 RWF';

        if (!Array.isArray(data.results) || data.results.length === 0) {
            setStatus(tr('no_matching_foods', 'No matching foods'), 'ready');
            resultNote.textContent = tr('search_hint', 'Try another search, category, or nutrient to explore more foods.');
            renderEmpty(tr('no_foods_match', 'No foods match this selection yet.'), tr('change_filters', 'Change or clear your filters to see more foods.'));
            return;
        }

        const categoryLabel = state.category ? tr('in_category', ' in ') + state.category : '';
        const searchLabel = state.search ? tr('matching', ' matching ') + '“' + state.search + '”' : '';
        setStatus(data.count + ' ' + (data.count === 1 ? tr('food_found', 'food found') : tr('foods_found', 'foods found')), 'ready');
        resultNote.textContent =
            tr('showing_value', 'Showing') + ' ' + nutrientLabel.toLowerCase() + ' ' + tr('value_suffix', 'value') + categoryLabel + searchLabel +
            ', ' + tr('based_on_price', 'based on each food’s latest recorded market price. Check market, date, and source for local relevance.');
        if (data.results.some((row) => row.source === 'sample_placeholder')) {
            resultNote.textContent += ' ' + tr('sample_warning', 'Sample placeholder prices are not live observations and must not be used for purchasing or policy decisions.');
        }

        resultsBody.innerHTML = data.results.map((row, index) => {
            const rank = String(index + 1).padStart(2, '0');
            const topRank = index < 3 ? ' top-rank' : '';
            const sourceLabels = {
                sample_placeholder: tr('sample_source', 'Sample placeholder (not observed)'),
                manual: tr('direct_observation', 'Direct market observation'),
                manual_observation: tr('direct_observation', 'Direct market observation'),
                esoko_import: tr('source_esoko', 'e-Soko'),
                nisr_dataset: tr('source_nisr', 'NISR dataset'),
                other_public_dataset: tr('public_dataset', 'Public dataset'),
            };
            const observationDate = new Date(row.recorded_on + 'T00:00:00');
            const ageDays = Number.isNaN(observationDate.getTime())
                ? null
                : Math.floor((new Date().setHours(0, 0, 0, 0) - observationDate.getTime()) / 86400000);
            const freshnessClass = ageDays === null ? '' : ageDays > 90 ? ' is-stale' : ageDays > 30 ? ' is-aging' : '';
            const priceContext = [
                row.market_name,
                row.district,
                row.recorded_on,
                sourceLabels[row.source] || row.source || tr('source_unknown', 'Source unknown'),
            ].filter(Boolean).map(escapeHtml).join(' · ');
            const sourceReference = row.source_reference
                ? '<span class="price-source-reference">' + escapeHtml(row.source_reference) + '</span>'
                : '';
            const nutritionProvenance = [
                row.preparation_state,
                row.source_note,
            ].filter(Boolean).map(escapeHtml).join(' · ');
            return (
                '<tr>' +
                '<td class="rank' + topRank + '" data-label="' + tr('rank', 'Rank') + '">' + rank + '</td>' +
                '<td class="food-cell" data-label="' + tr('food_label', 'Food') + '">' +
                '<span class="food-name">' + escapeHtml(row.name) + '</span>' +
                '<span class="food-meta">' + escapeHtml(row.category) + '</span>' +
                '<span class="food-meta">' + nutritionProvenance + '</span>' +
                '</td>' +
                '<td class="num price-value" data-label="' + tr('market_price', 'Market price') + '">' +
                formatNumber(row.price_rwf, 0) + ' RWF / ' + escapeHtml(row.unit_label) +
                '<span class="price-meta' + freshnessClass + '">' + priceContext + '</span>' +
                sourceReference +
                '</td>' +
                '<td class="num" data-label="' + tr('cost_100g', 'Cost / 100g') + '">' +
                formatNumber(row.cost_per_100g, 1) + ' RWF' +
                '</td>' +
                '<td class="num nutrient-value" data-label="' + escapeHtml(nutrientLabel) + ' / 100 RWF">' +
                formatNumber(row.nutrient_per_100rwf, 2) +
                '</td>' +
                '</tr>'
            );
        }).join('');
    }

    function setActiveNutrient(nutrient) {
        state.nutrient = nutrient;
        controlsBlock.querySelectorAll('button[data-nutrient]').forEach((button) => {
            const selected = button.dataset.nutrient === nutrient;
            button.classList.toggle('active', selected);
            button.setAttribute('aria-pressed', selected ? 'true' : 'false');
        });
        loadResults();
    }

    controlsBlock.addEventListener('click', (event) => {
        const button = event.target.closest('button[data-nutrient]');
        if (button && button.dataset.nutrient) setActiveNutrient(button.dataset.nutrient);
    });

    categorySelect.addEventListener('change', () => {
        state.category = categorySelect.value;
        loadResults();
    });

    foodSearch.addEventListener('input', () => {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => {
            state.search = foodSearch.value.trim();
            loadResults();
        }, 250);
    });

    resetButton.addEventListener('click', () => {
        window.clearTimeout(searchTimer);
        categorySelect.value = '';
        foodSearch.value = '';
        state.category = '';
        state.search = '';
        setActiveNutrient('protein_g');
    });

    addMealFoodButton.addEventListener('click', () => {
        resetLogIfNewDay();
        const foodId = Number(mealFoodSelect.value);
        if (!foodId || meal.portions.has(foodId)) return;
        meal.portions.set(foodId, 100);
        mealFoodSelect.value = '';
        saveDailyLog();
        renderMeal();
    });

    mealItems.addEventListener('input', (event) => {
        if (!event.target.matches('.meal-portion')) return;
        if (resetLogIfNewDay()) {
            renderMeal();
            return;
        }
        const foodId = Number(event.target.closest('[data-food-id]').dataset.foodId);
        const portion = Number(event.target.value);
        if (!Number.isFinite(portion) || portion <= 0 || portion > 10000) return;
        meal.portions.set(foodId, portion);
        saveDailyLog();
        renderMealNutrition(meal.foods.filter((food) => meal.portions.has(Number(food.id))));
    });

    mealItems.addEventListener('change', (event) => {
        if (!event.target.matches('.meal-portion')) return;
        const foodId = Number(event.target.closest('[data-food-id]').dataset.foodId);
        const portion = Number(event.target.value);
        if (!Number.isFinite(portion) || portion <= 0 || portion > 10000) {
            event.target.value = meal.portions.get(foodId);
        }
    });

    mealItems.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-food]');
        if (!button) return;
        if (resetLogIfNewDay()) {
            renderMeal();
            return;
        }
        meal.portions.delete(Number(button.dataset.removeFood));
        saveDailyLog();
        renderMeal();
    });

    loadResults();
    loadMealFoods();
})();
