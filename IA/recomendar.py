"""
JovemConnect — IA/recomendar.py  v3.00
Servidor Flask para compatibilidade de vagas e análise de currículo.

Rotas:
  POST /avaliar    → score de match jovem ↔ vaga
  POST /curriculo  → sugestões de melhoria do perfil
  GET  /ping       → health check

Gemini (opcional):
  Defina a variável de ambiente GEMINI_API_KEY para ativar análises
  textuais mais ricas. Sem ela o servidor funciona normalmente com
  mensagens estáticas baseadas no score.

Iniciar:
  python recomendar.py
  (ou via iniciar_ia.bat no Windows)
"""

from flask import Flask, request, jsonify
from flask_cors import CORS
import os
import re

app = Flask(__name__)

# CORS liberado apenas para localhost — ajuste em produção
CORS(app, origins=["http://localhost", "http://127.0.0.1", "http://localhost:80"])

# ── Gemini (opcional) ─────────────────────────────────────────────────────────
GEMINI_KEY   = os.environ.get("GEMINI_API_KEY", "")
gemini_model = None

if GEMINI_KEY:
    try:
        import google.generativeai as genai
        genai.configure(api_key=GEMINI_KEY)
        gemini_model = genai.GenerativeModel("gemini-1.5-flash")
        print("[IA] ✓ Gemini carregado.")
    except ImportError:
        print("[IA] ✗ google-generativeai não instalado. Rode: pip install google-generativeai")
else:
    print("[IA] Sem GEMINI_API_KEY — usando análise estática.")


# ── Helpers ───────────────────────────────────────────────────────────────────

def _str(value: object) -> str:
    """Converte para string limpa e minúscula com segurança."""
    return (value or "").lower().strip()


def analise_estatica(score: int) -> str:
    if score >= 80:
        return ("Excelente compatibilidade! Seu perfil está muito alinhado com "
                "os requisitos desta vaga. Não perca esta oportunidade!")
    if score >= 60:
        return ("Boa compatibilidade! Vale muito a pena se candidatar. "
                "Você atende os principais critérios da vaga.")
    if score >= 40:
        return ("Compatibilidade razoável. Você atende parte dos requisitos — "
                "considere se candidatar e destaque seus pontos fortes.")
    return ("Compatibilidade baixa com seu perfil atual. "
            "Explore outras vagas disponíveis que combinam melhor com você.")


def analise_gemini(score: int, jovem: dict, vaga: dict) -> str | None:
    """Gera análise via Gemini. Retorna None se indisponível ou com erro."""
    if not gemini_model:
        return None
    try:
        prompt = (
            "Você é o assistente de carreiras do JovemConnect, plataforma que conecta "
            "jovens aprendizes com empresas.\n\n"
            f"Score de compatibilidade calculado: {score}%\n"
            f"Cidade do jovem: {jovem.get('cidade_jovem') or 'não informada'}\n"
            f"Cidade da vaga:  {vaga.get('cidade_vaga')  or 'não informada'}\n"
            f"Formação: {jovem.get('tipo')} em {jovem.get('curso') or 'não informado'}\n"
            f"Área da vaga: {vaga.get('area') or 'não informada'}\n"
            f"Tipo de contrato: {vaga.get('contrato') or 'não informado'}\n\n"
            "Escreva exatamente 2 frases encorajadoras e diretas para o jovem, "
            "explicando o resultado. Use linguagem acessível. "
            "Não use markdown, asteriscos ou listas."
        )
        response = gemini_model.generate_content(prompt)
        return response.text.strip()
    except Exception as e:
        print(f"[Gemini] Erro: {e}")
        return None


# ── ROTA 1: AVALIAÇÃO DE COMPATIBILIDADE ─────────────────────────────────────

