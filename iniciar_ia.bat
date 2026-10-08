@echo off
setlocal
title Inicializador JovemConnect
echo --------------------------------------------------
echo   A iniciar os servidores do JovemConnect...
echo --------------------------------------------------

:: Funciona em qualquer computador: tudo e relativo a pasta onde este .bat
:: esta (pen drive, outra letra de unidade, outro usuario do Windows).
cd /d "%~dp0"
for %%I in ("%~dp0.") do set "PASTA=%%~nxI"

:: Um "venv" copiado de outro computador NAO funciona: ele guarda o caminho do
:: Python daquele PC (C:\Users\usuario\...). Por isso, nesta ordem:
::   1) o venv do projeto, se ele funcionar NESTE computador;
::   2) um ambiente so deste computador (venv-NOMEDOPC), criado na primeira
::      vez a partir do Python instalado aqui (precisa de internet uma vez);
::   3) o proprio Python do computador, se ja tiver o Flask.
set "PY="
if exist "venv\Scripts\python.exe" call :testar "venv\Scripts\python.exe"
if defined PY goto iniciar

set "LOCAL=venv-%COMPUTERNAME%"
if exist "%LOCAL%\Scripts\python.exe" call :testar "%LOCAL%\Scripts\python.exe"
if defined PY goto iniciar

:: Python instalado neste computador (launcher "py" ou "python" no PATH)
set "BASE="
py -3 -c "import sys" >nul 2>&1 && set "BASE=py -3"
if not defined BASE python -c "import sys" >nul 2>&1 && set "BASE=python"
if not defined BASE goto sem_python

echo [0/2] Primeira vez neste computador: preparando o servidor de IA...
if not exist "%LOCAL%\Scripts\python.exe" %BASE% -m venv "%LOCAL%"
if exist "%LOCAL%\Scripts\python.exe" "%LOCAL%\Scripts\python.exe" -m pip install --disable-pip-version-check flask flask-cors
if exist "%LOCAL%\Scripts\python.exe" call :testar "%LOCAL%\Scripts\python.exe"
if defined PY goto iniciar

%BASE% -c "import flask, flask_cors" >nul 2>&1 && set "PY=%BASE%"
if defined PY goto iniciar
echo.
echo   Nao foi possivel instalar o Flask. Verifique a internet e rode de novo.
pause
exit /b 1

:sem_python
echo.
echo   Python 3 nao encontrado neste computador.
echo   Instale em https://www.python.org/downloads/ marcando
echo   "Add python.exe to PATH" e rode este arquivo de novo.
pause
exit /b 1

:testar
"%~1" -c "import flask, flask_cors" >nul 2>&1 && set "PY=%~1"
exit /b 0

:iniciar
:: 1. Inicia o Flask numa nova janela minimizada
echo [1/2] A iniciar o servidor de IA (Flask)...
start "JovemConnect - IA" /min cmd /k %PY% IA\recomendar.py

:: 2. Aguarda 2 segundos e abre a pagina inicial (nome da pasta do projeto)
echo [2/2] A abrir a plataforma no seu navegador...
timeout /t 2 >nul
start "" "http://localhost/%PASTA%/index.php"
exit /b 0
