#!/usr/bin/env bash
# ======================================================================
#  iniciar_ia.sh - inicia o servidor de IA (IA/recomendar.py) no Linux
#  (mesmas regras do iniciar_ia.bat do Windows)
#
#  Uso: bash iniciar_ia.sh
#
#  Usa SOMENTE o ambiente oficial do projeto: <pasta deste arquivo>/venv
#   - nunca roda a IA com o Python do sistema;
#   - nunca adota outro ambiente (venv-NOMEDOPC, .venv...) e nunca apaga nada;
#   - se o venv estiver ausente ou invalido (ex.: copiado de outro PC ou criado
#     no Windows), PARA e explica. Com a sua confirmacao (S), reconstroi o venv
#     neste computador, guardando o anterior como venv.antigo-<data>.
#  A IA roda em segundo plano (saida em logs/ia/ia_console.log). Para desligar:
#  bash parar_jovemconnect.sh (ou kill <pid gravado em logs/ia_porta.json>).
#  Porta: a IA tenta a 5000 (JC_IA_PORTA) e, se outro programa estiver nela,
#  a faixa JC_IA_PORTAS_EXTRAS (padrao 5001-5010). Nenhum processo e encerrado.
#  Diagnostico: na tela e em logs/ia/iniciar_ia.log (sem senhas ou dados pessoais).
# ======================================================================

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"
PASTA="$(basename "$RAIZ")"
VENV="$RAIZ/venv"
PY="$VENV/bin/python"
APP="$RAIZ/IA/recomendar.py"
CHK="$RAIZ/IA/verificar_ambiente.py"
REQ="$RAIZ/IA/requirements-ia.txt"
LOG="$RAIZ/logs/ia/iniciar_ia.log"
CONSOLE="$RAIZ/logs/ia/ia_console.log"

# Isola o Python de configuracoes do computador
unset PYTHONHOME PYTHONPATH VIRTUAL_ENV
export PYTHONNOUSERSITE=1
export JC_LOG_INICIAR="$LOG"

log() { printf '%s\n' "$*"; printf '%s\n' "$*" >> "$LOG" 2>/dev/null; return 0; }
perguntar() {
    local resposta=""
    printf '%s [S/N] ' "$1"
    read -r resposta || { echo; return 1; }
    case "$resposta" in [sS]*) return 0 ;; *) return 1 ;; esac
}
falhou() {
    echo
    echo "  Diagnostico salvo em logs/ia/iniciar_ia.log"
    exit 1
}

motivo() {
    case "$1" in
        ausente) if [ -e "$VENV/Scripts/python.exe" ]; then
                     log "  Motivo: este venv foi criado no WINDOWS (tem venv/Scripts, nao venv/bin)."
                     log "  Atencao: se esta mesma pasta tambem for usada no Windows, depois de reconstruir"
                     log "  aqui o iniciar_ia.bat vai pedir para reconstruir o venv de novo la."
                 else
                     log "  Motivo: nao existe venv/bin/python."
                 fi ;;
        10) log "  Motivo: o Python do venv nao pertence a esta pasta do projeto." ;;
        11) log "  Motivo: o venv usa uma versao de Python antiga demais (minimo 3.10)." ;;
        12) log "  Motivo: faltam pacotes da IA no venv (flask / flask-cors)." ;;
        *)  log "  Motivo: o Python do venv nem abre (codigo $1). Normalmente o venv foi criado em OUTRO computador." ;;
    esac
    if [ -f "$VENV/pyvenv.cfg" ]; then
        local home
        home="$(grep -i '^home *=' "$VENV/pyvenv.cfg" | head -1 | cut -d= -f2-)"
        [ -n "$home" ] && log "  Este venv aponta para o Python em:$home"
    fi
}

reconstruir() {
    log "[R1] Procurando o Python instalado neste computador (3.10 ou mais novo)"
    local base="" c p
    for c in python3 python python3.13 python3.12 python3.11 python3.10; do
        p="$(command -v "$c" 2>/dev/null)" || continue
        if "$p" "$CHK" base; then base="$p"; break; fi
    done
    if [ -z "$base" ]; then
        log "ERRO: nenhum Python 3.10+ com venv foi encontrado neste computador."
        echo "  Instale, por exemplo (Debian/Ubuntu): sudo apt install python3 python3-venv"
        echo "  (Fedora: sudo dnf install python3)  e rode este arquivo de novo."
        return 1
    fi
    log "  Usando: $base"
    local ts
    ts="$("$base" "$CHK" carimbo)" || ts=""
    if [ -z "$ts" ]; then log "ERRO: nao consegui gerar a data para o nome do backup. Nada foi alterado."; return 1; fi
    if [ -e "$VENV" ]; then
        ANTIGO="venv.antigo-$ts"
        if [ -e "$RAIZ/$ANTIGO" ]; then
            log "ERRO: ja existe $ANTIGO; nada foi alterado. Rode de novo daqui a um segundo."
            return 1
        fi
        log "[R2] Guardando o venv atual como $ANTIGO (nada e apagado)"
        if ! mv "$VENV" "$RAIZ/$ANTIGO"; then
            log "ERRO: nao consegui renomear o venv atual (sem permissao?). Nada foi apagado."
            return 1
        fi
    fi
    log "[R3] Criando o venv oficial em $VENV"
    if ! "$base" -m venv "$VENV" || [ ! -x "$PY" ]; then
        log "ERRO: a criacao do venv falhou."
        [ -n "$ANTIGO" ] && log "  O ambiente anterior continua guardado em $ANTIGO."
        return 1
    fi
    log "[R4] Instalando as versoes fixas de IA/requirements-ia.txt (precisa de internet)"
    if ! "$PY" -m pip install --disable-pip-version-check --no-input -r "$REQ"; then
        log "ERRO: a instalacao das dependencias falhou (sem internet, proxy da rede ou bloqueio?)."
        [ -n "$ANTIGO" ] && log "  O ambiente anterior continua guardado em $ANTIGO."
        log "  O venv novo ficou incompleto; na proxima execucao este script oferece reconstruir de novo."
        return 1
    fi
    log "  Reconstrucao concluida. Conferindo de novo..."
    return 0
}

