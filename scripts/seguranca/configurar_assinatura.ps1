<#
  scripts\seguranca\configurar_assinatura.ps1 - configura a assinatura de commits e tags com chave SSH

  Passo a passo guiado (PI-04). Cada passo pede confirmacao. O roteiro:
    1. confere o Git (2.34 ou mais novo);
    2. cria a chave %USERPROFILE%\.ssh\jc_assinatura (VOCE digita a frase-senha) - se ja existir,
       usa a existente e NUNCA a sobrescreve;
    3. cria/atualiza %USERPROFILE%\.ssh\allowed_signers (para o Git conferir as assinaturas);
    4. TESTA assinar e verificar um arquivo temporario, fora do repositorio;
    5. so entao configura o Git (global): gpg.format ssh, user.signingkey, commit.gpgsign,
       tag.gpgsign, gpg.ssh.allowedSignersFile e gpg.ssh.program;
    6. copia a chave PUBLICA para a area de transferencia, para cadastrar no GitHub.

  Nao faz commit, tag nem push, nao envia nada para lugar nenhum e nao copia a chave privada.
  A chave privada (jc_assinatura, sem .pub) nunca deve sair do seu computador.
  Roteiro: docs\PROCEDIMENTO_EVIDENCIAS.md, secao 1.
#>
$ErrorActionPreference = 'Stop'

function Perguntar([string]$texto) {
    while ($true) {
        $r = (Read-Host "$texto [S/N]").Trim().ToUpper()
        if ($r -eq 'S') { return $true }
        if ($r -eq 'N') { return $false }
    }
}
function Barra([string]$c) { return ($c -replace '\\', '/') }

Write-Host '--------------------------------------------------'
Write-Host ' JovemConnect - configurar a assinatura (SSH)'
Write-Host '--------------------------------------------------'

# 1. Git
if (-not (Get-Command git -ErrorAction SilentlyContinue)) { Write-Host 'git nao encontrado no PATH. Instale o Git for Windows.'; exit 1 }
$verTexto = (git --version) -join ''
if ($verTexto -notmatch '(\d+)\.(\d+)') { Write-Host "Nao entendi a versao do Git: $verTexto"; exit 1 }
$maior = [int]$Matches[1]; $menor = [int]$Matches[2]
if ($maior -lt 2 -or ($maior -eq 2 -and $menor -lt 34)) {
    Write-Host "Seu Git ($verTexto) e antigo para assinatura SSH. Atualize o Git for Windows (2.34 ou mais novo)."; exit 1
}
Write-Host "[1/6] $verTexto - ok"

