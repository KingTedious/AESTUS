<#
  scripts\seguranca\gerar_evidencias.ps1 - pacote de evidencias de uma versao (JovemConnect)

  Para registrar, DAQUI PARA FRENTE, provas de conteudo e de data de uma versao (PI-04).
  Nao reescreve nada, nao cria commit, tag nem push e nao gera chaves.

  Gera, numa pasta FORA do htdocs (padrao: Documentos\JovemConnect_evidencias\<versao>_<data>):
    - codigo_<versao>.zip ........ copia exata dos arquivos da versao (git archive)
    - MANIFESTO_<versao>.csv ..... cada arquivo: caminho, bytes, SHA-256 e o id do Git (blob)
    - jovemconnect_<data>.bundle . backup COMPLETO do repositorio (todas as referencias,
                                    inclusive backups e refs/original) - git bundle --all
    - SHA256SUMS.txt ............. hash dos arquivos acima
    - EVIDENCIA.txt .............. commit, tag (e se ela e assinada), datas e como conferir

  Depois: guarde a pasta em DOIS lugares independentes (ex.: pendrive/HD externo e um
  armazenamento de outra pessoa ou da escola) e, se quiser uma data externa, envie o
  SHA256SUMS.txt por e-mail para voce e para o orientador. Roteiro completo:
  docs\PROCEDIMENTO_EVIDENCIAS.md

  Uso: scripts\seguranca\gerar_evidencias.bat [-Versao v4.60] [-Destino D:\evidencias]
#>
param(
    [string]$Versao = "HEAD",
    [string]$Destino = ""
)
$ErrorActionPreference = 'Stop'
$Raiz = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
Set-Location $Raiz
if (-not (Get-Command git -ErrorAction SilentlyContinue)) { Write-Host "git nao encontrado no PATH."; exit 1 }

$commit = (git rev-parse --verify "$Versao^{commit}" 2>$null) -join ''
if (-not $commit) { Write-Host "Versao '$Versao' nao encontrada (use uma tag como v4.60 ou HEAD)."; exit 1 }
$curto = $commit.Substring(0, 7)
$rotulo = if ($Versao -eq 'HEAD') { $curto } else { ($Versao -replace '[^\w.\-]', '_') }
$agora = Get-Date
$carimbo = $agora.ToString('yyyyMMdd-HHmmss')

if (-not $Destino) { $Destino = Join-Path ([Environment]::GetFolderPath('MyDocuments')) 'JovemConnect_evidencias' }
$pasta = Join-Path $Destino ($rotulo + '_' + $carimbo)
# Nunca dentro do htdocs: o Apache serviria o backup inteiro do repositorio
$htdocs = (Resolve-Path (Join-Path $Raiz '..')).Path
if ($pasta.ToLower().StartsWith($htdocs.ToLower())) {
    Write-Host "Recusado: o destino fica dentro de $htdocs (pasta publica do Apache). Escolha outra pasta."
    exit 1
}
New-Item -ItemType Directory -Force -Path $pasta | Out-Null
Write-Host "Gerando evidencias de $Versao ($curto) em: $pasta"

# 1. Copia exata da versao
$zip = Join-Path $pasta ("codigo_" + $rotulo + ".zip")
git archive --format=zip -o "$zip" $commit
if ($LASTEXITCODE -ne 0) { Write-Host "Falha no git archive."; exit 1 }

