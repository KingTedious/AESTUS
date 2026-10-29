#!/usr/bin/env bash
# ======================================================================
#  iniciar_jovemconnect.sh - liga tudo para usar o JovemConnect no Linux
#  (versao Linux do iniciar_jovemconnect.bat)
#
#  Uso: bash iniciar_jovemconnect.sh
#
#   1. confere o PHP (versao, extensoes, .env, pastas gravaveis); se algo
#      nao bater, mostra o motivo e pergunta se continua mesmo assim
#   2. oferece liberar a escrita do Apache em logs/ e uploads/curriculos/
#   3. liga o Apache e o MySQL (XAMPP em /opt/lampp, ou os servicos do sistema),
#      se ainda nao estiverem ligados (pode pedir a senha do sudo)
#   4. banco de dados: se nao existir, pergunta se pode criar e importar o
#      schema.sql (so em banco vazio; nunca importa por cima, nunca apaga)
#   5. confere se o Apache bloqueia logs/ e uploads/curriculos/ (.htaccess)
#   6. inicia a IA (iniciar_ia.sh) e mostra o endereco do site
#  Nada e apagado. Nenhum programa de terceiros e encerrado.
#  Para desligar (e, se quiser, limpar este computador): parar_jovemconnect.sh
#  Diagnostico: na tela e em logs/iniciar_jovemconnect.log
# ======================================================================

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"
PASTA="$(basename "$RAIZ")"
LOG="$RAIZ/logs/iniciar_jovemconnect.log"
# shellcheck source=scripts/linux_comum.sh
. "$RAIZ/scripts/linux_comum.sh" || { echo "ERRO: falta scripts/linux_comum.sh"; exit 1; }

falhou() {
    echo
    echo "  Diagnostico salvo em logs/iniciar_jovemconnect.log"
    exit 1
}

