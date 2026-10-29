@echo off
setlocal EnableExtensions
title JovemConnect - Gravar a v4.60 no Git (correcao de seguranca)
rem ======================================================================
rem  scripts\seguranca\git_correcao_seguranca.bat - roteiro de gravacao da v4.60
rem
rem  NADA e feito sem a sua confirmacao (S/N) em cada passo. Nunca usa
rem  --force, nunca apaga branch, tag ou historico, nunca recria uma tag que
rem  ja existe e nao altera nem apaga arquivos da pasta.
rem
rem   1. confere o Git e mostra o estado atual (git status);
rem   2. deixa de rastrear geminiAPI.env e repomix-output.xml (os arquivos
rem      CONTINUAM na pasta; o .gitignore impede que voltem ao Git);
rem   3. corrige no indice a grafia Candidaturas/ para candidaturas/ (PI-12),
rem      sem mexer no arquivo da pasta;
rem   4. prepara (git add) exatamente os arquivos desta versao (lista fixa);
rem   5. (opcional) instala o gancho pre-commit (nunca sobrescreve);
rem   6. commit (assinado, se a assinatura estiver configurada);
rem   7. tag v4.60 (assinada, se possivel; senao, anotada);
rem   8. envio do branch e da tag ao GitHub (sem --force);
rem   9. (opcional) pacote de evidencias fora do htdocs.
rem
rem  Pode rodar de novo: os passos ja feitos sao conferidos e pulados.
rem  Uso: dois cliques, com a pasta do projeto no lugar de sempre.
rem  Roteiro de assinatura e evidencias: docs\PROCEDIMENTO_EVIDENCIAS.md
rem ======================================================================
cd /d "%~dp0..\.."
set "VERSAO=v4.60"
set "CHAVE="
set "ASSINAR="

where git >nul 2>&1
if errorlevel 1 (
    echo git nao encontrado no PATH. Instale o Git for Windows e rode de novo.
    goto fim_erro
)
git rev-parse --is-inside-work-tree >nul 2>&1
if errorlevel 1 (
    echo Esta pasta nao e um repositorio Git: %CD%
    goto fim_erro
)
for %%M in (MERGE_HEAD REBASE_HEAD CHERRY_PICK_HEAD) do (
    git rev-parse -q --verify %%M >nul 2>&1 && (
        echo Ha uma operacao do Git em andamento [%%M]. Termine ou cancele essa operacao antes.
        goto fim_erro
    )
)
set "BRANCH="
for /f "delims=" %%B in ('git branch --show-current') do set "BRANCH=%%B"
if not defined BRANCH (
    echo O repositorio nao esta num branch [HEAD destacado]. Volte para o main antes.
    goto fim_erro
)
echo.
echo Pasta : %CD%
echo Branch: %BRANCH%
echo Versao: %VERSAO%
if /i "%BRANCH%"=="main" goto passo1
echo AVISO: o branch atual nao e o main.
choice /c SN /n /m "Continuar neste branch? [S/N] "
if errorlevel 2 goto fim_cancelado

:passo1
echo.
echo ==== 1. Estado atual [git status --short] ====
git status --short
echo.
choice /c SN /n /m "Seguir com a gravacao da %VERSAO%? [S/N] "
if errorlevel 2 goto fim_cancelado

rem ---- 2. geminiAPI.env e repomix-output.xml fora do indice (PI-08, PI-09)
:passo2
echo.
echo ==== 2. Deixar de rastrear geminiAPI.env e repomix-output.xml ====
set "RASTREADOS=0"
git ls-files | findstr /x /c:"geminiAPI.env" /c:"repomix-output.xml" >nul && set "RASTREADOS=1"
if "%RASTREADOS%"=="0" (
    echo   Nenhum dos dois esta rastreado: nada a fazer.
    goto passo3
)
echo   Os arquivos CONTINUAM na pasta [o repomix segue sendo gerado so localmente];
echo   so deixam de ir para o Git.
choice /c SN /n /m "Fazer agora? [S/N] "
if errorlevel 2 goto passo3
git rm --cached --quiet --ignore-unmatch -- geminiAPI.env repomix-output.xml
if errorlevel 1 (
    echo   Falhou o git rm --cached. Nada mais foi feito.
    goto fim_erro
)
for %%F in (geminiAPI.env repomix-output.xml) do (
    git ls-files | findstr /x /c:"%%F" >nul && echo   %%F: AINDA RASTREADO
    git ls-files | findstr /x /c:"%%F" >nul || echo   %%F: nao rastreado
    if exist "%%F" echo   %%F: continua na pasta
)

