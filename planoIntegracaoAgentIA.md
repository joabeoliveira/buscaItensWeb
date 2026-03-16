# Plano de Implementação — Agente IA para Atribuição Automática de CATMAT

## Resumo
Criar um agente de IA que, a partir da descrição extraída do Portal de Compras Públicas, encontre automaticamente o código CATMAT mais apropriado comparando a semântica entre a descrição extraída e as descrições do catálogo CATMAT armazenadas no Supabase. O agente retornará um código sugerido com score de similaridade e fluxo de fallback para revisão humana.

## Objetivos
- Atribuição automática de CATMAT com alta precisão quando a confiança for suficiente.
- Interface de revisão para casos de baixa confiança.
- Registro de feedback para melhorar modelo/thresholds.
- Integração com as tabelas existentes no Supabase e com o fluxo do projeto.

## Escopo
- Pré-processamento das descrições extraídas.
- Geração e armazenamento de embeddings para CATMAT e descrições novas.
- Busca de similaridade vetorial (pgvector ou vector DB).
- Regras de decisão (thresholds) e integração com UI/worker.
- Telemetria e melhoria contínua.

## Arquitetura (visão geral)
1. Fonte: rotina de extração do portal grava descrições na tabela `public.descriptions` no Supabase.
2. Worker de processamento (cron/event-driven) que:
   - Normaliza texto;
   - Gera embedding da descrição;
   - Consulta tabela `catmat` (embeddings) via pgvector (nearest neighbors);
   - Decide atribuição automática / sugestão / sinalização para revisão;
   - Persiste resultado e score na tabela de origem.
3. UI de revisão (frontend) para exibir sugestões e permitir correções humanas.
4. Feedback loop: correções humanas alimentam re-treino/ajuste de thresholds.

## Modelo de Dados (Supabase / Postgres)
Sugestão de tabelas:

- catmat
  - id serial PRIMARY KEY
  - codigo text UNIQUE
  - descricao text
  - embedding vector(1536) -- ou dimensão do modelo escolhido
  - updated_at timestamptz

- descriptions (existente)
  - id
  - source_description text
  - normalized_description text
  - embedding vector(1536)
  - suggested_catmat_id int REFERENCES catmat(id)
  - similarity_score numeric
  - status text -- enum: pending, auto_assigned, review_required, manual_assigned, no_match
  - assigned_by text
  - created_at, updated_at

- annotations / feedback
  - id
  - description_id
  - previous_suggestion_catmat_id
  - corrected_catmat_id
  - user_id
  - comment
  - created_at

Índices:
- IVFFlat / HNSW em catmat.embedding para buscas rápidas:
  - CREATE INDEX ON catmat USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100);

## Pré-processamento (recomendações)
- Normalização Unicode (NFKC), lowercase.
- Remover HTML e caracteres de controle.
- Normalizar unidades e números (kg → kg, m² → m2).
- Mapear abreviações comuns (UN → unidade).
- Remover excesso de pontuação, mas manter termos técnicos.
- Opcional: expandir siglas (dicionário) e lematizar (avaliar impacto).

## Embeddings (opções)
- Serviço gerenciado:
  - OpenAI embeddings (text-embedding-3-small/large) — boa compatibilidade com PT-BR.
- Modelos locais:
  - sentence-transformers (paraphrase-multilingual-MiniLM-L12-v2, LaBSE, etc.).
- Dimensionamento:
  - Escolher um modelo com dimensionalidade compatível com pgvector; normalize embeddings (L2) para consultas por cosseno.

## Indexação e Query (pgvector)
Exemplo de consulta (cosine similarity):
- Param: embedding da descrição nova
- SQL:
```sql
SELECT id, codigo, descricao, 1 - (embedding <#> $1) AS similarity
FROM catmat
ORDER BY embedding <#> $1
LIMIT 5;
```
Interpretação: similarity = 1 - distance_cosine

## Regras de decisão (sugestões)
- top_score >= 0.80 → auto assign (status = auto_assigned)
- 0.60 <= top_score < 0.80 → suggest top-3 para revisão humana (status = review_required)
- top_score < 0.60 → marcar como no_match e enviar para rotulagem manual (status = no_match)

(Ajustar thresholds após avaliação com dados rotulados.)

