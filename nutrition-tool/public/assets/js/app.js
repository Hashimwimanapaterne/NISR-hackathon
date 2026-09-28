/**
 * Loads ranked foods from the local read-only API and renders the results.
 * No framework or external runtime dependencies.
 */
(function () {
    'use strict';

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
    const meal = { foods: [], nutrients: [], portions: new Map() };
    let requestNumber = 0;
    let searchTimer;

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
            if (meal.foods.length === 0) {
                mealFoodSelect.innerHTML = '<option value="">No foods available</option>';
                mealStatus.textContent = 'No food nutrition profiles are available yet.';
                return;
            }

            mealFoodSelect.innerHTML = '<option value="">Choose a food…</option>' +
                meal.foods.map((food) =>
                    '<option value="' + Number(food.id) + '">' +
                    escapeHtml(food.name) + ' — ' + escapeHtml(food.category) +
                    '</option>'
                ).join('');
            mealFoodSelect.disabled = false;
            addMealFoodButton.disabled = false;
            mealStatus.textContent = 'Choose a food to add. Default portions are 100 g and can be adjusted.';
        } catch (error) {
            mealFoodSelect.innerHTML = '<option value="">Food data unavailable</option>';
            mealStatus.textContent = 'Could not load food nutrition data. Please refresh to try again.';
            console.error(error);
        }
    }

    function renderMeal() {
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
                '<label>Portion <span class="sr-only">for ' + escapeHtml(food.name) + '</span>' +
                '<input type="number" class="meal-portion" min="1" max="10000" step="1" inputmode="numeric" value="' + meal.portions.get(foodId) + '">' +
                '<span>g</span></label>' +
                '<button type="button" class="remove-food-button" data-remove-food="' + foodId + '" aria-label="Remove ' + escapeHtml(food.name) + '">Remove</button>' +
                '</div>'
            );
        }).join('');
        renderMealNutrition(selectedFoods);
    }

    function renderMealNutrition(selectedFoods) {
        if (selectedFoods.length === 0) {
            mealNutrition.innerHTML = '<p class="meal-empty">Add foods above to see their combined nutrient values.</p>';
            return;
        }

        mealNutrition.innerHTML =
            '<h3>Combined nutrition</h3>' +
            '<p class="meal-total-note">Total for ' + selectedFoods.length + (selectedFoods.length === 1 ? ' food' : ' foods') + ' in the selected portions</p>' +
            '<div class="daily-value-grid">' +
            meal.nutrients.map((nutrient) => {
                const amount = selectedFoods.reduce((sum, food) => {
                    return sum + Number(food.nutrients[nutrient.key] || 0) *
                        (meal.portions.get(Number(food.id)) / 100);
                }, 0);
                const dailyValue = dailyValues[nutrient.key];
                if (!dailyValue) return '';
                const percent = amount / dailyValue * 100;
                const decimals = nutrient.unit === 'kcal' ? 0 : 1;
                return (
                    '<article class="daily-value-card">' +
                    '<div class="daily-value-card-top"><h4>' + escapeHtml(nutrient.label) + '</h4>' +
                    '<span>' + formatNumber(amount, decimals) + ' ' + escapeHtml(nutrient.unit) + '</span></div>' +
                    '<div class="daily-value-track" role="progressbar" aria-label="' + escapeHtml(nutrient.label) + ' daily value" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + Math.min(100, percent).toFixed(0) + '">' +
                    '<span style="width:' + Math.min(100, Math.max(0, percent)).toFixed(1) + '%"></span></div>' +
                    '<p>' + formatNumber(percent, 0) + '% of ' + formatNumber(dailyValue, dailyValue < 10 ? 1 : 0) + ' ' + escapeHtml(nutrient.unit) + ' daily value</p>' +
                    '</article>'
                );
            }).join('') +
            '</div>';
    }

    function setStatus(message, status) {
        resultsCount.textContent = message;
        statusDot.classList.toggle('is-loading', status === 'loading');
        statusDot.classList.toggle('is-error', status === 'error');
    }

    function renderEmpty(title, message, retry) {
        const retryButton = retry
            ? '<button type="button" class="retry-button" id="retry-load">Try again</button>'
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
            '<tr><td colspan="5"><div class="loading-state"><span class="loader" aria-hidden="true"></span>Finding the best value foods…</div></td></tr>';
        setStatus('Loading foods…', 'loading');

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
            setStatus('Could not load foods', 'error');
            resultNote.textContent = 'The food list is temporarily unavailable.';
            renderEmpty('We could not reach the food data.', 'Check your connection and try again.', true);
            console.error(error);
        } finally {
            if (currentRequest === requestNumber) {
                resultsBody.setAttribute('aria-busy', 'false');
            }
        }
    }

    function renderResults(data) {
        nutrientHeadLabel.textContent = data.label + ' (' + data.unit + ') / 100 RWF';

        if (!Array.isArray(data.results) || data.results.length === 0) {
            setStatus('No matching foods', 'ready');
            resultNote.textContent = 'Try another search, category, or nutrient to explore more foods.';
            renderEmpty('No foods match this selection yet.', 'Change or clear your filters to see more foods.');
            return;
        }

        const categoryLabel = state.category ? ' in ' + state.category : '';
        const searchLabel = state.search ? ' matching “' + state.search + '”' : '';
        setStatus(data.count + (data.count === 1 ? ' food found' : ' foods found'), 'ready');
        resultNote.textContent =
            'Showing ' + data.label.toLowerCase() + ' value' + categoryLabel + searchLabel +
            ', based on each food’s latest recorded market price.';

        resultsBody.innerHTML = data.results.map((row, index) => {
            const rank = String(index + 1).padStart(2, '0');
            const topRank = index < 3 ? ' top-rank' : '';
            return (
                '<tr>' +
                '<td class="rank' + topRank + '" data-label="Rank">' + rank + '</td>' +
                '<td class="food-cell" data-label="Food">' +
                '<span class="food-name">' + escapeHtml(row.name) + '</span>' +
                '<span class="food-meta">' + escapeHtml(row.category) + '</span>' +
                '</td>' +
                '<td class="num price-value" data-label="Market price">' +
                formatNumber(row.price_rwf, 0) + ' RWF / ' + escapeHtml(row.unit_label) +
                '</td>' +
                '<td class="num" data-label="Cost / 100g">' +
                formatNumber(row.cost_per_100g, 1) + ' RWF' +
                '</td>' +
                '<td class="num nutrient-value" data-label="' + escapeHtml(data.label) + ' / 100 RWF">' +
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
        const foodId = Number(mealFoodSelect.value);
        if (!foodId || meal.portions.has(foodId)) return;
        meal.portions.set(foodId, 100);
        mealFoodSelect.value = '';
        renderMeal();
    });

    mealItems.addEventListener('input', (event) => {
        if (!event.target.matches('.meal-portion')) return;
        const foodId = Number(event.target.closest('[data-food-id]').dataset.foodId);
        const portion = Number(event.target.value);
        if (!Number.isFinite(portion) || portion <= 0 || portion > 10000) return;
        meal.portions.set(foodId, portion);
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
        meal.portions.delete(Number(button.dataset.removeFood));
        renderMeal();
    });

    loadResults();
    loadMealFoods();
})();
