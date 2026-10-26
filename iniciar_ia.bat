@echo off
setlocal EnableExtensions DisableDelayedExpansion
title Inicializador JovemConnect

rem ======================================================================
rem  iniciar_ia.bat - inicia o servidor de IA (IA\recomendar.py)
rem
rem  Usa SOMENTE o ambiente oficial do projeto: <pasta deste .bat>\venv
rem   - nunca roda a IA com o Python global do computador;
rem   - nunca adota outro ambiente (venv-NOMEDOPC, .venv...) e nunca apaga nada;
rem   - se o venv estiver ausente ou invalido (ex.: copiado de outro PC), PARA e
rem     explica. Com a sua confirmacao (S), reconstroi o proprio venv neste
rem     computador, guardando o anterior como venv.antigo-<data>.
rem  Cada computador deve ter o SEU venv: nao copie a pasta venv entre PCs.
rem  Porta: a IA tenta a 5000 (JC_IA_PORTA) e, se outro programa estiver nela,
rem  a faixa JC_IA_PORTAS_EXTRAS (padrao 5001-5010). Ela grava a porta escolhida
rem  em logs\ia_porta.json, que o PHP le. Nenhum processo e encerrado.
rem  Diagnostico: na tela e em logs\iniciar_ia.log (sem senhas ou dados pessoais).
rem ======================================================================

rem --- Caminhos absolutos, a partir da pasta deste arquivo ----------------
set "RAIZ=%~dp0"
if "%RAIZ:~-1%"=="\" set "RAIZ=%RAIZ:~0,-1%"
set "VENV=%RAIZ%\venv"
set "PY=%VENV%\Scripts\python.exe"
set "APP=%RAIZ%\IA\recomendar.py"
set "CHK=%RAIZ%\IA\verificar_ambiente.py"
set "REQ=%RAIZ%\IA\requirements-ia.txt"
set "LOGDIR=%RAIZ%\logs"
set "LOG=%RAIZ%\logs\iniciar_ia.log"
for %%I in ("%RAIZ%") do set "PASTA=%%~nxI"
set "REFEITO="
set "ANTIGO="

rem --- Isola o Python de configuracoes do computador -----------------------
set "PYTHONHOME="
set "PYTHONPATH="
set "VIRTUAL_ENV="
set "PYTHONNOUSERSITE=1"
set "JC_LOG_INICIAR=%LOG%"

cd /d "%RAIZ%" || goto falha_raiz
if not exist "%LOGDIR%\" mkdir "%LOGDIR%" 2>nul

echo --------------------------------------------------
echo   A iniciar os servidores do JovemConnect...
echo --------------------------------------------------
call :log "=== Inicializacao em %DATE% %TIME% ==="
call :log "[1/5] Diagnostico"
call :log "  Computador    : %COMPUTERNAME%"
call :log "  Raiz          : %RAIZ%"
call :log "  Pasta atual   : %CD%"
call :log "  venv oficial  : %VENV%"
call :log "  Python oficial: %PY%"
for /d %%D in ("%RAIZ%\venv-*") do call :log "  Aviso: ambiente nao oficial encontrado e IGNORADO: %%~nxD"
if exist "%RAIZ%\.venv\" call :log "  Aviso: ambiente nao oficial encontrado e IGNORADO: .venv"
for /d %%D in ("%RAIZ%\venv.antigo-*") do call :log "  Info: backup de ambiente anterior (nao usado): %%~nxD"

call :log "[2/5] Arquivos do projeto"
if not exist "%APP%" goto falta_arquivo
if not exist "%CHK%" goto falta_arquivo
if not exist "%REQ%" goto falta_arquivo
call :log "  OK: IA\recomendar.py, IA\verificar_ambiente.py e IA\requirements-ia.txt"

:validar
call :log "[3/5] Ambiente oficial (venv)"
set "RC=ausente"
if not exist "%PY%" goto venv_invalido
"%PY%" "%CHK%" verificar "%VENV%" "%REQ%"
set "RC=%ERRORLEVEL%"
if not "%RC%"=="0" goto venv_invalido

call :log "[4/5] A IA ja esta rodando?"
"%PY%" "%CHK%" ping
set "RC=%ERRORLEVEL%"
if "%RC%"=="0" goto ja_rodando
if "%RC%"=="1" call :log "  Nao: a porta preferida esta livre."

call :log "[5/5] Iniciando a IA na janela 'JovemConnect - IA' (feche-a para desligar a IA)"
start "JovemConnect - IA" /D "%RAIZ%" cmd /k ""%PY%" "%APP%""
"%PY%" "%CHK%" esperar 20
if errorlevel 1 goto nao_respondeu
goto abrir_site

:ja_rodando
call :log "  Nao abro outra: a IA que ja esta no ar sera usada."

:abrir_site
call :log "A abrir a plataforma no navegador: http://localhost/%PASTA%/index.php"
start "" "http://localhost/%PASTA%/index.php"
exit /b 0