rem ---- 3. Grafia candidaturas/ (PI-12): so no indice
:passo3
echo.
echo ==== 3. Grafia candidaturas/ no Git [PI-12] ====
git ls-files | findstr /b /c:"Candidaturas/minhas.php" >nul
if errorlevel 1 (
    echo   O indice ja usa candidaturas/: nada a fazer.
    goto passo4
)
if not exist "candidaturas\minhas.php" (
    echo   candidaturas\minhas.php nao existe na pasta: este passo foi pulado.
    goto passo4
)
echo   O Git registra "Candidaturas/minhas.php" [C maiusculo]; no disco e no codigo e "candidaturas/".
echo   Num clone no Linux, "Minhas candidaturas" daria 404. A correcao muda so o indice do Git.
choice /c SN /n /m "Corrigir agora? [S/N] "
if errorlevel 2 goto passo4
git rm --cached --quiet -- "Candidaturas/minhas.php"
if errorlevel 1 (
    echo   Falhou o git rm --cached de Candidaturas/minhas.php.
    goto fim_erro
)
git add -- "candidaturas/minhas.php"
if errorlevel 1 (
    echo   Falhou o git add de candidaturas/minhas.php.
    goto fim_erro
)
git ls-files | findstr /b /c:"candidaturas/minhas.php" >nul && echo   OK: o indice agora tem candidaturas/minhas.php
git ls-files | findstr /b /c:"Candidaturas/" >nul && echo   ATENCAO: ainda ha entradas com Candidaturas/ no indice

rem ---- 4. git add da lista fixa de arquivos desta versao
:passo4
echo.
echo ==== 4. Preparar os arquivos da %VERSAO% [git add de uma lista fixa] ====
set "FALTOU=0"
for %%F in (
    ".env.example"
    ".gitattributes"
    ".gitignore"
    ".htaccess"
    "assets\css\admin.css"
    "composer.json"
    "dashboard\empresa.php"
    "database\.htaccess"
    "docs\.htaccess"
    "docs\ADMINISTRACAO_E_MODERACAO.md"
    "docs\APRESENTACAO.md"
    "docs\ARQUITETURA.md"
    "docs\ARQUIVOS_E_FUNCOES.md"
    "docs\AUTORIA_E_ORIGEM.md"
    "docs\BANCO_DE_DADOS.md"
    "docs\CLASSIFICACAO.md"
    "docs\CONFIGURACAO.md"
    "docs\FLUXOS_E_FLUXOGRAMAS.md"
    "docs\GLOSSARIO.md"
    "docs\IA_E_MATCH.md"
    "docs\INSTALACAO_E_EXECUCAO.md"
    "docs\LOGS.md"
    "docs\LOGS_E_DIAGNOSTICO.md"
    "docs\MANUTENCAO_E_EVOLUCAO.md"
    "docs\MATRIZ_DE_DEPENDENCIAS.md"
    "docs\MIGRACOES.md"
    "docs\ORIGEM_DOS_ATIVOS.md"
    "docs\PROCEDIMENTO_EVIDENCIAS.md"
    "docs\README.md"
    "docs\RELATORIO_CORRECAO_SEGURANCA.md"
    "docs\REQUISITOS.md"
    "docs\ROTAS_E_ENDPOINTS.md"
    "docs\SEED_DEMO.md"
    "docs\SEGURANCA_E_PRIVACIDADE.md"
    "docs\TESTES.md"
    "docs\TESTES_RESULTADOS.md"
    "docs\TITULARIDADE_E_LICENCA.md"
    "docs\VISAO_GERAL.md"
    "empresa\cadastro.php"
    "empresa\perfil.php"
    "empresa\processar_login.php"
    "IA\recomendar.py"
    "includes\.htaccess"
    "includes\auth.php"
    "includes\conta_status.php"
    "includes\env.php"
    "includes\header.php"
    "includes\session.php"
    "includes\tentativas.php"
    "iniciar_jovemconnect.bat"
    "iniciar_jovemconnect.sh"
    "jovem\cadastro.php"
    "jovem\perfil.php"
    "jovem\processar_login.php"
    "liberar_logins.bat"
    "LICENSE"
    "logout.php"
    "migrations\.htaccess"
    "README.md"
    "recuperar_senha.php"
    "requirements.txt"
    "schema.sql"
    "scripts\jovemconnect.php"
    "scripts\seguranca\configurar_assinatura.bat"
    "scripts\seguranca\configurar_assinatura.ps1"
    "scripts\seguranca\diagnostico_somente_leitura.bat"
    "scripts\seguranca\diagnostico_somente_leitura.ps1"
    "scripts\seguranca\gerar_evidencias.bat"
    "scripts\seguranca\gerar_evidencias.ps1"
    "scripts\seguranca\git_correcao_seguranca.bat"
    "scripts\seguranca\inventariar_versoes_antigas.bat"
    "scripts\seguranca\inventariar_versoes_antigas.ps1"
    "scripts\seguranca\pre-commit"
    "scripts\seguranca\regressao_isolada.bat"
    "scripts\seguranca\regressao_isolada.php"
    "scripts\seguranca\verificar_protecao.bat"
    "scripts\seguranca\verificar_protecao.ps1"
    "THIRD_PARTY_NOTICES.md"
    "uploads\curriculos.php"
    "Vagas\avaliar.php"
    "Vagas\candidatos.php"
    "Vagas\curriculoIA.php"
    "verificacoes\verificar_email.php"
    "verificacoes\verificar_telefone.php"
) do (
    if exist "%%~F" (
        git add -- "%%~F"
        if errorlevel 1 set "FALTOU=1"
    ) else (
        echo   nao encontrado: %%~F
        set "FALTOU=1"
    )
)
if "%FALTOU%"=="1" (
    echo   Algum arquivo da lista faltou ou nao foi adicionado [veja acima]. Nada foi commitado.
    goto fim_erro
)
rem Conferencia: nada de segredo ou copia do codigo entre os arquivos adicionados/alterados
set "PERIGO=0"
for /f "delims=" %%P in ('git diff --cached --name-only --diff-filter=ACMR') do (
    echo %%P| findstr /r /i /c:"\.env$" /c:"repomix-output" /c:"\.bundle$" /c:"\.pem$" /c:"\.key$" /c:"\.p12$" /c:"\.pfx$" | findstr /v /i /c:".env.example" >nul && (
        echo   RECUSADO: %%P nao deve ir para o Git.
        set "PERIGO=1"
    )
)
if "%PERIGO%"=="1" (
    echo   Tire esses arquivos do indice [git restore --staged ARQUIVO] e rode de novo. Nada foi commitado.
    goto fim_erro
)
echo.
git status --short
echo.
git diff --cached --stat