## Fluxo detalhado do Worker
1. Detectar novos registros (webhook ou poll).
2. Pré-processar texto.
3. Gerar embedding.
4. Buscar top-k candidatos no catmat.
5. Avaliar top_score:
   - Se auto assign: persistir suggested_catmat_id, score e status.
   - Se revisão: persistir sugestão + enviar notificação ou colocar em fila de revisão.
6. Logar todas decisões e armazenar dados para análise.

## API / Endpoints
- POST /api/assign-catmat
  - Input: { description_id | text }
  - Output: { suggestions: [{catmat_id, codigo, descricao, score}], decision, status }

- GET /api/review/queue
  - Lista de casos para revisão humana

- POST /api/review/{description_id}
  - Correção manual e comentário

## UI de Revisão (frontend)
- Tela com lista filtrável (status = review_required/no_match).
- Para cada item: descrição original, descr. normalizada, top-3 sugestões com scores, botão para confirmar sugestão ou selecionar outro CATMAT.
- Histórico de decisões e botão "Marcar como irrelevante".

## Telemetria e Métricas
- precision@1, precision@3, recall sobre dataset rotulado.
- Distribuição de scores (para ajustar thresholds).
- Taxa de auto-assign por período.
- Tempo médio até revisão manual.
- Logs de erros e latência dos calls de embedding.

## Processo de Treinamento e Atualização
- Coletar dataset inicial de descrições rotuladas (se disponível).
- Avaliar performance offline (validação cruzada).
- Se usar classificador supervisionado: treinar modelo multiclasses (quando houver dados suficientes).
- Re-treinar periodicamente com feedback humano (batch).

## Segurança e Privacidade
- Se usar serviço externo (ex.: OpenAI), validar política de dados sensíveis.
- Criptografar chaves (env vars) e limitar acessos no Supabase.
- Audit logs para alterações manuais.

## Testes e Validação
- Unit tests:
  - Normalização de texto (casos de borda).
  - Score thresholds.
  - Integração com pgvector (mock).

- Integration tests:
  - Pipeline completo com sample dataset.

- Validar com uma amostra rotulada antes de rollout.

## Timeline sugerida (exemplo)
- Semana 1: Preparação de dados (normalização); upsert embeddings CATMAT.
- Semana 2: Implementação do worker + integração com embeddings (OpenAI ou local).
- Semana 3: Implementar lógica de decisão e endpoints API.
- Semana 4: UI de revisão mínima + testes e validação com dataset.
- Semana 5: Ajustes de thresholds e rollout gradual.

## Critérios de Aceitação
- >80% precision@1 em dataset de validação (meta inicial ajustável).
- Auto-assign funcionando sem erros em ambiente de staging.
- UI de revisão mostrando sugestões e possibilitando correções.
- Logs e métricas coletadas.

## Checklist de Implementação (tarefas)
- [ ] Criar/atualizar tabela catmat com coluna embedding e índice vetorial.
- [ ] Gerar embeddings para todo o catálogo CATMAT (batch).
- [ ] Implementar função de preprocessing.
- [ ] Implementar worker que gera embedding e consulta pgvector.
- [ ] Definir e aplicar thresholds iniciais.
- [ ] Persistir resultados e criar endpoint para consulta.
- [ ] Desenvolver UI de revisão (frontend).
- [ ] Criar mecanismo de feedback/annotations.
- [ ] Escrever testes automatizados.
- [ ] Monitoramento e dashboards (Grafana/Métricas).

## Exemplo rápido de snippet (Python + OpenAI + psycopg2)
```py
# gerar embedding
emb = openai.Embedding.create(model="text-embedding-3-small", input=[text])["data"][0]["embedding"]

# query top 5
cur.execute(
  "SELECT id, codigo, descricao, 1 - (embedding <#> %s) AS similarity FROM catmat ORDER BY embedding <#> %s LIMIT 5;",
  (emb, emb)
)
```

## Riscos e Mitigações
- Variação na qualidade das descrições: mitigar com normalização e fallback humano.
- Custo de embeddings em grande volume: avaliar custo vs. modelo local.
- Ambiguidade semântica para códigos muito granulares: usar UI e processo humano.

## Próximos passos sugeridos
1. Confirmar onde colocar o arquivo MD no repo (sugestão: docs/AI_CATMAT_IMPLEMENTATION_PLAN.md).
2. Decidir modelo de embeddings (OpenAI vs local).
3. Executar um POC com 100-500 descrições rotuladas para calibrar thresholds.
4. Iterar com feedback dos usuários.