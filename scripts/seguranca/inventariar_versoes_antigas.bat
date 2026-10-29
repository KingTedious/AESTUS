@echo off
setlocal EnableExtensions
title JovemConnect - Inventario das versoes antigas (so leitura)
rem ======================================================================
rem  So LE a pasta das versoes anteriores ao Git e grava, FORA dela e fora
rem  do htdocs (Documentos\JovemConnect_evidencias), o inventario com datas e
rem  SHA-256 de cada arquivo. Nao move, nao renomeia, nao apaga, nao muda datas.
rem  Uso: inventariar_versoes_antigas.bat               (escolhe a pasta numa janela)
rem       inventariar_versoes_antigas.bat -Pasta "C:\caminho\da\pasta"
rem  Roteiro: docs\PROCEDIMENTO_EVIDENCIAS.md
rem ======================================================================
powershell -NoProfile -ExecutionPolicy Bypass -STA -File "%~dp0inventariar_versoes_antigas.ps1" %*
echo.
pause
