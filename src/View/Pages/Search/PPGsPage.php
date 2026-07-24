<?php
/**
 * PRODMAIS UMC - Programas de Pós-Graduação
 * Lista todos os PPGs cadastrados no sistema
 */

require_once __DIR__ . '/../../../../config/config_umc.php';
require_once __DIR__ . '/../../../../src/UmcFunctions.php';
require_once __DIR__ . '/../../../../vendor/autoload.php';

use App\View\Components\Navbar\Navbar;
use App\View\Components\HeroSection\HeroSection;
use App\View\Components\Footer\Footer;

$client = getElasticsearchClient();

$ppg_stats = [];
foreach ($ppgs_umc as $ppg) {
    $nome_ppg = $ppg['nome'];
    $total_producoes = 0;

    if ($client !== null) {
        try {
            $params = [
                'index' => $index,
                'body'  => ['query' => ['match' => ['ppg' => $nome_ppg]], 'size' => 0],
            ];
            $response = $client->search($params);
            $total_producoes = $response['hits']['total']['value'] ?? 0;
        } catch (Exception $e) {
            error_log("Erro ao buscar produções do PPG {$nome_ppg}: " . $e->getMessage());
        }
    }

    $ppg_stats[$nome_ppg] = $total_producoes;
}

$total_ppgs           = count($ppgs_umc);
$total_producoes_geral = array_sum($ppg_stats);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/img/umc-favicon.png">
    <title>Programas de Pós-Graduação - <?php echo $branch; ?></title>
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
    'active_page'            => 'ppgs',
    'mostrar_link_dashboard' => $mostrar_link_dashboard ?? true,
]);
?>
<?php renderNavbarAuthBadge(); ?>

<?php
$ppg_palette = [
    'Biotecnologia'                   => ['from' => '#059669', 'to' => '#0d9488', 'light' => '#d1fae5', 'text' => '#065f46', 'icon' => 'dna',           'glow' => 'rgba(5,150,105,.18)'],
    'Engenharia Biomédica'            => ['from' => '#1a56db', 'to' => '#0369a1', 'light' => '#dbeafe', 'text' => '#1e3a8a', 'icon' => 'heartbeat',     'glow' => 'rgba(26,86,219,.18)'],
    'Políticas Públicas'              => ['from' => '#d97706', 'to' => '#b45309', 'light' => '#fef3c7', 'text' => '#78350f', 'icon' => 'balance-scale',  'glow' => 'rgba(217,119,6,.18)'],
    'Ciência e Tecnologia em Saúde'   => ['from' => '#7c3aed', 'to' => '#5b21b6', 'light' => '#ede9fe', 'text' => '#4c1d95', 'icon' => 'flask',          'glow' => 'rgba(124,58,237,.18)'],
];
?>

<!-- ══ Hero PPGs ══ -->
<style>
.ppgs-hero {
    background: #070d1f;
    background-image:
        radial-gradient(ellipse 50% 60% at 10% 70%, rgba(124,58,237,.12), transparent),
        radial-gradient(ellipse 40% 40% at 90% 10%, rgba(5,150,105,.09), transparent),
        radial-gradient(ellipse 35% 35% at 55% 90%, rgba(26,86,219,.08), transparent);
    position: relative;
    overflow: hidden;
    padding: 5.5rem 0 3.5rem;
}
.ppgs-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image: radial-gradient(rgba(255,255,255,.05) 1px, transparent 1px);
    background-size: 28px 28px;
    pointer-events: none;
}
.ppgs-hero-stats {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0;
    margin-top: 3rem;
    border: 1px solid rgba(255,255,255,.1);
    border-radius: 16px;
    overflow: hidden;
    background: rgba(255,255,255,.04);
    backdrop-filter: blur(8px);
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}
.ppgs-hero-stat {
    flex: 1;
    padding: 1.25rem 1.5rem;
    text-align: center;
}
.ppgs-hero-stat + .ppgs-hero-stat {
    border-left: 1px solid rgba(255,255,255,.1);
}
.ppgs-hero-stat-num {
    font-size: 2rem;
    font-weight: 900;
    color: #f1f5f9;
    line-height: 1;
    letter-spacing: -1.5px;
    margin-bottom: .2rem;
}
.ppgs-hero-stat-label {
    font-size: .7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: rgba(241,245,249,.4);
}

