<#
  scripts\seguranca\verificar_protecao.ps1 - verificador da protecao do Apache (JovemConnect)

  O que faz (somente leitura, exceto dois arquivos de teste temporarios):
    1. pede pelo Apache os arquivos e pastas INTERNOS que existem nesta pasta
       (.env, .git, docs, .sql, .py, venv, logs...) - esperado: bloqueado (403/404);
    2. tenta truques de nome do Windows (x.php., x.php::$DATA, maiusculas, %2e, ponto ou
       espaco no fim de pasta, ".git.", e os nomes curtos 8.3 REAIS do disco, como "GIT~1"):
       o codigo-fonte e os segredos nao podem sair;
    3. confere se a listagem de pastas esta desligada;
    4. cria um arquivo de teste em logs\ e em uploads\curriculos\, pede pelo
       Apache e apaga na hora (confirma que essas pastas estao fechadas);
    5. confere se as paginas e os arquivos do site continuam funcionando;
    6. confere o que fica FORA do projeto no mesmo Apache (.git na raiz do htdocs, .env e
       .git de outras pastas do htdocs) - o .htaccess do JovemConnect nao cobre essas pastas;
    7. com -Rede: repete alguns testes pelos IPs da rede local deste PC.

  Mostra so: caminho, codigo HTTP, esperado, observado e APROVADO/REPROVADO.
  NUNCA mostra nem grava o conteudo dos arquivos (podem conter senhas).
  O relatorio vai para logs\verificacao_protecao-AAAAMMDD-HHMMSS.log (fora do Git).

  Uso (na pasta do projeto):
    scripts\seguranca\verificar_protecao.bat
    powershell -ExecutionPolicy Bypass -File scripts\seguranca\verificar_protecao.ps1 [-Base http://127.0.0.1/JovemConnect] [-Rede]

  Codigo de saida: 0 = tudo aprovado; 1 = alguma reprovacao; 2 = Apache nao respondeu.
  Compativel com o Windows PowerShell 5.1 (padrao do Windows 10/11).
#>
param(
    [string]$Base = "",
    [switch]$Rede,
    [string]$Saida = ""
)

$ErrorActionPreference = 'Stop'
$Raiz = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$Pasta = Split-Path -Leaf $Raiz
if (-not $Base) { $Base = "http://127.0.0.1/$Pasta" }
$Base = $Base.TrimEnd('/')
if (-not $Saida) {
    $Saida = Join-Path $Raiz ("logs\verificacao_protecao-" + (Get-Date -Format 'yyyyMMdd-HHmmss') + ".log")
}
New-Item -ItemType Directory -Force -Path (Split-Path -Parent $Saida) | Out-Null

$script:Linhas = New-Object System.Collections.Generic.List[string]
$script:Aprovados = 0
$script:Reprovados = 0
$script:Pulados = 0

function Escrever([string]$texto) {
    Write-Host $texto
    $script:Linhas.Add($texto)
}

# Pedido HTTP "cru" por TCP: o caminho vai EXATAMENTE como escrito (o .NET normalizaria
# "x.php." para "x.php" e decodificaria %2e, e o teste perderia o sentido).
function Pedir([string]$baseUrl, [string]$caminho) {
    $u = [Uri]$baseUrl
    $hostNome = $u.Host
    $porta = $u.Port
    $prefixo = $u.AbsolutePath.TrimEnd('/')
    $alvo = $prefixo + '/' + $caminho
    $r = @{ Codigo = 0; Corpo = ''; Cabecalhos = ''; Erro = '' }
    $cliente = New-Object System.Net.Sockets.TcpClient
    try {
        $tarefa = $cliente.BeginConnect($hostNome, $porta, $null, $null)
        if (-not $tarefa.AsyncWaitHandle.WaitOne(3000)) { throw "sem resposta em ${hostNome}:${porta}" }
        $cliente.EndConnect($tarefa)
        $cliente.ReceiveTimeout = 5000
        $fluxo = $cliente.GetStream()
        $pedido = "GET $alvo HTTP/1.1`r`nHost: ${hostNome}`r`nUser-Agent: JovemConnect-verificador`r`nAccept: */*`r`nConnection: close`r`n`r`n"
        $bytes = [System.Text.Encoding]::ASCII.GetBytes($pedido)
        $fluxo.Write($bytes, 0, $bytes.Length)
        $mem = New-Object System.IO.MemoryStream
        $buf = New-Object byte[] 8192
        while ($mem.Length -lt 262144) {
            try { $n = $fluxo.Read($buf, 0, $buf.Length) } catch { break }
            if ($n -le 0) { break }
            $mem.Write($buf, 0, $n)
        }
        $texto = [System.Text.Encoding]::GetEncoding(28591).GetString($mem.ToArray())
        if ($texto -match '^HTTP/\d\.\d (\d{3})') { $r.Codigo = [int]$Matches[1] }
        $i = $texto.IndexOf("`r`n`r`n")
        if ($i -ge 0) { $r.Cabecalhos = $texto.Substring(0, $i); $r.Corpo = $texto.Substring($i + 4) }
    } catch {
        $r.Erro = $_.Exception.Message
    } finally {
        $cliente.Close()
    }
    return $r
}

function Registrar([string]$grupo, [string]$caminho, [int]$codigo, [string]$esperado, [string]$observado, [bool]$ok) {
    if ($ok) { $script:Aprovados++; $res = 'APROVADO' } else { $script:Reprovados++; $res = 'REPROVADO' }
    $cod = if ($codigo -eq 0) { '---' } else { "$codigo" }
    Escrever ("{0,-9} {1,-52} {2,4}  {3,-22} {4,-26} {5}" -f $grupo, $caminho, $cod, $esperado, $observado, $res)
}

function Pular([string]$grupo, [string]$caminho, [string]$motivo) {
    $script:Pulados++
    Escrever ("{0,-9} {1,-52} {2,4}  {3}" -f $grupo, $caminho, '', "PULADO ($motivo)")
}

function Bloqueado([int]$c) { return ($c -eq 403 -or $c -eq 404) }

Escrever "Verificacao da protecao do Apache - JovemConnect"
Escrever ("Data: " + (Get-Date -Format 'dd/MM/yyyy HH:mm:ss') + "   Computador: " + $env:COMPUTERNAME)
Escrever "Endereco testado: $Base/"
Escrever "Conteudo dos arquivos: nunca mostrado nem gravado."
Escrever ""

$inicio = Pedir $Base 'index.php'
if ($inicio.Codigo -eq 0) {
    Escrever "ERRO: o Apache nao respondeu em $Base/index.php ($($inicio.Erro))."
    Escrever "Ligue o Apache (XAMPP ou iniciar_jovemconnect.bat) e rode de novo. Nada foi testado."
    $script:Linhas | Set-Content -Path $Saida -Encoding UTF8
    exit 2
}

Escrever ("{0,-9} {1,-52} {2,4}  {3,-22} {4,-26} {5}" -f 'GRUPO', 'CAMINHO', 'HTTP', 'ESPERADO', 'OBSERVADO', 'RESULTADO')
Escrever ('-' * 130)

# 1. Arquivos e pastas internos: so os que existem (um 404 de arquivo inexistente nao prova nada)
$internos = @(
    '.env', '.env.example', '.gitignore', '.gitattributes', '.htaccess', 'geminiAPI.env',
    'repomix-output.xml', 'schema.sql', 'nextupdaqtes.sql', 'composer.json', 'README.md',
    'requirements.txt', 'iniciar_jovemconnect.bat', 'parar_jovemconnect.bat', 'iniciar_ia.bat',
    'iniciar_ia.sh', '.git/HEAD', '.git/config', '.git/index', '.git/logs/HEAD',
    'docs/README.md', 'docs/IA_E_MATCH.md', 'docs/ARQUIVOS_E_FUNCOES.md', 'docs/SEGURANCA_E_PRIVACIDADE.md',
    'database/seed_demo.sql', 'database/diagnostico_somente_leitura.sql',
    'migrations/2026-10-11_stage7_admin_moderacao.sql', 'includes/config.php', 'includes/env.php',
    'includes/match.php', 'scripts/admin.php', 'scripts/jovemconnect.php',
    'scripts/seguranca/verificar_protecao.ps1', 'IA/recomendar.py', 'IA/verificar_ambiente.py',
    'IA/requirements-ia.txt', 'venv/pyvenv.cfg', 'logs/app.log', 'logs/seguranca.log',
    'logs/ia_porta.json', 'uploads/curriculos/.htaccess'
)
foreach ($rel in $internos) {
    $disco = Join-Path $Raiz ($rel -replace '/', '\')
    if (-not (Test-Path -LiteralPath $disco)) { Pular 'interno' $rel 'nao existe nesta pasta'; continue }
    $r = Pedir $Base $rel
    $ok = Bloqueado $r.Codigo
    $obs = if ($ok) { 'bloqueado' } elseif ($r.Codigo -eq 0) { "sem resposta" } else { 'ACESSIVEL' }
    Registrar 'interno' $rel $r.Codigo 'bloqueado (403/404)' $obs $ok
}
# pastas internas que o Git ignora (sem .htaccess proprio)
foreach ($dir in @('.git/', 'venv/', 'IA/__pycache__/')) {
    $disco = Join-Path $Raiz ($dir.TrimEnd('/') -replace '/', '\')
    if (-not (Test-Path -LiteralPath $disco)) { Pular 'pasta' $dir 'nao existe nesta pasta'; continue }
    $r = Pedir $Base $dir
    $ok = Bloqueado $r.Codigo
    Registrar 'pasta' $dir $r.Codigo 'bloqueado (403/404)' $(if ($ok) { 'bloqueado' } else { 'ACESSIVEL' }) $ok
}
Get-ChildItem -LiteralPath $Raiz -Directory -Filter 'venv.antigo-*' -ErrorAction SilentlyContinue | ForEach-Object {
    $rel = $_.Name + '/pyvenv.cfg'
    if (Test-Path -LiteralPath (Join-Path $_.FullName 'pyvenv.cfg')) {
        $r = Pedir $Base $rel
        $ok = Bloqueado $r.Codigo
        Registrar 'pasta' $rel $r.Codigo 'bloqueado (403/404)' $(if ($ok) { 'bloqueado' } else { 'ACESSIVEL' }) $ok
    }
}

# 2. Truques de nome (Windows): maiusculas, ponto/espaco no fim, ::$DATA, %2e
$variantes = @(
    @('.ENV', '.env'), @('.Env', '.env'), @('.env.', '.env'), @('.env%20', '.env'), @('.env::$DATA', '.env'),
    @('%2eenv', '.env'), @('%2Egit/HEAD', '.git/HEAD'), @('.GIT/config', '.git/config'),
    @('README.MD', 'README.md'), @('README.md.', 'README.md'), @('Schema.SQL', 'schema.sql'),
    @('DOCS/README.md', 'docs/README.md'), @('Docs/IA_E_MATCH.md', 'docs/IA_E_MATCH.md'),
    @('docs%2fREADME.md', 'docs/README.md'), @('IA/recomendar.PY', 'IA/recomendar.py'),
    @('VENV/pyvenv.cfg', 'venv/pyvenv.cfg'), @('Logs/app.log', 'logs/app.log'),
    @('repomix-output.XML', 'repomix-output.xml'), @('composer.JSON', 'composer.json'),
    @('.git./HEAD', '.git/HEAD'), @('.git%20/HEAD', '.git/HEAD'), @('.git::$INDEX_ALLOCATION/HEAD', '.git/HEAD'),
    @('%2Egit./config', '.git/config'), @('includes./config.php', 'includes/config.php'),
    @('docs./README.md', 'docs/README.md'), @('logs./app.log', 'logs/app.log')
)
foreach ($v in $variantes) {
    $disco = Join-Path $Raiz ($v[1] -replace '/', '\')
    if (-not (Test-Path -LiteralPath $disco)) { Pular 'variante' $v[0] 'arquivo real nao existe'; continue }
    $r = Pedir $Base $v[0]
    $ok = (Bloqueado $r.Codigo) -or ($r.Codigo -eq 400)
    Registrar 'variante' $v[0] $r.Codigo 'bloqueado (400/403/404)' $(if ($ok) { 'bloqueado' } else { 'ACESSIVEL' }) $ok
}
# Nomes curtos 8.3 REAIS deste disco (ex.: "GIT~1" no lugar de ".git", "ENV~1" no lugar de ".env"):
# o Windows aceita os dois nomes para o mesmo arquivo. So testa quando o nome curto e diferente.
try {
    $fso = New-Object -ComObject Scripting.FileSystemObject
    foreach ($alvo in @(@('.env', 'arquivo', ''), @('geminiAPI.env', 'arquivo', ''), @('repomix-output.xml', 'arquivo', ''),
                        @('.git', 'pasta', 'HEAD'), @('includes', 'pasta', 'config.php'), @('migrations', 'pasta', ''),
                        @('database', 'pasta', 'seed_demo.sql'))) {
        $p = Join-Path $Raiz $alvo[0]
        if (-not (Test-Path -LiteralPath $p)) { continue }
        $curto = if ($alvo[1] -eq 'pasta') { $fso.GetFolder($p).ShortName } else { $fso.GetFile($p).ShortName }
        if (-not $curto -or $curto -eq $alvo[0]) { continue }
        $caminho = if ($alvo[2]) { $curto + '/' + $alvo[2] } else { $curto }
        $r = Pedir $Base $caminho
        $ok = (Bloqueado $r.Codigo) -or ($r.Codigo -eq 400)
        Registrar 'nome8.3' $caminho $r.Codigo 'bloqueado (400/403/404)' $(if ($ok) { 'bloqueado' } else { 'ACESSIVEL' }) $ok
    }
} catch {
    Pular 'nome8.3' '(nomes curtos)' 'nao consegui ler os nomes 8.3 deste disco'
}

# codigo-fonte do PHP nao pode sair como texto
foreach ($v in @('index.php.', 'index.php%20', 'index.php::$DATA', 'index.php%3A%3A%24DATA', 'INDEX.PHP.',
                 'includes/config.php.', 'jovem/login.php.', 'IA/avaliar_ajax.php::$DATA')) {
    $r = Pedir $Base $v
    $fonte = ($r.Corpo -match '<\?php')
    $ok = -not $fonte
    $obs = if ($fonte) { 'CODIGO-FONTE EXPOSTO' } elseif (Bloqueado $r.Codigo) { 'bloqueado' } else { 'sem codigo-fonte' }
    Registrar 'fonte' $v $r.Codigo 'sem codigo-fonte' $obs $ok
}

# 3. Listagem de pastas
foreach ($dir in @('assets/', 'assets/img/', 'IA/', 'Vagas/', 'uploads/')) {
    $r = Pedir $Base $dir
    $lista = ($r.Codigo -eq 200 -and $r.Corpo -match 'Index of')
    Registrar 'listagem' $dir $r.Codigo 'sem listagem' $(if ($lista) { 'LISTAGEM LIGADA' } else { 'sem listagem' }) (-not $lista)
}

# 4. Pastas que recebem arquivos: arquivo de teste temporario (criado e apagado na hora)
foreach ($dir in @('logs', 'uploads\curriculos')) {
    $nome = 'teste_protecao_' + [guid]::NewGuid().ToString('N').Substring(0, 8) + '.txt'
    $arq = Join-Path (Join-Path $Raiz $dir) $nome
    try {
        Set-Content -LiteralPath $arq -Value 'jovemconnect-teste' -Encoding ASCII
    } catch {
        Pular 'canario' "$dir/" 'sem permissao para criar o arquivo de teste'
        continue
    }
    $r = Pedir $Base (($dir -replace '\\', '/') + '/' + $nome)
    Remove-Item -LiteralPath $arq -Force -ErrorAction SilentlyContinue
    $aberto = ($r.Codigo -eq 200 -and $r.Corpo -match 'jovemconnect-teste')
    Registrar 'canario' (($dir -replace '\\', '/') + '/<teste>.txt') $r.Codigo 'bloqueado' $(if ($aberto) { 'ABERTA' } else { 'bloqueado' }) (-not $aberto)
}

# 5. O site continua funcionando (nao pode ter virado 403 nem 500)
$site = @(
    @('index.php', @(200)), @('comecar.php', @(200)), @('documentos.php', @(200)), @('documentos.php?doc=privacidade', @(200)),
    @('jovem/login.php', @(200)), @('empresa/login.php', @(200)), @('jovem/cadastro.php', @(200)),
    @('empresa/cadastro.php', @(200)), @('recuperar_senha.php', @(200)), @('admin/login.php', @(200)),
    @('dashboard/jovem.php', @(302)), @('Vagas/listar.php', @(302, 200)), @('candidaturas/minhas.php', @(302)),
    @('IA/avaliar_ajax.php', @(401, 405)), @('IA/assistente_ajax.php', @(401, 403, 405)),
    @('uploads/ver_curriculo.php?id=1', @(302)), @('assets/css/style.css', @(200)), @('assets/css/admin.css', @(200)),
    @('assets/js/admin.js', @(200)), @('assets/img/marca/jovemconnect-icone-32.png', @(200)),
    @('assets/img/home/edificio-empresa.jpg', @(200))
)
foreach ($s in $site) {
    $r = Pedir $Base $s[0]
    $ok = ($s[1] -contains $r.Codigo)
    $esp = ($s[1] -join '/')
    $obs = if ($ok) { 'normal' } elseif ($r.Codigo -eq 403) { 'BLOQUEADO' } elseif ($r.Codigo -ge 500) { 'ERRO NO SERVIDOR' } else { 'diferente' }
    Registrar 'site' $s[0] $r.Codigo $esp $obs $ok
}
# Cabecalhos de seguranca na pagina inicial
foreach ($h in @('X-Content-Type-Options', 'X-Frame-Options', 'Referrer-Policy', 'Content-Security-Policy')) {
    $tem = ($inicio.Cabecalhos -match ("(?im)^" + [regex]::Escape($h) + ':'))
    Registrar 'cabecalho' "index.php -> $h" $inicio.Codigo 'presente' $(if ($tem) { 'presente' } else { 'AUSENTE' }) $tem
}

# 5b. Fora do projeto, no mesmo Apache: o .htaccess do JovemConnect NAO protege o resto do
#     htdocs. Testa so o que existe: .git na raiz do htdocs e .env/.git de pastas vizinhas.
$htdocs = Split-Path -Parent $Raiz
$sufixo = '/' + $Pasta
$script:ForaReprovados = 0
if ($Base.EndsWith($sufixo, [StringComparison]::OrdinalIgnoreCase)) {
    $baseRaiz = $Base.Substring(0, $Base.Length - $sufixo.Length)
    $fora = New-Object System.Collections.Generic.List[string]
    if (Test-Path -LiteralPath (Join-Path $htdocs '.git\HEAD')) { $fora.Add('.git/HEAD') }
    $padraoXampp = '^(dashboard|img|webalizer|xampp|forbidden|restricted)$'
    Get-ChildItem -LiteralPath $htdocs -Directory -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -ne $Pasta -and $_.Name -notmatch $padraoXampp -and -not $_.Name.StartsWith('.') } |
        ForEach-Object {
            foreach ($rel in @('.env', '.git/HEAD', 'schema.sql', 'repomix-output.xml')) {
                if (Test-Path -LiteralPath (Join-Path $_.FullName ($rel -replace '/', '\'))) { $fora.Add([Uri]::EscapeDataString($_.Name) + '/' + $rel) }
            }
        }
    foreach ($rel in $fora) {
        $r = Pedir $baseRaiz $rel
        $ok = Bloqueado $r.Codigo
        if (-not $ok) { $script:ForaReprovados++ }
        Registrar 'htdocs' ('../' + $rel) $r.Codigo 'bloqueado (403/404)' $(if ($ok) { 'bloqueado' } else { 'ACESSIVEL (fora do projeto)' }) $ok
    }
    if ($fora.Count -eq 0) { Escrever ("htdocs    {0,-52} {1,4}  {2}" -f '../ (fora do projeto)', '', 'nada sensivel encontrado nas pastas vizinhas') }
}

# 6. Alcance pela rede local (do proprio PC; outros dispositivos dependem do firewall)
if ($Rede) {
    Escrever ""
    Escrever "Testes pelos IPs da rede local deste PC (mostra se o Apache escuta nesses enderecos):"
    $ips = @()
    try {
        $ips = Get-NetIPAddress -AddressFamily IPv4 -ErrorAction Stop |
               Where-Object { $_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.254.*' } |
               Select-Object -ExpandProperty IPAddress
    } catch {
        $ips = [System.Net.Dns]::GetHostAddresses($env:COMPUTERNAME) |
               Where-Object { $_.AddressFamily -eq 'InterNetwork' -and $_.ToString() -notlike '127.*' } |
               ForEach-Object { $_.ToString() }
    }
    foreach ($ip in $ips) {
        $baseIp = "http://$ip/$Pasta"
        $r = Pedir $baseIp 'index.php'
        if ($r.Codigo -eq 0) {
            Escrever ("rede      {0,-52} ---  Apache NAO escuta neste IP (bom para rede compartilhada)" -f "$ip/index.php")
            continue
        }
        Escrever ("rede      {0,-52} {1,4}  Apache ESCUTA neste IP: outros dispositivos da rede podem abrir o site se o firewall permitir" -f "$ip/index.php", $r.Codigo)
        foreach ($rel in @('.env', '.git/HEAD', 'schema.sql', 'docs/README.md')) {
            if (-not (Test-Path -LiteralPath (Join-Path $Raiz ($rel -replace '/', '\')))) { continue }
            $r2 = Pedir $baseIp $rel
            $ok = Bloqueado $r2.Codigo
            Registrar 'rede' "$ip/$rel" $r2.Codigo 'bloqueado (403/404)' $(if ($ok) { 'bloqueado' } else { 'ACESSIVEL' }) $ok
        }
    }
}

Escrever ""
Escrever ("Resumo: {0} aprovado(s), {1} reprovado(s), {2} pulado(s) (arquivo inexistente nao e testado)." -f $script:Aprovados, $script:Reprovados, $script:Pulados)
if ($script:Reprovados -eq 0) {
    Escrever "RESULTADO: a protecao esta valendo neste Apache e o site responde normalmente."
} else {
    Escrever "RESULTADO: ha reprovacoes. Veja docs\SEGURANCA_E_PRIVACIDADE.md (PI-01) e docs\INSTALACAO_E_EXECUCAO.md."
    Escrever "  ACESSIVEL/ABERTA: o .htaccess nao esta valendo (AllowOverride) ou falta uma regra."
    Escrever "  BLOQUEADO/ERRO NO SERVIDOR em 'site': a protecao afetou o site (avise antes de usar)."
    if ($script:ForaReprovados -gt 0) {
        Escrever "  ACESSIVEL (fora do projeto): outra pasta do mesmo htdocs entrega arquivos internos. O .htaccess"
        Escrever "  do JovemConnect nao alcanca essas pastas: mova-as para fora do htdocs ou proteja-as (ver"
        Escrever "  docs\INSTALACAO_E_EXECUCAO.md, secao 'Protecao dos arquivos internos')."
    }
}
Escrever "Relatorio salvo em: $Saida"
$script:Linhas | Set-Content -Path $Saida -Encoding UTF8
if ($script:Reprovados -gt 0) { exit 1 } else { exit 0 }
