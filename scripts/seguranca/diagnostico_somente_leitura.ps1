<#
  scripts\seguranca\diagnostico_somente_leitura.ps1 - diagnostico de seguranca (JovemConnect)

  SOMENTE LEITURA. Nao altera configuracao, arquivos do projeto, banco nem Git.
  Grava apenas o proprio relatorio: logs\diagnostico_seguranca-AAAAMMDD-HHMMSS.log (fora do Git).

  O que confere:
    A. XAMPP: versoes do Apache, PHP e MariaDB
    B. Apache: Listen, AllowOverride/Options da pasta htdocs, modulos usados pelo .htaccess,
       VirtualHosts e o acesso ao phpMyAdmin
    C. PHP: display_errors, sessao, uploads, extensoes e "php -l" em todos os .php do projeto
    D. MariaDB: bind-address e porta
    E. Rede: em que endereco cada servico escuta (80, 443, 3306, IA 5000-5010) e o tipo da rede
    F. Firewall do Windows: perfis e regras do Apache, do MariaDB e do Python
    G. Git (comandos de leitura): estado, branch, remotos (sem credenciais), tags,
       referencias de backup, arquivos rastreados que nao deveriam estar, assinatura
    G2. Outras pastas do htdocs e um eventual repositorio Git na raiz do htdocs (so nomes,
       existencia e contagens; nenhum conteudo)
    H. Varredura de segredos e dados pessoais, com saida REDIGIDA: so categoria, arquivo,
       commit/tag e quantidade - NUNCA o valor encontrado. Estado atual, logs e historico do Git.

  Uso (na pasta do projeto):  scripts\seguranca\diagnostico_somente_leitura.bat
  Compativel com o Windows PowerShell 5.1. Mensagens sem acento de proposito (console do Windows).
#>
param([string]$Saida = "")

$ErrorActionPreference = 'Continue'
$Raiz = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$Pasta = Split-Path -Leaf $Raiz
$Xampp = (Resolve-Path (Join-Path $Raiz '..\..') -ErrorAction SilentlyContinue).Path
if (-not $Xampp -or -not (Test-Path (Join-Path $Xampp 'apache'))) { $Xampp = 'C:\xampp' }
if (-not $Saida) {
    $Saida = Join-Path $Raiz ("logs\diagnostico_seguranca-" + (Get-Date -Format 'yyyyMMdd-HHmmss') + ".log")
}
New-Item -ItemType Directory -Force -Path (Split-Path -Parent $Saida) | Out-Null
$script:L = New-Object System.Collections.Generic.List[string]

function W([string]$t) { Write-Host $t; $script:L.Add($t) }
function Titulo([string]$t) { W ""; W ("== " + $t + " " + ('=' * [Math]::Max(3, 70 - $t.Length))) }
# Linhas de um arquivo de configuracao que casam com o padrao, sem comentarios
function LinhasConf([string]$arq, [string]$padrao) {
    if (-not (Test-Path -LiteralPath $arq)) { W "  (arquivo nao encontrado: $arq)"; return }
    $n = 0
    foreach ($l in (Get-Content -LiteralPath $arq -ErrorAction SilentlyContinue)) {
        $n++
        $t = $l.Trim()
        if ($t -eq '' -or $t.StartsWith('#') -or $t.StartsWith(';')) { continue }
        if ($t -match $padrao) { W ("  l.{0,-5} {1}" -f $n, $t) }
    }
}
# Esconde usuario/senha/token que estejam dentro de URLs de remotos (troca por <redigido>)
function SemCredencial([string]$t) { return ($t -replace '(?i)(https?://)[^/@\s]+@', '$1<redigido>@') }

W "Diagnostico de seguranca (somente leitura) - JovemConnect"
W ("Data: " + (Get-Date -Format 'dd/MM/yyyy HH:mm:ss') + "   Computador: " + $env:COMPUTERNAME)
W "Projeto: $Raiz"
W "XAMPP (presumido): $Xampp"
W ("Windows: " + [Environment]::OSVersion.VersionString + "   PowerShell: " + $PSVersionTable.PSVersion.ToString())

