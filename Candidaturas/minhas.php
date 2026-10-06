<?php
/* ============================================================
   Candidaturas/minhas.php  — corrigido com base no schema real
   ============================================================ */

require_once dirname(__DIR__) . '/includes/session.php';   // Stage 3: sessão endurecida (Stage 2) também nesta página
jc_session_start();

// Stage 3: login checado ANTES de qualquer saída HTML. Antes o header.php era
// impresso primeiro e o redirect só funcionava graças ao output_buffering do php.ini.
if (!isset($_SESSION['jovem_id'])) {
    header("Location: ../jovem/login.php");
    exit;
}

include("../includes/config.php");
include("../includes/header.php");

$jovem_id = intval($_SESSION['jovem_id']);

// Colunas reais do banco: criado_em (não created_at), salario VARCHAR, sem carga_horaria
$sql = "SELECT
            v.id            AS vaga_id,
            v.titulo,
            v.descricao,
            v.salario,
            v.tipo_contrato,
            v.cidade        AS vaga_cidade,
            v.estado        AS vaga_estado,
            e.nome_empresa,
            c.id            AS candidatura_id,
            c.status,
            c.criado_em     AS data_candidatura
        FROM candidaturas c
        JOIN vagas    v ON c.vaga_id    = v.id
        JOIN empresas e ON v.empresa_id = e.id
        WHERE c.jovem_id = ?
        ORDER BY c.criado_em DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $jovem_id);
$stmt->execute();
$result = $stmt->get_result();

// Badge de status baseado nos valores reais do banco (DEFAULT 'Recebido')
function statusBadge(string $status): string {
    $map = [
        'recebido'    => ['class' => '',               'label' => 'Recebido'],
        'visualizado' => ['class' => 'badge-info',     'label' => 'Visualizado'],
        'em análise'  => ['class' => 'badge-warning',  'label' => 'Em análise'],
        'aprovado'    => ['class' => 'badge-success',  'label' => 'Aprovado'],
        'reprovado'   => ['class' => 'badge-danger',   'label' => 'Reprovado'],
    ];
    $key  = mb_strtolower(trim($status));
    $info = $map[$key] ?? ['class' => '', 'label' => htmlspecialchars($status)];
    return '<span class="badge ' . $info['class'] . '">' . $info['label'] . '</span>';
}
?>

<div class="dashboard-wrap">
  <main class="dashboard-main">

    <div class="page-heading fade-up">
      <h1>Minhas Candidaturas</h1>
      <p>Acompanhe o andamento dos seus processos seletivos.</p>
    </div>

    <?php if ($result->num_rows > 0): ?>

      <div class="items-grid fade-up">

        <?php while ($row = $result->fetch_assoc()): ?>
          <div class="job-card">

            <div class="job-card-top">
              <h3 class="job-card-title"><?= htmlspecialchars($row['titulo']) ?></h3>
              <?= statusBadge($row['status']) ?>
            </div>

            <div class="job-card-company">
              <span>🏢</span>
              <?= htmlspecialchars($row['nome_empresa']) ?>
            </div>

            <div class="job-card-meta">
              <?php if (!empty($row['salario'])): ?>
              <div class="job-card-meta-item">
                <span>💰</span>
                <!-- salario é VARCHAR no banco — exibir direto, sem number_format() -->
                <span>R$ <?= htmlspecialchars($row['salario']) ?></span>
              </div>
              <?php endif; ?>

              <?php if (!empty($row['tipo_contrato'])): ?>
              <div class="job-card-meta-item">
                <span>📄</span>
                <span><?= htmlspecialchars($row['tipo_contrato']) ?></span>
              </div>
              <?php endif; ?>
            </div>

            <?php if (!empty($row['vaga_cidade'])): ?>
            <div class="job-card-location">
              <span>📍</span>
              <span>
                <?= htmlspecialchars($row['vaga_cidade']) ?>
                <?php if (!empty($row['vaga_estado'])): ?>
                  , <?= htmlspecialchars($row['vaga_estado']) ?>
                <?php endif; ?>
              </span>
            </div>
            <?php endif; ?>

            <p class="text-secondary" style="font-size:13px;line-height:1.6;margin-bottom:18px;flex:1;">
              <?= htmlspecialchars(mb_substr($row['descricao'] ?? '', 0, 130)) ?>…
            </p>

            <p class="text-muted" style="font-size:11px;margin-bottom:14px;">
              Candidatado em <?= date('d/m/Y', strtotime($row['data_candidatura'])) ?>
            </p>

            <div class="job-card-footer">
              <a href="../Vagas/listar.php?id=<?= intval($row['vaga_id']) ?>"
                 class="btn btn-ghost btn-sm btn-full">
                Ver Detalhes
              </a>
            </div>

          </div>
        <?php endwhile; ?>

      </div>

    <?php else: ?>

      <div class="card fade-up" style="text-align:center;padding:56px 32px;max-width:480px;margin:0 auto;">
        <div style="font-size:44px;margin-bottom:18px;">📋</div>
        <h3 style="font-size:18px;font-weight:700;margin-bottom:8px;">Nenhuma candidatura ainda</h3>
        <p class="text-secondary" style="font-size:14px;line-height:1.6;margin-bottom:28px;">
          Explore as vagas disponíveis e candidate-se às que mais combinam com o seu perfil.
        </p>
        <a href="../dashboard/jovem.php" class="btn btn-primary btn-full">Ver vagas disponíveis</a>
      </div>

    <?php endif; ?>

  </main>
</div>

<!-- Stage 3: FABs removidos daqui — includes/footer.php já os renderiza (IDs duplicados
     faziam o botão visível ficar sem ação) -->

<?php
$stmt->close();
include("../includes/footer.php");
?>