@echo off
title Inicializador JovemConnect
echo --------------------------------------------------
echo   A iniciar os servidores do JovemConnect...
echo --------------------------------------------------

:: 1. Inicia o Flask numa nova janela minimizada para não estorvar
echo [1/2] A iniciar o microsservico Flask (Python)...
start /min cmd /k "python ia/app.py"

:: 2. Aguarda 2 segundos e abre o navegador na página inicial do site
echo [2/2] A abrir a plataforma no seu navegador...
timeout /t 2 >nul
start http://localhost/JovemConnect_v3.00/index.php

:: 3. Fecha esta janela do prompt de comando automaticamente
exit