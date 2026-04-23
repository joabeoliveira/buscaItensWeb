<?php
/**
 * LICITADOR PRO - Extrator Inteligente + De-Para CATMAT
 * 
 * Arquitetura Modular:
 * - modules/Extrator.php      → Captura de itens do Portal de Compras Públicas
 * - modules/CatmatMatcher.php → Matching de códigos CATMAT via Supabase
 * - config/database.php       → Credenciais do Supabase
 * - api/catmat.php             → Endpoint AJAX para busca individual
 */

require_once __DIR__ . '/modules/Extrator.php';

// --- Processamento ---
$url = $_POST['url'] ?? '';
$htmlColado = $_POST['html_colado'] ?? '';
$extrator = new Extrator();
$resultado = [
    'itens'  => [],
    'total'  => 0,
    'metodo' => 'Nenhum',
    'erro'   => null,
    'tempo'  => 0,
];

if ($htmlColado) {
    // Detecta se é JSON do script do console ou HTML do código-fonte
    $trimmed = trim($htmlColado);
    if (strpos($trimmed, '{"tipo":"licitanet_console"') === 0) {
        $resultado = $extrator->processarJsonConsole($trimmed);
    } else {
        $resultado = $extrator->processarHtmlColado($htmlColado);
    }
} elseif ($url) {
    $resultado = $extrator->extrair($url);
}

$itens = $resultado['itens'] ?? [];
$error = $resultado['erro'] ?? null;
$metodoUtilizado = $resultado['metodo'] ?? 'Nenhum';
$tempoExecucao = $resultado['tempo'] ?? 0;
$meta = $resultado['meta'] ?? [
    'orgao' => '',
    'objeto' => '',
    'numero_processo' => '',
    'data_sessao' => '',
];

