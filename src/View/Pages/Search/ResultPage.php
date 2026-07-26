<?php
/**
 * PRODMAIS UMC - Resultados de Produções Científicas
 */

require_once __DIR__ . '/../../../../config/config_umc.php';
require_once __DIR__ . '/../../../../src/UmcFunctions.php';
require_once __DIR__ . '/../../../../vendor/autoload.php';

use App\View\Components\Navbar\Navbar;
use App\View\Components\HeroSection\HeroSection;
use App\View\Components\Footer\Footer;

// Capturar parâmetros de busca e filtros
$post_search = trim(strip_tags((string) filter_input(INPUT_POST, 'search')));
$get_q       = trim(strip_tags((string) filter_input(INPUT_GET, 'q')));
$get_pesquisador = trim(strip_tags((string) filter_input(INPUT_GET, 'pesquisador')));
$search_term = $post_search !== '' ? $post_search : ($get_q !== '' ? $get_q : $get_pesquisador);
$page = filter_input(INPUT_POST, 'page', FILTER_VALIDATE_INT) ?: (filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
$limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT) ?: 20;
$limit = min($limit, 100); // Máximo 100 por página

// Filtros
$filter_tipo       = trim(strip_tags((string) filter_input(INPUT_GET, 'tipo')));
$filter_ano_inicio = trim(strip_tags((string) filter_input(INPUT_GET, 'ano_inicio')));
$filter_ano_fim    = trim(strip_tags((string) filter_input(INPUT_GET, 'ano_fim')));
$filter_qualis     = trim(strip_tags((string) filter_input(INPUT_GET, 'qualis')));
$filter_ppg        = trim(strip_tags((string) filter_input(INPUT_GET, 'ppg')));

// Construir query com filtros
$must_queries   = [];
$filter_queries = [];

if (!empty($search_term)) {
    $must_queries[] = [
        'query_string' => [
            'query'            => $search_term,
            'default_operator' => 'OR'
        ]
    ];
}

if (!empty($filter_tipo)) {
    $filter_queries[] = ['term' => ['tipo.keyword' => $filter_tipo]];
}

if (!empty($filter_qualis)) {
    $filter_queries[] = ['term' => ['qualis.keyword' => $filter_qualis]];
}

if (!empty($filter_ppg)) {
    $must_queries[] = ['match' => ['ppg' => $filter_ppg]];
}

if (!empty($filter_ano_inicio) || !empty($filter_ano_fim)) {
    $range = [];
    if (!empty($filter_ano_inicio)) $range['gte'] = (int)$filter_ano_inicio;
    if (!empty($filter_ano_fim))    $range['lte'] = (int)$filter_ano_fim;
    $filter_queries[] = ['range' => ['ano' => $range]];
}

$query_body = [
    'from' => ($page - 1) * $limit,
    'size' => $limit,
    'sort' => [
        ['ano'    => ['order' => 'desc']],
        ['_score' => ['order' => 'desc']]
    ]
];

if (!empty($must_queries) || !empty($filter_queries)) {
    $query_body['query'] = ['bool' => []];
    if (!empty($must_queries))   $query_body['query']['bool']['must']   = $must_queries;
    if (!empty($filter_queries)) $query_body['query']['bool']['filter'] = $filter_queries;
} else {
    $query_body['query'] = ['match_all' => new stdClass()];
}

$results = [];
$total   = 0;
$client  = getElasticsearchClient();

if ($client) {
    try {
        $params = [
            'index' => $index,
            'body'  => $query_body
        ];

        error_log("Result.php - Executando busca com filtros: PPG={$filter_ppg}, Tipo={$filter_tipo}, Qualis={$filter_qualis}");
        error_log("Result.php - Query: " . json_encode($query_body));

        $response = $client->search($params);
        $results  = $response['hits']['hits'] ?? [];
        $total    = $response['hits']['total']['value'] ?? 0;

        error_log("Result.php - Total encontrado: {$total}");
    } catch (Exception $e) {
        error_log("Erro na busca: " . $e->getMessage());
    }
}

