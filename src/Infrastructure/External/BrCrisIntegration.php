<?php

/**
 * Integração com a Plataforma BrCris
 * 
 * Serviço para interoperabilidade com repositórios nacionais brasileiros
 * conforme especificações do projeto PIVIC UMC 2025
 */

class BrCrisIntegration
{
    private $config;
    private $apiUrl;
    private $institutionId;
    private $apiKey;
    private $timeout;

    public function __construct($config)
    {
        $this->config = $config;
        $this->apiUrl = $config['integrations']['brcris']['api_url'] ?? 'https://brcris.ibict.br/api';
        $this->institutionId = $config['integrations']['brcris']['institution_id'] ?? 'UMC';
        $this->apiKey = $config['integrations']['brcris']['api_key'] ?? '';
        $this->timeout = 30;
    }

    /**
     * A integração com o BrCris exige credencial emitida pela IBICT após
     * cadastro institucional formal — isso é um processo administrativo,
     * não uma configuração de código. Sem a chave, não há como autenticar
     * nenhuma chamada real à API.
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }
    
    /**
     * Sincronizar dados da UMC com BrCris
     */
    public function syncInstitutionData($programData)
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'synced_records' => 0,
                'errors' => ['BrCris não configurado — falta a credencial de API emitida pela IBICT após o cadastro institucional. Isso é uma etapa administrativa, não de código.'],
                'warnings' => []
            ];
        }

        $syncResults = [
            'success' => true,
            'synced_records' => 0,
            'errors' => [],
            'warnings' => []
        ];

        try {
            // Preparar dados no formato BrCris
            $brcrisData = $this->prepareBrCrisData($programData);
            
            // Enviar dados para BrCris
            foreach ($brcrisData as $record) {
                try {
                    $response = $this->sendToBrCris($record);
                    if ($response['success']) {
                        $syncResults['synced_records']++;
                    } else {
                        $syncResults['errors'][] = $response['error'];
                    }
                } catch (Exception $e) {
                    $syncResults['errors'][] = "Erro ao enviar registro: " . $e->getMessage();
                }
            }
            
            // Log da sincronização
            $this->logSyncActivity($syncResults);
            
        } catch (Exception $e) {
            $syncResults['success'] = false;
            $syncResults['errors'][] = $e->getMessage();
        }
        
        return $syncResults;
    }
    
    /**
     * Preparar dados no formato BrCris
     */
    private function prepareBrCrisData($programData)
    {
        $brcrisRecords = [];
        
        foreach ($programData as $record) {
            // Mapear campos para o padrão BrCris/DublinCore
            $brcrisRecord = [
                'dc:title' => $record['titulo'] ?? '',
                'dc:creator' => $this->formatAuthors($record['autores'] ?? []),
                'dc:date' => $record['ano_producao'] ?? '',
                'dc:type' => $this->mapProductionType($record['tipo_producao'] ?? ''),
                'dc:identifier' => $record['doi'] ?? $record['isbn'] ?? '',
                'dc:publisher' => $record['editora'] ?? $record['revista'] ?? '',
                'dc:subject' => $record['palavras_chave'] ?? [],
                'dc:language' => $record['idioma'] ?? 'pt',
                'dc:source' => 'Universidade de Mogi das Cruzes',
                'dc:relation' => $record['programa_ppg'] ?? '',
                'brcris:institution' => $this->institutionId,
                'brcris:program' => $record['programa_ppg'] ?? '',
                'brcris:research_line' => $record['linha_pesquisa'] ?? '',
                'brcris:qualis' => $record['qualis_capes'] ?? '',
                'brcris:indexation' => $record['indexacao'] ?? []
            ];
            
            // Adicionar metadados específicos se disponíveis
            if (isset($record['resumo'])) {
                $brcrisRecord['dc:description'] = $record['resumo'];
            }
            
            if (isset($record['url_acesso'])) {
                $brcrisRecord['dc:identifier.uri'] = $record['url_acesso'];
            }
            
            // Metadados de proveniência
            $brcrisRecord['dc:provenance'] = [
                'source' => 'Plataforma Lattes',
                'extraction_date' => date('Y-m-d H:i:s'),
                'lattes_id' => $record['id_lattes'] ?? ''
            ];
            
            $brcrisRecords[] = $brcrisRecord;
        }
        
        return $brcrisRecords;
    }
    
    /**
     * Enviar dados para BrCris
     */
    private function sendToBrCris($record)
    {
        $endpoint = $this->apiUrl . '/records';
        
        $postData = json_encode($record);
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/json',
                    'User-Agent: Prodmais-UMC/1.0',
                    'X-Institution-ID: ' . $this->institutionId,
                    'Authorization: Bearer ' . $this->apiKey
                ],
                'content' => $postData,
                'timeout' => $this->timeout
            ]
        ]);
        
        $response = file_get_contents($endpoint, false, $context);
        
        if ($response === false) {
            throw new Exception("Falha na comunicação com BrCris");
        }
        
        $result = json_decode($response, true);
        
        return [
            'success' => isset($result['id']),
            'id' => $result['id'] ?? null,
            'error' => $result['error'] ?? null
        ];
    }
    
    /**
     * Buscar dados da UMC no BrCris
     */
    public function searchInstitutionData($filters = [])
    {
        $endpoint = $this->apiUrl . '/search';
        
        $params = [
            'institution' => $this->institutionId,
            'format' => 'json'
        ];
        
        // Aplicar filtros
        if (!empty($filters['program'])) {
            $params['program'] = $filters['program'];
        }
        
        if (!empty($filters['year'])) {
            $params['year'] = $filters['year'];
        }
        
        if (!empty($filters['type'])) {
            $params['type'] = $filters['type'];
        }
        
        $url = $endpoint . '?' . http_build_query($params);
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Accept: application/json',
                    'User-Agent: Prodmais-UMC/1.0'
                ],
                'timeout' => $this->timeout
            ]
        ]);
        
        try {
            $response = file_get_contents($url, false, $context);
            
            if ($response === false) {
                throw new Exception("Falha na busca no BrCris");
            }
            
            $data = json_decode($response, true);
            
            return [
                'success' => true,
                'records' => $data['records'] ?? [],
                'total' => $data['total'] ?? 0
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'records' => [],
                'total' => 0
            ];
        }
    }
    
    /**
     * Validar dados antes do envio
     */
    public function validateRecord($record)
    {
        $validation = [
            'valid' => true,
            'errors' => [],
            'warnings' => []
        ];
        
        // Campos obrigatórios
        $required = ['dc:title', 'dc:creator', 'dc:date', 'dc:type'];
        
        foreach ($required as $field) {
            if (empty($record[$field])) {
                $validation['errors'][] = "Campo obrigatório ausente: $field";
                $validation['valid'] = false;
            }
        }
        
        // Validações específicas
        if (!empty($record['dc:date']) && !preg_match('/^\d{4}$/', $record['dc:date'])) {
            $validation['warnings'][] = "Formato de ano inválido: " . $record['dc:date'];
        }
        
        if (!empty($record['dc:identifier']) && 
            !filter_var($record['dc:identifier'], FILTER_VALIDATE_URL) &&
            !preg_match('/^10\.\d{4,}\//', $record['dc:identifier'])) {
            $validation['warnings'][] = "Identificador pode não ser válido: " . $record['dc:identifier'];
        }
        
        return $validation;
    }
    
    /**
     * Obter estatísticas de sincronização
     */
    public function getSyncStats()
    {
        // Buscar logs de sincronização
        $logFile = $this->config['data_paths']['logs'] ?? __DIR__ . '/../../data/logs.sqlite';
        
        try {
            $pdo = new PDO("sqlite:$logFile");
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmt = $pdo->query("
                SELECT 
                    DATE(timestamp) as date,
                    COUNT(*) as total_syncs,
                    SUM(CASE WHEN message LIKE '%success%' THEN 1 ELSE 0 END) as successful_syncs,
                    SUM(CASE WHEN message LIKE '%error%' THEN 1 ELSE 0 END) as failed_syncs
                FROM logs 
                WHERE context LIKE '%brcris%' 
                AND timestamp >= date('now', '-30 days')
                GROUP BY DATE(timestamp)
                ORDER BY date DESC
            ");
            
            $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'stats' => $stats
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'stats' => []
            ];
        }
    }
    
    /**
     * Formatar autores para BrCris
     */
    private function formatAuthors($authors)
    {
        if (is_string($authors)) {
            return $authors;
        }
        
        if (is_array($authors)) {
            return implode('; ', $authors);
        }
        
        return '';
    }
    
    /**
     * Mapear tipos de produção para BrCris
     */
    private function mapProductionType($type)
    {
        $mapping = [
            'artigo' => 'journal article',
            'livro' => 'book',
            'capitulo' => 'book chapter',
            'evento' => 'conference paper',
            'orientacao_mestrado' => 'master thesis',
            'orientacao_doutorado' => 'doctoral thesis',
            'patente' => 'patent',
            'software' => 'software',
            'produto_tecnico' => 'technical product'
        ];
        
        return $mapping[$type] ?? $type;
    }
    
    /**
     * Log da atividade de sincronização
     */
    private function logSyncActivity($results)
    {
        $logFile = $this->config['data_paths']['logs'] ?? __DIR__ . '/../../data/logs.sqlite';
        
        try {
            $pdo = new PDO("sqlite:$logFile");
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmt = $pdo->prepare("
                INSERT INTO logs (level, message, context, timestamp)
                VALUES (?, ?, ?, ?)
            ");
            
            $level = $results['success'] ? 'INFO' : 'ERROR';
            $message = sprintf(
                "BrCris sync - Records: %d, Errors: %d",
                $results['synced_records'],
                count($results['errors'])
            );
            $context = json_encode([
                'service' => 'brcris',
                'results' => $results
            ]);
            
            $stmt->execute([$level, $message, $context, date('Y-m-d H:i:s')]);
            
        } catch (Exception $e) {
            error_log("Erro ao registrar log de sincronização BrCris: " . $e->getMessage());
        }
    }
    
    /**
     * Obter metadados de um registro específico
     */
    public function getRecordMetadata($recordId)
    {
        $endpoint = $this->apiUrl . "/records/$recordId";
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Accept: application/json',
                    'User-Agent: Prodmais-UMC/1.0'
                ],
                'timeout' => $this->timeout
            ]
        ]);
        
        try {
            $response = file_get_contents($endpoint, false, $context);
            
            if ($response === false) {
                throw new Exception("Registro não encontrado no BrCris");
            }
            
            $data = json_decode($response, true);
            
            return [
                'success' => true,
                'metadata' => $data
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'metadata' => null
            ];
        }
    }
}