<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../config/config_umc.php';
require_once __DIR__ . '/../../../../src/UmcFunctions.php';
require_once __DIR__ . '/../../../../vendor/autoload.php';

use App\View\Components\Navbar\Navbar;
use App\View\Components\Footer\Footer;

if (!class_exists('LogService')) {
    require_once __DIR__ . '/../../../../src/Domain/Services/LogService.php';
}

if (!class_exists('\ProdmaisUMC\LattesImporter')) {
    require_once __DIR__ . '/../../../../src/Domain/Importers/LattesImporter.php';
}

if (!class_exists('LgpdComplianceService')) {
    require_once __DIR__ . '/../../../../src/Domain/Security/LgpdComplianceService.php';
}

require_once __DIR__ . '/../../../../src/Infrastructure/Database/MysqlConnectionFactory.php';

$config_legacy = [];
if (file_exists(__DIR__ . '/../../../../config/config.php')) {
    $config_legacy = require_once __DIR__ . '/../../../../config/config.php';
    if (!is_array($config_legacy)) {
        $config_legacy = [];
    }
}

// Aceita user_id (canônico) ou user (legado) como prova de autenticação
if (empty($_SESSION['user_id']) && empty($_SESSION['user'])) {
    header('Location: /login.php');
    exit;
}
// Painel administrativo é restrito a admin e pesquisador — visualizador não tem permissão
if (!in_array(papelEfetivo(), ['admin', 'pesquisador'], true)) {
    header('Location: /dashboard.php');
    exit;
}
// Compatibilidade: garante que $_SESSION['user'] existe para código legado abaixo
if (empty($_SESSION['user']) && !empty($_SESSION['username'])) {
    $_SESSION['user'] = $_SESSION['username'];
}

$log = new LogService($config_legacy);
$log->log($_SESSION['user'], 'Acesso à área administrativa');

$lgpdService = new LgpdComplianceService($config_legacy);
$dpiaReport = $lgpdService->generateDpiaReport();
$complianceStatus = $lgpdService->getComplianceStatus();

if (isset($_POST['expunge'])) {
    $removidos = $log->expungeOld(365);
    $msg = $removidos > 0
        ? "{$removidos} log(s) com mais de 365 dias removido(s)."
        : 'Nenhum log com mais de 365 dias — nada a remover.';
}

if (isset($_POST['expunge_all'])) {
    $removidos = $log->expungeAll();
    $msg = "{$removidos} log(s) removido(s). Histórico zerado.";
}

// ── Aprovação de cadastros pendentes (somente admin) ──
$souAdmin = papelEfetivo() === 'admin';
$pendingMsg = null;

if ($souAdmin && (isset($_POST['approve_user']) || isset($_POST['reject_user']))) {
    $pendingId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    if ($pendingId) {
        try {
            $pdoUsers = criarConexaoMysql();
            $stmt = $pdoUsers->prepare("SELECT username, email FROM usuarios_admin WHERE id = ? AND status = 'pendente'");
            $stmt->execute([$pendingId]);
            $alvo = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($alvo) {
                if (isset($_POST['approve_user'])) {
                    $stmt = $pdoUsers->prepare("UPDATE usuarios_admin SET status = 'ativo' WHERE id = ?");
                    $stmt->execute([$pendingId]);
                    $log->log($_SESSION['user'], "Aprovou cadastro de {$alvo['username']} ({$alvo['email']})");
                    $pendingMsg = "Cadastro de {$alvo['username']} aprovado — já pode fazer login.";
                } else {
                    $stmt = $pdoUsers->prepare("DELETE FROM usuarios_admin WHERE id = ?");
                    $stmt->execute([$pendingId]);
                    $log->log($_SESSION['user'], "Rejeitou cadastro de {$alvo['username']} ({$alvo['email']})");
                    $pendingMsg = "Solicitação de {$alvo['username']} rejeitada e removida.";
                }
            } else {
                $pendingMsg = 'Solicitação não encontrada (talvez já tenha sido tratada).';
            }
        } catch (\Exception $e) {
            error_log('Erro ao aprovar/rejeitar cadastro: ' . $e->getMessage());
            $pendingMsg = 'Erro ao processar a solicitação. Tente novamente.';
        }
    }
}

$pendingUsers = [];
if ($souAdmin) {
    try {
        $pdoUsers = $pdoUsers ?? criarConexaoMysql();
        $stmt = $pdoUsers->query(
            "SELECT id, username, email, nome_completo, papel, criado_em
             FROM usuarios_admin WHERE status = 'pendente' ORDER BY criado_em ASC"
        );
        $pendingUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Exception $e) {
        error_log('Erro ao listar cadastros pendentes: ' . $e->getMessage());
    }
}
$pendingCount = count($pendingUsers);

// ── Gerenciamento de todos os usuários (somente admin) ──
$manageMsg = null;
$meuId = (int) ($_SESSION['user_id'] ?? 0);

if ($souAdmin && (isset($_POST['update_role']) || isset($_POST['delete_account']))) {
    $alvoId = filter_input(INPUT_POST, 'account_id', FILTER_VALIDATE_INT);

    if ($alvoId === $meuId) {
        $manageMsg = 'Você não pode alterar o papel nem excluir a própria conta por aqui.';
    } elseif ($alvoId) {
        try {
            $pdoUsers = $pdoUsers ?? criarConexaoMysql();
            $stmt = $pdoUsers->prepare("SELECT username, email, papel, conta_sistema FROM usuarios_admin WHERE id = ?");
            $stmt->execute([$alvoId]);
            $alvo = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$alvo) {
                $manageMsg = 'Usuário não encontrado (talvez já tenha sido removido).';
            } elseif (!empty($alvo['conta_sistema'])) {
                $manageMsg = 'Esta é uma conta protegida do sistema e não pode ser alterada nem excluída por aqui.';
            } elseif (isset($_POST['update_role'])) {
                $novoPapel = filter_input(INPUT_POST, 'novo_papel', FILTER_SANITIZE_SPECIAL_CHARS);
                if (in_array($novoPapel, ['admin', 'pesquisador', 'visualizador'], true)) {
                    $stmt = $pdoUsers->prepare("UPDATE usuarios_admin SET papel = ? WHERE id = ?");
                    $stmt->execute([$novoPapel, $alvoId]);
                    $log->log($_SESSION['user'], "Alterou papel de {$alvo['username']}: {$alvo['papel']} -> {$novoPapel}");
                    $manageMsg = "Papel de {$alvo['username']} alterado para {$novoPapel}.";
                } else {
                    $manageMsg = 'Papel inválido.';
                }
            } else {
                $stmt = $pdoUsers->prepare("DELETE FROM usuarios_admin WHERE id = ?");
                $stmt->execute([$alvoId]);
                $log->log($_SESSION['user'], "Excluiu a conta de {$alvo['username']} ({$alvo['email']})");
                $manageMsg = "Conta de {$alvo['username']} excluída.";
            }
        } catch (\Exception $e) {
            error_log('Erro ao gerenciar usuário: ' . $e->getMessage());
            $manageMsg = 'Erro ao processar a solicitação. Tente novamente.';
        }
    }
}

$allUsers = [];
if ($souAdmin) {
    try {
        $pdoUsers = $pdoUsers ?? criarConexaoMysql();
        $stmt = $pdoUsers->query(
            "SELECT id, username, email, nome_completo, papel, status, criado_em, ultimo_login, conta_sistema
             FROM usuarios_admin ORDER BY nome_completo ASC"
        );
        $allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Exception $e) {
        error_log('Erro ao listar usuários: ' . $e->getMessage());
    }
}

$import_result = null;