# 2. Manifesto (caminho, bytes, SHA-256 do arquivo da versao, blob do Git)
$tmp = Join-Path ([IO.Path]::GetTempPath()) ('jc_evid_' + [guid]::NewGuid().ToString('N'))
Expand-Archive -LiteralPath $zip -DestinationPath $tmp
$blobs = @{}
foreach ($l in (git ls-tree -r $commit)) {
    if ($l -match '^\d+\s+blob\s+([0-9a-f]{40})\t(.+)$') { $blobs[$Matches[2]] = $Matches[1] }
}
$manifesto = Join-Path $pasta ("MANIFESTO_" + $rotulo + ".csv")
$linhas = New-Object System.Collections.Generic.List[string]
$linhas.Add('caminho;bytes;sha256;git_blob')
foreach ($caminho in ($blobs.Keys | Sort-Object)) {
    $arq = Join-Path $tmp ($caminho -replace '/', '\')
    if (Test-Path -LiteralPath $arq) {
        $h = (Get-FileHash -LiteralPath $arq -Algorithm SHA256).Hash.ToLower()
        $b = (Get-Item -LiteralPath $arq).Length
        $linhas.Add(('{0};{1};{2};{3}' -f $caminho, $b, $h, $blobs[$caminho]))
    }
}
[IO.File]::WriteAllLines($manifesto, $linhas)
Remove-Item -LiteralPath $tmp -Recurse -Force -ErrorAction SilentlyContinue

# 3. Backup completo (todas as referencias: branches, tags, backups, refs/original, refs/cline)
$bundle = Join-Path $pasta ("jovemconnect_" + $carimbo + ".bundle")
git bundle create "$bundle" --all 2>&1 | Out-Null
if ($LASTEXITCODE -ne 0) { Write-Host "Falha no git bundle."; exit 1 }
$verif = (git bundle verify "$bundle" 2>&1) -join ' '
$bundleOk = ($LASTEXITCODE -eq 0)

# 4. Hashes
$somas = Join-Path $pasta 'SHA256SUMS.txt'
$s = New-Object System.Collections.Generic.List[string]
foreach ($f in @($zip, $manifesto, $bundle)) {
    $s.Add(((Get-FileHash -LiteralPath $f -Algorithm SHA256).Hash.ToLower() + '  ' + (Split-Path -Leaf $f)))
}
[IO.File]::WriteAllLines($somas, $s)

# 5. Ficha da evidencia
$tagInfo = 'nenhuma tag nesta versao'
$tags = @(git tag --points-at $commit 2>$null)
if ($tags.Count -gt 0) {
    $partes = @()
    foreach ($t in $tags) {
        $tipo = (git cat-file -t "refs/tags/$t" 2>$null) -join ''
        $assin = 'leve (sem assinatura possivel)'
        if ($tipo -eq 'tag') {
            $temAssinatura = [bool](@(git cat-file -p "refs/tags/$t" 2>$null) -match '^-----BEGIN (SSH|PGP) SIGNATURE-----')
            git tag -v $t *> $null
            $assin = if ($LASTEXITCODE -eq 0) { 'ASSINADA e verificada neste PC' }
                     elseif ($temAssinatura) { 'ASSINADA, mas NAO verificada neste PC (falta a chave publica: gpg.ssh.allowedSignersFile ou chave GPG importada)' }
                     else { 'anotada, sem assinatura' }
        }
        $partes += "$t ($assin)"
    }
    $tagInfo = $partes -join '; '
}
$ficha = @(
    'EVIDENCIA DE VERSAO - JovemConnect',
    ('Gerada em : ' + $agora.ToString('dd/MM/yyyy HH:mm:ss zzz') + ' no computador ' + $env:COMPUTERNAME),
    ('Versao    : ' + $Versao),
    ('Commit    : ' + $commit),
    ('Data do commit (declarada no Git): ' + ((git log -1 --format=%cI $commit) -join '')),
    ('Tag(s)    : ' + $tagInfo),
    ('Arquivos  : ' + ($linhas.Count - 1) + ' (MANIFESTO_' + $rotulo + '.csv)'),
    ('Bundle    : ' + $(if ($bundleOk) { 'verificado (git bundle verify ok)' } else { 'FALHOU na verificacao: ' + $verif })),
    ('Git       : ' + ((git --version) -join '')),
    '',
    'Como conferir depois:',
    '  1. Hashes:  Get-FileHash <arquivo> -Algorithm SHA256   (ou: certutil -hashfile <arquivo> SHA256)',
    '              e compare com SHA256SUMS.txt (o SHA256SUMS.txt enviado por e-mail fixa a data).',
    '  2. Bundle:  git bundle verify jovemconnect_*.bundle',
    '              git clone jovemconnect_*.bundle restaurado   (recupera todo o historico)',
    '  3. Versao:  no clone, "git rev-parse <tag>^{commit}" deve dar o commit acima;',
    '              se a tag for assinada: git tag -v <tag>',
    '',
    'Limites: datas e autor do Git sao DECLARADOS por quem faz o commit; a assinatura prova',
    'quem tinha a chave, nao quem escreveu o codigo; a data externa vem do e-mail/armazenamento',
    'de terceiros onde o SHA256SUMS.txt foi guardado.'
)
[IO.File]::WriteAllLines((Join-Path $pasta 'EVIDENCIA.txt'), $ficha)

Write-Host ""
Write-Host "Pronto. Arquivos em: $pasta"
Get-Content -LiteralPath $somas | ForEach-Object { Write-Host ("  " + $_) }
Write-Host "Guarde a pasta em dois lugares independentes e registre o SHA256SUMS.txt fora deste PC."
exit 0