$total_pages = ceil($total / $limit);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/img/umc-favicon.png">
    <title>Resultados: <?php echo htmlspecialchars($search_term); ?> - Prodmais UMC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/prodmais-elegant.css?v=5">
    <link rel="stylesheet" href="/css/umc-theme.css">
</head>
<body>

<?php
Navbar::display([
    'mostrar_link_dashboard' => $mostrar_link_dashboard ?? true,
]);
?>
<?php renderNavbarAuthBadge(); ?>

<?php
HeroSection::display([
    'title'      => empty($search_term) ? 'Busca de Produções' : htmlspecialchars($search_term),
    'subtitle'   => 'Produções científicas e acadêmicas indexadas',
    'badge'      => number_format($total) . ' resultado' . ($total != 1 ? 's' : '') . ' encontrado' . ($total != 1 ? 's' : ''),
    'badge_icon' => 'search',
    'variant'    => 'primary',
]);
?>

<!-- Resultados -->
<section class="page-section page-section-gray">
    <div class="container">

        <?php if (empty($results)): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="fas fa-search-minus" aria-hidden="true"></i></div>
            <h3 class="empty-state-title">Nenhuma produção encontrada</h3>
            <p class="empty-state-sub">
                Não foram encontradas produções para <strong>"<?php echo htmlspecialchars($search_term); ?>"</strong>.<br>
                Verifique a ortografia, tente termos mais genéricos ou use sinônimos.
            </p>
            <a href="/index_umc.php" class="btn-primary-ds mt-3">
                <i class="fas fa-home me-2" aria-hidden="true"></i>Voltar ao Início
            </a>
        </div>
        <?php else: ?>

        <!-- Mobile: FAB + overlay que abrem o painel de filtros como bottom-sheet -->
        <button type="button" class="filter-fab" id="filterFabBtn" aria-label="Abrir filtros" aria-expanded="false" aria-controls="resultFilterPanel">
            <i class="fas fa-sliders" aria-hidden="true"></i>
        </button>
        <div class="filter-drawer-overlay" id="filterDrawerOverlay"></div>

        <div class="row">
            <!-- Sidebar de Filtros -->
            <div class="col-lg-3 mb-4">
                <div class="filter-panel" id="resultFilterPanel">
                    <span class="filter-drawer-handle" aria-hidden="true"></span>
                    <h5 class="filter-panel-title">
                        <i class="fas fa-filter" aria-hidden="true"></i> Filtros
                    </h5>

                    <form method="GET" action="/result.php" id="filterForm">
                        <input type="hidden" name="pesquisador" value="<?php echo htmlspecialchars($search_term); ?>">

                        <!-- Tipo de Produção -->
                        <div class="filter-group">
                            <label class="filter-group-label" for="filtroTipo">
                                <i class="fas fa-file-alt" aria-hidden="true"></i> Tipo
                            </label>
                            <select name="tipo" id="filtroTipo" class="form-select form-select-sm">
                                <option value="">Todos os Tipos</option>
                                <option value="PERIODICO" <?php echo $filter_tipo === 'PERIODICO' ? 'selected' : ''; ?>>Artigos em Periódicos</option>
                                <option value="LIVRO"     <?php echo $filter_tipo === 'LIVRO'     ? 'selected' : ''; ?>>Livros Publicados</option>
                                <option value="CAPITULO"  <?php echo $filter_tipo === 'CAPITULO'  ? 'selected' : ''; ?>>Capítulos de Livros</option>
                                <option value="EVENTO"    <?php echo $filter_tipo === 'EVENTO'    ? 'selected' : ''; ?>>Trabalhos em Eventos</option>
                            </select>
                        </div>

                        <!-- Qualis -->
                        <div class="filter-group">
                            <label class="filter-group-label" for="filtroQualis">
                                <i class="fas fa-star" aria-hidden="true"></i> Qualis
                            </label>
                            <select name="qualis" id="filtroQualis" class="form-select form-select-sm">
                                <option value="">Todos</option>
                                <option value="A1" <?php echo $filter_qualis === 'A1' ? 'selected' : ''; ?>>A1</option>
                                <option value="A2" <?php echo $filter_qualis === 'A2' ? 'selected' : ''; ?>>A2</option>
                                <option value="A3" <?php echo $filter_qualis === 'A3' ? 'selected' : ''; ?>>A3</option>
                                <option value="A4" <?php echo $filter_qualis === 'A4' ? 'selected' : ''; ?>>A4</option>
                                <option value="B1" <?php echo $filter_qualis === 'B1' ? 'selected' : ''; ?>>B1</option>
                                <option value="B2" <?php echo $filter_qualis === 'B2' ? 'selected' : ''; ?>>B2</option>
                                <option value="B3" <?php echo $filter_qualis === 'B3' ? 'selected' : ''; ?>>B3</option>
                                <option value="B4" <?php echo $filter_qualis === 'B4' ? 'selected' : ''; ?>>B4</option>
                                <option value="C"  <?php echo $filter_qualis === 'C'  ? 'selected' : ''; ?>>C</option>
                            </select>
                        </div>

                        <!-- Período -->
                        <div class="filter-group">
                            <label class="filter-group-label">
                                <i class="fas fa-calendar" aria-hidden="true"></i> Período
                            </label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="number" name="ano_inicio" class="form-control form-control-sm"
                                           placeholder="De" min="1900" max="<?php echo date('Y'); ?>"
                                           value="<?php echo htmlspecialchars($filter_ano_inicio); ?>"
                                           aria-label="Ano início">
                                </div>
                                <div class="col-6">
                                    <input type="number" name="ano_fim" class="form-control form-control-sm"
                                           placeholder="Até" min="1900" max="<?php echo date('Y'); ?>"
                                           value="<?php echo htmlspecialchars($filter_ano_fim); ?>"
                                           aria-label="Ano fim">
                                </div>
                            </div>
                        </div>

                        <!-- Resultados por página -->
                        <div class="filter-group">
                            <label class="filter-group-label" for="filtroLimit">
                                <i class="fas fa-list" aria-hidden="true"></i> Exibir
                            </label>
                            <select name="limit" id="filtroLimit" class="form-select form-select-sm">
                                <option value="20"  <?php echo $limit === 20  ? 'selected' : ''; ?>>20 por página</option>
                                <option value="50"  <?php echo $limit === 50  ? 'selected' : ''; ?>>50 por página</option>
                                <option value="100" <?php echo $limit === 100 ? 'selected' : ''; ?>>100 por página</option>
                            </select>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn-primary-ds btn-primary-ds--sm">
                                <i class="fas fa-check me-1" aria-hidden="true"></i>Aplicar Filtros
                            </button>
                            <a href="/result.php?pesquisador=<?php echo urlencode($search_term); ?>"
                               class="btn-outline-ds btn-outline-ds--sm">
                                <i class="fas fa-times me-1" aria-hidden="true"></i>Limpar
                            </a>
                        </div>
                    </form>

                    <!-- Estatísticas rápidas -->
                    <div class="filter-stats">
                        <h6 class="filter-stats-title">Estatísticas</h6>
                        <div class="filter-stats-body">
                            <div class="filter-stats-row">
                                <span>Total:</span>
                                <strong><?php echo number_format($total); ?></strong>
                            </div>
                            <div class="filter-stats-row">
                                <span>Página:</span>
                                <strong><?php echo $page; ?> de <?php echo $total_pages; ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Coluna de Resultados -->
            <div class="col-lg-9">

        <?php foreach ($results as $index => $hit):
            $source          = $hit['_source'];
            $titulo          = $source['titulo']          ?? 'Sem título';
            $autores         = $source['autores']         ?? '';
            $ano             = $source['ano']             ?? '';
            $tipo            = $source['tipo']            ?? '';
            $qualis          = $source['qualis']          ?? '';
            $doi             = $source['doi']             ?? '';
            $periodico       = $source['periodico']       ?? '';
            $ppg             = $source['ppg']             ?? '';
            $volume          = $source['volume']          ?? '';
            $pagina_inicial  = $source['pagina_inicial']  ?? '';
            $pagina_final    = $source['pagina_final']    ?? '';
            $issn            = $source['issn']            ?? '';
            $idioma          = $source['idioma']          ?? '';
            $area_concentracao = $source['area_concentracao'] ?? '';

            $unique_id    = 'modal_' . md5($hit['_id']);
            $qualis_class = 'qualis-' . strtolower($qualis);
        ?>
        <div class="result-card fade-in-up" style="animation-delay:<?php echo ($index * 0.05); ?>s">
            <div class="result-card-header">
                <div class="result-card-icon">
                    <i class="fas fa-file-alt" aria-hidden="true"></i>
                </div>
                <div class="result-card-content">
                    <h3 class="result-card-title"><?php echo htmlspecialchars($titulo); ?></h3>
                    <?php if (!empty($autores)): ?>
                    <p class="result-card-authors">
                        <i class="fas fa-users" aria-hidden="true"></i>
                        <?php echo htmlspecialchars($autores); ?>
                    </p>
                    <?php endif; ?>
                    <?php if (!empty($periodico)): ?>
                    <p class="result-card-authors">
                        <i class="fas fa-book" aria-hidden="true"></i>
                        <?php echo htmlspecialchars($periodico); ?>
                    </p>
                    <?php endif; ?>
                    <?php if (!empty($ppg)): ?>
                    <p class="result-card-authors">
                        <i class="fas fa-university" aria-hidden="true"></i>
                        <?php echo htmlspecialchars($ppg); ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="result-card-badges">
                <?php if (!empty($tipo)): ?>
                <span class="badge-elegant badge-primary">
                    <i class="fas fa-file-alt" aria-hidden="true"></i>
                    <?php echo htmlspecialchars($tipo); ?>
                </span>
                <?php endif; ?>
                <?php if (!empty($ano)): ?>
                <span class="badge-elegant badge-neutral">
                    <i class="fas fa-calendar" aria-hidden="true"></i>
                    <?php echo htmlspecialchars($ano); ?>
                </span>
                <?php endif; ?>
                <?php if (!empty($qualis)): ?>
                <span class="qualis-badge <?php echo $qualis_class; ?>">
                    Qualis <?php echo htmlspecialchars($qualis); ?>
                </span>
                <?php endif; ?>
            </div>

            <div class="result-card-actions">
                <button class="btn-outline-ds btn-outline-ds--sm"
                        data-bs-toggle="modal" data-bs-target="#<?php echo $unique_id; ?>">
                    <i class="fas fa-info-circle me-1" aria-hidden="true"></i>Ver Detalhes
                </button>
                <?php if (!empty($doi)): ?>
                <a href="https://doi.org/<?php echo urlencode($doi); ?>" target="_blank" rel="noopener noreferrer"
                   class="btn-outline-ds btn-outline-ds--sm"
                   aria-label="Acessar via DOI">
                    <i class="fas fa-external-link-alt me-1" aria-hidden="true"></i>DOI
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Modal de Detalhes -->
        <div class="modal fade" id="<?php echo $unique_id; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content modal-content-ds">
                    <div class="modal-header-ds">
                        <h5 class="modal-title">
                            <i class="fas fa-file-alt me-2" aria-hidden="true"></i>Detalhes da Produção
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body modal-body-ds">
                        <div class="modal-detail-item">
                            <div class="modal-detail-label">
                                <i class="fas fa-heading" aria-hidden="true"></i> Título
                            </div>
                            <div class="modal-detail-value modal-detail-value--lg"><?php echo htmlspecialchars($titulo); ?></div>
                        </div>

                        <?php if (!empty($autores)): ?>
                        <div class="modal-detail-item">
                            <div class="modal-detail-label">
                                <i class="fas fa-users" aria-hidden="true"></i> Autores
                            </div>
                            <div class="modal-detail-value"><?php echo htmlspecialchars($autores); ?></div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($periodico)): ?>
                        <div class="modal-detail-item">
                            <div class="modal-detail-label">
                                <i class="fas fa-book" aria-hidden="true"></i> Periódico/Livro
                            </div>
                            <div class="modal-detail-value"><?php echo htmlspecialchars($periodico); ?></div>
                        </div>
                        <?php endif; ?>

                        <div class="row mb-3">
                            <?php if (!empty($ano)): ?>
                            <div class="col-md-4 mb-2">
                                <div class="modal-detail-label"><i class="fas fa-calendar" aria-hidden="true"></i> Ano</div>
                                <div class="modal-detail-value"><?php echo htmlspecialchars($ano); ?></div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($tipo)): ?>
                            <div class="col-md-4 mb-2">
                                <div class="modal-detail-label"><i class="fas fa-file-alt" aria-hidden="true"></i> Tipo</div>
                                <div class="modal-detail-value"><?php echo htmlspecialchars($tipo); ?></div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($qualis)): ?>
                            <div class="col-md-4 mb-2">
                                <div class="modal-detail-label"><i class="fas fa-star" aria-hidden="true"></i> Qualis</div>
                                <span class="qualis-badge <?php echo $qualis_class; ?>">
                                    <?php echo htmlspecialchars($qualis); ?>
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($ppg)): ?>
                        <div class="modal-detail-item">
                            <div class="modal-detail-label">
                                <i class="fas fa-university" aria-hidden="true"></i> PPG
                            </div>
                            <div class="modal-detail-value"><?php echo htmlspecialchars($ppg); ?></div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($doi)): ?>
                        <div class="modal-doi-box">
                            <div class="modal-detail-label">
                                <i class="fas fa-link" aria-hidden="true"></i> DOI
                            </div>
                            <a href="https://doi.org/<?php echo urlencode($doi); ?>" target="_blank" rel="noopener noreferrer"
                               class="modal-doi-link">
                                <?php echo htmlspecialchars($doi); ?> <i class="fas fa-external-link-alt ms-1" aria-hidden="true"></i>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer modal-footer-ds">
                        <?php if (!empty($doi)): ?>
                        <a href="https://doi.org/<?php echo urlencode($doi); ?>" target="_blank" rel="noopener noreferrer"
                           class="btn-primary-ds btn-primary-ds--sm">
                            <i class="fas fa-external-link-alt me-1" aria-hidden="true"></i>Acessar via DOI
                        </a>
                        <?php endif; ?>
                        <button type="button" class="btn-outline-ds btn-outline-ds--sm" data-bs-dismiss="modal">Fechar</button>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Paginação -->
        <?php if ($total_pages > 1): ?>
        <nav class="mt-4" aria-label="Paginação de resultados">
            <ul class="pagination justify-content-center flex-wrap pagination-ds">
                <?php
                $start_page = max(1, $page - 2);
                $end_page   = min($total_pages, $page + 2);
                if ($page > 1): ?>
                <li>
                    <a href="?pesquisador=<?php echo urlencode($search_term); ?>&page=1&tipo=<?php echo urlencode($filter_tipo); ?>&qualis=<?php echo urlencode($filter_qualis); ?>&ano_inicio=<?php echo urlencode($filter_ano_inicio); ?>&ano_fim=<?php echo urlencode($filter_ano_fim); ?>&limit=<?php echo $limit; ?>"
                       class="pagination-btn" aria-label="Primeira página">
                        <i class="fas fa-angle-double-left" aria-hidden="true"></i>
                    </a>
                </li>
                <li>
                    <a href="?pesquisador=<?php echo urlencode($search_term); ?>&page=<?php echo $page - 1; ?>&tipo=<?php echo urlencode($filter_tipo); ?>&qualis=<?php echo urlencode($filter_qualis); ?>&ano_inicio=<?php echo urlencode($filter_ano_inicio); ?>&ano_fim=<?php echo urlencode($filter_ano_fim); ?>&limit=<?php echo $limit; ?>"
                       class="pagination-btn" aria-label="Página anterior">
                        <i class="fas fa-angle-left" aria-hidden="true"></i>
                    </a>
                </li>
                <?php endif; ?>

                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <li>
                    <a href="?pesquisador=<?php echo urlencode($search_term); ?>&page=<?php echo $i; ?>&tipo=<?php echo urlencode($filter_tipo); ?>&qualis=<?php echo urlencode($filter_qualis); ?>&ano_inicio=<?php echo urlencode($filter_ano_inicio); ?>&ano_fim=<?php echo urlencode($filter_ano_fim); ?>&limit=<?php echo $limit; ?>"
                       class="pagination-btn <?php echo $i == $page ? 'active' : ''; ?>"
                       <?php echo $i == $page ? 'aria-current="page"' : ''; ?>>
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                <li>
                    <a href="?pesquisador=<?php echo urlencode($search_term); ?>&page=<?php echo $page + 1; ?>&tipo=<?php echo urlencode($filter_tipo); ?>&qualis=<?php echo urlencode($filter_qualis); ?>&ano_inicio=<?php echo urlencode($filter_ano_inicio); ?>&ano_fim=<?php echo urlencode($filter_ano_fim); ?>&limit=<?php echo $limit; ?>"
                       class="pagination-btn" aria-label="Próxima página">
                        <i class="fas fa-angle-right" aria-hidden="true"></i>
                    </a>
                </li>
                <li>
                    <a href="?pesquisador=<?php echo urlencode($search_term); ?>&page=<?php echo $total_pages; ?>&tipo=<?php echo urlencode($filter_tipo); ?>&qualis=<?php echo urlencode($filter_qualis); ?>&ano_inicio=<?php echo urlencode($filter_ano_inicio); ?>&ano_fim=<?php echo urlencode($filter_ano_fim); ?>&limit=<?php echo $limit; ?>"
                       class="pagination-btn" aria-label="Última página">
                        <i class="fas fa-angle-double-right" aria-hidden="true"></i>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>

            </div><!-- fecha col-lg-9 -->
        </div><!-- fecha row -->

        <?php endif; ?>
    </div>