@app.route("/avaliar", methods=["POST"])
def avaliar():
    """
    Recebe JSON com dados do jovem e da vaga.
    Retorna score (0-100), analise textual e lista de motivos.

    Campos esperados:
      cidade_jovem, area_interesse, escolaridade_tipo,
      escolaridade_curso, escolaridade_status,
      cidade_vaga, area_vaga, tipo_contrato, descricao
    """
    data = request.get_json(silent=True)

    if not isinstance(data, dict):
        return jsonify({"error": "JSON inválido ou Content-Type incorreto.", "score": 0}), 400

    # Extração segura
    cidade_jovem   = _str(data.get("cidade_jovem"))
    cidade_vaga    = _str(data.get("cidade_vaga"))
    area_interesse = _str(data.get("area_interesse"))
    area_vaga      = _str(data.get("area_vaga"))
    tipo_ensino    = _str(data.get("escolaridade_tipo"))
    curso          = _str(data.get("escolaridade_curso"))
    status         = _str(data.get("escolaridade_status"))
    tipo_contrato  = _str(data.get("tipo_contrato"))
    descricao      = _str(data.get("descricao"))

    score   = 0
    motivos = []

    # ── 1. Localização (peso 25) ──────────────────────────────────────────────
    if cidade_jovem and cidade_vaga:
        if cidade_jovem == cidade_vaga:
            score += 25
            motivos.append("A vaga está localizada na sua cidade")
        elif any(p in cidade_vaga for p in cidade_jovem.split() if len(p) > 3):
            score += 12
            motivos.append("A vaga está em uma cidade próxima da sua")

    # ── 2. Área de interesse × área da vaga (peso 25) ─────────────────────────
    if area_interesse and area_vaga:
        if area_interesse == area_vaga:
            score += 25
            motivos.append(f"Área de interesse ({area_vaga.title()}) compatível com a vaga")
        elif area_interesse in area_vaga or area_vaga in area_interesse:
            score += 13
            motivos.append("Área da vaga tem relação com seu interesse")

    # ── 3. Ensino técnico alinhado (peso 25) ──────────────────────────────────
    if tipo_ensino == "tecnico" and curso:
        palavras = [p for p in re.split(r"\s+", curso) if len(p) > 3]
        hits      = sum(1 for p in palavras if p in descricao or p in area_vaga)

        if hits >= 2 or curso in descricao or curso in area_vaga:
            score += 25
            motivos.append(
                f"Seu curso técnico em {curso.title()} é um diferencial para esta vaga"
            )
        elif hits == 1:
            score += 12
            motivos.append("Seu curso técnico tem relação parcial com a vaga")
        else:
            # Ter ensino técnico mesmo sem match direto ainda é positivo
            score += 5
            motivos.append("Ensino técnico é valorizado pelos empregadores")

    # ── 4. Status de escolaridade × tipo de contrato (peso 15) ───────────────
    eh_aprendiz = "jovem aprendiz" in tipo_contrato or "jovem_aprendiz" in tipo_contrato

    if eh_aprendiz:
        if status == "cursando":
            score += 15
            motivos.append("Você está no perfil ideal para Jovem Aprendiz (EM em curso)")
        elif status == "completo":
            score += 8
            motivos.append("Ensino Médio completo é aceito em vagas de Jovem Aprendiz")
    elif status == "completo":
        score += 10
        motivos.append("Ensino Médio concluído é um diferencial")

    # ── 5. Tipo de contrato (peso 10) ─────────────────────────────────────────
    if eh_aprendiz or "estágio" in tipo_contrato:
        score += 10
        motivos.append("Contrato adequado para início de carreira")

    # ── Penalidade: vaga exige conclusão, jovem ainda cursa ──────────────────
    if "concluído" in descricao and status == "cursando":
        score -= 10
        motivos.append("Atenção: esta vaga pode preferir candidatos com EM concluído")

    # ── Garante intervalo [0, 100] ────────────────────────────────────────────
    score = max(0, min(100, score))

    # ── Gera análise textual ──────────────────────────────────────────────────
    jovem_ctx = {"cidade_jovem": cidade_jovem, "tipo": tipo_ensino, "curso": curso}
    vaga_ctx  = {"cidade_vaga": cidade_vaga, "area": area_vaga, "contrato": tipo_contrato}

    analise = analise_gemini(score, jovem_ctx, vaga_ctx) or analise_estatica(score)

    return jsonify({
        "score":   score,
        "analise": analise,
        "motivos": motivos,
        "status":  "ok"
    })


# ── ROTA 2: ANÁLISE DE CURRÍCULO ─────────────────────────────────────────────