// Processar upload de pesquisador específico - VERSÃO MELHORADA COM PPGs
if (isset($_POST['upload_researcher']) && isset($_FILES['lattes_xml'])) {
    try {
        $upload = $_FILES['lattes_xml'];

        // Validações
        if ($upload['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Erro no upload do arquivo");
        }

        if ($upload['size'] > 50 * 1024 * 1024) { // Max 50MB
            throw new Exception("Arquivo muito grande. Máximo: 50MB");
        }

        $ext = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
        if ($ext !== 'xml') {
            throw new Exception("Apenas arquivos XML são permitidos");
        }

        // Salvar arquivo temporariamente
        $upload_dir = __DIR__ . '/../data/lattes_xml/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $filename = 'lattes_' . date('YmdHis') . '_' . uniqid() . '.xml';
        $filepath = $upload_dir . $filename;

        if (!move_uploaded_file($upload['tmp_name'], $filepath)) {
            throw new Exception("Erro ao salvar arquivo");
        }

        // Importar com PPG e área
        $ppg = $_POST['ppg'] ?? null;
        $area = $_POST['area'] ?? null;

        $importer = new \ProdmaisUMC\LattesImporter();
        $import_result = $importer->importFromXML($filepath, $ppg, $area);

        $msg = "✅ Currículo importado com sucesso!";
        
        $log->log('INFO', 'Pesquisador adicionado via admin', [
            'file' => $filename,
            'ppg' => $ppg,
            'area' => $area,
            'result' => $import_result
        ]);
    } catch (Exception $e) {
        $msg_error = $e->getMessage();
        $log->log('ERROR', 'Erro ao importar pesquisador', [
            'error' => $e->getMessage()
        ]);
    }
}

$ppgs = getAllPPGs();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/img/umc-favicon.png">
    <title>Administração - PRODMAIS UMC</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- AdminPage premium styles -->
    <style>
    /* ── Hero ── */
    .adm-hero {
        background: #070d1f;
        background-image:
            radial-gradient(ellipse 60% 70% at 15% 65%, rgba(99,102,241,.13), transparent),
            radial-gradient(ellipse 40% 40% at 88% 12%, rgba(139,92,246,.10), transparent),
            radial-gradient(ellipse 30% 30% at 55% 88%, rgba(79,70,229,.08), transparent);
        position: relative; overflow: hidden;
        padding: 5.5rem 0 3.5rem;
    }
    .adm-hero::before {
        content: ''; position: absolute; inset: 0;
        background-image: radial-gradient(rgba(255,255,255,.05) 1px, transparent 1px);
        background-size: 28px 28px; pointer-events: none;
    }
    /* ══ Sidebar Layout ══ */
    .adm-layout {
        display: flex;
        gap: 0;
        align-items: flex-start;
        position: relative;
    }

    /* ── Sidebar ── */
    .adm-sidebar {
        width: 260px;
        flex-shrink: 0;
        background: white;
        border-radius: 20px;
        border: 1px solid rgba(0,0,0,.08);
        box-shadow: 0 4px 20px rgba(0,0,0,.07);
        overflow: hidden;
        position: sticky;
        top: 80px;
    }
    .adm-sidebar-header {
        background: linear-gradient(135deg,#4f46e5,#6366f1);
        padding: 1.25rem 1.5rem;
        display: flex; align-items: center; gap: .625rem;
    }
    .adm-sidebar-header span {
        color: white; font-weight: 700; font-size: .875rem; letter-spacing: .02em;
    }
    .adm-sidebar-header i { color: rgba(255,255,255,.75); font-size: .9rem; }
    .adm-sidebar-nav { padding: .625rem; }
    .adm-tab-btn {
        display: flex; align-items: center; gap: .75rem; width: 100%;
        border: none; background: transparent; border-radius: 12px;
        padding: .8rem 1rem; font-size: .875rem; font-weight: 600; color: #64748b;
        cursor: pointer; transition: all .2s ease; text-align: left;
        font-family: 'Inter', sans-serif; margin-bottom: .25rem;
    }
    .adm-tab-btn i { width: 18px; text-align: center; flex-shrink: 0; }
    .adm-tab-btn:hover { background: #f1f5f9; color: #4f46e5; }
    .adm-tab-btn.active {
        background: linear-gradient(135deg,#4f46e5,#6366f1); color: white;
        box-shadow: 0 4px 14px rgba(79,70,229,.3);
    }
    .adm-tab-label { flex: 1; }
    .adm-tab-badge {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 20px; height: 20px; padding: 0 .375rem;
        background: #ef4444; color: white; font-size: .7rem; font-weight: 800;
        border-radius: 100px; line-height: 1; flex-shrink: 0;
    }
    .adm-tab-btn.active .adm-tab-badge { background: rgba(255,255,255,.3); }
    .adm-sidebar-divider {
        height: 1px; background: rgba(0,0,0,.07); margin: .375rem .625rem;
    }

    /* ── Main content area ── */
    .adm-main { flex: 1; min-width: 0; }

    /* ── Mobile sidebar toggle ── */
    .adm-sidebar-toggle {
        display: none;
        position: fixed; bottom: 1.25rem; right: 1.25rem; z-index: 1050;
        width: 52px; height: 52px; border-radius: 16px;
        background: linear-gradient(135deg,#4f46e5,#6366f1);
        border: none; color: white; font-size: 1.25rem;
        box-shadow: 0 8px 24px rgba(79,70,229,.45);
        cursor: pointer; transition: transform .2s, box-shadow .2s;
        align-items: center; justify-content: center;
    }
    .adm-sidebar-toggle:hover { transform: scale(1.08); box-shadow: 0 12px 30px rgba(79,70,229,.55); }

    /* ── Mobile drawer ── */
    .adm-drawer-overlay {
        display: none;
        position: fixed; inset: 0; z-index: 1040;
        background: rgba(0,0,0,.45); backdrop-filter: blur(3px);
    }
    .adm-drawer-overlay.open { display: block; }
    .adm-drawer {
        position: fixed; bottom: 0; left: 0; right: 0; z-index: 1045;
        background: white; border-radius: 24px 24px 0 0;
        padding: .5rem .625rem 1.5rem;
        box-shadow: 0 -8px 40px rgba(0,0,0,.18);
        transform: translateY(100%);
        transition: transform .3s cubic-bezier(.32,.72,0,1);
    }
    .adm-drawer.open { transform: translateY(0); }
    .adm-drawer-handle {
        width: 40px; height: 4px; border-radius: 99px;
        background: #cbd5e1; margin: .75rem auto 1rem;
    }
    .adm-drawer-title {
        font-size: .7rem; font-weight: 700; letter-spacing: .08em;
        text-transform: uppercase; color: #94a3b8;
        padding: 0 .5rem .5rem;
    }

    /* ── Responsive ── */
    @media (max-width: 767px) {
        .adm-sidebar { display: none; }
        .adm-sidebar-toggle { display: flex; }
        .adm-layout { display: block; }
    }
    @media (min-width: 768px) {
        .adm-layout { gap: 1.75rem; }
        .adm-drawer-overlay, .adm-drawer, .adm-sidebar-toggle { display: none !important; }
    }
    /* ── Usuários pendentes ── */
    .pending-card {
        background: white; border-radius: 16px; border: 1px solid rgba(0,0,0,.07);
        box-shadow: 0 2px 10px rgba(0,0,0,.05); padding: 1.25rem;
        display: flex; flex-direction: column; gap: .875rem; margin-bottom: 1rem;
    }
    .pending-card-top { display: flex; align-items: center; gap: .75rem; flex: 1; min-width: 0; }
    .pending-card-avatar { display: none; flex-shrink: 0; }
    .pending-mobile-badge { display: none; }
    .pending-card-info { display: flex; flex-direction: column; gap: .2rem; min-width: 0; }
    .pending-card-name { font-weight: 700; color: #0f172a; font-size: .95rem; }
    .pending-card-meta { font-size: .8125rem; color: #64748b; }
    .pending-card-actions { display: flex; gap: .625rem; }
    .pending-card-actions form { flex: 1; }
    .pending-btn-approve, .pending-btn-reject {
        width: 100%; display: flex; align-items: center; justify-content: center; gap: .4rem;
        border: none; border-radius: 10px; padding: .7rem 1rem;
        font-size: .875rem; font-weight: 700; cursor: pointer; font-family: 'Inter', sans-serif;
    }
    .pending-btn-approve { background: linear-gradient(135deg,#059669,#10b981); color: white; }
    .pending-btn-reject { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
    .pending-empty { text-align: center; padding: 3rem 1.5rem; color: #94a3b8; }
    @media (min-width: 640px) {
        .pending-card { flex-direction: row; align-items: center; justify-content: space-between; }
        .pending-card-actions { flex-shrink: 0; width: auto; }
        .pending-btn-approve, .pending-btn-reject { width: auto; }
    }
    /* ── Redesign mobile: avatar com iniciais + badges mais legíveis ── */
    @media (max-width: 767px) {
        .pending-card {
            padding: 1rem 1.1rem;
            border-radius: 18px;
            gap: .75rem;
        }
        .pending-card-avatar {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: linear-gradient(135deg,#4f46e5,#6366f1);
            color: white;
            font-weight: 700;
            font-size: .9rem;
            box-shadow: 0 4px 10px rgba(79,70,229,.25);
        }
        .pending-card-name {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: .35rem;
            font-size: .9rem;
        }
        .pending-card-meta {
            display: block;
            line-height: 1.5;
        }
        .adm-badge {
            font-size: .625rem;
            padding: .18rem .55rem;
        }
        .pending-mobile-badge {
            display: inline-flex;
        }
    }
    /* ── Cards ── */
    .adm-card { background: white; border-radius: 20px; border: 1px solid rgba(0,0,0,.07); box-shadow: 0 2px 12px rgba(0,0,0,.06); overflow: hidden; margin-bottom: 1.5rem; }
    .adm-card-header { background: linear-gradient(135deg,#4f46e5,#6366f1); padding: 1.25rem 1.75rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; border-radius: 0; }
    .adm-card-header h5 { margin: 0; color: white; font-weight: 700; font-size: 1rem; }
    .adm-card-body { padding: 1.75rem; }
    /* ── Info box ── */
    .adm-info-box { background: rgba(79,70,229,.06); border: 1px solid rgba(79,70,229,.16); border-radius: 12px; padding: 1.25rem 1.5rem; margin-bottom: 1.75rem; }
    .adm-info-box h6 { color: #312e81; font-weight: 700; margin-bottom: .75rem; }
    .adm-info-box ol { margin: 0; padding-left: 1.25rem; color: #1e1b4b; }
    /* ── Upload zone ── */
    .adm-upload-zone { border: 2px dashed rgba(99,102,241,.35); border-radius: 18px; padding: 3rem 2rem; text-align: center; cursor: pointer; background: rgba(99,102,241,.03); transition: all .25s ease; position: relative; }
    .adm-upload-zone:hover { border-color: #6366f1; background: rgba(99,102,241,.07); transform: translateY(-3px); box-shadow: 0 12px 32px rgba(99,102,241,.15); }
    .adm-upload-zone.dragover { border-color: #4f46e5; background: rgba(99,102,241,.1); }
    .adm-upload-zone i { color: #6366f1; font-size: 3.5rem; margin-bottom: 1.25rem; display: block; }
    .adm-upload-zone h5 { font-weight: 700; color: #1e293b; margin-bottom: .5rem; }
    .adm-upload-zone p { color: #64748b; font-size: .9rem; margin: 0; }
    /* ── Stat items (import result) ── */
    .adm-stat-item { background: linear-gradient(135deg,#ede9fe,#ddd6fe); padding: 1.5rem; border-radius: 14px; transition: transform .2s ease; }
    .adm-stat-item:hover { transform: translateY(-3px); }
    .adm-stat-item .stat-number { font-size: 2rem; font-weight: 800; color: #312e81; margin: 0; }
    .adm-stat-item .stat-label { color: #4338ca; font-size: .875rem; margin: 0; }
    /* ── Success result card ── */
    .adm-success { background: rgba(5,150,105,.07); border: 1px solid rgba(5,150,105,.2); border-radius: 14px; padding: 1.75rem; margin-bottom: 1.5rem; }
    .adm-success h4 { color: #065f46; font-weight: 700; margin-bottom: 1.25rem; }
    /* ── Form controls ── */
    .adm-form-label { font-size: .875rem; font-weight: 600; color: #374151; margin-bottom: .5rem; display: block; }
    .adm-form-select { width: 100%; border: 1.5px solid rgba(0,0,0,.12); border-radius: 10px; padding: .65rem 1rem; font-size: .9rem; color: #1e293b; background: white; transition: border-color .2s; }
    .adm-form-select:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.12); }
    /* ── Buttons ── */
    .adm-btn-primary { display: block; width: 100%; background: linear-gradient(135deg,#4f46e5,#6366f1); color: white; border: none; border-radius: 12px; padding: .9rem 2rem; font-size: .95rem; font-weight: 700; cursor: pointer; transition: filter .2s, transform .2s; box-shadow: 0 4px 14px rgba(79,70,229,.3); font-family: 'Inter', sans-serif; }
    .adm-btn-primary:hover { filter: brightness(1.08); transform: translateY(-2px); }
    .adm-btn-bulk { background: linear-gradient(135deg,#4f46e5,#6366f1); color: white; border: none; border-radius: 12px; padding: .9rem 2rem; font-size: .95rem; font-weight: 700; cursor: pointer; font-family: 'Inter', sans-serif; box-shadow: 0 4px 14px rgba(79,70,229,.3); }
    .adm-btn-danger { background: linear-gradient(135deg,#dc2626,#ef4444); color: white !important; }
    /* ── Log table ── */
    .adm-table { width: 100%; border-collapse: collapse; }
    .adm-table th { background: linear-gradient(135deg,#4f46e5,#6366f1); color: white; font-size: .7rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; padding: 1rem 1.25rem; text-align: left; border-radius: 0; }
    .adm-table td { padding: .9rem 1.25rem; font-size: .875rem; color: #374151; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .adm-table tbody tr:last-child td { border-bottom: none; }
    .adm-table tbody tr:nth-child(even) td { background: #fafafe; }
    .adm-table tbody tr:hover td { background: rgba(99,102,241,.06); }
    .adm-table-wrap { border-radius: 0; overflow: hidden; border: 1px solid rgba(0,0,0,.08); box-shadow: 0 2px 10px rgba(0,0,0,.04); }
    .adm-log-user { display: inline-flex; align-items: center; gap: .4rem; font-weight: 600; color: #312e81; }
    .adm-log-user i { color: #818cf8; font-size: .8rem; }
    .adm-log-time { color: #94a3b8; font-size: .8rem; white-space: nowrap; }
    .adm-badge { display: inline-flex; align-items: center; padding: .2rem .65rem; border-radius: 100px; font-size: .68rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
    .adm-badge-info    { background: rgba(59,130,246,.12); color: #1d4ed8; }
    .adm-badge-success { background: rgba(16,185,129,.12); color: #059669; }
    .adm-badge-warning { background: rgba(245,158,11,.12); color: #b45309; }
    .adm-badge-error   { background: rgba(239,68,68,.12);  color: #b91c1c; }
    /* ── Alert info ── */
    .adm-alert-info { background: rgba(79,70,229,.06); border: 1px solid rgba(99,102,241,.2); border-radius: 14px; padding: 1.25rem 1.5rem; }
    .adm-alert-info h6 { color: #312e81; font-weight: 700; margin-bottom: .5rem; }
    .adm-alert-info p { color: #1e1b4b; margin: 0; font-size: .9rem; line-height: 1.6; }
    .adm-alert-info a { color: #4f46e5; font-weight: 600; }
    /* ── Section bg ── */
    .adm-section { background: #f8fafc; padding: 3rem 0 5rem; }
    </style>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="/css/umc-theme.css" rel="stylesheet">
    <link href="/css/prodmais-elegant.css?v=5" rel="stylesheet">
    
    <style>
        body {
            padding-top: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f8fafc;
        }
        /* legacy block kept for compatibility — overridden by adm-* classes above */
        .hero-admin {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 50%, #b45309 100%);
            padding: 4rem 0 3rem;
            position: relative;
            overflow: hidden;
            margin-bottom: 3rem;
        }
        
        .hero-admin::before {
            content: '';
            position: absolute;
            top: 20%;
            left: 10%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            filter: blur(80px);
        }
        
        .hero-admin::after {
            content: '';
            position: absolute;
            bottom: 20%;
            right: 10%;
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            filter: blur(100px);
        }
        
        .hero-admin h1 {
            color: white;
            font-weight: 900;
            font-size: 3.5rem;
            margin-bottom: 1rem;
            letter-spacing: -0.02em;
            line-height: 1.2;
            position: relative;
            z-index: 1;
        }
        
        .hero-admin p {
            color: rgba(255, 255, 255, 0.95);
            font-size: 1.25rem;
            margin: 0;
            position: relative;
            z-index: 1;
        }
        
        .card {
            border-radius: 16px;
            border: 1px solid var(--gray-200);
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }
        
        .card-header {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            border: none;
            border-radius: 16px 16px 0 0 !important;
            padding: 1.5rem;
            font-weight: 800;
        }
        
        .nav-tabs {
            border: none;
            background: white;
            padding: 1rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }
        
        .nav-tabs .nav-link {
            border: none;
            border-radius: 8px;
            font-weight: 600;
            color: var(--gray-700);
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
        }
        
        .nav-tabs .nav-link:hover {
            background: var(--gray-100);
            color: #f59e0b;
        }
        
        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }
        
        .upload-zone {
            border: 3px dashed var(--gray-300);
            border-radius: 16px;
            padding: 3rem;
            text-align: center;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            background: white;
            position: relative;
            overflow: hidden;
        }

        .upload-zone::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.05), rgba(217, 119, 6, 0.05));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .upload-zone:hover {
            border-color: #f59e0b;
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(245, 158, 11, 0.2);
        }

        .upload-zone:hover::before {
            opacity: 1;
        }

        .upload-zone.dragover {
            border-color: #d97706;
            background: rgba(245, 158, 11, 0.05);
            transform: scale(1.02);
            box-shadow: 0 16px 40px rgba(245, 158, 11, 0.25);
        }

        .upload-zone i {
            color: #f59e0b;
            font-size: 4rem;
            margin-bottom: 1.5rem;
        }
        
        .upload-zone h5 {
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.75rem;
        }
        
        .upload-zone p {
            color: var(--gray-600);
            font-size: 0.938rem;
        }
        
        .result-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }
        
        .stat-item {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            padding: 1.5rem;
            border-radius: 12px;
            transition: all 0.3s;
        }

        .stat-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.15);
        }

        .stat-item .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #b45309;
            margin: 0;
        }

        .stat-item .stat-label {
            color: #92400e;
            font-size: 0.9rem;
            margin: 0;
        }
    </style>
</head>

<body>
<?php
Navbar::display(['active_page' => 'admin', 'mostrar_link_dashboard' => $mostrar_link_dashboard ?? true]);
?>

<!-- ══ Hero Admin ══ -->
<section class="adm-hero">
    <div class="container text-center" style="position:relative;z-index:1;">

        <div style="display:inline-flex;align-items:center;gap:.5rem;background:rgba(99,102,241,.15);border:1px solid rgba(99,102,241,.3);border-radius:100px;padding:.375rem 1rem;font-size:.75rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#a5b4fc;margin-bottom:1.75rem;">
            <i class="fas fa-shield-alt" style="font-size:.7rem;"></i>
            Área Restrita · Administração
        </div>

        <h1 style="font-size:clamp(1.6rem,5vw,3.75rem);font-weight:900;line-height:1.05;letter-spacing:-2px;color:#f1f5f9;margin:0 0 1rem;">
            <i class="fas fa-cog me-3" style="color:#6366f1;"></i>Administração
        </h1>

        <p style="font-size:1rem;color:rgba(241,245,249,.5);max-width:500px;margin:0 auto;line-height:1.6;">
            Gestão de Pesquisadores, Currículos Lattes e Logs do Sistema
        </p>

        <?php if (!empty($_SESSION['username'])): ?>
        <div style="margin-top:2rem;display:inline-flex;align-items:center;gap:.5rem;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:100px;padding:.5rem 1.25rem;font-size:.82rem;color:rgba(241,245,249,.7);">
            <i class="fas fa-user-circle" style="color:#a5b4fc;"></i>
            Logado como <strong style="color:#c7d2fe;margin-left:.25rem;"><?= htmlspecialchars($_SESSION['username']) ?></strong>
        </div>
        <?php endif; ?>

    </div>
</section>
<!-- ══ /Hero Admin ══ -->

<section class="adm-section">
    <div class="container">

        <!-- ── Alertas globais ── -->
        <?php if (!empty($msg)) echo "<div class='alert alert-success mb-3'><i class='bi bi-check-circle me-2'></i>$msg</div>"; ?>
        <?php if (!empty($msg_error)) echo "<div class='alert alert-danger mb-3'><i class='bi bi-exclamation-triangle me-2'></i>$msg_error</div>"; ?>
        <?php if (!empty($pendingMsg)) echo "<div class='alert alert-success mb-3'><i class='bi bi-check-circle me-2'></i>" . htmlspecialchars($pendingMsg) . "</div>"; ?>
        <?php if (!empty($manageMsg)) echo "<div class='alert alert-success mb-3'><i class='bi bi-check-circle me-2'></i>" . htmlspecialchars($manageMsg) . "</div>"; ?>

        <!-- ── Layout: sidebar + conteúdo ── -->
        <div class="adm-layout">

            <!-- ══ SIDEBAR (desktop) ══ -->
            <aside class="adm-sidebar" aria-label="Navegação do painel">
                <div class="adm-sidebar-header">
                    <i class="fas fa-cog"></i>
                    <span>Painel Admin</span>
                </div>
                <nav class="adm-sidebar-nav" id="adminTabs" role="tablist">
                    <?php if ($souAdmin): ?>
                    <button class="adm-tab-btn<?= $pendingCount > 0 ? ' active' : '' ?>" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab" aria-controls="pending">
                        <i class="fas fa-user-clock" aria-hidden="true"></i>
                        <span class="adm-tab-label">Usuários Pendentes</span>
                        <?php if ($pendingCount > 0): ?><span class="adm-tab-badge"><?= $pendingCount ?></span><?php endif; ?>
                    </button>
                    <button class="adm-tab-btn" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button" role="tab" aria-controls="users">
                        <i class="fas fa-users-gear" aria-hidden="true"></i>
                        <span class="adm-tab-label">Gerenciar Usuários</span>
                    </button>
                    <div class="adm-sidebar-divider"></div>
                    <?php endif; ?>
                    <button class="adm-tab-btn<?= $pendingCount === 0 || !$souAdmin ? ' active' : '' ?>" id="researcher-tab" data-bs-toggle="tab" data-bs-target="#researcher" type="button" role="tab" aria-controls="researcher">
                        <i class="fas fa-user-plus" aria-hidden="true"></i>
                        <span class="adm-tab-label">Adicionar Pesquisador</span>
                    </button>
                    <button class="adm-tab-btn" id="bulk-tab" data-bs-toggle="tab" data-bs-target="#bulk" type="button" role="tab" aria-controls="bulk">
                        <i class="fas fa-upload" aria-hidden="true"></i>
                        <span class="adm-tab-label">Upload em Lote</span>
                    </button>
                    <div class="adm-sidebar-divider"></div>
                    <button class="adm-tab-btn" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs" type="button" role="tab" aria-controls="logs">
                        <i class="fas fa-file-alt" aria-hidden="true"></i>
                        <span class="adm-tab-label">Logs do Sistema</span>
                    </button>
                    <button class="adm-tab-btn" id="lgpd-tab" data-bs-toggle="tab" data-bs-target="#lgpd" type="button" role="tab" aria-controls="lgpd">
                        <i class="fas fa-shield-alt" aria-hidden="true"></i>
                        <span class="adm-tab-label">Conformidade LGPD</span>
                    </button>
                </nav>
            </aside>

            <!-- ══ CONTEÚDO ══ -->
            <div class="adm-main">

                <div class="tab-content" id="adminTabContent">
                    <?php if ($souAdmin): ?>
                    <!-- Aba: Usuários Pendentes -->
                    <div class="tab-pane fade<?= $pendingCount > 0 ? ' show active' : '' ?>" id="pending" role="tabpanel">
                        <?php if (empty($pendingUsers)): ?>
                            <div class="pending-empty">
                                <i class="fas fa-circle-check" style="font-size:2.5rem;opacity:.3;margin-bottom:1rem;display:block;"></i>
                                Nenhuma solicitação de cadastro pendente no momento.
                            </div>
                        <?php else: ?>
                            <?php foreach ($pendingUsers as $pu):
                                $puNome    = $pu['nome_completo'] ?: $pu['username'];
                                $puInicial = mb_strtoupper(mb_substr(trim($puNome), 0, 1));
                            ?>
                            <div class="pending-card">
                                <div class="pending-card-top">
                                    <span class="pending-card-avatar" aria-hidden="true"><?= htmlspecialchars($puInicial) ?></span>
                                    <div class="pending-card-info">
                                        <span class="pending-card-name">
                                            <?= htmlspecialchars($puNome) ?>
                                            <span class="adm-badge adm-badge-warning pending-mobile-badge">Pendente</span>
                                        </span>
                                        <span class="pending-card-meta">
                                            <i class="fas fa-at" aria-hidden="true"></i> <?= htmlspecialchars($pu['username']) ?>
                                            &nbsp;·&nbsp; <?= htmlspecialchars($pu['email']) ?>
                                            &nbsp;·&nbsp; solicitou <strong><?= htmlspecialchars($pu['papel']) ?></strong>
                                            &nbsp;·&nbsp; <?= date('d/m/Y', strtotime($pu['criado_em'])) ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="pending-card-actions">
                                    <form method="post">
                                        <input type="hidden" name="user_id" value="<?= (int) $pu['id'] ?>">
                                        <button type="submit" name="approve_user" class="pending-btn-approve">
                                            <i class="fas fa-check" aria-hidden="true"></i> Aprovar
                                        </button>
                                    </form>
                                    <form method="post" onsubmit="return confirm('Rejeitar e remover a solicitação de <?= htmlspecialchars(addslashes($pu['username'])) ?>?');">
                                        <input type="hidden" name="user_id" value="<?= (int) $pu['id'] ?>">
                                        <button type="submit" name="reject_user" class="pending-btn-reject">
                                            <i class="fas fa-xmark" aria-hidden="true"></i> Rejeitar
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Aba: Gerenciar Usuários -->
                    <div class="tab-pane fade" id="users" role="tabpanel">
                        <?php if (empty($allUsers)): ?>
                            <div class="pending-empty">
                                <i class="fas fa-users-slash" style="font-size:2.5rem;opacity:.3;margin-bottom:1rem;display:block;"></i>
                                Nenhum usuário cadastrado ainda.
                            </div>
                        <?php else: ?>
                            <?php foreach ($allUsers as $au):
                                $statusBadgeCls = 'adm-badge-error';
                                if ($au['status'] === 'ativo') {
                                    $statusBadgeCls = 'adm-badge-success';
                                } elseif ($au['status'] === 'pendente') {
                                    $statusBadgeCls = 'adm-badge-warning';
                                }
                                $auNome    = $au['nome_completo'] ?: $au['username'];
                                $auInicial = mb_strtoupper(mb_substr(trim($auNome), 0, 1));
                            ?>
                            <div class="pending-card">
                                <div class="pending-card-top">
                                    <span class="pending-card-avatar" aria-hidden="true"><?= htmlspecialchars($auInicial) ?></span>
                                    <div class="pending-card-info">
                                        <span class="pending-card-name">
                                            <?= htmlspecialchars($auNome) ?>
                                            <?php if ((int) $au['id'] === $meuId): ?><span class="adm-badge adm-badge-info" style="margin-left:.5rem;">Você</span><?php endif; ?>
                                            <?php if (!empty($au['conta_sistema'])): ?><span class="adm-badge adm-badge-info" style="margin-left:.5rem;"><i class="fas fa-shield-halved" aria-hidden="true"></i> Conta de Sistema</span><?php endif; ?>
                                            <span class="adm-badge <?= $statusBadgeCls ?>" style="margin-left:.4rem;"><?= htmlspecialchars($au['status']) ?></span>
                                        </span>
                                        <span class="pending-card-meta">
                                            <i class="fas fa-at" aria-hidden="true"></i> <?= htmlspecialchars($au['username']) ?>
                                            &nbsp;·&nbsp; <?= htmlspecialchars($au['email']) ?>
                                            &nbsp;·&nbsp; cadastrado em <?= date('d/m/Y', strtotime($au['criado_em'])) ?>
                                            <?php if (!empty($au['ultimo_login'])): ?>&nbsp;·&nbsp; último login <?= date('d/m/Y H:i', strtotime($au['ultimo_login'])) ?><?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="pending-card-actions">
                                    <?php if ((int) $au['id'] === $meuId): ?>
                                        <span class="pending-card-meta" style="align-self:center;">Gerencie sua própria conta em "Alterar senha"</span>
                                    <?php elseif (!empty($au['conta_sistema'])): ?>
                                        <span class="pending-card-meta" style="align-self:center;"><i class="fas fa-lock" aria-hidden="true"></i> Conta protegida — não pode ser editada nem excluída por aqui</span>
                                    <?php else: ?>
                                    <form method="post" style="display:flex;gap:.5rem;align-items:center;flex:1;">
                                        <input type="hidden" name="account_id" value="<?= (int) $au['id'] ?>">
                                        <select name="novo_papel" class="adm-form-select" style="flex:1;">
                                            <option value="admin" <?= $au['papel'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                            <option value="pesquisador" <?= $au['papel'] === 'pesquisador' ? 'selected' : '' ?>>Pesquisador</option>
                                            <option value="visualizador" <?= $au['papel'] === 'visualizador' ? 'selected' : '' ?>>Visualizador</option>
                                        </select>
                                        <button type="submit" name="update_role" class="pending-btn-approve" style="width:auto;white-space:nowrap;">
                                            <i class="fas fa-save" aria-hidden="true"></i> Salvar
                                        </button>
                                    </form>
                                    <form method="post" onsubmit="return confirm('Excluir permanentemente a conta de <?= htmlspecialchars(addslashes($au['username'])) ?>?');">
                                        <input type="hidden" name="account_id" value="<?= (int) $au['id'] ?>">
                                        <button type="submit" name="delete_account" class="pending-btn-reject">
                                            <i class="fas fa-trash" aria-hidden="true"></i> Excluir
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Aba: Adicionar Pesquisador Individual -->
                    <div class="tab-pane fade<?= $pendingCount === 0 || !$souAdmin ? ' show active' : '' ?>" id="researcher" role="tabpanel">
                        <?php if ($import_result): ?>
                            <div class="adm-success mb-4">
                                <h4><i class="fas fa-check-circle me-2" aria-hidden="true"></i>Importação Concluída!</h4>

                                <?php if (isset($import_result['pesquisador_nome'])): ?>
                                    <div class="d-flex align-items-center gap-3 mb-4">
                                        <div style="width:56px;height:56px;border-radius:16px;background:rgba(99,102,241,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                            <i class="fas fa-user-graduate" style="color:#6366f1;font-size:1.5rem;" aria-hidden="true"></i>
                                        </div>
                                        <div>
                                            <div style="font-weight:700;color:#0f172a;font-size:1rem;"><?= htmlspecialchars($import_result['pesquisador_nome']) ?></div>
                                            <?php if (isset($import_result['ppg'])): ?>
                                                <div style="color:#64748b;font-size:.875rem;">PPG: <?= htmlspecialchars($import_result['ppg']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="row g-3">
                                    <?php if (isset($import_result['total_producoes']) && $import_result['total_producoes'] > 0): ?>
                                        <div class="col-md-4">
                                            <div class="adm-stat-item">
                                                <p class="stat-number"><?= $import_result['total_producoes'] ?></p>
                                                <p class="stat-label">Produções Indexadas</p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (isset($import_result['artigos']) && $import_result['artigos'] > 0): ?>
                                        <div class="col-md-4">
                                            <div class="adm-stat-item">
                                                <p class="stat-number"><?= $import_result['artigos'] ?></p>
                                                <p class="stat-label">Artigos Publicados</p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (isset($import_result['livros']) && $import_result['livros'] > 0): ?>
                                        <div class="col-md-4">
                                            <div class="adm-stat-item">
                                                <p class="stat-number"><?= $import_result['livros'] ?></p>
                                                <p class="stat-label">Livros</p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="mt-4 d-flex gap-2 flex-wrap">
                                    <a href="/pesquisadores.php" style="display:inline-flex;align-items:center;gap:.4rem;background:linear-gradient(135deg,#4f46e5,#6366f1);color:white;border-radius:10px;padding:.6rem 1.25rem;font-size:.875rem;font-weight:700;text-decoration:none;">
                                        <i class="fas fa-users" aria-hidden="true"></i>Ver Pesquisadores
                                    </a>
                                    <a href="/admin.php" style="display:inline-flex;align-items:center;gap:.4rem;border:1.5px solid rgba(99,102,241,.3);color:#4f46e5;border-radius:10px;padding:.6rem 1.25rem;font-size:.875rem;font-weight:700;text-decoration:none;">
                                        <i class="fas fa-plus" aria-hidden="true"></i>Importar Outro
                                    </a>
                                </div>

                                <?php if (isset($import_result['total_producoes']) && $import_result['total_producoes'] == 0): ?>
                                <div class="adm-alert-info mt-4">
                                    <h6><i class="fas fa-info-circle me-2" aria-hidden="true"></i>Sobre as estatísticas do Dashboard</h6>
                                    <p>O pesquisador <strong><?= htmlspecialchars($import_result['pesquisador_nome'] ?? '') ?></strong> foi cadastrado com <strong>0 produções</strong> — exatamente como consta no Lattes importado. O Dashboard exibe totais globais de toda a base. Para ver apenas produções deste pesquisador, acesse a página <a href="/pesquisadores.php">Pesquisadores</a>.</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="adm-card">
                            <div class="adm-card-header">
                                <h5><i class="fas fa-user-plus me-2" aria-hidden="true"></i>Adicionar Novo Pesquisador UMC</h5>
                            </div>
                            <div class="adm-card-body">
                                <div class="adm-info-box">
                                    <h6><i class="fas fa-info-circle me-2"></i>Como exportar currículo Lattes</h6>
                                    <ol>
                                        <li>Acesse a <a href="http://lattes.cnpq.br/" target="_blank" rel="noopener" style="color:#4f46e5;font-weight:600;">Plataforma Lattes <i class="fas fa-external-link-alt fa-xs"></i></a></li>
                                        <li>Faça login e acesse seu currículo completo</li>
                                        <li>No menu, clique em <strong>"Exportar currículo"</strong> ou <strong>"Baixar XML"</strong></li>
                                        <li>Salve o arquivo XML no seu computador</li>
                                        <li>Faça o upload do arquivo no formulário abaixo</li>
                                    </ol>
                                </div>

                                <form method="post" enctype="multipart/form-data" id="uploadForm">
                                    <div class="mb-3">
                                        <label for="ppg" class="adm-form-label">Programa de Pós-Graduação *</label>
                                        <select class="adm-form-select" id="ppg" name="ppg" required>
                                            <option value="">Selecione o PPG</option>
                                            <?php foreach ($ppgs as $ppg): ?>
                                                <option value="<?= htmlspecialchars($ppg['nome']) ?>"><?= htmlspecialchars($ppg['nome']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="area" class="adm-form-label">Área de Concentração (opcional)</label>
                                        <select class="adm-form-select" id="area" name="area">
                                            <option value="">Selecione...</option>
                                        </select>
                                        <small style="color:#94a3b8;font-size:.8rem;">Selecione primeiro o PPG</small>
                                    </div>

                                    <div class="mb-4">
                                        <label class="adm-form-label">Arquivo XML do Lattes *</label>
                                        <div class="adm-upload-zone" id="uploadZone">
                                            <i class="fas fa-cloud-upload-alt" aria-hidden="true"></i>
                                            <h5>Arraste o arquivo aqui ou clique para selecionar</h5>
                                            <p>Arquivo XML exportado da Plataforma Lattes · Máx. 50 MB</p>
                                            <input type="file" id="lattes_xml" name="lattes_xml" style="display:none;" accept=".xml">
                                        </div>
                                        <div id="fileInfo" class="mt-2 d-none">
                                            <div style="background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.2);border-radius:10px;padding:.75rem 1rem;font-size:.875rem;color:#312e81;">
                                                <i class="fas fa-file-alt me-2" aria-hidden="true"></i>
                                                <strong id="fileName"></strong> (<span id="fileSize"></span>)
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" name="upload_researcher" class="adm-btn-primary" id="submitBtn">
                                        <i class="fas fa-cloud-upload-alt me-2" aria-hidden="true"></i>Importar Currículo Lattes
                                    </button>
                                    <button type="button" class="adm-btn-primary" id="processingBtn" style="display:none;opacity:.7;cursor:not-allowed;" disabled>
                                        <span class="spinner-border spinner-border-sm me-2"></span>Processando…
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Aba: Upload em Lote -->
                    <div class="tab-pane fade" id="bulk" role="tabpanel">
                        <div class="adm-card">
                            <div class="adm-card-header">
                                <h5><i class="fas fa-upload me-2" aria-hidden="true"></i>Upload em Lote</h5>
                            </div>
                            <div class="adm-card-body">
                                <form action="api/upload_and_index.php" method="post" enctype="multipart/form-data" id="upload-form">
                                    <div class="mb-4">
                                        <label for="lattes_files" class="adm-form-label">Selecione múltiplos arquivos XML do Lattes</label>
                                        <input class="adm-form-select" type="file" id="lattes_files" name="lattes_files[]" multiple required accept=".xml" style="padding:.55rem .875rem;">
                                        <small style="color:#94a3b8;font-size:.8rem;display:block;margin-top:.375rem;">Você pode selecionar múltiplos arquivos de uma vez para processamento em lote.</small>
                                    </div>
                                    <button type="submit" class="adm-btn-bulk">
                                        <i class="fas fa-cloud-upload-alt me-2" aria-hidden="true"></i>Processar Múltiplos Arquivos
                                    </button>
                                </form>
                                <div id="upload-status" class="mt-4"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Aba: Logs do Sistema -->
                    <div class="tab-pane fade" id="logs" role="tabpanel">
                        <div class="adm-card">
                            <div class="adm-card-header">
                                <h5><i class="fas fa-file-alt me-2" aria-hidden="true"></i>Logs do Sistema</h5>
                                <div class="d-inline-flex gap-2">
                                    <form method="post" class="d-inline">
                                        <button name="expunge" value="1" class="adm-btn-primary adm-btn-danger" style="width:auto;padding:.5rem 1.1rem;font-size:.8rem;" title="Remove apenas logs com mais de 365 dias">
                                            <i class="fas fa-trash me-1" aria-hidden="true"></i>Expurgar Antigos
                                        </button>
                                    </form>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Isso apaga TODOS os logs do sistema, sem volta. Confirma?');">
                                        <button name="expunge_all" value="1" class="adm-btn-primary adm-btn-danger" style="width:auto;padding:.5rem 1.1rem;font-size:.8rem;" title="Apaga todo o histórico de logs imediatamente">
                                            <i class="fas fa-eraser me-1" aria-hidden="true"></i>Limpar Tudo
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <div class="adm-card-body" style="padding:0;">
                                <div class="table-responsive adm-table-wrap">
                                    <table class="adm-table">
                                        <thead>
                                            <tr>
                                                <th>Nível</th>
                                                <th>Usuário/Sistema</th>
                                                <th>Ação</th>
                                                <th>Data/Hora</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                            <?php
                            $logs = $log->getLogs(100);
                            foreach ($logs as $row) {
                                $level = $row['level'] ?? 'INFO';
                                if ($level === 'ERROR') {
                                $badge_cls = 'adm-badge adm-badge-error';
                            } elseif ($level === 'WARNING') {
                                $badge_cls = 'adm-badge adm-badge-warning';
                            } else {
                                $badge_cls = 'adm-badge adm-badge-info';
                            }
                                $user = htmlspecialchars($row['user'] ?? $row['level'] ?? 'Sistema');
                                $action = htmlspecialchars($row['action'] ?? $row['message'] ?? 'N/A');
                                $timestamp = $row['timestamp'] ?? null;
                                $timestampFmt = $timestamp ? date('d/m/Y H:i', strtotime($timestamp)) : 'N/A';
                                echo "<tr><td><span class='{$badge_cls}'>{$level}</span></td>"
                                    . "<td><span class='adm-log-user'><i class='fas fa-user-circle' aria-hidden=\"true\"></i>{$user}</span></td>"
                                    . "<td>{$action}</td>"
                                    . "<td class='adm-log-time'>{$timestampFmt}</td></tr>";
                            }
                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Aba: Conformidade LGPD -->
                    <div class="tab-pane fade" id="lgpd" role="tabpanel">
                        <div class="adm-card">
                            <div class="adm-card-header">
                                <h5><i class="fas fa-shield-alt me-2" aria-hidden="true"></i>Status de Conformidade LGPD</h5>
                            </div>
                            <div class="adm-card-body">
                                <p style="color:#64748b;margin-bottom:1.5rem;">
                                    Última avaliação: <strong><?php echo htmlspecialchars($complianceStatus['last_assessment']); ?></strong>
                                    &mdash; Status geral: <strong style="color:#059669;"><?php echo htmlspecialchars($complianceStatus['overall_status']); ?></strong>
                                </p>
                                <div class="row g-3">
                                    <?php foreach ($complianceStatus['checks'] as $check): ?>
                                    <div class="col-md-6">
                                        <div class="adm-stat-item" style="display:flex;align-items:center;gap:.75rem;">
                                            <i class="fas fa-check-circle" style="color:#059669;font-size:1.25rem;"></i>
                                            <span style="color:#312e81;font-size:.875rem;"><?php echo htmlspecialchars($check['description']); ?></span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="adm-card">
                            <div class="adm-card-header">
                                <h5><i class="fas fa-file-contract me-2" aria-hidden="true"></i>Relatório de Impacto à Proteção de Dados (DPIA)</h5>
                                <a class="adm-btn-primary" style="width:auto;padding:.5rem 1.1rem;font-size:.8rem;text-decoration:none;"
                                   download="dpia-prodmais-<?php echo htmlspecialchars($dpiaReport['metadata']['date']); ?>.json"
                                   href="data:application/json;charset=utf-8,<?php echo rawurlencode(json_encode($dpiaReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?>">
                                    <i class="fas fa-download me-1" aria-hidden="true"></i>Baixar DPIA (JSON)
                                </a>
                            </div>
                            <div class="adm-card-body">
                                <p style="color:#64748b;"><?php echo htmlspecialchars($dpiaReport['project_description']['purpose'] ?? ''); ?></p>

                                <h6 style="font-weight:700;color:#1e1b4b;margin-top:1.5rem;">Categorias de dados pessoais tratados</h6>
                                <?php foreach ($dpiaReport['data_mapping']['personal_data_categories'] as $categoria): ?>
                                <div class="adm-info-box" style="margin-bottom:.75rem;">
                                    <strong><?php echo htmlspecialchars(implode(', ', $categoria['fields'])); ?></strong><br>
                                    <span style="font-size:.85rem;color:#4338ca;">
                                        Origem: <?php echo htmlspecialchars($categoria['source']); ?> &middot;
                                        Base legal: <?php echo htmlspecialchars($categoria['legal_basis']); ?>
                                    </span>
                                </div>
                                <?php endforeach; ?>

                                <h6 style="font-weight:700;color:#1e1b4b;margin-top:1.5rem;">Recomendações</h6>
                                <?php foreach ($dpiaReport['recommendations'] as $grupo => $itens): ?>
                                <p style="margin-bottom:.375rem;"><strong style="color:#312e81;text-transform:capitalize;"><?php echo htmlspecialchars(str_replace('_', ' ', $grupo)); ?>:</strong></p>
                                <ul style="color:#64748b;">
                                    <?php foreach ($itens as $item): ?>
                                    <li><?php echo htmlspecialchars($item); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-4 mb-2">
                    <a href="/" class="adm-back-link">
                        <i class="fas fa-arrow-left" aria-hidden="true"></i> Voltar ao Início
                    </a>
                </div>
                <style>.adm-back-link{display:inline-flex;align-items:center;gap:.5rem;color:#64748b;font-size:.875rem;font-weight:600;text-decoration:none;padding:.6rem 1.25rem;border:1.5px solid rgba(0,0,0,.1);border-radius:10px;transition:all .2s}.adm-back-link:hover,.adm-back-link:focus{border-color:#6366f1;color:#4f46e5}</style>

            </div><!-- /.adm-main -->
        </div><!-- /.adm-layout -->
    </div><!-- /.container -->
</section>

<!-- ══ MOBILE: botão flutuante + drawer ══ -->
<button class="adm-sidebar-toggle" id="sidebarToggle" aria-label="Abrir menu" aria-expanded="false" aria-controls="mobileDrawer">
    <i class="fas fa-bars" id="sidebarToggleIcon"></i>
</button>

<div class="adm-drawer-overlay" id="drawerOverlay" role="presentation"></div>

<div class="adm-drawer" id="mobileDrawer" role="dialog" aria-modal="true" aria-label="Menu de navegação">
    <div class="adm-drawer-handle"></div>
    <div class="adm-drawer-title">Navegação</div>
    <nav id="mobileAdminNav">
        <?php if ($souAdmin): ?>
        <button class="adm-tab-btn<?= $pendingCount > 0 ? ' active' : '' ?>" data-bs-target="#pending" type="button">
            <i class="fas fa-user-clock" aria-hidden="true"></i>
            <span class="adm-tab-label">Usuários Pendentes</span>
            <?php if ($pendingCount > 0): ?><span class="adm-tab-badge"><?= $pendingCount ?></span><?php endif; ?>
        </button>
        <button class="adm-tab-btn" data-bs-target="#users" type="button">
            <i class="fas fa-users-gear" aria-hidden="true"></i>
            <span class="adm-tab-label">Gerenciar Usuários</span>
        </button>
        <div class="adm-sidebar-divider"></div>
        <?php endif; ?>
        <button class="adm-tab-btn<?= $pendingCount === 0 || !$souAdmin ? ' active' : '' ?>" data-bs-target="#researcher" type="button">
            <i class="fas fa-user-plus" aria-hidden="true"></i>
            <span class="adm-tab-label">Adicionar Pesquisador</span>
        </button>
        <button class="adm-tab-btn" data-bs-target="#bulk" type="button">
            <i class="fas fa-upload" aria-hidden="true"></i>
            <span class="adm-tab-label">Upload em Lote</span>
        </button>
        <div class="adm-sidebar-divider"></div>
        <button class="adm-tab-btn" data-bs-target="#logs" type="button">
            <i class="fas fa-file-alt" aria-hidden="true"></i>
            <span class="adm-tab-label">Logs do Sistema</span>
        </button>
        <button class="adm-tab-btn" data-bs-target="#lgpd" type="button">
            <i class="fas fa-shield-alt" aria-hidden="true"></i>
            <span class="adm-tab-label">Conformidade LGPD</span>
        </button>
    </nav>
</div>

<?php Footer::display(); ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- JavaScript para Administração -->
    <script>
    // Configuração de dados
    const areas = <?= json_encode(array_column($ppgs, 'areas_concentracao', 'nome')) ?>;
    
    // Upload em Lote (Handler principal)
    document.getElementById('upload-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const statusDiv = document.getElementById('upload-status');
        
        statusDiv.innerHTML = '<div class="alert alert-info"><i class="spinner-border spinner-border-sm me-2"></i> Enviando e processando arquivos... Isso pode levar alguns minutos.</div>';
        
        fetch(form.action, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) throw new Error('Erro na comunicação com o servidor: ' + response.statusText);
            return response.json();
        })
        .then(data => {
            if (data.status === 'error') {
                statusDiv.innerHTML = `<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> ${data.message}</div>`;
                return;
            }
            
            let reportHtml = `
                <div class="alert alert-success">
                    <i class="bi bi-check-circle-fill me-2"></i> <strong>Processo Concluído!</strong><br>
                    <ul class="mb-0 mt-2">
                        <li>Arquivos processados: ${data.processed_files || 0}</li>
                        <li>Produções indexadas: ${data.indexed_productions || 0}</li>
                    </ul>
                </div>
            `;

            if (data.files && data.files.length > 0) {
                reportHtml += `<h6 class="mt-3">Detalhes por Arquivo:</h6><ul class="list-group shadow-sm">`;
                data.files.forEach(file => {
                    let icon, listClass, badge;
                    
                    if (file.status === 'success') {
                        icon = 'bi-check-circle';
                        listClass = 'list-group-item-success';
                        badge = `<span class="badge bg-primary rounded-pill">${file.indexed} produções</span>`;
                    } else if (file.status === 'skipped') {
                        icon = 'bi-info-circle';
                        listClass = 'list-group-item-warning';
                        badge = `<span class="badge bg-warning text-dark rounded-pill">Já atualizado</span>`;
                    } else {
                        icon = 'bi-x-circle';
                        listClass = 'list-group-item-danger';
                        badge = `<span class="badge bg-danger rounded-pill">Erro: ${file.message}</span>`;
                    }
                    
                    reportHtml += `<li class="list-group-item ${listClass} d-flex justify-content-between align-items-center">
                        <div><i class="bi ${icon} me-2"></i><strong>${file.name}</strong> ${file.researcher ? ' - ' + file.researcher : ''}</div>
                        ${badge}
                    </li>`;
                });
                reportHtml += `</ul>`;
            }
            statusDiv.innerHTML = reportHtml;
        })
        .catch(error => {
            statusDiv.innerHTML = `<div class="alert alert-danger"><i class="bi bi-x-circle"></i> <strong>Erro:</strong> ${error.message}</div>`;
        });
    });
    
    
    // Áreas de concentração por PPG (já carregado acima)

    document.getElementById('ppg').addEventListener('change', function() {
        const selectedPPG = this.value;
        const areaSelect = document.getElementById('area');

        areaSelect.innerHTML = '<option value="">Selecione...</option>';

        if (selectedPPG && areas[selectedPPG]) {
            areas[selectedPPG].forEach(area => {
                const option = document.createElement('option');
                option.value = area;
                option.textContent = area;
                areaSelect.appendChild(option);
            });
        }
    });

    // Upload zone
    const uploadZone = document.getElementById('uploadZone');
    const fileInput = document.getElementById('lattes_xml');
    const fileInfo = document.getElementById('fileInfo');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');

    if (uploadZone && fileInput) {
        uploadZone.addEventListener('click', () => fileInput.click());

        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.classList.add('dragover');
        });

        uploadZone.addEventListener('dragleave', () => {
            uploadZone.classList.remove('dragover');
        });

        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('dragover');

            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                updateFileInfo();
            }
        });

        fileInput.addEventListener('change', updateFileInfo);

        function updateFileInfo() {
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                fileName.textContent = file.name;
                fileSize.textContent = formatFileSize(file.size);
                fileInfo.classList.remove('d-none');
            }
        }

        function formatFileSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
        }
    }

    // Form submission com validação
    const uploadFormEl = document.getElementById('uploadForm');
    if (uploadFormEl) {
        uploadFormEl.addEventListener('submit', function(e) {
            // Validar se arquivo foi selecionado
            const fileInput = document.getElementById('lattes_xml');
            if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                e.preventDefault();
                alert('Por favor, selecione um arquivo XML antes de enviar.');
                return false;
            }
            
            const submitBtn = document.getElementById('submitBtn');
            const processingBtn = document.getElementById('processingBtn');
            if (submitBtn) submitBtn.style.display = 'none';
            if (processingBtn) processingBtn.style.display = 'block';
    });
    }

    // ── Mobile drawer ──
    (function () {
        const toggle  = document.getElementById('sidebarToggle');
        const overlay = document.getElementById('drawerOverlay');
        const drawer  = document.getElementById('mobileDrawer');
        const icon    = document.getElementById('sidebarToggleIcon');
        if (!toggle || !overlay || !drawer) return;

        let scrollY = 0;

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

        function openDrawer() {
            drawer.classList.add('open');
            overlay.classList.add('open');
            toggle.setAttribute('aria-expanded', 'true');
            if (icon) { icon.classList.replace('fa-bars', 'fa-times'); }
            lockBackgroundScroll();
        }

        function closeDrawer() {
            drawer.classList.remove('open');
            overlay.classList.remove('open');
            toggle.setAttribute('aria-expanded', 'false');
            if (icon) { icon.classList.replace('fa-times', 'fa-bars'); }
            unlockBackgroundScroll();
        }

        toggle.addEventListener('click', function () {
            drawer.classList.contains('open') ? closeDrawer() : openDrawer();
        });

        overlay.addEventListener('click', closeDrawer);

        // Botões dentro do drawer disparam a aba correspondente no desktop e fecham
        drawer.querySelectorAll('[data-bs-target]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const targetId = btn.getAttribute('data-bs-target'); // e.g. "#pending"
                const desktopBtn = document.querySelector('#adminTabs [data-bs-target="' + targetId + '"]');
                if (desktopBtn) {
                    // Ativa via Bootstrap Tab API
                    const bsTab = bootstrap.Tab.getOrCreateInstance(desktopBtn);
                    bsTab.show();
                    // Sincroniza classe active no drawer
                    drawer.querySelectorAll('.adm-tab-btn').forEach(function (b) { b.classList.remove('active'); });
                    btn.classList.add('active');
                }
                closeDrawer();
                // Rola suavemente pro início do conteúdo
                const main = document.querySelector('.adm-main');
                if (main) main.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });

        // Sincroniza estado active do drawer quando o Bootstrap Tab muda (pelo sidebar desktop)
        document.querySelectorAll('#adminTabs [data-bs-toggle="tab"]').forEach(function (desktopBtn) {
            desktopBtn.addEventListener('shown.bs.tab', function () {
                const target = desktopBtn.getAttribute('data-bs-target');
                drawer.querySelectorAll('.adm-tab-btn').forEach(function (b) {
                    b.classList.toggle('active', b.getAttribute('data-bs-target') === target);
                });
            });
        });
    }());
    </script>
</body>

</html>