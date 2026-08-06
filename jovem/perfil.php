<?php
    /* 1. CONFIGURAÇÕES E PROTEÇÃO */
    include("../includes/config.php");
    session_start();

    if(!isset($_SESSION['jovem_id'])){
        header("Location: login.php");
        exit;
    }

    $jovem_id = $_SESSION['jovem_id'];
    $mensagem = "";

    /* 2. PROCESSAMENTO DE ATUALIZAÇÃO (POST) */
    if($_SERVER['REQUEST_METHOD'] == 'POST'){
        
        // Dados de texto
        $nome = $conn->real_escape_string($_POST['nome']);
        $email = $conn->real_escape_string($_POST['email']);
        $formacao = $conn->real_escape_string($_POST['formacao']); // Ajustado para 'formacao' conforme PDF
        $cidade = $conn->real_escape_string($_POST['cidade']);
        $estado = $conn->real_escape_string($_POST['estado']);

        // Lógica de Upload de Currículo
        $nome_curriculo = ""; 
        if(isset($_FILES['curriculo']) && $_FILES['curriculo']['error'] == 0){
            $extensao = pathinfo($_FILES['curriculo']['name'], PATHINFO_EXTENSION);
            
            if($extensao == "pdf"){
                $novo_nome = "curriculo_" . $jovem_id . ".pdf";
                $destino = "../uploads/curriculos/" . $novo_nome;
                
                if(move_uploaded_file($_FILES['curriculo']['tmp_name'], $destino)){
                    $nome_curriculo = ", curriculo='$novo_nome'";
                }
            } else {
                $mensagem = "<p style='color:red;'>Apenas arquivos PDF são permitidos.</p>";
            }
        }

        // SQL Dinâmico para atualizar dados e currículo (se enviado)
        $sql_update = "UPDATE jovens SET 
                    nome='$nome', 
                    email='$email', 
                    formacao='$formacao', 
                    cidade='$cidade', 
                    estado='$estado' 
                    $nome_curriculo 
                    WHERE id='$jovem_id'";

        if($conn->query($sql_update)){
            $mensagem = "<p style='color:var(--principal);'>Perfil atualizado com sucesso!</p>";
        }
    }

    /* 3. BUSCA DADOS ATUAIS PARA PREENCHER O FORMULÁRIO */
    $sql = "SELECT * FROM jovens WHERE id = '$jovem_id'";
    $result = $conn->query($sql);
    $jovem = $result->fetch_assoc();

    include("../includes/header.php"); 
?>

<link rel="stylesheet" href="../assets/css/style.css">

<div class="container">
    <div class="card">
        <h1>Meu Perfil</h1>
        <p>Mantenha seus dados atualizados para melhores recomendações da IA.</p>
        <hr>

        <?php echo $mensagem; ?>

        <form method="POST" enctype="multipart/form-data">
            
            <label>Nome Completo</label>
            <input type="text" name="nome" value="<?php echo $jovem['nome']; ?>" required>

            <label>Email</label>
            <input type="email" name="email" value="<?php echo $jovem['email']; ?>" required>

            <div class="info-group">
                <label style="color: var(--text-secondary); font-size: 0.8rem;">ESCOLARIDADE</label>
                <p style="margin: 5px 0;">
                    <strong>Ensino Médio <?php echo ucfirst($jovem['escolaridade_status']); ?></strong>
                </p>
                <p style="font-size: 0.9rem; color: var(--primary);">
                    <?php 
                        if($jovem['escolaridade_tipo'] === 'tecnico') {
                            echo "Técnico em " . htmlspecialchars($jovem['escolaridade_curso']);
                        } else {
                            echo "Ensino Regular";
                        }
                    ?>
                </p>
            </div>

            <div style="display: flex; gap: 10px;">
                <div style="flex: 2;">
                    <label>Cidade</label>
                    <input type="text" name="cidade" value="<?php echo $jovem['cidade']; ?>" required>
                </div>
                <div style="flex: 1;">
                    <label>Estado</label>
                    <input type="text" name="estado" value="<?php echo $jovem['estado']; ?>" maxlength="2" required>
                </div>
            </div>

            <div class="card" style="background: #0F172A; margin-top: 15px;">
                <label>Atualizar Currículo (Apenas PDF)</label> colocar icone de documento
                <input type="file" name="curriculo" accept=".pdf">
                <?php if(!empty($jovem['curriculo'])): ?>
                    <p><small>Arquivo atual: <a href="../uploads/curriculos/<?php echo $jovem['curriculo']; ?>" target="_blank" style="color:var(--principal);">Ver currículo salvo</a></small></p>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn" style="width: 100%; margin-top: 10px;">Salvar Alterações</button>
        </form>
        
        <br>
        <a href="../dashboard/jovem.php" style="color: #64748b;">← Voltar ao Dashboard</a>
    </div>
</div>

<?php include("../includes/footer.php"); ?>