<?php
/**
 * PRODMAIS UMC - Página Individual de PPG
 * Exibe detalhes e produções de um programa específico
 */

require_once __DIR__ . '/../../../../config/config_umc.php';
require_once __DIR__ . '/../../../../src/UmcFunctions.php';
require_once __DIR__ . '/../../../../vendor/autoload.php';

use App\View\Components\Navbar\Navbar;
use App\View\Components\HeroSection\HeroSection;
use App\View\Components\Footer\Footer;

$ppg_nome = trim(strip_tags((string) filter_input(INPUT_GET, 'ppg')));

if (empty($ppg_nome)) {
    header('Location: /ppgs.php');
    exit;
}

$ppg_data = null;
foreach ($ppgs_umc as $ppg) {
    if ($ppg['nome'] === $ppg_nome) {
        $ppg_data = $ppg;
        break;
    }
}

if ($ppg_data === null) {
    header('Location: /ppgs.php');
    exit;
}

$client    = getElasticsearchClient();
$producoes = [];
$total     = 0;

$limit      = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT) ?: 20;
$page       = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
$tipo       = trim(strip_tags((string) filter_input(INPUT_GET, 'tipo')));
$qualis     = trim(strip_tags((string) filter_input(INPUT_GET, 'qualis')));
$ano_inicio = trim(strip_tags((string) filter_input(INPUT_GET, 'ano_inicio')));
$ano_fim    = trim(strip_tags((string) filter_input(INPUT_GET, 'ano_fim')));

$from = ($page - 1) * $limit;

if ($client !== null) {
    try {
        $must = [
            [
                'bool' => [
                    'should' => [
                        ['match'        => ['ppg' => $ppg_nome]],
                        ['match_phrase' => ['ppg' => $ppg_nome]],
                        ['term'         => ['ppg.keyword' => $ppg_nome]],
                    ],
                    'minimum_should_match' => 1,
                ],
            ],
        ];

        $filter = [];
        if (!empty($tipo))       { $filter[] = ['term'  => ['tipo.keyword'   => $tipo]]; }
        if (!empty($qualis))     { $filter[] = ['term'  => ['qualis.keyword' => $qualis]]; }
        if (!empty($ano_inicio) || !empty($ano_fim)) {
            $range = [];
            if (!empty($ano_inicio)) { $range['gte'] = (int)$ano_inicio; }
            if (!empty($ano_fim))    { $range['lte'] = (int)$ano_fim; }
            $filter[] = ['range' => ['ano' => $range]];
        }

        $query = ['bool' => ['must' => $must]];
        if (!empty($filter)) { $query['bool']['filter'] = $filter; }

        $params   = ['index' => $index, 'body' => ['query' => $query, 'sort' => [['ano' => ['order' => 'desc']]], 'from' => $from, 'size' => $limit]];
        $response = $client->search($params);
        $total    = $response['hits']['total']['value'] ?? 0;

        if (isset($response['hits']['hits'])) {
            foreach ($response['hits']['hits'] as $hit) {
                $producoes[] = $hit['_source'];
            }
        }
    } catch (Exception $e) {
        error_log("Erro ao buscar produções do PPG: " . $e->getMessage());
    }
}

$total_pages = ceil($total / $limit);

