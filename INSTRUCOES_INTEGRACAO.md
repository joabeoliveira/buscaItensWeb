# Documentação de Integração: BuscaItensWeb → Algorise

Esta documentação detalha como servir os dados das grades extraídas e codificadas no **Supabase** para o sistema **Algorise (app.algorise.com.br)**.

## 🏗️ Arquitetura de Dados no Supabase

As grades são armazenadas em duas tabelas principais no Supabase que seguem o padrão do Algorise:

1.  **`grades_de_itens`**: Armazena o cabeçalho da grade (nome, descrição, URL de origem).
2.  **`grade_catmat_associados`**: Armazena os itens individuais, incluindo a descrição do portal e o **código CATMAT** selecionado.

---

## 🚀 Como consumir no Algorise

Para integrar ao Algorise, você tem duas opções principais:

### 1. Importação via Classe PHP (Recomendado)
Você pode copiar o módulo `modules/GradeManager.php` para o diretório de serviços do Algorise.

```php
// No Controller do Algorise
require_once 'GradeManager.php';

$manager = new GradeManager();

// Lista todas as grades disponíveis no rascunho
$gradesDisponiveis = $manager->listar();

// Busca os itens de uma grade específica para popular a grade do sistema
$gradePayload = $manager->buscarComItens($gradeId);

foreach ($gradePayload['grade_catmat_associados'] as $item) {
    // Insere na estrutura de dados do Algorise
    // $item['codigo_catmat'] -> O código oficial do Governo Federal
    // $item['descricao_portal'] -> Descrição extraída original
    // $item['valor_referencia'] -> Valor de referência extraído
}

// Opcional: Marcar como sincronizado para não aparecer novamente na lista de rascunhos
$manager->marcarSincronizado($gradeId);
```

### 2. Integração Direta via REST API
O Supabase fornece uma API REST nativa. Se o Algorise quiser buscar os dados via JavaScript ou diretamente de outro servidor:

**Endpoint para listar grades pendentes:**
`GET https://brdbyfpgpzrxbbfsethm.supabase.co/rest/v1/grades_de_itens?sincronizado_algorise=eq.false`

**Headers necessários:**
- `apikey`: `SUA_CHAVE_SUPABASE`
- `Authorization`: `Bearer SUA_CHAVE_SUPABASE`

---

## 📊 Estrutura de De-Para (Mapeamento)

Ao popular a grade no Algorise, use o seguinte mapeamento:

| Campo no Supabase | Destino no Algorise | Descrição |
| :--- | :--- | :--- |
| `codigo_catmat` | `item_id_externo` | Código numérico oficial do material |
| `descricao_portal` | `nome_referencia` | Texto capturado do Portal de Compras |
| `descricao_catmat` | `descricao_padronizada` | Descrição técnica do CATMAT |
| `quantidade` | `quantidade` | Volume planejado |
| `valor_referencia` | `vlr_estimado` | Valor total ou unitário extraído |

---

## 💡 Sugestão de Fluxo de Trabalho

1.  **Analista**: Acessa o `extrator.php`, cola a URL do edital, escolhe os melhores CATMATs e clica em **"Salvar como Grade"**.
2.  **Algorise**: Dentro do sistema Algorise, na tela de "Nova Grade", adicionar um botão **"Importar do Extrator"**.
3.  **Processo**: O sistema Algorise consulta `listar()` do Supabase, exibe as grades salvas hoje, e ao usuário selecionar uma, todos os itens (já codificados) entram automaticamente na grade de planejamento.

> [!TIP]
> O campo `sincronizado_algorise` é essencial para que o Algorise saiba o que já foi importado e o que ainda é um rascunho novo vindo do extrator.

---
*Documentação gerada automaticamente para o projeto BuscaItensWeb.*
