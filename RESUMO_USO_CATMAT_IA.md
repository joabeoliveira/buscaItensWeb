# 🧠 Guia Rápido: Uso do Algorise IA - Módulo Busca CATMAT
**Versão 1.0**

## 🌐 1. O que acontece nos bastidores?
O sistema agora combina a clássica busca do banco de dados (rápida, busca pelas exatas palavras escritas nos editais) com um cérebro matemático inteligente (Lê o texto, compreende o contexto de máquina e associa aos 161.000 Catmats).

## 🚀 2. Como usar no seu dia a dia?
Quando extrair tabelas ou for buscar os códigos:
- Abra o seu extrator web em PHP.
- Pesquise **qualquer coisa**. (Sem precisar ser engessado ou exato). O sistema aceita traços (-), asteriscos (*), ou dezenas de palavras seguidas.
- A função limpará e tentará ler apenas do 1° ao 3° termo mais importantes para pesquisar primeiro no catálogo oficial (Evitando confusões extremas de Inteligência Artificial). E se mesmo assim der erro, chamará automaticamente a IA para retornar dados análogos (Mesmo significado, mas palavras que o Governo usa vs a que o pregoeiro usou).

## ⚡ 3. Ativando o Cérebro IA (Python)
Para a "Magia" Semântica de verdade funcionar conectada ao PHP (Senão ele apenas roda a Busca Textual Pura Inclusa no Supabase - Que já é ótima!):

1. **Sempre abra o seu Terminal do Windows (Bash/PowerShell)**
2. Entre na pasta raiz do site: `cd C:\xampp\htdocs\buscaItensWeb`
3. Rode o serviço Python: `python api_embeddings.py`

*O terminal vai dizer que "A Inteligência Artificial está ligada". Apenas deixe a janela preta minimizada e focada. É apenas uma interface muito leve rodando localmente (Não trava o PC, gasta só um pouquinho de RAM).*

## 🛑 O que você NÃO deve fazer (Limites)
- **Não apagar o arquivo `api_embeddings.py`:** É o comunicador Flask do PHP.
- **Não deletar do banco as colunas `embedding` ou `search_vector`.** (Eles representam 5h de vetorização de 160.000 linhas convertidas à força em matemática!)

--- 
*Se for implantar em nuvem oficial depois do projeto estar maduro em sua hospedagem Linux Ubuntu ou Docker, precisaremos empacotar essa rotina Python junto na hospedagem no mesmo servidor como ambiente permanente e fechado (`PM2 / systemd / Docker Container`). Mas agora localmente, tudo flui liso usando esse mini terminal Python ao lado.*