rem ======================================================================
rem  Ambiente oficial ausente ou invalido
rem ======================================================================
:venv_invalido
echo.
call :log "PROBLEMA: o ambiente oficial (venv) nao pode ser usado neste computador."
if "%RC%"=="ausente" call :log "  Motivo: nao existe venv\Scripts\python.exe."
if "%RC%"=="10" call :log "  Motivo: o Python do venv nao pertence a esta pasta do projeto."
if "%RC%"=="11" call :log "  Motivo: o venv usa uma versao de Python antiga demais (minimo 3.10)."
if "%RC%"=="12" call :log "  Motivo: faltam pacotes da IA no venv (flask / flask-cors)."
if not "%RC%"=="ausente" if not "%RC%"=="10" if not "%RC%"=="11" if not "%RC%"=="12" call :log "  Motivo: o Python do venv nem abre (codigo %RC%). Normalmente o venv foi criado em OUTRO computador."
if exist "%VENV%\pyvenv.cfg" for /f "usebackq tokens=1,* delims==" %%A in ("%VENV%\pyvenv.cfg") do if /i "%%A"=="home " call :log "  Este venv aponta para o Python em:%%B"
if defined REFEITO goto ainda_invalido
echo.
echo   Um venv so funciona no computador em que foi criado. A solucao e criar
echo   o venv DESTE computador a partir do Python instalado aqui e das versoes
echo   fixas em IA\requirements-ia.txt (precisa de internet so desta vez).
echo   O venv atual NAO sera apagado: sera guardado como venv.antigo-^<data^>.
echo.
choice /c SN /n /m "Reconstruir o venv deste computador agora? [S/N] "
if errorlevel 2 goto recusou
goto reconstruir

:recusou
call :log "Reconstrucao recusada. Nada foi alterado."
echo   Para reconstruir depois, rode este arquivo de novo e responda S.
goto falhou

:ainda_invalido
call :log "  O venv foi reconstruido, mas continua invalido. Nada mais foi alterado."
goto falhou

rem ======================================================================
rem  Reconstrucao do venv oficial (somente com confirmacao)
rem ======================================================================
:reconstruir
call :log "[R1] Procurando o Python instalado neste computador (3.10 ou mais novo; recomendado 3.13)"
set "BASEPY="
for /f "delims=" %%P in ('call py -3 "%CHK%" onde 2^>nul') do if not defined BASEPY set "BASEPY=%%P"
if defined BASEPY call :testar_base
if not defined BASEPY for /f "delims=" %%P in ('call python "%CHK%" onde 2^>nul') do if not defined BASEPY set "BASEPY=%%P"
if defined BASEPY call :testar_base
if not defined BASEPY goto sem_python
call :log "  Usando: %BASEPY%"

set "TS="
for /f "delims=" %%T in ('call "%BASEPY%" "%CHK%" carimbo') do set "TS=%%T"
if not defined TS goto falha_carimbo
if not exist "%VENV%\" goto criar_venv
set "ANTIGO=venv.antigo-%TS%"
if exist "%RAIZ%\%ANTIGO%" goto falha_renomear
call :log "[R2] Guardando o venv atual como %ANTIGO% (nada e apagado)"
ren "%VENV%" "%ANTIGO%"
if errorlevel 1 goto falha_renomear
if exist "%VENV%\" goto falha_renomear

:criar_venv
call :log "[R3] Criando o venv oficial em %VENV%"
"%BASEPY%" -m venv "%VENV%"
if errorlevel 1 goto falha_criar
if not exist "%PY%" goto falha_criar
call :log "[R4] Instalando as versoes fixas de IA\requirements-ia.txt (precisa de internet)"
"%PY%" -m pip install --disable-pip-version-check --no-input -r "%REQ%"
if errorlevel 1 goto falha_pip
call :log "  Reconstrucao concluida. Conferindo de novo..."
set "REFEITO=1"
goto validar

:testar_base
"%BASEPY%" "%CHK%" base
if errorlevel 1 set "BASEPY="
exit /b 0

rem ======================================================================
rem  Falhas: param a inicializacao com o motivo
rem ======================================================================
:sem_python
call :log "ERRO: nenhum Python 3.10+ utilizavel foi encontrado neste computador."
echo   Instale o Python 3.13 em https://www.python.org/downloads/ marcando
echo   "Add python.exe to PATH" e rode este arquivo de novo.
goto falhou

:falha_carimbo
call :log "ERRO: nao consegui gerar a data para o nome do backup. Nada foi alterado."
goto falhou

:falha_renomear
call :log "ERRO: nao consegui renomear o venv atual (algum programa pode estar usando a pasta)."
call :log "  Nada foi apagado. Feche janelas que usem o venv e tente de novo."
goto falhou

:falha_criar
call :log "ERRO: a criacao do venv falhou."
if defined ANTIGO call :log "  O ambiente anterior continua guardado em %ANTIGO%."
goto falhou

:falha_pip
call :log "ERRO: a instalacao das dependencias falhou (sem internet, proxy da rede ou bloqueio?)."
if defined ANTIGO call :log "  O ambiente anterior continua guardado em %ANTIGO%."
call :log "  O venv novo ficou incompleto; na proxima execucao o .bat oferece reconstruir de novo."
goto falhou

:falta_arquivo
call :log "ERRO: falta um arquivo do projeto (IA\recomendar.py, IA\verificar_ambiente.py ou IA\requirements-ia.txt)."
call :log "  Confira se a pasta do projeto foi copiada inteira."
goto falhou

:nao_respondeu
call :log "ERRO: a IA nao confirmou a porta em 20 segundos."
call :log "  Veja a mensagem na janela 'JovemConnect - IA' (ex.: nenhuma porta livre na faixa)."
call :log "  Nenhum processo foi encerrado."
goto falhou

:falha_raiz
echo ERRO: nao consegui entrar na pasta do projeto.
goto falhou

:falhou
echo.
echo   Diagnostico salvo em logs\iniciar_ia.log
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