</section>

<?php Footer::display(); ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    var fab     = document.getElementById('filterFabBtn');
    var overlay = document.getElementById('filterDrawerOverlay');
    var panel   = document.getElementById('resultFilterPanel');
    var handle  = panel ? panel.querySelector('.filter-drawer-handle') : null;
    if (!fab || !overlay || !panel) return;

    var scrollY = 0;

    function lockBackgroundScroll() {
        scrollY = window.scrollY;
        document.body.style.position = 'fixed';
        document.body.style.top = '-' + scrollY + 'px';
        document.body.style.left = '0';
        document.body.style.right = '0';
        document.body.style.width = '100%';
    }

    function unlockBackgroundScroll() {
        document.body.style.position = '';
        document.body.style.top = '';
        document.body.style.left = '';
        document.body.style.right = '';
        document.body.style.width = '';
        window.scrollTo(0, scrollY);
    }

    function openPanel() {
        panel.classList.add('open');
        overlay.classList.add('open');
        fab.classList.add('is-hidden');
        fab.setAttribute('aria-expanded', 'true');
        lockBackgroundScroll();
    }

    function closePanel() {
        panel.classList.remove('open');
        overlay.classList.remove('open');
        fab.classList.remove('is-hidden');
        fab.setAttribute('aria-expanded', 'false');
        unlockBackgroundScroll();
    }

    fab.addEventListener('click', function () {
        panel.classList.contains('open') ? closePanel() : openPanel();
    });
    overlay.addEventListener('click', closePanel);
    if (handle) handle.addEventListener('click', closePanel);
})();
</script>
</body>
</html>
