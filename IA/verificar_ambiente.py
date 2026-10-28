"""IA/verificar_ambiente.py — verificações usadas pelo iniciar_ia.bat (só biblioteca padrão).

Confere se a IA vai rodar com o ambiente OFICIAL do projeto (<raiz>\\venv) e mostra um
diagnóstico sem dados sensíveis: só caminhos, versões e nomes de pacotes.

Uso (chamado pelo iniciar_ia.bat):
  verificar <pasta do venv oficial> <requirements-ia.txt>
        0  ambiente oficial válido
        10 este Python não pertence ao venv oficial
        11 versão do Python abaixo do mínimo
        12 flask / flask_cors ausentes ou quebrados
  base      0 se ESTE Python serve para criar o venv (3.10+, instalação normal, não é venv)
  onde      imprime o caminho deste Python (sys.executable)
  carimbo   imprime a data/hora no formato AAAAMMDD-HHMMSS (nome do backup)
  ping      0 já existe IA do JovemConnect para reaproveitar (imprime a porta)
            1 nenhuma IA e a porta preferida está livre
            2 nenhuma IA e a porta preferida está ocupada por outro programa
              (não é erro: a IA, ao abrir, procura a próxima porta da faixa)
  esperar <segundos>   0 quando a IA DESTA pasta responder na porta gravada em
                       logs/ia_porta.json | 1 se não responder no prazo

Porta: a IA (IA/recomendar.py) escolhe e grava logs/ia_porta.json; aqui só se lê.
Regras de faixa iguais às de IA/recomendar.py e includes/ia_endereco.php.
"""
import json
import os
import re
import socket
import sys
import time
import urllib.error
import urllib.request

# click 8.3 (dependência do Flask 3.1 fixado em requirements-ia.txt) exige Python 3.10+.
MINIMO = (3, 10)
RAIZ = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
ARQ_PORTA = os.path.join(RAIZ, "logs", "ia_porta.json")


def log(msg):
    """Mostra na tela e, se o .bat indicar, grava em logs\\iniciar_ia.log."""
    print(msg, flush=True)
    caminho = os.environ.get("JC_LOG_INICIAR")
    if caminho:
        try:
            with open(caminho, "a", encoding="utf-8") as arquivo:
                arquivo.write(msg + "\n")
        except OSError:
            pass


def norm(caminho):
    return os.path.normcase(os.path.realpath(caminho))


def fixadas(req):
    """Lê 'pacote==versao' do requirements-ia.txt (ignora comentários; respeita o marcador do Windows)."""
    versoes = {}
    try:
        with open(req, encoding="utf-8") as arquivo:
            linhas = arquivo.read().splitlines()
    except OSError:
        return versoes
    for linha in linhas:
        linha = linha.split("#", 1)[0].strip()
        if "==" not in linha:
            continue
        pacote, _, marcador = linha.partition(";")
        if "Windows" in marcador and os.name != "nt":
            continue
        nome, _, versao = pacote.partition("==")
        versoes[nome.strip().lower()] = versao.strip()
    return versoes


def verificar(venv, req):
    v = sys.version_info
    log(f"  Python em uso : {sys.executable}")
    log(f"  Versao        : {v.major}.{v.minor}.{v.micro}")
    log(f"  sys.prefix    : {sys.prefix}")
    log(f"  Python base   : {sys.base_prefix}")
    if sys.prefix == sys.base_prefix or norm(sys.prefix) != norm(venv):
        log(f"  ERRO: este Python nao pertence ao venv oficial ({venv}).")
        return 10
    if v[:2] < MINIMO:
        log(f"  ERRO: Python {v.major}.{v.minor} e antigo; o projeto precisa de {MINIMO[0]}.{MINIMO[1]} ou mais novo.")
        return 11
    faltando = []
    for modulo in ("flask", "flask_cors"):
        try:
            __import__(modulo)
        except Exception as erro:  # noqa: BLE001 — qualquer falha de import invalida o ambiente
            faltando.append(f"{modulo} ({erro.__class__.__name__})")
    if faltando:
        log("  ERRO: pacotes ausentes ou quebrados no venv: " + ", ".join(faltando))
        return 12
    # Versões diferentes das fixadas: só aviso (a IA pode funcionar; nada é reinstalado aqui).
    from importlib import metadata
    for nome, esperada in fixadas(req).items():
        try:
            instalada = metadata.version(nome)
        except metadata.PackageNotFoundError:
            instalada = None
        if instalada != esperada:
            log(f"  Aviso: {nome} {instalada or 'ausente'} (fixado: {esperada}).")
    log("  OK: ambiente oficial valido.")
    return 0


def base():
    """Este Python pode CRIAR o venv? (3.10+, não está dentro de outro venv, tem venv e ensurepip)."""
    import importlib.util
    v = sys.version_info
    log(f"  Python do computador: {sys.executable} ({v.major}.{v.minor}.{v.micro})")
    if v[:2] < MINIMO:
        log(f"  Recusado: versao abaixo de {MINIMO[0]}.{MINIMO[1]}.")
        return 1
    if sys.prefix != sys.base_prefix:
        log("  Recusado: e um ambiente virtual, nao a instalacao do Python.")
        return 1
    if importlib.util.find_spec("venv") is None or importlib.util.find_spec("ensurepip") is None:
        log("  Recusado: sem os modulos venv/ensurepip.")
        return 1
    return 0


