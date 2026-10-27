@echo off
setlocal EnableExtensions DisableDelayedExpansion
title JovemConnect - Desligar e limpar

rem ======================================================================
rem  parar_jovemconnect.bat - desliga o JovemConnect e, SE VOCE QUISER,
rem  limpa este computador (para usar em PCs de escola/laboratorio)
rem
rem   1. fecha a IA desta pasta (so ela: confere a instancia gravada)
rem   2. pergunta se apaga o BANCO do projeto (DB_NAME do .env)
rem   3. pergunta se desliga o Apache e o MySQL do XAMPP
rem   4. pergunta se EXCLUI ESTA PASTA do projeto
rem  Cada exclusao pede S e, depois, que voce DIGITE o nome (banco/pasta).
rem  Avisa se a pasta tem trabalho do Git sem commit ou sem enviar.
rem  NAO USE A LIMPEZA NO SEU PC PRINCIPAL: o que for excluido nao volta.
rem ======================================================================

set "RAIZ=%~dp0"
if "%RAIZ:~-1%"=="\" set "RAIZ=%RAIZ:~0,-1%"
for %%I in ("%RAIZ%") do set "PASTA=%%~nxI"
rem Nome da pasta-mae, com o caminho normalizado antes (\.. resolvido)
for %%I in ("%RAIZ%\..") do set "PAIC=%%~fI"
for %%I in ("%PAIC%") do set "PAI=%%~nxI"
for %%I in ("%RAIZ%\..\..") do set "XAMPP=%%~fI"
set "PHP=%XAMPP%\php\php.exe"
set "AJUDA=%RAIZ%\scripts\jovemconnect.php"
set "BANCO="
cd /d "%RAIZ%" || goto falha_raiz

echo --------------------------------------------------
echo   JovemConnect - desligar e limpar
echo   Pasta: %RAIZ%
echo --------------------------------------------------
if not exist "%PHP%" goto sem_php
if not exist "%AJUDA%" goto sem_php
for /f "delims=" %%N in ('call "%PHP%" "%AJUDA%" banco-nome') do set "BANCO=%%N"

rem --- 1. IA -----------------------------------------------------------------
echo.
echo [1/4] Fechando a IA desta pasta...
"%PHP%" "%AJUDA%" ia-parar

rem --- 2. Banco --------------------------------------------------------------
echo.
echo [2/4] Banco de dados (%BANCO%)
choice /c SN /n /m "Apagar o banco %BANCO% deste computador, com TODOS os dados dele? [S/N] "
if errorlevel 2 goto xampp
"%PHP%" "%AJUDA%" porta 3306
if not errorlevel 1 goto banco_confirmar
echo   O MySQL esta desligado; ligando so para apagar o banco...
start "XAMPP - MySQL" /D "%XAMPP%" /min "%XAMPP%\mysql_start.bat"
"%PHP%" "%AJUDA%" esperar-porta 3306 40
if errorlevel 1 goto banco_sem_mysql
:banco_confirmar
set "CONF="
set /p "CONF=  Para confirmar, digite o nome do banco (%BANCO%; maiusculas tanto faz): "
set "OK_BANCO="
setlocal EnableDelayedExpansion
if /i "!CONF!"=="!BANCO!" (endlocal & set "OK_BANCO=1") else endlocal
if not defined OK_BANCO goto banco_nao
"%PHP%" "%AJUDA%" banco-apagar "%BANCO%"
goto xampp
:banco_nao
echo   Nome diferente: o banco NAO foi apagado.
goto xampp
:banco_sem_mysql
echo   ERRO: o MySQL nao ligou; o banco NAO foi apagado.

rem --- 3. XAMPP --------------------------------------------------------------
:xampp
echo.
echo [3/4] XAMPP
choice /c SN /n /m "Desligar o Apache e o MySQL do XAMPP? [S/N] "
if errorlevel 2 goto pasta
echo   Usando os scripts de parada do proprio XAMPP...
if exist "%XAMPP%\apache_stop.bat" call "%XAMPP%\apache_stop.bat"
if exist "%XAMPP%\mysql_stop.bat" call "%XAMPP%\mysql_stop.bat"
cd /d "%RAIZ%"
goto pasta

