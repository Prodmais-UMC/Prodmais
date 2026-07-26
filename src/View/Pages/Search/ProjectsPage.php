<?php
/**
 * PRODMAIS UMC - Projetos de Pesquisa
 */

require_once __DIR__ . '/../../../../config/config_umc.php';
require_once __DIR__ . '/../../../../src/UmcFunctions.php';
require_once __DIR__ . '/../../../../vendor/autoload.php';

use App\View\Components\Navbar\Navbar;
use App\View\Components\HeroSection\HeroSection;
use App\View\Components\Footer\Footer;

$client = getElasticsearchClient();

$limit       = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT) ?: 20;
$page        = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
$status      = trim(strip_tags((string) filter_input(INPUT_GET, 'status')));
$ppg         = trim(strip_tags((string) filter_input(INPUT_GET, 'ppg')));
$ano_inicio  = trim(strip_tags((string) filter_input(INPUT_GET, 'ano_inicio')));
$ano_fim     = trim(strip_tags((string) filter_input(INPUT_GET, 'ano_fim')));
$coordenador = trim(strip_tags((string) filter_input(INPUT_GET, 'coordenador')));

$from = ($page - 1) * $limit;

$projetos = [];
$total    = 0;

if ($client !== null) {
    try {
        $must_queries   = [];
        $filter_queries = [];

        if (!empty($coordenador)) {
            $must_queries[] = ['query_string' => ['query' => $coordenador, 'fields' => ['coordenador', 'equipe']]];
        }
        if (!empty($status)) { $filter_queries[] = ['term' => ['status.keyword' => $status]]; }
        if (!empty($ppg))    { $must_queries[]   = ['match' => ['ppg' => $ppg]]; }
        if (!empty($ano_inicio) || !empty($ano_fim)) {
            $range = [];
            if (!empty($ano_inicio)) { $range['gte'] = (int)$ano_inicio; }
            if (!empty($ano_fim))    { $range['lte'] = (int)$ano_fim; }
            $filter_queries[] = ['range' => ['ano_inicio' => $range]];
        }

        if (!empty($must_queries) || !empty($filter_queries)) {
            $query = ['bool' => []];
            if (!empty($must_queries))   { $query['bool']['must']   = $must_queries; }
            if (!empty($filter_queries)) { $query['bool']['filter'] = $filter_queries; }
        } else {
            $query = ['match_all' => new stdClass()];
        }

        $params   = ['index' => $index_projetos, 'body' => ['query' => $query, 'sort' => [['ano_inicio' => ['order' => 'desc']]], 'from' => $from, 'size' => $limit]];
        $response = $client->search($params);
        $total    = $response['hits']['total']['value'] ?? 0;

        if (isset($response['hits']['hits'])) {
            foreach ($response['hits']['hits'] as $hit) { $projetos[] = $hit['_source']; }
        }
    } catch (Exception $e) {
        error_log("Erro ao buscar projetos: " . $e->getMessage());
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
    <title>Projetos de Pesquisa - <?php echo $branch; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/prodmais-elegant.css?v=4">
    <link rel="stylesheet" href="/css/umc-theme.css">
</head>
<body>

<?php
Navbar::display([
    'active_page'            => 'projetos',
    'mostrar_link_dashboard' => $mostrar_link_dashboard ?? true,
]);
?>
<?php renderNavbarAuthBadge(); ?>

<!-- ══ Hero Projetos ══ -->
<style>
.proj-hero {
    background: #070d1f;
    background-image:
        radial-gradient(ellipse 55% 65% at 5% 75%, rgba(5,150,105,.12), transparent),
        radial-gradient(ellipse 40% 40% at 92% 10%, rgba(13,148,136,.09), transparent),
        radial-gradient(ellipse 30% 30% at 50% 92%, rgba(16,185,129,.07), transparent);
    position: relative; overflow: hidden;
    padding: 5.5rem 0 3.5rem;
}
.proj-hero::before {
    content: '';
    position: absolute; inset: 0;
    background-image: radial-gradient(rgba(255,255,255,.05) 1px, transparent 1px);
    background-size: 28px 28px;
    pointer-events: none;
}
.proj-hero-stats {
    display: flex; align-items: center; justify-content: center; gap: 0;
    margin-top: 2.5rem;
    border: 1px solid rgba(255,255,255,.1); border-radius: 14px;
    overflow: hidden; background: rgba(255,255,255,.04);
    backdrop-filter: blur(8px); max-width: 360px; margin-left: auto; margin-right: auto;
}
.proj-hero-stat { flex:1; padding: 1.1rem 1.25rem; text-align: center; }
.proj-hero-stat + .proj-hero-stat { border-left: 1px solid rgba(255,255,255,.1); }
.proj-hero-stat-num { font-size: 1.875rem; font-weight: 900; color: #f1f5f9; line-height: 1; letter-spacing: -1px; margin-bottom: .2rem; }
.proj-hero-stat-lbl { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: rgba(241,245,249,.4); }

/* ── Filter sidebar premium ── */
.proj-filter-panel {
    background: white;
    border-radius: 20px;
    border: 1px solid rgba(0,0,0,.07);
    box-shadow: 0 2px 14px rgba(0,0,0,.07);
    overflow: hidden;
}
@media (min-width: 992px) {
    .proj-filter-panel {
        position: sticky;
        top: 1.5rem;
        align-self: flex-start;
        max-height: calc(100vh - 3rem);
        overflow-y: auto;
    }
}
/* Mobile: painel de filtros vira bottom-sheet acionado pelo .filter-fab
   (classes .filter-fab/.filter-drawer-overlay vêm de prodmais-elegant.css) */
@media (max-width: 991px) {
    .proj-filter-panel {
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        top: auto;
        z-index: 1045;
        max-height: 85vh;
        overflow-y: auto;
        border-radius: 24px 24px 0 0;
        box-shadow: 0 -8px 40px rgba(0, 0, 0, .18);
        transform: translateY(100%);
        transition: transform .3s cubic-bezier(.32, .72, 0, 1);
    }
    .proj-filter-panel.open {
        transform: translateY(0);
    }
}
.proj-filter-title {
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.09em;
    color: var(--gray-400, #94a3b8);
    margin: 0 0 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.proj-filter-body { padding: 1.25rem; }
.proj-filter-group { margin-bottom: 1.1rem; }
.proj-filter-label { font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #64748b; margin-bottom: .4rem; display: block; }
.proj-filter-input, .proj-filter-select {
    width: 100%; border: 1.5px solid rgba(0,0,0,.1); border-radius: 10px;
    padding: .55rem .875rem; font-size: .875rem; color: #1e293b; background: white;
    transition: border-color .2s; appearance: none;
}
.proj-filter-input:focus, .proj-filter-select:focus { outline: none; border-color: #0d9488; box-shadow: 0 0 0 3px rgba(13,148,136,.1); }
.proj-filter-select { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 8L1 3h10z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right .875rem center; }
.proj-filter-btn {
    display: block; width: 100%; border: none; border-radius: 10px; padding: .7rem 1rem;
    background: linear-gradient(135deg,#059669,#0d9488); color: white; font-weight: 700; font-size: .875rem;
    cursor: pointer; transition: filter .2s, transform .2s;
    box-shadow: 0 4px 12px rgba(5,150,105,.25);
}
.proj-filter-btn:hover { filter: brightness(1.08); transform: translateY(-1px); }
.proj-filter-divider { border: none; border-top: 1px solid #f1f5f9; margin: 1rem 0; }
.proj-filter-count { text-align: center; }
.proj-filter-count-num { font-size: 2rem; font-weight: 900; color: #059669; line-height: 1; letter-spacing: -1px; }
.proj-filter-count-lbl { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; }
.proj-filter-clear { display: block; text-align: center; margin-top: .75rem; font-size: .8rem; font-weight: 600; color: #94a3b8; text-decoration: none; transition: color .2s; }
.proj-filter-clear:hover { color: #ef4444; }

/* ── Project cards ── */
.proj-section { background: #f8fafc; padding: 4rem 0 5rem; }
.proj-card {
    background: white; border-radius: 20px;
    border: 1px solid rgba(0,0,0,.07);
    box-shadow: 0 2px 12px rgba(0,0,0,.06);
    padding: 1.5rem 1.75rem;
    margin-bottom: 1rem;
    transition: transform .22s ease, box-shadow .22s ease;
}
.proj-card:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(0,0,0,.12); }
.proj-card-title { font-size: 1.05rem; font-weight: 700; color: #0f172a; margin: 0 0 .75rem; line-height: 1.35; }
.proj-meta { display: flex; flex-wrap: wrap; gap: .6rem; margin: .75rem 0; }
.proj-meta-item { display: flex; align-items: center; gap: .35rem; font-size: .8rem; color: #64748b; font-weight: 500; }
.proj-meta-item i { color: #059669; font-size: .75rem; }
.proj-badge-ppg { display: inline-flex; align-items: center; gap: .35rem; font-size: .72rem; font-weight: 700; padding: .3rem .75rem; border-radius: 100px; background: rgba(5,150,105,.1); color: #065f46; }
.proj-status-badge { display: inline-flex; align-items: center; gap: .35rem; font-size: .72rem; font-weight: 700; padding: .3rem .75rem; border-radius: 100px; white-space: nowrap; }
.proj-status-badge.concluido  { background: rgba(5,150,105,.12); color: #065f46; }
.proj-status-badge.andamento  { background: rgba(59,130,246,.12); color: #1e40af; }
.proj-status-badge.ativo      { background: rgba(16,185,129,.12); color: #065f46; }
.proj-status-badge i { font-size: .5rem; }
.proj-btn-detail {
    display: inline-flex; align-items: center; gap: .4rem;
    background: linear-gradient(135deg,#059669,#0d9488); color: white; border: none;
    border-radius: 10px; padding: .55rem 1.1rem; font-size: .8rem; font-weight: 700;
    cursor: pointer; transition: filter .2s, transform .2s;
    box-shadow: 0 3px 10px rgba(5,150,105,.22);
}
.proj-btn-detail:hover { filter: brightness(1.08); transform: translateY(-1px); }

/* ── Empty state ── */
.proj-empty {
    text-align: center; padding: 4rem 2rem;
    background: white; border-radius: 20px;
    border: 1px solid rgba(0,0,0,.07);
}
.proj-empty i { font-size: 3.5rem; color: #cbd5e1; margin-bottom: 1.25rem; display: block; }
.proj-empty h4 { font-weight: 700; color: #1e293b; margin-bottom: .5rem; }
.proj-empty p { color: #94a3b8; margin: 0; }
</style>

<section class="proj-hero">
    <div class="container text-center" style="position:relative;z-index:1;">

        <div style="display:inline-flex;align-items:center;gap:.5rem;background:rgba(5,150,105,.15);border:1px solid rgba(5,150,105,.3);border-radius:100px;padding:.375rem 1rem;font-size:.75rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#6ee7b7;margin-bottom:1.75rem;">
            <i class="fas fa-flask" style="font-size:.7rem;"></i>
            Pesquisa & Inovação · UMC
        </div>

        <h1 style="font-size:clamp(1.8rem,5vw,4rem);font-weight:900;line-height:1.05;letter-spacing:-2px;color:#f1f5f9;margin:0 0 1rem;">
            Projetos de<br>
            <span style="background:linear-gradient(135deg,#34d399 0%,#0d9488 55%,#60a5fa 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">Pesquisa</span>
        </h1>

        <p style="font-size:1rem;color:rgba(241,245,249,.5);max-width:480px;margin:0 auto;line-height:1.6;">
            Conheça os projetos desenvolvidos pelos Programas de Pós-Graduação da UMC
        </p>

        <div class="proj-hero-stats">
            <div class="proj-hero-stat">
                <div class="proj-hero-stat-num"><?= number_format($total) ?></div>
                <div class="proj-hero-stat-lbl">Projetos</div>
            </div>
            <div class="proj-hero-stat">
                <div class="proj-hero-stat-num"><?= count($ppgs_umc) ?></div>
                <div class="proj-hero-stat-lbl">Programas PPG</div>
            </div>
        </div>

    </div>
</section>
<!-- ══ /Hero Projetos ══ -->

<!-- Projetos Section -->
<section class="proj-section">
    <div class="container">
        <!-- Mobile: FAB + overlay que abrem o painel de filtros como bottom-sheet -->
        <button type="button" class="filter-fab" id="filterFabBtn" aria-label="Abrir filtros" aria-expanded="false" aria-controls="projFilterPanel">
            <i class="fas fa-filter" aria-hidden="true"></i>
        </button>
        <div class="filter-drawer-overlay" id="filterDrawerOverlay"></div>

        <div class="row g-4">
            <!-- Sidebar de Filtros -->
            <div class="col-lg-3">
                <div class="proj-filter-panel" id="projFilterPanel">
                    <div class="proj-filter-body">
                        <span class="filter-drawer-handle" aria-hidden="true"></span>
                        <p class="proj-filter-title">
                            <i class="fas fa-filter" aria-hidden="true"></i>
                            Filtros
                        </p>
                    <form method="GET" action="/projetos.php" id="filterForm">

                        <div class="proj-filter-group">
                            <label class="proj-filter-label" for="filterCoord">Coordenador</label>
                            <input type="text" name="coordenador" id="filterCoord"
                                   class="proj-filter-input"
                                   placeholder="Nome do coordenador"
                                   value="<?php echo htmlspecialchars($coordenador); ?>">
                        </div>

                        <div class="proj-filter-group">
                            <label class="proj-filter-label" for="filterStatus">Status</label>
                            <select name="status" id="filterStatus" class="proj-filter-select" onchange="this.form.submit()">
                                <option value="">Todos</option>
                                <option value="Em andamento" <?php echo $status === 'Em andamento' ? 'selected' : ''; ?>>Em Andamento</option>
                                <option value="Concluído"    <?php echo $status === 'Concluído'    ? 'selected' : ''; ?>>Concluído</option>
                                <option value="Aprovado"     <?php echo $status === 'Aprovado'     ? 'selected' : ''; ?>>Aprovado</option>
                            </select>
                        </div>

                        <div class="proj-filter-group">
                            <label class="proj-filter-label" for="filterPPG">PPG</label>
                            <select name="ppg" id="filterPPG" class="proj-filter-select" onchange="this.form.submit()">
                                <option value="">Todos</option>
                                <?php foreach ($ppgs_umc as $ppg_item): ?>
                                <option value="<?php echo htmlspecialchars($ppg_item['nome']); ?>"
                                        <?php echo $ppg === $ppg_item['nome'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ppg_item['sigla']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="proj-filter-group">
                            <label class="proj-filter-label">Ano de Início</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="number" name="ano_inicio" class="proj-filter-input" placeholder="De"
                                           min="2000" max="<?php echo date('Y'); ?>" value="<?php echo htmlspecialchars($ano_inicio); ?>">
                                </div>
                                <div class="col-6">
                                    <input type="number" name="ano_fim" class="proj-filter-input" placeholder="Até"
                                           min="2000" max="<?php echo date('Y'); ?>" value="<?php echo htmlspecialchars($ano_fim); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="proj-filter-group">
                            <label class="proj-filter-label" for="filterLimit">Exibir</label>
                            <select name="limit" id="filterLimit" class="proj-filter-select" onchange="this.form.submit()">
                                <option value="20"  <?php echo $limit === 20  ? 'selected' : ''; ?>>20 por página</option>
                                <option value="50"  <?php echo $limit === 50  ? 'selected' : ''; ?>>50 por página</option>
                                <option value="100" <?php echo $limit === 100 ? 'selected' : ''; ?>>100 por página</option>
                            </select>
                        </div>

                        <button type="submit" class="proj-filter-btn">
                            <i class="fas fa-search me-1" aria-hidden="true"></i>Aplicar Filtros
                        </button>

                        <?php if (!empty($status) || !empty($ppg) || !empty($ano_inicio) || !empty($ano_fim) || !empty($coordenador)): ?>
                        <a href="/projetos.php" class="proj-filter-clear">
                            <i class="fas fa-times me-1" aria-hidden="true"></i>Limpar Filtros
                        </a>
                        <?php endif; ?>
                    </form>

                    <hr class="proj-filter-divider">
                    <div class="proj-filter-count">
                        <div class="proj-filter-count-num"><?php echo number_format($total); ?></div>
                        <div class="proj-filter-count-lbl"><?php echo $total === 1 ? 'Projeto' : 'Projetos'; ?></div>
                        <?php if ($page > 1 || $total > $limit): ?>
                        <p style="font-size:.75rem;color:#94a3b8;margin:.5rem 0 0;">
                            Exibindo <?php echo number_format($from + 1); ?>–<?php echo number_format(min($from + $limit, $total)); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    </div>
                </div>
            </div>

            <!-- Lista de Projetos -->
            <div class="col-lg-9">
                <?php if (empty($projetos)): ?>
                <div class="proj-empty">
                    <i class="fas fa-flask" aria-hidden="true"></i>
                    <h4><?php echo $total === 0 ? 'Nenhum projeto cadastrado' : 'Nenhum projeto encontrado'; ?></h4>
                    <p><?php echo $total === 0 ? 'Os projetos serão exibidos assim que forem importados.' : 'Tente ajustar os filtros para ver mais resultados.'; ?></p>
                </div>
                <?php else: ?>

                <?php foreach ($projetos as $idx => $projeto):
                    $s = strtolower($projeto['status'] ?? '');
                    if (strpos($s, 'conclu') !== false) {
                        $status_class = 'concluido';
                    } elseif (strpos($s, 'andamento') !== false) {
                        $status_class = 'andamento';
                    } else {
                        $status_class = 'ativo';
                    }
                ?>
                <div class="proj-card fade-in-up" style="animation-delay:<?php echo min($idx * 0.05, 0.5); ?>s">
                    <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                        <h5 class="proj-card-title"><?php echo htmlspecialchars($projeto['titulo'] ?? 'Sem título'); ?></h5>
                        <?php if (!empty($projeto['status'])): ?>
                        <span class="proj-status-badge <?php echo $status_class; ?> flex-shrink-0">
                            <i class="fas fa-circle" aria-hidden="true"></i>
                            <?php echo htmlspecialchars($projeto['status']); ?>
                        </span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($projeto['ppg'])): ?>
                    <div style="margin-bottom:.625rem;">
                        <span class="proj-badge-ppg">
                            <i class="fas fa-graduation-cap" aria-hidden="true"></i><?php echo htmlspecialchars($projeto['ppg']); ?>
                        </span>
                    </div>
                    <?php endif; ?>

                    <div class="proj-meta">
                        <?php if (!empty($projeto['coordenador'])): ?>
                        <span class="proj-meta-item"><i class="fas fa-user-tie" aria-hidden="true"></i><?php echo htmlspecialchars($projeto['coordenador']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($projeto['ano_inicio'])): ?>
                        <span class="proj-meta-item"><i class="fas fa-calendar-alt" aria-hidden="true"></i><?php echo htmlspecialchars($projeto['ano_inicio']); ?><?php if (!empty($projeto['ano_fim'])): echo ' – ' . htmlspecialchars($projeto['ano_fim']); endif; ?></span>
                        <?php endif; ?>
                        <?php if (!empty($projeto['equipe'])): ?>
                        <span class="proj-meta-item"><i class="fas fa-users" aria-hidden="true"></i><?php echo is_array($projeto['equipe']) ? count($projeto['equipe']) : (substr_count($projeto['equipe'],',') + 1); ?> membros</span>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="button" class="proj-btn-detail"
                                data-bs-toggle="modal" data-bs-target="#modalProjeto<?php echo $idx; ?>">
                            <i class="fas fa-eye" aria-hidden="true"></i>Ver Detalhes
                        </button>
                    </div>
                </div>

                <!-- Modal -->
                <div class="modal fade" id="modalProjeto<?php echo $idx; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <div class="modal-content modal-content-ds">
                            <div class="modal-header modal-header-ds">
                                <h5 class="modal-title fw-bold"><i class="fas fa-flask me-2" aria-hidden="true"></i>Detalhes do Projeto</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                            </div>
                            <div class="modal-body p-4">
                                <h4 class="fw-bold mb-3"><?php echo htmlspecialchars($projeto['titulo'] ?? 'Sem título'); ?></h4>
                                <div class="d-flex flex-wrap gap-2 mb-4">
                                    <?php if (!empty($projeto['status'])): ?>
                                    <span class="project-status-badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($projeto['status']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($projeto['ppg'])): ?>
                                    <span class="badge-elegant badge-primary badge-xs"><?php echo htmlspecialchars($projeto['ppg']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <dl class="row row-cols-1 row-cols-md-2 g-3 detail-list">
                                    <?php if (!empty($projeto['coordenador'])): ?>
                                    <div class="col-12">
                                        <dt>Coordenador</dt>
                                        <dd class="dd-strong"><?php echo htmlspecialchars($projeto['coordenador']); ?></dd>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($projeto['descricao'])): ?>
                                    <div class="col-12">
                                        <dt>Descrição</dt>
                                        <dd><?php echo nl2br(htmlspecialchars($projeto['descricao'])); ?></dd>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($projeto['ano_inicio'])): ?>
                                    <div class="col">
                                        <dt>Ano de Início</dt>
                                        <dd class="dd-strong"><?php echo htmlspecialchars($projeto['ano_inicio']); ?></dd>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($projeto['ano_fim'])): ?>
                                    <div class="col">
                                        <dt>Ano de Término</dt>
                                        <dd class="dd-strong"><?php echo htmlspecialchars($projeto['ano_fim']); ?></dd>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($projeto['equipe'])): ?>
                                    <div class="col-12">
                                        <dt>Equipe</dt>
                                        <dd><?php echo is_array($projeto['equipe']) ? implode('<br>', array_map('htmlspecialchars', $projeto['equipe'])) : nl2br(htmlspecialchars($projeto['equipe'])); ?></dd>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($projeto['financiamento'])): ?>
                                    <div class="col">
                                        <dt>Financiamento</dt>
                                        <dd class="dd-strong"><?php echo htmlspecialchars($projeto['financiamento']); ?></dd>
                                    </div>
                                    <?php endif; ?>
                                </dl>
                            </div>
                            <div class="modal-footer modal-footer-ds">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                            </div>
                        </div>
                    </div>
                </div>

                <?php endforeach; ?>

                <!-- Paginação -->
                <?php if ($total_pages > 1):
                    $query_params = array_filter(['status' => $status, 'ppg' => $ppg, 'ano_inicio' => $ano_inicio, 'ano_fim' => $ano_fim, 'coordenador' => $coordenador, 'limit' => $limit]);
                    $query_string = http_build_query($query_params);
                    $start_page   = max(1, $page - 2);
                    $end_page     = min($total_pages, $page + 2);
                ?>
                <nav aria-label="Paginação" class="mt-4">
                    <ul class="d-flex justify-content-center gap-1 flex-wrap list-unstyled mb-0">
                        <?php if ($page > 1): ?>
                        <li><a href="/projetos.php?<?php echo $query_string; ?>&page=1"                          class="pagination-btn"><i class="fas fa-angle-double-left" aria-hidden="true"></i></a></li>
                        <li><a href="/projetos.php?<?php echo $query_string; ?>&page=<?php echo $page - 1; ?>"   class="pagination-btn"><i class="fas fa-angle-left" aria-hidden="true"></i></a></li>
                        <?php endif; ?>
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <li><a href="/projetos.php?<?php echo $query_string; ?>&page=<?php echo $i; ?>"
                               class="pagination-btn <?php echo $i === $page ? 'active' : ''; ?>"
                               <?php echo $i === $page ? 'aria-current="page"' : ''; ?>><?php echo $i; ?></a></li>
                        <?php endfor; ?>
                        <?php if ($page < $total_pages): ?>
                        <li><a href="/projetos.php?<?php echo $query_string; ?>&page=<?php echo $page + 1; ?>"   class="pagination-btn"><i class="fas fa-angle-right" aria-hidden="true"></i></a></li>
                        <li><a href="/projetos.php?<?php echo $query_string; ?>&page=<?php echo $total_pages; ?>" class="pagination-btn"><i class="fas fa-angle-double-right" aria-hidden="true"></i></a></li>
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
<script>
(function () {
    var fab     = document.getElementById('filterFabBtn');
    var overlay = document.getElementById('filterDrawerOverlay');
    var panel   = document.getElementById('projFilterPanel');
    if (!fab || !overlay || !panel) return;

    function openPanel() {
        panel.classList.add('open');
        overlay.classList.add('open');
        fab.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
    }

    function closePanel() {
        panel.classList.remove('open');
        overlay.classList.remove('open');
        fab.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }

    fab.addEventListener('click', function () {
        panel.classList.contains('open') ? closePanel() : openPanel();
    });
    overlay.addEventListener('click', closePanel);
})();
</script>
</body>
</html>
