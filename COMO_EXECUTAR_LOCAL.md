# Como Executar o Sistema Localmente para Testes

Este documento descreve os passos necessários para iniciar o ambiente local do sistema e poder testar todas as suas funcionalidades, como o **Extrator de Itens** e a **Busca de CATMAT com IA**.

O projeto utiliza uma arquitetura híbrida: um servidor PHP para a interface web/APIs convencionais e um microsserviço Python para o processamento de Inteligência Artificial (Embeddings).

## Passo 1: Iniciar o Servidor Web PHP

Os arquivos públicos do projeto (`index.php`, `extrator.php`, etc.) estão localizados diretamente na raiz do projeto (não em uma pasta `public`). 

1. Abra um terminal na pasta raiz do projeto (`e:\apps\buscaItensWeb`).
2. Execute o servidor embutido do PHP apontando para a pasta atual:

```bash
php -S localhost:8000
```

3. Agora você pode acessar a interface web do Extrator pelo navegador através do link:  
👉 **[http://localhost:8000/extrator.php](http://localhost:8000/extrator.php)**

---

## Passo 2: Iniciar a API de Inteligência Artificial (Python)

Para que a **busca semântica de CATMAT** (que cruza os dados com o banco Supabase Vector) funcione perfeitamente, o microsserviço em Flask precisa estar online para receber requisições do backend PHP.

1. Abra uma **nova aba de terminal** na mesma pasta raiz (`e:\apps\buscaItensWeb`).
2. Se você estiver usando um ambiente virtual (venv), ative-o.
3. Inicie a API de embeddings com o comando:

```bash
python api_embeddings.py
```

Isso fará o Flask iniciar e ficar ouvindo as requisições na porta especificada no código (geralmente `5000` ou `5001`).

---

## Resumo do Ambiente Local

Para que todas as integrações funcionem (Scraping + IA Vector Search), mantenha **ambos os terminais abertos e rodando**:
- **Terminal 1:** `php -S localhost:8000`
- **Terminal 2:** `python api_embeddings.py`

Se você precisar parar qualquer um dos servidores, basta ir no terminal correspondente e pressionar `Ctrl + C`.