main() {
    cd "$RAIZ" || { echo "ERRO: nao consegui entrar na pasta do projeto."; exit 1; }
    mkdir -p "$RAIZ/logs" 2>/dev/null
    echo "--------------------------------------------------"
    echo "  JovemConnect - preparando tudo..."
    echo "--------------------------------------------------"
    log "=== Inicializacao em $(date '+%d/%m/%Y %H:%M:%S') (computador $(hostname 2>/dev/null)) ==="

    # --- 1. PHP -------------------------------------------------------------
    log "[1/6] PHP"
    log "  Projeto: $RAIZ"
    log "  Ambiente: $MODO$([ "$MODO" = xampp ] && echo " ($LAMPP)")"
    if [ -z "$PHP" ] || [ ! -x "$PHP" ]; then
        log "ERRO: nao achei o PHP (nem $LAMPP/bin/php, nem 'php' no PATH)."
        echo "  Instale o XAMPP para Linux, ou o PHP do sistema (ex.: sudo apt install php php-mysql php-curl php-mbstring)."
        falhou
    fi
    log "  PHP    : $PHP"
    if [ ! -f "$AJUDA" ]; then
        log "ERRO: falta scripts/jovemconnect.php. Confira se a pasta do projeto foi copiada inteira."
        falhou
    fi
    ajuda checar
    local rc=$?
    if [ "$rc" != "0" ] && [ "$rc" != "20" ]; then
        echo
        log "AVISO: alguma verificacao nao bateu (veja as linhas acima)."
        if ! perguntar "Continuar mesmo assim? (o que depender disso pode nao funcionar)"; then
            log "Parado a seu pedido: a checagem do PHP nao bateu."
            falhou
        fi
        log "  Continuando por escolha do usuario, apesar do aviso."
    fi
    if [ ! -f "$RAIZ/.env" ]; then
        echo
        if perguntar "Nao existe .env. Criar a partir do .env.example (banco root sem senha, padrao do XAMPP)?"; then
            ajuda criar-env
        else
            log "  Sem .env: o site usa o padrao (localhost, root, sem senha, banco jovemconnect)."
        fi
    fi

    # --- 2. Permissoes ------------------------------------------------------
    log "[2/6] Permissoes de escrita do Apache (logs/ e uploads/curriculos/)"
    mkdir -p "$RAIZ/uploads/curriculos" 2>/dev/null
    local p falta=""
    for p in logs uploads/curriculos; do
        # O Apache roda com outro usuario (daemon, www-data, apache): precisa de escrita para "outros"
        if [ -d "$RAIZ/$p" ] && [ "$(stat -c %A "$RAIZ/$p" 2>/dev/null | cut -c9)" != "w" ]; then falta="$falta $p"; fi
    done
    if [ -z "$falta" ]; then
        log "  OK."
    else
        log "  O Apache pode nao conseguir gravar em:$falta"
        if perguntar "Liberar a escrita nessas pastas (chmod -R a+rwX; elas ja sao bloqueadas para o navegador)?"; then
            for p in $falta; do chmod -R a+rwX "$RAIZ/$p" && log "  Liberado: $p"; done
        else
            log "  Mantido como esta: logs e envio de curriculos podem falhar."
        fi
    fi

    # --- 3. Apache e MySQL --------------------------------------------------
    log "[3/6] Apache (porta 80)"
    if ajuda porta 80; then
        if ajuda apache || ajuda site "$PASTA"; then
            log "  Ja estava ligado."
        else
            log "ERRO: a porta 80 esta ocupada por um programa que nao e o Apache."
            ajuda quem-usa 80
            log "  Feche esse programa e rode de novo. Nada foi encerrado."
            falhou
        fi
    else
        log "  Ligando o Apache..."
        servico ligar apache
        if ! ajuda esperar-porta 80 25; then
            log "ERRO: o Apache nao ligou em 25 segundos."
            ajuda quem-usa 80
            falhou
        fi
        log "  OK: Apache ligado."
    fi

    log "[4/6] MySQL (porta 3306)"
    if ajuda porta 3306; then
        log "  Ja estava ligado."
    else
        log "  Ligando o MySQL..."
        servico ligar mysql
        if ! ajuda esperar-porta 3306 40; then
            log "ERRO: o MySQL nao ligou em 40 segundos."
            ajuda quem-usa 3306
            falhou
        fi
        log "  OK: MySQL ligado."
    fi

    # --- 4. Banco -----------------------------------------------------------
    log "[5/6] Banco de dados"
    ajuda banco-status
    rc=$?
    if [ "$rc" = "10" ] || [ "$rc" = "11" ]; then
        echo
        if ! perguntar "Criar o banco agora e instalar a estrutura (schema.sql)?"; then
            log "Banco nao criado. O site precisa dele: rode este arquivo de novo e responda S."
            falhou
        fi
        if ! ajuda banco-instalar; then
            log "ERRO: a instalacao do banco falhou (veja as linhas acima). Nada foi apagado."
            falhou
        fi
        echo
        perguntar "Carregar tambem os dados de DEMONSTRACAO (empresas, vagas e jovens ficticios)?" && ajuda banco-seed
    elif [ "$rc" != "0" ]; then
        log "ERRO: nao consegui consultar o banco (MySQL desligado, ou usuario/senha do .env errados)."
        if [ "$MODO" != "xampp" ]; then
            echo "  No MariaDB/MySQL do sistema, o usuario root normalmente so entra pelo sudo (sem senha pela rede)."
            echo "  Crie um usuario para o projeto e coloque-o em DB_USER/DB_PASS no .env, por exemplo:"
            echo "    sudo mysql -e \"CREATE USER 'jovemconnect'@'localhost' IDENTIFIED BY 'troque-esta-senha';"
            echo "                  GRANT ALL ON jovemconnect.* TO 'jovemconnect'@'localhost';\""
        fi
        falhou
    fi

    # --- 5. Site e protecao -------------------------------------------------
    log "[6/6] Site e protecao das pastas"
    if ajuda site "$PASTA"; then
        log "  OK: http://localhost/$PASTA/ responde."
        if ! ajuda protecao "$PASTA"; then
            log "  AVISO: o Apache nao esta aplicando os .htaccess do projeto. Arquivos internos (.env,"
            log "  .git, docs, .sql, logs e curriculos) ficam acessiveis pelo navegador. Ative"
            log "  'AllowOverride All' para a pasta do site no Apache (ex.: /etc/apache2/apache2.conf,"
            log "  bloco <Directory /var/www/>) e reinicie o Apache. Veja docs/SEGURANCA_E_PRIVACIDADE.md (PI-01)."
        fi
    else
        log "  AVISO: http://localhost/$PASTA/ nao mostrou o JovemConnect."
        log "  O Apache precisa servir a pasta-mae deste projeto (XAMPP: $LAMPP/htdocs; Apache do sistema: /var/www/html)."
    fi

    log "Iniciando o servidor de IA (iniciar_ia.sh)"
    bash "$RAIZ/iniciar_ia.sh"
    exit $?
}

main "$@"; exit $?
