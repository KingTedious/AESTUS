<?php
/* ============================================================
   uploads/curriculos.php — Handler de upload de currículo

   Recebe o PDF via POST (form do perfil do jovem),
   valida, salva e atualiza o banco.
   Redireciona de volta ao perfil após o resultado (PRG).

   ❌ PROBLEMAS DO ORIGINAL (todos corrigidos aqui):
   1. Validava apenas $_FILES['curriculo']['type'] — valor
      informado pelo browser, facilmente falsificado.
      Um atacante envia um .php renomeado como .pdf e o
      type chega como "application/pdf". O arquivo era
      aceito e poderia ser executado como PHP no servidor.
   2. Não fazia o upload de fato (sem move_uploaded_file).
   3. Sem session_start() → sem como saber quem é o jovem.
   4. Sem limite de tamanho de arquivo.
   5. Sem atualização no banco de dados.
   6. Acessível diretamente via GET sem consequência.
   ============================================================ */
session_start();

// ── Auth ──────────────────────────────────────────────────────────────────────
if (!isset($_SESSION['jovem_id'])) {
    header("Location: ../jovem/login.php");
    exit;
}

// ── Só aceita POST ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../jovem/perfil.php");
    exit;
}

require_once dirname(__DIR__) . "/includes/config.php";

$jovem_id = intval($_SESSION['jovem_id']);

// ── Verifica se o arquivo foi enviado sem erros do PHP ────────────────────────
if (!isset($_FILES['curriculo']) || $_FILES['curriculo']['error'] !== UPLOAD_ERR_OK) {
    $codigo_erro = $_FILES['curriculo']['error'] ?? -1;

    // Mapeia os códigos de erro do PHP para mensagens legíveis
    $erros_upload = [
        UPLOAD_ERR_INI_SIZE   => 'O arquivo excede o limite do servidor (upload_max_filesize).',
        UPLOAD_ERR_FORM_SIZE  => 'O arquivo excede o limite definido no formulário.',
        UPLOAD_ERR_PARTIAL    => 'O upload foi interrompido. Tente novamente.',
        UPLOAD_ERR_NO_FILE    => 'Nenhum arquivo foi selecionado.',
        UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária do servidor não encontrada.',
        UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar o arquivo no servidor.',
        UPLOAD_ERR_EXTENSION  => 'Upload bloqueado por extensão PHP.',
    ];

    $_SESSION['erro_upload'] = $erros_upload[$codigo_erro]
        ?? 'Erro desconhecido no upload (código ' . $codigo_erro . ').';

    header("Location: ../jovem/perfil.php?saved=2");
    exit;
}

$arquivo_tmp  = $_FILES['curriculo']['tmp_name'];
$arquivo_size = $_FILES['curriculo']['size'];

// ── 1. Limite de tamanho: 5 MB ────────────────────────────────────────────────
$limite_bytes = 5 * 1024 * 1024; // 5 MB
if ($arquivo_size > $limite_bytes) {
    $_SESSION['erro_upload'] = 'O arquivo excede o limite de 5 MB.';
    header("Location: ../jovem/perfil.php?saved=2");
    exit;
}

// ── 2. Validação real por magic bytes (não confia no $_FILES['type']) ─────────
//    Os primeiros 4 bytes de todo PDF válido são: %PDF  (0x25 0x50 0x44 0x46)
$handle = fopen($arquivo_tmp, 'rb');
$magic  = fread($handle, 4);
fclose($handle);

if ($magic !== '%PDF') {
    $_SESSION['erro_upload'] = 'O arquivo enviado não é um PDF válido.';
    header("Location: ../jovem/perfil.php?saved=2");
    exit;
}

// ── 3. Gera nome seguro e determinístico ──────────────────────────────────────
//    curriculo_{id}.pdf — um arquivo por jovem, sobrescreve o anterior.
//    Não usa o nome original do arquivo (evita path traversal).
$nome_arquivo = "curriculo_{$jovem_id}.pdf";

// ── 4. Cria o diretório de destino se não existir ─────────────────────────────
$dir_destino = dirname(__DIR__) . "/uploads/curriculos/";
if (!is_dir($dir_destino)) {
    mkdir($dir_destino, 0755, true);
}
$caminho_destino = $dir_destino . $nome_arquivo;

// ── 5. Move o arquivo da pasta temporária para o destino ──────────────────────
if (!move_uploaded_file($arquivo_tmp, $caminho_destino)) {
    $_SESSION['erro_upload'] = 'Falha ao salvar o arquivo no servidor. Tente novamente.';
    header("Location: ../jovem/perfil.php?saved=2");
    exit;
}

// ── 6. Atualiza o caminho do currículo no banco de dados ──────────────────────
$stmt = $conn->prepare(
    "UPDATE jovens SET curriculo = ? WHERE id = ?"
);
$stmt->bind_param("si", $nome_arquivo, $jovem_id);

if (!$stmt->execute()) {
    // Upload físico ocorreu mas o banco falhou — remove o arquivo para manter consistência
    unlink($caminho_destino);
    $stmt->close();
    $_SESSION['erro_upload'] = 'Erro ao registrar o currículo no sistema. Tente novamente.';
    header("Location: ../jovem/perfil.php?saved=2");
    exit;
}

$stmt->close();

// ── 7. Sucesso — redireciona com feedback ─────────────────────────────────────
$_SESSION['sucesso_upload'] = 'Currículo atualizado com sucesso!';
header("Location: ../jovem/perfil.php?saved=1");
exit;