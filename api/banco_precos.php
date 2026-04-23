<?php
/**
 * API Endpoint: Banco de Preços
 * 
 * GET /api/banco_precos.php?catmat=12345
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../modules/GradeManager.php';

try {
    $manager = new GradeManager();
    
    if (isset($_GET['busca'])) {
        $termo = $_GET['busca'];
        $precos = $manager->buscarPrecos($termo);
        echo json_encode(['sucesso' => true, 'dados' => $precos], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Parâmetro "busca" é obrigatório']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno: ' . $e->getMessage()]);
}
