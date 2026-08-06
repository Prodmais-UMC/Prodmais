<?php

header('Content-Type: application/json');

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

// Include required services
if (!class_exists('ElasticsearchService')) {
    require_once dirname(__DIR__, 2) . '/src/Infrastructure/Elasticsearch/ElasticsearchService.php';
}

$config = require dirname(__DIR__, 2) . '/config/config.php';

$query = trim(strip_tags((string) filter_input(INPUT_GET, 'q')));
$area = trim(strip_tags((string) filter_input(INPUT_GET, 'area')));
$institution = trim(strip_tags((string) filter_input(INPUT_GET, 'institution')));
$city = trim(strip_tags((string) filter_input(INPUT_GET, 'city')));
$size = filter_input(INPUT_GET, 'size', FILTER_VALIDATE_INT) ?: 20;

try {
    $esService = new ElasticsearchService($config['elasticsearch']);
    
    if ($esService->isFallbackMode()) {
        // Dados de exemplo para pesquisadores
        $fallbackResearchers = [
            [
                'name' => 'Dr. João Silva Santos',
                'lattes_id' => '1234567890123456',
                'production_count' => 15,
                'latest_year' => 2024,
                'institutions' => ['Universidade de Mogi das Cruzes'],
                'areas' => ['Educação', 'Gestão Universitária'],
                'h_index' => 8,
                'citations' => 142,
                'programa_umc' => 'mestrado_educacao'
            ],
            [
                'name' => 'Dra. Maria Oliveira Costa',
                'lattes_id' => '2345678901234567',
                'production_count' => 22,
                'latest_year' => 2024,
                'institutions' => ['Universidade de Mogi das Cruzes'],
                'areas' => ['Direito', 'Direito Constitucional'],
                'h_index' => 12,
                'citations' => 287,
                'programa_umc' => 'mestrado_direito'
            ],
            [
                'name' => 'Dr. Carlos Roberto Lima',
                'lattes_id' => '3456789012345678',
                'production_count' => 18,
                'latest_year' => 2024,
                'institutions' => ['Universidade de Mogi das Cruzes'],
                'areas' => ['Engenharia de Sistemas', 'Machine Learning'],
                'h_index' => 10,
                'citations' => 234,
                'programa_umc' => 'mestrado_engenharia_sistemas'
            ],
            [
                'name' => 'Dra. Ana Paula Ferreira',
                'lattes_id' => '4567890123456789',
                'production_count' => 13,
                'latest_year' => 2023,
                'institutions' => ['Universidade de Mogi das Cruzes'],
                'areas' => ['Psicologia', 'Psicologia Cognitiva'],
                'h_index' => 7,
                'citations' => 156,
                'programa_umc' => 'mestrado_psicologia'
            ]
        ];
        
        // Filtra por consulta se fornecida
        if ($query) {
            $query = strtolower($query);
            $fallbackResearchers = array_filter($fallbackResearchers, function($researcher) use ($query) {
                return strpos(strtolower($researcher['name']), $query) !== false;
            });
        }
        
        // Limita resultado
        $fallbackResearchers = array_slice($fallbackResearchers, 0, $size);
        
        echo json_encode([
            'researchers' => $fallbackResearchers,
            'total' => count($fallbackResearchers),
            '_fallback_mode' => true,
            '_message' => 'Dados de demonstração - Elasticsearch não disponível'
        ]);
        
        exit;
    }
    
    // Usar agregação para agrupar por pesquisador
    $filters = [];
    if ($area) $filters['area'] = $area;
    if ($institution) $filters['institution'] = $institution;
    
    $params = [
        'index' => $config['app']['index_name'],
        'body' => [
            'size' => 0, // Não retorna documentos, apenas agregações
            'query' => [
                'bool' => [
                    'must' => $query ? [
                        'multi_match' => [
                            'query' => $query,
                            'fields' => ['researcher_name', 'institution']
                        ]
                    ] : [
                        'match_all' => new stdClass()
                    ],
                    'filter' => []
                ]
            ],
            'aggs' => [
                'researchers' => [
                    'terms' => [
                        'field' => 'researcher_name.keyword',
                        'size' => $size
                    ],
                    'aggs' => [
                        'latest_year' => [
                            'max' => ['field' => 'year']
                        ],
                        'production_count' => [
                            'value_count' => ['field' => 'id']
                        ],
                        'institutions' => [
                            'terms' => [
                                'field' => 'institution.keyword',
                                'size' => 5
                            ]
                        ],
                        'areas' => [
                            'nested' => [
                                'path' => 'areas'
                            ],
                            'aggs' => [
                                'area_breakdown' => [
                                    'terms' => [
                                        'field' => 'areas.grande_area',
                                        'size' => 5
                                    ]
                                ]
                            ]
                        ],
                        'types' => [
                            'terms' => [
                                'field' => 'type',
                                'size' => 10
                            ]
                        ],
                        'sample_doc' => [
                            'top_hits' => [
                                'size' => 1,
                                '_source' => ['researcher_lattes_id', 'institution', 'city', 'state', 'country']
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ];
    
    // Aplicar filtros
    if ($area) {
        $params['body']['query']['bool']['filter'][] = [
            'nested' => [
                'path' => 'areas',
                'query' => [
                    'bool' => [
                        'should' => [
                            ['match' => ['areas.grande_area' => $area]],
                            ['match' => ['areas.area' => $area]]
                        ]
                    ]
                ]
            ]
        ];
    }
    
    if ($institution) {
        $params['body']['query']['bool']['filter'][] = [
            'match' => ['institution' => $institution]
        ];
    }
    
    if ($city) {
        $params['body']['query']['bool']['filter'][] = [
            'term' => ['city' => $city]
        ];
    }
    
    $response = $esService->getClient()->search($params);
    $result = $response->asArray();
    
    // Formatar resultado
    $researchers = [];
    if (!empty($result['aggregations']['researchers']['buckets'])) {
        foreach ($result['aggregations']['researchers']['buckets'] as $bucket) {
            $sampleDoc = $bucket['sample_doc']['hits']['hits'][0]['_source'] ?? [];
            
            $researchers[] = [
                'name' => $bucket['key'],
                'lattes_id' => $sampleDoc['researcher_lattes_id'] ?? null,
                'production_count' => $bucket['production_count']['value'],
                'latest_year' => $bucket['latest_year']['value'],
                'institutions' => array_map(function($inst) {
                    return $inst['key'];
                }, $bucket['institutions']['buckets']),
                'areas' => array_map(function($area) {
                    return $area['key'];
                }, $bucket['areas']['area_breakdown']['buckets']),
                'production_types' => array_map(function($type) {
                    return [
                        'type' => $type['key'],
                        'count' => $type['doc_count']
                    ];
                }, $bucket['types']['buckets']),
                'location' => [
                    'city' => $sampleDoc['city'] ?? null,
                    'state' => $sampleDoc['state'] ?? null,
                    'country' => $sampleDoc['country'] ?? null
                ]
            ];
        }
    }
    
    echo json_encode([
        'researchers' => $researchers,
        'total' => count($researchers),
        'filters_applied' => array_filter([
            'query' => $query,
            'area' => $area,
            'institution' => $institution,
            'city' => $city
        ])
    ], JSON_PRETTY_PRINT);

} catch (\Exception $e) {
    http_response_code(500);
    error_log($e->getMessage());
    echo json_encode([
        'error' => 'Erro ao buscar pesquisadores',
        'details' => $e->getMessage()
    ]);
}