def _config(nome):
    """Variável de ambiente ou .env da raiz (só as chaves JC_IA_*; nada é exibido)."""
    if os.environ.get(nome):
        return os.environ[nome]
    try:
        with open(os.path.join(RAIZ, ".env"), encoding="utf-8") as arquivo:
            for linha in arquivo:
                chave, sep, valor = linha.strip().partition("=")
                if sep and chave.strip() == nome:
                    valor = valor.strip()
                    if len(valor) >= 2 and valor[0] == valor[-1] and valor[0] in "\"'":
                        valor = valor[1:-1]
                    return valor
    except OSError:
        pass
    return ""


def portas():
    """(preferida, faixa extra) — 1024–65535, faixa de no máximo 50 portas; padrão 5000 e 5001-5010."""
    texto = _config("JC_IA_PORTA").strip()
    preferida = int(texto) if texto.isdigit() and 1024 <= int(texto) <= 65535 else 5000
    inicio, fim = 5001, 5010
    m = re.fullmatch(r"\s*(\d{4,5})\s*-\s*(\d{4,5})\s*", _config("JC_IA_PORTAS_EXTRAS"))
    if m and 1024 <= int(m.group(1)) <= int(m.group(2)) <= 65535 and int(m.group(2)) - int(m.group(1)) < 50:
        inicio, fim = int(m.group(1)), int(m.group(2))
    return preferida, range(inicio, fim + 1)


def consultar(porta):
    """GET /ping em 127.0.0.1:<porta> sem proxy. Devolve (codigo, dados): 0 = IA do JovemConnect,
    1 = ninguém na porta, 2 = outro programa (ou sem resposta válida)."""
    abridor = urllib.request.build_opener(urllib.request.ProxyHandler({}))
    try:
        with abridor.open(f"http://127.0.0.1:{porta}/ping", timeout=2) as resposta:
            dados = json.loads(resposta.read(4096).decode("utf-8"))
        if isinstance(dados, dict) and dados.get("status") == "ok" and "versao" in dados:
            return 0, dados
        return 2, None
    except urllib.error.HTTPError:
        return 2, None
    except urllib.error.URLError as erro:
        return (1 if isinstance(erro.reason, ConnectionRefusedError) else 2), None
    except ConnectionRefusedError:
        return 1, None
    except (socket.timeout, TimeoutError, OSError, ValueError):
        return 2, None


def estado():
    """Porta da IA desta pasta segundo logs/ia_porta.json, só se ela estiver respondendo com a mesma instância."""
    preferida, extras = portas()
    try:
        with open(ARQ_PORTA, encoding="utf-8") as arquivo:
            dados = json.loads(arquivo.read(4096))
    except (OSError, ValueError):
        return None
    porta, inst = (dados.get("porta"), dados.get("instancia")) if isinstance(dados, dict) else (None, None)
    if not (isinstance(porta, int) and not isinstance(porta, bool) and (porta == preferida or porta in extras)):
        return None
    if not (isinstance(inst, str) and re.fullmatch(r"[a-f0-9]{32}", inst)):
        return None
    codigo, ping_dados = consultar(porta)
    return porta if codigo == 0 and ping_dados.get("instancia") == inst else None


def ping():
    porta = estado()
    if porta is not None:
        log(f"  A IA desta pasta ja esta no ar na porta {porta}.")
        print(f"PORTA={porta}")
        return 0
    preferida, extras = portas()
    codigo, _ = consultar(preferida)
    if codigo == 0:
        log(f"  Ja ha uma IA do JovemConnect na porta {preferida}; ela sera reaproveitada.")
        print(f"PORTA={preferida}")
        return 0
    if codigo == 2:
        log(f"  A porta {preferida} esta ocupada por outro programa; a IA vai procurar uma porta livre entre {extras.start} e {extras.stop - 1}.")
    return codigo


def esperar(segundos):
    limite = time.monotonic() + segundos
    while time.monotonic() < limite:
        porta = estado()
        if porta is not None:
            log(f"  IA respondendo em http://127.0.0.1:{porta}/ping (porta gravada em logs{os.sep}ia_porta.json)")
            return 0
        time.sleep(0.5)
    return 1


def main(args):
    if not args:
        print(__doc__)
        return 2
    modo = args[0]
    if modo == "verificar" and len(args) == 3:
        return verificar(args[1], args[2])
    if modo == "base":
        return base()
    if modo == "onde":
        print(sys.executable)
        return 0
    if modo == "carimbo":
        print(time.strftime("%Y%m%d-%H%M%S"))
        return 0
    if modo == "ping":
        return ping()
    if modo == "esperar" and len(args) == 2:
        return esperar(float(args[1]))
    print(__doc__)
    return 2


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