rem ---- 5. Gancho pre-commit (opcional; nunca sobrescreve)
:passo5
echo.
echo ==== 5. Gancho pre-commit [opcional] ====
set "GANCHOS="
for /f "delims=" %%H in ('git rev-parse --git-path hooks') do set "GANCHOS=%%H"
set "GANCHOS=%GANCHOS:/=\%"
if exist "%GANCHOS%\pre-commit" (
    echo   Ja existe um gancho pre-commit: nao foi alterado.
    goto passo6
)
echo   Recusa commits com .env, chaves, repomix ou textos com formato de chave [so mostra o nome do arquivo].
choice /c SN /n /m "Instalar? [S/N] "
if errorlevel 2 goto passo6
if not exist "%GANCHOS%" mkdir "%GANCHOS%"
copy /y "scripts\seguranca\pre-commit" "%GANCHOS%\pre-commit" >nul
if errorlevel 1 (
    echo   Nao consegui copiar o gancho. Siga sem ele.
) else (
    echo   Instalado: %GANCHOS%\pre-commit
)

rem ---- 6. Commit
:passo6
echo.
echo ==== 6. Commit ====
git diff --cached --quiet
if not errorlevel 1 (
    echo   Nada preparado para commit [talvez ja tenha sido feito]. Ultimo commit:
    git log -1 --oneline
    goto passo7
)
for /f "delims=" %%K in ('git config --get user.signingkey') do set "CHAVE=%%K"
if defined CHAVE set "ASSINAR=-S"
if defined CHAVE echo   Assinatura configurada: o commit sai assinado [o Git pode pedir a frase-senha].
if not defined CHAVE echo   Assinatura NAO configurada: commit comum. Roteiro: docs\PROCEDIMENTO_EVIDENCIAS.md
choice /c SN /n /m "Fazer o commit da %VERSAO% agora? [S/N] "
if errorlevel 2 goto fim_sem_commit
git commit %ASSINAR% -m "v4.60 - correcao de seguranca e protecao da propriedade intelectual" -m "Seguranca: .htaccess na raiz bloqueia os arquivos internos no Apache (PI-01, PI-13); limite de tentativas no login e nos codigos (SEG-02) e intervalo entre envios de codigo (SEG-07); troca de senha encerra as outras sessoes e o token CSRF e renovado no login (SEG-09); uma identidade por sessao (SEG-13); perfil do jovem privado por padrao nas contas novas (SEG-03); curriculos com nome aleatorio (SEG-10); cabecalhos de seguranca e erros do PHP fora da tela; Flask sem CORS e so com Host local (SEG-06); IA Advisor sem innerHTML (SEG-05); contas de demonstracao bloqueadas com JC_AMBIENTE=producao." -m "Git: geminiAPI.env e repomix-output.xml fora do indice, mantidos na pasta (PI-08, PI-09); grafia candidaturas/ corrigida no indice (PI-12); gancho pre-commit opcional (PI-17)." -m "Documentos: classificacao da informacao, autoria e origem, origem dos ativos e avisos de terceiros, titularidade e licenca (pendencias), procedimento de evidencias e relatorio da correcao; scripts de verificacao em scripts/seguranca."
if errorlevel 1 (
    echo   O commit falhou [veja a mensagem acima; o gancho pre-commit pode ter recusado]. Nada foi enviado.
    goto fim_erro
)
git log -1 --oneline