# ---------------------------------------------------------------- A/B Apache
Titulo "A. Versoes"
$httpd = Join-Path $Xampp 'apache\bin\httpd.exe'
$php = Join-Path $Xampp 'php\php.exe'
$mysqld = Join-Path $Xampp 'mysql\bin\mysqld.exe'
if (Test-Path $httpd) { W ("  Apache : " + ((& $httpd -v 2>&1 | Select-Object -First 1) -join '')) } else { W "  Apache : httpd.exe nao encontrado" }
if (Test-Path $php) { W ("  PHP    : " + ((& $php -v 2>&1 | Select-Object -First 1) -join '')) } else { W "  PHP    : php.exe nao encontrado" }
if (Test-Path $mysqld) { W ("  MariaDB: " + ((& $mysqld --version 2>&1 | Select-Object -First 1) -join '')) } else { W "  MariaDB: mysqld.exe nao encontrado" }

Titulo "B. Apache: configuracao que decide se o .htaccess vale"
$conf = Join-Path $Xampp 'apache\conf\httpd.conf'
W "  httpd.conf - Listen, ServerName, ServerTokens/Signature, Includes:"
LinhasConf $conf '^(Listen|ServerName|ServerTokens|ServerSignature|Include)\b'
W "  httpd.conf - bloco da pasta htdocs (Options / AllowOverride / Require):"
if (Test-Path -LiteralPath $conf) {
    $dentro = $false; $n = 0
    foreach ($l in (Get-Content -LiteralPath $conf)) {
        $n++
        $t = $l.Trim()
        if ($t -match '^<Directory\s+"?[^">]*htdocs"?\s*>') { $dentro = $true; W ("  l.{0,-5} {1}" -f $n, $t); continue }
        if ($dentro) {
            if ($t -match '^(Options|AllowOverride|Require|Order|Allow|Deny)\b') { W ("  l.{0,-5} {1}" -f $n, $t) }
            if ($t -match '^</Directory>') { $dentro = $false }
        }
    }
}
W "  Modulos carregados que o .htaccess do projeto usa (authz_core obrigatorio):"
if (Test-Path $httpd) {
    $mods = (& $httpd -M 2>&1) -join "`n"
    foreach ($m in @('authz_core_module', 'access_compat_module', 'alias_module', 'rewrite_module', 'headers_module', 'php_module', 'php7_module', 'php8_module')) {
        $tem = $mods -match ("\b" + $m + "\b")
        W ("    {0,-22} {1}" -f $m, $(if ($tem) { 'carregado' } else { '-' }))
    }
}
W "  httpd-vhosts.conf (VirtualHosts ativos):"
LinhasConf (Join-Path $Xampp 'apache\conf\extra\httpd-vhosts.conf') '^(<VirtualHost|DocumentRoot|ServerName|</VirtualHost)'
W "  httpd-xampp.conf (phpMyAdmin e outras areas do XAMPP: 'Require local' = so este PC):"
LinhasConf (Join-Path $Xampp 'apache\conf\extra\httpd-xampp.conf') '^(Alias|<Directory|<LocationMatch|Require|AllowOverride|</Directory)'

