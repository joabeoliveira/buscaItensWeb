<?php
/**
 * API Endpoint: Busca CATMAT similar para um item via AJAX
 * 
 * POST /api/catmat.php
 * Body: { "descricao": "...", "limite": 5 }
 * 
 * Retorna JSON com sugestões de códigos CATMAT
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../modules/CatmatMatcher.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || empty($input['descricao'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Campo "descricao" é obrigatório']);
        exit;
    }
    
    $descricao = $input['descricao'];
    $limite = intval($input['limite'] ?? 10);
    $limite = max(1, min($limite, 50)); // Entre 1 e 50
    
    $matcher = new CatmatMatcher();
    $sugestoes = $matcher->buscarParaItem($descricao, $limite);
    
    echo json_encode([
        'descricao_original' => $descricao,
        'sugestoes' => $sugestoes,
        'total' => count($sugestoes),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno: ' . $e->getMessage()]);
}