/* PPG Cards */
.ppgs-section {
    background: #f8fafc;
    padding: 4rem 0 5rem;
}
.ppgs-section-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    margin-bottom: 2.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}
.ppg-card-v2 {
    background: white;
    border-radius: 22px;
    overflow: hidden;
    border: 1px solid rgba(0,0,0,.07);
    box-shadow: 0 1px 3px rgba(0,0,0,.06), 0 4px 16px rgba(0,0,0,.04);
    display: flex;
    flex-direction: column;
    height: 100%;
    transition: transform .25s ease, box-shadow .25s ease;
}
.ppg-card-v2:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 36px rgba(0,0,0,.13);
}
.ppg-card-v2-banner {
    height: 6px;
}
.ppg-card-v2-body {
    padding: 1.75rem 1.75rem 1.25rem;
    flex: 1;
    display: flex;
    flex-direction: column;
}
.ppg-card-v2-icon-wrap {
    width: 56px;
    height: 56px;
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.125rem;
    flex-shrink: 0;
}
.ppg-card-v2-nivel {
    font-size: .68rem;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    margin-bottom: .375rem;
}
.ppg-card-v2-name {
    font-size: 1.175rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.25;
    margin: 0 0 1rem;
}
.ppg-card-v2-campus {
    display: flex;
    align-items: center;
    gap: .4rem;
    font-size: .8rem;
    font-weight: 600;
    color: #64748b;
    margin-bottom: 1.25rem;
}
.ppg-card-v2-areas-label {
    font-size: .68rem;
    font-weight: 700;
    letter-spacing: .07em;
    text-transform: uppercase;
    color: #94a3b8;
    margin-bottom: .6rem;
}
.ppg-card-v2-pills {
    display: flex;
    flex-wrap: wrap;
    gap: .375rem;
    margin-bottom: 1.25rem;
}
.ppg-card-v2-pill {
    font-size: .75rem;
    font-weight: 600;
    padding: .3rem .8rem;
    border-radius: 100px;
}
.ppg-card-v2-footer {
    padding: 1rem 1.75rem 1.375rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    border-top: 1px solid #f1f5f9;
}
.ppg-card-v2-count {
    display: flex;
    align-items: baseline;
    gap: .3rem;
}
.ppg-card-v2-count-num {
    font-size: 1.375rem;
    font-weight: 900;
    color: #0f172a;
    line-height: 1;
    letter-spacing: -1px;
}
.ppg-card-v2-count-lbl {
    font-size: .75rem;
    color: #94a3b8;
    font-weight: 600;
}
.ppg-card-v2-cta {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    font-size: .875rem;
    font-weight: 700;
    padding: .65rem 1.35rem;
    border-radius: 12px;
    text-decoration: none;
    color: white;
    box-shadow: 0 4px 14px rgba(0,0,0,.2);
    transition: filter .2s ease, transform .2s ease;
    white-space: nowrap;
}
.ppg-card-v2-cta:hover { filter: brightness(1.1); transform: translateY(-1px); color: white; }
</style>

<section class="ppgs-hero">
    <div class="container text-center" style="position:relative;z-index:1;">

        <div style="display:inline-flex;align-items:center;gap:.5rem;background:rgba(124,58,237,.15);border:1px solid rgba(124,58,237,.3);border-radius:100px;padding:.375rem 1rem;font-size:.75rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#c4b5fd;margin-bottom:1.75rem;">
            <i class="fas fa-graduation-cap" style="font-size:.7rem;"></i>
            Stricto Sensu · Credenciados CAPES
        </div>

        <h1 style="font-size:clamp(1.85rem,5.5vw,4.5rem);font-weight:900;line-height:1.05;letter-spacing:-2.5px;color:#f1f5f9;margin:0 0 1rem;">
            Pós-Graduação<br>
            <span style="background:linear-gradient(135deg,#a78bfa 0%,#34d399 55%,#60a5fa 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">da UMC</span>
        </h1>

        <p style="font-size:1.05rem;color:rgba(241,245,249,.5);max-width:480px;margin:0 auto;line-height:1.6;">
            Conheça os programas Stricto Sensu da Universidade de Mogi das Cruzes e explore suas produções científicas indexadas.
        </p>

        <div class="ppgs-hero-stats">
            <div class="ppgs-hero-stat">
                <div class="ppgs-hero-stat-num"><?= $total_ppgs ?></div>
                <div class="ppgs-hero-stat-label">Programas</div>
            </div>
            <div class="ppgs-hero-stat">
                <div class="ppgs-hero-stat-num"><?= number_format($total_producoes_geral, 0, ',', '.') ?></div>
                <div class="ppgs-hero-stat-label">Produções indexadas</div>
            </div>
        </div>

    </div>
