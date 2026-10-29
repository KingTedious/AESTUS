@echo off
setlocal EnableExtensions DisableDelayedExpansion
title JovemConnect - Liberar logins

rem ======================================================================
rem  liberar_logins.bat - libera logins e codigos travados pelo limite de
rem  tentativas ("Muitas tentativas..."). Para usar na apresentacao.
rem  So apaga os contadores de tentativas deste projeto (arquivos com
rem  horarios, fora da pasta publica). Nao mexe no banco nem nas contas.
rem  Mesmo que: php scripts\jovemconnect.php limpar-tentativas
rem ======================================================================

set "RAIZ=%~dp0"
if "%RAIZ:~-1%"=="\" set "RAIZ=%RAIZ:~0,-1%"
for %%I in ("%RAIZ%\..\..") do set "XAMPP=%%~fI"
set "PHP=%XAMPP%\php\php.exe"
if not exist "%PHP%" set "PHP=C:\xampp\php\php.exe"
if not exist "%PHP%" (
    echo   PHP do XAMPP nao encontrado. Rode na pasta do projeto dentro do htdocs.
    goto fim
)
cd /d "%RAIZ%"
echo.
"%PHP%" "%RAIZ%\scripts\jovemconnect.php" limpar-tentativas
if errorlevel 1 (
    echo   Nao foi possivel liberar. Veja docs\APRESENTACAO.md.
) else (
    echo   Se o site ainda mostrar "Muitas tentativas", o Apache pode estar rodando
    echo   como servico do Windows, com outra pasta temporaria: defina
    echo   JC_TENTATIVAS_DIR=C:\xampp\tmp\jovemconnect_tentativas no .env,
    echo   reinicie o Apache e rode este atalho de novo.
)
:fim
echo.
pause
endlocal
