<?php
/**
 * Módulo: Gerenciador de Grades
 * 
 * Responsável por:
 * - Salvar grades de itens no Supabase
 * - Listar grades existentes
 * - Compatibilidade com schema do Algorise (grades_de_itens + grade_catmat_associados)
 */

if (file_exists(__DIR__ . '/../config/database.php')) {
    require_once __DIR__ . '/../config/database.php';
}

class GradeManager {
    
    private $supabaseUrl;
    private $supabaseKey;
    
    public function __construct() {
        $this->supabaseUrl = defined('SUPABASE_URL') ? SUPABASE_URL : getenv('SUPABASE_URL');
        $this->supabaseKey = defined('SUPABASE_KEY') ? SUPABASE_KEY : getenv('SUPABASE_KEY');
    }
    
    /**
     * Salva uma grade completa (cabeçalho + itens) no Supabase
     * 
     * @param array $dados  [nome, descricao, url_origem, processo_id, criada_por, itens[]]
     * @return array        [sucesso, grade_id, mensagem]
     */
    public function salvar($dados) {
        // 1. Criar a grade (cabeçalho)
        $gradePayload = [
            'nome'           => $dados['nome'],
            'descricao'      => $dados['descricao'] ?? '',
            'url_origem'     => $dados['url_origem'] ?? '',
            'processo_id'    => $dados['processo_id'] ?? '',
            'portal_origem'  => strpos($dados['url_origem'] ?? '', 'licitanet') !== false ? 'Licitanet' : ($dados['portal_origem'] ?? 'Portal de Compras Públicas'),
            'criada_por'     => $dados['criada_por'] ?? 'extrator-web',
            'status'         => 'rascunho',
            'orgao'          => $dados['orgao'] ?? '',
            'objeto'         => $dados['objeto'] ?? '',
            'numero_processo'=> $dados['numero_processo'] ?? '',
            'data_sessao'    => !empty($dados['data_sessao']) ? $dados['data_sessao'] : null,
        ];
        
        $response = $this->request(
            $this->supabaseUrl . '/rest/v1/grades_de_itens',
            'POST',
            json_encode($gradePayload),
            ['Prefer: return=representation']
        );
        
        if (!$response) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao criar grade no Supabase'];
        }
        
        $gradeResult = json_decode($response, true);
        if (empty($gradeResult[0]['id'])) {
            return ['sucesso' => false, 'mensagem' => 'Resposta inválida do Supabase: ' . $response];
        }
        
        $gradeId = $gradeResult[0]['id'];
        
        // 2. Inserir itens da grade
        $itens = $dados['itens'] ?? [];
        if (!empty($itens)) {
            $itensPayload = [];
            foreach ($itens as $ordem => $item) {
                $itensPayload[] = [
                    'grade_id'          => $gradeId,
                    'codigo_catmat'     => !empty($item['codigo_catmat']) ? intval($item['codigo_catmat']) : null,
                    'descricao_portal'  => $item['descricao_portal'] ?? $item['descricao'] ?? '',
                    'descricao_catmat'  => $item['descricao_catmat'] ?? null,
                    'quantidade'        => floatval($item['quantidade'] ?? 1),
                    'unidade'           => $item['unidade'] ?? 'UN',
                    'valor_referencia'  => floatval($item['valor_referencia'] ?? 0),
                    'melhor_lance'      => floatval($item['melhor_lance'] ?? 0),
                    'ordem'             => intval($ordem) + 1,
                    'item_numero'       => $item['numero'] ?? strval($ordem + 1),
                    'status_item'       => $item['status'] ?? 'N/A',
                ];
            }
            
            $responseItens = $this->request(
                $this->supabaseUrl . '/rest/v1/grade_catmat_associados',
                'POST',
                json_encode($itensPayload)
            );
            
            if (!$responseItens) {
                return [
                    'sucesso' => true, 
                    'grade_id' => $gradeId,
                    'mensagem' => 'Grade criada, mas houve erro ao inserir alguns itens.',
                    'itens_inseridos' => 0,
                ];
            }
        }
        
        return [
            'sucesso'          => true,
            'grade_id'         => $gradeId,
            'mensagem'         => 'Grade salva com sucesso!',
            'itens_inseridos'  => count($itens),
        ];
    }
    
    /**
     * Lista todas as grades
     */
    public function listar() {
        $response = $this->request(
            $this->supabaseUrl . '/rest/v1/grades_de_itens?select=id,nome,descricao,status,criada_por,portal_origem,url_origem,orgao,objeto,numero_processo,data_sessao,data_criacao,grade_catmat_associados(count)&order=data_criacao.desc',
            'GET'
        );
        
        if (!$response) return [];
        return json_decode($response, true) ?: [];
    }
    
    /**
     * Busca uma grade com seus itens
     */
    public function buscarComItens($gradeId) {
        // Grade
        $response = $this->request(
            $this->supabaseUrl . '/rest/v1/grades_de_itens?id=eq.' . intval($gradeId) . '&select=*,grade_catmat_associados(*)',
            'GET'
        );
        
        if (!$response) return null;
        $data = json_decode($response, true);
        return !empty($data[0]) ? $data[0] : null;
    }
    
    /**
     * Atualiza status de sincronização
     */
    public function marcarSincronizado($gradeId) {
        return $this->request(
            $this->supabaseUrl . '/rest/v1/grades_de_itens?id=eq.' . intval($gradeId),
            'PATCH',
            json_encode(['sincronizado_algorise' => true, 'status' => 'sincronizado'])
        );
    }
    
    /**
     * Busca histórico de preços por código CATMAT ou descrição
     */
    public function buscarPrecos($termo) {
        $termoLimpo = trim($termo);
        $termoBusca = urlencode('*' . $termoLimpo . '*');
        
        if (is_numeric($termoLimpo)) {
            // Se for número, busca por código ou por trecho na descrição
            $orCond = "or=(codigo_catmat.eq." . intval($termoLimpo) . ",descricao_portal.ilike." . $termoBusca . ",descricao_catmat.ilike." . $termoBusca . ")";
        } else {
            // Se for texto, busca apenas nas descrições
            $orCond = "or=(descricao_portal.ilike." . $termoBusca . ",descricao_catmat.ilike." . $termoBusca . ")";
        }
        
        $url = $this->supabaseUrl . '/rest/v1/grade_catmat_associados?' . $orCond . '&select=*,grades_de_itens(nome,url_origem,data_criacao)';
        $response = $this->request($url, 'GET');
        
        if (!$response) return [];
        return json_decode($response, true) ?: [];
    }
    
    /**
     * Exclui uma grade
     */
    public function excluir($gradeId) {
        return $this->request(
            $this->supabaseUrl . '/rest/v1/grades_de_itens?id=eq.' . intval($gradeId),
            'DELETE'
        );
    }
    
    /**
     * Requisição HTTP para o Supabase
     */
    private function request($url, $method = 'GET', $body = null, $extraHeaders = []) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        
        $headers = array_merge([
            'Content-Type: application/json',
            'apikey: ' . $this->supabaseKey,
            'Authorization: Bearer ' . $this->supabaseKey,
        ], $extraHeaders);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        } elseif ($method === 'PATCH') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return ($httpCode >= 200 && $httpCode < 300) ? $response : null;
    }
}
