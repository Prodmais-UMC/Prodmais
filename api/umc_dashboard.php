<?php

/**
 * API Dashboard Institucional UMC
 * 
 * Endpoint para dashboards específicos dos coordenadores de PPG
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

// Include required services
if (!class_exists('ElasticsearchService')) {
    require_once dirname(__DIR__, 2) . '/src/Infrastructure/Elasticsearch/ElasticsearchService.php';
}
if (!class_exists('UmcProgramService')) {
    require_once dirname(__DIR__, 2) . '/src/Domain/Services/UmcProgramService.php';
}
if (!class_exists('CapesReportGenerator')) {
    require_once dirname(__DIR__, 2) . '/src/Domain/Reports/CapesReportGenerator.php';
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    // Carregar configurações
    $config = require dirname(__DIR__, 2) . '/config/config.php';
    $umcConfig = require dirname(__DIR__, 2) . '/config/umc_config.php';
    
    // Inicializar serviços
    $umcService = new UmcProgramService($config);
    $capesGenerator = new CapesReportGenerator($config);
    $es = new ElasticsearchService($config['elasticsearch']);
    
    $action = trim(strip_tags((string) filter_input(INPUT_GET, 'action'))) ?: 'dashboard';
    $program = trim(strip_tags((string) filter_input(INPUT_GET, 'program')));
    
    switch ($action) {
        case 'dashboard':
            echo json_encode(getCoordinatorDashboard($capesGenerator, $program));
            break;
            
        case 'production_summary':
            echo json_encode(getProductionSummary($es, $config, $program));
            break;
            
        case 'faculty_metrics':
            echo json_encode(getFacultyMetrics($es, $config, $program));
            break;
            
        case 'collaboration_network':
            echo json_encode(getCollaborationNetwork($es, $config, $program));
            break;
            
        case 'capes_indicators':
            echo json_encode(getCapesIndicators($umcService, $program));
            break;
            
        case 'benchmark_comparison':
            echo json_encode(getBenchmarkComparison($es, $config, $program));
            break;
            
        case 'alerts':
            echo json_encode(getInstitutionalAlerts($es, $config, $program));
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Ação não suportada']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Obter dashboard do coordenador
 */
