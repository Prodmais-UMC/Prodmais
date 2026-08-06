<?php
/**
 * PRODMAIS UMC - Autocomplete de nomes de pesquisadores
 * GET /api/autocomplete.php?q=<termo>&limit=<n>
 */

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/src/UmcFunctions.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

$termo  = trim((string) filter_input(INPUT_GET, 'q', FILTER_SANITIZE_SPECIAL_CHARS));
$limite = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT) ?: 8;
$limite = min(max($limite, 1), 15);

if (mb_strlen($termo) < 2) {
    echo json_encode(['sugestoes' => []]);
    exit;
}

$sugestoes = [];
$client = getElasticsearchClient();

if ($client !== null) {
    try {
        $params = [
            'index' => 'prodmais_umc_cv',
            'body'  => [
                'size'    => $limite,
                '_source' => ['nome_completo', 'instituicao'],
                'query'   => [
                    'match_phrase_prefix' => [
                        'nome_completo' => [
                            'query'          => $termo,
                            'max_expansions' => 25,
                        ],
                    ],
                ],
            ],
        ];
        $response = $client->search($params);

        $vistos = [];
        foreach ($response['hits']['hits'] as $hit) {
            $nome = $hit['_source']['nome_completo'] ?? null;
            if (!$nome || isset($vistos[$nome])) {
                continue;
            }
            $vistos[$nome] = true;
            $sugestoes[] = [
                'nome'        => $nome,
                'instituicao' => $hit['_source']['instituicao'] ?? null,
            ];
        }
    } catch (Exception $e) {
        error_log('Erro no autocomplete de pesquisadores: ' . $e->getMessage());
    }
}

echo json_encode(['sugestoes' => $sugestoes]);
