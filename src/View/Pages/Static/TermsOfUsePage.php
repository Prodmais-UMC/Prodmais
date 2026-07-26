<?php
/**
 * PRODMAIS UMC - Termos de Uso
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
    <meta name="description" content="Termos de Uso do Prodmais UMC — condições para utilização do sistema acadêmico da UMC.">
    <link rel="icon" type="image/png" href="/img/umc-favicon.png">
    <title>Termos de Uso — Prodmais UMC</title>
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
            border: 1px solid var(--gray-200, #e2e8f0);
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
            color: #10b981;
            background: rgba(16,185,129,.05);
            padding-left: 1rem;
        }
        .toc-nav a.toc-active {
            color: #10b981;
            background: rgba(16,185,129,.08);
            border-left-color: #10b981;
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
            background: linear-gradient(135deg, #10b981, #059669);
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
        .content-card p { color: var(--gray-700, #334155); line-height: 1.75; margin-bottom: .875rem; }
        .content-card ul, .content-card ol {
            color: var(--gray-700, #334155);
            padding-left: 1.5rem;
            margin-bottom: 1.25rem;
        }
        .content-card li { margin-bottom: .5rem; line-height: 1.65; }

        /* ── Highlight Box ── */
        .highlight-box {
            background: linear-gradient(135deg, rgba(16,185,129,.04), rgba(5,150,105,.06));
            border: 1px solid rgba(16,185,129,.16);
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            margin: 1.5rem 0 .5rem;
        }
        .highlight-box p { margin-bottom: .5rem; color: var(--gray-700, #334155); }
        .highlight-box p:last-child { margin-bottom: 0; }
        .highlight-box strong { color: #065f46; }

        /* ── Warning Box ── */
        .warning-box {
            background: linear-gradient(135deg, rgba(245,158,11,.04), rgba(239,68,68,.04));
            border: 1px solid rgba(245,158,11,.2);
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            margin: 1.25rem 0 .5rem;
        }
        .warning-box p { margin-bottom: .5rem; color: var(--gray-700, #334155); }
        .warning-box p:last-child { margin-bottom: 0; }
        .warning-box strong { color: #92400e; }
        .warning-box ul { margin-bottom: 0; color: var(--gray-700, #334155); padding-left: 1.5rem; }
        .warning-box li { margin-bottom: .375rem; }

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
        .update-badge i { color: #10b981; }

        /* ── Acceptance banner ── */
        .acceptance-banner {
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 16px;
            padding: 2rem;
            text-align: center;
            margin-bottom: 1.75rem;
            color: white;
        }
        .acceptance-banner p { color: rgba(255,255,255,.92); margin-bottom: .75rem; }
        .acceptance-banner p:last-child { margin-bottom: 0; }
        .acceptance-banner .version-note { font-size: .875rem; opacity: .8; }

        /* ── CTA buttons ── */
        .btn-accept {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            background: white;
            color: #059669;
            padding: .875rem 2rem;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            text-decoration: none;
            box-shadow: 0 4px 14px rgba(0,0,0,.15);
            transition: all .3s ease;
        }
        .btn-accept:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,.2); color: #047857; }
        .btn-policy {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            background: rgba(255,255,255,.15);
            color: white;
            border: 1.5px solid rgba(255,255,255,.4);
            padding: .875rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            text-decoration: none;
            transition: all .3s ease;
        }
        .btn-policy:hover { background: rgba(255,255,255,.25); color: white; border-color: white; }

        /* ── Inline link ── */
        .policy-link { color: #10b981; font-weight: 600; text-decoration: none; }
        .policy-link:hover { color: #059669; text-decoration: underline; }

        @media (max-width: 991.98px) {
            .toc-card { position: static; margin-bottom: 1.5rem; }
        }
    </style>
</head>
<body>

<?php Navbar::display(['active_page' => '']); ?>

<?php HeroSection::display([
    'title'      => 'Termos de Uso',
    'subtitle'   => 'Condições para utilização do sistema Prodmais UMC',
    'badge'      => 'Termos e Condições',
    'badge_icon' => 'file-contract',
    'variant'    => 'success',
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
                            <a href="#aceitacao"><span class="toc-icon"><i class="fas fa-handshake"></i></span>Aceitação</a>
                            <a href="#servicos"><span class="toc-icon"><i class="fas fa-info-circle"></i></span>Sobre o Sistema</a>
                            <a href="#usuarios"><span class="toc-icon"><i class="fas fa-user-circle"></i></span>Tipos de Usuários</a>
                            <a href="#direitos-uso"><span class="toc-icon"><i class="fas fa-gavel"></i></span>Direitos de Uso</a>
                            <a href="#responsabilidades"><span class="toc-icon"><i class="fas fa-shield-alt"></i></span>Responsabilidades</a>
                            <a href="#responsabilidades-umc"><span class="toc-icon"><i class="fas fa-university"></i></span>Responsab. UMC</a>
                            <a href="#propriedade"><span class="toc-icon"><i class="fas fa-copyright"></i></span>Prop. Intelectual</a>
                            <a href="#proibicoes"><span class="toc-icon"><i class="fas fa-ban"></i></span>Proibições</a>
                            <a href="#seguranca"><span class="toc-icon"><i class="fas fa-lock"></i></span>Segurança</a>
                            <a href="#links"><span class="toc-icon"><i class="fas fa-link"></i></span>Links Externos</a>
                            <a href="#modificacoes"><span class="toc-icon"><i class="fas fa-sync-alt"></i></span>Modificações</a>
                            <a href="#suspensao"><span class="toc-icon"><i class="fas fa-times-circle"></i></span>Suspensão</a>
                            <a href="#legislacao"><span class="toc-icon"><i class="fas fa-balance-scale"></i></span>Legislação</a>
                            <a href="#contato"><span class="toc-icon"><i class="fas fa-phone"></i></span>Contato</a>
                            <a href="#disposicoes"><span class="toc-icon"><i class="fas fa-check-double"></i></span>Disposições Finais</a>
                        </nav>
                    </div>
                </details>

                <!-- Desktop sticky -->
                <div class="toc-card d-none d-lg-block">
                    <p class="toc-title"><i class="fas fa-list me-1"></i> Neste documento</p>
                    <nav class="toc-nav" id="tocNavDesktop" aria-label="Índice do documento">
                        <a href="#aceitacao"><span class="toc-icon"><i class="fas fa-handshake"></i></span>Aceitação</a>
                        <a href="#servicos"><span class="toc-icon"><i class="fas fa-info-circle"></i></span>Sobre o Sistema</a>
                        <a href="#usuarios"><span class="toc-icon"><i class="fas fa-user-circle"></i></span>Tipos de Usuários</a>
                        <a href="#direitos-uso"><span class="toc-icon"><i class="fas fa-gavel"></i></span>Direitos de Uso</a>
                        <a href="#responsabilidades"><span class="toc-icon"><i class="fas fa-shield-alt"></i></span>Responsabilidades</a>
                        <a href="#responsabilidades-umc"><span class="toc-icon"><i class="fas fa-university"></i></span>Responsab. UMC</a>
                        <a href="#propriedade"><span class="toc-icon"><i class="fas fa-copyright"></i></span>Prop. Intelectual</a>
                        <a href="#proibicoes"><span class="toc-icon"><i class="fas fa-ban"></i></span>Proibições</a>
                        <a href="#seguranca"><span class="toc-icon"><i class="fas fa-lock"></i></span>Segurança</a>
                        <a href="#links"><span class="toc-icon"><i class="fas fa-link"></i></span>Links Externos</a>
                        <a href="#modificacoes"><span class="toc-icon"><i class="fas fa-sync-alt"></i></span>Modificações</a>
                        <a href="#suspensao"><span class="toc-icon"><i class="fas fa-times-circle"></i></span>Suspensão</a>
                        <a href="#legislacao"><span class="toc-icon"><i class="fas fa-balance-scale"></i></span>Legislação</a>
                        <a href="#contato"><span class="toc-icon"><i class="fas fa-phone"></i></span>Contato</a>
                        <a href="#disposicoes"><span class="toc-icon"><i class="fas fa-check-double"></i></span>Disposições Finais</a>
                    </nav>
                </div>
            </div>

            <!-- Content Area -->
            <div class="col-lg-9">

                <div class="update-badge">
                    <i class="fas fa-calendar-check"></i>
                    <strong>Última atualização:</strong>&nbsp;<?php echo date('d/m/Y'); ?>
                </div>

                <!-- 1. Aceitação -->
                <section id="aceitacao" class="content-card fade-in-up">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-handshake"></i></div>
                        <h2>Aceitação dos Termos</h2>
                    </div>
                    <p>
                        Bem-vindo ao <strong>Prodmais UMC</strong>. Ao acessar e utilizar este sistema, você concorda em
                        cumprir e estar vinculado aos seguintes Termos de Uso. Se você não concorda com qualquer parte
                        destes termos, por favor, não utilize o sistema.
                    </p>
                    <div class="highlight-box">
                        <p><strong><i class="fas fa-check-circle me-2"></i>Aceite implícito:</strong>
                        O uso continuado do sistema constitui aceitação destes termos e de todas as suas atualizações.</p>
                    </div>
                </section>

                <!-- 2. Sobre o Sistema -->
                <section id="servicos" class="content-card fade-in-up" style="animation-delay:.05s">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-info-circle"></i></div>
                        <h2>Sobre o Sistema</h2>
                    </div>
                    <p>
                        O <strong>Prodmais UMC</strong> é um sistema de gestão e visualização da produção científica dos
                        Programas de Pós-Graduação da Universidade de Mogi das Cruzes (UMC). O sistema tem como objetivos:
                    </p>
                    <ul>
                        <li>Organizar e indexar produções científicas dos pesquisadores vinculados aos PPGs da UMC</li>
                        <li>Facilitar a busca e consulta de publicações acadêmicas</li>
                        <li>Gerar estatísticas e indicadores para gestão dos programas</li>
                        <li>Promover a visibilidade da pesquisa desenvolvida na instituição</li>
                        <li>Auxiliar na prestação de contas aos órgãos de fomento (CAPES, CNPq, etc.)</li>
                    </ul>
                </section>

                <!-- 3. Tipos de Usuários -->
                <section id="usuarios" class="content-card fade-in-up" style="animation-delay:.1s">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-user-circle"></i></div>
                        <h2>Tipos de Usuários</h2>
                    </div>
                    <h3>1. Usuário Público (Visitante)</h3>
                    <p>Qualquer pessoa pode acessar livremente:</p>
                    <ul>
                        <li>Consulta de produções científicas</li>
                        <li>Visualização de perfis de pesquisadores</li>
                        <li>Informações sobre os Programas de Pós-Graduação</li>
                        <li>Estatísticas e indicadores públicos</li>
                    </ul>
                    <h3>2. Pesquisador</h3>
                    <p>Pesquisadores vinculados aos PPGs da UMC:</p>
                    <ul>
                        <li>Têm seus dados importados da Plataforma Lattes</li>
                        <li>Suas produções são indexadas e disponibilizadas publicamente</li>
                        <li>Podem solicitar correções ou atualizações através dos canais oficiais</li>
                    </ul>
                    <h3>3. Administrador</h3>
                    <p>Gestores dos PPGs e da instituição:</p>
                    <ul>
                        <li>Acesso autenticado ao painel administrativo</li>
                        <li>Importação e gerenciamento de currículos Lattes</li>
                        <li>Configuração do sistema e geração de relatórios completos</li>
                    </ul>
                </section>

                <!-- 4. Direitos de Uso -->
                <section id="direitos-uso" class="content-card fade-in-up" style="animation-delay:.15s">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-gavel"></i></div>
                        <h2>Direitos de Uso</h2>
                    </div>
                    <h3>Você PODE:</h3>
                    <ul>
                        <li><strong>Consultar:</strong> Buscar e visualizar produções científicas publicamente disponíveis</li>
                        <li><strong>Citar:</strong> Referenciar as produções encontradas em trabalhos acadêmicos, seguindo normas de citação</li>
                        <li><strong>Compartilhar:</strong> Compartilhar links para produções específicas</li>
                        <li><strong>Exportar:</strong> Exportar referências bibliográficas para uso acadêmico</li>
                    </ul>
                    <h3>Você NÃO PODE:</h3>
                    <div class="warning-box">
                        <ul>
                            <li><strong>Copiar em massa:</strong> Realizar scraping, extração automatizada ou download em massa de dados</li>
                            <li><strong>Uso comercial:</strong> Utilizar os dados para fins comerciais sem autorização prévia</li>
                            <li><strong>Modificar:</strong> Alterar, distorcer ou falsificar informações do sistema</li>
                            <li><strong>Sobrecarregar:</strong> Realizar ataques DDoS, flooding ou qualquer ação que prejudique o funcionamento</li>
                            <li><strong>Acessar indevidamente:</strong> Tentar acessar áreas restritas sem autorização</li>
                            <li><strong>Revender:</strong> Comercializar dados ou acesso ao sistema</li>
                        </ul>
                    </div>
                </section>

                <!-- 5. Responsabilidades do Usuário -->
                <section id="responsabilidades" class="content-card fade-in-up" style="animation-delay:.2s">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-shield-alt"></i></div>
                        <h2>Responsabilidades do Usuário</h2>
                    </div>
                    <h3>Como Usuário Público, você deve:</h3>
                    <ol>
                        <li>Utilizar o sistema de forma ética e legal</li>
                        <li>Respeitar os direitos autorais das produções científicas</li>
                        <li>Não tentar comprometer a segurança do sistema</li>
                        <li>Reportar bugs ou vulnerabilidades de forma responsável</li>
                    </ol>
                    <h3>Como Administrador, você deve:</h3>
                    <ol>
                        <li>Manter suas credenciais de acesso em sigilo</li>
                        <li>Não compartilhar sua senha com terceiros</li>
                        <li>Utilizar o sistema apenas para fins institucionais</li>
                        <li>Respeitar a privacidade e os dados dos pesquisadores</li>
                        <li>Realizar backups regulares dos dados</li>
                        <li>Reportar incidentes de segurança imediatamente</li>
                    </ol>
                </section>

                <!-- 6. Responsabilidades da UMC -->
                <section id="responsabilidades-umc" class="content-card fade-in-up" style="animation-delay:.25s">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-university"></i></div>
                        <h2>Responsabilidades da UMC</h2>
                    </div>
                    <p>A Universidade de Mogi das Cruzes se compromete a:</p>
                    <ul>
                        <li><strong>Disponibilidade:</strong> Manter o sistema disponível e funcional (não garantimos 100% de uptime)</li>
                        <li><strong>Segurança:</strong> Implementar medidas de segurança adequadas para proteção dos dados</li>
                        <li><strong>Atualização:</strong> Manter os dados atualizados conforme fontes públicas (Plataforma Lattes)</li>
                        <li><strong>Privacidade:</strong> Respeitar a privacidade dos usuários conforme LGPD</li>
                        <li><strong>Suporte:</strong> Oferecer canais de suporte para dúvidas e problemas</li>
                    </ul>
                    <div class="warning-box">
                        <p><strong><i class="fas fa-exclamation-triangle me-2"></i>Isenção de Responsabilidade:</strong></p>
                        <ul>
                            <li>O sistema é fornecido "no estado em que se encontra"</li>
                            <li>Não garantimos que o sistema estará livre de erros ou interrupções</li>
                            <li>Não nos responsabilizamos por perdas decorrentes de falhas técnicas</li>
                            <li>Os dados são importados de fontes públicas e podem conter imprecisões</li>
                        </ul>
                    </div>
                </section>

                <!-- 7. Propriedade Intelectual -->
                <section id="propriedade" class="content-card fade-in-up" style="animation-delay:.3s">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-copyright"></i></div>
                        <h2>Propriedade Intelectual</h2>
                    </div>
                    <h3>Código-fonte e Sistema</h3>
                    <p>
                        O código-fonte do sistema, design, interface e funcionalidades são de propriedade da
                        Universidade de Mogi das Cruzes e estão protegidos por direitos autorais.
                    </p>
                    <h3>Produções Científicas</h3>
                    <p>
                        Os direitos autorais das produções científicas pertencem aos seus respectivos autores e/ou
                        editoras. O sistema apenas indexa e disponibiliza metadados públicos.
                    </p>
                    <h3>Dados de Pesquisadores</h3>
                    <p>
                        Os dados são importados de fontes públicas (Plataforma Lattes, ORCID) e o tratamento é
                        realizado conforme LGPD para fins acadêmicos e institucionais.
                    </p>
                </section>

                <!-- 8. Restrições e Proibições -->
                <section id="proibicoes" class="content-card fade-in-up" style="animation-delay:.35s">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-ban"></i></div>
                        <h2>Restrições e Proibições</h2>
                    </div>
                    <p>É expressamente proibido:</p>
                    <ol>
                        <li><strong>Engenharia Reversa:</strong> Descompilar, fazer engenharia reversa ou tentar extrair o código-fonte</li>
                        <li><strong>Ataques:</strong> Realizar ataques de negação de serviço, injeção SQL, XSS ou similares</li>
                        <li><strong>Fraude:</strong> Falsificar identidade, criar contas falsas ou fornecer informações incorretas</li>
                        <li><strong>Spam:</strong> Enviar spam, malware ou conteúdo malicioso através do sistema</li>
                        <li><strong>Violação de Privacidade:</strong> Coletar dados pessoais de outros usuários sem autorização</li>
                        <li><strong>Uso Indevido:</strong> Utilizar o sistema para fins ilegais, antiéticos ou prejudiciais</li>
                    </ol>
                    <div class="warning-box">
                        <p><strong><i class="fas fa-gavel me-2"></i>Consequências:</strong> Violações destes termos podem resultar em:</p>
                        <ul>
                            <li>Bloqueio imediato do acesso</li>
                            <li>Notificação às autoridades competentes</li>
                            <li>Ações legais cabíveis</li>
                        </ul>
                    </div>
                </section>

                <!-- 9. Segurança e Privacidade -->
                <section id="seguranca" class="content-card fade-in-up" style="animation-delay:.4s">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-lock"></i></div>
                        <h2>Segurança e Privacidade</h2>
                    </div>
                    <p>
                        O tratamento de dados pessoais é regido pela nossa
                        <a href="/politica-privacidade.php" class="policy-link"><i class="fas fa-shield-alt me-1"></i>Política de Privacidade</a>,
                        em conformidade com a LGPD.
                    </p>
                    <div class="highlight-box">
                        <p><strong><i class="fas fa-shield-alt me-2"></i>Medidas de Segurança:</strong></p>
                        <ul style="margin-bottom:0; padding-left:1.25rem;">
                            <li>Senhas criptografadas com bcrypt</li>
                            <li>Comunicação via HTTPS (SSL/TLS)</li>
                            <li>Proteção contra CSRF e XSS</li>
                            <li>Logs de auditoria</li>
                            <li>Backups regulares</li>
                        </ul>
                    </div>
                </section>

                <!-- 10. Links Externos -->
                <section id="links" class="content-card fade-in-up" style="animation-delay:.45s">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-link"></i></div>
                        <h2>Links Externos</h2>
                    </div>
                    <p>
                        O sistema pode conter links para sites externos (Plataforma Lattes, ORCID, editoras, etc.).
                        Não somos responsáveis pelo conteúdo ou práticas de privacidade desses sites.
                    </p>
                </section>

                <!-- 11. Modificações -->
                <section id="modificacoes" class="content-card fade-in-up" style="animation-delay:.5s">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-sync-alt"></i></div>
                        <h2>Modificações</h2>
                    </div>
                    <p>
                        Reservamo-nos o direito de modificar estes Termos de Uso a qualquer momento. Alterações
                        significativas serão comunicadas através do site. O uso continuado após as alterações
                        constitui aceitação dos novos termos.
                    </p>
                    <p><strong>Recomendamos que você revise estes termos periodicamente.</strong></p>
                </section>

                <!-- 12. Suspensão e Término -->
                <section id="suspensao" class="content-card fade-in-up" style="animation-delay:.5s">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-times-circle"></i></div>
                        <h2>Suspensão e Término</h2>
                    </div>
                    <p>A UMC pode, a seu critério:</p>
                    <ul>
                        <li>Suspender ou encerrar o acesso de usuários que violem estes termos</li>
                        <li>Modificar ou descontinuar funcionalidades do sistema</li>
                        <li>Interromper temporariamente o sistema para manutenção</li>
                    </ul>
                    <p>
                        Usuários podem solicitar a remoção de seus dados conforme LGPD, exceto quando houver
                        obrigação legal de retenção.
                    </p>
                </section>

                <!-- 13. Lei Aplicável -->
                <section id="legislacao" class="content-card fade-in-up" style="animation-delay:.5s">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-balance-scale"></i></div>
                        <h2>Lei Aplicável</h2>
                    </div>
                    <p>
                        Estes Termos de Uso são regidos pelas leis brasileiras. Qualquer disputa será resolvida
                        no foro da Comarca de Mogi das Cruzes — SP.
                    </p>
                    <p><strong>Legislação aplicável:</strong></p>
                    <ul>
                        <li>Lei nº 13.709/2018 (LGPD)</li>
                        <li>Lei nº 12.965/2014 (Marco Civil da Internet)</li>
                        <li>Lei nº 9.610/1998 (Direitos Autorais)</li>
                        <li>Código Civil Brasileiro</li>
                        <li>Código de Defesa do Consumidor (quando aplicável)</li>
                    </ul>
                </section>

                <!-- 14. Contato -->
                <section id="contato" class="content-card fade-in-up" style="animation-delay:.5s">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-phone"></i></div>
                        <h2>Contato e Suporte</h2>
                    </div>
                    <p>Para dúvidas, sugestões ou problemas técnicos:</p>
                    <div class="highlight-box">
                        <p><strong>Suporte Técnico</strong></p>
                        <p class="mb-1"><i class="fas fa-envelope me-2"></i><strong>E-mail:</strong> prodmais@umc.br</p>
                        <p class="mb-1"><i class="fas fa-phone me-2"></i><strong>Telefone:</strong> (11) 4798-7000</p>
                        <p class="mb-1"><i class="fas fa-clock me-2"></i><strong>Horário:</strong> Segunda a sexta, 8h às 18h</p>
                        <p class="mb-0"><i class="fas fa-map-marker-alt me-2"></i><strong>Endereço:</strong> Av. Dr. Cândido Xavier de Almeida e Souza, 200 — Mogi das Cruzes — SP</p>
                    </div>
                    <p class="mt-3"><strong>Para questões sobre privacidade e dados:</strong></p>
                    <div class="highlight-box" style="margin-top:.75rem;">
                        <p class="mb-0"><i class="fas fa-envelope me-2"></i><strong>DPO (Encarregado de Dados):</strong> dpo@umc.br</p>
                    </div>
                </section>

                <!-- 15. Disposições Finais -->
                <section id="disposicoes" class="content-card fade-in-up" style="animation-delay:.5s">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-check-double"></i></div>
                        <h2>Disposições Finais</h2>
                    </div>
                    <ul>
                        <li>Se qualquer cláusula destes termos for considerada inválida, as demais permanecem em vigor</li>
                        <li>A tolerância ao descumprimento de qualquer cláusula não constitui renúncia de direitos</li>
                        <li>Estes termos constituem o acordo integral entre você e a UMC quanto ao uso do sistema</li>
                    </ul>
                </section>

                <!-- Acceptance Banner -->
                <div class="acceptance-banner fade-in-up">
                    <p style="font-size:1.1rem; font-weight:700; margin-bottom:.625rem;">
                        <i class="fas fa-check-circle me-2"></i>Ao utilizar o Prodmais UMC, você confirma que leu, compreendeu e concorda com estes Termos de Uso.
                    </p>
                    <p class="version-note"><i class="fas fa-calendar-alt me-1"></i>Versão vigente desde: <?php echo date('d/m/Y'); ?></p>
                    <div class="d-flex gap-3 justify-content-center flex-wrap mt-3">
                        <a href="/index_umc.php" class="btn-accept"><i class="fas fa-check"></i> Aceito os Termos</a>
                        <a href="/politica-privacidade.php" class="btn-policy"><i class="fas fa-shield-alt"></i> Ver Política de Privacidade</a>
                    </div>
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
            a.classList.toggle('toc-active', a.getAttribute('href') === '#' + id);
        });
    }

    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => { if (entry.isIntersecting) setActive(entry.target.id); });
    }, { rootMargin: '-20% 0px -65% 0px', threshold: 0 });

    sections.forEach(s => observer.observe(s));

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
