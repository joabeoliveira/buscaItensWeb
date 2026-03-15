<?php
/**
 * Módulo: Extrator de Itens do Portal de Compras Públicas
 * 
 * Responsável por:
 * - Buscar HTML de uma URL (fallback)
 * - Buscar itens via API oficial com paginação automática
 * - Extrair itens via scraping (fallback)
 * - Detectar o ID do processo na URL
 */

class Extrator {
    
    private $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    private $apiBase = 'https://compras.api.portaldecompraspublicas.com.br/v2/licitacao';
    
    /**
     * Extrai o ID do processo a partir da URL do Portal
     */
    public function extrairProcessoId($url) {
        if (preg_match('/(\d{5,})(?:\?.*)?$/', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }
    
    /**
     * Busca TODOS os itens via API com paginação automática
     */
    public function buscarViaAPI($processoId) {
        $itensCompletos = [];
        $paginaAtiva = 1;
        $totalPaginas = 1;
        
        do {
            $apiUrl = "{$this->apiBase}/{$processoId}/itens?pagina={$paginaAtiva}";
            $response = $this->fazerRequisicao($apiUrl);
            
            if ($response === null) break;
            
            $data = json_decode($response, true);
            if (!isset($data['itens']['result'])) break;
            
            foreach ($data['itens']['result'] as $it) {
                $itensCompletos[] = [
                    'numero'           => $it['codigo'] ?? ($it['numero'] ?? ''),
                    'status'           => $it['situacao']['descricao'] ?? ($it['situacaoDescricao'] ?? 'N/A'),
                    'descricao'        => $it['descricao'] ?? '',
                    'quantidade'       => $it['quantidade'] ?? '0',
                    'unidade'          => $it['unidadeExtenso'] ?? ($it['unidade'] ?? 'Unid'),
                    'valor_referencia' => $it['valorReferencia'] ?? 0,
                    'melhor_lance'     => $it['melhorLance'] ?? ($it['valorMelhorLance'] ?? 0),
                ];
            }
            
            $totalPaginas = intval($data['itens']['pageCount'] ?? 1);
            $paginaAtiva++;
            
        } while ($paginaAtiva <= $totalPaginas && $paginaAtiva < 50);
        
        return $itensCompletos;
    }
    
    /**
     * Busca itens via scraping HTML (fallback caso API falhe)
     */
    public function buscarViaScraping($url) {
        $html = $this->fazerRequisicao($url);
        if (!$html) return ['itens' => [], 'total' => 0];
        
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);
        
        $itens = [];
        $cards = $xpath->query("//div[contains(@class, 'card-item')]");
        
        foreach ($cards as $card) {
            $item = [];
            
            $numeroNode = $xpath->query(".//p[contains(@class, 'descr')]/span", $card)->item(0);
            $item['numero'] = $numeroNode ? trim($numeroNode->textContent) : '';
            
            $statusNode = $xpath->query(".//app-status//span[contains(@class, 'status-text')]", $card)->item(0);
            $item['status'] = $statusNode ? trim($statusNode->textContent) : 'N/A';
            
            $descricaoNode = $xpath->query(".//p[contains(@class, 'descr-2')]", $card)->item(0);
            if ($descricaoNode) {
                $tempDom = new DOMDocument();
                $tempDom->appendChild($tempDom->importNode($descricaoNode, true));
                $tempXpath = new DOMXPath($tempDom);
                $badNodes = $tempXpath->query(".//button | .//script | .//style");
                foreach ($badNodes as $bad) { $bad->parentNode->removeChild($bad); }
                $item['descricao'] = trim($tempDom->textContent);
            } else {
                $item['descricao'] = '';
            }
            
            $infoItems = $xpath->query(".//div[contains(@class, 'info-item')]", $card);
            foreach ($infoItems as $info) {
                $labelNode = $xpath->query(".//span", $info)->item(0);
                $valueNode = $xpath->query(".//p", $info)->item(0);
                if (!$labelNode || !$valueNode) continue;
                
                $label = strtolower(trim($labelNode->textContent));
                $value = trim($valueNode->textContent);
                
                if (strpos($label, 'quantidade') !== false) $item['quantidade'] = $value;
                elseif (strpos($label, 'unidade') !== false) $item['unidade'] = $value;
                elseif (strpos($label, 'referência') !== false) {
                    $item['valor_referencia'] = $this->limparValor($value);
                } elseif (strpos($label, 'melhor lance') !== false) {
                    $item['melhor_lance'] = $this->limparValor($value);
                }
            }
            
            if (!empty($item['numero'])) $itens[] = $item;
        }
        
        // Total de registros
        $total = count($itens);
        $nodes = $xpath->query("//p[contains(@class, 'subtitle')]");
        foreach ($nodes as $node) {
            if (preg_match('/(\d+)\s+registros/i', $node->textContent, $m)) {
                $total = (int)$m[1];
            }
        }
        
        return ['itens' => $itens, 'total' => $total];
    }
    
    /**
     * Método principal: tenta API primeiro, fallback para scraping
     */
    public function extrair($url) {
        $resultado = [
            'itens'   => [],
            'total'   => 0,
            'metodo'  => 'Nenhum',
            'erro'    => null,
            'tempo'   => 0,
        ];
        
        $start = microtime(true);
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $resultado['erro'] = 'URL inválida. Por favor, insira uma URL válida do Portal de Compras Públicas.';
            return $resultado;
        }
        
        // Tenta API primeiro (suporta paginação)
        $processoId = $this->extrairProcessoId($url);
        if ($processoId) {
            $itens = $this->buscarViaAPI($processoId);
            if (!empty($itens)) {
                $resultado['itens']  = $itens;
                $resultado['total']  = count($itens);
                $resultado['metodo'] = 'API Direta (Paginação Automática)';
            }
        }
        
        // Fallback para scraping
        if (empty($resultado['itens'])) {
            $scraping = $this->buscarViaScraping($url);
            if (!empty($scraping['itens'])) {
                $resultado['itens']  = $scraping['itens'];
                $resultado['total']  = $scraping['total'];
                $resultado['metodo'] = 'Scraping HTML (Primeira Página)';
            } else {
                $resultado['erro'] = 'Nenhum item encontrado. O portal pode ter alterado a estrutura ou a URL não contém uma lista de itens.';
            }
        }
        
        $resultado['tempo'] = round(microtime(true) - $start, 2);
        return $resultado;
    }
    
    // --- Métodos auxiliares ---
    
    private function fazerRequisicao($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($httpCode == 200) ? $response : null;
    }
    
    private function limparValor($value) {
        $clean = preg_replace('/[^\d,.]/', '', $value);
        return str_replace(',', '.', str_replace('.', '', $clean));
    }
}
