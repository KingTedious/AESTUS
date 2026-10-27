<?php
require_once __DIR__ . '/../compat.php';   // PHP 7.4/8.0: str_contains etc. (includes/compat.php)
/* ============================================================
   includes/admin/servicos.php — operações da Central administrativa
   (Stage 7)

   As páginas de admin/ só montam a tela e chamam estas funções.
   Aqui ficam as consultas e as alterações, sempre:
   - com consultas parametrizadas;
   - selecionando SÓ colunas não sensíveis (nunca senha, CPF, data de
     nascimento, telefone, CEP/endereço, códigos de verificação ou o
     caminho do currículo);
   - dentro de jc_admin_transacao() junto com a linha de auditoria;
   - com justificativa obrigatória nas decisões;
   - sem apagar nada (suspender e tirar do ar são reversíveis).
   ============================================================ */

if (!function_exists('jc_admin_indicadores')) {

    // ── Catálogos ───────────────────────────────────────────────────────

    function jc_admin_motivos_suspensao(): array {
        return [
            'fraude'              => 'Fraude ou golpe',
            'spam'                => 'Spam ou uso abusivo',
            'conteudo_ofensivo'   => 'Conteúdo ofensivo, assédio ou ameaça',
            'discriminacao'       => 'Discriminação',
            'personificacao'      => 'Identidade falsa ou personificação',
            'seguranca'           => 'Risco de segurança (conta possivelmente comprometida)',
            'solicitacao_titular' => 'Pedido do próprio titular',
            'outro'               => 'Outro motivo (descreva na justificativa)',
        ];
    }

    function jc_admin_motivos_reativacao(): array {
        return [
            'revisao_concluida' => 'Revisão concluída',
            'engano'            => 'Suspensão feita por engano',
            'regularizada'      => 'Situação regularizada',
            'outro'             => 'Outro motivo (descreva na justificativa)',
        ];
    }

    function jc_admin_motivos_aprovacao(): array {
        return [
            'revisao_concluida' => 'Revisão concluída: a vaga segue as regras',
            'corrigida'         => 'Vaga corrigida',
            'falso_positivo'    => 'Sinalização automática indevida',
        ];
    }

    /** Ações de moderação de vaga: estado de destino e estados de origem permitidos. */
    function jc_admin_vaga_acoes(): array {
        return [
            'aprovar'   => ['para' => 'aprovada',  'rotulo' => 'Aprovar e deixar visível',
                            'de' => ['pendente', 'sinalizada', 'rejeitada', 'suspensa']],
            'revisar'   => ['para' => 'pendente',  'rotulo' => 'Tirar do ar para revisão',
                            'de' => ['aprovada', 'sinalizada', 'rejeitada', 'suspensa']],
            'rejeitar'  => ['para' => 'rejeitada', 'rotulo' => 'Rejeitar',
                            'de' => ['aprovada', 'pendente', 'sinalizada']],
            'suspender' => ['para' => 'suspensa',  'rotulo' => 'Suspender',
                            'de' => ['aprovada', 'pendente', 'sinalizada']],
        ];
    }

    function jc_admin_decisoes_item(): array {
        return [
            'sem_violacao'        => 'Sem violação (conteúdo mantido)',
            'violacao_confirmada' => 'Violação confirmada (medida aplicada)',
            'conteudo_corrigido'  => 'Conteúdo corrigido',
        ];
    }

    function jc_admin_estados_vaga(): array {
        return [
            'aprovada'   => 'Aprovada',
            'sinalizada' => 'Sinalizada (visível, em análise)',
            'pendente'   => 'Em revisão (fora do ar)',
            'rejeitada'  => 'Rejeitada',
            'suspensa'   => 'Suspensa',
        ];
    }

    /** Campos editáveis pela administração (chave do mapa de validação → coluna). */
    function jc_admin_campos_editaveis(string $tipo): array {
        // (sem "match", que só existe no PHP 8.0+: o projeto também roda no PHP 7.4)
        $campos = [
            'jovem'   => ['jovem.nome' => 'nome', 'jovem.area_interesse' => 'area_interesse',
                          'jovem.resumo' => 'resumo_profissional', 'jovem.cidade' => 'cidade'],
            'empresa' => ['empresa.nome' => 'nome_empresa', 'empresa.area_atuacao' => 'area_atuacao',
                          'empresa.descricao' => 'descricao', 'empresa.site' => 'site_oficial',
                          'empresa.linkedin' => 'linkedin', 'empresa.cidade' => 'cidade'],
            'vaga'    => ['vaga.titulo' => 'titulo', 'vaga.descricao' => 'descricao',
                          'vaga.salario' => 'salario', 'vaga.carga_horaria' => 'carga_horaria'],
        ];
        return $campos[$tipo] ?? [];
    }

    /** Escapa curingas de LIKE. */
    function jc_admin_like(string $t): string {
        return '%' . addcslashes($t, '%_\\') . '%';
    }

    /** Data AAAA-MM-DD válida (ou null). */
    function jc_admin_data_param(?string $d): ?string {
        if (!is_string($d) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            return null;
        }
        [$a, $m, $dia] = array_map('intval', explode('-', $d));
        return checkdate($m, $dia, $a) ? $d : null;
    }

    /** Executa SELECT parametrizado e devolve todas as linhas. */
    function jc_admin_select(mysqli $conn, string $sql, string $tipos = '', array $args = []): array {
        $stmt = $conn->prepare($sql);
        if ($tipos !== '') {
            $stmt->bind_param($tipos, ...$args);
        }
        $stmt->execute();
        $linhas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $linhas;
    }

    function jc_admin_select_um(mysqli $conn, string $sql, string $tipos = '', array $args = []): ?array {
        $l = jc_admin_select($conn, $sql, $tipos, $args);
        return $l[0] ?? null;
    }

    // ── Painel ──────────────────────────────────────────────────────────

    function jc_admin_indicadores(mysqli $conn): array {
        $i = [];
        foreach (['jovens' => 'jovens', 'empresas' => 'empresas'] as $chave => $tabela) {
            $i[$chave] = jc_admin_select_um($conn,
                "SELECT COUNT(*) AS total,
                        COALESCE(SUM(conta_status = 'ativa'), 0) AS ativas,
                        COALESCE(SUM(conta_status = 'suspensa'), 0) AS suspensas,
                        COALESCE(SUM(criado_em >= NOW() - INTERVAL 7 DAY), 0) AS novas7
                 FROM `{$tabela}`");
        }
        $i['vagas'] = jc_admin_select_um($conn,
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(moderacao_status = 'aprovada'), 0)   AS aprovadas,
                    COALESCE(SUM(moderacao_status = 'sinalizada'), 0) AS sinalizadas,
                    COALESCE(SUM(moderacao_status = 'pendente'), 0)   AS pendentes,
                    COALESCE(SUM(moderacao_status = 'rejeitada'), 0)  AS rejeitadas,
                    COALESCE(SUM(moderacao_status = 'suspensa'), 0)   AS suspensas,
                    COALESCE(SUM(criado_em >= NOW() - INTERVAL 7 DAY), 0) AS novas7
             FROM vagas");
        $i['vagas']['visiveis'] = intval(jc_admin_select_um($conn,
            "SELECT COUNT(*) AS n FROM vagas v WHERE 1 = 1" . jc_vagas_visiveis_sql($conn, 'v'))['n'] ?? 0);
        $i['candidaturas'] = jc_admin_select_um($conn,
            "SELECT COUNT(*) AS total, COALESCE(SUM(criado_em >= NOW() - INTERVAL 7 DAY), 0) AS novas7 FROM candidaturas");
        $i['fila'] = jc_admin_select_um($conn,
            "SELECT COALESCE(SUM(estado = 'pendente'), 0) AS pendentes,
                    COALESCE(SUM(estado = 'pendente' AND risco = 'alto'), 0)  AS alto,
                    COALESCE(SUM(estado = 'pendente' AND risco = 'medio'), 0) AS medio,
                    COALESCE(SUM(estado = 'pendente' AND risco = 'baixo'), 0) AS baixo,
                    COALESCE(SUM(estado = 'pendente' AND origem = 'denuncia'), 0) AS denuncias
             FROM moderacao_itens");
        $i['denuncias'] = jc_admin_select_um($conn,
            "SELECT COUNT(*) AS total, COALESCE(SUM(criado_em >= NOW() - INTERVAL 7 DAY), 0) AS novas7 FROM denuncias");
        $i['seguranca'] = jc_admin_limite_resumo($conn);
        $i['seguranca']['negados_24h'] = intval(jc_admin_select_um($conn,
            "SELECT COUNT(*) AS n FROM admin_auditoria WHERE resultado = 'negado' AND criado_em >= NOW() - INTERVAL 1 DAY")['n'] ?? 0);
        $i['recentes'] = jc_admin_select($conn,
            "SELECT a.id, DATE_FORMAT(a.criado_em, '%Y-%m-%d %H:%i:%s') AS criado_em, a.acao, a.recurso_tipo, a.recurso_id,
                    a.resultado, a.motivo_codigo, ad.nome AS admin_nome
             FROM admin_auditoria a LEFT JOIN administradores ad ON ad.id = a.admin_id
             ORDER BY a.id DESC LIMIT 8");
        return $i;
    }

    // ── Contas ──────────────────────────────────────────────────────────

    /**
     * Busca contas. Filtros: q, tipo (jovem|empresa|''), status (ativa|suspensa|''), de, ate.
     * @return array{total:int, linhas:array}
     */
    function jc_admin_contas_buscar(mysqli $conn, array $f, int $pagina, int $por): array {
        $ramos = [];
        $tipos = '';
        $args  = [];
        $q = trim((string) ($f['q'] ?? ''));
        foreach (['jovem' => ['jovens', 'nome'], 'empresa' => ['empresas', 'nome_empresa']] as $tipo => [$tabela, $col_nome]) {
            if (!empty($f['tipo']) && $f['tipo'] !== $tipo) {
                continue;
            }
            $w = [];
            if ($q !== '') {
                if (ctype_digit($q)) {
                    $w[] = 't.id = ?';
                    $tipos .= 'i';
                    $args[] = intval($q);
                } else {
                    $w[] = "(t.`{$col_nome}` LIKE ? OR t.email LIKE ?)";
                    $tipos .= 'ss';
                    $args[] = jc_admin_like($q);
                    $args[] = jc_admin_like($q);
                }
            }
            if (in_array($f['status'] ?? '', ['ativa', 'suspensa'], true)) {
                $w[] = 't.conta_status = ?';
                $tipos .= 's';
                $args[] = $f['status'];
            }
            if ($de = jc_admin_data_param($f['de'] ?? null)) {
                $w[] = 't.criado_em >= ?';
                $tipos .= 's';
                $args[] = $de . ' 00:00:00';
            }
            if ($ate = jc_admin_data_param($f['ate'] ?? null)) {
                $w[] = 't.criado_em <= ?';
                $tipos .= 's';
                $args[] = $ate . ' 23:59:59';
            }
            $ramos[] = "SELECT '{$tipo}' AS tipo, t.id, t.`{$col_nome}` AS nome, t.email, t.cidade, t.estado,
                               t.conta_status, DATE_FORMAT(t.criado_em, '%Y-%m-%d %H:%i:%s') AS criado_em
                        FROM `{$tabela}` t" . ($w ? ' WHERE ' . implode(' AND ', $w) : '');
        }
        if (!$ramos) {
            return ['total' => 0, 'linhas' => []];
        }
        $uniao = implode(' UNION ALL ', $ramos);
        $total = intval(jc_admin_select_um($conn, "SELECT COUNT(*) AS n FROM ({$uniao}) c", $tipos, $args)['n'] ?? 0);
        $offset = max(0, ($pagina - 1) * $por);
        $linhas = jc_admin_select($conn,
            "SELECT * FROM ({$uniao}) c ORDER BY c.criado_em DESC, c.id DESC LIMIT {$por} OFFSET {$offset}",
            $tipos, $args);
        return ['total' => $total, 'linhas' => $linhas];
    }

    /** Uma conta, só com dados não sensíveis, e números relacionados. */
    function jc_admin_conta_carregar(mysqli $conn, string $tipo, int $id): ?array {
        if ($tipo === 'jovem') {
            $c = jc_admin_select_um($conn,
                "SELECT id, nome, email, cidade, estado, area_interesse, resumo_profissional, perfil_publico,
                        escolaridade_status, escolaridade_tipo, escolaridade_curso,
                        COALESCE(email_verificado, 0) AS email_verificado, COALESCE(telefone_verificado, 0) AS telefone_verificado,
                        (curriculo IS NOT NULL AND curriculo <> '') AS tem_curriculo,
                        DATE_FORMAT(ultimo_login, '%Y-%m-%d %H:%i:%s') AS ultimo_login,
                        conta_status, DATE_FORMAT(conta_status_em, '%Y-%m-%d %H:%i:%s') AS conta_status_em,
                        DATE_FORMAT(criado_em, '%Y-%m-%d %H:%i:%s') AS criado_em
                 FROM jovens WHERE id = ? LIMIT 1", "i", [$id]);
            if (!$c) {
                return null;
            }
            $c['tipo'] = 'jovem';
            $c['nome_exibicao'] = $c['nome'];
            $c['habilidades']  = array_column(jc_admin_select($conn, "SELECT habilidade FROM habilidades_jovem WHERE jovem_id = ? ORDER BY id", "i", [$id]), 'habilidade');
            $c['formacoes']    = jc_admin_select($conn, "SELECT titulo, instituicao, conclusao, area FROM formacao_jovem WHERE jovem_id = ? ORDER BY id", "i", [$id]);
            $c['experiencias'] = jc_admin_select($conn, "SELECT titulo, local, periodo, descricao FROM experiencias_jovem WHERE jovem_id = ? ORDER BY id", "i", [$id]);
            $c['numeros'] = jc_admin_select_um($conn,
                "SELECT (SELECT COUNT(*) FROM candidaturas WHERE jovem_id = ?) AS candidaturas,
                        (SELECT COUNT(*) FROM favoritos WHERE jovem_id = ?) AS favoritos,
                        (SELECT COUNT(*) FROM denuncias WHERE jovem_id = ?) AS denuncias_feitas", "iii", [$id, $id, $id]);
        } elseif ($tipo === 'empresa') {
            $c = jc_admin_select_um($conn,
                "SELECT id, nome_empresa, email, cnpj, cidade, estado, area_atuacao, descricao, site_oficial, linkedin,
                        perfil_verificado, COALESCE(email_verificado, 0) AS email_verificado,
                        COALESCE(telefone_verificado, 0) AS telefone_verificado,
                        conta_status, DATE_FORMAT(conta_status_em, '%Y-%m-%d %H:%i:%s') AS conta_status_em,
                        DATE_FORMAT(criado_em, '%Y-%m-%d %H:%i:%s') AS criado_em
                 FROM empresas WHERE id = ? LIMIT 1", "i", [$id]);
            if (!$c) {
                return null;
            }
            $c['tipo'] = 'empresa';
            $c['nome_exibicao'] = $c['nome_empresa'];
            $c['numeros'] = jc_admin_select_um($conn,
                "SELECT COUNT(*) AS vagas,
                        COALESCE(SUM(moderacao_status IN ('aprovada','sinalizada')), 0) AS vagas_visiveis,
                        COALESCE(SUM(moderacao_status IN ('pendente','rejeitada','suspensa')), 0) AS vagas_fora,
                        (SELECT COUNT(*) FROM candidaturas c JOIN vagas v2 ON v2.id = c.vaga_id WHERE v2.empresa_id = ?) AS candidaturas,
                        (SELECT COUNT(*) FROM empresa_locais WHERE empresa_id = ?) AS locais
                 FROM vagas WHERE empresa_id = ?", "iii", [$id, $id, $id]);
            $c['vagas'] = jc_admin_select($conn,
                "SELECT id, titulo, moderacao_status, DATE_FORMAT(criado_em, '%Y-%m-%d %H:%i:%s') AS criado_em
                 FROM vagas WHERE empresa_id = ? ORDER BY criado_em DESC, id DESC LIMIT 20", "i", [$id]);
        } else {
            return null;
        }
        $c['itens'] = jc_admin_select($conn,
            "SELECT id, campo, origem, regras, risco, estado, decisao, ocorrencias,
                    DATE_FORMAT(criado_em, '%Y-%m-%d %H:%i:%s') AS criado_em
             FROM moderacao_itens WHERE recurso_tipo = ? AND recurso_id = ? ORDER BY id DESC LIMIT 20", "si", [$tipo, $id]);
        $c['historico'] = jc_admin_historico_recurso($conn, $tipo, $id);
        return $c;
    }

    /** Histórico de auditoria de um recurso (mais recente primeiro). */
    function jc_admin_historico_recurso(mysqli $conn, string $tipo, int $id, int $limite = 30): array {
        return jc_admin_select($conn,
            "SELECT a.id, DATE_FORMAT(a.criado_em, '%Y-%m-%d %H:%i:%s') AS criado_em, a.acao, a.resultado,
                    a.motivo_codigo, a.justificativa, a.campos, ad.nome AS admin_nome
             FROM admin_auditoria a LEFT JOIN administradores ad ON ad.id = a.admin_id
             WHERE a.recurso_tipo = ? AND a.recurso_id = ?
             ORDER BY a.id DESC LIMIT {$limite}", "si", [$tipo, $id]);
    }

    /**
     * Corrige campos não sensíveis de uma conta ou vaga com a MESMA validação
     * dos formulários públicos. Só grava o que mudou.
     * @return array{ok:bool, erros:array, campos:array}
     */
    function jc_admin_editar(mysqli $conn, array $admin, string $tipo, int $id, array $entrada, string $justificativa): array {
        $mapa = jc_admin_campos_editaveis($tipo);
        $tabela = ['jovem' => 'jovens', 'empresa' => 'empresas', 'vaga' => 'vagas'][$tipo] ?? null;
        if (!$mapa || $tabela === null) {
            return ['ok' => false, 'erros' => ['geral' => 'Tipo inválido.'], 'campos' => []];
        }
        $j = jc_validar_campo('admin.justificativa', $justificativa);
        if (!$j['ok']) {
            return ['ok' => false, 'erros' => ['justificativa' => $j['erro']], 'campos' => []];
        }
        $colunas = implode(', ', array_map(fn($c) => "`{$c}`", $mapa));
        $atual = jc_admin_select_um($conn, "SELECT {$colunas} FROM `{$tabela}` WHERE id = ? LIMIT 1", "i", [$id]);
        if (!$atual) {
            return ['ok' => false, 'erros' => ['geral' => 'Registro não encontrado.'], 'campos' => []];
        }
        $recebidos = [];
        foreach ($mapa as $chave => $coluna) {
            if (array_key_exists($chave, $entrada)) {
                $recebidos[$chave] = $entrada[$chave];
            }
        }
        $analise = jc_mod_analisar_campos($recebidos);   // estrutura + bloqueio de código
        if ($analise['erros']) {
            return ['ok' => false, 'erros' => $analise['erros'], 'campos' => []];
        }
        $mudou = [];
        foreach ($analise['valores'] as $chave => $valor) {
            $coluna = $mapa[$chave];
            $antes  = (string) ($atual[$coluna] ?? '');
            if ($chave === 'empresa.site' && $valor !== '') {
                $valor = jc_url_valida($valor) ?? $valor;
            }
            if ($valor !== $antes) {
                $mudou[$coluna] = ($valor === '' && !in_array($chave, ['jovem.nome', 'empresa.nome', 'vaga.titulo', 'vaga.descricao'], true)) ? null : $valor;
            }
        }
        if (!$mudou) {
            return ['ok' => true, 'erros' => [], 'campos' => []];
        }
        jc_admin_transacao($conn, function () use ($conn, $admin, $tipo, $tabela, $id, $mudou, $j) {
            $sets = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($mudou)));
            $stmt = $conn->prepare("UPDATE `{$tabela}` SET {$sets} WHERE id = ?");
            $valores = array_values($mudou);
            $valores[] = $id;
            $stmt->bind_param(str_repeat('s', count($mudou)) . 'i', ...$valores);
            $stmt->execute();
            $stmt->close();
            jc_admin_auditar($conn, intval($admin['id']), $tipo === 'vaga' ? 'vaga.editada' : 'conta.editada',
                             $tipo, $id, 'sucesso', 'correcao', $j['valor'], array_keys($mudou));
        });
        return ['ok' => true, 'erros' => [], 'campos' => array_keys($mudou)];
    }

    /**
     * Suspende ou reativa uma conta (jovem|empresa). Nada é apagado.
     * @return array{ok:bool, erro:?string}
     */
    function jc_admin_conta_status(mysqli $conn, array $admin, string $tipo, int $id, string $novo,
                                   string $motivo, string $justificativa): array {
        $tabela = ['jovem' => 'jovens', 'empresa' => 'empresas'][$tipo] ?? null;
        if ($tabela === null || !in_array($novo, ['ativa', 'suspensa'], true)) {
            return ['ok' => false, 'erro' => 'Operação inválida.'];
        }
        $motivos = $novo === 'suspensa' ? jc_admin_motivos_suspensao() : jc_admin_motivos_reativacao();
        if (!isset($motivos[$motivo])) {
            return ['ok' => false, 'erro' => 'Escolha o motivo.'];
        }
        $j = jc_validar_campo('admin.justificativa', $justificativa);
        if (!$j['ok']) {
            return ['ok' => false, 'erro' => $j['erro']];
        }
        try {
            jc_admin_transacao($conn, function () use ($conn, $admin, $tipo, $tabela, $id, $novo, $motivo, $j) {
                $atual = jc_admin_select_um($conn, "SELECT conta_status FROM `{$tabela}` WHERE id = ? FOR UPDATE", "i", [$id]);
                if (!$atual) {
                    throw new DomainException('Conta não encontrada.');
                }
                if ($atual['conta_status'] === $novo) {
                    throw new DomainException($novo === 'suspensa' ? 'A conta já está suspensa.' : 'A conta já está ativa.');
                }
                $stmt = $conn->prepare("UPDATE `{$tabela}` SET conta_status = ?, conta_status_em = NOW() WHERE id = ?");
                $stmt->bind_param("si", $novo, $id);
                $stmt->execute();
                $stmt->close();
                jc_admin_auditar($conn, intval($admin['id']), $novo === 'suspensa' ? 'conta.suspensa' : 'conta.reativada',
                                 $tipo, $id, 'sucesso', $motivo, $j['valor'], ['conta_status']);
            });
        } catch (DomainException $e) {
            return ['ok' => false, 'erro' => $e->getMessage()];
        }
        return ['ok' => true, 'erro' => null];
    }

    // ── Vagas ───────────────────────────────────────────────────────────

    /** Filtros: q, estado, empresa (id), area, empresa_suspensa (1), de, ate. */
    function jc_admin_vagas_buscar(mysqli $conn, array $f, int $pagina, int $por): array {
        $w = [];
        $tipos = '';
        $args = [];
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            if (ctype_digit($q)) {
                $w[] = 'v.id = ?';
                $tipos .= 'i';
                $args[] = intval($q);
            } else {
                $w[] = '(v.titulo LIKE ? OR e.nome_empresa LIKE ?)';
                $tipos .= 'ss';
                $args[] = jc_admin_like($q);
                $args[] = jc_admin_like($q);
            }
        }
        if (isset(jc_admin_estados_vaga()[$f['estado'] ?? ''])) {
            $w[] = 'v.moderacao_status = ?';
            $tipos .= 's';
            $args[] = $f['estado'];
        }
        if (intval($f['empresa'] ?? 0) > 0) {
            $w[] = 'v.empresa_id = ?';
            $tipos .= 'i';
            $args[] = intval($f['empresa']);
        }
        if (!empty($f['area']) && is_string($f['area'])) {
            $w[] = 'v.area = ?';
            $tipos .= 's';
            $args[] = mb_substr($f['area'], 0, 100);
        }
        if (!empty($f['empresa_suspensa'])) {
            $w[] = "e.conta_status = 'suspensa'";
        }
        if ($de = jc_admin_data_param($f['de'] ?? null)) {
            $w[] = 'v.criado_em >= ?';
            $tipos .= 's';
            $args[] = $de . ' 00:00:00';
        }
        if ($ate = jc_admin_data_param($f['ate'] ?? null)) {
            $w[] = 'v.criado_em <= ?';
            $tipos .= 's';
            $args[] = $ate . ' 23:59:59';
        }
        $where = $w ? ' WHERE ' . implode(' AND ', $w) : '';
        $total = intval(jc_admin_select_um($conn,
            "SELECT COUNT(*) AS n FROM vagas v JOIN empresas e ON e.id = v.empresa_id{$where}", $tipos, $args)['n'] ?? 0);
        $offset = max(0, ($pagina - 1) * $por);
        $linhas = jc_admin_select($conn,
            "SELECT v.id, v.titulo, v.cidade, v.estado, v.area, v.tipo_contrato, v.moderacao_status,
                    DATE_FORMAT(v.criado_em, '%Y-%m-%d %H:%i:%s') AS criado_em,
                    e.id AS empresa_id, e.nome_empresa, e.conta_status AS empresa_status,
                    (SELECT COUNT(*) FROM candidaturas c WHERE c.vaga_id = v.id) AS candidaturas,
                    (SELECT COUNT(*) FROM moderacao_itens m WHERE m.recurso_tipo = 'vaga' AND m.recurso_id = v.id
                        AND m.estado = 'pendente') AS itens_abertos
             FROM vagas v JOIN empresas e ON e.id = v.empresa_id{$where}
             ORDER BY v.criado_em DESC, v.id DESC LIMIT {$por} OFFSET {$offset}", $tipos, $args);
        return ['total' => $total, 'linhas' => $linhas];
    }

    /** Áreas existentes (para o filtro). */
    function jc_admin_vagas_areas(mysqli $conn): array {
        return array_column(jc_admin_select($conn,
            "SELECT DISTINCT area FROM vagas WHERE area IS NOT NULL AND area <> '' ORDER BY area LIMIT 100"), 'area');
    }

    function jc_admin_vaga_carregar(mysqli $conn, int $id): ?array {
        $v = jc_admin_select_um($conn,
            "SELECT v.id, v.titulo, v.descricao, v.cidade, v.estado, v.salario, v.tipo_contrato, v.carga_horaria, v.area,
                    v.local_trabalho, v.local_id, v.moderacao_status, v.moderacao_motivo,
                    DATE_FORMAT(v.moderacao_em, '%Y-%m-%d %H:%i:%s') AS moderacao_em,
                    DATE_FORMAT(v.criado_em, '%Y-%m-%d %H:%i:%s') AS criado_em,
                    DATE_FORMAT(v.atualizado_em, '%Y-%m-%d %H:%i:%s') AS atualizado_em,
                    e.id AS empresa_id, e.nome_empresa, e.conta_status AS empresa_status,
                    l.nome AS local_nome, l.tipo AS local_tipo, l.cidade AS local_cidade, l.estado AS local_estado
             FROM vagas v
             JOIN empresas e ON e.id = v.empresa_id
             LEFT JOIN empresa_locais l ON l.id = v.local_id
             WHERE v.id = ? LIMIT 1", "i", [$id]);
        if (!$v) {
            return null;
        }
        $v['requisitos'] = array_column(jc_admin_select($conn, "SELECT requisito FROM requisitos_vaga WHERE vaga_id = ? ORDER BY id", "i", [$id]), 'requisito');
        $v['beneficios'] = array_column(jc_admin_select($conn, "SELECT beneficio FROM beneficios_vaga WHERE vaga_id = ? ORDER BY id", "i", [$id]), 'beneficio');
        $v['numeros'] = jc_admin_select_um($conn,
            "SELECT (SELECT COUNT(*) FROM candidaturas WHERE vaga_id = ?) AS candidaturas,
                    (SELECT COUNT(*) FROM favoritos WHERE vaga_id = ?) AS favoritos,
                    (SELECT COUNT(*) FROM denuncias WHERE vaga_id = ?) AS denuncias", "iii", [$id, $id, $id]);
        $v['itens'] = jc_admin_select($conn,
            "SELECT id, campo, origem, regras, risco, estado, decisao, ocorrencias,
                    DATE_FORMAT(criado_em, '%Y-%m-%d %H:%i:%s') AS criado_em
             FROM moderacao_itens WHERE recurso_tipo = 'vaga' AND recurso_id = ? ORDER BY id DESC LIMIT 30", "i", [$id]);
        $v['denuncias'] = jc_admin_select($conn,
            "SELECT d.id, d.motivo, d.detalhe, d.jovem_id, DATE_FORMAT(d.criado_em, '%Y-%m-%d %H:%i:%s') AS criado_em
             FROM denuncias d WHERE d.vaga_id = ? ORDER BY d.id DESC LIMIT 30", "i", [$id]);
        $v['historico'] = jc_admin_historico_recurso($conn, 'vaga', $id);
        return $v;
    }

    /**
     * Decisão de moderação sobre uma vaga. Separada da publicação: só muda
     * vagas.moderacao_status (e motivo/data). Nada volta ao ar sozinho.
     * @return array{ok:bool, erro:?string, de:?string, para:?string, itens:int}
     */
    function jc_admin_vaga_moderar(mysqli $conn, array $admin, int $vaga_id, string $acao, string $motivo,
                                   string $justificativa, bool $resolver_itens = true): array {
        $acoes = jc_admin_vaga_acoes();
        if (!isset($acoes[$acao])) {
            return ['ok' => false, 'erro' => 'Ação inválida.', 'de' => null, 'para' => null, 'itens' => 0];
        }
        $motivos = $acao === 'aprovar' ? jc_admin_motivos_aprovacao() : jc_moderacao_motivos_vaga();
        if (!isset($motivos[$motivo])) {
            return ['ok' => false, 'erro' => $acao === 'aprovar'
                        ? 'Para aprovar, escolha um motivo do grupo "Para aprovar".'
                        : 'Escolha um motivo do grupo de revisão, rejeição ou suspensão (é o texto que a empresa vai ver).',
                    'de' => null, 'para' => null, 'itens' => 0];
        }
        $j = jc_validar_campo('admin.justificativa', $justificativa);
        if (!$j['ok']) {
            return ['ok' => false, 'erro' => $j['erro'], 'de' => null, 'para' => null, 'itens' => 0];
        }
        $para = $acoes[$acao]['para'];
        try {
            return jc_admin_transacao($conn, function () use ($conn, $admin, $vaga_id, $acao, $acoes, $para, $motivo, $j, $resolver_itens) {
                $atual = jc_admin_select_um($conn, "SELECT moderacao_status FROM vagas WHERE id = ? FOR UPDATE", "i", [$vaga_id]);
                if (!$atual) {
                    throw new DomainException('Vaga não encontrada.');
                }
                $de = (string) $atual['moderacao_status'];
                if (!in_array($de, $acoes[$acao]['de'], true)) {
                    throw new DomainException('Essa ação não se aplica a uma vaga com o estado atual ('
                        . (jc_admin_estados_vaga()[$de] ?? $de) . ').');
                }
                $motivo_vaga = $acao === 'aprovar' ? null : $motivo;
                $stmt = $conn->prepare("UPDATE vagas SET moderacao_status = ?, moderacao_motivo = ?, moderacao_em = NOW() WHERE id = ?");
                $stmt->bind_param("ssi", $para, $motivo_vaga, $vaga_id);
                $stmt->execute();
                $stmt->close();
                $itens = 0;
                if ($resolver_itens) {
                    $decisao = 'vaga_' . $para;
                    $admin_id = intval($admin['id']);
                    $stmt = $conn->prepare(
                        "UPDATE moderacao_itens SET estado = 'resolvido', decisao = ?, justificativa = ?, decidido_por = ?, decidido_em = NOW()
                         WHERE recurso_tipo = 'vaga' AND recurso_id = ? AND estado = 'pendente'"
                    );
                    $just = jc_admin_mascarar_texto($j['valor']);
                    $stmt->bind_param("ssii", $decisao, $just, $admin_id, $vaga_id);
                    $stmt->execute();
                    $itens = $stmt->affected_rows;
                    $stmt->close();
                }
                jc_admin_auditar($conn, intval($admin['id']), 'vaga.moderada', 'vaga', $vaga_id, 'sucesso',
                                 $acao . '.' . $motivo, $j['valor'], ['moderacao_status']);
                return ['ok' => true, 'erro' => null, 'de' => $de, 'para' => $para, 'itens' => $itens];
            });
        } catch (DomainException $e) {
            return ['ok' => false, 'erro' => $e->getMessage(), 'de' => null, 'para' => null, 'itens' => 0];
        }
    }

    // ── Fila de moderação ───────────────────────────────────────────────

    /** Filtros: estado (pendente|resolvido|todos), risco, tipo, origem, campo. */
    function jc_admin_fila_buscar(mysqli $conn, array $f, int $pagina, int $por): array {
        $w = [];
        $tipos = '';
        $args = [];
        $estado = $f['estado'] ?? 'pendente';
        if (in_array($estado, ['pendente', 'resolvido'], true)) {
            $w[] = 'm.estado = ?';
            $tipos .= 's';
            $args[] = $estado;
        }
        if (in_array($f['risco'] ?? '', ['baixo', 'medio', 'alto'], true)) {
            $w[] = 'm.risco = ?';
            $tipos .= 's';
            $args[] = $f['risco'];
        }
        if (in_array($f['tipo'] ?? '', ['vaga', 'jovem', 'empresa'], true)) {
            $w[] = 'm.recurso_tipo = ?';
            $tipos .= 's';
            $args[] = $f['tipo'];
        }
        if (in_array($f['origem'] ?? '', ['regra', 'denuncia'], true)) {
            $w[] = 'm.origem = ?';
            $tipos .= 's';
            $args[] = $f['origem'];
        }
        $where = $w ? ' WHERE ' . implode(' AND ', $w) : '';
        $total = intval(jc_admin_select_um($conn, "SELECT COUNT(*) AS n FROM moderacao_itens m{$where}", $tipos, $args)['n'] ?? 0);
        $offset = max(0, ($pagina - 1) * $por);
        $linhas = jc_admin_select($conn,
            "SELECT m.id, m.recurso_tipo, m.recurso_id, m.campo, m.origem, m.regras, m.risco, m.estado, m.decisao,
                    m.ocorrencias, DATE_FORMAT(m.criado_em, '%Y-%m-%d %H:%i:%s') AS criado_em,
                    DATE_FORMAT(m.atualizado_em, '%Y-%m-%d %H:%i:%s') AS atualizado_em,
                    COALESCE(v.titulo, j.nome, e.nome_empresa) AS recurso_nome,
                    COALESCE(v.moderacao_status, j.conta_status, e.conta_status) AS recurso_estado
             FROM moderacao_itens m
             LEFT JOIN vagas v    ON m.recurso_tipo = 'vaga'    AND v.id = m.recurso_id
             LEFT JOIN jovens j   ON m.recurso_tipo = 'jovem'   AND j.id = m.recurso_id
             LEFT JOIN empresas e ON m.recurso_tipo = 'empresa' AND e.id = m.recurso_id
             {$where}
             ORDER BY (m.estado = 'pendente') DESC,
                      CASE m.risco WHEN 'alto' THEN 0 WHEN 'medio' THEN 1 ELSE 2 END,
                      m.criado_em ASC, m.id ASC
             LIMIT {$por} OFFSET {$offset}", $tipos, $args);
        return ['total' => $total, 'linhas' => $linhas];
    }

    function jc_admin_item_carregar(mysqli $conn, int $id): ?array {
        $i = jc_admin_select_um($conn,
            "SELECT m.*, DATE_FORMAT(m.criado_em, '%Y-%m-%d %H:%i:%s') AS criado_em_fmt,
                    DATE_FORMAT(m.atualizado_em, '%Y-%m-%d %H:%i:%s') AS atualizado_em_fmt,
                    DATE_FORMAT(m.decidido_em, '%Y-%m-%d %H:%i:%s') AS decidido_em_fmt,
                    ad.nome AS decidido_por_nome
             FROM moderacao_itens m LEFT JOIN administradores ad ON ad.id = m.decidido_por
             WHERE m.id = ? LIMIT 1", "i", [$id]);
        if (!$i) {
            return null;
        }
        $i['conteudo'] = jc_admin_conteudo_campo($conn, (string) $i['recurso_tipo'], intval($i['recurso_id']), (string) $i['campo']);
        $i['conteudo_igual'] = null;
        if ($i['conteudo'] !== null && !empty($i['conteudo_hash'])) {
            $i['conteudo_igual'] = false;
            foreach ($i['conteudo'] as $texto) {
                if (hash_equals((string) $i['conteudo_hash'], hash('sha256', (string) $texto))) {
                    $i['conteudo_igual'] = true;
                    break;
                }
            }
        }
        $i['denuncias'] = [];
        if ($i['origem'] === 'denuncia' && $i['recurso_tipo'] === 'vaga') {
            $i['denuncias'] = jc_admin_select($conn,
                "SELECT id, motivo, detalhe, jovem_id, DATE_FORMAT(criado_em, '%Y-%m-%d %H:%i:%s') AS criado_em
                 FROM denuncias WHERE vaga_id = ? ORDER BY id DESC LIMIT 30", "i", [intval($i['recurso_id'])]);
        }
        if ($i['recurso_tipo'] === 'vaga') {
            $i['vaga'] = jc_admin_select_um($conn,
                "SELECT v.id, v.titulo, v.moderacao_status, e.nome_empresa, e.id AS empresa_id
                 FROM vagas v JOIN empresas e ON e.id = v.empresa_id WHERE v.id = ?", "i", [intval($i['recurso_id'])]);
        }
        return $i;
    }

    /**
     * Conteúdo ATUAL do campo sinalizado, lido do registro original (a fila
     * não guarda cópia). @return string[]|null  null = campo sem leitura
     */
    function jc_admin_conteudo_campo(mysqli $conn, string $tipo, int $id, string $campo): ?array {
        $simples = [
            'vaga.titulo' => ['vagas', 'titulo'], 'vaga.descricao' => ['vagas', 'descricao'],
            'vaga.salario' => ['vagas', 'salario'], 'vaga.carga_horaria' => ['vagas', 'carga_horaria'],
            'jovem.nome' => ['jovens', 'nome'], 'jovem.resumo' => ['jovens', 'resumo_profissional'],
            'jovem.area_interesse' => ['jovens', 'area_interesse'],
            'empresa.nome' => ['empresas', 'nome_empresa'], 'empresa.descricao' => ['empresas', 'descricao'],
            'empresa.area_atuacao' => ['empresas', 'area_atuacao'], 'empresa.site' => ['empresas', 'site_oficial'],
            'empresa.linkedin' => ['empresas', 'linkedin'],
        ];
        $listas = [
            'vaga.requisito' => ['requisitos_vaga', 'requisito', 'vaga_id'],
            'vaga.beneficio' => ['beneficios_vaga', 'beneficio', 'vaga_id'],
            'jovem.habilidade' => ['habilidades_jovem', 'habilidade', 'jovem_id'],
            'jovem.formacao_titulo' => ['formacao_jovem', 'titulo', 'jovem_id'],
            'jovem.formacao_instituicao' => ['formacao_jovem', 'instituicao', 'jovem_id'],
            'jovem.formacao_area' => ['formacao_jovem', 'area', 'jovem_id'],
            'jovem.exp_titulo' => ['experiencias_jovem', 'titulo', 'jovem_id'],
            'jovem.exp_local' => ['experiencias_jovem', 'local', 'jovem_id'],
            'jovem.exp_descricao' => ['experiencias_jovem', 'descricao', 'jovem_id'],
        ];
        $esperado = explode('.', $campo)[0];
        if ($esperado !== $tipo) {
            return null;
        }
        if ($campo === 'jovem.formacao_livre') {   // texto de "Outro": formação principal ou adicional
            $l = jc_admin_select_um($conn, "SELECT formacao AS v FROM jovens WHERE id = ? LIMIT 1", "i", [$id]);
            $extras = array_column(jc_admin_select($conn, "SELECT titulo AS v FROM formacao_jovem WHERE jovem_id = ? ORDER BY id LIMIT 50", "i", [$id]), 'v');
            return array_values(array_filter(array_map('strval', array_merge($l ? [$l['v']] : [], $extras)), fn($t) => $t !== ''));
        }
        if (isset($simples[$campo])) {
            [$t, $c] = $simples[$campo];
            $l = jc_admin_select_um($conn, "SELECT `{$c}` AS v FROM `{$t}` WHERE id = ? LIMIT 1", "i", [$id]);
            return $l ? [(string) ($l['v'] ?? '')] : [];
        }
        if (isset($listas[$campo])) {
            [$t, $c, $fk] = $listas[$campo];
            return array_map('strval', array_column(
                jc_admin_select($conn, "SELECT `{$c}` AS v FROM `{$t}` WHERE `{$fk}` = ? ORDER BY id LIMIT 50", "i", [$id]), 'v'));
        }
        return null;
    }

    /**
     * Registra a decisão sobre um item da fila. Para itens de VAGA, pode
     * aplicar junto uma ação de moderação (aprovar/revisar/rejeitar/suspender).
     * @return array{ok:bool, erro:?string}
     */
    function jc_admin_item_decidir(mysqli $conn, array $admin, int $item_id, string $decisao, string $justificativa,
                                   ?string $acao_vaga = null, ?string $motivo_vaga = null): array {
        if (!isset(jc_admin_decisoes_item()[$decisao])) {
            return ['ok' => false, 'erro' => 'Escolha a decisão.'];
        }
        $j = jc_validar_campo('admin.justificativa', $justificativa);
        if (!$j['ok']) {
            return ['ok' => false, 'erro' => $j['erro']];
        }
        try {
            jc_admin_transacao($conn, function () use ($conn, $admin, $item_id, $decisao, $j, $acao_vaga, $motivo_vaga) {
                $item = jc_admin_select_um($conn, "SELECT id, recurso_tipo, recurso_id, estado FROM moderacao_itens WHERE id = ? FOR UPDATE", "i", [$item_id]);
                if (!$item) {
                    throw new DomainException('Item não encontrado.');
                }
                if ($item['estado'] !== 'pendente') {
                    throw new DomainException('Este item já foi decidido.');
                }
                if ($acao_vaga !== null && $acao_vaga !== '') {
                    if ($item['recurso_tipo'] !== 'vaga') {
                        throw new DomainException('A ação de moderação de vaga só vale para itens de vaga.');
                    }
                    $r = jc_admin_vaga_moderar($conn, $admin, intval($item['recurso_id']), $acao_vaga, (string) $motivo_vaga,
                                               $j['valor'], false);
                    if (!$r['ok']) {
                        throw new DomainException((string) $r['erro']);
                    }
                }
                $admin_id = intval($admin['id']);
                $just = jc_admin_mascarar_texto($j['valor']);
                $stmt = $conn->prepare(
                    "UPDATE moderacao_itens SET estado = 'resolvido', decisao = ?, justificativa = ?, decidido_por = ?, decidido_em = NOW()
                     WHERE id = ? AND estado = 'pendente'"
                );
                $stmt->bind_param("ssii", $decisao, $just, $admin_id, $item_id);
                $stmt->execute();
                $stmt->close();
                jc_admin_auditar($conn, $admin_id, 'moderacao.decidida', 'moderacao_item', $item_id, 'sucesso',
                                 $decisao, $j['valor'], ['estado', 'decisao']);
            });
        } catch (DomainException $e) {
            return ['ok' => false, 'erro' => $e->getMessage()];
        }
        return ['ok' => true, 'erro' => null];
    }

    // ── Histórico ───────────────────────────────────────────────────────

    /** Filtros: admin (id), acao, tipo, recurso (id), resultado, de, ate. */
    function jc_admin_auditoria_buscar(mysqli $conn, array $f, int $pagina, int $por): array {
        $w = [];
        $tipos = '';
        $args = [];
        if (intval($f['admin'] ?? 0) > 0) {
            $w[] = 'a.admin_id = ?';
            $tipos .= 'i';
            $args[] = intval($f['admin']);
        }
        if (isset(jc_admin_acoes()[$f['acao'] ?? ''])) {
            $w[] = 'a.acao = ?';
            $tipos .= 's';
            $args[] = $f['acao'];
        }
        if (in_array($f['tipo'] ?? '', ['jovem', 'empresa', 'vaga', 'moderacao_item', 'administrador'], true)) {
            $w[] = 'a.recurso_tipo = ?';
            $tipos .= 's';
            $args[] = $f['tipo'];
        }
        if (intval($f['recurso'] ?? 0) > 0) {
            $w[] = 'a.recurso_id = ?';
            $tipos .= 'i';
            $args[] = intval($f['recurso']);
        }
        if (in_array($f['resultado'] ?? '', ['sucesso', 'falha', 'negado'], true)) {
            $w[] = 'a.resultado = ?';
            $tipos .= 's';
            $args[] = $f['resultado'];
        }
        if ($de = jc_admin_data_param($f['de'] ?? null)) {
            $w[] = 'a.criado_em >= ?';
            $tipos .= 's';
            $args[] = $de . ' 00:00:00';
        }
        if ($ate = jc_admin_data_param($f['ate'] ?? null)) {
            $w[] = 'a.criado_em <= ?';
            $tipos .= 's';
            $args[] = $ate . ' 23:59:59';
        }
        $where = $w ? ' WHERE ' . implode(' AND ', $w) : '';
        $total = intval(jc_admin_select_um($conn, "SELECT COUNT(*) AS n FROM admin_auditoria a{$where}", $tipos, $args)['n'] ?? 0);
        $offset = max(0, ($pagina - 1) * $por);
        $linhas = jc_admin_select($conn,
            "SELECT a.id, DATE_FORMAT(a.criado_em, '%Y-%m-%d %H:%i:%s') AS criado_em, a.admin_id, ad.nome AS admin_nome,
                    a.acao, a.recurso_tipo, a.recurso_id, a.resultado, a.motivo_codigo, a.justificativa, a.campos,
                    a.req_id, a.ip_anon, LEFT(a.hash, 12) AS hash_curto
             FROM admin_auditoria a LEFT JOIN administradores ad ON ad.id = a.admin_id
             {$where} ORDER BY a.id DESC LIMIT {$por} OFFSET {$offset}", $tipos, $args);
        return ['total' => $total, 'linhas' => $linhas];
    }

    function jc_admin_lista_admins(mysqli $conn): array {
        return jc_admin_select($conn, "SELECT id, nome FROM administradores ORDER BY nome");
    }

    // ── Eventos de segurança (logs/seguranca.log) ───────────────────────

    /**
     * Últimos eventos de logs/seguranca.log (já sem dados sensíveis: o logger
     * remove senhas, tokens, e-mails, telefones e CPF antes de gravar).
     */
    function jc_admin_eventos_seguranca(int $limite = 100, string $prefixo = ''): array {
        if (!function_exists('jc_log_dir')) {
            return [];
        }
        $arquivo = jc_log_dir() . '/seguranca.log';
        if (!is_file($arquivo) || !is_readable($arquivo)) {
            return [];
        }
        $tam = filesize($arquivo);
        $ler = min($tam, 512 * 1024);   // só o final do arquivo
        $fh = @fopen($arquivo, 'rb');
        if (!$fh) {
            return [];
        }
        fseek($fh, -$ler, SEEK_END);
        $bloco = (string) fread($fh, $ler);
        fclose($fh);
        $linhas = array_reverse(array_filter(explode("\n", $bloco)));
        $eventos = [];
        foreach ($linhas as $l) {
            $e = json_decode($l, true);
            if (!is_array($e) || !isset($e['evento'])) {
                continue;
            }
            if ($prefixo !== '' && !str_starts_with((string) $e['evento'], $prefixo)) {
                continue;
            }
            $eventos[] = [
                'ts'      => (string) ($e['ts'] ?? ''),
                'nivel'   => (string) ($e['nivel'] ?? ''),
                'evento'  => (string) $e['evento'],
                'usuario' => (string) ($e['usuario'] ?? ''),
                'pagina'  => (string) ($e['pagina'] ?? ''),
                'ip'      => (string) ($e['ip'] ?? ''),
                'req'     => (string) ($e['req'] ?? ''),
                'dados'   => is_array($e['dados'] ?? null) ? $e['dados'] : [],
            ];
            if (count($eventos) >= $limite) {
                break;
            }
        }
        return $eventos;
    }
}