$debug_info = ['ppg_nome' => $ppg_nome, 'total' => $total, 'producoes_count' => count($producoes)];
error_log("PPG.php - Debug Final: " . json_encode($debug_info));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/img/umc-favicon.png">
    <title><?php echo htmlspecialchars($ppg_data['nome']); ?> - <?php echo $branch; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/prodmais-elegant.css?v=5">
    <link rel="stylesheet" href="/css/umc-theme.css">
    <style>
        .pagination-btn {
            display: flex; align-items: center; justify-content: center;
            width: 38px; height: 38px; border-radius: var(--radius-md);
            font-size: 0.875rem; font-weight: 600; text-decoration: none;
            transition: all var(--transition-fast);
            border: 1px solid var(--gray-200);
            background: #fff; color: var(--gray-700);
        }
        .pagination-btn:hover { background: var(--gray-50); color: var(--primary); border-color: var(--primary); }
        .pagination-btn.active { background: var(--primary); color: #fff; border-color: var(--primary); }
        .modal-header-ds { background: linear-gradient(135deg, var(--primary-dark), var(--primary)); color: #fff; border: none; padding: 1.5rem 2rem; border-radius: var(--radius-xl) var(--radius-xl) 0 0; }
        .modal-content { border-radius: var(--radius-xl); border: none; box-shadow: 0 20px 60px rgba(0,0,0,0.28); overflow: hidden; }
        .filter-submit-btn {
            display: block; width: 100%; border: none; border-radius: 10px; padding: .7rem 1rem;
            background: linear-gradient(135deg,#059669,#0d9488); color: #fff; font-weight: 700; font-size: .85rem;
            cursor: pointer; transition: filter .2s, transform .2s;
            box-shadow: 0 4px 12px rgba(5,150,105,.25);
        }
        .filter-submit-btn:hover { filter: brightness(1.08); transform: translateY(-1px); color: #fff; }
    </style>
</head>
<body>

<?php
Navbar::display([
    'active_page'            => 'ppgs',
    'mostrar_link_dashboard' => $mostrar_link_dashboard ?? true,
]);
?>
<?php renderNavbarAuthBadge(); ?>

<?php
HeroSection::display([
    'title'    => htmlspecialchars($ppg_data['nome']),
    'subtitle' => implode(' &bull; ', array_map('htmlspecialchars', $ppg_data['areas_concentracao'] ?? [])),
    'badge'    => number_format($total) . ' Produções &bull; ' . htmlspecialchars($ppg_data['nivel'] ?? ''),
    'badge_icon' => 'graduation-cap',
    'variant'  => 'lavender',
]);
?>

<!-- Conteúdo Principal -->
<section class="page-section page-section-gray">
    <div class="container">

        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="d-flex gap-2 align-items-center list-unstyled mb-0" style="font-size:0.85rem;">
                <li><a href="/ppgs.php" style="color:var(--primary);text-decoration:none;"><i class="fas fa-university me-1" aria-hidden="true"></i>PPGs</a></li>
                <li style="color:var(--gray-400);">/</li>
                <li style="color:var(--gray-600);"><?php echo htmlspecialchars($ppg_data['nome']); ?></li>
            </ol>
        </nav>

        <div class="row g-4">
            <!-- Sidebar de Filtros -->
            <div class="col-lg-3">
                <div class="filter-panel">
                    <p class="filter-panel-title"><i class="fas fa-filter" aria-hidden="true"></i>Filtros</p>

                    <form method="GET" action="/ppg.php" id="filterForm">
                        <input type="hidden" name="ppg" value="<?php echo htmlspecialchars($ppg_nome); ?>">

                        <div class="filter-group">
                            <label class="filter-group-label" for="filterTipo">Tipo de Produção</label>
                            <select name="tipo" id="filterTipo" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Todos os Tipos</option>
                                <option value="PERIODICO"       <?php echo $tipo === 'PERIODICO'       ? 'selected' : ''; ?>>Artigos em Periódicos</option>
                                <option value="LIVRO"           <?php echo $tipo === 'LIVRO'           ? 'selected' : ''; ?>>Livros Publicados</option>
                                <option value="CAPITULO"        <?php echo $tipo === 'CAPITULO'        ? 'selected' : ''; ?>>Capítulos de Livros</option>
                                <option value="EVENTO"          <?php echo $tipo === 'EVENTO'          ? 'selected' : ''; ?>>Trabalhos em Eventos</option>
                                <option value="ARTIGO-PUBLICADO"<?php echo $tipo === 'ARTIGO-PUBLICADO'? 'selected' : ''; ?>>Artigo Publicado</option>
                                <option value="RESUMO-CONGRESSO"<?php echo $tipo === 'RESUMO-CONGRESSO'? 'selected' : ''; ?>>Resumo em Congresso</option>
                                <option value="LIVRO-PUBLICADO" <?php echo $tipo === 'LIVRO-PUBLICADO' ? 'selected' : ''; ?>>Livro Publicado</option>
                                <option value="CAPITULO-LIVRO"  <?php echo $tipo === 'CAPITULO-LIVRO'  ? 'selected' : ''; ?>>Capítulo de Livro</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-group-label" for="filterQualis">Qualis CAPES</label>
                            <select name="qualis" id="filterQualis" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Todos</option>
                                <?php foreach (['A1','A2','A3','A4','B1','B2','B3','B4','C'] as $q): ?>
                                <option value="<?php echo $q; ?>" <?php echo $qualis === $q ? 'selected' : ''; ?>><?php echo $q; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-group-label">Período</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="number" name="ano_inicio" class="form-control form-control-sm" placeholder="De"
                                           min="1900" max="<?php echo date('Y'); ?>" value="<?php echo htmlspecialchars($ano_inicio); ?>">
                                </div>
                                <div class="col-6">
                                    <input type="number" name="ano_fim" class="form-control form-control-sm" placeholder="Até"
                                           min="1900" max="<?php echo date('Y'); ?>" value="<?php echo htmlspecialchars($ano_fim); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="filter-group">
                            <label class="filter-group-label" for="filterLimit">Resultados por página</label>
                            <select name="limit" id="filterLimit" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="20"  <?php echo $limit === 20  ? 'selected' : ''; ?>>20 por página</option>
                                <option value="50"  <?php echo $limit === 50  ? 'selected' : ''; ?>>50 por página</option>
                                <option value="100" <?php echo $limit === 100 ? 'selected' : ''; ?>>100 por página</option>
                            </select>
                        </div>

                        <button type="submit" class="filter-submit-btn">
                            <i class="fas fa-search me-1" aria-hidden="true"></i>Aplicar Filtros
                        </button>

                        <?php if (!empty($tipo) || !empty($qualis) || !empty($ano_inicio) || !empty($ano_fim)): ?>
                        <a href="/ppg.php?ppg=<?php echo urlencode($ppg_nome); ?>"
                           class="d-block text-center mt-2"
                           style="font-size:0.8rem;color:var(--gray-500);">
                            <i class="fas fa-times me-1" aria-hidden="true"></i>Limpar Filtros
                        </a>
                        <?php endif; ?>
                    </form>

                    <hr style="border-color:var(--gray-100);margin:1.25rem 0;">
                    <div class="text-center">
                        <div class="stat-card-value" style="font-size:1.75rem;"><?php echo number_format($total); ?></div>
                        <div class="stat-card-label">Total de resultados</div>
                        <?php if ($page > 1 || $total > $limit): ?>
                        <p style="font-size:0.78rem;color:var(--gray-400);margin-top:0.5rem;margin-bottom:0;">
                            Mostrando <?php echo number_format($from + 1); ?>–<?php echo number_format(min($from + $limit, $total)); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Lista de Produções -->
            <div class="col-lg-9">

                <?php if (isset($_GET['debug'])): ?>
                <div class="alert alert-warning mb-4" style="font-family:monospace;font-size:0.82rem;">
                    <strong>DEBUG:</strong> PPG=<?php echo htmlspecialchars($ppg_nome); ?> | Total=<?php echo $total; ?> |
                    ES=<?php echo $client ? 'OK' : 'ERRO'; ?>
                    <a href="?ppg=<?php echo urlencode($ppg_nome); ?>" class="ms-3 text-warning">Remover debug</a>
                </div>
                <?php endif; ?>

                <?php if (empty($producoes)): ?>
                <div class="empty-state">
                    <i class="fas fa-search empty-state-icon" aria-hidden="true"></i>
                    <h4 class="empty-state-title">Nenhuma produção encontrada</h4>
                    <p class="empty-state-sub">Tente ajustar os filtros para ver mais resultados.</p>
                </div>
                <?php else: ?>

                <?php foreach ($producoes as $idx => $prod):
                    $qualis_class_map = ['A1'=>'a1','A2'=>'a2','B1'=>'b1','B2'=>'b2','B3'=>'b3','B4'=>'b4','C'=>'c'];
                    $q_class = 'qualis-' . strtolower($prod['qualis'] ?? 'np');
                ?>
                <div class="result-card fade-in-up" style="animation-delay:<?php echo min($idx * 0.05, 0.5); ?>s;">
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-1">
                        <h5 class="result-card-title mb-0">
                            <?php echo htmlspecialchars($prod['titulo'] ?? 'Sem título'); ?>
                        </h5>
                        <?php if (!empty($prod['qualis'])): ?>
                        <span class="qualis-badge <?php echo $q_class; ?>">
                            Qualis <?php echo htmlspecialchars($prod['qualis']); ?>
                        </span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($prod['autores'])): ?>
                    <p class="result-card-authors"><?php echo htmlspecialchars($prod['autores']); ?></p>
                    <?php endif; ?>

                    <div class="result-card-meta">
                        <?php if (!empty($prod['ano'])): ?>
                        <span><i class="fas fa-calendar me-1" aria-hidden="true"></i><?php echo htmlspecialchars($prod['ano']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($prod['periodico'])): ?>
                        <span class="separator">·</span>
                        <span><i class="fas fa-book me-1" aria-hidden="true"></i><?php echo htmlspecialchars($prod['periodico']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($prod['tipo'])): ?>
                        <span class="separator">·</span>
                        <span class="badge-elegant badge-primary" style="padding:0.15rem 0.55rem;font-size:0.68rem;"><?php echo htmlspecialchars($prod['tipo']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <?php if (!empty($prod['doi'])): ?>
                        <a href="https://doi.org/<?php echo urlencode($prod['doi']); ?>"
                           target="_blank" rel="noopener noreferrer"
                           class="export-btn export-btn-bibtex">
                            <i class="fas fa-link" aria-hidden="true"></i>DOI
                        </a>
                        <?php endif; ?>
                        <button type="button"
                                class="btn-primary-ds"
                                style="font-size:0.78rem;padding:0.35rem 0.9rem;"
                                data-bs-toggle="modal"
                                data-bs-target="#modalProducao<?php echo $idx; ?>">
                            <i class="fas fa-eye me-1" aria-hidden="true"></i>Detalhes
                        </button>
                    </div>
                </div>

                <!-- Modal de Detalhes -->
                <div class="modal fade" id="modalProducao<?php echo $idx; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header modal-header-ds">
                                <h5 class="modal-title fw-800">
                                    <i class="fas fa-file-alt me-2" aria-hidden="true"></i>Detalhes da Produção
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                            </div>
                            <div class="modal-body p-4">
                                <h4 class="fw-800 mb-3" style="color:var(--gray-900);line-height:1.4;">
                                    <?php echo htmlspecialchars($prod['titulo'] ?? 'Sem título'); ?>
                                </h4>
                                <div class="d-flex flex-wrap gap-2 mb-4">
                                    <?php if (!empty($prod['tipo'])): ?>
                                    <span class="badge-elegant badge-primary"><?php echo htmlspecialchars($prod['tipo']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($prod['qualis'])): ?>
                                    <span class="qualis-badge <?php echo $q_class; ?>">Qualis <?php echo htmlspecialchars($prod['qualis']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <dl class="row row-cols-1 row-cols-md-2 g-3" style="font-size:0.875rem;">
                                    <?php if (!empty($prod['autores'])): ?>
                                    <div class="col-12">
                                        <dt style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.07em;color:var(--gray-400);margin-bottom:0.25rem;">Autores</dt>
                                        <dd style="color:var(--gray-800);"><?php echo nl2br(htmlspecialchars($prod['autores'])); ?></dd>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($prod['ano'])): ?>
                                    <div class="col">
                                        <dt style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.07em;color:var(--gray-400);margin-bottom:0.25rem;">Ano</dt>
                                        <dd style="color:var(--gray-800);font-weight:700;"><?php echo htmlspecialchars($prod['ano']); ?></dd>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($prod['ppg'])): ?>
                                    <div class="col">
                                        <dt style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.07em;color:var(--gray-400);margin-bottom:0.25rem;">PPG</dt>
                                        <dd style="color:var(--gray-800);font-weight:600;"><?php echo htmlspecialchars($prod['ppg']); ?></dd>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($prod['periodico'])): ?>
                                    <div class="col-12">
                                        <dt style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.07em;color:var(--gray-400);margin-bottom:0.25rem;">Periódico / Veículo</dt>
                                        <dd style="color:var(--gray-800);font-weight:600;"><?php echo htmlspecialchars($prod['periodico']); ?></dd>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($prod['volume'])): ?>
                                    <div class="col">
                                        <dt style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.07em;color:var(--gray-400);margin-bottom:0.25rem;">Volume</dt>
                                        <dd style="color:var(--gray-800);"><?php echo htmlspecialchars($prod['volume']); ?></dd>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($prod['paginas'])): ?>
                                    <div class="col">
                                        <dt style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.07em;color:var(--gray-400);margin-bottom:0.25rem;">Páginas</dt>
                                        <dd style="color:var(--gray-800);"><?php echo htmlspecialchars($prod['paginas']); ?></dd>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($prod['issn'])): ?>
                                    <div class="col">
                                        <dt style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.07em;color:var(--gray-400);margin-bottom:0.25rem;">ISSN</dt>
                                        <dd style="color:var(--gray-800);font-family:monospace;"><?php echo htmlspecialchars($prod['issn']); ?></dd>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($prod['doi'])): ?>
                                    <div class="col-12">
                                        <dt style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.07em;color:var(--gray-400);margin-bottom:0.25rem;">DOI</dt>
                                        <dd><a href="https://doi.org/<?php echo urlencode($prod['doi']); ?>" target="_blank" rel="noopener noreferrer" style="color:var(--primary);font-weight:600;">
                                            <?php echo htmlspecialchars($prod['doi']); ?> <i class="fas fa-external-link-alt ms-1" style="font-size:0.7rem;" aria-hidden="true"></i>
                                        </a></dd>
                                    </div>
                                    <?php endif; ?>
                                </dl>
                            </div>
                            <div class="modal-footer" style="border:none;background:var(--gray-50);padding:1rem 1.5rem;">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius:var(--radius-md);">Fechar</button>
                                <?php if (!empty($prod['doi'])): ?>
                                <a href="https://doi.org/<?php echo urlencode($prod['doi']); ?>"
                                   target="_blank" rel="noopener noreferrer"
                                   class="btn-primary-ds" style="font-size:0.875rem;padding:0.5rem 1.1rem;">
                                    <i class="fas fa-external-link-alt me-1" aria-hidden="true"></i>Abrir DOI
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php endforeach; ?>

                <!-- Paginação -->
                <?php if ($total_pages > 1):
                    $query_params  = array_filter(['ppg' => $ppg_nome, 'tipo' => $tipo, 'qualis' => $qualis, 'ano_inicio' => $ano_inicio, 'ano_fim' => $ano_fim, 'limit' => $limit]);
                    $query_string  = http_build_query($query_params);
                    $start_page    = max(1, $page - 2);
                    $end_page      = min($total_pages, $page + 2);
                ?>
                <nav aria-label="Paginação" class="mt-4">
                    <ul class="d-flex justify-content-center gap-1 flex-wrap list-unstyled mb-0">
                        <?php if ($page > 1): ?>
                        <li><a href="/ppg.php?<?php echo $query_string; ?>&page=1"               class="pagination-btn" aria-label="Primeira"><i class="fas fa-angle-double-left" aria-hidden="true"></i></a></li>
                        <li><a href="/ppg.php?<?php echo $query_string; ?>&page=<?php echo $page - 1; ?>" class="pagination-btn" aria-label="Anterior"><i class="fas fa-angle-left" aria-hidden="true"></i></a></li>
                        <?php endif; ?>
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <li><a href="/ppg.php?<?php echo $query_string; ?>&page=<?php echo $i; ?>"
                               class="pagination-btn <?php echo $i === $page ? 'active' : ''; ?>"
                               <?php echo $i === $page ? 'aria-current="page"' : ''; ?>><?php echo $i; ?></a></li>
                        <?php endfor; ?>
                        <?php if ($page < $total_pages): ?>
                        <li><a href="/ppg.php?<?php echo $query_string; ?>&page=<?php echo $page + 1; ?>"      class="pagination-btn" aria-label="Próxima"><i class="fas fa-angle-right" aria-hidden="true"></i></a></li>
                        <li><a href="/ppg.php?<?php echo $query_string; ?>&page=<?php echo $total_pages; ?>"   class="pagination-btn" aria-label="Última"><i class="fas fa-angle-double-right" aria-hidden="true"></i></a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>

                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php Footer::display(); ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
