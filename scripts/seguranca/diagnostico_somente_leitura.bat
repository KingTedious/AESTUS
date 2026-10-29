@echo off
setlocal EnableExtensions
title JovemConnect - Diagnostico de seguranca (somente leitura)
rem ======================================================================
rem  Diagnostico SOMENTE LEITURA: XAMPP (Apache, PHP, MariaDB), rede, firewall,
rem  Git e varredura de segredos com saida redigida (sem mostrar valores).
rem  Nao altera nada. Relatorio: logs\diagnostico_seguranca-AAAAMMDD-HHMMSS.log
rem  Leva de alguns segundos a poucos minutos (varre o historico do Git).
rem ======================================================================
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0diagnostico_somente_leitura.ps1" %*
echo.
echo Pronto. O relatorio esta em logs\ (arquivo diagnostico_seguranca-*.log).
pause