# ssh-keygen: o que vem com o Git for Windows (OpenSSH recente); senao, o do Windows
$sshkeygen = ''
try {
    $exec = (git --exec-path) -join ''
    $raizGit = [System.IO.Path]::GetFullPath((Join-Path ($exec -replace '/', '\') '..\..\..'))
    $cand = Join-Path $raizGit 'usr\bin\ssh-keygen.exe'
    if (Test-Path -LiteralPath $cand) { $sshkeygen = $cand }
} catch { }
if (-not $sshkeygen) {
    $c = Get-Command ssh-keygen -ErrorAction SilentlyContinue
    if ($c) { $sshkeygen = $c.Source }
}
if (-not $sshkeygen) { Write-Host 'ssh-keygen nao encontrado (nem no Git for Windows nem no Windows).'; exit 1 }
Write-Host "      ssh-keygen: $sshkeygen"

# 2. Chave
$pastaSsh = Join-Path $env:USERPROFILE '.ssh'
$chave = Join-Path $pastaSsh 'jc_assinatura'
$publica = $chave + '.pub'
if ((Test-Path -LiteralPath $chave) -and (Test-Path -LiteralPath $publica)) {
    Write-Host "[2/6] A chave ja existe ($chave). Ela sera usada; nada sera sobrescrito."
} elseif ((Test-Path -LiteralPath $chave) -or (Test-Path -LiteralPath $publica)) {
    Write-Host "[2/6] Existe so metade da chave em $pastaSsh (jc_assinatura ou jc_assinatura.pub)."
    Write-Host '      Nada sera sobrescrito. Renomeie ou remova esse arquivo voce mesmo e rode de novo.'
    exit 1
} else {
    Write-Host '[2/6] Criar a chave de assinatura agora?'
    Write-Host '      O ssh-keygen vai pedir uma FRASE-SENHA (duas vezes). Use uma forte e guarde-a:'
    Write-Host '      ela sera pedida a cada commit ou tag assinados.'
    if (-not (Perguntar '      Criar a chave')) { Write-Host 'Cancelado. Nada foi alterado.'; exit 1 }
    if (-not (Test-Path -LiteralPath $pastaSsh)) { New-Item -ItemType Directory -Path $pastaSsh | Out-Null }
    & $sshkeygen -t ed25519 -C 'assinatura JovemConnect' -f (Barra $chave)
    if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $publica)) { Write-Host 'A chave nao foi criada.'; exit 1 }
    Write-Host '      Chave criada.'
}
$conteudoPub = (Get-Content -LiteralPath $publica -Raw).Trim()

# 3. E-mail e allowed_signers
$email = ((git config --global user.email) -join '').Trim()
if (-not $email) { $email = ((git config user.email) -join '').Trim() }
if (-not $email) {
    $email = (Read-Host '      E-mail que voce usa nos commits (o mesmo verificado no GitHub)').Trim()
    if (-not $email) { Write-Host 'Sem e-mail, nao da para continuar.'; exit 1 }
}
Write-Host "[3/6] E-mail dos commits: $email"
$assinantes = Join-Path $pastaSsh 'allowed_signers'
$linhaAssinante = "$email namespaces=`"git`" $conteudoPub"
$jaTem = $false
if (Test-Path -LiteralPath $assinantes) {
    $jaTem = @(Get-Content -LiteralPath $assinantes | Where-Object { $_.Trim() -eq $linhaAssinante }).Count -gt 0
}
if (-not $jaTem) {
    Add-Content -LiteralPath $assinantes -Value $linhaAssinante -Encoding ASCII
    Write-Host "      Linha acrescentada em $assinantes"
} else {
    Write-Host "      $assinantes ja tem esta chave."
}

# 4. Teste fora do repositorio
Write-Host '[4/6] Teste: assinar e verificar um arquivo temporario (digite a frase-senha se for pedida).'
$tmp = Join-Path $env:TEMP ('jc_teste_assinatura_' + [guid]::NewGuid().ToString('N') + '.txt')
Set-Content -LiteralPath $tmp -Value ('teste de assinatura ' + (Get-Date).ToString('s')) -Encoding ASCII
$assinatura = $tmp + '.sig'
& $sshkeygen -Y sign -f (Barra $chave) -n git (Barra $tmp)
$okAssinou = ($LASTEXITCODE -eq 0) -and (Test-Path -LiteralPath $assinatura)
$okConferiu = $false
if ($okAssinou) {
    $p = Start-Process -FilePath $sshkeygen -NoNewWindow -Wait -PassThru `
        -ArgumentList @('-Y', 'verify', '-f', ('"' + (Barra $assinantes) + '"'), '-I', $email, '-n', 'git', '-s', ('"' + (Barra $assinatura) + '"')) `
        -RedirectStandardInput $tmp
    $okConferiu = ($p.ExitCode -eq 0)
}
Remove-Item -LiteralPath $tmp, $assinatura -ErrorAction SilentlyContinue
if (-not ($okAssinou -and $okConferiu)) {
    Write-Host '      O TESTE FALHOU. O Git NAO foi configurado. Me envie a mensagem acima.'
    exit 1
}
Write-Host '      Teste aprovado: assinou e conferiu.'

# 5. Configuracao do Git (global)
Write-Host '[5/6] Configuracao atual do Git (global):'
foreach ($k in @('gpg.format', 'user.signingkey', 'commit.gpgsign', 'tag.gpgsign', 'gpg.ssh.allowedSignersFile', 'gpg.ssh.program')) {
    $v = ((git config --global $k) -join '').Trim()
    if (-not $v) { $v = '(vazio)' }
    Write-Host ("      {0,-28} {1}" -f $k, $v)
}
Write-Host '      Novos valores: assinatura SSH ligada para commits e tags, com a chave jc_assinatura.'
if (-not (Perguntar '      Gravar a configuracao')) { Write-Host 'Cancelado. A chave existe, mas o Git nao foi configurado.'; exit 1 }
git config --global gpg.format ssh
# Caminho da chave PRIVADA: sem ssh-agent, o ssh-keygen precisa dela (pede a frase-senha)
git config --global user.signingkey (Barra $chave)
git config --global commit.gpgsign true
git config --global tag.gpgsign true
git config --global gpg.ssh.allowedSignersFile (Barra $assinantes)
git config --global gpg.ssh.program (Barra $sshkeygen)
Write-Host '      Configuracao gravada.'

# 6. GitHub
try { Set-Clipboard -Value $conteudoPub; $copiou = $true } catch { $copiou = $false }
Write-Host '[6/6] Cadastre a chave PUBLICA no GitHub (para aparecer "Verified"):'
Write-Host '      GitHub > Settings > SSH and GPG keys > New SSH key > Key type: Signing Key'
if ($copiou) { Write-Host '      A chave publica ja esta na area de transferencia: cole (Ctrl+V) no campo Key.' }
Write-Host "      Chave publica: $conteudoPub"
Write-Host "      O e-mail $email precisa estar verificado na sua conta do GitHub."
Write-Host ''
Write-Host 'Pronto. Agora o git_correcao_seguranca.bat assina o commit e a tag v4.60.'
Write-Host 'Guarde a frase-senha. A chave PRIVADA (jc_assinatura, sem .pub) nunca deve ser enviada a ninguem.'
exit 0