rem ---- 7. Tag (nunca recria uma existente)
:passo7
echo.
echo ==== 7. Tag %VERSAO% ====
git rev-parse -q --verify "refs/tags/%VERSAO%" >nul 2>&1
if not errorlevel 1 (
    echo   A tag %VERSAO% ja existe: NAO foi recriada.
    goto passo8
)
echo   Commit atual:
git log -1 --oneline
for /f "delims=" %%K in ('git config --get user.signingkey') do set "CHAVE=%%K"
choice /c SN /n /m "Criar a tag %VERSAO% neste commit? [S/N] "
if errorlevel 2 goto passo8
if defined CHAVE (
    git tag -s "%VERSAO%" -m "JovemConnect %VERSAO% - correcao de seguranca e protecao da propriedade intelectual"
) else (
    git tag -a "%VERSAO%" -m "JovemConnect %VERSAO% - correcao de seguranca e protecao da propriedade intelectual"
)
if errorlevel 1 (
    echo   A tag nao foi criada [veja a mensagem acima].
    goto fim_erro
)
if defined CHAVE git tag -v "%VERSAO%"
if not defined CHAVE echo   Tag anotada criada, sem assinatura [configure a assinatura para as proximas versoes].

rem ---- 8. Envio (sem --force)
:passo8
echo.
echo ==== 8. Enviar ao GitHub [sem --force] ====
git remote get-url origin >nul 2>&1
if errorlevel 1 (
    echo   Sem remoto "origin": envio pulado.
    goto passo9
)
choice /c SN /n /m "Enviar o branch %BRANCH% e a tag %VERSAO% para o origin? [S/N] "
if errorlevel 2 goto passo9
git push origin "%BRANCH%"
if errorlevel 1 (
    echo   O GitHub recusou o envio do branch. NAO use --force: veja a mensagem acima e peca ajuda.
    goto fim_erro
)
git rev-parse -q --verify "refs/tags/%VERSAO%" >nul 2>&1
if errorlevel 1 goto passo9
git push origin "refs/tags/%VERSAO%"
if errorlevel 1 (
    echo   O envio da tag foi recusado [talvez ja exista no GitHub com outro conteudo]. Nada foi forcado.
    goto fim_erro
)

rem ---- 9. Pacote de evidencias (opcional)
:passo9
echo.
echo ==== 9. Pacote de evidencias [opcional] ====
set "ALVO=HEAD"
git rev-parse -q --verify "refs/tags/%VERSAO%" >nul 2>&1
if not errorlevel 1 set "ALVO=%VERSAO%"
echo   Gera fora do htdocs: zip da versao, manifesto SHA-256, git bundle e hashes [%ALVO%].
choice /c SN /n /m "Gerar agora? [S/N] "
if errorlevel 2 goto fim_ok
powershell -NoProfile -ExecutionPolicy Bypass -File "scripts\seguranca\gerar_evidencias.ps1" -Versao "%ALVO%"
goto fim_ok

:fim_ok
echo.
echo Pronto. Para conferir: git status   /   git log -1 --show-signature   /   git tag -v %VERSAO%
echo Depois, com o Apache ligado: scripts\seguranca\verificar_protecao.bat
goto fim

:fim_sem_commit
echo.
echo Nada foi commitado. Os arquivos preparados continuam no indice [git status mostra].
goto fim

:fim_cancelado
echo.
echo Cancelado. Nada foi alterado.
goto fim

:fim_erro
echo.
echo Parado. Nada foi apagado. Corrija o problema mostrado acima e rode de novo.

:fim
echo.
pause
endlocal