// Estatísticas
$valorTotalRef = 0;
foreach ($itens as $it) {
    $qtdStr = $it['quantidade'] ?? '0';
    // Detecta formato: se tem vírgula → BR (1.000,50); senão → internacional (60.00)
    if (strpos($qtdStr, ',') !== false) {
        $qtd = floatval(str_replace(',', '.', str_replace('.', '', $qtdStr)));
    } else {
        $qtd = floatval($qtdStr);
    }
    $val = floatval($it['valor_referencia'] ?? 0);
    $valorTotalRef += ($qtd * $val);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Licitador Pro | Extrator + CATMAT</title>
    <meta name="description" content="Ferramenta inteligente para extração de itens de licitação e correspondência de códigos CATMAT do governo federal.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary: #0ea5e9;
            --primary-dark: #0284c7;
            --primary-glow: rgba(14, 165, 233, 0.15);
            --bg-dark: #0f172a;
            --bg-card: #1e293b;
            --bg-card-alt: #162032;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --accent-green: #4ade80;
            --accent-amber: #fbbf24;
            --accent-red: #f87171;
            --glass: rgba(30, 41, 59, 0.7);
            --border: rgba(255, 255, 255, 0.08);
            --radius: 16px;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-dark);
            background-image: radial-gradient(circle at 50% 0%, var(--primary-glow) 0%, transparent 50%);
            color: var(--text-main);
            line-height: 1.6;
            min-height: 100vh;
        }
        h1,h2,h3 { font-family: 'Outfit', sans-serif; font-weight: 700; }
        .container { max-width: 1500px; margin: 0 auto; padding: 30px 20px; }

        /* Header */
        header { text-align: center; margin-bottom: 40px; }
        .logo { font-size: 2.2rem; color: var(--primary); display: flex; align-items: center; justify-content: center; gap: 12px; }
        .logo i { font-size: 2.5rem; filter: drop-shadow(0 0 8px rgba(14,165,233,0.4)); }
        .logo .badge { 
            font-size: 0.6rem; background: linear-gradient(135deg, var(--accent-green), #22d3ee); 
            color: #000; padding: 3px 8px; border-radius: 20px; font-weight: 700; vertical-align: super;
            letter-spacing: 0.5px;
        }
        header p { color: var(--text-muted); font-size: 1rem; margin-top: 5px; }

        /* Glass Card */
        .glass-card {
            background: var(--glass); backdrop-filter: blur(10px);
            border: 1px solid var(--border); border-radius: var(--radius);
            padding: 25px; margin-bottom: 30px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.25);
        }

        /* Search */
        .input-group { display: flex; gap: 12px; margin-top: 12px; }
        input[type="url"], input[type="text"] {
            flex: 1; padding: 14px 22px; border-radius: 12px;
            border: 1px solid var(--border); background: rgba(15,23,42,0.5);
            color: #fff; font-size: 0.95rem; outline: none; transition: all 0.3s;
        }
        input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(14,165,233,0.1); }
        .btn-primary {
            padding: 14px 28px; border-radius: 12px; border: none;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff; font-weight: 600; cursor: pointer; display: flex;
            align-items: center; gap: 8px; transition: all 0.3s; white-space: nowrap;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(14,165,233,0.4); }
        .btn-outline {
            padding: 9px 18px; border-radius: 8px; border: 1px solid var(--border);
            background: transparent; color: var(--text-main); cursor: pointer;
            display: flex; align-items: center; gap: 6px; transition: all 0.2s; font-size: 0.85rem;
        }
        .btn-outline:hover { background: rgba(255,255,255,0.05); border-color: var(--text-muted); }
        .btn-sm {
            padding: 6px 14px; border-radius: 6px; font-size: 0.78rem; border: 1px solid var(--border);
            background: rgba(14,165,233,0.1); color: var(--primary); cursor: pointer;
            transition: all 0.2s; display: inline-flex; align-items: center; gap: 5px;
        }
        .btn-sm:hover { background: rgba(14,165,233,0.2); }
        .btn-sm.active { background: var(--primary); color: #fff; }

        /* Stats */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .stat-card { background: var(--bg-card); padding: 18px; border-radius: var(--radius); border: 1px solid var(--border); text-align: center; }
        .stat-card .label { color: var(--text-muted); font-size: 0.8rem; display: block; margin-bottom: 4px; }
        .stat-card .value { font-size: 1.4rem; font-weight: 700; color: var(--primary); }

        /* Table */
        .table-wrap { overflow-x: auto; }
        .table-header { padding: 18px 22px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: rgba(255,255,255,0.03); text-align: left; padding: 12px 16px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; font-size: 0.72rem; letter-spacing: 0.8px; white-space: nowrap; }
        td { padding: 14px 16px; border-bottom: 1px solid var(--border); font-size: 0.88rem; vertical-align: top; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(255,255,255,0.02); }
        .status-badge { padding: 3px 8px; border-radius: 5px; font-size: 0.75rem; font-weight: 600; background: rgba(14,165,233,0.1); color: var(--primary); white-space: nowrap; }
        .val { font-family: 'JetBrains Mono', monospace; font-weight: 600; font-size: 0.85rem; }

        /* CATMAT Suggestions Panel */
        .catmat-panel {
            margin-top: 10px; padding: 12px; border-radius: 10px;
            background: var(--bg-card-alt); border: 1px solid var(--border);
            animation: fadeIn 0.3s ease;
        }
        .catmat-panel h4 { font-size: 0.8rem; color: var(--accent-amber); margin-bottom: 8px; }
        .catmat-suggestion {
            display: flex; flex-direction: column; gap: 6px;
            padding: 10px 12px; border-radius: 8px; margin-bottom: 6px;
            background: rgba(255,255,255,0.02); border: 1px solid var(--border);
            transition: all 0.2s; cursor: pointer; position: relative;
        }
        .catmat-suggestion:hover { border-color: var(--primary); background: rgba(14,165,233,0.05); }
        .catmat-suggestion.selected { border-color: var(--accent-green); background: rgba(74,222,128,0.08); }
        .catmat-suggestion.selected::after {
            content: '\f00c'; font-family: 'Font Awesome 6 Free'; font-weight: 900;
            position: absolute; top: 8px; right: 10px; color: var(--accent-green); font-size: 0.8rem;
        }
        .catmat-top { display: flex; justify-content: space-between; align-items: center; gap: 8px; padding-right: 22px; }
        .catmat-code { font-family: 'JetBrains Mono', monospace; font-weight: 700; color: var(--accent-green); font-size: 0.85rem; white-space: nowrap; }
        .catmat-desc { font-size: 0.82rem; color: var(--text-muted); line-height: 1.5; word-break: break-word; }
        .catmat-score { font-size: 0.72rem; padding: 2px 8px; border-radius: 20px; font-weight: 600; white-space: nowrap; }
        .score-high { background: rgba(74,222,128,0.15); color: var(--accent-green); }
        .score-medium { background: rgba(251,191,36,0.15); color: var(--accent-amber); }
        .score-low { background: rgba(248,113,113,0.15); color: var(--accent-red); }

        /* Selected CATMAT display */
        .catmat-chosen {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 10px; border-radius: 6px; font-size: 0.8rem;
            background: rgba(74,222,128,0.1); border: 1px solid rgba(74,222,128,0.3);
            color: var(--accent-green); font-family: 'JetBrains Mono', monospace; font-weight: 700;
        }
        .catmat-chosen i { font-size: 0.7rem; }

        /* Manual Search Box */
        .catmat-search-container {
            display: flex; gap: 8px; margin-bottom: 12px;
            background: rgba(0,0,0,0.2); padding: 8px; border-radius: 8px;
            border: 1px solid var(--border);
        }
        .catmat-search-input {
            flex: 1; background: transparent; border: none; color: #fff;
            font-size: 0.82rem; padding: 4px 8px; outline: none;
        }
        .catmat-search-btn {
            background: var(--primary); color: #fff; border: none;
            padding: 4px 10px; border-radius: 6px; cursor: pointer;
            font-size: 0.8rem; transition: background 0.2s;
        }
        .catmat-search-btn:hover { background: var(--primary-hover); }

        /* Item description expandable */
        .desc-text { font-size: 0.85rem; line-height: 1.5; color: var(--text-main); word-break: break-word; }
        .desc-toggle { color: var(--primary); cursor: pointer; font-size: 0.78rem; font-weight: 600; border: none; background: none; padding: 2px 0; margin-top: 3px; }
        .desc-toggle:hover { text-decoration: underline; }

        /* Loader */
        .loader { display: none; width: 20px; height: 20px; border: 3px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: #fff; animation: spin 0.8s linear infinite; }
        .loader-inline { display: inline-block; width: 14px; height: 14px; border: 2px solid rgba(14,165,233,0.3); border-radius: 50%; border-top-color: var(--primary); animation: spin 0.8s linear infinite; vertical-align: middle; }
        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

        /* Alert */
        .alert { padding: 18px; border-radius: 12px; margin-bottom: 25px; display: flex; align-items: center; gap: 12px; }
        .alert-error { background: rgba(239,68,68,0.1); color: var(--accent-red); border: 1px solid rgba(239,68,68,0.2); }

        /* Code */
        pre { background: #000; padding: 18px; border-radius: 12px; border: 1px solid var(--border); font-size: 0.8rem; color: #10b981; max-height: 350px; overflow-y: auto; font-family: 'JetBrains Mono', monospace; }

        /* Responsive */
        @media(max-width:768px) {
            .input-group { flex-direction: column; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .table-header { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
<div class="container" id="app">
    <header style="position: relative;">
        <div style="position: absolute; right: 0; top: 0;">
            <a href="banco-precos.html" style="color: var(--primary); text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 8px; background: rgba(14,165,233,0.1); padding: 8px 16px; border-radius: 8px; transition: 0.3s; border: 1px solid rgba(14,165,233,0.2);">
                <i class="fas fa-database"></i> Acessar Banco de Preços
            </a>
        </div>
        <div class="logo">
            <i class="fa-solid fa-cube"></i>
            <span>LICITADOR PRO</span>
            <span class="badge">+ CATMAT</span>
        </div>
        <p>Extrator inteligente com correspondência automática de códigos CATMAT</p>
    </header>

    <div class="glass-card">
        <h3 style="margin-bottom: 12px;">Iniciar Extração</h3>
        <form method="post" id="scrapForm" onsubmit="showLoader()">
            <label for="url" style="color: var(--text-muted); font-size: 0.85rem;">Insira a URL do detalhamento do processo:</label>
            <div class="input-group">
                <input type="url" name="url" id="url" value="<?php echo htmlspecialchars($url); ?>"
                       placeholder="https://www.portaldecompraspublicas.com.br/... ou https://www.licitanet.com.br/sessao/..." >
                <button type="submit" class="btn-primary" id="btnExtrair">
                    <i class="fa-solid fa-bolt"></i>
                    <span>Extrair Agora</span>
                    <div class="loader" id="loader"></div>
                </button>
            </div>

            <!-- Modo alternativo: Extração manual para Licitanet -->
            <div style="margin-top: 16px;">
                <button type="button" onclick="togglePasteMode()" class="btn-outline" style="font-size: 0.82rem; gap: 6px;">
                    <i class="fa-solid fa-paste"></i>
                    <span id="pasteBtnText">Licitanet bloqueada? Extraia os dados manualmente</span>
                    <i class="fa-solid fa-chevron-down" id="pasteChevron" style="font-size:0.65rem; transition: transform 0.3s;"></i>
                </button>
                <div id="pasteSection" style="display: none; margin-top: 12px; animation: fadeIn 0.3s ease;">
                    
                    <!-- Tabs -->
                    <div style="display:flex;gap:0;margin-bottom:0;">
                        <button type="button" onclick="switchPasteTab('console')" id="tabConsole" style="flex:1;padding:10px;background:var(--primary);color:#fff;border:none;border-radius:10px 10px 0 0;font-weight:600;font-size:0.82rem;cursor:pointer;">
                            <i class="fa-solid fa-terminal"></i> Via Console (Recomendado)
                        </button>
                        <button type="button" onclick="switchPasteTab('source')" id="tabSource" style="flex:1;padding:10px;background:rgba(255,255,255,0.05);color:var(--text-muted);border:none;border-radius:10px 10px 0 0;font-size:0.82rem;cursor:pointer;">
                            <i class="fa-solid fa-code"></i> Via Código-Fonte
                        </button>
                    </div>

                    <!-- Tab Console (Recomendado - pega melhor lance) -->
                    <div id="panelConsole" style="background:rgba(14,165,233,0.05);border:1px solid rgba(14,165,233,0.2);border-top:none;border-radius:0 0 10px 10px;padding:16px;">
                        <div style="background:rgba(74,222,128,0.08);border:1px solid rgba(74,222,128,0.2);border-radius:8px;padding:12px;margin-bottom:12px;">
                            <p style="color:var(--accent-green);font-size:0.82rem;margin:0;">
                                <i class="fa-solid fa-star"></i>
                                <strong>Melhor método!</strong> Captura itens + melhor lance + todos os dados.
                            </p>
                        </div>
                        <ol style="color:var(--text-muted);font-size:0.82rem;padding-left:20px;margin-bottom:12px;line-height:2;">
                            <li>Abra a página da Licitanet no navegador</li>
                            <li>Pressione <kbd style="background:rgba(255,255,255,0.1);padding:2px 6px;border-radius:4px;">F12</kbd> → Aba <strong>Console</strong></li>
                            <li>Copie o script abaixo e cole no console → Enter</li>
                            <li>Cole o resultado aqui embaixo (Ctrl+V)</li>
                        </ol>
                        <div style="position:relative;">
                            <pre id="consoleScript" style="background:#000;color:#0f0;padding:12px;border-radius:8px;font-size:0.72rem;max-height:120px;overflow:auto;cursor:pointer;border:1px solid var(--border);" onclick="copiarScript()" title="Clique para copiar">(function(){var d=JSON.parse(document.getElementById('app').getAttribute('data-page'));var r=d.props.disputeRoom;var items=r.items.data||r.items;var lances=[];document.querySelectorAll('p').forEach(function(p){if(p.textContent.trim()==='Melhor Lance'){var v=p.nextElementSibling;if(v)lances.push(v.textContent.trim());}});var result=items.map(function(it,i){return{numero:it.batch,descricao:it.name,quantidade:it.quantity,unidade:it.unit,valor_referencia:it.estimatedValue,melhor_lance:lances[i]||'R$ 0,00',status:r.status};});var out=JSON.stringify({tipo:'licitanet_console',meta:{orgao:r.buyer,objeto:r.description,numero_processo:r.number,data_sessao:r.startDate},itens:result});copy(out);console.log('✅ '+result.length+' itens copiados! Cole no Licitador Pro (Ctrl+V).');})();</pre>
                            <button type="button" onclick="copiarScript()" style="position:absolute;top:8px;right:8px;background:var(--primary);color:#fff;border:none;padding:4px 10px;border-radius:6px;font-size:0.75rem;cursor:pointer;">
                                <i class="fa-solid fa-copy"></i> Copiar Script
                            </button>
                        </div>
                    </div>

                    <!-- Tab Código-Fonte (fallback) -->
                    <div id="panelSource" style="display:none;background:rgba(251,191,36,0.05);border:1px solid rgba(251,191,36,0.2);border-top:none;border-radius:0 0 10px 10px;padding:16px;">
                        <div style="background:rgba(251,191,36,0.08);border:1px solid rgba(251,191,36,0.2);border-radius:8px;padding:12px;margin-bottom:12px;">
                            <p style="color:var(--accent-amber);font-size:0.82rem;margin:0;">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                <strong>Atenção:</strong> Este método NÃO captura o Melhor Lance (ele é renderizado dinamicamente).
                            </p>
                        </div>
                        <p style="color:var(--text-muted);font-size:0.82rem;margin-bottom:12px;">
                            <kbd style="background:rgba(255,255,255,0.1);padding:2px 6px;border-radius:4px;">Ctrl+U</kbd> → 
                            <kbd style="background:rgba(255,255,255,0.1);padding:2px 6px;border-radius:4px;">Ctrl+A</kbd> → 
                            <kbd style="background:rgba(255,255,255,0.1);padding:2px 6px;border-radius:4px;">Ctrl+C</kbd> → Cole abaixo
                        </p>
                    </div>

                    <!-- Campo de colagem (compartilhado) -->
                    <textarea name="html_colado" id="htmlColado" rows="5" 
                              placeholder="Cole aqui o resultado do script do console ou o código-fonte da página..."
                              style="width:100%;padding:14px;border-radius:12px;border:1px solid var(--border);background:rgba(15,23,42,0.5);color:#94a3b8;font-size:0.82rem;font-family:'JetBrains Mono',monospace;resize:vertical;outline:none;transition:border-color 0.3s;margin-top:12px;"
                              onfocus="this.style.borderColor='var(--primary)'"
                              onblur="this.style.borderColor='var(--border)'"
                    ></textarea>
                    <button type="submit" class="btn-primary" style="margin-top: 10px; background: linear-gradient(135deg, #10b981, #059669);" onclick="document.getElementById('url').removeAttribute('required')">
                        <i class="fa-solid fa-bolt"></i>
                        <span>Processar Dados</span>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span><?php echo $error; ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($itens)): ?>
        <div class="stats-grid">
            <div class="stat-card">
                <span class="label">Itens Extraídos</span>
                <span class="value"><?php echo count($itens); ?></span>
            </div>
            <div class="stat-card">
                <span class="label">Valor Total Estimado</span>
                <span class="value">R$ <?php echo number_format($valorTotalRef, 2, ',', '.'); ?></span>
            </div>
            <div class="stat-card">
                <span class="label">Progresso CATMAT</span>
                <span class="value" id="progressoTexto" style="font-size: 0.9rem;">0 / <?php echo count($itens); ?></span>
                <div style="margin-top:6px;background:rgba(255,255,255,0.08);border-radius:20px;height:6px;overflow:hidden;">
                    <div id="progressoBarra" style="width:0%;height:100%;background:linear-gradient(90deg,var(--accent-green),#22d3ee);border-radius:20px;transition:width 0.4s ease;"></div>
                </div>
            </div>
            <div class="stat-card">
                <span class="label">Método de Captura</span>
                <span class="value" style="font-size: 0.9rem;"><?php echo $metodoUtilizado; ?></span>
            </div>
            <div class="stat-card">
                <span class="label">Tempo</span>
                <span class="value"><?php echo $tempoExecucao; ?>s</span>
            </div>
        </div>

        <!-- Banner de progresso restaurado (aparece se tiver sessão salva) -->
        <div id="bannerRestaurado" style="display:none;padding:14px 20px;border-radius:12px;margin-bottom:20px;background:rgba(74,222,128,0.08);border:1px solid rgba(74,222,128,0.2);display:none;align-items:center;gap:12px;justify-content:space-between;flex-wrap:wrap;">
            <div style="display:flex;align-items:center;gap:10px;">
                <i class="fa-solid fa-rotate-left" style="color:var(--accent-green);font-size:1.1rem;"></i>
                <span style="font-size:0.88rem;"><strong>Sessão restaurada!</strong> <span id="restauradoInfo"></span></span>
            </div>
            <button onclick="limparProgresso()" class="btn-outline" style="font-size:0.78rem;padding:5px 12px;border-color:var(--accent-red);color:var(--accent-red);">
                <i class="fa-solid fa-trash-can"></i> Limpar e recomeçar
            </button>
        </div>

        <div class="glass-card" style="padding: 0;">
            <div class="table-header">
                <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                    <h3>Lista de Itens</h3>
                    <div style="position: relative;">
                        <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:0.75rem;"></i>
                        <input type="text" id="tableSearch" placeholder="Filtrar itens..."
                               style="padding:7px 10px 7px 32px;border-radius:8px;border:1px solid var(--border);background:rgba(0,0,0,0.2);color:#fff;font-size:0.82rem;width:220px;">
                    </div>
                </div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <button onclick="buscarCatmatTodos()" class="btn-outline" id="btnCatmatAll">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> Buscar CATMAT (Todos)
                    </button>
                    <button onclick="abrirModalGrade()" class="btn-outline" style="border-color:var(--accent-green);color:var(--accent-green);" id="btnSalvarGrade">
                        <i class="fa-solid fa-floppy-disk"></i> Salvar como Grade
                    </button>
                    <button onclick="exportToCSV()" class="btn-outline">
                        <i class="fa-solid fa-file-csv"></i> Baixar CSV
                    </button>
                    <button onclick="downloadJSON()" class="btn-outline">
                        <i class="fa-solid fa-file-code"></i> Baixar JSON
                    </button>
                    <button onclick="copyJSON()" class="btn-outline" title="Copiar JSON para o clipboard">
                        <i class="fa-solid fa-copy"></i> Copiar
                    </button>
                </div>
            </div>
            <div class="table-wrap">
                <table id="itensTable">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Status</th>
                            <th>Descrição</th>
                            <th>Qtd / Unid</th>
                            <th>Valor Ref.</th>
                            <th>Melhor Lance</th>
                            <th>Valor Total</th>
                            <th>CATMAT</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($itens as $idx => $item): ?>
                            <?php
                                $qtdStr = $item['quantidade'] ?? '0';
                                $qtd = (strpos($qtdStr, ',') !== false) 
                                    ? floatval(str_replace(',', '.', str_replace('.', '', $qtdStr))) 
                                    : floatval($qtdStr);
                                $valUnit = floatval($item['valor_referencia'] ?? 0);
                                $valMelhorLance = floatval($item['melhor_lance'] ?? 0);
                                $valTotal = $qtd * $valUnit;
                            ?>
                            <tr data-idx="<?php echo $idx; ?>" data-desc="<?php echo htmlspecialchars($item['descricao']); ?>">
                                <td style="font-weight:700;color:var(--primary);">#<?php echo htmlspecialchars($item['numero']); ?></td>
                                <td><span class="status-badge"><?php echo htmlspecialchars($item['status']); ?></span></td>
                                <td style="min-width:350px;max-width:500px;">
                                    <?php 
                                        $descFull = htmlspecialchars($item['descricao']);
                                        $descShort = htmlspecialchars(mb_substr($item['descricao'], 0, 120));
                                        $isLong = mb_strlen($item['descricao']) > 120;
                                    ?>
                                    <div class="desc-text">
                                        <span id="desc-short-<?php echo $idx; ?>"><?php echo $descShort; ?><?php if($isLong): ?>...
                                            <button class="desc-toggle" onclick="toggleDesc(<?php echo $idx; ?>, true)">ver mais ▼</button>
                                        <?php endif; ?></span>
                                        <span id="desc-full-<?php echo $idx; ?>" style="display:none;"><?php echo $descFull; ?>
                                            <button class="desc-toggle" onclick="toggleDesc(<?php echo $idx; ?>, false)">ver menos ▲</button>
                                        </span>
                                    </div>
                                    <div id="catmat-panel-<?php echo $idx; ?>"></div>
                                </td>
                                <td>
                                    <div style="font-weight:600;"><?php echo htmlspecialchars($item['quantidade']); ?></div>
                                    <div style="font-size:0.75rem;color:var(--text-muted);"><?php echo htmlspecialchars($item['unidade'] ?? 'Unid'); ?></div>
                                </td>
                                <td class="val">R$ <?php echo number_format($valUnit, 2, ',', '.'); ?></td>
                                <td class="val" style="color:var(--accent-amber);">R$ <?php echo number_format($valMelhorLance, 2, ',', '.'); ?></td>
                                <td class="val" style="color:var(--accent-green);">R$ <?php echo number_format($valTotal, 2, ',', '.'); ?></td>
                                <td id="catmat-col-<?php echo $idx; ?>">
                                    <button class="btn-sm" onclick="buscarCatmat(<?php echo $idx; ?>)">
                                        <i class="fa-solid fa-magnifying-glass"></i> Buscar
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="glass-card" style="margin-top:30px;">
            <h3 style="margin-bottom:12px;">JSON de Integração</h3>
            <pre id="jsonOutput"><?php echo json_encode($itens, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
        </div>
    <?php endif; ?>
</div>

<script>
    const allItems = <?php echo json_encode($itens, JSON_UNESCAPED_UNICODE); ?>;
    const currentUrl = <?php echo json_encode($url); ?>;
    const extractMeta = <?php echo json_encode($meta, JSON_UNESCAPED_UNICODE); ?>;
    
    // Identificador único para o cache local (evita colisões em URLs longas)
    const urlHash = currentUrl ? btoa(unescape(encodeURIComponent(currentUrl))).replace(/[/+=]/g, '').slice(-50) : 'default';
    const STORAGE_KEY = 'licitapro_v2_' + urlHash;
    
    let selectedCatmat = carregarProgresso(); // Restaura do localStorage se existir

    function showLoader() {
        document.getElementById('loader').style.display = 'inline-block';
        document.querySelector('#btnExtrair span').innerText = 'Capturando...';
    }

    function togglePasteMode() {
        const section = document.getElementById('pasteSection');
        const chevron = document.getElementById('pasteChevron');
        const isHidden = section.style.display === 'none';
        section.style.display = isHidden ? 'block' : 'none';
        chevron.style.transform = isHidden ? 'rotate(180deg)' : '';
    }

    function switchPasteTab(tab) {
        const tabConsole = document.getElementById('tabConsole');
        const tabSource = document.getElementById('tabSource');
        const panelConsole = document.getElementById('panelConsole');
        const panelSource = document.getElementById('panelSource');
        if (tab === 'console') {
            tabConsole.style.background = 'var(--primary)'; tabConsole.style.color = '#fff'; tabConsole.style.fontWeight = '600';
            tabSource.style.background = 'rgba(255,255,255,0.05)'; tabSource.style.color = 'var(--text-muted)'; tabSource.style.fontWeight = 'normal';
            panelConsole.style.display = 'block'; panelSource.style.display = 'none';
        } else {
            tabSource.style.background = 'var(--accent-amber)'; tabSource.style.color = '#000'; tabSource.style.fontWeight = '600';
            tabConsole.style.background = 'rgba(255,255,255,0.05)'; tabConsole.style.color = 'var(--text-muted)'; tabConsole.style.fontWeight = 'normal';
            panelSource.style.display = 'block'; panelConsole.style.display = 'none';
        }
    }

    function copiarScript() {
        const script = document.getElementById('consoleScript').textContent;
        navigator.clipboard.writeText(script).then(() => {
            const btn = event.currentTarget;
            const orig = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-check"></i> Copiado!';
            setTimeout(() => { btn.innerHTML = orig; }, 2000);
        });
    }

    // --- CATMAT: Busca individual ---
    async function buscarCatmat(idx, limite = 10, consultaManual = null) {
        const row = document.querySelector(`tr[data-idx="${idx}"]`);
        const descOriginal = row.getAttribute('data-desc');
        const consulta = consultaManual || descOriginal;
        const panel = document.getElementById(`catmat-panel-${idx}`);
        
        // Mantém o valor do input se for busca manual
        const inputVal = consultaManual || descOriginal;

        // Se for a primeira busca ou busca de 'Ver mais', mantemos o container mas mostramos loader
        const suggestionsContainer = panel.querySelector('.catmat-suggestions-list');
        if (!suggestionsContainer) {
            panel.innerHTML = `
                <div class="catmat-panel">
                    <div class="catmat-search-container">
                        <i class="fa-solid fa-magnifying-glass" style="color:var(--text-muted);align-self:center;margin-left:4px;"></i>
                        <input type="text" class="catmat-search-input" value="${inputVal}" id="input-catmat-${idx}" placeholder="Pesquisar manual...">
                        <button class="catmat-search-btn" onclick="buscarCatmat(${idx}, 10, document.getElementById('input-catmat-${idx}').value)">Buscar</button>
                    </div>
                    <div class="catmat-suggestions-list">
                        <div class="loader-inline" style="margin:8px 0;"></div> <span style="font-size:0.78rem;color:var(--text-muted);">Buscando CATMAT...</span>
                    </div>
                </div>`;
        } else {
            suggestionsContainer.innerHTML = '<div class="loader-inline" style="margin:8px 0;"></div> <span style="font-size:0.78rem;color:var(--text-muted);">Buscando...</span>';
        }

        try {
            const resp = await fetch('api/catmat.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ descricao: consulta, limite: limite })
            });
            const data = await resp.json();
            renderCatmatPanel(panel, data.sugestoes || [], idx, limite, consulta);
        } catch (e) {
            const list = panel.querySelector('.catmat-suggestions-list');
            if (list) list.innerHTML = '<div style="color:var(--accent-red);font-size:0.78rem;margin-top:6px;"><i class="fa-solid fa-circle-xmark"></i> Erro na busca</div>';
        }
    }

    // --- CATMAT: Busca em lote ---
    async function buscarCatmatTodos() {
        const btn = document.getElementById('btnCatmatAll');
        btn.innerHTML = '<div class="loader-inline"></div> Processando...';
        btn.disabled = true;

        for (let i = 0; i < allItems.length; i++) {
            await buscarCatmat(i);
            // Pequeno delay para não sobrecarregar
            await new Promise(r => setTimeout(r, 200));
        }

        btn.innerHTML = '<i class="fa-solid fa-check"></i> Concluído!';
        btn.style.borderColor = 'var(--accent-green)';
        btn.style.color = 'var(--accent-green)';
    }

    // --- Expandir/colapsar descrição do item ---
    function toggleDesc(idx, expand) {
        document.getElementById(`desc-short-${idx}`).style.display = expand ? 'none' : '';
        document.getElementById(`desc-full-${idx}`).style.display = expand ? '' : 'none';
    }

    // --- Renderizar painel de sugestões CATMAT ---
    function renderCatmatPanel(container, sugestoes, itemIdx, limiteAtual = 10, consultaAtual = '') {
        const listContainer = container.querySelector('.catmat-suggestions-list');
        if (!listContainer) return; // Segurança

        if (!sugestoes.length) {
            listContainer.innerHTML = '<div style="color:var(--text-muted);font-size:0.75rem;margin-top:6px;">Nenhuma correspondência encontrada. Tente palavras-chave mais simples.</div>';
            return;
        }

        let html = '';
        sugestoes.forEach((s) => {
            const pct = (s.similaridade * 100).toFixed(0);
            let cls = 'score-low';
            if (pct >= 30) cls = 'score-high';
            else if (pct >= 15) cls = 'score-medium';
            const isSelected = selectedCatmat[itemIdx]?.codigo == s.codigo_catmat;
            
            html += `<div class="catmat-suggestion${isSelected ? ' selected' : ''}" 
                         onclick="selecionarCatmat(${itemIdx}, '${s.codigo_catmat}', this)" 
                         title="Clique para selecionar este código">
                <div class="catmat-top">
                    <span class="catmat-code">${s.codigo_catmat}</span>
                    <span class="catmat-score ${cls}">${pct}%</span>
                </div>
                <span class="catmat-desc">${s.descricao}</span>
            </div>`;
        });

        // Botão para carregar mais
        if (sugestoes.length === limiteAtual && limiteAtual < 50) {
            const proximoLimite = limiteAtual + 10;
            html += `
                <div style="text-align:center;margin-top:10px;">
                    <button class="btn-sm" style="width:100%;background:rgba(255,255,255,0.03);border:1px dashed var(--border);" 
                            onclick="buscarCatmat(${itemIdx}, ${proximoLimite}, '${consultaAtual.replace(/'/g, "\\'")}')">
                        <i class="fa-solid fa-plus"></i> Ver mais sugestões (+10)...
                    </button>
                </div>
            `;
        }

        listContainer.innerHTML = html;
    }

    // --- Selecionar CATMAT para um item ---
    function selecionarCatmat(itemIdx, codigo, el) {
        // Guarda a seleção
        const descEl = el.querySelector('.catmat-desc');
        selectedCatmat[itemIdx] = { 
            codigo: codigo, 
            descricao: descEl ? descEl.innerText : '' 
        };

        // Atualiza a coluna CATMAT da tabela
        aplicarSelecaoVisual(itemIdx, codigo);

        // Fecha o painel de sugestões
        const panel = document.getElementById(`catmat-panel-${itemIdx}`);
        panel.innerHTML = '';

        // Auto-save no localStorage
        salvarProgresso();

        // Atualiza barra de progresso
        atualizarProgresso();

        // Atualiza o JSON Output
        atualizarJsonOutput();

        // Copia para clipboard
        navigator.clipboard.writeText(codigo);
    }

    function aplicarSelecaoVisual(itemIdx, codigo) {
        const col = document.getElementById(`catmat-col-${itemIdx}`);
        if (!col) return;
        col.innerHTML = `
            <div class="catmat-chosen"><i class="fa-solid fa-check"></i> ${codigo}</div>
            <button class="btn-sm" style="margin-top:6px;" onclick="buscarCatmat(${itemIdx})">
                <i class="fa-solid fa-arrows-rotate"></i> Alterar
            </button>
        `;
    }

    // --- AUTO-SAVE: localStorage ---
    function salvarProgresso() {
        if (!currentUrl) return; // Não salva se não houver URL
        const dados = {
            selectedCatmat: selectedCatmat,
            url: currentUrl,
            timestamp: new Date().toISOString(),
            totalItens: allItems.length,
        };
        try { localStorage.setItem(STORAGE_KEY, JSON.stringify(dados)); } catch(e) {}
    }

    function carregarProgresso() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return {};
            const dados = JSON.parse(raw);
            return dados.selectedCatmat || {};
        } catch(e) { return {}; }
    }

    function restaurarProgressoVisual() {
        const total = Object.keys(selectedCatmat).length;
        if (total === 0) return;

        // Restaura visual de cada item selecionado
        for (const [idx, catmat] of Object.entries(selectedCatmat)) {
            aplicarSelecaoVisual(idx, catmat.codigo);
        }

        // Mostra banner de restauração
        const banner = document.getElementById('bannerRestaurado');
        if (banner) {
            banner.style.display = 'flex';
            const raw = localStorage.getItem(STORAGE_KEY);
            const dados = JSON.parse(raw);
            const quando = new Date(dados.timestamp);
            const horaStr = quando.toLocaleString('pt-BR', {day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'});
            document.getElementById('restauradoInfo').textContent = 
                `${total} de ${allItems.length} itens codificados. Último salvo em ${horaStr}.`;
        }

        atualizarProgresso();
    }

    function atualizarProgresso() {
        const total = Object.keys(selectedCatmat).length;
        const pct = allItems.length > 0 ? Math.round((total / allItems.length) * 100) : 0;
        const textoEl = document.getElementById('progressoTexto');
        const barraEl = document.getElementById('progressoBarra');
        if (textoEl) textoEl.textContent = `${total} / ${allItems.length} (${pct}%)`;
        if (barraEl) barraEl.style.width = pct + '%';
    }

    function limparProgresso(confirmar = true) {
        if (confirmar && !confirm('Tem certeza? Isso vai apagar todas as seleções CATMAT desta sessão.')) return;
        localStorage.removeItem(STORAGE_KEY);
        selectedCatmat = {};
        // Restaura botões originais
        allItems.forEach((_, idx) => {
            const col = document.getElementById(`catmat-col-${idx}`);
            if (col) col.innerHTML = `<button class="btn-sm" onclick="buscarCatmat(${idx})"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button>`;
            const panel = document.getElementById(`catmat-panel-${idx}`);
            if (panel) panel.innerHTML = '';
        });
        const banner = document.getElementById('bannerRestaurado');
        if (banner) banner.style.display = 'none';
        atualizarProgresso();
        atualizarJsonOutput();
    }

    // --- Gera Itens Enriquecidos com CATMAT ---
    function getEnrichedItems() {
        return allItems.map((item, idx) => {
            const catmat = selectedCatmat[idx];
            return {
                ...item,
                codigo_catmat: catmat ? catmat.codigo : null,
                descricao_catmat: catmat ? catmat.descricao : null
            };
        });
    }

    function atualizarJsonOutput() {
        const enriched = getEnrichedItems();
        const el = document.getElementById('jsonOutput');
        if (el) el.innerText = JSON.stringify(enriched, null, 4);
    }

    function copyJSON() {
        const enriched = getEnrichedItems();
        const code = JSON.stringify(enriched, null, 4);
        navigator.clipboard.writeText(code).then(() => {
            const btn = event.currentTarget;
            const orig = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-check"></i> Copiado!';
            btn.style.borderColor = 'var(--accent-green)';
            btn.style.color = 'var(--accent-green)';
            setTimeout(() => { btn.innerHTML = orig; btn.style.borderColor=''; btn.style.color=''; }, 2000);
        });
    }

    function downloadJSON() {
        const enriched = getEnrichedItems();
        const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(enriched, null, 4));
        const downloadAnchorNode = document.createElement('a');
        downloadAnchorNode.setAttribute("href",     dataStr);
        downloadAnchorNode.setAttribute("download", "licitacao.json");
        document.body.appendChild(downloadAnchorNode);
        downloadAnchorNode.click();
        downloadAnchorNode.remove();
    }

    // --- Restaurar ao carregar a página ---
    if (allItems.length > 0) {
        restaurarProgressoVisual();
        atualizarJsonOutput();
    }

    // --- CSV Export ---
    function exportToCSV() {
        if (!allItems.length) return;
        let csv = "\uFEFF";
        csv += "Item;Status;Descricao;Quantidade;Unidade;Valor_Referencia;Melhor_Lance;Cod_CATMAT;Desc_CATMAT\r\n";
        
        getEnrichedItems().forEach(item => {
            let row = [
                item.numero, item.status,
                `"${(item.descricao||'').replace(/"/g,'""').replace(/(\r\n|\n|\r)/gm,' ')}"`,
                item.quantidade, item.unidade || '',
                (item.valor_referencia||'').toString().replace('.',','),
                (item.melhor_lance||'').toString().replace('.',','),
                item.codigo_catmat || '',
                `"${(item.descricao_catmat||'').replace(/"/g,'""').replace(/(\r\n|\n|\r)/gm,' ')}"`
            ].join(";");
            csv += row + "\r\n";
        });
        const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'licitacao_itens.csv';
        document.body.appendChild(link); link.click(); document.body.removeChild(link);
    }

    // --- JSON Copy ---
    function copyJSON() {
        const code = document.getElementById('jsonOutput').innerText;
        navigator.clipboard.writeText(code).then(() => {
            const btn = event.currentTarget;
            const orig = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-check"></i> Copiado!';
            btn.style.borderColor = 'var(--accent-green)';
            btn.style.color = 'var(--accent-green)';
            setTimeout(() => { btn.innerHTML = orig; btn.style.borderColor=''; btn.style.color=''; }, 2000);
        });
    }

    // --- Table Filter ---
    document.getElementById('tableSearch')?.addEventListener('keyup', function() {
        const term = this.value.toLowerCase();
        document.querySelectorAll('#itensTable tbody tr').forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(term) ? '' : 'none';
        });
    });

    // --- Modal: Salvar como Grade ---
    function abrirModalGrade() {
        // Conta quantos itens têm CATMAT selecionado
        const comCatmat = Object.keys(selectedCatmat).length;
        const total = allItems.length;
        
        const modal = document.createElement('div');
        modal.id = 'modalGrade';
        modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);display:flex;align-items:center;justify-content:center;z-index:9999;backdrop-filter:blur(4px);';
        modal.innerHTML = `
            <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:30px;max-width:520px;width:90%;animation:fadeIn 0.3s ease;">
                <h3 style="margin-bottom:6px;"><i class="fa-solid fa-floppy-disk" style="color:var(--accent-green);"></i> Salvar como Grade</h3>
                <p style="color:var(--text-muted);font-size:0.85rem;margin-bottom:20px;">
                    ${total} itens serão salvos no Supabase. <strong>${comCatmat}</strong> possuem código CATMAT vinculado.
                </p>
                <div style="margin-bottom:14px;">
                    <label style="color:var(--text-muted);font-size:0.82rem;display:block;margin-bottom:4px;">Nome da Grade *</label>
                    <input type="text" id="gradeNome" placeholder="Ex: Alimentação Escolar - Pequeri 2026" 
                           style="width:100%;padding:12px 16px;border-radius:10px;border:1px solid var(--border);background:rgba(0,0,0,0.3);color:#fff;font-size:0.9rem;">
                </div>
                <div style="display:flex;gap:10px;margin-bottom:14px;">
                    <div style="flex:1;">
                        <label style="color:var(--text-muted);font-size:0.82rem;display:block;margin-bottom:4px;">Órgão</label>
                        <input type="text" id="gradeOrgao" value="${extractMeta.orgao || ''}" placeholder="Nome do Órgão" style="width:100%;padding:10px;border-radius:10px;border:1px solid var(--border);background:rgba(0,0,0,0.3);color:#fff;font-size:0.85rem;">
                    </div>
                    <div style="width:140px;">
                        <label style="color:var(--text-muted);font-size:0.82rem;display:block;margin-bottom:4px;">Processo</label>
                        <input type="text" id="gradeProcesso" value="${extractMeta.numero_processo || ''}" placeholder="Ex: 001/2026" style="width:100%;padding:10px;border-radius:10px;border:1px solid var(--border);background:rgba(0,0,0,0.3);color:#fff;font-size:0.85rem;">
                    </div>
                </div>
                <div style="display:flex;gap:10px;margin-bottom:14px;">
                    <div style="flex:1;">
                        <label style="color:var(--text-muted);font-size:0.82rem;display:block;margin-bottom:4px;">Objeto</label>
                        <input type="text" id="gradeObjeto" value="${extractMeta.objeto || ''}" placeholder="Objeto da Licitação" style="width:100%;padding:10px;border-radius:10px;border:1px solid var(--border);background:rgba(0,0,0,0.3);color:#fff;font-size:0.85rem;">
                    </div>
                    <div style="width:180px;">
                        <label style="color:var(--text-muted);font-size:0.82rem;display:block;margin-bottom:4px;">Data/Hora</label>
                        <input type="text" id="gradeDataSessao" value="${extractMeta.data_sessao || ''}" placeholder="Ex: 2026-04-01T16:00:00" style="width:100%;padding:10px;border-radius:10px;border:1px solid var(--border);background:rgba(0,0,0,0.3);color:#fff;font-size:0.85rem;">
                    </div>
                </div>
                <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;">
                    <button onclick="fecharModal()" class="btn-outline">Cancelar</button>
                    <button onclick="salvarGrade()" class="btn-primary" id="btnConfirmarGrade">
                        <i class="fa-solid fa-cloud-arrow-up"></i> Salvar no Supabase
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        document.getElementById('gradeNome').focus();
    }

    function fecharModal() {
        const m = document.getElementById('modalGrade');
        if (m) m.remove();
    }

    async function salvarGrade() {
        const nome = document.getElementById('gradeNome').value.trim();
        const orgao = document.getElementById('gradeOrgao').value.trim();
        const objeto = document.getElementById('gradeObjeto').value.trim();
        const numero_processo = document.getElementById('gradeProcesso').value.trim();
        const data_sessao = document.getElementById('gradeDataSessao').value.trim();
        
        const btn = document.getElementById('btnConfirmarGrade');

        if (!nome) {
            document.getElementById('gradeNome').style.borderColor = 'var(--accent-red)';
            return;
        }

        btn.innerHTML = '<div class="loader-inline"></div> Salvando...';
        btn.disabled = true;

        // Monta payload com CATMAT selecionados
        const itensPayload = allItems.map((item, idx) => {
            const catmat = selectedCatmat[idx];
            return {
                numero: item.numero,
                descricao_portal: item.descricao,
                descricao_catmat: catmat ? catmat.descricao : null,
                codigo_catmat: catmat ? catmat.codigo : null,
                quantidade: item.quantidade,
                unidade: item.unidade || 'UN',
                valor_referencia: item.valor_referencia || 0,
                melhor_lance: item.melhor_lance || 0,
                status: item.status || 'N/A',
            };
        });

        try {
            const resp = await fetch('api/grade.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    nome: nome,
                    orgao: orgao,
                    objeto: objeto,
                    numero_processo: numero_processo,
                    data_sessao: data_sessao,
                    url_origem: document.getElementById('url').value,
                    processo_id: '<?php echo $extrator->extrairProcessoId($url) ?? ""; ?>',
                    itens: itensPayload,
                })
            });
            const data = await resp.json();

            if (data.sucesso) {
                btn.innerHTML = '<i class="fa-solid fa-check"></i> Salvo!';
                btn.style.background = 'var(--accent-green)';
                btn.style.color = '#000';
                
                setTimeout(() => {
                    fecharModal();
                    // Atualiza o botão principal
                    const mainBtn = document.getElementById('btnSalvarGrade');
                    mainBtn.innerHTML = '<i class="fa-solid fa-check"></i> Grade Salva (ID: ' + data.grade_id + ')';
                    mainBtn.style.background = 'rgba(74,222,128,0.15)';

                    // Limpa DOM e Memória
                    limparProgresso(false);
                    const tableBody = document.querySelector('#itensTable tbody');
                    if (tableBody) tableBody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:40px;color:var(--text-muted);">Grade salva com sucesso. Inicie uma nova captura.</td></tr>';
                    const stats = document.querySelector('.stats-container');
                    if (stats) stats.style.opacity = '0.3';
                }, 1500);
            } else {
                btn.innerHTML = '<i class="fa-solid fa-xmark"></i> Erro';
                btn.style.borderColor = 'var(--accent-red)';
                alert('Erro: ' + (data.mensagem || data.error));
                setTimeout(() => {
                    btn.innerHTML = '<i class="fa-solid fa-cloud-arrow-up"></i> Salvar no Supabase';
                    btn.disabled = false;
                    btn.style.borderColor = '';
                }, 2000);
            }
        } catch (e) {
            btn.innerHTML = '<i class="fa-solid fa-xmark"></i> Erro de conexão';
            alert('Erro de conexão: ' + e.message);
            setTimeout(() => {
                btn.innerHTML = '<i class="fa-solid fa-cloud-arrow-up"></i> Salvar no Supabase';
                btn.disabled = false;
            }, 2000);
        }
    }
</script>
</body>
</html>