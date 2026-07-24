<?php
/**
 * PRODMAIS UMC - Página Principal
 */

require_once __DIR__ . '/../../../../src/UmcFunctions.php';
require_once __DIR__ . '/../../../../vendor/autoload.php';

use App\View\Components\Navbar\Navbar;
use App\View\Components\HeroSection\HeroSection;
use App\View\Components\Footer\Footer;
use App\View\Components\StatCard\StatCard;

$client = getElasticsearchClient();
$elasticsearch_available = ($client !== null);
$total_records = 0;

if ($elasticsearch_available) {
    try {
        $count = $client->count(['index' => $index]);
        $total_records = $count['count'] ?? 0;
    } catch (Exception $e) {
        $elasticsearch_available = false;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/img/umc-favicon.png">
    <title><?php echo $branch; ?> - Sistema de Gestão de Produção Científica</title>
    <meta name="description" content="<?php echo $branch_description; ?>">
    <meta name="keywords" content="produção científica, lattes, ORCID, UMC, pós-graduação, biotecnologia, engenharia biomédica">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:url" content="<?php echo $url_base; ?>">
    <meta property="og:title" content="<?php echo $branch; ?> - Página Principal">
    <meta property="og:description" content="<?php echo $branch_description; ?>">
    <meta property="og:image" content="<?php echo $facebook_image; ?>">
    <meta property="og:type" content="website">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/prodmais-elegant.css?v=4">
    <link rel="stylesheet" href="/css/umc-theme.css">
    <?php HookManager::doAction('app_head'); ?>
</head>
<body>

<?php Navbar::display(['active_page' => 'home', 'mostrar_link_dashboard' => $mostrar_link_dashboard]); ?>
<?php renderNavbarAuthBadge(); ?>

<!-- ══ Hero Customizado ══ -->
<style>
.home-hero {
    background: #070d1f;
    background-image:
        radial-gradient(ellipse 55% 55% at 15% 65%, rgba(59,130,246,.13), transparent),
        radial-gradient(ellipse 45% 45% at 85% 15%, rgba(99,102,241,.09), transparent),
        radial-gradient(ellipse 30% 30% at 60% 85%, rgba(16,185,129,.07), transparent);
    position: relative;
    overflow: hidden;
    padding: 5.5rem 0 4rem;
}
.home-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image: radial-gradient(rgba(255,255,255,.055) 1px, transparent 1px);
    background-size: 28px 28px;
    pointer-events: none;
}
.home-hero::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 80px;
    background: linear-gradient(to bottom, transparent, rgba(7,13,31,.6));
    pointer-events: none;
}
.hero-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    background: rgba(59,130,246,.12);
    border: 1px solid rgba(59,130,246,.25);
    border-radius: 100px;
    padding: .375rem 1rem;
    font-size: .75rem;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: #93c5fd;
    margin-bottom: 1.75rem;
}
.hero-eyebrow-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #3b82f6;
    animation: pulse-dot 2s ease-in-out infinite;
}
@keyframes pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50%       { opacity: .5; transform: scale(.7); }
}
.hero-title {
    font-size: clamp(1.85rem, 6vw, 5rem);
    font-weight: 900;
    line-height: 1.05;
    letter-spacing: -2.5px;
    color: #f1f5f9;
    margin: 0 0 1.125rem;
}
.hero-title .hl {
    background: linear-gradient(135deg, #60a5fa 0%, #34d399 60%, #a78bfa 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.hero-sub {
    font-size: 1.1rem;
    color: rgba(241,245,249,.55);
    max-width: 520px;
    line-height: 1.65;
    margin: 0 auto 2.5rem;
}
.hero-search-wrap {
    max-width: 620px;
    margin: 0 auto 1.5rem;
    position: relative;
    z-index: 1;
}
.hero-search-inner {
    display: flex;
    background: rgba(255,255,255,.06);
    border: 1.5px solid rgba(255,255,255,.12);
    border-radius: 16px;
    overflow: hidden;
    backdrop-filter: blur(12px);
    transition: border-color .2s ease, box-shadow .2s ease;
}
.hero-search-inner:focus-within {
    border-color: rgba(96,165,250,.5);
    box-shadow: 0 0 0 4px rgba(59,130,246,.12);
}
.hero-search-inner input {
    flex: 1;
    background: transparent;
    border: none;
    outline: none;
    padding: 1rem 1.25rem;
    font-size: 1rem;
    color: #f1f5f9;
    font-family: 'Inter', sans-serif;
}
.hero-search-inner input::placeholder { color: rgba(241,245,249,.3); }
.hero-search-btn {
    background: linear-gradient(135deg, #1a56db, #0369a1);
    border: none;
    color: white;
    padding: .75rem 1.75rem;
    font-size: .9375rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: .5rem;
    cursor: pointer;
    transition: all .2s ease;
    margin: .375rem;
    border-radius: 12px;
    white-space: nowrap;
}
.hero-search-btn:hover { background: linear-gradient(135deg, #1e40af, #0369a1); transform: translateY(-1px); }
.hero-pills {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .75rem;
    flex-wrap: wrap;
}
.hero-pill {
    display: inline-flex;
    align-items: center;
    gap: .375rem;
    background: rgba(255,255,255,.06);
    border: 1px solid rgba(255,255,255,.1);
    border-radius: 100px;
    padding: .3rem .875rem;
    font-size: .775rem;
    font-weight: 600;
    color: rgba(241,245,249,.6);
}
.hero-pill i { font-size: .7rem; opacity: .7; }
.hero-es-notice {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    background: rgba(245,158,11,.08);
    border: 1px solid rgba(245,158,11,.2);
    border-radius: 10px;
    padding: .5rem 1rem;
    font-size: .8125rem;
    color: rgba(253,230,138,.85);
    margin-bottom: 2rem;
    max-width: 480px;
}
.hero-es-notice i { color: #fbbf24; flex-shrink: 0; }

@media (max-width: 480px) {
    /* Botão com white-space:nowrap não encolhia — espremia o campo de
       busca até sobrar quase nada de espaço pra digitar no mobile. */
    .hero-search-inner { flex-direction: column; }
    .hero-search-btn { margin: 0; width: calc(100% - .75rem); justify-content: center; }
}
</style>
<section class="home-hero">
    <div class="container text-center" style="position:relative;z-index:1;">

        <div class="hero-eyebrow">
            <span class="hero-eyebrow-dot"></span>
            Gestão Científica · CAPES · UMC
        </div>

        <h1 class="hero-title">
            Produção científica<br>
            da UMC, <span class="hl">indexada</span>
        </h1>

        <p class="hero-sub"><?php echo htmlspecialchars($slogan); ?></p>

        <?php if (!$elasticsearch_available): ?>
        <div class="hero-es-notice" style="margin:0 auto 2rem;">
            <i class="fas fa-exclamation-triangle"></i>
            Elasticsearch offline — buscas em modo limitado via banco de dados.
        </div>
        <?php endif; ?>

        <div class="hero-search-wrap">
            <form action="/presearch.php" method="POST">
                <div class="hero-search-inner">
                    <input type="search"
                           name="search"
                           placeholder="Pesquise produções, pesquisadores, projetos..."
                           aria-label="Buscar produções científicas"
                           required>
                    <button type="submit" class="hero-search-btn">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                </div>
            </form>
        </div>

        <div class="hero-pills">
            <span class="hero-pill"><i class="fas fa-dna"></i> Biotecnologia</span>
            <span class="hero-pill"><i class="fas fa-heartbeat"></i> Eng. Biomédica</span>
            <span class="hero-pill"><i class="fas fa-balance-scale"></i> Políticas Públicas</span>
            <span class="hero-pill"><i class="fas fa-flask"></i> Ciência e Saúde</span>
        </div>

    </div>
</section>
<!-- ══ /Hero ══ -->

<?php
$total_producoes    = 0;
$total_pesquisadores = 0;
$total_projetos     = 0;

if ($client) {
    try { $total_producoes    = $client->count(['index' => $index])['count'] ?? 0; } catch (Exception $e) {}
    try { $total_pesquisadores = $client->count(['index' => $index_cv])['count'] ?? 0; } catch (Exception $e) {}
    try { $total_projetos     = $client->count(['index' => $index_projetos])['count'] ?? 0; } catch (Exception $e) {}
}
?>

<!-- Números do Sistema -->
<section style="background:#fff;padding:3.5rem 0;border-bottom:1px solid var(--gray-100);">
    <div class="container">
        <div class="row g-0" style="max-width:860px;margin:0 auto;">
            <?php
            $metrics = [
                ['val' => $total_producoes,    'label' => 'Produções indexadas', 'icon' => 'microscope',      'link' => '/presearch.php',      'grad' => 'linear-gradient(135deg,#1a56db,#0369a1)'],
                ['val' => $total_pesquisadores, 'label' => 'Pesquisadores ativos','icon' => 'user-graduate',   'link' => '/pesquisadores.php',  'grad' => 'linear-gradient(135deg,#10b981,#059669)'],
                ['val' => count($ppgs_umc),     'label' => 'Programas PPG',       'icon' => 'university',      'link' => '/ppgs.php',           'grad' => 'linear-gradient(135deg,#8b5cf6,#6d28d9)'],
                ['val' => $total_projetos,      'label' => 'Projetos de pesquisa','icon' => 'project-diagram', 'link' => '/projetos.php',       'grad' => 'linear-gradient(135deg,#f59e0b,#d97706)'],
            ];
            foreach ($metrics as $m => $stat):
            ?>
            <div class="col-6 col-md-3 fade-in-up" style="animation-delay:<?= $m * 0.07 ?>s;<?= $m < 3 ? 'border-right:1px solid var(--gray-100);' : '' ?>padding:1.5rem 2rem;text-align:center;">
                <a href="<?= $stat['link'] ?>" style="text-decoration:none;display:block;">
                    <div style="width:48px;height:48px;border-radius:14px;background:<?= $stat['grad'] ?>;display:flex;align-items:center;justify-content:center;margin:0 auto .875rem;box-shadow:0 6px 16px rgba(0,0,0,.12);">
                        <i class="fas fa-<?= $stat['icon'] ?>" style="color:white;font-size:1.1rem;"></i>
                    </div>
                    <div class="stat-num" data-value="<?= $stat['val'] ?>" style="font-size:2.5rem;font-weight:900;line-height:1;color:var(--gray-900);letter-spacing:-1.5px;margin-bottom:.375rem;"><?= number_format($stat['val'], 0, ',', '.') ?></div>
                    <div style="font-size:.8rem;font-weight:600;color:var(--gray-500);text-transform:uppercase;letter-spacing:.06em;"><?= $stat['label'] ?></div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Programas de Pós-Graduação -->
<?php
$ppg_colors = [
    'Biotecnologia'                   => ['from' => '#059669', 'to' => '#0d9488', 'light' => '#d1fae5', 'text' => '#065f46', 'icon' => 'dna'],
    'Engenharia Biomédica'            => ['from' => '#1a56db', 'to' => '#0369a1', 'light' => '#dbeafe', 'text' => '#1e3a8a', 'icon' => 'heartbeat'],
    'Políticas Públicas'              => ['from' => '#d97706', 'to' => '#b45309', 'light' => '#fef3c7', 'text' => '#78350f', 'icon' => 'balance-scale'],
    'Ciência e Tecnologia em Saúde'   => ['from' => '#7c3aed', 'to' => '#5b21b6', 'light' => '#ede9fe', 'text' => '#4c1d95', 'icon' => 'flask'],
];
?>
<section class="page-section page-section-gray">
    <div class="container">
        <div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:3rem;">
            <div>
                <span style="display:inline-block;font-size:.75rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--blue-600,#1a56db);margin-bottom:.5rem;">Pós-Graduação UMC</span>
                <h2 style="font-size:clamp(1.5rem,3vw,2.25rem);font-weight:900;color:var(--gray-900);margin:0;line-height:1.1;">Programas de Excelência</h2>
                <p style="color:var(--gray-500);margin:.5rem 0 0;font-size:1rem;">Quatro programas credenciados pela CAPES</p>
            </div>
            <a href="/ppgs.php" style="display:inline-flex;align-items:center;gap:.5rem;color:var(--blue-600,#1a56db);font-weight:600;font-size:.9rem;text-decoration:none;white-space:nowrap;">
                Ver todos os PPGs <i class="fas fa-arrow-right"></i>
            </a>
        </div>

        <div class="row g-4">
            <?php foreach ($ppgs_umc as $i => $ppg):
                $slug = trim($ppg['nome']);
                $c = $ppg_colors[$slug] ?? $ppg_colors['Engenharia Biomédica'];
            ?>
            <div class="col-12 col-lg-6 fade-in-up" style="animation-delay:<?= $i * 0.1 ?>s">
                <div style="background:white;border-radius:20px;overflow:hidden;border:1px solid var(--gray-200);box-shadow:0 2px 16px rgba(0,0,0,.06);transition:all .3s cubic-bezier(.4,0,.2,1);height:100%;display:flex;flex-direction:column;" onmouseover="this.style.transform='translateY(-6px)';this.style.boxShadow='0 16px 40px rgba(0,0,0,.12)'" onmouseout="this.style.transform='';this.style.boxShadow='0 2px 16px rgba(0,0,0,.06)'">

                    <!-- Colored Header Band -->
                    <div style="background:linear-gradient(135deg,<?= $c['from'] ?>,<?= $c['to'] ?>);padding:1.75rem 1.75rem 1.25rem;position:relative;overflow:hidden;">
                        <div style="position:absolute;top:-20px;right:-20px;width:100px;height:100px;border-radius:50%;background:rgba(255,255,255,.08);"></div>
                        <div style="position:absolute;bottom:-30px;right:30px;width:70px;height:70px;border-radius:50%;background:rgba(255,255,255,.05);"></div>
                        <div style="display:flex;align-items:center;gap:1rem;">
                            <div style="width:52px;height:52px;border-radius:16px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0;backdrop-filter:blur(8px);">
                                <i class="fas fa-<?= $c['icon'] ?>" style="color:white;font-size:1.375rem;"></i>
                            </div>
                            <div>
                                <div style="font-size:.7rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.7);margin-bottom:.25rem;"><?= htmlspecialchars($ppg['nivel']) ?></div>
                                <h3 style="font-size:1.2rem;font-weight:800;color:white;margin:0;line-height:1.2;"><?= htmlspecialchars($ppg['nome']) ?></h3>
                            </div>
                        </div>
                    </div>

                    <!-- Body -->
                    <div style="padding:1.5rem 1.75rem;flex:1;">
                        <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:1rem;">
                            <i class="fas fa-map-marker-alt" style="color:<?= $c['from'] ?>;font-size:.85rem;"></i>
                            <span style="font-size:.85rem;font-weight:600;color:var(--gray-600);"><?= htmlspecialchars($ppg['campus']) ?></span>
                        </div>
                        <div style="font-size:.75rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--gray-400);margin-bottom:.875rem;">Áreas de Concentração</div>
                        <div style="display:flex;flex-wrap:wrap;gap:.5rem;">
                            <?php foreach ($ppg['areas_concentracao'] as $area): ?>
                            <span style="display:inline-block;background:<?= $c['light'] ?>;color:<?= $c['text'] ?>;font-size:.775rem;font-weight:600;padding:.3rem .75rem;border-radius:100px;"><?= htmlspecialchars($area) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div style="padding:1.25rem 1.75rem;border-top:1px solid var(--gray-100);display:flex;align-items:center;justify-content:space-between;gap:1rem;">
                        <span style="font-size:.8rem;color:var(--gray-400);font-family:'Courier New',monospace;letter-spacing:.02em;"><?= htmlspecialchars($ppg['codigo_capes']) ?></span>
                        <a href="/ppg.php?ppg=<?= urlencode($ppg['nome']) ?>" style="display:inline-flex;align-items:center;gap:.5rem;background:linear-gradient(135deg,<?= $c['from'] ?>,<?= $c['to'] ?>);color:white;font-size:.875rem;font-weight:700;padding:.6rem 1.25rem;border-radius:10px;text-decoration:none;box-shadow:0 4px 12px rgba(0,0,0,.15);transition:all .25s ease;white-space:nowrap;" onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 6px 18px rgba(0,0,0,.2)'" onmouseout="this.style.transform='';this.style.boxShadow='0 4px 12px rgba(0,0,0,.15)'">
                            Ver Produções <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>

                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Recursos e Funcionalidades -->
<section class="page-section page-section-white">
    <div class="container">
        <div class="page-section-header text-center">
            <h2 class="page-section-title">Recursos e Funcionalidades</h2>
            <p class="page-section-sub mx-auto">Tecnologia de ponta para gestão científica da pós-graduação</p>
        </div>
        <div class="row g-4">
            <?php
            $features = [
                ['icon' => 'search',      'fa' => 'fas', 'fi' => 'fi-blue',  'title' => 'Busca Avançada',     'body' => 'Sistema de busca multi-índice com filtros por PPG, área, período e tipo de produção.'],
                ['icon' => 'file-export', 'fa' => 'fas', 'fi' => 'fi-green', 'title' => 'Exportação Múltipla', 'body' => 'Exporte para BibTeX, RIS, CSV, JSON, XML, ORCID e BrCris com um clique.'],
                ['icon' => 'shield-alt',  'fa' => 'fas', 'fi' => 'fi-amber', 'title' => 'Conformidade LGPD',   'body' => 'Sistema em conformidade com a Lei Geral de Proteção de Dados Pessoais.'],
                ['icon' => 'orcid',       'fa' => 'fab', 'fi' => 'fi-orcid', 'title' => 'Integração ORCID',    'body' => 'Exportação e vinculação direta ao perfil ORCID dos pesquisadores.'],
                ['icon' => 'chart-bar',   'fa' => 'fas', 'fi' => 'fi-navy',  'title' => 'Dashboard Interativo','body' => 'Visualizações interativas e métricas de produção em tempo real.'],
                ['icon' => 'award',       'fa' => 'fas', 'fi' => 'fi-sky',   'title' => 'Qualis CAPES',        'body' => 'Classificação Qualis 2017-2020 integrada para cada produção indexada.'],
            ];
            foreach ($features as $j => $f):
            ?>
            <div class="col-12 col-sm-6 col-lg-4 fade-in-up" style="animation-delay:<?php echo $j * 0.06; ?>s">
                <div class="content-card text-center">
                    <div class="feature-icon <?php echo $f['fi']; ?> mx-auto mb-3">
                        <i class="<?php echo $f['fa']; ?> fa-<?php echo $f['icon']; ?>" aria-hidden="true"></i>
                    </div>
                    <h5 class="content-card-title"><?php echo $f['title']; ?></h5>
                    <p class="content-card-body"><?php echo $f['body']; ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CTA Final -->
<section class="page-section page-section-dark" style="background:linear-gradient(160deg,#0f1f4b 0%,#0d1b4a 55%,#0a1535 100%);position:relative;overflow:hidden;">
    <div style="position:absolute;inset:0;background-image:radial-gradient(circle at 20% 50%,rgba(59,130,246,.12),transparent 55%),radial-gradient(circle at 80% 20%,rgba(30,64,175,.15),transparent 55%);pointer-events:none;"></div>
    <div class="container text-center" style="position:relative;z-index:1;">
        <span class="badge-elegant badge-primary mb-3" style="background:rgba(255,255,255,.1);color:rgba(255,255,255,.85);border:1px solid rgba(255,255,255,.2);">
            <i class="fas fa-rocket me-1"></i>Comece agora
        </span>
        <h2 style="font-size:clamp(1.75rem,3.5vw,2.75rem);font-weight:900;color:#fff;margin-bottom:1rem;line-height:1.2;">
            Explore a Produção Científica<br>
            <span style="background:linear-gradient(135deg,#60a5fa,#34d399);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">da UMC em tempo real</span>
        </h2>
        <p style="color:rgba(255,255,255,.7);font-size:1.125rem;max-width:560px;margin:0 auto 2.5rem;">
            Acesse artigos, livros, projetos e currículos de pesquisadores dos nossos Programas de Pós-Graduação.
        </p>
        <div class="d-flex gap-3 justify-content-center flex-wrap">
            <a href="/presearch.php?q=pesquisa" class="btn-primary-ds d-inline-flex align-items-center gap-2" style="padding:.9rem 2rem;border-radius:12px;font-size:1rem;text-decoration:none;background:linear-gradient(135deg,#1a56db,#0369a1);">
                <i class="fas fa-search"></i> Explorar Produções Científicas
            </a>
            <a href="/pesquisadores.php" style="display:inline-flex;align-items:center;gap:.5rem;background:rgba(255,255,255,.1);color:#fff;border:1.5px solid rgba(255,255,255,.3);padding:.9rem 2rem;border-radius:12px;font-weight:600;font-size:1rem;text-decoration:none;transition:all .3s ease;" onmouseover="this.style.background='rgba(255,255,255,.2)'" onmouseout="this.style.background='rgba(255,255,255,.1)'">
                <i class="fas fa-users"></i> Ver Pesquisadores
            </a>
        </div>
    </div>
</section>

<?php Footer::display(); ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animar contadores nos stat-cards
    document.querySelectorAll('.stat-card-value').forEach(function(el) {
        var raw = el.getAttribute('data-value') || el.textContent.replace(/\D/g, '');
        var target = parseInt(raw, 10);
        if (!isNaN(target) && target > 0) {
            var current = 0;
            var increment = target / 50;
            var timer = setInterval(function() {
                current += increment;
                if (current >= target) {
                    el.textContent = target.toLocaleString('pt-BR');
                    clearInterval(timer);
                } else {
                    el.textContent = Math.floor(current).toLocaleString('pt-BR');
                }
            }, 20);
        }
    });
    <?php HookManager::doAction('app_footer'); ?>
});
</script>
</body>
</html>
