<?php
session_start();

// Proteção: Garante que só empresa logada acessa
if (!isset($_SESSION['empresa_id'])) {
    header("Location: login.php");
    exit;
}

// Caminho absoluto para encontrar o config.php na pasta includes
require_once dirname(__DIR__) . "/includes/config.php";

$empresa_id = $_SESSION['empresa_id'];
$mensagem = "";
$tipo_mensagem = "";

// 1. SE O FORMULÁRIO FOR ENVIADO, ATUALIZA OS DADOS NO BANCO
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome_empresa = trim($_POST['nome_empresa']);
    $email = trim($_POST['email']);
    $cnpj = trim($_POST['cnpj']);
    $cidade = trim($_POST['cidade']);
    $estado = trim($_POST['estado']);
    $telefone = trim($_POST['telefone']);

    if (empty($nome_empresa) || empty($email) || empty($cnpj)) {
        $mensagem = "Por favor, preencha os campos obrigatórios (Nome, E-mail e CNPJ).";
        $tipo_mensagem = "erro";
    } else {
        $query_update = "UPDATE empresas SET nome_empresa = ?, email = ?, cnpj = ?, cidade = ?, estado = ?, telefone = ? WHERE id = ?";
        $stmt_update = $conn->prepare($query_update);
        $stmt_update->bind_param("ssssssi", $nome_empresa, $email, $cnpj, $cidade, $estado, $telefone, $empresa_id);

        if ($stmt_update->execute()) {
            $mensagem = "Perfil atualizado com sucesso!";
            $tipo_mensagem = "sucesso";
            $_SESSION['empresa_nome'] = $nome_empresa;
        } else {
            $mensagem = "Erro ao atualizar o perfil. Tente novamente.";
            $tipo_mensagem = "erro";
        }
    }
}

// 2. BUSCA OS DADOS ATUAIS DA EMPRESA
$query_busca = "SELECT nome_empresa, email, cnpj, cidade, estado, telefone FROM empresas WHERE id = ?";
$stmt_busca = $conn->prepare($query_busca);
$stmt_busca->bind_param("i", $empresa_id);
$stmt_busca->execute();
$dados_empresa = $stmt_busca->get_result()->fetch_assoc();

if (!$dados_empresa) {
    echo "Erro ao carregar dados da empresa.";
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Editar Perfil | JovemConnect</title>
    <link rel="stylesheet" href="../Assets/css/style.css">
</head>
<body class="dark-theme">

    <div class="container-perfil">
        <a href="../dashboard/empresa.php" class="btn-voltar">← Voltar para Dashboard</a>
        
        <h2>🏢 Editar Perfil da Empresa</h2>
        <p class="subtitulo">Mantenha os dados da sua instituição atualizados para os candidatos.</p>

        <?php if (!empty($mensagem)): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?>">
                <?php echo $mensagem; ?>
            </div>
        <?php endif; ?>

        <form action="perfil.php" method="POST">
            
            <div class="form-group">
                <label for="nome_empresa">Nome da Empresa *</label>
                <input type="text" id="nome_empresa" name="nome_empresa" value="<?php echo htmlspecialchars($dados_empresa['nome_empresa']); ?>" required>
            </div>

            <div class="form-group">
                <label for="email">E-mail Corporativo *</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($dados_empresa['email']); ?>" required>
            </div>

            <div class="row-dupla">
                <div class="form-group">
                    <label for="cnpj">CNPJ *</label>
                    <input type="text" id="cnpj" name="cnpj" value="<?php echo htmlspecialchars($dados_empresa['cnpj']); ?>" required placeholder="00.000.000/0001-00">
                </div>
                <div class="form-group">
                    <label for="telefone">Telefone / WhatsApp</label>
                    <input type="text" id="telefone" name="telefone" value="<?php echo htmlspecialchars($dados_empresa['telefone'] ?? ''); ?>" placeholder="(11) 99999-9999">
                </div>
            </div>

            <div class="row-dupla">
                <div class="form-group">
                    <label for="cidade">Cidade</label>
                    <input type="text" id="cidade" name="cidade" value="<?php echo htmlspecialchars($dados_empresa['cidade'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="estado">Estado (UF)</label>
                    <input type="text" id="estado" name="estado" value="<?php echo htmlspecialchars($dados_empresa['estado'] ?? ''); ?>" maxlength="2" placeholder="Ex: SP">
                </div>
            </div>

            <button type="submit" class="btn-salvar">Salvar Alterações</button>

        </form>
    </div>

</body>
</html>