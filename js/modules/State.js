export const appState = {
    currentFilters: {},
    currentResults: [],
    charts: {}
};

export const getElements = () => ({
    searchQuery: document.getElementById('search-query'),
    btnSearch: document.getElementById('btn-search'),
    btnFilter: document.getElementById('btn-filter'),
    btnClear: document.getElementById('btn-clear'),
    loadingIndicator: document.getElementById('loading'),
    tableBody: document.getElementById('results-table-body'),
    resultsSummary: document.getElementById('results-summary'),
    researcherSearch: document.getElementById('researcher-search'),
    btnSearchResearchers: document.getElementById('btn-search-researchers'),
    researchersResults: document.getElementById('researchers-results')
});

export const getFilters = () => ({
    type: document.getElementById('filter-type'),
    language: document.getElementById('filter-language'),
    institution: document.getElementById('filter-institution'),
    year: document.getElementById('filter-year'),
    yearFrom: document.getElementById('filter-year-from'),
    yearTo: document.getElementById('filter-year-to'),
    author: document.getElementById('filter-author')
});
