# scripts/linux_comum.sh - funcoes em comum dos scripts Linux do JovemConnect
# (iniciar_jovemconnect.sh e parar_jovemconnect.sh). Nao rode este arquivo direto:
# ele e carregado com "source" por eles. No Windows, os .bat fazem o mesmo papel.
#
# Ambientes reconhecidos (nesta ordem):
#   1. XAMPP para Linux em /opt/lampp (ou na pasta de JC_LAMPP): usa o PHP dele e
#      liga/desliga com "sudo /opt/lampp/lampp startapache|startmysql|stopapache|stopmysql"
#   2. Apache e MySQL/MariaDB do sistema (systemd): "sudo systemctl start|stop <servico>"
#      servicos procurados: apache2 ou httpd; mariadb, mysql ou mysqld
#   3. Nenhum dos dois: os servicos precisam ja estar ligados (o script so confere)
# Variaveis opcionais: JC_PHP (php a usar), JC_LAMPP (pasta do XAMPP).

LAMPP="${JC_LAMPP:-/opt/lampp}"
if [ "$(id -u)" = "0" ]; then SUDO=""; else SUDO="sudo"; fi

if [ -n "${JC_PHP:-}" ]; then
    PHP="$JC_PHP"
elif [ -x "$LAMPP/bin/php" ]; then
    PHP="$LAMPP/bin/php"
else
    PHP="$(command -v php 2>/dev/null || true)"
fi

if [ -x "$LAMPP/lampp" ]; then
    MODO="xampp"
elif command -v systemctl >/dev/null 2>&1 && [ -d /run/systemd/system ]; then
    MODO="sistema"
else
    MODO="manual"
fi

AJUDA="$RAIZ/scripts/jovemconnect.php"

# Mostra a mensagem e grava no log ($LOG), sem cores nem dados sensiveis
log() {
    printf '%s\n' "$*"
    [ -n "${LOG:-}" ] && printf '%s\n' "$*" >> "$LOG" 2>/dev/null
    return 0
}

# Pergunta S/N; sem resposta (fim da entrada) conta como N
perguntar() {
    local resposta=""
    printf '%s [S/N] ' "$1"
    read -r resposta || { echo; return 1; }
    case "$resposta" in [sS]*) return 0 ;; *) return 1 ;; esac
}

ajuda() { "$PHP" "$AJUDA" "$@"; }

# Primeiro servico do systemd que existe, entre os nomes dados
servico_existente() {
    local s
    for s in "$@"; do
        if systemctl cat "$s.service" >/dev/null 2>&1; then
            printf '%s' "$s"
            return 0
        fi
    done
    return 1
}

# ligar|desligar apache|mysql, conforme o ambiente
servico() {
    local acao="$1" qual="$2" s
    case "$MODO" in
        xampp)
            local cmd="start$qual"
            [ "$acao" = "desligar" ] && cmd="stop$qual"
            log "  Rodando: $SUDO $LAMPP/lampp $cmd (pode pedir a sua senha)"
            $SUDO "$LAMPP/lampp" "$cmd"
            ;;
        sistema)
            if [ "$qual" = "apache" ]; then s="$(servico_existente apache2 httpd)"; else s="$(servico_existente mariadb mysql mysqld)"; fi
            if [ -z "$s" ]; then
                log "  Nao encontrei o servico do $qual no systemd."
                return 1
            fi
            local verbo="start"
            [ "$acao" = "desligar" ] && verbo="stop"
            log "  Rodando: $SUDO systemctl $verbo $s (pode pedir a sua senha)"
            $SUDO systemctl "$verbo" "$s"
            ;;
        *)
            log "  Nao sei ligar/desligar o $qual neste computador (nem XAMPP em $LAMPP, nem systemd)."
            return 1
            ;;
    esac
}
