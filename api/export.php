<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

// Include required services
if (!class_exists('ElasticsearchService')) {
    require_once dirname(__DIR__, 2) . '/src/Infrastructure/Elasticsearch/ElasticsearchService.php';
}
if (!class_exists('ExportService')) {
    require_once dirname(__DIR__, 2) . '/src/Domain/Services/ExportService.php';
}

$config = require dirname(__DIR__, 2) . '/config/config.php';

// Validar formato
$format = trim(strip_tags((string) filter_input(INPUT_GET, 'format')));
$allowedFormats = ['bibtex', 'ris', 'csv', 'json', 'xml'];

if (!$format || !in_array($format, $allowedFormats)) {
    http_response_code(400);
    echo json_encode(['error' => 'Formato inválido. Formatos permitidos: ' . implode(', ', $allowedFormats)]);
    exit;
}

// Recuperar filtros da query string
$filters = [];
$filterFields = ['q', 'type', 'subtype', 'year', 'year_from', 'year_to', 'institution', 'language', 'area', 'author'];

foreach ($filterFields as $field) {
    $value = trim(strip_tags((string) filter_input(INPUT_GET, $field)));
    if ($value !== null && $value !== '') {
        if ($field === 'year' || $field === 'year_from' || $field === 'year_to') {
            $filters[$field] = filter_var($value, FILTER_VALIDATE_INT);
        } else {
            $filters[$field] = $value;
        }
    }
}

// Tamanho máximo para exportação
$maxSize = 1000;
$size = filter_input(INPUT_GET, 'size', FILTER_VALIDATE_INT) ?: 100;
$size = min($size, $maxSize);

try {
    $esService = new ElasticsearchService($config['elasticsearch']);
    $exportService = new ExportService();
    
    // Buscar dados
    $results = $esService->search($config['app']['index_name'], $filters, $size);
    
    // Verifica se é resposta do Elasticsearch ou modo fallback
    if (is_array($results)) {
        $data = $results; // Modo fallback já retorna array
    } else {
        $data = $results->asArray(); // Resposta normal do Elasticsearch
    }
    
    if (empty($data['hits']['hits'])) {
        http_response_code(404);
        echo json_encode(['error' => 'Nenhum resultado encontrado para os filtros especificados']);
        exit;
    }
    
    // Extrair documentos
    $productions = [];
    foreach ($data['hits']['hits'] as $hit) {
        $productions[] = $hit['_source'];
    }
    
    // Gerar conteúdo baseado no formato
    $content = '';
    $contentType = 'text/plain';
    $extension = $format;
    
    switch ($format) {
        case 'bibtex':
            $content = $exportService->exportBibTeX($productions);
            $contentType = 'application/x-bibtex';
            $extension = 'bib';
            break;
            
        case 'ris':
            $content = $exportService->exportRIS($productions);
            $contentType = 'application/x-research-info-systems';
            break;
            
        case 'csv':
            $content = $exportService->exportCSV($productions);
            $contentType = 'text/csv';
            break;
            
        case 'json':
            $content = $exportService->exportJSON($productions);
            $contentType = 'application/json';
            break;
            
        case 'xml':
            $content = $exportService->exportXML($productions);
            $contentType = 'application/xml';
            break;
    }
    
    // Configurar headers para download
    $filename = 'prodmais_export_' . date('Y-m-d_H-i-s') . '.' . $extension;
    
    header("Content-Type: {$contentType}; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header('Content-Length: ' . strlen($content));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Para CSV, adicionar BOM para compatibilidade com Excel
    if ($format === 'csv') {
        echo "\xEF\xBB\xBF";
    }
    
    echo $content;

} catch (\Exception $e) {
    http_response_code(500);
    error_log($e->getMessage());
    echo json_encode([
        'error' => 'Erro ao exportar dados',
        'details' => $e->getMessage()
    ]);
}