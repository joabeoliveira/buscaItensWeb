import os
import time
import json
import requests
import numpy as np
from sentence_transformers import SentenceTransformer
from dotenv import load_dotenv

# Carrega chaves do .env local
load_dotenv()

SUPABASE_URL = os.getenv("SUPABASE_URL")
SUPABASE_KEY = os.getenv("SUPABASE_KEY")

# 1. Carregar o Modelo (Leve e Multilingue)
print("--- Carregando modelo de IA (paraphrase-multilingual-MiniLM-L12-v2) ---")
print("Isso pode demorar um pouco na primeira vez pois vai baixar o modelo (~420MB)...")
model = SentenceTransformer('paraphrase-multilingual-MiniLM-L12-v2')
print("Modelo carregado com sucesso!\n")

def process_batch(limit=100):
    """Busca itens sem embedding, gera os vetores e salva de volta"""
    
    # Busca itens que ainda não tem embedding
    url = f"{SUPABASE_URL}/rest/v1/catalogo_materiais?select=id,descricao&embedding=is.null&limit={limit}"
    headers = {
        "apikey": SUPABASE_KEY,
        "Authorization": f"Bearer {SUPABASE_KEY}",
        "Content-Type": "application/json"
    }
    
    response = requests.get(url, headers=headers)
    
    if response.status_code != 200:
        print(f"Erro ao buscar lote no Supabase: {response.text}")
        return -1
        
    items = response.json()
    
    if not items:
        return 0
    
    ids = [item['id'] for item in items]
    descriptions = [item['descricao'] for item in items]
    
    # 2. Gerar Embeddings
    print(f"Gerando embeddings para {len(descriptions)} itens...")
    embeddings = model.encode(descriptions)
    
    # 3. Salvar de volta (Lote via RPC para evitar timeouts e acelerar)
    payload_data = []
    for i, item_id in enumerate(ids):
        vector_str = "[" + ",".join(map(str, embeddings[i])) + "]"
        payload_data.append({"id": item_id, "embedding": vector_str})
        
    rpc_url = f"{SUPABASE_URL}/rest/v1/rpc/bulk_update_embeddings"
    rpc_data = {"payload": payload_data}
    
    upd_resp = requests.post(rpc_url, headers=headers, json=rpc_data)
    
    if upd_resp.status_code in [200, 204]:
        return len(ids)
    else:
        print(f"Erro no Bulk Update: {upd_resp.text}")
        return -1 # Retorna -1 para sinalizar erro transitório e não abortar

if __name__ == "__main__":
    print("--- Iniciando processamento de embeddings ---")
    total_processed = 0
    
    # Lote de 50 itens por vez (equilíbrio entre velocidade e memória)
    batch_size = 50
    
    try:
        while True:
            processed = process_batch(batch_size)
            
            if processed == 0:
                print("\n🎉 Todos os itens foram processados!")
                break
                
            if processed == -1:
                print("⏳ Aguardando 5 segundos para tentar novamente em caso de instabilidade na conexão...")
                time.sleep(5)
                continue
                
            total_processed += processed
            print(f"✅ Progresso total: {total_processed} itens")
            
            # Pequena pausa para não sobrecarregar a API do Supabase em rajadas
            time.sleep(0.1)
            
    except KeyboardInterrupt:
        print("\nProcessamento interrompido pelo usuário.")
    except Exception as e:
        print(f"Erro fatal: {e}")