main() {
    cd "$RAIZ" || { echo "ERRO: nao consegui entrar na pasta do projeto."; exit 1; }
    mkdir -p "$RAIZ/logs/ia" 2>/dev/null
    echo "--------------------------------------------------"
    echo "  A iniciar o servidor de IA do JovemConnect..."
    echo "--------------------------------------------------"
    log "=== Inicializacao em $(date '+%d/%m/%Y %H:%M:%S') ==="
    log "[1/5] Diagnostico"
    log "  Computador    : $(hostname 2>/dev/null)"
    log "  Raiz          : $RAIZ"
    log "  venv oficial  : $VENV"
    log "  Python oficial: $PY"
    local d
    for d in "$RAIZ"/venv-* "$RAIZ"/.venv; do
        [ -d "$d" ] && log "  Aviso: ambiente nao oficial encontrado e IGNORADO: $(basename "$d")"
    done
    for d in "$RAIZ"/venv.antigo-*; do
        [ -d "$d" ] && log "  Info: backup de ambiente anterior (nao usado): $(basename "$d")"
    done

    log "[2/5] Arquivos do projeto"
    if [ ! -f "$APP" ] || [ ! -f "$CHK" ] || [ ! -f "$REQ" ]; then
        log "ERRO: falta um arquivo do projeto (IA/recomendar.py, IA/verificar_ambiente.py ou IA/requirements-ia.txt)."
        falhou
    fi
    log "  OK: IA/recomendar.py, IA/verificar_ambiente.py e IA/requirements-ia.txt"

    local refeito="" rc
    ANTIGO=""
    while :; do
        log "[3/5] Ambiente oficial (venv)"
        rc="ausente"
        if [ -x "$PY" ]; then
            "$PY" "$CHK" verificar "$VENV" "$REQ"
            rc=$?
        fi
        [ "$rc" = "0" ] && break
        echo
        log "PROBLEMA: o ambiente oficial (venv) nao pode ser usado neste computador."
        motivo "$rc"
        if [ -n "$refeito" ]; then
            log "  O venv foi reconstruido, mas continua invalido. Nada mais foi alterado."
            falhou
        fi
        echo
        echo "  Um venv so funciona no computador (e no sistema) em que foi criado. A solucao"
        echo "  e criar o venv DESTE computador a partir do Python instalado aqui e das versoes"
        echo "  fixas em IA/requirements-ia.txt (precisa de internet so desta vez)."
        echo "  O venv atual NAO sera apagado: sera guardado como venv.antigo-<data>."
        echo
        if ! perguntar "Reconstruir o venv deste computador agora?"; then
            log "Reconstrucao recusada. Nada foi alterado."
            echo "  Para reconstruir depois, rode este arquivo de novo e responda S."
            falhou
        fi
        reconstruir || falhou
        refeito=1
    done

    log "[4/5] A IA ja esta rodando?"
    "$PY" "$CHK" ping
    rc=$?
    if [ "$rc" = "0" ]; then
        log "  Nao abro outra: a IA que ja esta no ar sera usada."
    else
        [ "$rc" = "1" ] && log "  Nao: a porta preferida esta livre."
        log "[5/5] Iniciando a IA em segundo plano (saida em logs/ia/ia_console.log)"
        # Pasta de trabalho FORA do projeto (a IA usa caminhos absolutos), como no Windows
        # (o subshell vira a propria IA com "exec": nada fica preso a este terminal)
        if command -v setsid >/dev/null 2>&1; then
            ( cd "$RAIZ/.." || exit 1; exec setsid nohup "$PY" "$APP" >> "$CONSOLE" 2>&1 < /dev/null ) > /dev/null 2>&1 < /dev/null &
        else
            ( cd "$RAIZ/.." || exit 1; exec nohup "$PY" "$APP" >> "$CONSOLE" 2>&1 < /dev/null ) > /dev/null 2>&1 < /dev/null &
        fi
        if ! "$PY" "$CHK" esperar 20; then
            log "ERRO: a IA nao confirmou a porta em 20 segundos."
            log "  Veja o final de logs/ia/ia_console.log (ex.: nenhuma porta livre na faixa)."
            log "  Nenhum processo foi encerrado."
            falhou
        fi
    fi

    local url="http://localhost/$PASTA/index.php"
    log "Plataforma: $url"
    if [ -z "${JC_NAO_ABRIR:-}" ] && [ -n "${DISPLAY:-}${WAYLAND_DISPLAY:-}" ] && command -v xdg-open >/dev/null 2>&1; then
        xdg-open "$url" >/dev/null 2>&1 &
    fi
    echo "  Para desligar a IA: bash parar_jovemconnect.sh"
    exit 0
}

main "$@"; exit $?
