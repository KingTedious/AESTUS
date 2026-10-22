<?php
/* ============================================================
   empresa/locais.php

   Locais de trabalho da empresa.
   - Endereço principal (sede): é o endereço do perfil da empresa e o
     local padrão das vagas. Editado em empresa/perfil.php.
   - Outros locais (escritório, filial, unidade ou outro): cadastrados
     aqui e escolhidos em "Criar vaga".
   Um local usado por alguma vaga não pode ser removido (a vaga
   perderia o endereço); editar atualiza a cidade/UF/CEP dessas vagas.
   ============================================================ */
require_once dirname(__DIR__) . '/includes/csrf.php';

if (!isset($_SESSION['empresa_id'])) {
    header("Location: login.php");
    exit;
}

require_once dirname(__DIR__) . "/includes/config.php";
require_once dirname(__DIR__) . "/includes/locais.php";

$empresa_id  = intval($_SESSION['empresa_id']);
$disponivel  = jc_locais_disponiveis($conn);

/* ── POST: adicionar, editar ou remover ─────────────────────────────────── */
if ($disponivel && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $acao = $_POST['acao'] ?? '';

    // editar/remover: só locais da própria empresa
    $do_proprio = function (int $id) use ($conn, $empresa_id): bool {
        $stmt = $conn->prepare("SELECT id FROM empresa_locais WHERE id = ? AND empresa_id = ? LIMIT 1");
        $stmt->bind_param("ii", $id, $empresa_id);
        $stmt->execute();
        $achou = (bool) $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $achou;
    };

    if ($acao === 'salvar') {
        $local = jc_local_do_post($_POST);
        $erro  = jc_local_erro($local);
        $id    = intval($_POST['local_id'] ?? 0);
        if ($erro !== null) {
            $_SESSION['locais_erro']  = $erro;
            $_SESSION['locais_form']  = $local + ['id' => $id];
            header("Location: locais.php" . ($id > 0 ? "?editar={$id}" : '') . "#formLocal");
            exit;
        }
        if ($id > 0) {
            if (!$do_proprio($id)) {
                header("Location: locais.php?ok=erro");
                exit;
            }
            $stmt = $conn->prepare(
                "UPDATE empresa_locais
                 SET tipo = ?, nome = ?, cep = ?, logradouro = ?, numero = ?, complemento = ?,
                     bairro = ?, cidade = ?, estado = ?
                 WHERE id = ? AND empresa_id = ?"
            );
            $nome = $local['nome'] !== '' ? $local['nome'] : null;
            $cep  = $local['cep']  !== '' ? $local['cep']  : null;
            $stmt->bind_param("sssssssssii", $local['tipo'], $nome, $cep, $local['logradouro'], $local['numero'],
                              $local['complemento'], $local['bairro'], $local['cidade'], $local['estado'],
                              $id, $empresa_id);
            $stmt->execute();
            $stmt->close();
            jc_vagas_sincronizar_local($conn, $empresa_id, $id, $local);   // vagas que usam este local
            jc_log('local.editado', 'info', ['local' => $id]);
            header("Location: locais.php?ok=editado");
            exit;
        }
        $novo = jc_local_inserir($conn, $empresa_id, $local);
        jc_log($novo > 0 ? 'local.adicionado' : 'local.erro', $novo > 0 ? 'info' : 'erro', ['local' => $novo]);
        header("Location: locais.php?ok=" . ($novo > 0 ? 'adicionado' : 'erro'));
        exit;
    }

    if ($acao === 'remover') {
        $id = intval($_POST['local_id'] ?? 0);
        if (!$do_proprio($id)) {
            header("Location: locais.php?ok=erro");
            exit;
        }
        $stmt = $conn->prepare("SELECT COUNT(*) AS n FROM vagas WHERE local_id = ? AND empresa_id = ?");
        $stmt->bind_param("ii", $id, $empresa_id);
        $stmt->execute();
        $em_uso = intval($stmt->get_result()->fetch_assoc()['n'] ?? 0);
        $stmt->close();
        if ($em_uso > 0) {
            jc_log('local.remocao_bloqueada', 'aviso', ['local' => $id, 'vagas' => $em_uso]);
            header("Location: locais.php?ok=em_uso");
            exit;
        }
        $stmt = $conn->prepare("DELETE FROM empresa_locais WHERE id = ? AND empresa_id = ?");
        $stmt->bind_param("ii", $id, $empresa_id);
        $stmt->execute();
        $stmt->close();
        jc_log('local.removido', 'info', ['local' => $id]);
        header("Location: locais.php?ok=removido");
        exit;
    }

    header("Location: locais.php");
    exit;
}

