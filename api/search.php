<?php

header('Content-Type: application/json');

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

// Include required services
if (!class_exists('ElasticsearchService')) {
    require_once dirname(__DIR__, 2) . '/src/Infrastructure/Elasticsearch/ElasticsearchService.php';
}

$config = require dirname(__DIR__, 2) . '/config/config.php';

// Sanitiza os inputs expandidos
$program = trim(strip_tags((string) filter_input(INPUT_GET, 'program')));
$type = trim(strip_tags((string) filter_input(INPUT_GET, 'type')));
$subtype = trim(strip_tags((string) filter_input(INPUT_GET, 'subtype')));
$year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT);
$year_from = filter_input(INPUT_GET, 'year_from', FILTER_VALIDATE_INT);
$year_to = filter_input(INPUT_GET, 'year_to', FILTER_VALIDATE_INT);
$query = trim(strip_tags((string) filter_input(INPUT_GET, 'q')));
$institution = trim(strip_tags((string) filter_input(INPUT_GET, 'institution')));
$language = trim(strip_tags((string) filter_input(INPUT_GET, 'language')));
$area = trim(strip_tags((string) filter_input(INPUT_GET, 'area')));
$author = trim(strip_tags((string) filter_input(INPUT_GET, 'author')));
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$size = filter_input(INPUT_GET, 'size', FILTER_VALIDATE_INT) ?: 50;

// Máximo de 100 resultados por página
$size = min($size, 100);

$filters = array_filter([
    'program' => $program,
    'type' => $type,
    'subtype' => $subtype,
    'year' => $year,
    'year_from' => $year_from,
    'year_to' => $year_to,
    'q' => $query,
    'institution' => $institution,
    'language' => $language,
    'area' => $area,
    'author' => $author
]);

try {
    $esService = new ElasticsearchService($config['elasticsearch']);
    
    // Buscar resultados
    $results = $esService->search($config['app']['index_name'], $filters, $size);
    
    // Se requisitado, buscar agregações/estatísticas
    $includeStats = filter_input(INPUT_GET, 'include_stats', FILTER_VALIDATE_BOOLEAN);
    
    // Verifica se é resposta do Elasticsearch ou modo fallback
    if (is_array($results)) {
        $response = $results; // Modo fallback já retorna array
    } else {
        $response = $results->asArray(); // Resposta normal do Elasticsearch
    }
    
    if ($includeStats && !$esService->isFallbackMode()) {
        $aggregations = $esService->getAggregations($config['app']['index_name'], $filters);
        $response['aggregations'] = $aggregations['aggregations'] ?? [];
    }
    
    // Adicionar informações de paginação
    $total = $response['hits']['total']['value'] ?? 0;
    $response['pagination'] = [
        'page' => $page,
        'size' => $size,
        'total' => $total,
        'total_pages' => $total > 0 ? ceil($total / $size) : 0
    ];

    echo json_encode($response, JSON_PRETTY_PRINT);

} catch (\Exception $e) {
    http_response_code(500);
    error_log($e->getMessage()); 
    echo json_encode([
        'error' => 'Erro ao realizar a busca.',
        'details' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}