@echo off
setlocal EnableExtensions
title JovemConnect - Testes de regressao (ambiente isolado)
rem ======================================================================
rem  Roda os testes das correcoes de seguranca num BANCO NOVO de teste e numa
rem  COPIA temporaria do projeto (seu banco e esta pasta nao sao alterados).
rem  Precisa do MySQL do XAMPP ligado. O banco e a copia de teste sao apagados
rem  no fim (para guardar: regressao_isolada.bat --manter).
rem  Relatorio: logs\regressao_isolada-AAAAMMDDHHMMSS.log
rem ======================================================================
set "RAIZ=%~dp0..\.."
for %%I in ("%RAIZ%") do set "RAIZ=%%~fI"
for %%I in ("%RAIZ%\..\..") do set "XAMPP=%%~fI"
set "PHP=%XAMPP%\php\php.exe"
if not exist "%PHP%" set "PHP=php"
"%PHP%" "%~dp0regressao_isolada.php" %*
echo.
pause