/* ── Leitura ─────────────────────────────────────────────────────────────── */
$principal = jc_endereco_principal($conn, $empresa_id);
$principal_txt = endereco_empresa_formatado($principal);
$locais = $disponivel ? jc_locais_da_empresa($conn, $empresa_id) : [];

// formulário: edição (?editar=ID) ou dados devolvidos após erro
$form  = ['id' => 0, 'tipo' => 'escritorio', 'nome' => '', 'cep' => '', 'logradouro' => '', 'numero' => '',
          'complemento' => '', 'bairro' => '', 'cidade' => '', 'estado' => ''];
$editar = intval($_GET['editar'] ?? 0);
if ($editar > 0) {
    foreach ($locais as $l) {
        if (intval($l['id']) === $editar) {
            $form = array_merge($form, array_map(fn($v) => (string) ($v ?? ''), $l));
            $form['id'] = $editar;
        }
    }
}
$erro_form = (string) ($_SESSION['locais_erro'] ?? '');
if (!empty($_SESSION['locais_form']) && is_array($_SESSION['locais_form'])) {
    $form = array_merge($form, $_SESSION['locais_form']);
}
unset($_SESSION['locais_erro'], $_SESSION['locais_form']);

$ok = $_GET['ok'] ?? '';
$avisos = [
    'adicionado' => ['sucesso', 'Local adicionado. Ele já aparece em "Criar vaga".'],
    'editado'    => ['sucesso', 'Local atualizado. As vagas que usam este local foram atualizadas.'],
    'removido'   => ['sucesso', 'Local removido.'],
    'em_uso'     => ['erro',    'Este local está em uso por uma ou mais vagas e não pode ser removido.'],
    'erro'       => ['erro',    'Não foi possível salvar o local agora. Tente de novo.'],
];
$tipos = jc_local_tipos();
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Locais de Trabalho | JovemConnect</title>
    <link rel="stylesheet" href="../Assets/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/marca/jovemconnect-icone-32.png">
    <link rel="apple-touch-icon" href="../assets/img/marca/jovemconnect-icone-180.png">
    <script>
        (function () {
            var t = localStorage.getItem('jc-theme');
            if (t) document.documentElement.setAttribute('data-theme', t);
        }());
    </script>
</head>
<body>

