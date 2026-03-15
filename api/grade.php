<?php
/**
 * API Endpoint: Salvar Grade de Itens
 * 
 * POST /api/grade.php
 * Body: { nome, descricao, url_origem, processo_id, itens: [{descricao, numero, quantidade, ...}] }
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../modules/GradeManager.php';

$manager = new GradeManager();

try {
    // POST: Salvar nova grade
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || empty($input['nome'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Campo "nome" é obrigatório']);
            exit;
        }
        
        $resultado = $manager->salvar($input);
        
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    // GET: Listar grades ou buscar uma específica
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['id'])) {
            $grade = $manager->buscarComItens($_GET['id']);
            if ($grade) {
                echo json_encode($grade, JSON_UNESCAPED_UNICODE);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Grade não encontrada']);
            }
        } else {
            $grades = $manager->listar();
            echo json_encode(['grades' => $grades], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
    
    // DELETE: Excluir grade
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID é obrigatório']);
            exit;
        }
        $manager->excluir($id);
        echo json_encode(['sucesso' => true, 'mensagem' => 'Grade excluída']);
        exit;
    }
    
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno: ' . $e->getMessage()]);
}
