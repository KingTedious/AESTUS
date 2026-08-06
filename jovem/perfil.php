<?php
    /* 1. CONFIGURAÇÕES E PROTEÇÃO */
    require_once dirname(__DIR__) . "/includes/config.php";
    session_start();

    // Bloqueia se não houver NEM jovem logado e NEM empresa logada
    if(!isset($_SESSION['jovem_id']) && !isset($_SESSION['empresa_id'])){
        header("Location: ../index.php");
        exit;
    }

    // Define de quem é o perfil que estamos olhando
    if (isset($_GET['id'])) {
        $jovem_id = intval($_GET['id']);
        $eh_dono_do_perfil = (isset($_SESSION['jovem_id']) && $_SESSION['jovem_id'] == $jovem_id);
    } else if (isset($_SESSION['jovem_id'])) {
        $jovem_id = $_SESSION['jovem_id'];
        $eh_dono_do_perfil = true;
    } else {
        header("Location: ../dashboard/empresa.php");
        exit;
    }

    $mensagem = "";

    /* 2. PROCESSAMENTO DE ATUALIZAÇÃO (APENAS SE FOR O DONO DO PERFIL) */
    if($_SERVER['REQUEST_METHOD'] == 'POST' && $eh_dono_do_perfil){
        
        $nome = trim($_POST['nome']);
        $email = trim($_POST['email']);
        $cidade = trim($_POST['cidade']);
        $estado = trim($_POST['estado']);
        $formacao = trim($_POST['nome']); // Mantendo o mapeamento que você usou

        // Lógica de Upload de Currículo
        $nome_curriculo = null; 
        $upload_ok = true;

        if(isset($_FILES['curriculo']) && $_FILES['curriculo']['error'] == 0){
            $extensao = strtolower(pathinfo($_FILES['curriculo']['name'], PATHINFO_EXTENSION));
            
            if($extensao == "pdf"){
                $novo_name = "curriculo_" . $jovem_id . ".pdf";
                $destino = "../uploads/curriculos/" . $novo_name;
                
                // Cria a pasta caso ela não exista no XAMPP
                if (!is_dir("../uploads/curriculos/")) {
                    mkdir("../uploads/curriculos/", 0777, true);
                }

                if(move_uploaded_file($_FILES['curriculo']['tmp_name'], $destino)){
                    $nome_curriculo = $novo_name;
                }
            } else {
                $mensagem = "<p class='alert error'>Apenas arquivos PDF são permitidos.</p>";
                $upload_ok = false;
            }
        }

        if ($upload_ok) {
            // Update Seguro com Prepared Statements
            if ($nome_curriculo) {
                $sql_update = "UPDATE jovens SET nome=?, email=?, cidade=?, estado=? , curriculo=? WHERE id=?";
                $stmt = $conn->prepare($sql_update);
                $stmt->bind_param("sssssi", $nome, $email, $cidade, $estado, $nome_curriculo, $jovem_id);
            } else {
                $sql_update = "UPDATE jovens SET nome=?, email=?, cidade=?, estado=? WHERE id=?";
                $stmt = $conn->prepare($sql_update);
                $stmt->bind_param("ssssi", $nome, $email, $cidade, $estado, $jovem_id);
            }

            if($stmt->execute()){
                $mensagem = "<p class='alert success'>Perfil atualizado com sucesso!</p>";
            } else {
                $mensagem = "<p class='alert error'>Erro ao atualizar perfil no banco de dados.</p>";
            }
        }
    }

    /* 3. BUSCA DADOS ATUAIS */
    $sql = "SELECT * FROM jovens WHERE id = ?";
    $stmt_busca = $conn->prepare($sql);
    $stmt_busca->bind_param("i", $jovem_id);
    $stmt_busca->execute();
    $jovem = $stmt_busca->get_result()->fetch_assoc();

    if (!$jovem) {
        echo "Usuário não encontrado.";
        exit;
    }

    include("../includes/header.php"); 
?>

<link rel="stylesheet" href="../assets/css/style.css">

