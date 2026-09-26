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
    const apiUrl = new URL('../api/foods.php', window.location.href);
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

    loadResults();
})();
