<?php
/**
 * PRODMAIS UMC - Página de Pesquisadores
 * Lista todos os pesquisadores cadastrados no sistema
 */

require_once __DIR__ . '/../../../../src/UmcFunctions.php';
require_once __DIR__ . '/../../../../vendor/autoload.php';

use App\View\Components\Navbar\Navbar;
use App\View\Components\HeroSection\HeroSection;
use App\View\Components\Footer\Footer;

$client = getElasticsearchClient();
$pesquisadores = [];
$total_pesquisadores = 0;

if ($client !== null) {
    try {
        $params = [
            'index' => 'prodmais_umc_cv',
            'body' => [
                'size' => 100,
                'sort' => [
                    ['nome_completo.keyword' => ['order' => 'asc']]
                ],
                'query' => [
                    'match_all' => new stdClass()
                ]
            ]
        ];
        $response = $client->search($params);
        $total_pesquisadores = $response['hits']['total']['value'];
        foreach ($response['hits']['hits'] as $hit) {
            $pesquisadores[] = $hit['_source'];
        }
    } catch (Exception $e) {
        error_log("Erro ao buscar pesquisadores: " . $e->getMessage());
    }
}

if ($client === null || empty($pesquisadores)) {
    try {
        require_once __DIR__ . '/../../../../src/Infrastructure/Database/DatabaseService.php';
        $dbService = new DatabaseService($config ?? []);
        $pesquisadores = $dbService->getPesquisadores();
        $total_pesquisadores = count($pesquisadores);
    } catch (Exception $e) {
        error_log("Erro ao buscar pesquisadores no banco relacional: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/img/umc-favicon.png">
    <title>Pesquisadores - <?php echo $branch; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- CSS Elegante Profissional -->
    <link rel="stylesheet" href="/css/prodmais-elegant.css?v=5">
    <link rel="stylesheet" href="/css/umc-theme.css">
    
</head>
<body>

<?php 
Navbar::display([
    'active_page' => 'pesquisadores',
    'mostrar_link_dashboard' => $mostrar_link_dashboard ?? true
]); 
?>
<?php renderNavbarAuthBadge(); ?>

<?php
$avatar_palettes = [
    ['bg' => '#1a56db', 'fg' => '#fff'],
    ['bg' => '#059669', 'fg' => '#fff'],
    ['bg' => '#7c3aed', 'fg' => '#fff'],
    ['bg' => '#d97706', 'fg' => '#fff'],
    ['bg' => '#0369a1', 'fg' => '#fff'],
    ['bg' => '#0d9488', 'fg' => '#fff'],
];
$ppg_chip = [
    'Biotecnologia'                   => ['bg' => '#d1fae5', 'tx' => '#065f46'],
    'Engenharia Biomédica'            => ['bg' => '#dbeafe', 'tx' => '#1e3a8a'],
    'Políticas Públicas'              => ['bg' => '#fef3c7', 'tx' => '#78350f'],
    'Ciência e Tecnologia em Saúde'   => ['bg' => '#ede9fe', 'tx' => '#4c1d95'],
];
?>
<!-- ══ Hero Pesquisadores ══ -->
<style>
.researchers-hero {
    background: #070d1f;
    background-image:
        radial-gradient(ellipse 55% 50% at 15% 65%, rgba(26,86,219,.13), transparent),
        radial-gradient(ellipse 40% 40% at 85% 15%, rgba(5,150,105,.09), transparent);
    position: relative;
    overflow: hidden;
    padding: 5rem 0 3.5rem;
}
.researchers-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image: radial-gradient(rgba(255,255,255,.05) 1px, transparent 1px);
    background-size: 28px 28px;
    pointer-events: none;
}
.researchers-section { background: #f8fafc; padding: 3.5rem 0 5rem; }
.rc-search-wrap {
    background: white;
    border: 1px solid rgba(0,0,0,.07);
    border-radius: 18px;
    padding: 1.5rem 1.75rem;
    box-shadow: 0 1px 3px rgba(0,0,0,.05), 0 4px 12px rgba(0,0,0,.04);
    margin-bottom: 2rem;
}
.rc-search-inner {
    display: flex;
    align-items: center;
    background: #f8fafc;
    border: 1.5px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    transition: border-color .2s, box-shadow .2s;
    margin-top: .75rem;
}
.rc-search-inner:focus-within { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.1); }
.rc-search-inner i { padding: 0 .875rem; color: #94a3b8; font-size: .9rem; }
.rc-search-inner input { flex:1; border:none; background:transparent; outline:none; padding:.75rem 0; font-size:.9375rem; color:#0f172a; }
.rc-search-inner input::placeholder { color:#94a3b8; }
.rc-stats { font-size:.8125rem; color:#94a3b8; margin-top:.625rem; }

/* Researcher Card */
.rc-card {
    background: white;
    border-radius: 18px;
    border: 1px solid rgba(0,0,0,.07);
    box-shadow: 0 1px 3px rgba(0,0,0,.04), 0 4px 12px rgba(0,0,0,.04);
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    height: 100%;
    transition: transform .22s ease, box-shadow .22s ease;
}
.rc-card:hover { transform: translateY(-5px); box-shadow: 0 10px 28px rgba(0,0,0,.1); }
.rc-avatar {
    width: 54px;
    height: 54px;
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    font-weight: 800;
    flex-shrink: 0;
    letter-spacing: -.5px;
    line-height: 1;
}
.rc-name {
    font-size: .9375rem;
    font-weight: 800;
    color: #0f172a;
    margin: 0;
    line-height: 1.3;
}
.rc-ppg-chip {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    font-size: .7rem;
    font-weight: 700;
    padding: .25rem .65rem;
    border-radius: 100px;
    margin-top: .375rem;
    text-transform: uppercase;
    letter-spacing: .04em;
}
.rc-divider { border: none; border-top: 1px solid #f1f5f9; margin: 1rem 0; }
.rc-meta { font-size: .775rem; color: #94a3b8; display: flex; flex-direction: column; gap: .35rem; flex:1; margin-bottom: 1rem; }
.rc-meta span { display:flex; align-items:center; gap:.4rem; }
.rc-meta i { width: 14px; font-size: .75rem; }
.rc-actions { display: flex; gap: .5rem; margin-top: auto; }
.rc-btn-primary {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .4rem;
    background: linear-gradient(135deg, #1a56db, #0369a1);
    color: white;
    border: none;
    border-radius: 10px;
    padding: .6rem .875rem;
    font-size: .8rem;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    transition: filter .2s;
}
.rc-btn-primary:hover { filter: brightness(1.1); color: white; }
.rc-btn-orcid {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #059669;
    font-size: .9rem;
    flex-shrink: 0;
    transition: background .2s;
    text-decoration: none;
}
.rc-btn-orcid:hover { background: #dcfce7; color: #059669; }
</style>

<section class="researchers-hero">
    <div class="container text-center" style="position:relative;z-index:1;">

        <div style="display:inline-flex;align-items:center;gap:.5rem;background:rgba(26,86,219,.12);border:1px solid rgba(26,86,219,.25);border-radius:100px;padding:.375rem 1rem;font-size:.75rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#93c5fd;margin-bottom:1.75rem;">
            <i class="fas fa-users" style="font-size:.7rem;"></i>
            Corpo Docente · PPG UMC
        </div>

        <h1 style="font-size:clamp(1.85rem,5.5vw,4.25rem);font-weight:900;line-height:1.05;letter-spacing:-2.5px;color:#f1f5f9;margin:0 0 1rem;">
            Pesquisadores da
            <span style="background:linear-gradient(135deg,#60a5fa,#34d399);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">UMC</span>
        </h1>

        <p style="font-size:1.05rem;color:rgba(241,245,249,.5);max-width:480px;margin:0 auto;">
            Conheça os docentes permanentes e colaboradores dos programas Stricto Sensu da Universidade de Mogi das Cruzes.
        </p>

        <div style="display:inline-flex;align-items:center;gap:.5rem;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:100px;padding:.5rem 1.25rem;margin-top:2rem;font-size:.875rem;font-weight:700;color:rgba(241,245,249,.7);">
            <i class="fas fa-user-graduate" style="color:#60a5fa;font-size:.8rem;"></i>
            <?= number_format($total_pesquisadores) ?> pesquisador<?= $total_pesquisadores != 1 ? 'es' : '' ?> cadastrado<?= $total_pesquisadores != 1 ? 's' : '' ?>
        </div>

    </div>
</section>

<!-- ══ Lista de Pesquisadores ══ -->
<section class="researchers-section">
    <div class="container">

        <!-- Barra de busca -->
        <div class="rc-search-wrap" data-autocomplete-wrap>
            <div style="font-size:.8125rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.06em;">
                <i class="fas fa-filter" style="margin-right:.4rem;"></i> Filtrar pesquisadores
            </div>
            <div class="rc-search-inner">
                <i class="fas fa-search" aria-hidden="true"></i>
                <input type="search"
                       id="searchPesquisador"
                       placeholder="Buscar por nome..."
                       onkeyup="filterPesquisadores()"
                       data-autocomplete="pesquisador"
                       aria-label="Filtrar pesquisadores por nome">
            </div>
            <div id="searchStats" class="rc-stats" role="status" aria-live="polite"></div>
        </div>

        <?php if (empty($pesquisadores)): ?>
        <div style="text-align:center;padding:4rem 2rem;background:white;border-radius:18px;border:1px solid rgba(0,0,0,.07);">
            <div style="width:64px;height:64px;border-radius:20px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;">
                <i class="fas fa-users" style="font-size:1.5rem;color:#cbd5e1;" aria-hidden="true"></i>
            </div>
            <div style="font-size:1rem;font-weight:700;color:#0f172a;margin-bottom:.375rem;">Nenhum pesquisador cadastrado</div>
            <div style="font-size:.875rem;color:#94a3b8;">Importe currículos Lattes para visualizar os pesquisadores.</div>
        </div>
        <?php else: ?>
        <div class="row g-3 g-md-4" id="pesquisadoresList">
            <?php foreach ($pesquisadores as $idx => $p):
                $nome       = $p['nome_completo'] ?? 'Nome não informado';
                $orcid      = $p['orcidID'] ?? $p['orcid'] ?? '';
                $lattes     = $p['id_lattes'] ?? '';
                $atualizado = $p['data_atualizacao_cv'] ?? '';
                $email      = $p['email'] ?? '';
                $ppg        = $p['ppg'] ?? '';
                $orcidVerificado  = !empty($p['orcid_verificado']);
                $orcidTotalObras  = (int) ($p['orcid_total_obras'] ?? 0);
                $partes     = preg_split('/\s+/', trim($nome));
                $iniciais   = strtoupper(substr($partes[0], 0, 1) . (isset($partes[1]) ? substr($partes[1], 0, 1) : ''));
                $pal        = $avatar_palettes[$idx % count($avatar_palettes)];
                $chip       = $ppg_chip[$ppg] ?? ['bg' => '#f1f5f9', 'tx' => '#475569'];
            ?>
            <div class="col-12 col-md-6 col-lg-4 fade-in-up pesquisador-card"
                 data-nome="<?= strtolower(htmlspecialchars($nome)) ?>"
                 style="animation-delay:<?= min($idx * 0.05, 0.5) ?>s;">
                <div class="rc-card">

                    <div style="display:flex;align-items:flex-start;gap:.875rem;margin-bottom:.25rem;">
                        <div class="rc-avatar" style="background:<?= $pal['bg'] ?>;color:<?= $pal['fg'] ?>;" aria-hidden="true">
                            <?= $iniciais ?>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <h3 class="rc-name"><?= htmlspecialchars($nome) ?></h3>
                            <?php if ($ppg): ?>
                            <span class="rc-ppg-chip" style="background:<?= $chip['bg'] ?>;color:<?= $chip['tx'] ?>;">
                                <i class="fas fa-graduation-cap" style="font-size:.6rem;" aria-hidden="true"></i>
                                <?= htmlspecialchars($ppg) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <hr class="rc-divider">

                    <div class="rc-meta">
                        <?php if ($lattes): ?>
                        <span><i class="fas fa-id-badge"></i> Currículo Lattes disponível</span>
                        <?php endif; ?>
                        <?php if ($atualizado): ?>
                        <span><i class="fas fa-clock"></i> Atualizado em <?= date('d/m/Y', strtotime($atualizado)) ?></span>
                        <?php endif; ?>
                        <?php if ($email): ?>
                        <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($email) ?></span>
                        <?php endif; ?>
                        <?php if ($orcidVerificado): ?>
                        <span><i class="fab fa-orcid" style="color:#a6ce39;"></i> ORCID verificado<?= $orcidTotalObras > 0 ? " · {$orcidTotalObras} obra(s)" : '' ?></span>
                        <?php endif; ?>
                        <?php if (!$lattes && !$atualizado && !$email): ?>
                        <span><i class="fas fa-university"></i> Pesquisador do PPG UMC</span>
                        <?php endif; ?>
                    </div>

                    <div class="rc-actions">
                        <a href="/result.php?pesquisador=<?= urlencode($nome) ?>" class="rc-btn-primary">
                            <i class="fas fa-search" aria-hidden="true"></i> Ver Produções
                        </a>
                        <?php if ($orcid): ?>
                        <a href="https://orcid.org/<?= htmlspecialchars($orcid) ?>"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="rc-btn-orcid"
                           aria-label="Perfil ORCID de <?= htmlspecialchars($nome) ?>">
                            <i class="fab fa-orcid" aria-hidden="true"></i>
                        </a>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>
<!-- ══ /Pesquisadores ══ -->

<?php Footer::display(); ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/js/autocomplete.js"></script>

<script>
function filterPesquisadores() {
    const searchInput = document.getElementById('searchPesquisador');
    const filter = searchInput.value.toLowerCase();
    const cards = document.getElementsByClassName('pesquisador-card');
    const searchStats = document.getElementById('searchStats');
    
    let visibleCount = 0;
    let totalCount = cards.length;
    
    for (let i = 0; i < cards.length; i++) {
        const nome = cards[i].getAttribute('data-nome');
        if (nome.indexOf(filter) > -1) {
            cards[i].style.display = '';
            visibleCount++;
        } else {
            cards[i].style.display = 'none';
        }
    }
    
    // Atualizar estatísticas de busca
    if (filter === '') {
        searchStats.innerHTML = `<i class="fas fa-info-circle me-1"></i>Mostrando todos os ${totalCount} pesquisadores`;
    } else {
        searchStats.innerHTML = `<i class="fas fa-filter me-1"></i>Encontrados <strong>${visibleCount}</strong> de ${totalCount} pesquisadores`;
    }
}

// Inicializar estatísticas
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.getElementsByClassName('pesquisador-card');
    document.getElementById('searchStats').innerHTML = `<i class="fas fa-info-circle me-1"></i>Mostrando todos os ${cards.length} pesquisadores`;
});
</script>

</body>
</html>