@app.route("/curriculo", methods=["POST"])
def analisar_curriculo():
    """
    Analisa o perfil do jovem e sugere melhorias.

    Campos esperados:
      resumo (str), habilidades (list[str]), formacoes (list[dict])
    """
    data = request.get_json(silent=True)

    if not isinstance(data, dict):
        return jsonify({"error": "JSON inválido.", "sugestoes": []}), 400

    resumo      = (data.get("resumo",      "") or "").strip()
    habilidades = data.get("habilidades",  []) or []
    formacoes   = data.get("formacoes",    []) or []
    texto       = resumo.lower()
    sugestoes   = []

    # Resumo muito curto
    if len(resumo) < 80:
        sugestoes.append(
            "Seu resumo está muito curto. Descreva seus objetivos, "
            "áreas de interesse e o que você tem a oferecer."
        )

    # Linguagem negativa
    negativos = ["não tenho experiência", "sem experiência", "nunca trabalhei", "não sei fazer"]
    if any(frase in texto for frase in negativos):
        sugestoes.append(
            "Evite frases como 'sem experiência'. Destaque seus "
            "cursos, projetos e habilidades que você já tem."
        )

    # Tecnologia vaga sem especificação
    if any(p in texto for p in ["computador", "informática", "tecnologia"]):
        especificos = ["excel", "word", "python", "canva", "photoshop", "java", "php", "figma"]
        if not any(p in texto for p in especificos):
            sugestoes.append(
                "Especifique suas habilidades tecnológicas "
                "(ex: Excel Básico, Canva, Python)."
            )

    # Sem habilidades cadastradas
    if not habilidades:
        sugestoes.append(
            "Adicione suas habilidades ao perfil "
            "(ex: Boa Comunicação, Organização, Excel Básico)."
        )
    elif len(habilidades) < 3:
        sugestoes.append("Tente adicionar pelo menos 3 habilidades para se destacar.")

    # Sem formação cadastrada
    if not formacoes:
        sugestoes.append("Adicione sua formação acadêmica ao perfil.")

    # Sugestão Gemini (primeira posição para maior destaque)
    if gemini_model and resumo:
        try:
            prompt = (
                "Analise este resumo profissional de um jovem aprendiz e dê "
                "1 sugestão específica e prática para melhorá-lo. "
                "Use linguagem direta e amigável. Máximo 2 frases. "
                "Não use markdown ou asteriscos.\n\n"
                f'Resumo: "{resumo}"'
            )
            resp          = gemini_model.generate_content(prompt)
            sug_gemini    = resp.text.strip()
            if sug_gemini and sug_gemini not in sugestoes:
                sugestoes.insert(0, sug_gemini)
        except Exception:
            pass  # Falha silenciosa — sugestões estáticas ainda são retornadas

    if sugestoes:
        mensagem = f"Encontramos {len(sugestoes)} ponto(s) para melhorar no seu perfil."
    else:
        mensagem = "Seu perfil está com uma boa base! Continue atualizando com novas experiências."

    return jsonify({
        "mensagem":  mensagem,
        "sugestoes": sugestoes,
        "status":    "ok"
    })


# ── ROTA 3: HEALTH CHECK ─────────────────────────────────────────────────────

@app.route("/ping", methods=["GET"])
def ping():
    """Verifica se o servidor está ativo e informa status do Gemini."""
    return jsonify({
        "status": "ok",
        "gemini": gemini_model is not None,
        "versao": "3.00"
    })


# ── INICIALIZAÇÃO ─────────────────────────────────────────────────────────────

if __name__ == "__main__":
    print("=" * 50)
    print("  JovemConnect — Servidor de IA  v3.00")
    print("  http://127.0.0.1:5000")
    print(f"  Gemini: {'✓ Ativo' if gemini_model else '✗ Inativo (sem GEMINI_API_KEY)'}")
    print("=" * 50)
    # Etapa 4 (V3.10): debug nunca fica ligado por padrão no fluxo normal de
    # execução (iniciar_ia.bat). Para depuração local, defina a variável de
    # ambiente FLASK_DEBUG=1 antes de rodar "python IA/recomendar.py".
    debug_mode = os.environ.get("FLASK_DEBUG", "0") == "1"
    app.run(host="127.0.0.1", port=5000, debug=debug_mode)