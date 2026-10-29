@echo off
setlocal EnableExtensions
title JovemConnect - Gerar pacote de evidencias de uma versao
rem ======================================================================
rem  Gera, FORA do htdocs (Documentos\JovemConnect_evidencias), a copia exata
rem  de uma versao (.zip), o manifesto SHA-256 de cada arquivo, um backup
rem  completo do repositorio (git bundle) e os hashes. Nao altera o Git.
rem  Uso: gerar_evidencias.bat                (versao atual)
rem       gerar_evidencias.bat -Versao v4.60  (uma tag)
rem  Roteiro: docs\PROCEDIMENTO_EVIDENCIAS.md
rem ======================================================================
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0gerar_evidencias.ps1" %*
echo.
pause
