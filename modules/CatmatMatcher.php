<?php
/**
 * Módulo: De-Para CATMAT (Classificação de Materiais do Governo Federal)
 * 
 * Responsável por:
 * - Conectar ao Supabase para buscar o catálogo de materiais
 * - Buscar códigos CATMAT similares com base na descrição do item
 * - Utilizar a função PostgreSQL buscar_catmat_similar (pg_trgm)
 */

require_once __DIR__ . '/../config/database.php';

class CatmatMatcher {
    
    private $supabaseUrl;
    private $supabaseKey;
    
    public function __construct() {
        $this->supabaseUrl = SUPABASE_URL;
        $this->supabaseKey = SUPABASE_KEY;
    }
    
    /**
     * Busca os códigos CATMAT mais similares para uma descrição de item
     * 
     * @param string $descricao  Descrição do item extraído do portal
     * @param int    $limite     Quantidade de sugestões (default: 5)
     * @return array             Lista de sugestões com codigo_catmat, descricao, similaridade
     */
    public function buscarSimilares($descricao, $limite = 5) {
        // Limpa a descrição para melhorar a busca
        $descricaoLimpa = $this->prepararDescricao($descricao);
        
        // Chama a função RPC do Supabase
        $endpoint = $this->supabaseUrl . '/rest/v1/rpc/buscar_catmat_similar';
        
        $payload = json_encode([
            'p_descricao' => $descricaoLimpa,
            'p_limit'     => $limite,
        ]);
        
        $response = $this->request($endpoint, 'POST', $payload);
        
        if ($response === null) {
            throw new Exception("Erro de comunicação com o Supabase ou timeout da consulta.");
        }
        
        $data = json_decode($response, true);
        return is_array($data) ? $data : [];
    }
    
    /**
     * Busca CATMAT para múltiplos itens de uma vez
     * 
     * @param array $itens   Array de itens (cada um com campo 'descricao')
     * @param int   $limite  Sugestões por item
     * @return array         Array indexado pelo número do item com as sugestões
     */
    public function buscarParaLote($itens, $limite = 5) {
        $resultados = [];
        
        foreach ($itens as $item) {
            $numero = $item['numero'] ?? '?';
            $descricao = $item['descricao'] ?? '';
            
            if (empty($descricao)) {
                $resultados[$numero] = [];
                continue;
            }
            
            $resultados[$numero] = $this->buscarSimilares($descricao, $limite);
        }
        
        return $resultados;
    }
    
    /**
     * Busca CATMAT para um único item (via AJAX)
     */
    public function buscarParaItem($descricao, $limite = 5) {
        return $this->buscarSimilares($descricao, $limite);
    }
    
    /**
     * Prepara a descrição para a busca por similaridade
     * A função SQL buscar_catmat_similar() cuida da extração de palavras-chave,
     * então aqui fazemos apenas limpeza básica
     */
    private function prepararDescricao($descricao) {
        // Remove pontuação excessiva que atrapalha trigramas
        $descricao = preg_replace('/[;()\[\]{}]/', ' ', $descricao);
        
        // Remove textos entre aspas que geralmente são especificações técnicas
        $descricao = preg_replace('/"[^"]*"/', ' ', $descricao);
        
        // Remove múltiplos espaços e hífens repetidos
        $descricao = preg_replace('/[-–]{2,}/', ' ', $descricao);
        $descricao = preg_replace('/\s+/', ' ', trim($descricao));
        
        return $descricao;
    }
    
    /**
     * Requisição HTTP para o Supabase
     */
    private function request($url, $method = 'GET', $body = null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        
        $headers = [
            'Content-Type: application/json',
            'apikey: ' . $this->supabaseKey,
            'Authorization: Bearer ' . $this->supabaseKey,
        ];
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return ($httpCode >= 200 && $httpCode < 300) ? $response : null;
    }
}
