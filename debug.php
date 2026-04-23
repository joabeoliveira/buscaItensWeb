<?php
/**
 * Utilitário de Depuração para Licitador Pro
 * Mostra o conteúdo capturado da última tentativa de extração
 */

$file = __DIR__ . '/debug_licitanet.html';

if (!file_exists($file)) {
    echo "<h1>Nenhum arquivo de debug encontrado.</h1>";
    echo "<p>Tente realizar uma extração da Licitanet primeiro para gerar o log.</p>";
    exit;
}

$content = file_get_contents($file);
$size = strlen($content);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Licitanet | Licitador Pro</title>
    <style>
        body { font-family: sans-serif; background: #f0f2f5; padding: 20px; }
        .info { background: #fff; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #ddd; }
        .preview { background: #fff; border: 1px solid #ddd; padding: 20px; border-radius: 8px; min-height: 400px; }
        pre { background: #222; color: #0f0; padding: 15px; overflow: auto; border-radius: 8px; max-height: 500px; }
    </style>
</head>
<body>
    <div class="info">
        <h2>Arquivo: debug_licitanet.html</h2>
        <p>Tamanho: <?php echo round($size / 1024, 2); ?> KB</p>
        <p>Data da captura: <?php echo date("d/m/Y H:i:s", filemtime($file)); ?></p>
        <button onclick="location.reload()">Atualizar</button>
        <button onclick="document.getElementById('raw').style.display='block'">Ver Código Fonte (HTML)</button>
    </div>

    <div id="raw" style="display:none; margin-bottom: 20px;">
        <h3>Código Fonte Bruto:</h3>
        <pre><?php echo htmlspecialchars(substr($content, 0, 10000)); ?> ... (mostrando primeiros 10KB)</pre>
    </div>

    <h3>Visualização (Como o PHP enxerga):</h3>
    <div class="preview">
        <?php 
            // Tenta limpar scripts para não quebrar a página de debug, mas mantém o estilo
            $preview = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', "", $content);
            echo $preview; 
        ?>
    </div>
</body>
</html>