<div class="container">
    <div class="card">
        
        <?php if ($eh_dono_do_perfil): ?>
            <h1>Meu Perfil</h1>
            <p class="subtitulo">Mantenha seus dados atualizados para melhores recomendações da IA.</p>
        <?php else: ?>
            <h1>Perfil do Candidato</h1>
            <p class="subtitulo">Análise as informações e competências acadêmicas do jovem.</p>
        <?php endif; ?>
        
        <hr>

        <?php echo $mensagem; ?>

        <?php if ($eh_dono_do_perfil): ?>
            <form method="POST" enctype="multipart/form-data">
                
                <label for="nome">Nome Completo</label>
                <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($jovem['nome']); ?>" required>

                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($jovem['email']); ?>" required>

                <div class="info-group">
                    <span class="label-badge">ESCOLARIDADE</span>
                    <p class="status-escolar">
                        <strong>Ensino Médio <?php echo ucfirst($jovem['escolaridade_status']); ?></strong>
                    </p>
                    <p class="curso-escolar">
                        <?php 
                            if($jovem['escolaridade_tipo'] === 'tecnico') {
                                echo "Técnico em " . htmlspecialchars($jovem['escolaridade_curso']);
                            } else {
                                echo "Ensino Regular";
                            }
                        ?>
                    </p>
                </div>

                <div class="form-row">
                    <div class="col-cidade">
                        <label Land="cidade">Cidade</label>
                        <input type="text" id="cidade" name="cidade" value="<?php echo htmlspecialchars($jovem['cidade']); ?>" required>
                    </div>
                    <div class="col-estado">
                        <label for="estado">Estado</label>
                        <input type="text" id="estado" name="estado" value="<?php echo htmlspecialchars($jovem['estado']); ?>" maxlength="2" required>
                    </div>
                </div>

                <div class="card card-upload">
                    <label>📄 Atualizar Currículo (Apenas PDF)</label>
                    <input type="file" name="curriculo" accept=".pdf">
                    <?php if(!empty($jovem['curriculo'])): ?>
                        <p class="link-doc-atual"><small>Arquivo atual: <a href="../uploads/curriculos/<?php echo $jovem['curriculo']; ?>" target="_blank">Ver currículo salvo</a></small></p>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-block">Salvar Alterações</button>
            </form>
            
            <br>
            <a href="../dashboard/jovem.php" class="link-back">← Voltar ao Dashboard</a>

        <?php else: ?>
            <div class="perfil-view">
                <div class="info-bloco">
                    <h3>👤 Nome Completo</h3>
                    <p class="dado-texto"><?php echo htmlspecialchars($jovem['nome']); ?></p>
                </div>

                <div class="info-bloco">
                    <h3>✉️ Contato</h3>
                    <p class="dado-texto"><?php echo htmlspecialchars($jovem['email']); ?></p>
                    <p class="dado-texto"><?php echo htmlspecialchars($jovem['telefone'] ?? 'Telefone não cadastrado'); ?></p>
                </div>

                <div class="info-bloco">
                    <h3>📍 Localização</h3>
                    <p class="dado-texto"><?php echo htmlspecialchars($jovem['cidade']) . " - " . htmlspecialchars($jovem['estado']); ?></p>
                </div>

                <div class="info-bloco card-escolaridade-view">
                    <h3>🎓 Formação e Escolaridade</h3>
                    <p class="dado-texto"><strong>Status:</strong> Ensino Médio <?php echo ucfirst($jovem['escolaridade_status']); ?></p>
                    <p class="dado-texto"><strong>Tipo:</strong> <?php echo $jovem['escolaridade_tipo'] === 'tecnico' ? "Ensino Técnico" : "Ensino Regular"; ?></p>
                    <?php if($jovem['escolaridade_tipo'] === 'tecnico'): ?>
                        <p class="dado-texto"><strong>Habilitação:</strong> Técnico em <?php echo htmlspecialchars($jovem['escolaridade_curso']); ?></p>
                    <?php endif; ?>
                </div>

                <div class="info-bloco">
                    <h3>📄 Currículo Profissional</h3>
                    <?php if(!empty($jovem['curriculo'])): ?>
                        <br>
                        <a href="../uploads/curriculos/<?php echo $jovem['curriculo']; ?>" target="_blank" class="btn btn-download">📄 Visualizar Currículo PDF</a>
                    <?php else: ?>
                        <p class="dado-texto aviso-vazio">Este candidato ainda não realizou o upload de um currículo em PDF.</p>
                    <?php endif; ?>
                </div>
            </div>

            <br>
            <a href="../dashboard/empresa.php" class="link-back">← Voltar para Painel da Empresa</a>
        <?php endif; ?>

    </div>
</div>

<?php include("../includes/footer.php"); ?>