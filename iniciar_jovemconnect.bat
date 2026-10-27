@echo off
setlocal EnableExtensions DisableDelayedExpansion
title JovemConnect - Inicializador

rem ======================================================================
rem  iniciar_jovemconnect.bat - liga tudo para usar o JovemConnect
rem
rem   1. confere o XAMPP e o PHP (versao, extensoes, .env, pastas gravaveis);
rem      se algo nao bater, mostra o motivo e pergunta se continua mesmo assim
rem      (PHP 7.4 e 8.0 funcionam em modo de compatibilidade)
rem   2. liga o Apache e o MySQL do XAMPP, se ainda nao estiverem ligados
rem      (se ja estiverem, nao mexe; se outro programa usar a porta, avisa)
rem   3. banco de dados: se nao existir, pergunta se pode criar e importar
rem      o schema.sql (so em banco vazio; nunca importa por cima, nunca apaga)
rem   4. inicia a IA (iniciar_ia.bat) e abre o site
rem  Nada e apagado. Nenhum programa de terceiros e encerrado.
rem  Para desligar (e, se quiser, limpar este computador): parar_jovemconnect.bat
rem  Diagnostico: na tela e em logs\iniciar_jovemconnect.log
rem ======================================================================

set "RAIZ=%~dp0"
if "%RAIZ:~-1%"=="\" set "RAIZ=%RAIZ:~0,-1%"
for %%I in ("%RAIZ%") do set "PASTA=%%~nxI"
for %%I in ("%RAIZ%\..\..") do set "XAMPP=%%~fI"
set "PHP=%XAMPP%\php\php.exe"
set "AJUDA=%RAIZ%\scripts\jovemconnect.php"
set "LOG=%RAIZ%\logs\iniciar_jovemconnect.log"

cd /d "%RAIZ%" || goto falha_raiz
if not exist "%RAIZ%\logs\" mkdir "%RAIZ%\logs" 2>nul

echo --------------------------------------------------
echo   JovemConnect - preparando tudo...
echo --------------------------------------------------
call :log "=== Inicializacao em %DATE% %TIME% (computador %COMPUTERNAME%) ==="

rem --- 1. XAMPP --------------------------------------------------------------
call :log "[1/5] XAMPP e PHP"
call :log "  Projeto: %RAIZ%"
call :log "  XAMPP  : %XAMPP%"
if not exist "%PHP%" goto sem_xampp
if not exist "%XAMPP%\apache_start.bat" goto sem_xampp
if not exist "%AJUDA%" goto falta_arquivo
"%PHP%" "%AJUDA%" checar
set "RC=%ERRORLEVEL%"
rem 0 = ok, 20 = ok sem .env; qualquer outro codigo (1, ou erro do proprio PHP) = aviso
if "%RC%"=="0" goto checar_env
if "%RC%"=="20" goto checar_env
echo.
call :log "AVISO: alguma verificacao nao bateu (veja as linhas acima)."
choice /c SN /n /m "Continuar mesmo assim? (o que depender disso pode nao funcionar) [S/N] "
if errorlevel 2 goto falha_checagem
call :log "  Continuando por escolha do usuario, apesar do aviso."
:checar_env
if exist "%RAIZ%\.env" goto apache
echo.
choice /c SN /n /m "Nao existe .env. Criar a partir do .env.example (banco root sem senha, padrao do XAMPP)? [S/N] "
if errorlevel 2 goto sem_env
"%PHP%" "%AJUDA%" criar-env
goto apache
:sem_env
call :log "  Sem .env: o site usa o padrao (localhost, root, sem senha, banco jovemconnect)."

rem --- 2. Apache -------------------------------------------------------------
:apache
call :log "[2/5] Apache (porta 80)"
"%PHP%" "%AJUDA%" porta 80
if errorlevel 1 goto ligar_apache
"%PHP%" "%AJUDA%" apache
if errorlevel 1 goto porta80_ocupada
call :log "  Ja estava ligado."
goto mysql
:ligar_apache
call :log "  Ligando o Apache do XAMPP (janela 'XAMPP - Apache')..."
start "XAMPP - Apache" /D "%XAMPP%" /min "%XAMPP%\apache_start.bat"
"%PHP%" "%AJUDA%" esperar-porta 80 25
if errorlevel 1 goto apache_nao_ligou
call :log "  OK: Apache ligado."

