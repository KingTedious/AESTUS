<?php
/* ============================================================
   includes/conta_status.php — contas suspensas e visibilidade (Stage 7)

   Regras do lado PÚBLICO do site para as decisões da administração
   (admin/). Tudo depende da migração da Stage 7; sem ela, nenhuma
   função muda o comportamento da v4.00 (devolvem "ativa" / "" / true).

   Conta suspensa (jovens.conta_status / empresas.conta_status):
     - não entra (login recusado com mensagem própria);
     - quem já estava logado perde a sessão na próxima página
       (jc_conta_verificar_sessao, chamada por includes/config.php);
     - o conteúdo dela deixa de aparecer para os outros usuários:
       vagas de empresa suspensa e perfil/currículo de jovem suspenso
       ficam indisponíveis;
     - NADA é apagado: candidaturas, vagas, favoritos e perfil continuam
       no banco e voltam a aparecer quando a conta é reativada.

   Vaga (vagas.moderacao_status):
     aprovada | sinalizada → visível (sinalizada = em análise, mas no ar)
     pendente | rejeitada | suspensa → oculta para os jovens; a empresa
     continua vendo a vaga em "Minhas vagas", com o aviso do motivo.
   ============================================================ */
require_once __DIR__ . '/esquema.php';
require_once __DIR__ . '/documentos.php';   // JC_CONTATO_EMAIL

