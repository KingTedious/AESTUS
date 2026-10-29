@echo off
setlocal EnableExtensions
title JovemConnect - Configurar a assinatura de commits e tags
rem ======================================================================
rem  Passo a passo guiado para assinar commits e tags com chave SSH.
rem  Voce cria a chave e a frase-senha; nada e sobrescrito; o roteiro testa
rem  a assinatura antes de configurar o Git e nao faz commit, tag nem push.
rem  Rode ANTES do git_correcao_seguranca.bat.
rem  Roteiro: docs\PROCEDIMENTO_EVIDENCIAS.md (secao 1)
rem ======================================================================
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0configurar_assinatura.ps1" %*
echo.
pause
