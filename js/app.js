import { appState, getElements, getFilters } from './modules/State.js';
import { ApiService } from './modules/API.js';

document.addEventListener('DOMContentLoaded', () => {
    const elements = getElements();
    const filters = getFilters();

    // Inicialização
    init();

    async function init() {
        loadFilterOptions();
        setupEventListeners();
        fetchResults(); // Carrega resultados iniciais
    }

    function setupEventListeners() {
        // Busca principal
        elements.searchQuery?.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') fetchResults();
        });
        elements.btnSearch?.addEventListener('click', fetchResults);
        elements.btnFilter?.addEventListener('click', fetchResults);
        elements.btnClear?.addEventListener('click', clearFilters);

        // Busca de pesquisadores
        elements.researcherSearch?.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') searchResearchers();
        });
        elements.btnSearchResearchers?.addEventListener('click', searchResearchers);

        // Exportação
        document.querySelectorAll('[data-format]').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                exportData(e.target.dataset.format);
            });
        });

        // Abas e Navegação
        const statsTab = document.getElementById('nav-stats-tab');
        statsTab?.addEventListener('shown.bs.tab', loadStatistics);
    }

    async function loadFilterOptions() {
        const filterFields = ['type', 'language', 'institution', 'year'];
        for (const field of filterFields) {
            try {
                const data = await ApiService.fetchFilterOptions(field);
                if (data.values && filters[field]) {
                    populateSelect(filters[field], data.values, field === 'year');
                }
            } catch (error) {
                console.error(`Erro ao carregar opções para ${field}:`, error);
            }
        }
    }

    function populateSelect(selectElement, values, isNumeric = false) {
        while (selectElement.children.length > 1) {
            selectElement.removeChild(selectElement.lastChild);
        }
        
        if (isNumeric) {
            values.sort((a, b) => b - a);
        } else {
            values.sort();
        }

        values.forEach(value => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = value;
            selectElement.appendChild(option);
        });
    }

    async function fetchResults() {
        const queryFilters = getFiltersFromForm();
        appState.currentFilters = queryFilters;

        if (elements.tableBody) elements.tableBody.innerHTML = '';
        if (elements.resultsSummary) elements.resultsSummary.innerHTML = '';
        if (elements.loadingIndicator) elements.loadingIndicator.style.display = 'block';

        try {
            const data = await ApiService.search(queryFilters);
            if (elements.loadingIndicator) elements.loadingIndicator.style.display = 'none';

            if (data.hits && data.hits.hits.length > 0) {
                appState.currentResults = data.hits.hits.map(hit => ({
                    ...hit._source,
                    _id: hit._id
                }));
                displayResults(data.hits.hits);
                if (elements.resultsSummary) {
                    elements.resultsSummary.textContent = `Exibindo ${data.hits.hits.length} de ${data.hits.total.value} resultados.`;
                }
            } else {
                if (elements.resultsSummary) {
                    elements.resultsSummary.innerHTML = '<div class="alert alert-info">Nenhum resultado encontrado.</div>';
                }
            }
        } catch (error) {
            if (elements.loadingIndicator) elements.loadingIndicator.style.display = 'none';
            if (elements.resultsSummary) {
                elements.resultsSummary.innerHTML = `<div class="alert alert-danger">Erro ao buscar dados: ${error.message}</div>`;
            }
            console.error('Fetch error:', error);
        }
    }

    function getFiltersFromForm() {
        const formFilters = {
            q: elements.searchQuery?.value.trim() || '',
            type: filters.type?.value || '',
            language: filters.language?.value || '',
            institution: filters.institution?.value || '',
            year: filters.year?.value || '',
            year_from: filters.yearFrom?.value || '',
            year_to: filters.yearTo?.value || '',
            author: filters.author?.value.trim() || ''
        };

        return Object.fromEntries(
            Object.entries(formFilters).filter(([key, value]) => value !== '')
        );
    }

    function displayResults(hits) {
        const tbody = elements.tableBody;
        if (!tbody) return;
        tbody.innerHTML = '';

        hits.forEach(hit => {
            const doc = hit._source;
            const row = document.createElement('tr');
            
            const title = doc.titulo || doc.title || 'Sem título';
            const year = doc.ano || doc.year || 'N/A';
            const type = doc.tipo || doc.type || 'N/A';
            const researcher = doc.autores || doc.researcher_name || doc.authors || 'N/A';
            const venue = doc.periodico || doc.journal || doc.nome_evento || doc.event_name || doc.titulo_livro || doc.book_title || doc.editora || doc.publisher || 'N/A';
            
            row.innerHTML = `
                <td>
                    <strong>${title}</strong>
                    ${doc.natureza || doc.subtype ? `<br><small class="text-muted">${doc.natureza || doc.subtype}</small>` : ''}
                </td>
                <td>${researcher}</td>
                <td>${year}</td>
                <td><span class="badge bg-primary">${type}</span></td>
                <td>
                    ${venue}
                    ${doc.idioma || doc.language ? `<br><small class="text-muted">Idioma: ${doc.idioma || doc.language}</small>` : ''}
                </td>
                <td>
                    <div class="btn-group-vertical btn-group-sm">
                        ${doc.doi ? `<a href="https://doi.org/${doc.doi}" target="_blank" class="btn btn-outline-primary btn-sm">DOI</a>` : ''}
                        <button class="btn btn-outline-info btn-sm" onclick="showDetails('${doc._id || hit._id}')">Detalhes</button>
                    </div>
                </td>
            `;
            tbody.appendChild(row);
        });
    }

    async function searchResearchers() {
        const query = elements.researcherSearch?.value.trim();
        if (!query) return;

        const resultsDiv = elements.researchersResults;
        if (resultsDiv) resultsDiv.innerHTML = '<div class="text-center"><div class="spinner-border"></div></div>';

        try {
            const data = await ApiService.fetchResearchers(query);
            if (data.researchers && data.researchers.length > 0) {
                displayResearchers(data.researchers);
            } else {
                if (resultsDiv) resultsDiv.innerHTML = '<div class="alert alert-info">Nenhum pesquisador encontrado.</div>';
            }
        } catch (error) {
            if (resultsDiv) resultsDiv.innerHTML = `<div class="alert alert-danger">Erro ao buscar pesquisadores: ${error.message}</div>`;
        }
    }

    function displayResearchers(researchers) {
        const resultsDiv = elements.researchersResults;
        if (!resultsDiv) return;
        
        resultsDiv.innerHTML = researchers.map(researcher => `
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h5 class="card-title">${researcher.name}</h5>
                            <p class="card-text"><strong>Produções:</strong> ${researcher.production_count}</p>
                        </div>
                        <div class="col-md-4 text-end">
                            <button class="btn btn-primary btn-sm" onclick="filterByResearcher('${researcher.name}')">Ver Produções</button>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    }

    async function loadStatistics() {
        try {
            const data = await ApiService.fetchStatistics();
            if (data.aggregations) {
                // Logic for charts would go here (or imported from a module)
            }
        } catch (error) {
            console.error('Erro ao carregar estatísticas:', error);
        }
    }

    function clearFilters() {
        elements.searchQuery.value = '';
        Object.values(filters).forEach(filter => {
            if (filter?.tagName === 'SELECT') filter.selectedIndex = 0;
            else if (filter) filter.value = '';
        });
        fetchResults();
    }

    function exportData(format) {
        const queryParams = new URLSearchParams(appState.currentFilters);
        queryParams.set('format', format);
        window.open(`api/export.php?${queryParams.toString()}`, '_blank');
    }

    // Expose globals for HTML callbacks
    window.showDetails = (id) => {
        const production = appState.currentResults.find(p => (p._id === id || p.id === id));
        if (production) showProductionModal(production);
    };

    window.filterByResearcher = (name) => {
        if (elements.searchQuery) elements.searchQuery.value = name;
        document.getElementById('nav-search-tab')?.click();
        setTimeout(fetchResults, 100);
    };

    window.viewLattesProfile = (lattesId) => {
        window.open(`http://lattes.cnpq.br/${lattesId}`, '_blank');
    };

    function showProductionModal(p) {
        // Basic modal implementation
        alert(`Detalhes de: ${p.titulo || p.title}`);
    }
});
