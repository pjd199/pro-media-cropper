/**
 * Pro Media Cropper - Stock Search
 *
 * Infinite-scroll stock image search with IntersectionObserver pagination.
 */

import { state, els } from './pmc-state.js';
import { loadSource } from './pmc-source.js';

// ── Public API ────────────────────────────────────────────────────────────────

/**
 * (Re)start a search from page 1.
 * Clears existing results and attaches a fresh IntersectionObserver sentinel.
 * Exposed on window for legacy compatibility (called from inline HTML onkeypress etc.).
 */
export function startNewSearch() {
    state.stockPage    = 1;
    state.stockLoading = false;

    els.stockResults.innerHTML = '<div id="pmc-stock-load-sentinel"></div>';
    const sentinel = els.stockResults.querySelector('#pmc-stock-load-sentinel');

    const obs = new IntersectionObserver(
        (entries) => {
            if (entries[0].isIntersecting && !state.stockLoading) fetchStock();
        },
        { root: els.stockResults, threshold: 0.1 }
    );
    obs.observe(sentinel);
    fetchStock();
}

// ── Internal ──────────────────────────────────────────────────────────────────

function fetchStock() {
    const query = els.stockQuery.value;
    if (!query || state.stockLoading) return;
    state.stockLoading = true;

    const sentinel = els.stockResults.querySelector('#pmc-stock-load-sentinel');
    sentinel.innerHTML = '<div class="pmc-spinner"></div>';

    const params = new URLSearchParams({
        query,
        provider: els.stockProvider.value,
        page:     state.stockPage,
    });

    fetch(`${pmc_vars.root}pmc/v1/search-stock?${params}`, {
        headers: { 'X-WP-Nonce': pmc_vars.nonce },
    })
    .then(r => r.ok ? r.json() : Promise.reject(r.status))
    .then(data => {
        if (data.length) {
            data.forEach(item => {
                const img   = document.createElement('img');
                img.src     = item.thumb;
                img.onclick = () => {
                    els.searchModal.style.display = 'none';
                    loadSource(item.full, query, item);
                };
                els.stockResults.insertBefore(img, sentinel);
            });
            state.stockPage++;
            state.stockLoading = false;
            sentinel.innerHTML = '';
        } else {
            sentinel.textContent = 'No more results.';
            state.stockLoading   = false;
        }
    })
    .catch(() => {
        sentinel.textContent = 'Search failed.';
        state.stockLoading   = false;
    });
}