</section>
<!-- ══ /Hero PPGs ══ -->

<!-- ══ Cards de PPGs ══ -->
<section class="ppgs-section">
    <div class="container">
        <div class="ppgs-section-header">
            <div>
                <span style="font-size:.72rem;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:#7c3aed;">Programas disponíveis</span>
                <h2 style="font-size:clamp(1.4rem,2.5vw,1.875rem);font-weight:900;color:#0f172a;margin:.3rem 0 .4rem;line-height:1.1;">Explore cada programa</h2>
                <p style="color:#64748b;margin:0;font-size:.9rem;">Selecione um programa para ver suas produções e pesquisadores</p>
            </div>
        </div>

        <div class="row g-4">
            <?php foreach ($ppgs_umc as $i => $ppg):
                $nome      = $ppg['nome'];
                $nivel     = $ppg['nivel']   ?? '';
                $campus    = $ppg['campus']  ?? '';
                $areas     = $ppg['areas_concentracao'] ?? [];
                $codigo    = $ppg['codigo_capes'] ?? '';
                $producoes = $ppg_stats[$nome] ?? 0;
                $c = $ppg_palette[$nome] ?? $ppg_palette['Engenharia Biomédica'];
            ?>
            <div class="col-12 col-lg-6 fade-in-up" style="animation-delay:<?= $i * 0.1 ?>s">
                <div class="ppg-card-v2">

                    <div class="ppg-card-v2-banner"
                         style="background:linear-gradient(90deg,<?= $c['from'] ?>,<?= $c['to'] ?>);"></div>

                    <div class="ppg-card-v2-body">
                        <div class="ppg-card-v2-icon-wrap"
                             style="background:<?= $c['light'] ?>;">
                            <i class="fas fa-<?= $c['icon'] ?>"
                               style="color:<?= $c['from'] ?>;font-size:1.5rem;" aria-hidden="true"></i>
                        </div>

                        <?php if ($nivel): ?>
                        <div class="ppg-card-v2-nivel" style="color:<?= $c['from'] ?>;">
                            <?= htmlspecialchars($nivel) ?>
                        </div>
                        <?php endif; ?>

                        <h3 class="ppg-card-v2-name"><?= htmlspecialchars($nome) ?></h3>

                        <?php if ($campus): ?>
                        <div class="ppg-card-v2-campus">
                            <i class="fas fa-map-marker-alt" style="color:<?= $c['from'] ?>;font-size:.75rem;" aria-hidden="true"></i>
                            <?= htmlspecialchars($campus) ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($areas)): ?>
                        <div class="ppg-card-v2-areas-label">Áreas de Concentração</div>
                        <div class="ppg-card-v2-pills">
                            <?php foreach ($areas as $area): ?>
                            <span class="ppg-card-v2-pill"
                                  style="background:<?= $c['light'] ?>;color:<?= $c['text'] ?>;">
                                <?= htmlspecialchars($area) ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="ppg-card-v2-footer">
                        <div class="ppg-card-v2-count">
                            <span class="ppg-card-v2-count-num" style="color:<?= $c['from'] ?>;">
                                <?= number_format($producoes, 0, ',', '.') ?>
                            </span>
                            <span class="ppg-card-v2-count-lbl">produções</span>
                        </div>
                        <a href="/ppg.php?ppg=<?= urlencode($nome) ?>"
                           class="ppg-card-v2-cta"
                           style="background:linear-gradient(135deg,<?= $c['from'] ?>,<?= $c['to'] ?>);">
                            Ver programa <i class="fas fa-arrow-right" aria-hidden="true"></i>
                        </a>
                    </div>

                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<!-- ══ /Cards PPGs ══ -->

<?php Footer::display(); ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
