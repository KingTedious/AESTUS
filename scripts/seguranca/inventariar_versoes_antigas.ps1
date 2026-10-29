<#
  scripts\seguranca\inventariar_versoes_antigas.ps1 - inventario das versoes anteriores ao Git (JovemConnect)

  As versoes de antes de 06/08/2026 estao em pastas locais, com as datas originais. Este roteiro
  SO LE essas pastas: nao abre os arquivos para edicao, nao move, nao renomeia, nao apaga e nao
  muda datas. As datas de cada arquivo sao lidas ANTES do calculo do hash.

  Grava, FORA da pasta inventariada e FORA do htdocs (padrao: Documentos\JovemConnect_evidencias\
  versoes_antigas_<data>):
    - INVENTARIO.csv ..... caminho relativo, bytes, criacao, ultima modificacao (UTC) e SHA-256
    - RESUMO.txt ......... pasta, quantidade de arquivos, total de bytes, datas mais antiga e mais nova
    - SHA256SUMS.txt ..... hash do INVENTARIO.csv e do RESUMO.txt

  Depois: mande o SHA256SUMS.txt para o seu e-mail (data externa, sem revelar o conteudo).
  Roteiro: docs\PROCEDIMENTO_EVIDENCIAS.md

  Uso: scripts\seguranca\inventariar_versoes_antigas.bat [-Pasta "C:\Users\...\Documents\Versoes"] [-Destino D:\evidencias]
#>
param(
    [string]$Pasta = "",
    [string]$Destino = ""
)
$ErrorActionPreference = 'Stop'
$Raiz = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$htdocs = (Resolve-Path (Join-Path $Raiz '..')).Path

