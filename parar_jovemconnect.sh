#!/usr/bin/env bash
# ======================================================================
#  parar_jovemconnect.sh - desliga o JovemConnect no Linux e, SE VOCE QUISER,
#  limpa este computador (versao Linux do parar_jovemconnect.bat)
#
#  Uso: bash parar_jovemconnect.sh
#
#   1. fecha a IA desta pasta (so ela: confere a instancia gravada)
#   2. pergunta se apaga o BANCO do projeto (DB_NAME do .env)
#   3. pergunta se desliga o Apache e o MySQL (XAMPP ou servicos do sistema)
#   4. pergunta se EXCLUI ESTA PASTA do projeto
#  Cada exclusao pede S e, depois, que voce DIGITE o nome (banco/pasta;
#  maiusculas tanto faz). Avisa se a pasta tem trabalho do Git sem commit
#  ou sem enviar.
#  NAO USE A LIMPEZA NO SEU PC PRINCIPAL: o que for excluido nao volta.
# ======================================================================

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"
PASTA="$(basename "$RAIZ")"
LOG=""
# shellcheck source=scripts/linux_comum.sh
. "$RAIZ/scripts/linux_comum.sh" || { echo "ERRO: falta scripts/linux_comum.sh"; exit 1; }

minusculas() { printf '%s' "$1" | tr '[:upper:]' '[:lower:]'; }

aviso_git() {
    [ -d "$RAIZ/.git" ] || return 0
    if ! command -v git >/dev/null 2>&1; then
        echo "a pasta e um repositorio Git e o git nao esta instalado aqui para conferir se ha trabalho nao enviado."
        return 0
    fi
    local alterados pendentes msg=""
    alterados="$(git -C "$RAIZ" status --porcelain 2>/dev/null | wc -l)"
    pendentes="$(git -C "$RAIZ" log --oneline '@{u}..' 2>/dev/null | wc -l)"
    [ "$alterados" != "0" ] && msg="ha $alterados arquivo(s) alterado(s) sem commit."
    [ "$pendentes" != "0" ] && msg="$msg Ha $pendentes commit(s) que nao foram enviados ao GitHub."
    printf '%s' "$msg"
}

excluir_pasta() {
    echo
    echo "[4/4] Pasta do projeto"
    perguntar "EXCLUIR a pasta $RAIZ deste computador?" || return 0
    # Mesmas protecoes do Windows: so uma pasta do JovemConnect servida pelo Apache
    local pai
    pai="$(basename "$(dirname "$RAIZ")")"
    case "$RAIZ" in
        /var/www/*) ;;
        *) if [ "$(minusculas "$pai")" != "htdocs" ]; then
               echo "  Por seguranca, so excluo uma pasta do JovemConnect que esteja dentro de .../htdocs/ ou /var/www/."
               echo "  Nada foi excluido."
               return 0
           fi ;;
    esac
    if [ -z "$RAIZ" ] || [ "$RAIZ" = "/" ] || [ ! -f "$RAIZ/index.php" ] || [ ! -f "$RAIZ/includes/ia_endereco.php" ] \
       || [ ! -f "$RAIZ/scripts/jovemconnect.php" ]; then
        echo "  Esta pasta nao parece ser a do JovemConnect. Nada foi excluido."
        return 0
    fi
    local aviso
    aviso="$(aviso_git)"
    if [ -n "$aviso" ]; then
        echo
        echo "  ATENCAO: $aviso"
        echo "  Se excluir a pasta, esse trabalho se perde."
    fi
    local conf=""
    printf '%s' "  Para confirmar, digite o nome da pasta ($PASTA; maiusculas tanto faz): "
    read -r conf || conf=""
    if [ "$(minusculas "$conf")" != "$(minusculas "$PASTA")" ]; then
        echo "  Nome diferente: a pasta NAO foi excluida."
        return 0
    fi
    # Este script ja foi lido inteiro (tudo esta em funcoes), entao pode apagar a propria pasta
    cd / || return 0
    local tentativa
    for tentativa in 1 2 3; do
        echo "  Tentativa $tentativa de 3: excluindo $RAIZ ..."
        chmod -R u+rwX "$RAIZ" 2>/dev/null
        rm -rf -- "$RAIZ" 2>/dev/null
        [ -e "$RAIZ" ] || break
        sleep 2
    done
    if [ -e "$RAIZ" ]; then
        echo
        echo "  Nao consegui excluir tudo. Ainda restam (primeiros itens):"
        find "$RAIZ" 2>/dev/null | head -15 | sed 's/^/    /'
        echo "  Normalmente sao arquivos criados pelo Apache (outro usuario, ex.: daemon ou www-data)."
        if perguntar "  Tentar de novo com sudo?"; then
            $SUDO rm -rf -- "$RAIZ"
        fi
    fi
    if [ -e "$RAIZ" ]; then
        echo "  A pasta NAO foi excluida por completo: $RAIZ"
    else
        echo "  Pasta excluida: $RAIZ"
    fi
}

main() {
    cd "$RAIZ" || { echo "ERRO: nao consegui entrar na pasta do projeto."; exit 1; }
    echo "--------------------------------------------------"
    echo "  JovemConnect - desligar e limpar"
    echo "  Pasta: $RAIZ"
    echo "--------------------------------------------------"
    local tem_php=1 banco=""
    if [ -z "$PHP" ] || [ ! -x "$PHP" ] || [ ! -f "$AJUDA" ]; then
        tem_php=""
        echo
        echo "  Nao achei o PHP: pulo a IA, o banco e o Apache/MySQL."
        echo "  Se a IA estiver rodando, encerre-a antes (kill <pid de logs/ia_porta.json>)."
    else
        banco="$(ajuda banco-nome)"
    fi

    if [ -n "$tem_php" ]; then
        # --- 1. IA ---
        echo
        echo "[1/4] Fechando a IA desta pasta..."
        ajuda ia-parar

        # --- 2. Banco ---
        echo
        echo "[2/4] Banco de dados ($banco)"
        if perguntar "Apagar o banco $banco deste computador, com TODOS os dados dele?"; then
            local ok_mysql=1
            if ! ajuda porta 3306; then
                echo "  O MySQL esta desligado; ligando so para apagar o banco..."
                servico ligar mysql
                ajuda esperar-porta 3306 40 || ok_mysql=""
            fi
            if [ -z "$ok_mysql" ]; then
                echo "  ERRO: o MySQL nao ligou; o banco NAO foi apagado."
            else
                local conf=""
                printf '%s' "  Para confirmar, digite o nome do banco ($banco; maiusculas tanto faz): "
                read -r conf || conf=""
                if [ "$(minusculas "$conf")" = "$(minusculas "$banco")" ]; then
                    ajuda banco-apagar "$banco"
                else
                    echo "  Nome diferente: o banco NAO foi apagado."
                fi
            fi
        fi

        # --- 3. Apache e MySQL ---
        echo
        echo "[3/4] Apache e MySQL"
        [ "$MODO" = "sistema" ] && echo "  (sao os servicos do sistema: outros sites deste computador tambem param)"
        if perguntar "Desligar o Apache e o MySQL?"; then
            servico desligar apache
            servico desligar mysql
        fi
    fi

    excluir_pasta
    echo
    echo "  Pronto."
    exit 0
}

main "$@"; exit $?