# ---------------------------------------------------------------- C PHP
Titulo "C. PHP"
if (Test-Path $php) {
    $tmpPhp = Join-Path $env:TEMP ('jc_diag_' + [guid]::NewGuid().ToString('N') + '.php')
    $phpCodigo = @'
<?php
foreach (['display_errors','display_startup_errors','log_errors','error_log','expose_php','session.cookie_httponly',
          'session.use_strict_mode','session.cookie_samesite','session.gc_maxlifetime','file_uploads',
          'upload_max_filesize','post_max_size','allow_url_include','allow_url_fopen','upload_tmp_dir'] as $k) {
    echo str_pad($k, 26), ' = ', var_export(ini_get($k), true), PHP_EOL;
}
echo str_pad('sys_get_temp_dir()', 26), ' = ', sys_get_temp_dir(), PHP_EOL;
foreach (['mysqli','curl','openssl','mbstring','fileinfo','json'] as $e) {
    echo str_pad('extensao ' . $e, 26), ' = ', extension_loaded($e) ? 'sim' : 'NAO', PHP_EOL;
}
'@
    try {
        [System.IO.File]::WriteAllText($tmpPhp, $phpCodigo)
        foreach ($l in (& $php $tmpPhp 2>&1)) { W ("  " + $l) }
    } finally {
        Remove-Item -LiteralPath $tmpPhp -Force -ErrorAction SilentlyContinue
    }
    W "  Observacao: o projeto esconde os erros da pagina por conta propria (includes/env.php);"
    W "  JC_EXIBIR_ERROS=1 no .env volta a mostrar (desenvolvimento)."
    W "  php -l em todos os .php do projeto (sintaxe com ESTA versao do PHP):"
    $total = 0; $erros = 0
    $phps = Get-ChildItem -LiteralPath $Raiz -Recurse -Filter *.php -File -ErrorAction SilentlyContinue |
            Where-Object { $_.FullName -notmatch '\\(venv[^\\]*|\.git|vendor|node_modules)\\' }
    foreach ($arqPhp in $phps) {
        $total++
        $saidaLint = (& $php -l $arqPhp.FullName 2>&1) -join ' '
        if ($saidaLint -notmatch 'No syntax errors') {
            $erros++
            W ("    ERRO: " + $arqPhp.FullName.Substring($Raiz.Length + 1) + " -> " + ($saidaLint -replace [regex]::Escape($Raiz), '<projeto>'))
        }
    }
    W ("    {0} de {1} arquivo(s) sem erro de sintaxe" -f ($total - $erros), $total)
}

# ---------------------------------------------------------------- D MariaDB
Titulo "D. MariaDB (my.ini)"
$myini = Join-Path $Xampp 'mysql\bin\my.ini'
if (-not (Test-Path $myini)) { $myini = Join-Path $Xampp 'mysql\my.ini' }
LinhasConf $myini '^(bind-address|port|skip-networking|socket)\b'
W "  (sem bind-address = escuta em todas as interfaces; recomendado em rede compartilhada: bind-address=127.0.0.1)"

