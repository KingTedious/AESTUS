@echo off
title Inicializador JovemConnect
echo --------------------------------------------------
echo   A iniciar os servidores do JovemConnect...
echo --------------------------------------------------

:: Stage 3: executa a partir da pasta do proprio .bat (funciona tambem via atalho)
cd /d "%~dp0"

:: Stage 3: usa o Python do ambiente virtual do projeto (venv), onde o Flask e o
:: flask-cors estao instalados. Antes usava o "python" do sistema, que pode nao
:: ter o Flask: o servidor de IA nao subia e o Match mostrava "IA indisponivel".
set "PY=python"
if exist "venv\Scripts\python.exe" set "PY=venv\Scripts\python.exe"

:: 1. Inicia o Flask numa nova janela minimizada para não estorvar
echo [1/2] A iniciar o microsservico Flask (Python)...
start "JovemConnect - IA" /min cmd /k %PY% IA/recomendar.py

:: 2. Aguarda 2 segundos e abre o navegador na página inicial do site
echo [2/2] A abrir a plataforma no seu navegador...
timeout /t 2 >nul
start http://localhost/JovemConnect/index.php

:: 3. Fecha esta janela do prompt de comando automaticamente
exit