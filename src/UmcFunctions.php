<?php
/**
 * PRODMAIS UMC - Funções Principais
 * Integração UNIFESP + UMC
 * Conformidade LGPD e CAPES
 */

if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* Load libraries for PHP composer */
require(__DIR__ . '/../vendor/autoload.php');

/* Load configuration */
require(__DIR__ . '/../config/config_umc.php');

/* Load UMC classes */
require_once(__DIR__ . '/Infrastructure/Elasticsearch/ElasticsearchService.php');
require_once(__DIR__ . '/Domain/Importers/LattesParser.php');
require_once(__DIR__ . '/Infrastructure/External/OrcidFetcher.php');
require_once(__DIR__ . '/Infrastructure/External/OpenAlexFetcher.php');
require_once(__DIR__ . '/Domain/Services/ExportService.php');
require_once(__DIR__ . '/Domain/Services/LogService.php');
require_once(__DIR__ . '/Core/Anonymizer.php');
require_once(__DIR__ . '/Core/HookManager.php');
require_once(__DIR__ . '/Core/PluginLoader.php');

// Carregar Camada de Plugins
PluginLoader::loadPlugins();

// Carregar camada relacional
require_once(__DIR__ . '/Infrastructure/Database/DatabaseService.php');

/* Load Elasticsearch Client */
use OpenSearch\ClientBuilder;

/**
 * Criar cliente Elasticsearch
 */
function getElasticsearchClient() {
    global $hosts, $elasticsearch_user, $elasticsearch_password;
    
    try {
        // Timeout maior que o padrão — em CPU limitada (ex: Render Free,
        // 0.1 vCPU) o handshake TLS com um cluster remoto pode facilmente
        // passar de 2-3s, o que fazia o cliente reportar "no alive nodes"
        // mesmo com o cluster saudável.
        $connectionParams = [
            'client' => [
                'curl' => [
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_CONNECTTIMEOUT => 8,
                ],
            ],
        ];
        $caBundle = __DIR__ . '/http_ca.crt';

        if (isset($elasticsearch_user) && !empty($elasticsearch_user)) {
            $builder = ClientBuilder::create()
                ->setHosts($hosts)
                ->setBasicAuthentication($elasticsearch_user, $elasticsearch_password)
                ->setConnectionParams($connectionParams);

            // CA bundle customizado só existe no Elasticsearch local do
            // Docker Compose (certificado autoassinado). Em produção
            // (ex: AWS OpenSearch) o certificado já é público e válido,
            // então usamos o bundle de CA padrão do sistema.
            if (file_exists($caBundle)) {
                $builder->setSSLVerification($caBundle);
            }

            $client = $builder->build();
        } else {
            $client = ClientBuilder::create()
                ->setHosts($hosts)
                ->setConnectionParams($connectionParams)
                ->build();
        }
        return $client;
    } catch (Exception $e) {
        error_log("Erro ao conectar no Elasticsearch: " . $e->getMessage());
        return null;
    }
}

/**
 * Verificar e criar índices do Elasticsearch
 */
function initializeElasticsearchIndexes() {
    global $index, $index_cv, $index_ppg, $index_projetos;
    
    $client = getElasticsearchClient();
    if (!$client) {
        return false;
    }
    
    $indexes = [
        $index => 'produções científicas',
        $index_cv => 'currículos Lattes',
        $index_ppg => 'programas de pós-graduação',
        $index_projetos => 'projetos de pesquisa',
        'qualis' => 'classificação Qualis CAPES',
        'openalexcitedworks' => 'citações OpenAlex'
    ];
    
    foreach ($indexes as $idx => $description) {
        try {
            $indexParams['index'] = $idx;
            $exists = $client->indices()->exists($indexParams);
            
            if (!$exists) {
                createIndex($idx, $client, $description);
                applyMappings($idx, $client);
            }
        } catch (Exception $e) {
            error_log("Erro ao verificar índice $idx: " . $e->getMessage());
        }
    }
    
    return true;
}

/**
 * Criar índice no Elasticsearch
 */
