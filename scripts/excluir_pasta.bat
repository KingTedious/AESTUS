@echo off
setlocal EnableExtensions DisableDelayedExpansion
title JovemConnect - excluindo a pasta

rem ======================================================================
rem  scripts\excluir_pasta.bat - NAO rode este arquivo direto.
rem  O parar_jovemconnect.bat copia este arquivo para %TEMP% e o chama com a
rem  pasta do projeto, depois que voce confirmou digitando o nome da pasta.
rem  Roda de fora da pasta (um .bat nao consegue apagar a pasta em que esta).
rem  Repete as mesmas protecoes: so exclui uma pasta do JovemConnect que esteja
rem  dentro de ...\htdocs\. Diagnostico em %TEMP%\jovemconnect_limpeza.log.
rem ======================================================================

set "ALVO=%~1"
set "LOG=%TEMP%\jovemconnect_limpeza.log"
cd /d "%TEMP%"
if "%ALVO%"=="" goto recusado
for %%I in ("%ALVO%\..") do set "PAIC=%%~fI"
for %%I in ("%PAIC%") do set "PAI=%%~nxI"
if /i not "%PAI%"=="htdocs" goto recusado
if not exist "%ALVO%\index.php" goto recusado
if not exist "%ALVO%\iniciar_ia.bat" goto recusado
if not exist "%ALVO%\scripts\jovemconnect.php" goto recusado
>>"%LOG%" echo === %DATE% %TIME% excluindo "%ALVO%"

echo Aguardando o JovemConnect fechar...
timeout /t 3 /nobreak >nul

:rodada
set "N=0"
:tentar
set /a N+=1
echo Tentativa %N% de 3: excluindo "%ALVO%"...
rem Arquivos somente leitura (ex.: objetos do .git) e ocultos
attrib -r -s -h "%ALVO%\*" /s /d >nul 2>&1
rmdir /s /q "%ALVO%" 2>>"%LOG%"
if not exist "%ALVO%\" goto excluida
if %N% geq 3 goto sobrou
timeout /t 3 /nobreak >nul
goto tentar

:sobrou
>>"%LOG%" echo   sobrou parte da pasta
echo.
echo Nao consegui excluir tudo. Ainda restam:
dir /b /s "%ALVO%" 2>nul
echo.
echo Algum programa ainda esta usando esses arquivos. Feche, por exemplo:
echo   - a janela "JovemConnect - IA" ou outra janela de comando aberta nessa pasta;
echo   - o VS Code ou outro editor com a pasta aberta;
echo   - arquivos da pasta abertos em algum programa.
echo.
choice /c SN /n /m "Tentar de novo? [S/N] "
if errorlevel 2 goto fim
goto rodada

:excluida
>>"%LOG%" echo   pasta excluida
echo.
echo Pasta excluida: "%ALVO%"
goto fim

:recusado
>>"%LOG%" echo === %DATE% %TIME% recusado: "%ALVO%"
echo Por seguranca, so excluo uma pasta do JovemConnect dentro de ...\htdocs\.
echo Nada foi excluido.

:fim
echo.
pause
rem Apaga esta copia temporaria
(goto) 2>nul & del "%~f0"
