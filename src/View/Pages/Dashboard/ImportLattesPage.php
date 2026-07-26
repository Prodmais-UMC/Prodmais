<?php

/**
 * PRODMAIS UMC - Interface para Importação de Currículos Lattes
 */

require_once __DIR__ . '/../../../../config/config_umc.php';
require_once __DIR__ . '/../../../../src/UmcFunctions.php';
require_once __DIR__ . '/../../../../src/Domain/Importers/LattesImporter.php';

use App\View\Components\Navbar\Navbar;
use App\View\Components\Footer\Footer;

// Importação de currículos é restrita a usuários autenticados com papel admin ou pesquisador
if (empty($_SESSION['user_id']) && empty($_SESSION['user'])) {
    header('Location: /login.php');
    exit;
}
if (!in_array($_SESSION['papel'] ?? '', ['admin', 'pesquisador'], true)) {
    header('Location: /dashboard.php');
    exit;
}

$message = '';
$error = '';
$result = null;

// Processar upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['lattes_xml'])) {
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

        // Importar
        $ppg = $_POST['ppg'] ?? null;
        $area = $_POST['area'] ?? null;

        $importer = new \ProdmaisUMC\LattesImporter();
        $result = $importer->importFromXML($filepath, $ppg, $area);

        $message = "✅ Currículo importado com sucesso!";
    } catch (Exception $e) {
        $error = $e->getMessage();
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
    <title>Importar Currículo Lattes - PRODMAIS UMC</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="/css/umc-theme.css" rel="stylesheet">
    <link href="/css/prodmais-elegant.css?v=5" rel="stylesheet">

    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }

        .upload-zone {
            border: 2px dashed rgba(99,102,241,.35);
            border-radius: 18px;
            padding: 3rem 2rem;
            text-align: center;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            background: rgba(99,102,241,.03);
            position: relative;
            overflow: hidden;
        }
        .upload-zone:hover {
            border-color: #6366f1;
            background: rgba(99,102,241,.07);
            transform: translateY(-3px);
            box-shadow: 0 12px 32px rgba(99,102,241,.15);
        }
        .upload-zone.dragover {
            border-color: #4f46e5;
            background: rgba(99,102,241,.1);
            transform: scale(1.01);
        }
        .upload-zone i { color: #6366f1; font-size: 3.5rem; margin-bottom: 1.25rem; }
        .upload-zone h5 { font-weight: 700; color: #1e293b; margin-bottom: 0.75rem; }
        .upload-zone p  { color: #64748b; font-size: 0.938rem; }

        .result-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 2px 14px rgba(0,0,0,.07);
            border: 1px solid rgba(0,0,0,.07);
            margin-bottom: 20px;
        }
        .result-header {
            border-bottom: 3px solid #6366f1;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .result-header h4 { color: #312e81; font-weight: 700; margin: 0; }
        .result-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .stat-item {
            background: linear-gradient(135deg,#ede9fe,#ddd6fe);
            padding: 20px;
            border-radius: 14px;
            transition: transform .2s ease;
        }
        .stat-item:hover { transform: translateY(-3px); }
        .stat-item .stat-icon { font-size: 2rem; color: #4f46e5; margin-bottom: 10px; }
        .stat-item .stat-number { font-size: 2rem; font-weight: 800; color: #312e81; margin: 0; }
        .stat-item .stat-label  { color: #4338ca; font-size: 0.9rem; margin: 0; }

        .page-header {
            background: #070d1f;
            background-image:
                radial-gradient(ellipse 60% 70% at 15% 65%, rgba(99,102,241,.13), transparent),
                radial-gradient(ellipse 40% 40% at 88% 12%, rgba(139,92,246,.10), transparent),
                radial-gradient(ellipse 30% 30% at 55% 88%, rgba(79,70,229,.08), transparent);
            color: white;
            padding: 5.5rem 0 3.5rem;
            position: relative;
            overflow: hidden;
        }
        .page-header::before {
            content: '';
            position: absolute; inset: 0;
            background-image: radial-gradient(rgba(255,255,255,.05) 1px, transparent 1px);
            background-size: 28px 28px;
            pointer-events: none;
        }
        .page-header h1 { font-weight: 900; margin-bottom: 1rem; font-size: clamp(1.6rem, 5vw, 3.25rem); letter-spacing: -0.02em; line-height: 1.15; position: relative; z-index: 1; }
        .page-header .lead { font-size: 1.05rem; opacity: .7; font-weight: 400; position: relative; z-index: 1; }

        .card-umc-custom {
            background: white;
            border-radius: 20px;
            box-shadow: 0 2px 12px rgba(0,0,0,.06);
            border: 1px solid rgba(0,0,0,.07);
            overflow: hidden;
        }
        .card-header-umc {
            background: linear-gradient(135deg,#1e1b4b,#312e81);
            color: white;
            padding: 1.25rem 1.75rem;
            border: none;
        }
        .card-header-umc h4 { margin: 0; font-weight: 700; font-size: 1rem; }
        .card-umc {
            background: white;
            border-radius: 20px;
            box-shadow: 0 2px 12px rgba(0,0,0,.06);
            border: 1px solid rgba(0,0,0,.07);
        }
        .btn-umc-primary {
            background: linear-gradient(135deg,#4f46e5,#6366f1);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 12px;
            font-weight: 700;
            transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
            box-shadow: 0 4px 14px rgba(79,70,229,.3);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        .btn-umc-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(79,70,229,.4); color: white; }
        .btn-umc-outline {
            background: white;
            color: #4f46e5;
            border: 2px solid #4f46e5;
            padding: 0.75rem 2rem;
            border-radius: 12px;
            font-weight: 700;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        .btn-umc-outline:hover { background: #4f46e5; color: white; transform: translateY(-2px); }
    </style>
</head>

<body>

<?php Navbar::display(['active_page' => 'importar', 'mostrar_link_dashboard' => $mostrar_link_dashboard ?? true]); ?>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1><i class="fas fa-file-upload me-3"></i>Importação de Currículos Lattes</h1>
            <p class="lead mb-0">Indexe currículos completos da Plataforma Lattes no sistema PRODMAIS UMC</p>
        </div>
    </div>

    <div class="container my-5">
        <div class="row">
            <div class="col-md-10 mx-auto">

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                            <div>
                                <h5 class="mb-1">Erro na Importação</h5>
                                <p class="mb-0"><?= htmlspecialchars($error) ?></p>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($result): ?>
                    <div class="result-card">
                        <div class="result-header">
                            <h4>
                                <i class="fas fa-check-circle" style="color: #28a745;"></i>
                                Importação Concluída com Sucesso!
                            </h4>
                        </div>

                        <?php if (isset($result['pesquisador_nome'])): ?>
                            <div class="mb-4">
                                <div class="d-flex align-items-center">
                                    <div class="me-3" style="font-size: 3rem; color: #6366f1;">
                                        <i class="fas fa-user-graduate"></i>
                                    </div>
                                    <div>
                                        <h5 class="mb-1" style="color: #312e81; font-weight: 600;">
                                            <?= htmlspecialchars($result['pesquisador_nome']) ?>
                                        </h5>
                                        <p class="text-muted mb-0">Currículo Lattes indexado no sistema</p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="result-stats">
                            <?php if (isset($result['total_producoes']) && $result['total_producoes'] > 0): ?>
                                <div class="stat-item">
                                    <div class="stat-icon">
                                        <i class="fas fa-file-alt"></i>
                                    </div>
                                    <p class="stat-number"><?= $result['total_producoes'] ?></p>
                                    <p class="stat-label">Produções Indexadas</p>
                                </div>
                            <?php endif; ?>

                            <?php if (isset($result['artigos']) && $result['artigos'] > 0): ?>
                                <div class="stat-item">
                                    <div class="stat-icon">
                                        <i class="fas fa-newspaper"></i>
                                    </div>
                                    <p class="stat-number"><?= $result['artigos'] ?></p>
                                    <p class="stat-label">Artigos Publicados</p>
                                </div>
                            <?php endif; ?>

                            <?php if (isset($result['livros']) && $result['livros'] > 0): ?>
                                <div class="stat-item">
                                    <div class="stat-icon">
                                        <i class="fas fa-book"></i>
                                    </div>
                                    <p class="stat-number"><?= $result['livros'] ?></p>
                                    <p class="stat-label">Livros</p>
                                </div>
                            <?php endif; ?>

                            <?php if (isset($result['capitulos']) && $result['capitulos'] > 0): ?>
                                <div class="stat-item">
                                    <div class="stat-icon">
                                        <i class="fas fa-book-open"></i>
                                    </div>
                                    <p class="stat-number"><?= $result['capitulos'] ?></p>
                                    <p class="stat-label">Capítulos de Livro</p>
                                </div>
                            <?php endif; ?>

                            <?php if (isset($result['eventos']) && $result['eventos'] > 0): ?>
                                <div class="stat-item">
                                    <div class="stat-icon">
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                    <p class="stat-number"><?= $result['eventos'] ?></p>
                                    <p class="stat-label">Trabalhos em Eventos</p>
                                </div>
                            <?php endif; ?>

                            <?php if (isset($result['projetos']) && $result['projetos'] > 0): ?>
                                <div class="stat-item">
                                    <div class="stat-icon">
                                        <i class="fas fa-project-diagram"></i>
                                    </div>
                                    <p class="stat-number"><?= $result['projetos'] ?></p>
                                    <p class="stat-label">Projetos de Pesquisa</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mt-4 text-center">
                            <a href="pesquisadores.php" class="btn-umc-primary me-2">
                                <i class="fas fa-users me-2"></i>Ver Pesquisadores
                            </a>
                            <a href="importar_lattes.php" class="btn-umc-outline">
                                <i class="fas fa-plus me-2"></i>Importar Outro Currículo
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card-umc-custom">
                    <div class="card-header-umc">
                        <h4>
                            <i class="fas fa-file-upload me-2"></i>
                            Importar Novo Currículo Lattes
                        </h4>
                    </div>
                    <div class="card-body p-4">

                        <div class="mb-4">
                            <div class="alert" style="background: linear-gradient(135deg, #e3f2fd 0%, #f0f7ff 100%); border: 1px solid rgba(99,102,241,.2); border-radius: 8px;">
                                <h6 style="color: #312e81; font-weight: 600;">
                                    <i class="fas fa-info-circle me-2"></i>Como exportar seu currículo Lattes
                                </h6>
                                <ol style="margin-bottom: 0; padding-left: 20px;">
                                    <li>Acesse a <a href="http://lattes.cnpq.br/" target="_blank" style="color: #4f46e5; font-weight: 500;">Plataforma Lattes <i class="fas fa-external-link-alt fa-xs"></i></a></li>
                                    <li>Faça login e acesse seu currículo completo</li>
                                    <li>No menu, clique em <strong>"Exportar currículo"</strong> ou <strong>"Baixar XML"</strong></li>
                                    <li>Salve o arquivo XML no seu computador</li>
                                    <li>Faça o upload do arquivo no formulário abaixo</li>
                                </ol>
                            </div>

                            <div class="alert alert-warning">
                                <i class="fas fa-hourglass-half me-2"></i>
                                <strong>Currículos extensos:</strong> O sistema processa currículos com milhares de publicações (até 50MB). O processamento pode levar alguns minutos, aguarde a conclusão.
                            </div>
                        </div>

                        <form method="POST" enctype="multipart/form-data" id="uploadForm">
                            <div class="mb-3">
                                <label for="ppg" class="form-label">Programa de Pós-Graduação *</label>
                                <select class="form-select" id="ppg" name="ppg" required>
                                    <option value="">Selecione o PPG</option>
                                    <?php foreach ($ppgs as $ppg): ?>
                                        <option value="<?= htmlspecialchars($ppg['nome']) ?>">
                                            <?= htmlspecialchars($ppg['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="area" class="form-label">Área de Concentração (opcional)</label>
                                <select class="form-select" id="area" name="area">
                                    <option value="">Selecione...</option>
                                </select>
                                <small class="text-muted">Selecione primeiro o PPG</small>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Arquivo XML do Lattes *</label>
                                <div class="upload-zone" id="uploadZone">
                                    <i class="bi bi-cloud-upload fs-1 text-muted"></i>
                                    <p class="mb-2">
                                        <strong>Arraste o arquivo XML aqui</strong><br>
                                        ou clique para selecionar
                                    </p>
                                    <input type="file"
                                        class="d-none"
                                        id="lattes_xml"
                                        name="lattes_xml"
                                        accept=".xml">
                                    <small class="text-muted">Tamanho máximo: 50MB</small>
                                </div>
                                <div id="fileInfo" class="mt-2 d-none">
                                    <div class="alert alert-info mb-0">
                                        <i class="bi bi-file-earmark-check"></i>
                                        <strong>Arquivo selecionado:</strong>
                                        <span id="fileName"></span>
                                        <span class="badge bg-secondary" id="fileSize"></span>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn-umc-primary btn-lg" id="submitBtn" style="padding: 15px;">
                                    <i class="fas fa-cloud-upload-alt me-2"></i>
                                    Importar Currículo Lattes
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-lg" id="processingBtn" style="display: none; padding: 15px;" disabled>
                                    <span class="spinner-border spinner-border-sm me-2"></span>
                                    Processando... Isso pode levar alguns minutos
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Card de ajuda -->
                <div class="card-umc mt-4">
                    <div class="card-body">
                        <h5 style="color: #312e81; font-weight: 600; margin-bottom: 20px;">
                            <i class="fas fa-question-circle me-2"></i>Dúvidas Frequentes
                        </h5>
                        <div class="accordion accordion-flush" id="faqAccordion">
                            <div class="accordion-item" style="border: none; border-bottom: 1px solid #e0e0e0;">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq1"
                                        style="color: #312e81; font-weight: 500;">
                                        <i class="fas fa-file-export me-2" style="color: #6366f1;"></i>
                                        Como exportar meu currículo da Plataforma Lattes?
                                    </button>
                                </h2>
                                <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        No menu do seu currículo Lattes, procure pela opção <strong>"Exportar currículo"</strong> ou <strong>"Baixar XML"</strong>. O arquivo baixado terá extensão <code>.xml</code> e conterá todas as informações do seu currículo acadêmico.
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item" style="border: none; border-bottom: 1px solid #e0e0e0;">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2"
                                        style="color: #312e81; font-weight: 500;">
                                        <i class="fas fa-database me-2" style="color: #6366f1;"></i>
                                        Meu currículo é muito grande, vai funcionar?
                                    </button>
                                </h2>
                                <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Sim! O sistema foi otimizado para processar currículos extensos com milhares de publicações (até 50MB). O processamento pode levar alguns minutos, mas funciona perfeitamente. Aguarde até aparecer a mensagem de conclusão.
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item" style="border: none; border-bottom: 1px solid #e0e0e0;">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3"
                                        style="color: #312e81; font-weight: 500;">
                                        <i class="fas fa-cogs me-2" style="color: #6366f1;"></i>
                                        O que acontece após a importação?
                                    </button>
                                </h2>
                                <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Seu currículo, produções científicas e projetos de pesquisa serão indexados no Elasticsearch e ficarão disponíveis para consulta em todo o sistema. Você poderá visualizar no dashboard, nas buscas e nos relatórios.
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item" style="border: none;">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4"
                                        style="color: #312e81; font-weight: 500;">
                                        <i class="fas fa-sync-alt me-2" style="color: #6366f1;"></i>
                                        Posso atualizar um currículo já importado?
                                    </button>
                                </h2>
                                <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Sim! Basta importar novamente o XML atualizado do mesmo pesquisador. O sistema irá sobrescrever os dados antigos com as informações mais recentes do currículo Lattes.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php Footer::display(); ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Áreas de concentração por PPG
        const areas = <?= json_encode(array_column($ppgs, 'areas_concentracao', 'nome')) ?>;

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

        // Form submission
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            // Validar se o arquivo foi selecionado
            if (!fileInput.files || fileInput.files.length === 0) {
                e.preventDefault();
                alert('Por favor, selecione um arquivo XML do currículo Lattes.');
                return false;
            }
            
            // Validar se é XML
            const file = fileInput.files[0];
            if (!file.name.toLowerCase().endsWith('.xml')) {
                e.preventDefault();
                alert('Por favor, selecione um arquivo XML válido.');
                return false;
            }
            
            document.getElementById('submitBtn').style.display = 'none';
            document.getElementById('processingBtn').style.display = 'block';
        });
    </script>
</body>

</html>