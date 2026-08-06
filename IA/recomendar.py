from flask import Flask, request, jsonify

app = Flask(__name__)

# --- ROTA 1: AVALIAÇÃO DE COMPATIBILIDADE ---
@app.route("/avaliar", methods=["POST"])
def avaliar():
    """
    Recebe dados do jovem e da vaga via JSON.
    Calcula um score de 0 a 100 baseado em critérios fixos.
    """
    data = request.json

    # Extração de dados (convertendo para minúsculo para facilitar a busca)
    formacao = data.get("formacao", "").lower()
    cidade_jovem = data.get("cidade_jovem", "").lower()
    descricao = data.get("descricao", "").lower()
    cidade_vaga = data.get("cidade_vaga", "").lower()

    score = 0
    motivos = []

    # Verificação de Formação vs Descrição da Vaga
    if formacao and formacao in descricao:
        score += 40
        motivos.append("Sua formação combina com os requisitos da vaga")

    # Verificação de Proximidade (Cidades identificadas no banco de dados)
    if cidade_jovem == cidade_vaga and cidade_jovem != "":
        score += 30
        motivos.append("A vaga está localizada na sua cidade")

    # Verificação de tipo de contrato (Estágio)
    if "estágio" in descricao:
        score += 20
        motivos.append("Esta é uma oportunidade de estágio")

    # Verificação de tipo de contrato (Jovem Aprendiz)
    if "jovem aprendiz" in descricao:
        score += 30
        motivos.append("Esta é uma vaga específica para Jovem Aprendiz")

    # Limita o score máximo a 100%
    if score > 100:
        score = 100

    # Define a mensagem final baseada no score
    mensagem = "Boa compatibilidade!" if score > 60 else "Compatibilidade média ou baixa"

    return jsonify({
        "compatibilidade": score,
        "mensagem": mensagem,
        "motivos": motivos
    })

# --- ROTA 2: MELHORIA DE CURRÍCULO ---
@app.route("/curriculo", methods=["POST"])
def melhorar_curriculo():
    """
    Analisa o texto do currículo e fornece sugestões de melhoria.
    """
    data = request.json
    texto = data.get("texto", "").lower()
    sugestoes = []

    # Dica de competências técnicas
    if "computador" in texto or "informática" in texto:
        sugestoes.append("Especifique suas habilidades (ex: Excel Avançado, Python ou Hardware)")

    # Transformando falta de experiência em algo positivo
    if "não tenho experiência" in texto or "sem experiência" in texto:
        sugestoes.append("Em vez de 'não tenho experiência', destaque seus projetos, cursos e voluntariados")

    # Verificação de profundidade do texto
    if len(texto) < 50:
        sugestoes.append("Seu resumo está muito curto. Tente descrever melhor seus objetivos e interesses")

    # Resposta padrão baseada na existência de sugestões
    resposta = "Encontramos alguns pontos que podem ser melhorados no seu perfil."
    if not sugestoes:
        resposta = "Seu currículo está com uma base sólida!"

    return jsonify({
        "mensagem": resposta,
        "sugestoes": sugestoes
    })

# O app.run DEVE ser a última parte do código
if __name__ == "__main__":
    print("Servidor de IA Jovem Connect rodando na porta 5000...")
    app.run(port=5000, debug=True)