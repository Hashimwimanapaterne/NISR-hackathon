/**
 * Fetches ranked foods from /api/foods.php and renders them.
 * No frameworks — kept dependency-free for easy deployment.
 */
(function () {
    'use strict';

    const state = {
        nutrient: 'protein_g',
        category: '',
    };

    const tabsEl = document.getElementById('nutrient-tabs');
    const categorySelect = document.getElementById('category-select');
    const resultsBody = document.getElementById('results-body');
    const resultNote = document.getElementById('result-note');
    const nutrientHeadLabel = document.getElementById('nutrient-head-label');

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML;
    }

    function formatNumber(value, decimals) {
        if (value === null || value === undefined) return '—';
        return Number(value).toLocaleString('en-US', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        });
    }

    async function loadResults() {
        resultsBody.innerHTML = '<tr><td colspan="5">Loading…</td></tr>';

        const params = new URLSearchParams({ nutrient: state.nutrient, limit: '20' });
        if (state.category) params.set('category', state.category);

        try {
            const res = await fetch('/api/foods.php?' + params.toString());
            if (!res.ok) throw new Error('Request failed: ' + res.status);
            const data = await res.json();
            renderResults(data);
        } catch (err) {
            resultsBody.innerHTML =
                '<tr><td colspan="5"><div class="empty-state">' +
                'Could not load data right now. Please try again in a moment.' +
                '</div></td></tr>';
            console.error(err);
        }
    }

    function renderResults(data) {
        nutrientHeadLabel.textContent = data.label + ' per 100 RWF';

        if (!data.results || data.results.length === 0) {
            resultsBody.innerHTML =
                '<tr><td colspan="5"><div class="empty-state">' +
                'No foods found for this filter yet. Try a different category.' +
                '</div></td></tr>';
            resultNote.textContent = '';
            return;
        }

        resultNote.textContent =
            'Ranked by ' + data.label.toLowerCase() + ' delivered per 100 RWF spent, using the latest recorded price for each food.';

        resultsBody.innerHTML = data.results
            .map((row, i) => {
                return (
                    '<tr>' +
                    '<td class="rank">' + (i + 1) + '</td>' +
                    '<td>' +
                    '<span class="food-name">' + escapeHtml(row.name) + '</span>' +
                    '<span class="food-meta">' + escapeHtml(row.category) + '</span>' +
                    '</td>' +
                    '<td class="num">' + formatNumber(row.price_rwf, 0) + ' RWF/' + escapeHtml(row.unit_label) + '</td>' +
                    '<td class="num">' + formatNumber(row.cost_per_100g, 1) + ' RWF</td>' +
                    '<td class="num nutrient-value">' + formatNumber(row.nutrient_per_100rwf, 2) + '</td>' +
                    '</tr>'
                );
            })
            .join('');
    }

    function setActiveTab(nutrient) {
        state.nutrient = nutrient;
        [...tabsEl.querySelectorAll('button')].forEach((btn) => {
            btn.classList.toggle('active', btn.dataset.nutrient === nutrient);
        });
        loadResults();
    }

    tabsEl.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-nutrient]');
        if (!btn) return;
        setActiveTab(btn.dataset.nutrient);
    });

    categorySelect.addEventListener('change', () => {
        state.category = categorySelect.value;
        loadResults();
    });

    loadResults();
})();