<div class="form-page">
<div class="form-container">

    <div class="form-page-logo">
        <a href="../dashboard/empresa.php" class="marca marca-vertical nav-logo" aria-label="JovemConnect — meu painel">
            <img class="marca-tema-escuro" src="../assets/img/marca/jovemconnect-vertical.png" alt="JovemConnect" width="878" height="689"><img class="marca-tema-claro" src="../assets/img/marca/jovemconnect-vertical-claro.png" alt="JovemConnect" width="878" height="689" loading="lazy">
        </a>
    </div>

    <p style="margin:0 0 12px;"><a href="perfil.php" class="back-link"><span class="i i-arrow-left" aria-hidden="true"></span> Voltar ao perfil</a></p>

    <?php if (isset($avisos[$ok])): ?>
    <div class="alerta alerta-<?= $avisos[$ok][0] ?>" role="<?= $avisos[$ok][0] === 'erro' ? 'alert' : 'status' ?>">
        <span class="i i-<?= $avisos[$ok][0] === 'erro' ? 'alert-triangle' : 'check-circle' ?>" aria-hidden="true"></span>
        <span><?= $h($avisos[$ok][1]) ?></span>
    </div>
    <?php endif; ?>

    <div class="form-card">
        <div class="form-page-title">
            <span class="i i-map-pin" aria-hidden="true"></span>
            <h1 style="font-size:22px;">Locais de trabalho</h1>
        </div>
        <p class="form-page-subtitle">
            O endereço principal da empresa é o local padrão das vagas. Cadastre aqui
            escritórios, filiais ou outros locais para escolher em cada vaga.
        </p>
    </div>

    <?php if (!$disponivel): ?>
    <div class="form-card">
        <p class="text-secondary" style="font-size:14px;line-height:1.6;">
            Os locais de trabalho ainda não estão disponíveis nesta instalação.
            As vagas continuam usando a cidade informada em cada uma.
        </p>
    </div>
    <?php else: ?>

    <!-- Sede / endereço principal -->
    <div class="form-card">
        <div class="form-section-header">
            <span class="i i-building" aria-hidden="true"></span> Endereço principal (sede)
        </div>
        <?php if ($principal_txt !== ''): ?>
        <p class="local-endereco"><?= $h($principal_txt) ?></p>
        <?php else: ?>
        <p class="text-secondary" style="font-size:14px;">A empresa ainda não tem endereço cadastrado.</p>
        <?php endif; ?>
        <p style="margin-top:12px;font-size:13px;"><a href="perfil.php" class="link-seta">Editar no perfil da empresa <span class="i i-arrow-right" aria-hidden="true"></span></a></p>
    </div>

    <!-- Outros locais -->
    <div class="form-card">
        <div class="form-section-header">
            <span class="i i-map-pin" aria-hidden="true"></span> Outros locais
        </div>
        <?php if (!$locais): ?>
        <p class="text-secondary" style="font-size:14px;">Nenhum outro local cadastrado.</p>
        <?php else: ?>
        <ul class="locais-lista">
            <?php foreach ($locais as $l): $em_uso = intval($l['em_uso']); ?>
            <li class="local-item">
                <div class="local-item-texto">
                    <strong><?= $h(jc_local_rotulo($l['tipo'], $l['nome'])) ?></strong>
                    <span class="local-endereco"><?= $h(endereco_empresa_formatado($l)) ?></span>
                    <span class="text-muted" style="font-size:12px;">
                        <?= $em_uso > 0 ? ($em_uso === 1 ? 'Usado por 1 vaga' : "Usado por {$em_uso} vagas") : 'Nenhuma vaga usa este local' ?>
                    </span>
                </div>
                <div class="local-item-acoes">
                    <a href="locais.php?editar=<?= intval($l['id']) ?>#formLocal" class="btn btn-ghost btn-sm"
                       aria-label="Editar <?= $h(jc_local_rotulo($l['tipo'], $l['nome'])) ?>"><span class="i i-pencil" aria-hidden="true"></span> Editar</a>
                    <form method="POST" action="locais.php" data-envio-unico="Removendo...">
                        <?= csrf_field() ?>
                        <input type="hidden" name="acao" value="remover">
                        <input type="hidden" name="local_id" value="<?= intval($l['id']) ?>">
                        <button type="submit" class="btn btn-danger btn-sm" <?= $em_uso > 0 ? 'disabled aria-disabled="true" title="Em uso por vagas"' : '' ?>
                                aria-label="Remover <?= $h(jc_local_rotulo($l['tipo'], $l['nome'])) ?>"><span class="i i-trash" aria-hidden="true"></span> Remover</button>
                    </form>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>

    <!-- Adicionar / editar -->
    <form method="POST" action="locais.php" id="formLocal" class="form-card" novalidate data-envio-unico="Salvando...">
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="salvar">
        <input type="hidden" name="local_id" value="<?= intval($form['id']) ?>">
        <div class="form-section-header">
            <span class="i i-<?= intval($form['id']) > 0 ? 'pencil' : 'plus' ?>" aria-hidden="true"></span>
            <?= intval($form['id']) > 0 ? 'Editar local' : 'Adicionar local' ?>
        </div>

        <?php if ($erro_form !== ''): ?>
        <div class="alerta alerta-erro" role="alert">
            <span class="i i-alert-triangle" aria-hidden="true"></span><span><?= $h($erro_form) ?></span>
        </div>
        <?php endif; ?>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="localTipo">Tipo</label>
                <select name="tipo" id="localTipo" class="form-control">
                    <?php foreach ($tipos as $v => $t): ?>
                    <option value="<?= $h($v) ?>" <?= $form['tipo'] === $v ? 'selected' : '' ?>><?= $h($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="localNome">Nome (opcional)</label>
                <input type="text" name="nome" id="localNome" class="form-control" maxlength="100"
                       placeholder="Ex: Unidade Augusta" value="<?= $h($form['nome']) ?>">
            </div>
        </div>
        <?php $campos_prefixo = 'local'; include __DIR__ . '/../includes/locais_campos.php'; ?>

        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary"><span class="i i-check" aria-hidden="true"></span> Salvar local</button>
            <?php if (intval($form['id']) > 0): ?>
            <a href="locais.php" class="btn btn-secondary">Cancelar edição</a>
            <?php endif; ?>
        </div>
    </form>

    <?php endif; ?>

</div><!-- /.form-container -->
</div><!-- /.form-page -->

<?php include dirname(__DIR__) . '/includes/assistente.php'; ?>

</body>
</html>
