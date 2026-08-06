<?php

/**
 * API de Validação e Monitoramento UMC
 * 
 * Endpoints para execução e monitoramento de validações
 * conforme especificações do projeto PIVIC UMC 2025
 */

// Configurações básicas
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

// Include required services
if (!class_exists('UmcValidationSystem')) {
    require_once __DIR__ . '/../../src/Domain/Validation/UmcValidationSystem.php';
}
if (!class_exists('LogService')) {
    require_once __DIR__ . '/../../src/Domain/Services/LogService.php';
}

// Headers CORS e JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Tratar OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Configurações
$config = require __DIR__ . '/../../config/config.php';
$umcConfig = require __DIR__ . '/../../config/umc_config.php';

// Inicializar serviços
$validation = new UmcValidationSystem($config);
$logger = new LogService($config);

// Roteamento
$request = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($request, PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

// Extrair ação da URL
$action = $_GET['action'] ?? $pathParts[array_search('validation', $pathParts) + 1] ?? 'status';

try {
    switch ($action) {
        case 'run':
            handleRunValidation($validation, $logger);
            break;
            
        case 'status':
            handleValidationStatus($config);
            break;
            
        case 'report':
            handleValidationReport($config);
            break;
            
        case 'reports':
            handleValidationReports($config);
            break;
            
        case 'monitor':
            handleSystemMonitor($validation);
            break;
            
        case 'health':
            handleHealthCheck($validation);
            break;
            
        case 'metrics':
            handleMetrics($validation);
            break;
            
        case 'feedback':
            handleUserFeedback($config, $logger);
            break;
            
        default:
            http_response_code(404);
            echo json_encode([
                'error' => 'Endpoint não encontrado',
                'available_actions' => [
                    'run', 'status', 'report', 'reports', 
                    'monitor', 'health', 'metrics', 'feedback'
                ]
            ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Erro interno do servidor',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    // Log do erro
    $logger->log('ERROR', 'Validation API Error', [
        'action' => $action,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}

/**
 * Executar validação completa
 */
function handleRunValidation($validation, $logger)
{
    // Verificar se já existe validação em execução
    $lockFile = __DIR__ . '/../../data/validation.lock';
    
    if (file_exists($lockFile)) {
        $lockTime = filemtime($lockFile);
        $timeDiff = time() - $lockTime;
        
        // Se lock tem mais de 1 hora, remover (processo pode ter travado)
        if ($timeDiff > 3600) {
            unlink($lockFile);
        } else {
            http_response_code(409);
            echo json_encode([
                'error' => 'Validação já em execução',
                'started_at' => date('Y-m-d H:i:s', $lockTime),
                'duration' => $timeDiff . ' segundos'
            ]);
            return;
        }
    }
    
    // Criar lock file
    file_put_contents($lockFile, date('Y-m-d H:i:s'));
    
    try {
        // Log início da validação
        $logger->log('INFO', 'Validation Started', [
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
        ]);
        
        // Executar validação
        $result = $validation->runFullValidation();
        
        // Remover lock
        unlink($lockFile);
        
        // Log conclusão
        $logger->log('INFO', 'Validation Completed', [
            'overall_score' => $result['overall_score'],
            'duration' => time() - filemtime($lockFile)
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Validação executada com sucesso',
            'data' => $result
        ]);
        
    } catch (Exception $e) {
        // Remover lock em caso de erro
        if (file_exists($lockFile)) {
            unlink($lockFile);
        }
        throw $e;
    }
}

/**
 * Status da validação atual
 */
function handleValidationStatus($config)
{
    $lockFile = __DIR__ . '/../../data/validation.lock';
    $logsDir = $config['data_paths']['logs'] ?? __DIR__ . '/../../data/logs';
    
    $status = [
        'is_running' => file_exists($lockFile),
        'last_execution' => null,
        'reports_available' => []
    ];
    
    if ($status['is_running']) {
        $status['started_at'] = date('Y-m-d H:i:s', filemtime($lockFile));
        $status['duration'] = time() - filemtime($lockFile);
    }
    
    // Buscar relatórios disponíveis
    if (is_dir($logsDir)) {
        $reports = glob($logsDir . '/validation_report_*.json');
        rsort($reports); // Mais recentes primeiro
        
        foreach (array_slice($reports, 0, 10) as $report) {
            $filename = basename($report);
            $timestamp = filemtime($report);
            
            $status['reports_available'][] = [
                'filename' => $filename,
                'timestamp' => date('Y-m-d H:i:s', $timestamp),
                'size' => filesize($report)
            ];
        }
        
        if (!empty($status['reports_available'])) {
            $status['last_execution'] = $status['reports_available'][0]['timestamp'];
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $status
    ]);
}

/**
 * Buscar relatório específico
 */
function handleValidationReport($config)
{
    $filename = trim(strip_tags((string) filter_input(INPUT_GET, 'filename')));
    $logsDir = $config['data_paths']['logs'] ?? __DIR__ . '/../../data/logs';

    // Só aceita o padrão de nome gerado por este sistema — bloqueia path traversal
    if ($filename !== '' && !preg_match('/^validation_report_[\w.-]+\.json$/', $filename)) {
        http_response_code(400);
        echo json_encode(['error' => 'Nome de arquivo inválido']);
        return;
    }

    if (!$filename) {
        // Buscar relatório mais recente
        $reports = glob($logsDir . '/validation_report_*.json');
        if (empty($reports)) {
            http_response_code(404);
            echo json_encode(['error' => 'Nenhum relatório encontrado']);
            return;
        }
        rsort($reports);
        $filepath = $reports[0];
    } else {
        $filepath = $logsDir . '/' . basename($filename);
    }
    
    if (!file_exists($filepath)) {
        http_response_code(404);
        echo json_encode(['error' => 'Relatório não encontrado']);
        return;
    }
    
    $reportData = json_decode(file_get_contents($filepath), true);
    
    if (!$reportData) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao ler relatório']);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $reportData
    ]);
}

/**
 * Listar todos os relatórios
 */
function handleValidationReports($config)
{
    $logsDir = $config['data_paths']['logs'] ?? __DIR__ . '/../../data/logs';
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(50, max(1, intval($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;
    
    $reports = [];
    
    if (is_dir($logsDir)) {
        $files = glob($logsDir . '/validation_report_*.json');
        rsort($files); // Mais recentes primeiro
        
        $total = count($files);
        $pageFiles = array_slice($files, $offset, $limit);
        
        foreach ($pageFiles as $file) {
            $filename = basename($file);
            $timestamp = filemtime($file);
            $size = filesize($file);
            
            // Tentar ler score do relatório
            $score = null;
            try {
                $content = json_decode(file_get_contents($file), true);
                $score = $content['overall_score'] ?? null;
            } catch (Exception $e) {
                // Ignorar erro de leitura
            }
            
            $reports[] = [
                'filename' => $filename,
                'timestamp' => date('Y-m-d H:i:s', $timestamp),
                'size' => $size,
                'overall_score' => $score
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'reports' => $reports,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit)
                ]
            ]
        ]);
        
    } else {
        echo json_encode([
            'success' => true,
            'data' => [
                'reports' => [],
                'pagination' => [
                    'page' => 1,
                    'limit' => $limit,
                    'total' => 0,
                    'total_pages' => 0
                ]
            ]
        ]);
    }
}

/**
 * Monitor do sistema em tempo real
 */
function handleSystemMonitor($validation)
{
    $monitors = [
        'timestamp' => date('Y-m-d H:i:s'),
        'server_health' => getServerHealth(),
        'elasticsearch_status' => getElasticsearchStatus(),
        'disk_usage' => getDiskUsage(),
        'memory_usage' => getMemoryUsage(),
        'active_sessions' => getActiveSessions(),
        'error_rate' => getErrorRate()
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $monitors
    ]);
}

/**
 * Health check básico
 */
function handleHealthCheck($validation)
{
    $health = [
        'status' => 'healthy',
        'timestamp' => date('Y-m-d H:i:s'),
        'services' => [
            'php' => version_compare(PHP_VERSION, '8.0.0', '>='),
            'elasticsearch' => testElasticsearchConnection(),
            'filesystem' => is_writable(__DIR__ . '/../../data'),
            'memory' => memory_get_usage() < (1024 * 1024 * 512) // 512MB
        ]
    ];
    
    // Verificar se todos os serviços estão OK
    $allHealthy = !in_array(false, $health['services'], true);
    
    if (!$allHealthy) {
        $health['status'] = 'unhealthy';
        http_response_code(503);
    }
    
    echo json_encode([
        'success' => $allHealthy,
        'data' => $health
    ]);
}

/**
 * Métricas do sistema
 */
function handleMetrics($validation)
{
    $metrics = [
        'timestamp' => date('Y-m-d H:i:s'),
        'system' => [
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize')
        ],
        'performance' => [
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'load_average' => getLoadAverage(),
            'uptime' => getSystemUptime()
        ],
        'elasticsearch' => getElasticsearchMetrics(),
        'application' => getApplicationMetrics()
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $metrics
    ]);
}

/**
 * Feedback dos usuários
 */
function handleUserFeedback($config, $logger)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Método não permitido']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Dados inválidos']);
        return;
    }
    
    $feedback = [
        'timestamp' => date('Y-m-d H:i:s'),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        'type' => $input['type'] ?? 'general',
        'rating' => intval($input['rating'] ?? 0),
        'message' => substr($input['message'] ?? '', 0, 1000),
        'module' => $input['module'] ?? 'system',
        'page' => $input['page'] ?? 'unknown'
    ];
    
    // Validar dados obrigatórios
    if (empty($feedback['message'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Mensagem é obrigatória']);
        return;
    }
    
    // Salvar feedback
    $feedbackFile = ($config['data_paths']['logs'] ?? __DIR__ . '/../../data/logs') . '/user_feedback.json';
    
    $allFeedback = [];
    if (file_exists($feedbackFile)) {
        $allFeedback = json_decode(file_get_contents($feedbackFile), true) ?? [];
    }
    
    $allFeedback[] = $feedback;
    
    // Manter apenas últimos 1000 feedbacks
    if (count($allFeedback) > 1000) {
        $allFeedback = array_slice($allFeedback, -1000);
    }
    
    file_put_contents($feedbackFile, json_encode($allFeedback, JSON_PRETTY_PRINT));
    
    // Log do feedback
    $logger->log('INFO', 'User Feedback Received', $feedback);
    
    echo json_encode([
        'success' => true,
        'message' => 'Feedback registrado com sucesso'
    ]);
}

// Funções auxiliares
function getServerHealth()
{
    return [
        'cpu_load' => sys_getloadavg()[0] ?? 0,
        'memory_usage' => memory_get_usage(true),
        'disk_free' => disk_free_space('.'),
        'uptime' => getSystemUptime()
    ];
}

function getElasticsearchStatus()
{
    try {
        // Implementação básica - expandir conforme necessário
        return ['status' => 'unknown', 'reason' => 'Not implemented'];
    } catch (Exception $e) {
        return ['status' => 'error', 'reason' => $e->getMessage()];
    }
}

function getDiskUsage()
{
    $totalBytes = disk_total_space('.');
    $freeBytes = disk_free_space('.');
    $usedBytes = $totalBytes - $freeBytes;
    
    return [
        'total' => $totalBytes,
        'used' => $usedBytes,
        'free' => $freeBytes,
        'usage_percentage' => round(($usedBytes / $totalBytes) * 100, 2)
    ];
}

function getMemoryUsage()
{
    return [
        'current' => memory_get_usage(true),
        'peak' => memory_get_peak_usage(true),
        'limit' => ini_get('memory_limit')
    ];
}

function getActiveSessions()
{
    // Implementação básica - expandir conforme necessário
    return ['count' => 0, 'method' => 'Not implemented'];
}

function getErrorRate()
{
    // Implementação básica - analisar logs de erro
    return ['rate' => 0, 'method' => 'Not implemented'];
}

function testElasticsearchConnection()
{
    try {
        // Implementação básica
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function getLoadAverage()
{
    $load = sys_getloadavg();
    return $load ? $load[0] : 0;
}

function getSystemUptime()
{
    if (PHP_OS_FAMILY === 'Windows') {
        return 'Unknown (Windows)';
    }
    
    $uptime = shell_exec('uptime -p');
    return trim($uptime) ?: 'Unknown';
}

function getElasticsearchMetrics()
{
    return [
        'status' => 'unknown',
        'nodes' => 0,
        'indices' => 0,
        'documents' => 0
    ];
}

function getApplicationMetrics()
{
    return [
        'total_uploads' => 0,
        'total_searches' => 0,
        'active_users' => 0,
        'data_size' => 0
    ];
}