<?php
/**
 * PRODMAIS UMC - Pré-Busca Multi-Índice
 * Estilo UNIFESP - Mostra contadores de resultados
 */

require_once __DIR__ . '/../../../../config/config_umc.php';
require_once __DIR__ . '/../../../../src/UmcFunctions.php';
require_once __DIR__ . '/../../../../vendor/autoload.php';

use App\View\Components\Navbar\Navbar;
use App\View\Components\HeroSection\HeroSection;
use App\View\Components\Footer\Footer;

// Processar busca
$post_search = trim(strip_tags((string) filter_input(INPUT_POST, 'search')));
$get_q       = trim(strip_tags((string) filter_input(INPUT_GET, 'q')));
$search_term = $post_search !== '' ? $post_search : $get_q;

if (empty($search_term)) {
    header('Location: /index_umc.php');
    exit;
}

// Busca multi-índice
$multiSearch = new MultiIndexSearch();
$client = getElasticsearchClient();

// Contadores por índice
$count_producoes = 0;
$count_pesquisadores = 0;
$count_projetos = 0;

if ($client) {
    try {
        // Contar produções
        $params_prod = [
            'index' => $index,
            'body'  => [
                'query' => ['query_string' => ['query' => $search_term]]
            ]
        ];
        $result_prod = $client->count($params_prod);
        $count_producoes = $result_prod['count'] ?? 0;

        // Contar pesquisadores
        $params_cv = [
            'index' => $index_cv,
            'body'  => [
                'query' => ['query_string' => ['query' => $search_term, 'fields' => ['nome_completo', 'resumo_cv.texto_resumo_cv_rh']]]
            ]
        ];
        $result_cv = $client->count($params_cv);
        $count_pesquisadores = $result_cv['count'] ?? 0;

        // Contar projetos
        $params_proj = [
            'index' => $index_projetos,
            'body'  => [
                'query' => ['query_string' => ['query' => $search_term]]
            ]
        ];
        $result_proj = $client->count($params_proj);
        $count_projetos = $result_proj['count'] ?? 0;

    } catch (Exception $e) {
        error_log("Erro na pré-busca: " . $e->getMessage());
    }
}

$total_results = $count_producoes + $count_pesquisadores + $count_projetos;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/img/umc-favicon.png">
    <title>Resultados para "<?php echo htmlspecialchars($search_term); ?>" - <?php echo $branch; ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/umc-theme.css">
    <link rel="stylesheet" href="/css/prodmais-elegant.css?v=4">

</head>
<body>

<?php
Navbar::display([
    'mostrar_link_dashboard' => $mostrar_link_dashboard ?? true,
]);
?>
<?php renderNavbarAuthBadge(); ?>

<!-- ══ Hero Presearch ══ -->
<style>
.presearch-hero {
    background: #070d1f;
    background-image:
        radial-gradient(ellipse 50% 50% at 20% 60%, rgba(59,130,246,.12), transparent),
        radial-gradient(ellipse 40% 40% at 80% 20%, rgba(99,102,241,.09), transparent);
    position: relative;
    overflow: hidden;
    padding: 4.5rem 0 3.5rem;
}
.presearch-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image: radial-gradient(rgba(255,255,255,.05) 1px, transparent 1px);
    background-size: 28px 28px;
    pointer-events: none;
}
.presearch-query {
    display: inline-flex;
    align-items: center;
    gap: .6rem;
    background: rgba(255,255,255,.07);
    border: 1px solid rgba(255,255,255,.14);
    border-radius: 12px;
    padding: .75rem 1.5rem;
    font-size: 1.1rem;
    font-weight: 700;
    color: #f1f5f9;
    max-width: 600px;
    margin: 0 auto 1.5rem;
    word-break: break-word;
}
.presearch-query i { color: #60a5fa; font-size: .9rem; flex-shrink: 0; }
.presearch-total {
    font-size: .8rem;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: rgba(241,245,249,.4);
    margin-bottom: .5rem;
}

/* Category Cards */
.presearch-section {
    background: #f8fafc;
    padding: 3.5rem 0 4rem;
}
.prescat-card {
    background: white;
    border-radius: 20px;
    border: 1px solid rgba(0,0,0,.07);
    box-shadow: 0 1px 3px rgba(0,0,0,.05), 0 4px 12px rgba(0,0,0,.04);
    padding: 2rem 1.75rem 1.75rem;
    display: flex;
    flex-direction: column;
    height: 100%;
    transition: transform .22s ease, box-shadow .22s ease;
    position: relative;
    overflow: hidden;
}
.prescat-card:hover { transform: translateY(-6px); box-shadow: 0 12px 32px rgba(0,0,0,.11); }
.prescat-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
}
.prescat-icon {
    width: 52px;
    height: 52px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.375rem;
    margin-bottom: 1.25rem;
    flex-shrink: 0;
}
.prescat-count {
    font-size: 3rem;
    font-weight: 900;
    line-height: 1;
    letter-spacing: -2px;
    margin-bottom: .375rem;
}
.prescat-label {
    font-size: .95rem;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: .375rem;
}
.prescat-desc {
    font-size: .8rem;
    color: #94a3b8;
    line-height: 1.5;
    flex: 1;
    margin-bottom: 1.5rem;
}
.prescat-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .5rem;
    width: 100%;
    padding: .75rem 1rem;
    border-radius: 12px;
    font-size: .875rem;
    font-weight: 700;
    border: none;
    cursor: pointer;
    text-decoration: none;
    transition: filter .2s ease, transform .2s ease;
    color: white;
}
.prescat-btn:hover { filter: brightness(1.1); transform: translateY(-1px); color: white; }
.prescat-btn:disabled, .prescat-btn.disabled {
    background: #e2e8f0 !important;
    color: #94a3b8 !important;
    cursor: not-allowed;
    filter: none;
    transform: none;
}

