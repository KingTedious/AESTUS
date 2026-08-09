<?php
session_start();
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: listar.php");
    exit;
}

$action = $_POST['action'] ?? '';

// 1. Enviar Proposta
if ($action === 'enviar_proposta') {
    if (!isset($_SESSION['jovem_id'])) {
        header("Location: ../jovem/login.php");
        exit;
    }

    $jovem_id = intval($_SESSION['jovem_id']);
    $freelance_id = intval($_POST['freelance_id']);
    $valor_proposto = floatval($_POST['valor_proposto']);
    $prazo_dias = intval($_POST['prazo_dias']);
    $mensagem = trim($_POST['mensagem']);

    // Regra da Taxa
    if ($valor_proposto > 500) {
        $percentual = 5.0;
    } elseif ($valor_proposto > 200) {
        $percentual = 8.0;
    } else {
        $percentual = 10.0;
    }

    $taxa_valor = round($valor_proposto * ($percentual / 100), 2);
    $valor_liquido = round($valor_proposto - $taxa_valor, 2);

    $stmt = $conn->prepare("
        INSERT INTO propostas_freelance 
        (freelance_id, jovem_id, valor_proposto, taxa_percentual, taxa_valor, valor_liquido, prazo_dias, mensagem) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iiddddis", $freelance_id, $jovem_id, $valor_proposto, $percentual, $taxa_valor, $valor_liquido, $prazo_dias, $mensagem);
    
    if ($stmt->execute()) {
        $stmt->close();
        header("Location: detalhe.php?id={$freelance_id}&success=1");
        exit;
    } else {
        $stmt->close();
        header("Location: detalhe.php?id={$freelance_id}&error=1");
        exit;
    }
}

// 2. Criar Anúncio de Freelance
if ($action === 'criar_freelance') {
    $titulo = trim($_POST['titulo']);
    $descricao = trim($_POST['descricao']);
    $categoria = trim($_POST['categoria']);
    $orcamento = floatval($_POST['orcamento_maximo']);
    $prazo = intval($_POST['prazo_dias']);

    $empresa_id = $_SESSION['empresa_id'] ?? null;
    $jovem_id = $_SESSION['jovem_id'] ?? null;

    $stmt = $conn->prepare("
        INSERT INTO freelances (empresa_id, jovem_contratante_id, titulo, descricao, categoria, orcamento_maximo, prazo_dias)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iisssdi", $empresa_id, $jovem_id, $titulo, $descricao, $categoria, $orcamento, $prazo);

    if ($stmt->execute()) {
        $stmt->close();
        header("Location: listar.php?created=1");
        exit;
    } else {
        $stmt->close();
        header("Location: criar.php?error=1");
        exit;
    }
}