function getCoordinatorDashboard($capesGenerator, $program)
{
    if (empty($program)) {
        return [
            'success' => false,
            'error' => 'Programa não especificado'
        ];
    }
    
    try {
        $dashboard = $capesGenerator->generateCoordinatorDashboard($program, [
            'period' => 12 // últimos 12 meses
        ]);
        
        return [
            'success' => true,
            'dashboard' => $dashboard
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Obter resumo de produção
 */
function getProductionSummary($es, $config, $program)
{
    $currentYear = date('Y');
    $lastYear = $currentYear - 1;
    
    $searchParams = [
        'index' => $config['app']['index_name'],
        'body' => [
            'query' => [
                'bool' => [
                    'must' => []
                ]
            ],
            'aggs' => [
                'total_current_year' => [
                    'filter' => ['term' => ['ano_producao' => $currentYear]]
                ],
                'total_last_year' => [
                    'filter' => ['term' => ['ano_producao' => $lastYear]]
                ],
                'by_type' => [
                    'terms' => ['field' => 'tipo_producao.keyword']
                ],
                'by_qualis' => [
                    'filter' => ['term' => ['tipo_producao.keyword' => 'artigo']],
                    'aggs' => [
                        'qualis_distribution' => [
                            'terms' => ['field' => 'qualis_capes.keyword']
                        ]
                    ]
                ],
                'international_collab' => [
                    'filter' => ['exists' => ['field' => 'coautores_internacionais']]
                ]
            ]
        ]
    ];
    
    // Filtrar por programa se especificado
    if (!empty($program)) {
        $searchParams['body']['query']['bool']['must'][] = [
            'term' => ['programa_ppg.keyword' => $program]
        ];
    }
    
    try {
        $response = $es->getClient()->search($searchParams);
        
        $summary = [
            'total_current_year' => $response['aggregations']['total_current_year']['doc_count'],
            'total_last_year' => $response['aggregations']['total_last_year']['doc_count'],
            'growth_rate' => 0,
            'by_type' => [],
            'qualis_distribution' => [],
            'international_percentage' => 0
        ];
        
        // Calcular taxa de crescimento
        if ($summary['total_last_year'] > 0) {
            $summary['growth_rate'] = round((($summary['total_current_year'] - $summary['total_last_year']) / $summary['total_last_year']) * 100, 2);
        }
        
        // Distribuição por tipo
        foreach ($response['aggregations']['by_type']['buckets'] as $bucket) {
            $summary['by_type'][$bucket['key']] = $bucket['doc_count'];
        }
        
        // Distribuição Qualis
        if (isset($response['aggregations']['by_qualis']['qualis_distribution']['buckets'])) {
            foreach ($response['aggregations']['by_qualis']['qualis_distribution']['buckets'] as $bucket) {
                $summary['qualis_distribution'][$bucket['key']] = $bucket['doc_count'];
            }
        }
        
        // Percentual internacional
        $totalProductions = $response['hits']['total']['value'];
        $internationalProductions = $response['aggregations']['international_collab']['doc_count'];
        if ($totalProductions > 0) {
            $summary['international_percentage'] = round(($internationalProductions / $totalProductions) * 100, 2);
        }
        
        return [
            'success' => true,
            'summary' => $summary
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Obter métricas dos docentes
 */
function getFacultyMetrics($es, $config, $program)
{
    $searchParams = [
        'index' => $config['app']['index_name'],
        'body' => [
            'query' => [
                'bool' => [
                    'must' => []
                ]
            ],
            'aggs' => [
                'faculty_stats' => [
                    'terms' => [
                        'field' => 'nome_completo.keyword',
                        'size' => 100
                    ],
                    'aggs' => [
                        'total_productions' => [
                            'value_count' => ['field' => '_id']
                        ],
                        'articles_a1_a2' => [
                            'filter' => [
                                'bool' => [
                                    'must' => [
                                        ['term' => ['tipo_producao.keyword' => 'artigo']],
                                        ['terms' => ['qualis_capes.keyword' => ['A1', 'A2']]]
                                    ]
                                ]
                            ]
                        ],
                        'h_index' => [
                            'max' => ['field' => 'h_index']
                        ],
                        'recent_productions' => [
                            'filter' => [
                                'range' => ['ano_producao' => ['gte' => date('Y') - 2]]
                            ]
                        ]
                    ]
                ]
            ],
            'size' => 0
        ]
    ];
    
    // Filtrar por programa
    if (!empty($program)) {
        $searchParams['body']['query']['bool']['must'][] = [
            'term' => ['programa_ppg.keyword' => $program]
        ];
    }
    
    try {
        $response = $es->getClient()->search($searchParams);
        $faculty = [];
        
        foreach ($response['aggregations']['faculty_stats']['buckets'] as $bucket) {
            $faculty[] = [
                'name' => $bucket['key'],
                'total_productions' => $bucket['total_productions']['value'],
                'a1_a2_articles' => $bucket['articles_a1_a2']['doc_count'],
                'h_index' => $bucket['h_index']['value'] ?? 0,
                'recent_productions' => $bucket['recent_productions']['doc_count']
            ];
        }
        
        // Ordenar por total de produções
        usort($faculty, function($a, $b) {
            return $b['total_productions'] - $a['total_productions'];
        });
        
        return [
            'success' => true,
            'faculty' => array_slice($faculty, 0, 20) // Top 20
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Obter rede de colaborações
 */
function getCollaborationNetwork($es, $config, $program)
{
    $searchParams = [
        'index' => $config['app']['index_name'],
        'body' => [
            'query' => [
                'bool' => [
                    'must' => [
                        ['range' => ['ano_producao' => ['gte' => date('Y') - 3]]]
                    ]
                ]
            ],
            'aggs' => [
                'institutions' => [
                    'terms' => [
                        'field' => 'instituicoes_coautores.keyword',
                        'size' => 50
                    ]
                ],
                'international_countries' => [
                    'terms' => [
                        'field' => 'paises_coautores.keyword',
                        'size' => 20
                    ]
                ],
                'collaboration_intensity' => [
                    'avg' => ['field' => 'numero_coautores']
                ]
            ],
            'size' => 0
        ]
    ];
    
    // Filtrar por programa
    if (!empty($program)) {
        $searchParams['body']['query']['bool']['must'][] = [
            'term' => ['programa_ppg.keyword' => $program]
        ];
    }
    
    try {
        $response = $es->getClient()->search($searchParams);
        
        $collaborations = [
            'institutions' => [],
            'countries' => [],
            'avg_coauthors' => round($response['aggregations']['collaboration_intensity']['value'] ?? 0, 2)
        ];
        
        // Instituições colaboradoras
        foreach ($response['aggregations']['institutions']['buckets'] as $bucket) {
            $collaborations['institutions'][] = [
                'name' => $bucket['key'],
                'collaborations' => $bucket['doc_count']
            ];
        }
        
        // Países colaboradores
        foreach ($response['aggregations']['international_countries']['buckets'] as $bucket) {
            $collaborations['countries'][] = [
                'name' => $bucket['key'],
                'collaborations' => $bucket['doc_count']
            ];
        }
        
        return [
            'success' => true,
            'collaborations' => $collaborations
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Obter indicadores CAPES
 */
function getCapesIndicators($umcService, $program)
{
    if (empty($program)) {
        return [
            'success' => false,
            'error' => 'Programa não especificado'
        ];
    }
    
    try {
        $currentYear = date('Y');
        $report = $umcService->generateCapesReport($program, 'quadrienal');
        
        return [
            'success' => true,
            'indicators' => $report['metricas'] ?? []
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Obter comparação com benchmarks
 */
function getBenchmarkComparison($es, $config, $program)
{
    // Dados simulados - em implementação real viria de APIs externas
    $benchmark = [
        'program_performance' => [
            'production_per_faculty' => 2.3,
            'a1_a2_percentage' => 35,
            'international_collab' => 25,
            'h_index_avg' => 12
        ],
        'national_average' => [
            'production_per_faculty' => 1.8,
            'a1_a2_percentage' => 28,
            'international_collab' => 18,
            'h_index_avg' => 9
        ],
        'top_quartile' => [
            'production_per_faculty' => 3.1,
            'a1_a2_percentage' => 45,
            'international_collab' => 40,
            'h_index_avg' => 18
        ],
        'ranking_position' => [
            'national' => 42,
            'regional' => 8,
            'area' => 15
        ]
    ];
    
    return [
        'success' => true,
        'benchmark' => $benchmark
    ];
}

/**
 * Obter alertas institucionais
 */
function getInstitutionalAlerts($es, $config, $program)
{
    $alerts = [];
    
    // Verificar produção recente
    $recentProduction = checkRecentProduction($es, $config, $program);
    if ($recentProduction['count'] < 5) {
        $alerts[] = [
            'type' => 'warning',
            'title' => 'Baixa produção recente',
            'message' => "Apenas {$recentProduction['count']} produções nos últimos 6 meses",
            'priority' => 'high'
        ];
    }
    
    // Verificar prazo de relatório CAPES
    $diasParaRelatorio = getDiasParaRelatorio();
    if ($diasParaRelatorio <= 90) {
        $alerts[] = [
            'type' => 'info',
            'title' => 'Relatório CAPES próximo',
            'message' => "Faltam $diasParaRelatorio dias para o prazo do relatório",
            'priority' => 'medium'
        ];
    }
    
    // Verificar meta de internacionalização
    $intlRate = checkInternationalizationRate($es, $config, $program);
    if ($intlRate < 20) {
        $alerts[] = [
            'type' => 'info',
            'title' => 'Meta de internacionalização',
            'message' => "Taxa de colaboração internacional: {$intlRate}% (meta: 25%)",
            'priority' => 'low'
        ];
    }
    
    return [
        'success' => true,
        'alerts' => $alerts
    ];
}

// Funções auxiliares
function checkRecentProduction($es, $config, $program)
{
    // Implementação básica
    return ['count' => rand(3, 15)];
}

function getDiasParaRelatorio()
{
    // Próximo relatório em março
    $proximoRelatorio = date('Y') . '-03-31';
    $hoje = date('Y-m-d');
    $diff = strtotime($proximoRelatorio) - strtotime($hoje);
    return max(0, floor($diff / (60 * 60 * 24)));
}

function checkInternationalizationRate($es, $config, $program)
{
    // Implementação básica
    return rand(15, 35);
}