/* Refine section */
.presearch-refine {
    background: white;
    border: 1px solid rgba(0,0,0,.07);
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 2px 12px rgba(0,0,0,.04);
}
.presearch-refine-inner {
    display: flex;
    align-items: center;
    background: #f8fafc;
    border: 1.5px solid #e2e8f0;
    border-radius: 14px;
    overflow: hidden;
    transition: border-color .2s, box-shadow .2s;
}
.presearch-refine-inner:focus-within {
    border-color: #3b82f6;
    box-shadow: 0 0 0 4px rgba(59,130,246,.1);
}
.presearch-refine-inner input {
    flex: 1;
    border: none;
    background: transparent;
    outline: none;
    padding: .9rem 1.125rem;
    font-size: .9375rem;
    color: #0f172a;
}
.presearch-refine-inner input::placeholder { color: #94a3b8; }
.presearch-refine-submit {
    background: linear-gradient(135deg, #1a56db, #0369a1);
    border: none;
    color: white;
    padding: .7rem 1.5rem;
    font-size: .875rem;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: .5rem;
    margin: .375rem;
    border-radius: 10px;
    white-space: nowrap;
    transition: filter .2s;
}
.presearch-refine-submit:hover { filter: brightness(1.1); }

@media (max-width: 575px) {
    .presearch-refine-inner {
        flex-wrap: wrap;
        overflow: visible;
    }
    .presearch-refine-inner input {
        flex-basis: 100%;
    }
    .presearch-refine-submit {
        width: calc(100% - .75rem);
        justify-content: center;
    }
}
</style>

<section class="presearch-hero">
    <div class="container text-center" style="position:relative;z-index:1;">

        <div class="presearch-total">
            <?php if ($total_results > 0): ?>
                <i class="fas fa-check-circle" style="color:#34d399;margin-right:.4rem;"></i>
                <?= number_format($total_results) ?> resultado<?= $total_results != 1 ? 's' : '' ?> encontrado<?= $total_results != 1 ? 's' : '' ?> em 3 índices
            <?php else: ?>
                <i class="fas fa-times-circle" style="color:#f87171;margin-right:.4rem;"></i>
                Nenhum resultado encontrado
            <?php endif; ?>
        </div>

        <div style="font-size:.7rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:rgba(241,245,249,.35);margin-bottom:.75rem;">Você buscou por</div>
        <div class="presearch-query">
            <i class="fas fa-search"></i>
            <?= htmlspecialchars($search_term) ?>
        </div>

    </div>
</section>
<!-- ══ /Hero ══ -->

<!-- ══ Category Cards ══ -->
<section class="presearch-section">
    <div class="container">

        <div class="row g-4 mb-4">

            <!-- Produções Científicas -->
            <div class="col-md-4">
                <div class="prescat-card" style="--accent:#1a56db;">
                    <div class="prescat-card" style="display:contents;">
                        <style>.prescat-card:nth-child(1)::before{background:linear-gradient(90deg,#1a56db,#0369a1);}</style>
                    </div>
                    <div style="position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,#1a56db,#0369a1);"></div>
                    <div class="prescat-icon" style="background:#dbeafe;">
                        <i class="fas fa-microscope" style="color:#1a56db;" aria-hidden="true"></i>
                    </div>
                    <div class="prescat-count" style="color:#1a56db;"><?= number_format($count_producoes) ?></div>
                    <div class="prescat-label">Produções Científicas</div>
                    <div class="prescat-desc">Artigos, livros, capítulos e trabalhos em eventos indexados via Lattes</div>
                    <?php if ($count_producoes > 0): ?>
                    <form action="/result.php" method="POST" style="margin:0;">
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search_term) ?>">
                        <button type="submit" class="prescat-btn" style="background:linear-gradient(135deg,#1a56db,#0369a1);">
                            <i class="fas fa-arrow-right" aria-hidden="true"></i> Ver produções
                        </button>
                    </form>
                    <?php else: ?>
                    <span class="prescat-btn disabled">
                        <i class="fas fa-times" aria-hidden="true"></i> Sem resultados
                    </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pesquisadores -->
            <div class="col-md-4">
                <div class="prescat-card">
                    <div style="position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,#059669,#0d9488);"></div>
                    <div class="prescat-icon" style="background:#d1fae5;">
                        <i class="fas fa-users" style="color:#059669;" aria-hidden="true"></i>
                    </div>
                    <div class="prescat-count" style="color:#059669;"><?= number_format($count_pesquisadores) ?></div>
                    <div class="prescat-label">Pesquisadores</div>
                    <div class="prescat-desc">Docentes permanentes e colaboradores dos programas de pós-graduação</div>
                    <?php if ($count_pesquisadores > 0): ?>
                    <form action="/pesquisadores.php" method="GET" style="margin:0;">
                        <input type="hidden" name="q" value="<?= htmlspecialchars($search_term) ?>">
                        <button type="submit" class="prescat-btn" style="background:linear-gradient(135deg,#059669,#0d9488);">
                            <i class="fas fa-arrow-right" aria-hidden="true"></i> Ver pesquisadores
                        </button>
                    </form>
                    <?php else: ?>
                    <span class="prescat-btn disabled">
                        <i class="fas fa-times" aria-hidden="true"></i> Sem resultados
                    </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Projetos -->
            <div class="col-md-4">
                <div class="prescat-card">
                    <div style="position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,#7c3aed,#5b21b6);"></div>
                    <div class="prescat-icon" style="background:#ede9fe;">
                        <i class="fas fa-project-diagram" style="color:#7c3aed;" aria-hidden="true"></i>
                    </div>
                    <div class="prescat-count" style="color:#7c3aed;"><?= number_format($count_projetos) ?></div>
                    <div class="prescat-label">Projetos de Pesquisa</div>
                    <div class="prescat-desc">Projetos institucionais em andamento e concluídos pelos pesquisadores UMC</div>
                    <?php if ($count_projetos > 0): ?>
                    <form action="/projetos.php" method="POST" style="margin:0;">
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search_term) ?>">
                        <button type="submit" class="prescat-btn" style="background:linear-gradient(135deg,#7c3aed,#5b21b6);">
                            <i class="fas fa-arrow-right" aria-hidden="true"></i> Ver projetos
                        </button>
                    </form>
                    <?php else: ?>
                    <span class="prescat-btn disabled">
                        <i class="fas fa-times" aria-hidden="true"></i> Sem resultados
                    </span>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Refinar busca -->
        <div class="presearch-refine">
            <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1.125rem;">
                <div style="width:36px;height:36px;border-radius:10px;background:#eff6ff;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas fa-search-plus" style="color:#1a56db;font-size:.85rem;" aria-hidden="true"></i>
                </div>
                <div>
                    <div style="font-size:.875rem;font-weight:700;color:#0f172a;">Refinar busca</div>
                    <div style="font-size:.775rem;color:#94a3b8;">Tente outros termos para resultados mais específicos</div>
                </div>
                <a href="/" style="margin-left:auto;font-size:.8rem;font-weight:600;color:#64748b;text-decoration:none;display:flex;align-items:center;gap:.35rem;">
                    <i class="fas fa-home" style="font-size:.7rem;" aria-hidden="true"></i> Início
                </a>
            </div>
            <form action="/presearch.php" method="POST">
                <div class="presearch-refine-inner">
                    <input type="search" name="search"
                           placeholder="Ex: biotecnologia, genômica, saúde pública..."
                           value="<?= htmlspecialchars($search_term) ?>"
                           required
                           aria-label="Refinar busca">
                    <button type="submit" class="presearch-refine-submit">
                        <i class="fas fa-search" aria-hidden="true"></i> Buscar
                    </button>
                </div>
            </form>
        </div>

    </div>
</section>
<!-- ══ /Category Cards ══ -->

<?php Footer::display(); ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