rem --- 3. MySQL --------------------------------------------------------------
:mysql
call :log "[3/5] MySQL (porta 3306)"
"%PHP%" "%AJUDA%" porta 3306
if errorlevel 1 goto ligar_mysql
call :log "  Ja estava ligado."
goto banco
:ligar_mysql
call :log "  Ligando o MySQL do XAMPP (janela 'XAMPP - MySQL')..."
start "XAMPP - MySQL" /D "%XAMPP%" /min "%XAMPP%\mysql_start.bat"
"%PHP%" "%AJUDA%" esperar-porta 3306 40
if errorlevel 1 goto mysql_nao_ligou
call :log "  OK: MySQL ligado."

rem --- 4. Banco de dados -----------------------------------------------------
:banco
call :log "[4/5] Banco de dados"
"%PHP%" "%AJUDA%" banco-status
set "RC=%ERRORLEVEL%"
if "%RC%"=="0" goto ia
if "%RC%"=="2" goto banco_inacessivel
if not "%RC%"=="10" if not "%RC%"=="11" goto banco_inacessivel
echo.
choice /c SN /n /m "Criar o banco agora e instalar a estrutura (schema.sql)? [S/N] "
if errorlevel 2 goto banco_recusado
"%PHP%" "%AJUDA%" banco-instalar
if errorlevel 1 goto banco_falhou
echo.
choice /c SN /n /m "Carregar tambem os dados de DEMONSTRACAO (empresas, vagas e jovens ficticios)? [S/N] "
if errorlevel 2 goto ia
"%PHP%" "%AJUDA%" banco-seed
goto ia

rem --- 5. IA -----------------------------------------------------------------
:ia
call :log "[5/5] Servidor de IA (iniciar_ia.bat)"
call "%RAIZ%\iniciar_ia.bat"
exit /b %ERRORLEVEL%

rem ======================================================================
rem  Falhas: param com o motivo (nada foi apagado nem encerrado)
rem ======================================================================
:sem_xampp
call :log "ERRO: nao achei o XAMPP. Esta pasta precisa estar em ...\xampp\htdocs\%PASTA%"
call :log "  (procurei php\php.exe e apache_start.bat em %XAMPP%)"
goto falhou

:falta_arquivo
call :log "ERRO: falta scripts\jovemconnect.php. Confira se a pasta do projeto foi copiada inteira."
goto falhou

:falha_checagem
call :log "Parado a seu pedido: a checagem do PHP nao bateu (veja as linhas acima)."
goto falhou

:porta80_ocupada
call :log "ERRO: a porta 80 esta ocupada por um programa que nao e o Apache."
"%PHP%" "%AJUDA%" quem-usa 80
call :log "  Feche esse programa (ou mude a porta do Apache no XAMPP) e rode de novo. Nada foi encerrado."
goto falhou

:apache_nao_ligou
call :log "ERRO: o Apache nao ligou em 25 segundos. Veja a janela 'XAMPP - Apache'."
"%PHP%" "%AJUDA%" quem-usa 80
goto falhou

:mysql_nao_ligou
call :log "ERRO: o MySQL nao ligou em 40 segundos. Veja a janela 'XAMPP - MySQL'."
"%PHP%" "%AJUDA%" quem-usa 3306
goto falhou

:banco_inacessivel
call :log "ERRO: nao consegui consultar o banco (MySQL desligado, ou usuario/senha do .env errados)."
goto falhou

:banco_recusado
call :log "Banco nao criado. O site precisa dele: rode este arquivo de novo e responda S."
goto falhou

:banco_falhou
call :log "ERRO: a instalacao do banco falhou (veja as linhas acima). Nada foi apagado."
goto falhou

:falha_raiz
echo ERRO: nao consegui entrar na pasta do projeto.
goto falhou

:falhou
echo.
echo   Diagnostico salvo em logs\iniciar_jovemconnect.log
pause
exit /b 1

rem --- Mostra a mensagem e grava no log (caminhos com espaco ou parenteses OK)
:log
set "JC_MSG=%~1"
setlocal EnableDelayedExpansion
echo(!JC_MSG!
>>"!LOG!" echo(!JC_MSG!
endlocal
exit /b 0
