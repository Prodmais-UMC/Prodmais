<?php
/**
 * API de Upload e Indexação em Lote
 * Utiliza o ProdmaisUMC\LattesImporter para indexação completa
 */

// Limpeza de buffer para evitar carateres extras no JSON
ob_start();

// Aumenta limites para arquivos grandes (importante para Lattes extensos)
ini_set('memory_limit', '1024M');
set_time_limit(1800); // 30 minutos

// Desativa exibição de erros no output (vão para o log do servidor)
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

try {
    // Autenticação e checagem de papel — apenas admin e pesquisador podem importar/indexar em lote
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['user_id']) && empty($_SESSION['user'])) {
        ob_end_clean();
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Não autenticado. Faça login para importar currículos.']);
        exit;
    }
    require_once dirname(__DIR__, 2) . '/src/UmcFunctions.php';
    if (!in_array(papelEfetivo(), ['admin', 'pesquisador'], true)) {
        ob_end_clean();
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Acesso negado. Apenas administradores e pesquisadores podem importar currículos.']);
        exit;
    }

    require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
    
    // Carrega o Importador se necessário
    if (!class_exists('\ProdmaisUMC\LattesImporter')) {
        require_once dirname(__DIR__, 2) . '/src/Domain/Importers/LattesImporter.php';
    }

    // --- Validação ---
    if (empty($_FILES['lattes_files']['name'][0])) {
        throw new Exception('Nenhum arquivo foi enviado.');
    }

    $importer = new \ProdmaisUMC\LattesImporter();
    $uploadDir = dirname(__DIR__, 2) . '/data/uploads/';
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $results = [
        'status' => 'success',
        'processed_files' => 0,
        'indexed_productions' => 0,
        'files' => []
    ];

    // --- Processamento dos arquivos ---
    for ($i = 0; $i < count($_FILES['lattes_files']['name']); $i++) {
        $fileName = $_FILES['lattes_files']['name'][$i];
        $tmpName = $_FILES['lattes_files']['tmp_name'][$i];
        $fileError = $_FILES['lattes_files']['error'][$i];

        if ($fileError !== UPLOAD_ERR_OK) {
            $results['files'][] = [
                'name' => $fileName, 
                'status' => 'error', 
                'message' => "Erro no upload (code: {$fileError})"
            ];
            continue;
        }

        $destination = $uploadDir . basename($fileName);
        if (!move_uploaded_file($tmpName, $destination)) {
            $results['files'][] = [
                'name' => $fileName, 
                'status' => 'error', 
                'message' => "Erro ao salvar arquivo temporário."
            ];
            continue;
        }

        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        try {
            if ($extension === 'xml') {
                // Importação robusta (Pesquisador + Produções + Projetos)
                $importResult = $importer->importFromXML($destination);
                
                if (isset($importResult['status']) && $importResult['status'] === 'skipped') {
                    $results['files'][] = [
                        'name' => $fileName, 
                        'status' => 'skipped', 
                        'message' => $importResult['message'] ?? 'Já estava atualizado.',
                        'researcher' => $importResult['pesquisador_nome'] ?? 'Desconhecido'
                    ];
                } else {
                    $results['indexed_productions'] += ($importResult['total_producoes'] ?? 0);
                    $results['files'][] = [
                        'name' => $fileName, 
                        'status' => 'success', 
                        'indexed' => $importResult['total_producoes'] ?? 0,
                        'researcher' => $importResult['pesquisador_nome'] ?? 'Desconhecido'
                    ];
                    $results['processed_files']++;
                }
            } else {
                $results['files'][] = [
                    'name' => $fileName, 
                    'status' => 'error', 
                    'message' => 'Formato não suportado. Apenas .xml é aceito.'
                ];
            }
        } catch (\Exception $e) {
            error_log("Lattes Import Error [{$fileName}]: " . $e->getMessage());
            $results['files'][] = [
                'name' => $fileName, 
                'status' => 'error', 
                'message' => $e->getMessage()
            ];
        }
    }

    // Limpa qualquer saída acidental do buffer antes de enviar o JSON
    ob_end_clean();
    echo json_encode($results);

} catch (\Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro crítico: ' . $e->getMessage()
    ]);
}