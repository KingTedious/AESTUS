<?php 
/* 1. INCLUSÕES E CONFIGURAÇÃO */
include("../includes/header.php"); 
include("../includes/config.php"); 

$mensagem = ""; // Variável para armazenar alertas

/* 2. PROCESSAMENTO DO FORMULÁRIO (MÉTODO POST) */
if($_SERVER["REQUEST_METHOD"] == "POST"){

    // Limpeza básica para evitar caracteres maliciosos
    $nome = $conn->real_escape_string($_POST['nome_empresa']);
    $email = $conn->real_escape_string($_POST['email']);
    $cnpj = $conn->real_escape_string($_POST['cnpj']);
    $cidade = $conn->real_escape_string($_POST['cidade']);
    $estado = $conn->real_escape_string($_POST['estado']);
    $telefone = $conn->real_escape_string($_POST['telefone']);
    
    // Criptografia da senha
    $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);

    /* 3. VERIFICAÇÃO DE DUPLICIDADE 
       Garante que não existam duas empresas com o mesmo CNPJ ou Email. */
    $verifica = "SELECT id FROM empresas WHERE email='$email' OR cnpj='$cnpj'";
    $result = $conn->query($verifica);

    if($result->num_rows > 0){
        $mensagem = "<p style='color: #ff4d4d;'>Erro: Email ou CNPJ já cadastrado.</p>";
    } else {
        /* 4. INSERÇÃO NO BANCO DE DADOS 
           Os campos seguem a ordem da tabela identificada no PDF.  */
        $sql = "INSERT INTO empresas (nome_empresa, email, senha, cnpj, cidade, estado, telefone) 
                VALUES ('$nome', '$email', '$senha', '$cnpj', '$cidade', '$estado', '$telefone')";

        if($conn->query($sql) === TRUE){
            $mensagem = "<p style='color: var(--principal);'>Empresa cadastrada com sucesso! <a href='login.php'>Fazer Login</a></p>";
        } else {
            $mensagem = "<p style='color: #ff4d4d;'>Erro ao cadastrar: " . $conn->error . "</p>";
        }
    }
}
?>

<link rel="stylesheet" href="../assets/css/style.css">

<div class="container">
    <div class="auth">
        <h2>Cadastro de Empresa</h2>
        <p>Crie uma conta para publicar vagas e encontrar jovens talentos.</p>
        <hr>

        <?php echo $mensagem; // Exibe feedback ao usuário ?>

        <form method="POST">
            <label>Nome da empresa:</label>
            <input type="text" name="nome_empresa" placeholder="Ex: Tech Soluções" required>

            <label>Email Corporativo:</label>
            <input type="email" name="email" placeholder="empresa@contato.com" required>

            <label>Senha:</label>
            <input type="password" name="senha" placeholder="Crie uma senha forte" required>

            <label>CNPJ:</label>
            <input type="text" name="cnpj" placeholder="00.000.000/0001-00" required>

            <div style="display: flex; gap: 10px;">
                <div style="flex: 2;">
                    <label>Cidade:</label>
                    <input type="text" name="cidade" placeholder="Ex: São Paulo" required>
                </div>
                <div style="flex: 1;">
                    <label>Estado (UF):</label>
                    <input type="text" name="estado" maxlength="2" placeholder="SP" required>
                </div>
            </div>

            <label>Telefone:</label>
            <input type="text" name="telefone" placeholder="(11) 99999-9999">

            <button type="submit" class="btn">Cadastrar Minha Empresa</button>
        </form>

        <p style="margin-top: 15px; text-align: center;">
            Já tem conta? <a href="login.php">Entrar agora</a>
        </p>
    </div>
</div>

<?php include("../includes/footer.php"); ?>