<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// 1. Sempre inicie a sessão e a lógica de processamento ANTES de qualquer HTML
session_start();
include("../includes/config.php");

$erro = ""; // Variável para armazenar mensagens de erro

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $senha = $_POST['senha'];

    $stmt = $conn->prepare("SELECT * FROM jovens WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $jovem = $result->fetch_assoc();
        
        if (password_verify($senha, $jovem['senha'])) {
            // Login com sucesso
            $_SESSION['jovem_id'] = $jovem['id'];
            $_SESSION['jovem_nome'] = $jovem['nome'];
            $_SESSION['primeiro_acesso'] = ($jovem['ultimo_login'] == NULL);

            // Atualiza o último login
            $id_usuario = $jovem['id'];
            $conn->query("UPDATE jovens SET ultimo_login = NOW() WHERE id = '$id_usuario'");

            header("Location: ../dashboard/jovem.php");
            exit;
        } else {
            // Senha errada, mas usamos a mensagem genérica
            $erro = "E-mail ou senha incorretos.";
        }
    } else {
        // E-mail não existe, mas usamos a mesma mensagem genérica
        $erro = "E-mail ou senha incorretos.";
    }

    }
    if (password_verify($senha, $jovem['senha'])) {
        $_SESSION['jovem_id'] = $jovem['id'];
        $_SESSION['jovem_nome'] = $jovem['nome'];
    
        // GUARDA O ESTADO ANTERIOR: Se for NULL, é o primeiro login
        $_SESSION['primeiro_acesso'] = ($jovem['ultimo_login'] == NULL);

        // ATUALIZA O BANCO: Define que ele logou agora
        $id_usuario = $jovem['id'];
        $conn->query("UPDATE jovens SET ultimo_login = NOW() WHERE id = '$id_usuario'");

    header("Location: ../dashboard/jovem.php");
    exit;
    }
}

// 2. Agora sim, começamos a parte visual (HTML)
include("../includes/header.php");
?>

<link rel="stylesheet" href="../assets/css/style.css">

<div class="container" style="display: flex; justify-content: center; align-items: center; min-height: 80vh;">
    <div class="card" style="width: 100%; max-width: 400px; text-align: center;">
        
        <div style="font-size: 2.5rem; margin-bottom: 10px;">🎓</div>
        <h2>Acesse sua conta</h2>
        <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 20px;">
            Área do Candidato
        </p>

        <?php if($erro): ?>
            <p style="color: #ff4d4d; background: rgba(255, 77, 77, 0.1); padding: 10px; border-radius: 5px; font-size: 0.8rem;">
                ⚠️ <?php echo $erro; ?>
            </p>
        <?php endif; ?>

        <form method="POST">
            <div style="text-align: left;">
                <label style="font-size: 0.8rem; color: var(--text-secondary);">E-mail</label>
                <input type="email" name="email" placeholder="seu@email.com" required>
                
                <label style="font-size: 0.8rem; color: var(--text-secondary);">Senha</label>
                <input type="password" name="senha" placeholder="••••••••" required>
            </div>
            
            <button type="submit" class="btn" style="width: 100%; margin-top: 10px;">Entrar</button>
        </form>
        
        <p style="margin-top: 25px; font-size: 0.9rem; color: var(--text-secondary);">
            Não tem uma conta? <a href="cadastro.php" style="color: var(--primary);">Cadastre-se agora</a>
        </p>
    </div>
</div>

<?php include("../includes/footer.php"); ?>