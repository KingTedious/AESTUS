@echo off
setlocal EnableExtensions
title JovemConnect - Verificar protecao do Apache
rem ======================================================================
rem  Confere no Apache de verdade se os arquivos internos (.env, .git, docs,
rem  .sql, .py, logs, curriculos) estao bloqueados e se o site continua no ar.
rem  Somente leitura: so cria e apaga na hora dois arquivos de teste.
rem  Uso: de dois cliques (Apache ligado). Para testar tambem pelos IPs da rede:
rem       scripts\seguranca\verificar_protecao.bat -Rede
rem  Relatorio: logs\testes\verificacao_protecao-AAAAMMDD-HHMMSS.log
rem ======================================================================
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0verificar_protecao.ps1" %*
set "RC=%ERRORLEVEL%"
echo.
if "%RC%"=="0" (echo Tudo aprovado.) else if "%RC%"=="2" (echo O Apache nao respondeu: ligue o Apache e rode de novo.) else (echo Ha reprovacoes: veja a tabela acima e o relatorio em logs\testes\.)
pause
exit /b %RC%
