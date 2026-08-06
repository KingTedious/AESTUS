<?php 
/* 1. CONFIGURAÇÕES INICIAIS */
include("../includes/config.php"); 
session_start();

$erro = ""; // Variável para armazenar mensagens de erro

/* 2. PROCESSAMENTO DO LOGIN */
if($_SERVER["REQUEST_METHOD"] == "POST"){

    // Proteção básica contra SQL Injection
    $email = $conn->real_escape_string($_POST['email']);
    $senha = $_POST['senha'];

    // Procura a empresa pelo email (tabela identificada no PDF)
    $sql = "SELECT id, nome_empresa, senha FROM empresas WHERE email='$email'";
    $result = $conn->query($sql);

    if($result->num_rows > 0){
        $empresa = $result->fetch_assoc();

        /* 3. VERIFICAÇÃO DE SENHA 
           Compara a senha digitada com o hash seguro do banco. */
        if(password_verify($senha, $empresa['senha'])){
            
            // Define as variáveis de sessão para uso no Dashboard
            $_SESSION['empresa_id'] = $empresa['id'];
            $_SESSION['empresa_nome'] = $empresa['nome_empresa'];

            // Redireciona para o painel principal
            header("Location: ../dashboard/empresa.php");
            exit;

        } else {
            $erro = "Senha incorreta. Tente novamente.";
        }
    } else {
        $erro = "Empresa não encontrada com este email.";
    }
}

/* 4. CABEÇALHO VISUAL */
include("../includes/header.php"); 
?>

<link rel="stylesheet" href="../assets/css/style.css">

<div class="container">
    <div class="auth">
        <h2>Login Empresa</h2>
        <p>Aceda ao painel do Jovem Connect.</p>
        <hr>

        <?php 
        // Exibe mensagem de erro caso exista
        if($erro != "") {
            echo "<p style='color: #ff4d4d; margin-bottom: 15px;'>$erro</p>";
        }
        ?>

        <form method="POST">
            <label>Email:</label>
            <input type="email" name="email" placeholder="exemplo@empresa.com" required>

            <label>Senha:</label>
            <input type="password" name="senha" placeholder="Sua senha" required>

            <button type="submit" class="btn">Entrar no Sistema</button>
        </form>

        <p style="margin-top: 15px; text-align: center;">
            Ainda não tem conta? <a href="cadastro.php">Cadastre sua empresa</a>
        </p>
    </div>
</div>

<?php include("../includes/footer.php"); ?>