if (!function_exists('jc_conta_suspensa')) {

    /** Mensagem mostrada a quem tenta entrar numa conta suspensa (sem detalhes internos). */
    function jc_conta_msg_suspensa(): string {
        return 'Esta conta está suspensa pela administração do JovemConnect. '
             . 'Se você acredita que houve um engano, fale com a equipe pelo e-mail ' . JC_CONTATO_EMAIL . '.';
    }

    /** true se a conta (jovem|empresa) está suspensa. Sem a migração: sempre false. */
    function jc_conta_suspensa(mysqli $conn, string $tipo, int $id): bool {
        if ($id <= 0 || !in_array($tipo, ['jovem', 'empresa'], true) || !jc_stage7_disponivel($conn)) {
            return false;
        }
        $tabela = $tipo === 'jovem' ? 'jovens' : 'empresas';
        $stmt = $conn->prepare("SELECT conta_status FROM `{$tabela}` WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $linha = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return ($linha['conta_status'] ?? 'ativa') === 'suspensa';
    }

    /** Caminho relativo até a raiz do projeto a partir do script atual (ex.: "../"). */
    function jc_url_raiz_relativa(): string {
        $raiz   = realpath(dirname(__DIR__));
        $script = realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: '';
        if ($raiz && $script && stripos($script, $raiz) === 0) {
            $rel = trim(str_replace('\\', '/', substr(dirname($script), strlen($raiz))), '/');
            return str_repeat('../', ($rel === '') ? 0 : substr_count($rel, '/') + 1);
        }
        return '';
    }

    /** A requisição espera JSON (fetch/AJAX)? */
    function jc_requisicao_json(): bool {
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
            return true;
        }
        $aceita = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        $tipo   = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
        return stripos($aceita, 'application/json') !== false || stripos($tipo, 'application/json') !== false;
    }

    /**
     * Chamada por includes/config.php em toda página com sessão de jovem ou
     * empresa: se a conta foi suspensa depois do login, encerra a identidade
     * na sessão e manda para a tela de login com o aviso (ou 403 em JSON).
     */
    function jc_conta_verificar_sessao(mysqli $conn): void {
        if (session_status() !== PHP_SESSION_ACTIVE || !isset($_SESSION) || !is_array($_SESSION)) {
            return;
        }
        $tipo = isset($_SESSION['jovem_id']) ? 'jovem' : (isset($_SESSION['empresa_id']) ? 'empresa' : null);
        if ($tipo === null) {
            return;
        }
        if (!jc_conta_suspensa($conn, $tipo, intval($_SESSION[$tipo . '_id']))) {
            return;
        }
        if (function_exists('jc_log_seguranca')) {
            jc_log_seguranca('conta.sessao_encerrada', 'aviso', ['tipo' => $tipo, 'motivo' => 'conta_suspensa']);
        }
        unset($_SESSION['jovem_id'], $_SESSION['jovem_nome'], $_SESSION['empresa_id'], $_SESSION['empresa_nome']);
        @session_regenerate_id(true);
        $_SESSION['erro_login'] = jc_conta_msg_suspensa();

        if (jc_requisicao_json()) {
            if (!headers_sent()) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode(['error' => jc_conta_msg_suspensa()]);
            exit;
        }
        if (!headers_sent()) {
            header('Location: ' . jc_url_raiz_relativa() . $tipo . '/login.php');
        }
        exit;
    }

    /**
     * Trecho SQL que deixa só as vagas visíveis para os jovens:
     * moderação aprovada/sinalizada e empresa não suspensa.
     * $alias = apelido da tabela vagas na consulta. Sem a migração: ''.
     */
    function jc_vagas_visiveis_sql(mysqli $conn, string $alias = 'v'): string {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $alias) || !jc_stage7_disponivel($conn)) {
            return '';
        }
        return " AND {$alias}.moderacao_status IN ('aprovada','sinalizada')"
             . " AND NOT EXISTS (SELECT 1 FROM empresas jc_es WHERE jc_es.id = {$alias}.empresa_id"
             . " AND jc_es.conta_status = 'suspensa')";
    }

    /**
     * Expressão SQL (0/1) "esta vaga está visível para os jovens?", para listas
     * que mostram também as vagas fora do ar (favoritos, candidaturas).
     * Sem a migração: '1'.
     */
    function jc_vaga_visivel_expr(mysqli $conn, string $alias = 'v'): string {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $alias) || !jc_stage7_disponivel($conn)) {
            return '1';
        }
        return "({$alias}.moderacao_status IN ('aprovada','sinalizada')"
             . " AND NOT EXISTS (SELECT 1 FROM empresas jc_es WHERE jc_es.id = {$alias}.empresa_id"
             . " AND jc_es.conta_status = 'suspensa'))";
    }

    /** true se a vaga existe e está visível para os jovens. */
    function jc_vaga_visivel(mysqli $conn, int $vaga_id): bool {
        if ($vaga_id <= 0) {
            return false;
        }
        $stmt = $conn->prepare("SELECT v.id FROM vagas v WHERE v.id = ?" . jc_vagas_visiveis_sql($conn, 'v') . " LIMIT 1");
        $stmt->bind_param("i", $vaga_id);
        $stmt->execute();
        $achou = (bool) $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $achou;
    }

    /** Trecho SQL que deixa só jovens com conta ativa ($alias = apelido de jovens). Sem a migração: ''. */
    function jc_jovens_ativos_sql(mysqli $conn, string $alias = 'j'): string {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $alias) || !jc_stage7_disponivel($conn)) {
            return '';
        }
        return " AND {$alias}.conta_status = 'ativa'";
    }

    /** Textos do estado de moderação para a EMPRESA dona da vaga (null = vaga normal). */
    function jc_vaga_aviso_moderacao(?string $status, ?string $motivo): ?array {
        $motivos = jc_moderacao_motivos_vaga();
        $texto_motivo = $motivos[(string) $motivo] ?? null;
        switch ((string) $status) {
            case 'pendente':
                return ['selo' => 'Em revisão', 'classe' => 'aviso',
                        'texto' => 'Esta vaga está em revisão pela equipe do JovemConnect e ainda não aparece para os jovens.'];
            case 'rejeitada':
                return ['selo' => 'Não publicada', 'classe' => 'erro',
                        'texto' => 'Esta vaga não foi aprovada pela moderação. ' . ($texto_motivo ?? 'Ela não segue as regras da plataforma.')];
            case 'suspensa':
                return ['selo' => 'Suspensa', 'classe' => 'erro',
                        'texto' => 'Esta vaga foi suspensa pela moderação e não aparece para os jovens. ' . ($texto_motivo ?? '')];
            default:
                return null;   // aprovada / sinalizada / sem migração: nada muda para a empresa
        }
    }

    /** Motivos de moderação de vaga (código → texto mostrado à empresa). */
    function jc_moderacao_motivos_vaga(): array {
        return [
            'revisao_automatica'  => 'A vaga está em revisão pela equipe do JovemConnect.',
            'cobranca_candidato'  => 'A vaga pede pagamento ou compra do candidato, o que não é permitido.',
            'dados_sensiveis'     => 'A vaga pede documentos, senhas ou dados pessoais de forma indevida.',
            'conteudo_enganoso'   => 'A vaga tem informações enganosas ou incompatíveis com uma oferta de trabalho.',
            'discriminacao'       => 'A vaga contém critérios discriminatórios.',
            'linguagem_inadequada' => 'A vaga contém linguagem ofensiva ou inadequada.',
            'spam'                => 'A vaga parece propaganda ou conteúdo repetitivo.',
            'contato_externo'     => 'A vaga direciona o contato para fora da plataforma de forma inadequada.',
            'outro'               => 'A vaga não segue as regras da plataforma.',
        ];
    }
}
