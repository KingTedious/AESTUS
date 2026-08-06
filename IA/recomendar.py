from flask import Flask, request, jsonify

app = Flask(__name__)

@app.route("/avaliar", methods=["POST"])
def avaliar():

    data = request.json

    formacao = data.get("formacao", "").lower()
    cidade_jovem = data.get("cidade_jovem", "").lower()

    descricao = data.get("descricao", "").lower()
    cidade_vaga = data.get("cidade_vaga", "").lower()

    score = 0
    motivos = []

    if formacao and formacao in descricao:
        score += 40
        motivos.append("Sua formação combina com a vaga")

    if cidade_jovem == cidade_vaga:
        score += 30
        motivos.append("A vaga está na sua cidade")

    if "estágio" in descricao:
        score += 20
        motivos.append("É uma vaga de estágio")

    if "jovem aprendiz" in descricao:
        score += 30
        motivos.append("É uma vaga de jovem aprendiz")

    if score > 100:
        score = 100

    mensagem = "Boa compatibilidade!" if score > 60 else "Compatibilidade média"

    return jsonify({
        "compatibilidade": score,
        "mensagem": mensagem,
        "motivos": motivos
    })

if __name__ == "__main__":
    app.run(port=5000)
    
@app.route("/curriculo", methods=["POST"])
def melhorar_curriculo():

    data = request.json

    texto = data.get("texto", "").lower()

    sugestoes = []

    if "computador" in texto:
        sugestoes.append("Descreva melhor suas habilidades em informática (ex: pacote Office, programação, etc.)")

    if "não tenho experiência" in texto:
        sugestoes.append("Adicione cursos, projetos pessoais ou estudos relevantes")

    if len(texto) < 50:
        sugestoes.append("Seu currículo está muito curto, tente detalhar mais suas habilidades")

    resposta = "Seu currículo pode ser melhorado com mais detalhes e clareza."

    if not sugestoes:
        resposta = "Seu currículo está bem estruturado!"

    return jsonify({
        "mensagem": resposta,
        "sugestoes": sugestoes
    })