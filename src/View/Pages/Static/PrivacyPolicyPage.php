<?php
/**
 * PRODMAIS UMC - Política de Privacidade
 * Conforme Lei Geral de Proteção de Dados (LGPD)
 */

require_once __DIR__ . '/../../../../src/UmcFunctions.php';

use App\View\Components\Navbar\Navbar;
use App\View\Components\HeroSection\HeroSection;
use App\View\Components\Footer\Footer;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Política de Privacidade do Prodmais UMC — transparência e proteção de dados em conformidade com a LGPD.">
    <link rel="icon" type="image/png" href="/img/umc-favicon.png">
    <title>Política de Privacidade — Prodmais UMC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/prodmais-elegant.css?v=5">
    <link rel="stylesheet" href="/css/umc-theme.css">
    <style>
        body { font-family: 'Inter', sans-serif; -webkit-font-smoothing: antialiased; }

        /* ── Sidebar TOC ── */
        .toc-card {
            background: white;
            border-radius: 16px;
            border: 1px solid var(--gray-200);
            box-shadow: 0 2px 12px rgba(0,0,0,.06);
            padding: 1.5rem;
            position: sticky;
            top: 5rem;
        }
        .toc-card .toc-title {
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--gray-400, #94a3b8);
            margin-bottom: 1rem;
        }
        .toc-nav { display: flex; flex-direction: column; gap: .125rem; }
        .toc-nav a {
            display: flex;
            align-items: center;
            gap: .5rem;
            padding: .5rem .75rem;
            border-radius: 8px;
            font-size: .875rem;
            font-weight: 500;
            color: var(--gray-600, #475569);
            text-decoration: none;
            transition: all .2s ease;
            border-left: 2px solid transparent;
        }
        .toc-nav a:hover {
            color: var(--blue-600, #1a56db);
            background: rgba(26,86,219,.05);
            padding-left: 1rem;
        }
        .toc-nav a.toc-active {
            color: var(--blue-600, #1a56db);
            background: rgba(26,86,219,.08);
            border-left-color: var(--blue-600, #1a56db);
            font-weight: 600;
        }
        .toc-nav .toc-icon {
            width: 18px;
            text-align: center;
            flex-shrink: 0;
            font-size: .8rem;
            opacity: .7;
        }

        /* ── Content Cards ── */
        .content-card {
            background: white;
            border-radius: 16px;
            border: 1px solid var(--gray-200, #e2e8f0);
            box-shadow: 0 2px 12px rgba(0,0,0,.06);
            padding: 2.5rem;
            margin-bottom: 1.75rem;
            scroll-margin-top: 5.5rem;
            height: auto;
            transform: none;
            transition: none;
        }
        .content-card:hover {
            transform: none;
            box-shadow: 0 2px 12px rgba(0,0,0,.06);
        }
        .content-card .section-header {
            display: flex;
            align-items: center;
            gap: .875rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1.25rem;
            border-bottom: 1.5px solid var(--gray-100, #f1f5f9);
        }
        .section-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, #1a56db, #0369a1);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .section-icon i { color: white; font-size: 1.1rem; }
        .content-card h2 {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--gray-900, #0f172a);
            margin: 0;
            line-height: 1.3;
        }
        .content-card h3 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--gray-800, #1e293b);
            margin: 1.5rem 0 .75rem;
        }
        .content-card p {
            color: var(--gray-700, #334155);
            line-height: 1.75;
            margin-bottom: .875rem;
        }
        .content-card ul, .content-card ol {
            color: var(--gray-700, #334155);
            padding-left: 1.5rem;
            margin-bottom: 1.25rem;
        }
        .content-card li { margin-bottom: .5rem; line-height: 1.65; }

        /* ── Highlight Box ── */
        .highlight-box {
            background: linear-gradient(135deg, rgba(26,86,219,.04), rgba(59,130,246,.06));
            border: 1px solid rgba(26,86,219,.14);
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            margin: 1.5rem 0 .5rem;
        }
        .highlight-box p { margin-bottom: .5rem; color: var(--gray-700, #334155); }
        .highlight-box p:last-child { margin-bottom: 0; }
        .highlight-box strong { color: var(--blue-700, #1a3a6b); }

        /* ── Update badge ── */
        .update-badge {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            background: var(--gray-100, #f1f5f9);
            border: 1px solid var(--gray-200, #e2e8f0);
            border-radius: 100px;
            padding: .5rem 1rem;
            font-size: .8125rem;
            color: var(--gray-500, #64748b);
            margin-bottom: 2rem;
        }
        .update-badge i { color: var(--blue-600, #1a56db); }

        /* ── Mobile accordion ── */
        @media (max-width: 991.98px) {
            .toc-card { position: static; margin-bottom: 1.5rem; }
        }
    </style>
</head>
<body>

<?php Navbar::display(['active_page' => '']); ?>

<?php HeroSection::display([
    'title'    => 'Política de Privacidade',
    'subtitle' => 'Transparência e proteção dos seus dados em conformidade com a LGPD',
    'badge'    => 'LGPD — Proteção de Dados',
    'badge_icon' => 'shield-alt',
    'variant'  => 'primary',
]); ?>

<section class="page-section page-section-gray">
    <div class="container">
        <div class="row g-4 align-items-start">

            <!-- Sidebar TOC -->
            <div class="col-lg-3" style="align-self:flex-start;">
                <!-- Mobile accordion -->
                <details class="d-lg-none mb-3">
                    <summary class="btn btn-outline-secondary w-100 text-start fw-600">
                        <i class="fas fa-list me-2"></i>Navegar neste documento
                    </summary>
                    <div class="toc-card mt-2" style="position:static;">
                        <nav class="toc-nav" id="tocNavMobile" aria-label="Índice do documento (mobile)">
                            <a href="#introducao"><span class="toc-icon"><i class="fas fa-info-circle"></i></span>Introdução</a>
                            <a href="#dados-coletados"><span class="toc-icon"><i class="fas fa-database"></i></span>Dados Coletados</a>
                            <a href="#finalidade"><span class="toc-icon"><i class="fas fa-bullseye"></i></span>Finalidade</a>
                            <a href="#compartilhamento"><span class="toc-icon"><i class="fas fa-share-alt"></i></span>Compartilhamento</a>
                            <a href="#seguranca"><span class="toc-icon"><i class="fas fa-lock"></i></span>Segurança</a>
                            <a href="#direitos"><span class="toc-icon"><i class="fas fa-user-check"></i></span>Direitos</a>
                            <a href="#retencao"><span class="toc-icon"><i class="fas fa-clock"></i></span>Retenção</a>
                            <a href="#cookies"><span class="toc-icon"><i class="fas fa-cookie-bite"></i></span>Cookies</a>
                            <a href="#menores"><span class="toc-icon"><i class="fas fa-child"></i></span>Menores</a>
                            <a href="#alteracoes"><span class="toc-icon"><i class="fas fa-edit"></i></span>Alterações</a>
                            <a href="#legislacao"><span class="toc-icon"><i class="fas fa-gavel"></i></span>Legislação</a>
                            <a href="#contato"><span class="toc-icon"><i class="fas fa-phone"></i></span>Contato</a>
                        </nav>
                    </div>
                </details>

                <!-- Desktop sticky -->
                <div class="toc-card d-none d-lg-block">
                    <p class="toc-title"><i class="fas fa-list me-1"></i> Neste documento</p>
                    <nav class="toc-nav" id="tocNavDesktop" aria-label="Índice do documento">
                        <a href="#introducao"><span class="toc-icon"><i class="fas fa-info-circle"></i></span>Introdução</a>
                        <a href="#dados-coletados"><span class="toc-icon"><i class="fas fa-database"></i></span>Dados Coletados</a>
                        <a href="#finalidade"><span class="toc-icon"><i class="fas fa-bullseye"></i></span>Finalidade</a>
                        <a href="#compartilhamento"><span class="toc-icon"><i class="fas fa-share-alt"></i></span>Compartilhamento</a>
                        <a href="#seguranca"><span class="toc-icon"><i class="fas fa-lock"></i></span>Segurança</a>
                        <a href="#direitos"><span class="toc-icon"><i class="fas fa-user-check"></i></span>Direitos</a>
                        <a href="#retencao"><span class="toc-icon"><i class="fas fa-clock"></i></span>Retenção</a>
                        <a href="#cookies"><span class="toc-icon"><i class="fas fa-cookie-bite"></i></span>Cookies</a>
                        <a href="#menores"><span class="toc-icon"><i class="fas fa-child"></i></span>Menores</a>
                        <a href="#alteracoes"><span class="toc-icon"><i class="fas fa-edit"></i></span>Alterações</a>
                        <a href="#legislacao"><span class="toc-icon"><i class="fas fa-gavel"></i></span>Legislação</a>
                        <a href="#contato"><span class="toc-icon"><i class="fas fa-phone"></i></span>Contato</a>
                    </nav>
                </div>
            </div>

            <!-- Content Area -->
            <div class="col-lg-9">

                <div class="update-badge">
                    <i class="fas fa-calendar-check"></i>
                    <strong>Última atualização:</strong>&nbsp;<?php echo date('d/m/Y'); ?>
                </div>

                <!-- 1. Introdução -->
                <section id="introducao" class="content-card fade-in-up">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-info-circle"></i></div>
                        <h2>Introdução</h2>
                    </div>
                    <p>
                        O <strong>Prodmais UMC</strong> é um sistema desenvolvido pela Universidade de Mogi das Cruzes (UMC)
                        para gestão e análise da produção científica dos seus Programas de Pós-Graduação. Este documento
                        descreve como coletamos, usamos, armazenamos e protegemos os dados pessoais dos usuários, em
                        conformidade com a <strong>Lei Geral de Proteção de Dados (Lei nº 13.709/2018 — LGPD)</strong>.
                    </p>
                    <div class="highlight-box">
                        <p><strong><i class="fas fa-check-circle me-2"></i>Compromisso com a LGPD:</strong>
                        Garantimos a proteção dos seus dados pessoais e o cumprimento de todos os princípios e direitos
                        estabelecidos pela legislação brasileira de proteção de dados.</p>
                    </div>
                </section>

                <!-- 2. Dados Coletados -->
                <section id="dados-coletados" class="content-card fade-in-up" style="animation-delay:.05s">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-database"></i></div>
                        <h2>Dados Coletados</h2>
                    </div>
                    <h3>1. Dados de Pesquisadores</h3>
                    <p>Coletamos dados disponíveis publicamente na <strong>Plataforma Lattes (CNPq)</strong> e outras fontes acadêmicas:</p>
                    <ul>
                        <li>Nome completo</li>
                        <li>ID Lattes</li>
                        <li>ORCID (quando disponível)</li>
                        <li>Vínculo institucional (PPG)</li>
                        <li>Produções científicas (artigos, livros, capítulos, etc.)</li>
                        <li>Áreas de atuação e pesquisa</li>
                        <li>Projetos de pesquisa</li>
                    </ul>
                    <h3>2. Dados de Acesso ao Sistema</h3>
                    <p>Para administradores do sistema:</p>
                    <ul>
                        <li>E-mail institucional</li>
                        <li>Senha criptografada</li>
                        <li>Logs de acesso (data, hora, IP)</li>
                        <li>Histórico de ações no sistema</li>
                    </ul>
                    <h3>3. Dados Técnicos</h3>
                    <ul>
                        <li>Endereço IP</li>
                        <li>Tipo de navegador</li>
                        <li>Sistema operacional</li>
                        <li>Cookies essenciais para funcionamento do sistema</li>
                    </ul>
                </section>

                <!-- 3. Finalidade -->
                <section id="finalidade" class="content-card fade-in-up" style="animation-delay:.1s">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-bullseye"></i></div>
                        <h2>Finalidade do Tratamento</h2>
                    </div>
                    <p>Os dados pessoais são tratados para as seguintes finalidades:</p>
                    <ol>
                        <li><strong>Gestão da Produção Científica:</strong> Organizar, indexar e disponibilizar produções científicas dos programas de pós-graduação.</li>
                        <li><strong>Análise e Relatórios:</strong> Gerar estatísticas, indicadores e relatórios para gestão institucional e prestação de contas à CAPES.</li>
                        <li><strong>Divulgação Científica:</strong> Promover a visibilidade da pesquisa desenvolvida na UMC.</li>
                        <li><strong>Conformidade Legal:</strong> Cumprir obrigações legais e regulatórias perante órgãos de fomento e avaliação.</li>
                        <li><strong>Segurança:</strong> Proteger o sistema contra acessos não autorizados e garantir a integridade dos dados.</li>
                    </ol>
                    <div class="highlight-box">
                        <p><strong><i class="fas fa-balance-scale me-2"></i>Base Legal (LGPD):</strong>
                        O tratamento de dados é realizado com base no <strong>legítimo interesse</strong> da instituição
                        (Art. 7º, IX da LGPD) e no <strong>cumprimento de obrigação legal</strong> (Art. 7º, II da LGPD),
                        especialmente perante a CAPES e órgãos de avaliação da pós-graduação.</p>
                    </div>
                </section>

                <!-- 4. Compartilhamento -->
                <section id="compartilhamento" class="content-card fade-in-up" style="animation-delay:.15s">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-share-alt"></i></div>
                        <h2>Compartilhamento de Dados</h2>
                    </div>
                    <p>Os dados podem ser compartilhados nas seguintes situações:</p>
                    <ul>
                        <li><strong>Divulgação Pública:</strong> Dados de produções científicas são disponibilizados publicamente no site, conforme já são públicos nas fontes originais (Plataforma Lattes, ORCID).</li>
                        <li><strong>CAPES e Órgãos de Fomento:</strong> Relatórios e dados agregados para avaliação dos programas de pós-graduação.</li>
                        <li><strong>Pesquisadores:</strong> Cada pesquisador tem acesso aos seus próprios dados e produções.</li>
                        <li><strong>Determinação Legal:</strong> Quando exigido por lei ou ordem judicial.</li>
                    </ul>
                    <div class="highlight-box">
                        <p><strong><i class="fas fa-ban me-2"></i>Não compartilhamos dados com terceiros para fins comerciais ou publicitários.</strong></p>
                    </div>
                </section>

                <!-- 5. Segurança -->
                <section id="seguranca" class="content-card fade-in-up" style="animation-delay:.2s">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-lock"></i></div>
                        <h2>Segurança dos Dados</h2>
                    </div>
                    <p>Implementamos medidas técnicas e organizacionais para proteger os dados pessoais:</p>
                    <ul>
                        <li><strong>Criptografia:</strong> Senhas armazenadas com hash bcrypt (irreversível).</li>
                        <li><strong>HTTPS:</strong> Comunicação criptografada via SSL/TLS.</li>
                        <li><strong>Controle de Acesso:</strong> Sistema de autenticação e autorização robusto.</li>
                        <li><strong>Backups:</strong> Backups regulares e seguros dos dados.</li>
                        <li><strong>Logs de Auditoria:</strong> Registro de todas as ações críticas no sistema.</li>
                        <li><strong>Manutenção:</strong> Atualização constante de segurança e correção de vulnerabilidades.</li>
                        <li><strong>Firewall:</strong> Proteção contra acessos não autorizados.</li>
                    </ul>
                </section>

                <!-- 6. Direitos -->
                <section id="direitos" class="content-card fade-in-up" style="animation-delay:.25s">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-user-check"></i></div>
                        <h2>Direitos dos Titulares</h2>
                    </div>
                    <p>Conforme a LGPD (Art. 18), você tem os seguintes direitos:</p>
                    <ol>
                        <li><strong>Confirmação e Acesso:</strong> Confirmar se seus dados são tratados e acessá-los.</li>
                        <li><strong>Correção:</strong> Corrigir dados incompletos, inexatos ou desatualizados.</li>
                        <li><strong>Anonimização ou Bloqueio:</strong> Solicitar anonimização, bloqueio ou eliminação de dados desnecessários.</li>
                        <li><strong>Portabilidade:</strong> Solicitar a portabilidade dos dados a outro fornecedor.</li>
                        <li><strong>Eliminação:</strong> Solicitar a eliminação de dados tratados com seu consentimento.</li>
                        <li><strong>Informação:</strong> Saber com quem compartilhamos seus dados.</li>
                        <li><strong>Revogação:</strong> Revogar consentimento quando aplicável.</li>
                        <li><strong>Oposição:</strong> Opor-se ao tratamento em determinadas situações.</li>
                    </ol>
                    <div class="highlight-box">
                        <p><strong><i class="fas fa-envelope me-2"></i>Para exercer seus direitos, entre em contato:</strong></p>
                        <p class="mb-1"><strong>E-mail:</strong> dpo@umc.br ou prodmais@umc.br</p>
                        <p class="mb-0"><strong>Endereço:</strong> Universidade de Mogi das Cruzes — Av. Dr. Cândido Xavier de Almeida e Souza, 200 — Mogi das Cruzes — SP</p>
                    </div>
                </section>

                <!-- 7. Retenção -->
                <section id="retencao" class="content-card fade-in-up" style="animation-delay:.3s">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-clock"></i></div>
                        <h2>Retenção de Dados</h2>
                    </div>
                    <p>Os dados são retidos pelo período necessário para:</p>
                    <ul>
                        <li><strong>Produções Científicas:</strong> Mantidos permanentemente para fins de registro histórico e memória acadêmica.</li>
                        <li><strong>Dados de Acesso:</strong> Logs mantidos por 6 meses, conforme Marco Civil da Internet.</li>
                        <li><strong>Dados Administrativos:</strong> Mantidos enquanto necessário para gestão dos programas e prestação de contas.</li>
                    </ul>
                    <p>Após o período de retenção, os dados são anonimizados ou eliminados de forma segura.</p>
                </section>

                <!-- 8. Cookies -->
                <section id="cookies" class="content-card fade-in-up" style="animation-delay:.35s">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-cookie-bite"></i></div>
                        <h2>Cookies</h2>
                    </div>
                    <p>Utilizamos apenas cookies essenciais para o funcionamento do sistema:</p>
                    <ul>
                        <li><strong>Sessão:</strong> Mantém você autenticado durante a navegação.</li>
                        <li><strong>Segurança:</strong> CSRF tokens para proteção contra ataques.</li>
                    </ul>
                    <div class="highlight-box">
                        <p><strong><i class="fas fa-ban me-2"></i>Não utilizamos cookies de rastreamento, publicidade ou análise de comportamento.</strong></p>
                    </div>
                </section>

                <!-- 9. Menores -->
                <section id="menores" class="content-card fade-in-up" style="animation-delay:.4s">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-child"></i></div>
                        <h2>Dados de Menores</h2>
                    </div>
                    <p>
                        O sistema não coleta intencionalmente dados de menores de 18 anos. Caso identifique que dados de
                        menores foram coletados inadvertidamente, entre em contato para que possamos removê-los imediatamente.
                    </p>
                </section>

                <!-- 10. Alterações -->
                <section id="alteracoes" class="content-card fade-in-up" style="animation-delay:.45s">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-edit"></i></div>
                        <h2>Alterações nesta Política</h2>
                    </div>
                    <p>
                        Esta Política de Privacidade pode ser atualizada periodicamente. Alterações significativas serão
                        comunicadas através do site. A data da última atualização está sempre indicada no topo do documento.
                    </p>
                    <p>
                        Recomendamos que você revise esta política regularmente para se manter informado sobre como
                        protegemos seus dados.
                    </p>
                </section>

                <!-- 11. Legislação -->
                <section id="legislacao" class="content-card fade-in-up" style="animation-delay:.5s">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-gavel"></i></div>
                        <h2>Legislação Aplicável</h2>
                    </div>
                    <p>Esta Política de Privacidade é regida pelas seguintes legislações brasileiras:</p>
                    <ul>
                        <li><strong>Lei nº 13.709/2018</strong> — Lei Geral de Proteção de Dados (LGPD)</li>
                        <li><strong>Lei nº 12.965/2014</strong> — Marco Civil da Internet</li>
                        <li><strong>Decreto nº 8.771/2016</strong> — Regulamentação do Marco Civil</li>
                        <li><strong>Constituição Federal</strong> — Art. 5º, X e XII (privacidade e sigilo)</li>
                    </ul>
                </section>

                <!-- 12. Contato -->
                <section id="contato" class="content-card fade-in-up" style="animation-delay:.5s">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-phone"></i></div>
                        <h2>Contato</h2>
                    </div>
                    <p>Para dúvidas, solicitações ou reclamações sobre privacidade e proteção de dados:</p>
                    <div class="highlight-box">
                        <p><strong>Encarregado de Proteção de Dados (DPO)</strong></p>
                        <p class="mb-1"><i class="fas fa-envelope me-2"></i><strong>E-mail:</strong> dpo@umc.br</p>
                        <p class="mb-1"><i class="fas fa-envelope me-2"></i><strong>E-mail do Sistema:</strong> prodmais@umc.br</p>
                        <p class="mb-1"><i class="fas fa-phone me-2"></i><strong>Telefone:</strong> (11) 4798-7000</p>
                        <p class="mb-0"><i class="fas fa-map-marker-alt me-2"></i><strong>Endereço:</strong> Av. Dr. Cândido Xavier de Almeida e Souza, 200 — Centro Cívico — Mogi das Cruzes — SP — CEP 08780-911</p>
                    </div>
                </section>

                <!-- CTA de volta -->
                <div class="text-center pt-2 pb-4">
                    <a href="/index_umc.php" class="btn-primary-ds d-inline-flex align-items-center gap-2" style="padding:.875rem 2rem; border-radius:12px; text-decoration:none; font-size:1rem;">
                        <i class="fas fa-home"></i> Voltar para o Início
                    </a>
                </div>

            </div><!-- /col-lg-9 -->
        </div><!-- /row -->
    </div><!-- /container -->
</section>

<?php Footer::display(); ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const sections = document.querySelectorAll('.content-card[id]');
    const navLinks = document.querySelectorAll('.toc-nav a');

    function setActive(id) {
        navLinks.forEach(a => {
            const href = a.getAttribute('href');
            a.classList.toggle('toc-active', href === '#' + id);
        });
    }

    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) setActive(entry.target.id);
        });
    }, { rootMargin: '-20% 0px -65% 0px', threshold: 0 });

    sections.forEach(s => observer.observe(s));

    // Smooth scroll
    navLinks.forEach(a => {
        a.addEventListener('click', e => {
            e.preventDefault();
            const target = document.querySelector(a.getAttribute('href'));
            if (target) target.scrollIntoView({ behavior: 'smooth' });
        });
    });
})();
</script>
</body>
</html>
