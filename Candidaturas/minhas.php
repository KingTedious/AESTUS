<?php 
/* 1. INCLUSÃO DE CABEÇALHO E CONFIGURAÇÕES */
include("../includes/header.php"); 
include("../includes/config.php");
session_start();

/* 2. PROTEÇÃO DE ACESSO
   Verifica se o ID do jovem existe na sessão. Se não, bloqueia o acesso. */
if(!isset($_SESSION['jovem_id'])){
    echo "<div class='container'><h1>Acesso negado.</h1><p>Por favor, faça login.</p></div>";
    include("../includes/footer.php");
    exit;
}

$jovem_id = $_SESSION['jovem_id'];

/* 3. CONSULTA AO BANCO DE DATOS (SQL)
   Explicação: Selecionamos dados da vaga, o nome da empresa e o STATUS da candidatura.
   O JOIN liga as tabelas através das chaves estrangeiras (IDs). */
$sql = "SELECT 
            vagas.titulo, 
            vagas.descricao, 
            empresas.nome_empresa, 
            candidaturas.status 
        FROM candidaturas
        JOIN vagas ON candidaturas.vaga_id = vagas.id
        JOIN empresas ON vagas.empresa_id = empresas.id
        WHERE candidaturas.jovem_id = '$jovem_id'";

$result = $conn->query($sql);
?>

<div class="container">
    <h1>Minhas Candidaturas</h1>
    <p>Acompanhe aqui o estado dos seus processos seletivos.</p>
    <hr>

    <?php
    /* 5. VERIFICAÇÃO DE RESULTADOS
       Se o número de linhas (num_rows) for maior que 0, mostramos as vagas. */
    if($result->num_rows > 0){

        while($row = $result->fetch_assoc()){
            ?>
            <div class="card">
                <h3><?php echo $row['titulo']; ?></h3>
                <p><strong>Empresa:</strong> <?php echo $row['nome_empresa']; ?></p>
                <p><?php echo $row['descricao']; ?></p>
                
                <p><strong>Status:</strong> 
                    <span style="color: var(--principal);">
                        <?php echo $row['status']; ?>
                    </span>
                </p>
            </div>
            <?php
        }

    } else {
        /* Caso o jovem ainda não tenha concorrido a nada */
        echo "<div class='card'><p>Você ainda não se candidatou a nenhuma vaga.</p></div>";
    }
    ?>
</div>

<?php 
/* 6. RODAPÉ */
include("../includes/footer.php"); 
?>