if (-not $Pasta) {
    try {
        Add-Type -AssemblyName System.Windows.Forms
        $dlg = New-Object System.Windows.Forms.FolderBrowserDialog
        $dlg.Description = 'Escolha a pasta que guarda as versoes antigas do JovemConnect (so leitura)'
        $dlg.SelectedPath = [Environment]::GetFolderPath('MyDocuments')
        $dlg.ShowNewFolderButton = $false
        if ($dlg.ShowDialog() -eq [System.Windows.Forms.DialogResult]::OK) { $Pasta = $dlg.SelectedPath }
    } catch { }
}
if (-not $Pasta) { $Pasta = Read-Host 'Caminho da pasta das versoes antigas' }
if (-not $Pasta -or -not (Test-Path -LiteralPath $Pasta -PathType Container)) {
    Write-Host "Pasta nao encontrada: '$Pasta'"; exit 1
}
$Pasta = (Resolve-Path -LiteralPath $Pasta).Path.TrimEnd('\')

if (-not $Destino) { $Destino = Join-Path ([Environment]::GetFolderPath('MyDocuments')) 'JovemConnect_evidencias' }
$saida = Join-Path $Destino ('versoes_antigas_' + (Get-Date).ToString('yyyyMMdd-HHmmss'))
$saidaFull = [System.IO.Path]::GetFullPath($saida).TrimEnd('\')
foreach ($proibida in @($Pasta, $htdocs)) {
    $p = $proibida.TrimEnd('\').ToLower()
    if ($saidaFull.ToLower() -eq $p -or $saidaFull.ToLower().StartsWith($p + '\')) {
        Write-Host "Recusado: o destino ($saidaFull) fica dentro de $proibida. Escolha outro destino com -Destino."
        exit 1
    }
}
if ($Pasta.ToLower().StartsWith($htdocs.TrimEnd('\').ToLower() + '\')) {
    Write-Host "AVISO: a pasta inventariada fica dentro do htdocs ($htdocs): o Apache pode entregar o conteudo dela."
    Write-Host "       Depois do inventario, considere mover a pasta INTEIRA para fora do htdocs (mover no mesmo disco preserva as datas)."
}

Write-Host "Inventariando (so leitura): $Pasta"
$arquivos = @(Get-ChildItem -LiteralPath $Pasta -Recurse -Force -File -ErrorAction SilentlyContinue)
if ($arquivos.Count -eq 0) { Write-Host 'Nenhum arquivo encontrado.'; exit 1 }
New-Item -ItemType Directory -Force -Path $saida | Out-Null

$linhas = New-Object System.Collections.Generic.List[object]
$erros = 0; $total = 0L; $i = 0
foreach ($f in $arquivos) {
    $i++
    if ($i % 200 -eq 0) { Write-Host "  $i de $($arquivos.Count)..." }
    # Datas lidas antes do hash
    $criacao = $f.CreationTimeUtc.ToString('yyyy-MM-dd HH:mm:ss')
    $modific = $f.LastWriteTimeUtc.ToString('yyyy-MM-dd HH:mm:ss')
    $rel = $f.FullName.Substring($Pasta.Length).TrimStart('\')
    $hash = ''
    try { $hash = (Get-FileHash -LiteralPath $f.FullName -Algorithm SHA256).Hash.ToLower() }
    catch { $hash = 'ERRO_LEITURA'; $erros++ }
    $total += $f.Length
    $linhas.Add([pscustomobject]@{
        caminho = $rel; bytes = $f.Length; criacao_utc = $criacao; modificacao_utc = $modific; sha256 = $hash
    })
}
$csv = Join-Path $saida 'INVENTARIO.csv'
$linhas | Sort-Object caminho | Export-Csv -LiteralPath $csv -NoTypeInformation -Encoding UTF8

# Sort-Object (Measure-Object nao compara datas no Windows PowerShell 5.1)
$porModificacao = @($arquivos | Sort-Object LastWriteTimeUtc)
$maisAntiga = $porModificacao[0].LastWriteTimeUtc
$maisNova = $porModificacao[-1].LastWriteTimeUtc
$criacaoAntiga = (@($arquivos | Sort-Object CreationTimeUtc))[0].CreationTimeUtc
$resumo = Join-Path $saida 'RESUMO.txt'
@(
    'Inventario das versoes anteriores ao Git - JovemConnect',
    ('Gerado em (UTC): ' + (Get-Date).ToUniversalTime().ToString('yyyy-MM-dd HH:mm:ss')),
    ('Computador: ' + $env:COMPUTERNAME),
    ('Pasta inventariada: ' + $Pasta),
    ('Arquivos: ' + $arquivos.Count + '   Bytes: ' + $total + '   Erros de leitura: ' + $erros),
    ('Modificacao mais antiga (UTC): ' + $maisAntiga.ToString('yyyy-MM-dd HH:mm:ss')),
    ('Modificacao mais recente (UTC): ' + $maisNova.ToString('yyyy-MM-dd HH:mm:ss')),
    ('Criacao mais antiga (UTC): ' + $criacaoAntiga.ToString('yyyy-MM-dd HH:mm:ss')),
    '',
    'As datas sao do sistema de arquivos e podem ser alteradas por quem tem o computador.',
    'O SHA-256 de cada arquivo permite mostrar depois que o conteudo nao mudou.',
    'Conferir: Get-FileHash INVENTARIO.csv -Algorithm SHA256 (deve bater com o SHA256SUMS.txt).'
) | Set-Content -LiteralPath $resumo -Encoding UTF8

$sums = Join-Path $saida 'SHA256SUMS.txt'
@($csv, $resumo) | ForEach-Object {
    (Get-FileHash -LiteralPath $_ -Algorithm SHA256).Hash.ToLower() + '  ' + (Split-Path $_ -Leaf)
} | Set-Content -LiteralPath $sums -Encoding ASCII

Write-Host ''
Write-Host "Pronto: $($arquivos.Count) arquivo(s), $erros erro(s) de leitura."
Write-Host "Resultado em: $saida"
Write-Host 'Proximo passo: mande o SHA256SUMS.txt (so ele) para o seu e-mail. Nao edite nem mova as pastas antigas.'
if ($erros -gt 0) { exit 2 }
exit 0
