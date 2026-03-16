from flask import Flask, request, jsonify
from sentence_transformers import SentenceTransformer
import logging

logging.basicConfig(level=logging.INFO)

app = Flask(__name__)

print("--- Carregando modelo matemático da IA (isso leva apenas 2 segundos na memória) ---")
model = SentenceTransformer('paraphrase-multilingual-MiniLM-L12-v2')
print("--- Inteligência Artificial ON! Pronta para ouvir os chamados do PHP ---")

@app.route('/embed', methods=['POST'])
def get_embedding():
    try:
        data = request.get_json()
        if not data or 'texto' not in data:
            return jsonify({"error": "Parâmetro 'texto' é obrigatório"}), 400
            
        texto = data['texto']
        
        # O modelo .encode já transforma o texto em um array do NumPy
        # Convertendo isso para list (array comum do Python)
        vetor = model.encode([texto])[0].tolist()
        
        return jsonify({"embedding": vetor})
        
    except Exception as e:
        return jsonify({"error": str(e)}), 500

if __name__ == '__main__':
    # Roda esse micro-servidor na porta 5000 do localhost
    app.run(host='127.0.0.1', port=5000)