function createIndex($indexName, $client, $description = '') {
    try {
        $params = [
            'index' => $indexName,
            'body' => [
                'settings' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 0,
                    'analysis' => [
                        'analyzer' => [
                            'brazilian' => [
                                'type' => 'standard',
                                'stopwords' => '_brazilian_'
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        $client->indices()->create($params);
        error_log("Índice $indexName criado com sucesso" . ($description ? " ($description)" : ""));
        return true;
    } catch (Exception $e) {
        error_log("Erro ao criar índice $indexName: " . $e->getMessage());
        return false;
    }
}

/**
 * Aplicar mappings ao índice
 */
function applyMappings($indexName, $client) {
    // Mappings específicos por tipo de índice
    $mappings = [];
    
    // Sub-campo .keyword pra permitir sort/filtro exato em cima de um
    // campo text — sem isso, sort/term query em "<campo>.keyword" falha
    // com "No mapping found" mesmo o campo existindo como texto.
    $keywordSubfield = ['keyword' => ['type' => 'keyword', 'ignore_above' => 256]];

    if (strpos($indexName, '_cv') !== false) {
        // Mappings para currículos
        $mappings = [
            'properties' => [
                'nome_completo' => ['type' => 'text', 'analyzer' => 'brazilian', 'fields' => $keywordSubfield],
                'lattesID' => ['type' => 'keyword'],
                'orcidID' => ['type' => 'keyword'],
                'email' => ['type' => 'keyword'],
                'instituicao' => ['type' => 'text', 'fields' => $keywordSubfield],
                'ppg' => ['type' => 'keyword'],
                'area_concentracao' => ['type' => 'keyword'],
                'campus' => ['type' => 'keyword'],
                'producoes' => ['type' => 'nested'],
                'projetos' => ['type' => 'nested']
            ]
        ];
    } elseif (strpos($indexName, '_ppg') !== false) {
        // Mappings para PPGs
        $mappings = [
            'properties' => [
                'nome' => ['type' => 'text', 'fields' => $keywordSubfield],
                'codigo_capes' => ['type' => 'keyword'],
                'nivel' => ['type' => 'keyword'],
                'campus' => ['type' => 'keyword'],
                'areas_concentracao' => ['type' => 'keyword'],
                'docentes' => ['type' => 'nested'],
                'producoes_totais' => ['type' => 'integer']
            ]
        ];
    } elseif (strpos($indexName, '_projetos') !== false) {
        // Mappings para projetos
        $mappings = [
            'properties' => [
                'titulo' => ['type' => 'text', 'analyzer' => 'brazilian', 'fields' => $keywordSubfield],
                'ano_inicio' => ['type' => 'integer'],
                'ano_fim' => ['type' => 'integer'],
                'situacao' => ['type' => 'keyword'],
                'financiamento' => ['type' => 'text', 'fields' => $keywordSubfield],
                'equipe' => ['type' => 'nested'],
                'ppg' => ['type' => 'keyword']
            ]
        ];
    } else {
        // Mappings para produções científicas
        $mappings = [
            'properties' => [
                'titulo' => ['type' => 'text', 'analyzer' => 'brazilian', 'fields' => $keywordSubfield],
                'autores' => ['type' => 'text', 'analyzer' => 'brazilian', 'fields' => $keywordSubfield],
                'ano' => ['type' => 'integer'],
                'tipo' => ['type' => 'keyword'],
                'doi' => ['type' => 'keyword'],
                'issn' => ['type' => 'keyword'],
                'qualis' => ['type' => 'keyword'],
                'ppg' => ['type' => 'keyword'],
                'area_concentracao' => ['type' => 'keyword'],
                'idioma' => ['type' => 'keyword'],
                'citacoes' => ['type' => 'integer'],
                'openalex_id' => ['type' => 'keyword']
            ]
        ];
    }
    
    if (!empty($mappings)) {
        try {
            $params = [
                'index' => $indexName,
                'body' => $mappings
            ];
            $client->indices()->putMapping($params);
            return true;
        } catch (Exception $e) {
            error_log("Erro ao aplicar mappings no índice $indexName: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Classe para busca multi-índice (UNIFESP style)
 */
class MultiIndexSearch {
    private $client;
    
    public function __construct() {
        $this->client = getElasticsearchClient();
    }
    
    /**
     * Busca em múltiplos índices
     */
    public function search($query, $indexes = null) {
        global $index, $index_cv, $index_projetos;
        
        if ($indexes === null) {
            $indexes = [$index, $index_cv, $index_projetos];
        }
        
        $results = [];
        
        foreach ($indexes as $idx) {
            try {
                $params = [
                    'index' => $idx,
                    'body' => [
                        'query' => [
                            'query_string' => [
                                'query' => $query,
                                'default_operator' => 'OR'
                            ]
                        ],
                        'size' => 50
                    ]
                ];
                
                $response = $this->client->search($params);
                $results[$idx] = [
                    'total' => $response['hits']['total']['value'] ?? 0,
                    'hits' => $response['hits']['hits'] ?? []
                ];
            } catch (Exception $e) {
                $results[$idx] = ['total' => 0, 'hits' => []];
            }
        }
        
        return $results;
    }
    
    /**
     * Contar resultados por índice
     */
    public function count($query, $index) {
        try {
            $params = [
                'index' => $index,
                'body' => [
                    'query' => [
                        'query_string' => [
                            'query' => $query
                        ]
                    ]
                ]
            ];
            
            $response = $this->client->count($params);
            return $response['count'] ?? 0;
        } catch (Exception $e) {
            return 0;
        }
    }
}

/**
 * Classe para processamento de requisições (UNIFESP style)
 */
class RequestProcessor {
    
    /**
     * Processar POST de busca
     */
    public static function parseSearchPost($post) {
        $search = $post['search'] ?? '';
        $page = isset($post['page']) ? (int)$post['page'] : 1;
        $limit = isset($post['limit']) ? (int)$post['limit'] : 50;
        
        // Filtros
        $filters = [];
        
        if (isset($post['ppg']) && !empty($post['ppg'])) {
            $filters['ppg'] = $post['ppg'];
        }
        
        if (isset($post['area_concentracao']) && !empty($post['area_concentracao'])) {
            $filters['area_concentracao'] = $post['area_concentracao'];
        }
        
        if (isset($post['ano_inicio']) && !empty($post['ano_inicio'])) {
            $filters['ano_inicio'] = (int)$post['ano_inicio'];
        }
        
        if (isset($post['ano_fim']) && !empty($post['ano_fim'])) {
            $filters['ano_fim'] = (int)$post['ano_fim'];
        }
        
        if (isset($post['tipo']) && !empty($post['tipo'])) {
            $filters['tipo'] = $post['tipo'];
        }
        
        if (isset($post['qualis']) && !empty($post['qualis'])) {
            $filters['qualis'] = $post['qualis'];
        }
        
        // Construir query Elasticsearch
        $query = [
            'query' => [
                'bool' => [
                    'must' => [
                        [
                            'query_string' => [
                                'query' => $search,
                                'default_operator' => 'OR'
                            ]
                        ]
                    ]
                ]
            ],
            'from' => ($page - 1) * $limit,
            'size' => $limit,
            'sort' => [
                ['ano' => ['order' => 'desc']],
                ['_score' => ['order' => 'desc']]
            ]
        ];
        
        // Aplicar filtros
        foreach ($filters as $field => $value) {
            if ($field === 'ano_inicio' || $field === 'ano_fim') {
                $query['query']['bool']['filter'][] = [
                    'range' => [
                        'ano' => [
                            'gte' => $filters['ano_inicio'] ?? 1900,
                            'lte' => $filters['ano_fim'] ?? date('Y')
                        ]
                    ]
                ];
            } else {
                $query['query']['bool']['filter'][] = [
                    'term' => [$field => $value]
                ];
            }
        }
        
        return [
            'query' => $query,
            'page' => $page,
            'limit' => $limit,
            'filters' => $filters
        ];
    }
}

/**
 * Função auxiliar para obter PPG por código CAPES
 */
function getPPGByCodigo($codigo_capes) {
    global $ppgs_umc;
    foreach ($ppgs_umc as $ppg) {
        if ($ppg['codigo_capes'] === $codigo_capes) {
            return $ppg;
        }
    }
    return null;
}

/**
 * Função auxiliar para listar todos os PPGs
 */
function getAllPPGs() {
    global $ppgs_umc;
    return $ppgs_umc;
}

/**
 * Função auxiliar para verificar se está em modo de desenvolvimento
 */
function isDebugMode() {
    global $debug_mode;
    return $debug_mode ?? false;
}


/**
 * Inicializar Elasticsearch e banco relacional na primeira execução
 */
if (php_sapi_name() !== 'cli') {
    initializeElasticsearchIndexes();
    // Inicializa banco relacional (SQLite por padrão)
    try {
        $dbService = new DatabaseService($config ?? []);
    } catch (Exception $e) {
        error_log('Erro ao inicializar banco relacional: ' . $e->getMessage());
    }
}

/**
 * Exibe uma página de acesso negado (403) e encerra a execução.
 * Usar no lugar de redirecionar silenciosamente quando o papel do
 * usuário não tem permissão para a página atual.
 */
function exibirAcessoNegado(string $mensagem = 'Você não tem permissão para acessar esta página.'): void {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>403 - Acesso negado - PRODMAIS UMC</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="/css/prodmais-elegant.css?v=5">
        <link rel="stylesheet" href="/css/umc-theme.css">
    </head>
    <body>
        <?php \App\View\Components\Navbar\Navbar::display(['active_page' => '']); ?>
        <section style="min-height:60vh;display:flex;align-items:center;justify-content:center;padding:4rem 1.5rem;">
            <div style="text-align:center;max-width:420px;">
                <div style="width:72px;height:72px;border-radius:20px;background:rgba(239,68,68,.1);display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;">
                    <i class="fas fa-lock" style="font-size:1.75rem;color:#dc2626;" aria-hidden="true"></i>
                </div>
                <h1 style="font-size:2.25rem;font-weight:800;color:#0f172a;margin-bottom:.5rem;">403</h1>
                <p style="color:#64748b;margin-bottom:1.75rem;"><?php echo htmlspecialchars($mensagem); ?></p>
                <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap;">
                    <button type="button" onclick="window.location.reload()" class="btn-outline-ds btn-outline-ds--sm">
                        <i class="fas fa-rotate-right" aria-hidden="true"></i> Atualizar
                    </button>
                    <a href="/index_umc.php" class="btn-outline-ds btn-outline-ds--sm">
                        <i class="fas fa-house" aria-hidden="true"></i> Voltar ao início
                    </a>
                </div>
            </div>
        </section>
    </body>
    </html>
    <?php
    exit;
}

/**
 * Papel do usuário logado, respeitando o modo de pré-visualização.
 * Só contas com `conta_sistema` (marcadas no banco, ex: desenvolvedor) podem
 * ter `papel_preview` na sessão — usuários comuns sempre recebem o papel real.
 */
function papelEfetivo(): string {
    $papelReal = $_SESSION['papel'] ?? '';
    if (!empty($_SESSION['conta_sistema']) && !empty($_SESSION['papel_preview'])) {
        return $_SESSION['papel_preview'];
    }
    return $papelReal;
}

/**
 * Renderiza badge de usuário logado após o Navbar.
 * Injeta JS que modifica o botão "Área Admin" dinamicamente.
 */
function renderNavbarAuthBadge(): void {
    if (session_status() === PHP_SESSION_NONE) {
        return;
    }
    $user_id  = $_SESSION['user_id']       ?? null;
    $username = htmlspecialchars($_SESSION['username']      ?? '', ENT_QUOTES);
    $nome     = htmlspecialchars($_SESSION['nome_completo'] ?? $username, ENT_QUOTES);
    $papel    = papelEfetivo();

    if (!$user_id) {
        return;
    }

    $admin_href = in_array($papel, ['admin', 'pesquisador']) ? '/admin.php' : '/dashboard.php';
    echo <<<HTML
<script>
(function(){
  document.addEventListener('DOMContentLoaded', function() {
    var btn = document.querySelector('.nav-cta-admin');
    if (!btn) return;
    btn.href = '{$admin_href}';
    btn.innerHTML = '<i class="fas fa-user-circle" aria-hidden="true"></i> {$nome}';
    btn.title = 'Logado como {$username}';
    btn.insertAdjacentHTML('afterend',
      '<a href="/logout.php" class="nav-cta-admin" style="margin-left:.375rem;background:rgba(239,68,68,.15);color:#fca5a5;border-color:rgba(239,68,68,.3);" title="Sair"><i class=\"fas fa-sign-out-alt\"></i></a>'
    );
  });
})();
</script>
HTML;
}

/**
 * Log de acesso (LGPD)
 */
if (($lgpd_log_access ?? false) && class_exists('LogService')) {
    try {
        $logService = new LogService();
        $logService->logAction($_SERVER['REMOTE_ADDR'] ?? 'unknown', 'access:' . ($_SERVER['REQUEST_URI'] ?? '/'));
    } catch (Exception $e) {
        // Silenciar erro se LogService não estiver disponível
    }
}