:sem_php
echo.
echo   Nao achei o PHP do XAMPP: pulo a IA, o banco e o XAMPP.
echo   Se a janela "JovemConnect - IA" estiver aberta, feche-a antes de excluir a pasta.

rem --- 4. Pasta do projeto ---------------------------------------------------
:pasta
echo.
echo [4/4] Pasta do projeto
choice /c SN /n /m "EXCLUIR a pasta %RAIZ% deste computador? [S/N] "
if errorlevel 2 goto fim
if /i not "%PAI%"=="htdocs" goto pasta_recusada
if not exist "%RAIZ%\index.php" goto pasta_recusada
if not exist "%RAIZ%\includes\ia_endereco.php" goto pasta_recusada
if not exist "%RAIZ%\iniciar_ia.bat" goto pasta_recusada
set "AVISO_GIT="
if exist "%RAIZ%\.git\" call :checar_git
if not defined AVISO_GIT goto pasta_confirmar
echo.
echo   ATENCAO: %AVISO_GIT%
echo   Se excluir a pasta, esse trabalho se perde.
:pasta_confirmar
set "CONF="
set /p "CONF=  Para confirmar, digite o nome da pasta (%PASTA%; maiusculas tanto faz): "
set "OK_PASTA="
setlocal EnableDelayedExpansion
if /i "!CONF!"=="!PASTA!" (endlocal & set "OK_PASTA=1") else endlocal
if not defined OK_PASTA goto pasta_nao

rem A exclusao roda numa copia de scripts\excluir_pasta.bat em %TEMP% (este arquivo
rem esta dentro da pasta). Ela espera este arquivo terminar, tenta ate 3 vezes, tira
rem o "somente leitura" (arquivos do .git) e, se algo ficar, mostra o que e oferece
rem tentar de novo. Diagnostico em %TEMP%\jovemconnect_limpeza.log.
if not exist "%RAIZ%\scripts\excluir_pasta.bat" goto pasta_sem_script
set "LIMPA=%TEMP%\jovemconnect_limpeza_%RANDOM%%RANDOM%.bat"
copy /y "%RAIZ%\scripts\excluir_pasta.bat" "%LIMPA%" >nul
if not exist "%LIMPA%" goto pasta_sem_script
echo   Excluindo em uma janela separada ("JovemConnect - limpeza")...
cd /d "%TEMP%"
start "JovemConnect - limpeza" /D "%TEMP%" cmd /c ""%LIMPA%" "%RAIZ%""
endlocal & cd /d "%TEMP%" & exit /b 0

:pasta_nao
echo   Nome diferente: a pasta NAO foi excluida.
goto fim

:pasta_sem_script
echo   ERRO: nao consegui preparar a exclusao (falta scripts\excluir_pasta.bat ou %%TEMP%% sem permissao).
echo   Nada foi excluido. Apague a pasta pelo Explorer depois de fechar esta janela.
goto fim

:pasta_recusada
echo   Por seguranca, so excluo uma pasta do JovemConnect que esteja dentro de ...\htdocs\.
echo   Nada foi excluido.
goto fim

:falha_raiz
echo ERRO: nao consegui entrar na pasta do projeto.

:fim
echo.
echo   Pronto.
pause
exit /b 0

rem --- Trabalho do Git que se perderia com a exclusao ------------------------
:checar_git
where git >nul 2>&1
if errorlevel 1 set "AVISO_GIT=a pasta e um repositorio Git e o git nao esta instalado aqui para conferir se ha trabalho nao enviado." & exit /b 0
for /f %%L in ('git -C "%RAIZ%" status --porcelain 2^>nul ^| find /c /v ""') do if not "%%L"=="0" set "AVISO_GIT=ha %%L arquivo(s) alterado(s) sem commit."
for /f %%L in ('git -C "%RAIZ%" log --oneline "@{u}.." 2^>nul ^| find /c /v ""') do if not "%%L"=="0" call set "AVISO_GIT=%%AVISO_GIT%% Ha %%L commit(s) que nao foram enviados ao GitHub."
exit /b 0