# ---------------------------------------------------------------- E Rede
Titulo "E. Rede: quem escuta em que endereco"
$portas = @(80, 443, 3306) + (5000..5010)
$procs = @{}
Get-Process -ErrorAction SilentlyContinue | ForEach-Object { $procs[[int]$_.Id] = $_.ProcessName }
$escutas = @()
try {
    $escutas = Get-NetTCPConnection -State Listen -ErrorAction Stop | Where-Object { $portas -contains [int]$_.LocalPort } |
               ForEach-Object { [pscustomobject]@{ End = $_.LocalAddress; Porta = [int]$_.LocalPort; Proc = [int]$_.OwningProcess } }
} catch {
    # netstat em qualquer idioma: socket escutando = endereco remoto 0.0.0.0:0 ou [::]:0
    foreach ($l in (netstat -ano 2>$null)) {
        if ($l -match '^\s*TCP\s+(\S+):(\d+)\s+(0\.0\.0\.0:0|\[::\]:0)\s+\S+\s+(\d+)\s*$') {
            if ($portas -contains [int]$Matches[2]) {
                $escutas += [pscustomobject]@{ End = $Matches[1]; Porta = [int]$Matches[2]; Proc = [int]$Matches[4] }
            }
        }
    }
}
if (-not $escutas) { W "  nenhum servico escutando nas portas 80, 443, 3306 e 5000-5010 agora (XAMPP/IA desligados?)" }
foreach ($e in ($escutas | Sort-Object Porta, End -Unique)) {
    $local = ($e.End -eq '127.0.0.1' -or $e.End -eq '::1' -or $e.End -eq '[::1]')
    $alcance = if ($local) { 'so este PC' } else { 'TODAS as interfaces (rede local, se o firewall permitir)' }
    W ("  porta {0,-5} endereco {1,-16} processo {2,-12} -> {3}" -f $e.Porta, $e.End, $procs[$e.Proc], $alcance)
}
try {
    Get-NetConnectionProfile -ErrorAction Stop | ForEach-Object {
        W ("  Rede '{0}': categoria {1} (Publica = Windows bloqueia mais por padrao)" -f $_.Name, $_.NetworkCategory)
    }
} catch { W "  (tipo da rede: nao consegui ler)" }
try {
    Get-NetIPAddress -AddressFamily IPv4 -ErrorAction Stop | Where-Object { $_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.254.*' } |
        ForEach-Object { W ("  IPv4 deste PC: {0} ({1})" -f $_.IPAddress, $_.InterfaceAlias) }
} catch { }

# ---------------------------------------------------------------- F Firewall
Titulo "F. Firewall do Windows"
try {
    Get-NetFirewallProfile -ErrorAction Stop | ForEach-Object {
        W ("  perfil {0,-8} ativado={1}  entrada padrao={2}" -f $_.Name, $_.Enabled, $_.DefaultInboundAction)
    }
    $filtros = Get-NetFirewallApplicationFilter -ErrorAction Stop |
               Where-Object { $_.Program -match '(?i)\\(httpd|mysqld|mariadbd|python[0-9.]*|pythonw)\.exe$' }
    if (-not $filtros) { W "  nenhuma regra especifica para httpd/mysqld/python (vale o padrao do perfil)" }
    foreach ($f in $filtros) {
        $regra = $f | Get-NetFirewallRule -ErrorAction SilentlyContinue
        foreach ($r in $regra) {
            W ("  regra '{0}': {1} {2} {3} perfis={4} ativada={5}" -f $r.DisplayName, $r.Direction, $r.Action, (Split-Path -Leaf $f.Program), $r.Profile, $r.Enabled)
        }
    }
} catch { W "  nao consegui ler o firewall (pode exigir PowerShell como administrador): $($_.Exception.Message)" }

# ---------------------------------------------------------------- G Git
Titulo "G. Git (somente leitura)"
$git = Get-Command git -ErrorAction SilentlyContinue
if (-not $git) {
    W "  git nao encontrado no PATH: secao G e varredura do historico puladas."
} else {
    Push-Location $Raiz
    W ("  " + ((git --version) -join ''))
    W "  git status --short --branch:"
    foreach ($l in (git status --short --branch 2>&1)) { W ("    " + $l) }
    W ("  branch atual: " + ((git branch --show-current 2>&1) -join ''))
    W "  ultimos commits:"
    foreach ($l in (git log -5 --oneline 2>&1)) { W ("    " + $l) }
    W "  remotos (credenciais redigidas):"
    foreach ($l in (git remote -v 2>&1)) { W ("    " + (SemCredencial $l)) }
    $tags = @(git tag --list 2>$null)
    W ("  tags: {0}  ({1})" -f $tags.Count, ($tags -join ' '))
    W "  referencias de backup e evidencia (devem ser preservadas):"
    foreach ($l in (git for-each-ref --format='%(refname) %(objectname:short) %(creatordate:short)' refs/original refs/cline refs/heads 2>&1)) { W ("    " + $l) }
    W ("  entradas no reflog do HEAD: " + (@(git reflog 2>$null).Count))
    W ("  core.ignorecase = " + ((git config --get core.ignorecase) -join '') + "   core.autocrlf = " + ((git config --get core.autocrlf) -join ''))
    W ("  arquivos rastreados: " + (@(git ls-files).Count))
    W "  rastreados que nao deveriam estar (esperado: nenhum depois da correcao):"
    foreach ($f in @('geminiAPI.env', 'repomix-output.xml', '.env')) {
        $r = (git ls-files -- $f 2>$null) -join ''
        W ("    {0,-22} {1}" -f $f, $(if ($r) { 'RASTREADO' } else { 'nao rastreado' }))
    }
    W "  grafia de candidaturas/ no indice (Linux diferencia maiusculas):"
    foreach ($l in (git ls-files 2>$null | Where-Object { $_ -match '(?i)^candidaturas/' })) { W ("    " + $l) }
    W "  ignorados pelo .gitignore (esperado: todos ignorados):"
    foreach ($f in @('.env', 'geminiAPI.env', 'repomix-output.xml', 'logs/app.log', 'venv/pyvenv.cfg')) {
        $r = (git check-ignore -- $f 2>$null) -join ''
        W ("    {0,-22} {1}" -f $f, $(if ($r) { 'ignorado' } else { 'NAO ignorado' }))
    }
    W "  assinatura de commits/tags (so se esta configurada; a chave nunca e mostrada):"
    foreach ($k in @('user.signingkey', 'gpg.format', 'commit.gpgsign', 'tag.gpgsign')) {
        $v = (git config --get $k 2>$null) -join ''
        if ($k -eq 'user.signingkey') { $v = if ($v) { 'configurada' } else { 'nao configurada' } }
        W ("    {0,-16} {1}" -f $k, $(if ($v) { $v } else { '(vazio)' }))
    }
    $ganchos = Get-ChildItem -LiteralPath (Join-Path $Raiz '.git\hooks') -File -ErrorAction SilentlyContinue | Where-Object { $_.Name -notlike '*.sample' }
    W ("  ganchos (hooks) ativos: " + $(if ($ganchos) { ($ganchos.Name -join ', ') } else { 'nenhum' }))
    Pop-Location
}

# ---------------------------------------------------------------- G2 Fora do projeto
Titulo "G2. Outras pastas do htdocs (fora do projeto; so nomes e existencia)"
W "  O .htaccess do JovemConnect so protege a pasta do projeto. Copias do projeto ou outro"
W "  repositorio Git no mesmo htdocs ficam expostos pelo mesmo Apache."
$htdocs = Split-Path -Parent $Raiz
$padraoXampp = '^(dashboard|img|webalizer|xampp|forbidden|restricted)$'
foreach ($d in (Get-ChildItem -LiteralPath $htdocs -Directory -Force -ErrorAction SilentlyContinue)) {
    if ($d.Name -eq $Pasta) { continue }
    if ($d.Name -eq '.git') { W ("  {0,-24} {1}" -f '.git', 'REPOSITORIO GIT NA RAIZ DO HTDOCS (detalhes abaixo)'); continue }
    $marcas = @()
    foreach ($rel in @('.env', '.git', '.htaccess', 'schema.sql', 'repomix-output.xml', 'includes\match.php', 'IA\recomendar.py')) {
        if (Test-Path -LiteralPath (Join-Path $d.FullName $rel)) { $marcas += $rel }
    }
    $tipo = if ($d.Name -match $padraoXampp) { 'padrao do XAMPP' } elseif ($marcas -contains 'includes\match.php') { 'PARECE COPIA DO JOVEMCONNECT' } else { 'pasta' }
    W ("  {0,-24} {1,-34} {2}" -f $d.Name, $tipo, $(if ($marcas) { 'contem: ' + ($marcas -join ', ') } else { '' }))
}
if ($git -and (Test-Path -LiteralPath (Join-Path $htdocs '.git'))) {
    W "  repositorio Git na raiz do htdocs (comandos de leitura):"
    W ("    raiz do repositorio : " + ((git -C $htdocs rev-parse --show-toplevel 2>&1) -join ''))
    W ("    arquivos rastreados : " + (@(git -C $htdocs ls-files 2>$null).Count) + "   (dentro de $Pasta/: " + (@(git -C $htdocs ls-files -- "$Pasta/" 2>$null).Count) + ")")
    W ("    ultimo commit       : " + ((git -C $htdocs log -1 --format='%h %ci' 2>&1) -join ''))
    foreach ($l in (git -C $htdocs remote -v 2>&1)) { W ("    remoto: " + (SemCredencial $l)) }
    W "    -> o Apache pode entregar http://localhost/.git/ (verifique com verificar_protecao.bat)."
}

# ---------------------------------------------------------------- H Varredura redigida
Titulo "H. Varredura de segredos e dados pessoais (saida redigida)"
W "  Mostra so categoria, arquivo, commit/tag e quantidade. O valor encontrado NUNCA aparece."
$padroes = [ordered]@{
    'chave Google/Gemini'      = 'AIza[0-9A-Za-z_-]{35}'
    'token GitHub'             = 'gh[pousr]_[0-9A-Za-z]{36,}'
    'chave privada'            = '-----BEGIN [A-Z ]*PRIVATE KEY-----'
    'Twilio SID/chave'         = '\b(AC|SK)[0-9a-f]{32}\b'
    'chave estilo sk-'         = '\bsk-[A-Za-z0-9_-]{20,}'
    'JWT'                      = 'eyJ[A-Za-z0-9_-]{10,}\.eyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}'
    'URL com credencial'       = '[a-z]+://[^/\s:@]+:[^/\s@]+@[a-z0-9.-]+'
}
# So em arquivos de configuracao (.env, .ini, .cfg, .json, .example...): CHAVE=valor preenchido
# [\t\x20] = espaco ou tab sem pular linha; \x22 e \x27 = aspas (sem aspas nem espacos literais, o padrao
# chega intacto ao git grep do PowerShell 5.1)
$padraoConf = '(?im)^[\t\x20]*[A-Z0-9_]*(PASS|SENHA|SECRET|SEGREDO|TOKEN|API_KEY|AUTH|_KEY)[A-Z0-9_]*[\t\x20]*=[\t\x20]*[\x22\x27]?[^\s#\x22\x27]{4,}'
function ArquivoConf([string]$nome) { return ($nome -match '(?i)(\.env(\..*)?|\.ini|\.cfg|\.conf|\.json|\.ya?ml|\.toml|\.example)$') }
function ExtensaoTexto([string]$nome) {
    return ($nome -match '(?i)\.(php|py|js|css|md|txt|sql|json|xml|env|example|ini|cfg|conf|bat|sh|ps1|htaccess|gitignore|gitattributes|log|html?)$' -or $nome -match '^\.')
}
function CpfValido([string]$c) {
    $d = ($c -replace '\D', '')
    if ($d.Length -ne 11 -or $d -match '^(\d)\1{10}$') { return $false }
    $n = $d.ToCharArray() | ForEach-Object { [int][string]$_ }
    $s = 0; for ($i = 0; $i -lt 9; $i++) { $s += $n[$i] * (10 - $i) }
    $v1 = (($s * 10) % 11) % 10
    $s = 0; for ($i = 0; $i -lt 10; $i++) { $s += $n[$i] * (11 - $i) }
    $v2 = (($s * 10) % 11) % 10
    return ($v1 -eq $n[9] -and $v2 -eq $n[10])
}
$dominiosExemplo = '(?i)@(exemplo|example|teste|test|demo|jovemconnect|empresa-demo|seed)[a-z0-9.-]*\.|@localhost'

W ""
W "  H1. Arquivos atuais (sem .git, venv, curriculos; o .env e o lugar certo dos segredos e NAO e lido):"
$arquivos = Get-ChildItem -LiteralPath $Raiz -Recurse -File -Force -ErrorAction SilentlyContinue |
    Where-Object { $_.FullName -notmatch '\\(\.git|venv[^\\]*|node_modules|vendor|__pycache__)\\' -and
                   $_.FullName -notmatch '\\uploads\\curriculos\\' -and $_.Name -ne '.env' -and
                   $_.Name -notmatch '^(diagnostico_seguranca|verificacao_protecao|regressao_isolada)-.*\.log$' -and
                   (ExtensaoTexto $_.Name) -and $_.Length -lt 5MB }
$achou = 0
foreach ($a in $arquivos) {
    $rel = $a.FullName.Substring($Raiz.Length + 1)
    $txt = [System.IO.File]::ReadAllText($a.FullName)
    foreach ($k in $padroes.Keys) {
        $q = ([regex]::Matches($txt, $padroes[$k])).Count
        if ($q -gt 0) { $achou++; W ("    {0,-26} {1,-55} {2} ocorrencia(s)" -f $k, $rel, $q) }
    }
    if (ArquivoConf $a.Name) {
        $q = ([regex]::Matches($txt, $padraoConf)).Count
        if ($q -gt 0) { $achou++; W ("    {0,-26} {1,-55} {2} ocorrencia(s) (confira se e marcador de exemplo)" -f 'CHAVE=valor em config', $rel, $q) }
    }
    $cpfs = 0
    foreach ($m in [regex]::Matches($txt, '\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b')) { if (CpfValido $m.Value) { $cpfs++ } }
    if ($cpfs -gt 0) { $achou++; W ("    {0,-26} {1,-55} {2} ocorrencia(s)" -f 'CPF valido', $rel, $cpfs) }
    $emails = @([regex]::Matches($txt, '[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}') | ForEach-Object { $_.Value.ToLower() } |
              Where-Object { $_ -notmatch $dominiosExemplo } | Sort-Object -Unique)
    if ($rel -like 'logs\*' -and $emails.Count -gt 0) { $achou++; W ("    {0,-26} {1,-55} {2} endereco(s) distinto(s)" -f 'e-mail em log', $rel, $emails.Count) }
    if ($rel -like 'logs\*') {
        $ips = @([regex]::Matches($txt, '\b(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})\b') | Where-Object { $_.Groups[4].Value -ne '0' -and $_.Value -notlike '127.*' } | ForEach-Object { $_.Value } | Sort-Object -Unique)
        if ($ips.Count -gt 0) { $achou++; W ("    {0,-26} {1,-55} {2} IP(s) completo(s)" -f 'IP sem anonimizar em log', $rel, $ips.Count) }
    }
}
if ($achou -eq 0) { W "    nenhuma ocorrencia" }
$envPath = Join-Path $Raiz '.env'
W ("    .env: " + $(if (Test-Path -LiteralPath $envPath) { 'existe (nao lido; protegido pelo .htaccess e ignorado pelo Git)' } else { 'nao existe' }))

if ($git) {
    Push-Location $Raiz
    W ""
    W "  H2. Historico do Git (todas as referencias e o reflog; nada e reescrito):"
    $revs = @(git rev-list --all --reflog 2>$null | Sort-Object -Unique)
    W ("    commits examinados: " + $revs.Count)
    $pcre = $true
    $teste = (git grep -q -P -e 'x' HEAD -- . 2>&1) -join ' '
    if ($teste -match '(?i)perl|pcre') {
        $pcre = $false
        W "    AVISO: este git nao tem expressoes Perl (PCRE); a varredura do historico foi pulada."
    }
    $nomes = @{}
    function NomeRev([string]$rev) {
        if (-not $nomes.ContainsKey($rev)) {
            $nomes[$rev] = ((git name-rev --name-only --always $rev 2>$null) -join '').Trim()
        }
        return $nomes[$rev]
    }
    $lotes = @()
    for ($i = 0; $i -lt $revs.Count; $i += 40) { $lotes += ,($revs[$i..([Math]::Min($i + 39, $revs.Count - 1))]) }
    $algum = $false
    $buscas = [ordered]@{}
    foreach ($k in $padroes.Keys) { $buscas[$k] = @($padroes[$k], @('.')) }
    $buscas['CHAVE=valor em config'] = @(($padraoConf -replace '^\(\?im\)', '(?i)'), @('*.env', '*.env.*', '*.ini', '*.cfg', '*.conf', '*.json', '*.example'))
    foreach ($k in $(if ($pcre) { $buscas.Keys } else { @() })) {
        $porArquivo = @{}
        foreach ($lote in $lotes) {
            foreach ($l in (git grep -I -c -P -e $buscas[$k][0] @lote -- @($buscas[$k][1]) 2>$null)) {
                if ($l -match '^([0-9a-f]{40}):(.+):(\d+)$') {
                    $arq = $Matches[2]
                    if (-not $porArquivo.ContainsKey($arq)) { $porArquivo[$arq] = New-Object System.Collections.Generic.List[string] }
                    $porArquivo[$arq].Add($Matches[1])
                }
            }
        }
        foreach ($arq in $porArquivo.Keys) {
            $algum = $true
            $cs = $porArquivo[$arq]
            $refs = ($cs | ForEach-Object { NomeRev $_ } | Sort-Object -Unique | Select-Object -First 8) -join ', '
            W ("    {0,-26} {1,-40} em {2} commit(s): {3}" -f $k, $arq, $cs.Count, $refs)
        }
    }
    # CPF valido e e-mails reais no historico (valor validado aqui e descartado; so a contagem sai)
    $cpfPorArq = @{}
    foreach ($lote in $(if ($pcre) { $lotes } else { @() })) {
        foreach ($l in (git grep -I -o -P -e '\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b' @lote -- '*.sql' '*.php' '*.md' '*.json' '*.txt' 2>$null)) {
            if ($l -match '^([0-9a-f]{40}):(.+):(\S+)$' -and (CpfValido $Matches[3])) {
                $chave = $Matches[2]
                if (-not $cpfPorArq.ContainsKey($chave)) { $cpfPorArq[$chave] = New-Object System.Collections.Generic.HashSet[string] }
                [void]$cpfPorArq[$chave].Add($Matches[1])
            }
        }
    }
    foreach ($arq in $cpfPorArq.Keys) {
        $algum = $true
        $cs = @($cpfPorArq[$arq])
        $refs = ($cs | ForEach-Object { NomeRev $_ } | Sort-Object -Unique | Select-Object -First 8) -join ', '
        W ("    {0,-26} {1,-40} em {2} commit(s): {3}" -f 'CPF valido', $arq, $cs.Count, $refs)
    }
    $envHist = @{}
    foreach ($rev in $revs) {
        foreach ($f in (git ls-tree -r --name-only $rev 2>$null | Where-Object { $_ -match '(^|/)(\.env|[^/]*\.env)$' -and $_ -ne '.env.example' })) {
            $tam = ((git cat-file -s ("{0}:{1}" -f $rev, $f) 2>$null) -join '')
            if ($tam -and [int]$tam -gt 0) {
                if (-not $envHist.ContainsKey($f)) { $envHist[$f] = New-Object System.Collections.Generic.List[string] }
                $envHist[$f].Add($rev)
            }
        }
    }
    foreach ($f in $envHist.Keys) {
        $algum = $true
        $refs = ($envHist[$f] | ForEach-Object { NomeRev $_ } | Sort-Object -Unique | Select-Object -First 8) -join ', '
        W ("    {0,-26} {1,-40} nao vazio em {2} commit(s): {3}" -f 'arquivo .env versionado', $f, $envHist[$f].Count, $refs)
    }
    if (-not $algum) { W "    nenhuma ocorrencia no historico" }
    W "    Lembrete: reescrever o historico NAO e feito por este script (decisao do proprietario; ver relatorio)."
    Pop-Location
}

W ""
W "Fim do diagnostico. Relatorio: $Saida"
$script:L | Set-Content -Path $Saida -Encoding UTF8
exit 0
