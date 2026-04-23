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
    
    private $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';
    private $lastHttpCode = 0;
    private $lastError = '';
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
     * Busca itens via Licitanet (dados embutidos no HTML)
     */
    public function buscarLicitanet($url) {
        $html = $this->fazerRequisicao($url);
        if (!$html) return [];
        
        // DEBUG: Salva o HTML recebido para inspeção. 
        file_put_contents(__DIR__ . '/../debug_licitanet.html', $html);

        $itensCompletos = [];
        $meta = [
            'orgao' => '',
            'objeto' => '',
            'numero_processo' => '',
            'data_sessao' => '',
        ];
        
        $jsonData = null;
        // Regex robusta para capturar o atributo data-page
        if (preg_match('/data-page\s*=\s*(["\'])(.*?)\1/s', $html, $matches)) {
            $jsonData = html_entity_decode($matches[2]);
        }

        if ($jsonData) {
            $data = json_decode($jsonData, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->lastError = "Erro JSON: " . json_last_error_msg();
                return [];
            }
            
            $room = $data['props']['disputeRoom'] ?? null;
            if (!$room) {
                $this->lastError = "Estrutura 'disputeRoom' não encontrada.";
                return [];
            }

            // Suporte para Licitanet 4.0 (items pode ser um array simples ou um objeto paginado com 'data')
            $itemsRaw = $room['items'] ?? [];
            $items = isset($itemsRaw['data']) && is_array($itemsRaw['data']) ? $itemsRaw['data'] : $itemsRaw;

            $statusGeral = $room['status'] ?? 'N/A';
            $messages = $room['messages']['data'] ?? $room['messages'] ?? [];
            
            $meta['orgao'] = $room['buyer'] ?? '';
            $meta['objeto'] = $room['description'] ?? '';
            $meta['numero_processo'] = $room['number'] ?? '';
            $meta['data_sessao'] = $room['startDate'] ?? '';

            // Tenta achar o melhor lance nas mensagens (ACEITA pelo valor de R$ X)
            $melhoresLances = [];
            if (is_array($messages)) {
                foreach ($messages as $msg) {
                    if (isset($msg['batch']) && isset($msg['message'])) {
                        if (preg_match('/ACEITA pelo valor de R\$\s*([\d\.,]+)/i', $msg['message'], $m)) {
                            $melhoresLances[$msg['batch']] = $this->limparValor($m[1]);
                        } elseif (preg_match('/R\$\s*([\d\.,]+)/i', $msg['message'], $m)) {
                            if (!isset($melhoresLances[$msg['batch']])) {
                                $melhoresLances[$msg['batch']] = $this->limparValor($m[1]);
                            }
                        }
                    }
                }
            }
            
            if (is_array($items)) {
                foreach ($items as $it) {
                    $batch = $it['batch'] ?? ($it['numero'] ?? '');
                    if (!$batch) continue;

                    $itensCompletos[] = [
                        'numero'           => $batch,
                        'status'           => strtoupper($statusGeral),
                        'descricao'        => $it['name'] ?? ($it['descricao'] ?? ''),
                        'quantidade'       => $it['quantity'] ?? ($it['quantidade'] ?? '0'),
                        'unidade'          => $it['unit'] ?? ($it['unidade'] ?? 'Unid'),
                        'valor_referencia' => isset($it['estimatedValue']) ? $this->limparValor($it['estimatedValue']) : (isset($it['valorReferencia']) ? $this->limparValor($it['valorReferencia']) : 0),
                        'melhor_lance'     => $melhoresLances[$batch] ?? 0,
                    ];
                }
            }
        } else {
            $this->lastError = "Atributo 'data-page' não encontrado no HTML.";
        }
        
        return ['itens' => $itensCompletos, 'meta' => $meta];
    }

    /**
     * Método principal: tenta API primeiro, fallback para scraping
     */
    public function extrair($url) {
        $resultado = [
            'itens'   => [],
            'meta'    => [
                'orgao' => '',
                'objeto' => '',
                'numero_processo' => '',
                'data_sessao' => '',
            ],
            'total'   => 0,
            'metodo'  => 'Nenhum',
            'erro'    => null,
            'tempo'   => 0,
        ];
        
        $start = microtime(true);
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $resultado['erro'] = 'URL inválida. Por favor, insira uma URL válida.';
            return $resultado;
        }

        // Verifica se é Licitanet
        if (strpos($url, 'licitanet.com.br') !== false) {
            $dadosLicitanet = $this->buscarLicitanet($url);
            if (!empty($dadosLicitanet['itens'])) {
                $resultado['itens']  = $dadosLicitanet['itens'];
                $resultado['total']  = count($dadosLicitanet['itens']);
                $resultado['meta']   = $dadosLicitanet['meta'];
                $resultado['metodo'] = 'Licitanet (JSON Embutido)';
            } else {
                $statusMsg = $this->lastHttpCode ? " (HTTP {$this->lastHttpCode})" : "";
                $resultado['erro'] = "Nenhum item encontrado no Licitanet{$statusMsg}. O portal pode ter bloqueado o acesso ou a estrutura mudou.";
                if ($this->lastError) $resultado['erro'] .= " Detalhe: " . $this->lastError;
            }
            $resultado['tempo'] = round(microtime(true) - $start, 2);
            return $resultado;
        }
        
        // Tenta API primeiro (suporta paginação)
        $processoId = $this->extrairProcessoId($url);
        
        // Vamos tentar pegar os metadados do HTML primeiro (pois a API de itens não tem o órgão)
        $htmlPortal = $this->fazerRequisicao($url);
        if ($htmlPortal) {
            $domMeta = new DOMDocument();
            libxml_use_internal_errors(true);
            $domMeta->loadHTML($htmlPortal);
            libxml_clear_errors();
            $xpathMeta = new DOMXPath($domMeta);
            
            // Órgão geralmente fica em algum h3 ou na área de informações
            $orgaoNode = $xpathMeta->query("//h3[contains(@class, 'subtitle')]");
            if ($orgaoNode->length > 0) {
                $resultado['meta']['orgao'] = trim($orgaoNode->item(0)->textContent);
            }
            
            // Número do processo
            $procNode = $xpathMeta->query("//div[contains(text(), 'Processo')]/following-sibling::div");
            if ($procNode->length > 0) {
                $resultado['meta']['numero_processo'] = trim($procNode->item(0)->textContent);
            }
        }
        
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
        $this->lastHttpCode = 0;
        $this->lastError = '';
        
        $flareUrl = getenv('FLARESOLVERR_URL');
        
        // Se o FlareSolverr estiver configurado, usamos ele para bypassar WAF
        if ($flareUrl && strpos($url, 'licitanet.com.br') !== false) {
            return $this->requisicaoViaFlareSolverr($flareUrl, $url);
        }

        // Caso contrário, segue o cURL normal (com suporte a cookies)
        $cookieFile = sys_get_temp_dir() . '/licitador_cookies.txt';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_ENCODING, ""); 
        curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
        
        // Força o uso de IPv6 se disponível
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V6);
        
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
            'Cache-Control: max-age=0',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
            'Sec-Ch-Ua: "Chromium";v="124", "Google Chrome";v="124", "Not-A.Brand";v="99"',
            'Sec-Ch-Ua-Mobile: ?0',
            'Sec-Ch-Ua-Platform: "Windows"',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Sec-Fetch-User: ?1',
            'Pragma: no-cache'
        ]);
        
        $response = curl_exec($ch);
        $this->lastHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($response === false) {
            $this->lastError = curl_error($ch);
        }
        
        curl_close($ch);
        return ($this->lastHttpCode == 200) ? $response : null;
    }

    /**
     * Envia a requisição para o FlareSolverr (bypass de Cloudflare/WAF)
     * Agora com o 'Inertia Hack' para tentar burlar o bloqueio 403
     */
    private function requisicaoViaFlareSolverr($flareUrl, $targetUrl) {
        // Tentamos fingir que somos uma navegação interna do Inertia.js
        // Isso muitas vezes pula o bloqueio de firewall da página principal
        $postData = json_encode([
            'cmd' => 'request.get',
            'url' => $targetUrl,
            'maxTimeout' => 60000,
            'headers' => [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => '6a74cf2f574f429f', // Versão comum ou detectada
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'text/html, application/xhtml+xml, application/xml;q=0.9, image/webp, */*;q=0.8'
            ]
        ]);

        $ch = curl_init($flareUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 65);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            $targetStatus = $data['solution']['status'] ?? 200;
            
            // Atualiza o status real (ex: se a Licitanet mandou 403, reportamos 403)
            $this->lastHttpCode = $targetStatus;

            if (isset($data['solution']['response'])) {
                $content = $data['solution']['response'];
                
                // Se o Inertia responder com JSON direto (devido ao header X-Inertia),
                // o conteúdo pode já ser o JSON que queremos
                if (strpos($content, '{"props":') === 0 || strpos($content, '{"component":') === 0) {
                    // Criamos um "fake" data-page para o resto do código funcionar
                    return '<div id="app" data-page=\'' . htmlspecialchars($content) . '\'></div>';
                }
                
                return $content;
            }
        }

        $this->lastHttpCode = $httpCode;
        $this->lastError = "FlareSolverr falhou ou o portal bloqueou a requisição.";
        return null;
    }
    
    private function limparValor($value) {
        $clean = preg_replace('/[^\d,.]/', '', $value);
        return str_replace(',', '.', str_replace('.', '', $clean));
    }
}
