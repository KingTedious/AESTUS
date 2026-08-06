<?php 
/* 1. INCLUSÕES */
include("../includes/header.php"); 
include("../includes/config.php"); 

$mensagem = "";

/* 2. PROCESSAMENTO DO FORMULÁRIO */
if($_SERVER["REQUEST_METHOD"] == "POST"){

    // Recolha de dados do formulário
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
    $cpf = $_POST['cpf'];
    $data_nasc = $_POST['data_nascimento'];
    $cidade = $_POST['cidade'];
    $estado = $_POST['estado'];
    $formacao = $_POST['formacao'];
    $telefone = $_POST['telefone'];
    
    // Dentro do seu arquivo de processamento PHP:
    $escolaridade_status = $_POST['escolaridade_status'];
    $escolaridade_tipo = $_POST['escolaridade_tipo'];
    $escolaridade_curso = NULL;

    if ($escolaridade_tipo === 'tecnico') {
    // Se escolheu 'Outro', pega o texto digitado; senão, pega o valor do select predefinido
    $escolaridade_curso = ($_POST['curso_predefinido'] === 'Outro') ? $_POST['curso_outro'] : $_POST['curso_predefinido'];
    }

    // Na sua query SQL, agora você salva essas 3 variáveis
    $sql = "INSERT INTO jovens (nome, email, escolaridade_status, escolaridade_tipo, escolaridade_curso) VALUES (?, ?, ?, ?, ?)";
    // ... bind_param e execute ...

    /* 3. VERIFICAÇÃO DE DUPLICIDADE 
       Verifica se o CPF ou Email já existem na tabela 'jovens' (conforme o PDF). */
    $verifica = "SELECT id FROM jovens WHERE email='$email' OR cpf='$cpf'";
    $result = $conn->query($verifica);

    if($result->num_rows > 0){
        $mensagem = "<p style='color: #ff4d4d;'>Erro: Email ou CPF já cadastrado no sistema.</p>";
    } else {
        /* 4. INSERÇÃO SEGURA (Prepared Statements)
           Aqui incluímos TODOS os campos que a tua tabela jovens possui. */
        $stmt = $conn->prepare("INSERT INTO jovens (nome, email, senha, cpf, data_nascimento, cidade, estado, formacao, telefone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        // "sssssssss" significa que estamos a passar 9 strings
        $stmt->bind_param("sssssssss", $nome, $email, $senha, $cpf, $data_nasc, $cidade, $estado, $formacao, $telefone);

        if($stmt->execute()){
            $mensagem = "<p style='color: var(--principal);'>Cadastro realizado com sucesso! <a href='login.php'>Fazer Login</a></p>";
        } else {
            $mensagem = "<p style='color: #ff4d4d;'>Erro ao cadastrar: " . $conn->error . "</p>";
        }
        $stmt->close();
    }
}
?>

<link rel="stylesheet" href="../assets/css/style.css">

<div class="container">
    <div class="auth">
        <h2>Cadastro de Jovem</h2>
        <p>Preencha os dados para começar a sua carreira.</p>
        <hr>

        <?php echo $mensagem; ?>

        <form method="POST">
            <label>Nome Completo:</label>
            <input type="text" name="nome" placeholder="Seu nome" required>

            <label>Email:</label>
            <input type="email" name="email" placeholder="exemplo@email.com" required>

            <label>Senha:</label>
            <input type="password" name="senha" placeholder="Mínimo 6 caracteres" required>

            <div style="display: flex; gap: 10px;">
                <div style="flex: 1;">
                    <label>CPF:</label>
                    <input type="text" name="cpf" placeholder="000.000.000-00" required>
                </div>
                <div style="flex: 1;">
                    <label>Nascimento:</label>
                    <input type="date" name="data_nascimento" required>
                </div>
            </div>

            <div style="display: flex; gap: 10px;">
                <div style="flex: 2;">
                    <label>Cidade:</label>
                    <input type="text" name="cidade" placeholder="Sua cidade" required>
                </div>
                <div style="flex: 1;">
                    <label>Estado (UF):</label>
                    <input type="text" name="estado" maxlength="2" placeholder="EX: SP" required>
                </div>
            </div>

            <label>Ensino Médio</label>
            <select name="escolaridade_status" required>
                <option value="cursando">Cursando</option>
                <option value="completo">Completo</option>
            </select>

            <label>Tipo de Formação</label>
            <select name="escolaridade_tipo" id="tipo_ensino" onchange="toggleTecnico()" required>
                <option value="regular">Regular</option>
                <option value="tecnico">Técnico</option>
            </select>

            <div id="campo_tecnico" style="display: none; margin-top: 15px;">
                <label>Área do Técnico</label>
                <select name="curso_predefinido" id="curso_predefinido" onchange="verificarOutro()">
                    <option value="TI">Tecnologia da Informação (TI)</option>
                    <option value="Enfermagem">Enfermagem</option>
                    <option value="Administração">Administração</option>
                    <option value="Logística">Logística</option>
                    <option value="Mecânica">Mecânica</option>
                    <option value="Outro">Outro (Especificar)</option>
                </select>
                
                <input type="text" name="curso_outro" id="curso_outro" placeholder="Digite o nome do curso" style="display: none; margin-top: 10px;">
            </div>

            <label>Telefone:</label>
            <input type="text" name="telefone" placeholder="(00) 00000-0000">

            <button type="submit" class="btn">Criar Minha Conta</button>
        </form>

        <p style="margin-top: 15px; text-align: center;">
            Já tem conta? <a href="login.php">Entrar</a>
        </p>
        <script>
            function toggleTecnico() {
                const tipo = document.getElementById('tipo_ensino').value;
                const campoTecnico = document.getElementById('campo_tecnico');
                campoTecnico.style.display = (tipo === 'tecnico') ? 'block' : 'none';
            }

            function verificarOutro() {
                const curso = document.getElementById('curso_predefinido').value;
                const campoOutro = document.getElementById('curso_outro');
                campoOutro.style.display = (curso === 'Outro') ? 'block' : 'none';
            }
        </script>
    </div>
</div>

<?php include("../includes/footer.php"); ?>
