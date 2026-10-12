<?php
/* ============================================================
   includes/admin/auditoria.php — histórico de ações administrativas
   (Stage 7)

   Tabela admin_auditoria (somente inclusão). Cada linha guarda:
   quem (admin_id), o quê (acao), em quê (recurso_tipo + recurso_id),
   quando (criado_em, relógio do BANCO), resultado (sucesso | falha |
   negado), código do motivo, justificativa (mascarada), NOMES dos
   campos alterados (nunca os valores), identificador da requisição
   (req_id — o mesmo dos logs app.log/seguranca.log) e IP anonimizado.

   Integridade: cada linha tem hash = SHA-256(hash da linha anterior +
   campos desta linha). Alterar ou apagar uma linha do meio quebra a
   cadeia; jc_admin_auditoria_verificar() aponta a primeira linha
   inconsistente. O espelho em logs/seguranca.log (gravado só depois
   do COMMIT) leva o id e o começo do hash de cada linha;
   jc_admin_auditoria_conferir_log() compara os dois e aponta registros
   removidos — inclusive no FIM da tabela, que a cadeia sozinha não
   denuncia. A Central não tem nenhuma tela ou ação para editar ou
   apagar o histórico. (Para proteção máxima, o usuário do banco usado
   pelo site pode ter só SELECT/INSERT nessa tabela — ver a documentação.)

   Ordem: a cadeia exige uma linha por vez. jc_admin_transacao() pega
   uma trava nomeada do banco (GET_LOCK) ANTES da transação e só a
   solta depois do COMMIT; assim a ação e a sua linha de auditoria
   entram juntas (ou nenhuma das duas).
   ============================================================ */

if (!function_exists('jc_admin_auditar')) {

    define('JC_AUDITORIA_GENESE', str_repeat('0', 64));

    /** Trava da cadeia (reentrante dentro da mesma requisição). */
    function jc_admin_trava(mysqli $conn, bool $pegar): bool {
        static $nivel = 0;
        if ($pegar) {
            if ($nivel === 0) {
                $r = $conn->query("SELECT GET_LOCK('jc_admin_auditoria', 10) AS ok")->fetch_assoc();
                if (intval($r['ok'] ?? 0) !== 1) {
                    throw new RuntimeException('Não foi possível obter a trava do histórico.');
                }
            }
            $nivel++;
            return true;
        }
        if ($nivel > 0) {
            $nivel--;
            if ($nivel === 0) {
                $conn->query("SELECT RELEASE_LOCK('jc_admin_auditoria')");
            }
        }
        return true;
    }

    /**
     * Executa $acao numa transação junto com a(s) linha(s) de auditoria.
     * Se algo falhar, nada é gravado (rollback) e a exceção sobe.
     */
    function jc_admin_transacao(mysqli $conn, callable $acao) {
        if (jc_admin_transacao_nivel() > 0) {
            return $acao();   // já dentro de uma transação desta requisição: participa dela
        }
        jc_admin_trava($conn, true);
        try {
            $conn->begin_transaction();
            jc_admin_transacao_nivel(1);
            try {
                $resultado = $acao();
                $conn->commit();
            } catch (Throwable $e) {
                $conn->rollback();
                jc_admin_espelho('descartar');   // nada foi gravado: o log não pode dizer o contrário
                throw $e;
            } finally {
                jc_admin_transacao_nivel(-1);
            }
        } finally {
            jc_admin_trava($conn, false);
        }
        jc_admin_gravou(true);
        jc_admin_espelho('gravar');   // só depois do COMMIT
        return $resultado;
    }

    /** Alguma transação administrativa foi confirmada (COMMIT) nesta requisição? */
    function jc_admin_gravou(bool $marcar = false): bool {
        static $gravou = false;
        if ($marcar) {
            $gravou = true;
        }
        return $gravou;
    }

    /**
     * Espelho do histórico em logs/seguranca.log. Dentro de uma transação, as
     * linhas esperam o COMMIT ('gravar'); se houver ROLLBACK, são descartadas.
     * @param string $op 'adicionar' | 'gravar' | 'descartar'
     */
    function jc_admin_espelho(string $op, array $item = []): void {
        static $pendentes = [];
        if ($op === 'adicionar') {
            if (jc_admin_transacao_nivel() > 0) {
                $pendentes[] = $item;
                return;
            }
            $lista = [$item];
        } elseif ($op === 'gravar') {
            $lista = $pendentes;
            $pendentes = [];
        } else {
            $pendentes = [];
            return;
        }
        if (function_exists('jc_log_seguranca')) {
            foreach ($lista as $i) {
                jc_log_seguranca($i['evento'], $i['nivel'], $i['dados']);
            }
        }
    }

    /** Contador de transações abertas por jc_admin_transacao() nesta requisição. */
    function jc_admin_transacao_nivel(int $delta = 0): int {
        static $n = 0;
        $n = max(0, $n + $delta);
        return $n;
    }

    /** Justificativa sem dados pessoais óbvios, sem controles, até 500 caracteres. */
    function jc_admin_mascarar_texto(?string $t): ?string {
        if ($t === null) {
            return null;
        }
        $t = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', ' ', $t) ?? '';
        $t = preg_replace('/[^\s@"\'<>]+@[^\s@"\'<>]+\.[^\s@"\'<>]+/u', '[e-mail]', $t) ?? '';
        $t = preg_replace('/\d[\d.\-\/ ]{7,}\d/u', '[número]', $t) ?? '';
        $t = trim($t);
        return $t === '' ? null : mb_substr($t, 0, 500, 'UTF-8');
    }

    /** Hash de uma linha (formato estável: JSON dos campos na ordem fixa). */
    function jc_admin_auditoria_hash(string $anterior, array $l): string {
        $dados = [
            $anterior,
            (string) $l['criado_em'],
            $l['admin_id'] === null ? null : intval($l['admin_id']),
            (string) $l['acao'],
            $l['recurso_tipo'] === null ? null : (string) $l['recurso_tipo'],
            $l['recurso_id'] === null ? null : intval($l['recurso_id']),
            (string) $l['resultado'],
            $l['motivo_codigo'] === null ? null : (string) $l['motivo_codigo'],
            $l['justificativa'] === null ? null : (string) $l['justificativa'],
            $l['campos'] === null ? null : (string) $l['campos'],
            $l['req_id'] === null ? null : (string) $l['req_id'],
            $l['ip_anon'] === null ? null : (string) $l['ip_anon'],
        ];
        return hash('sha256', json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Registra uma ação. Dentro de jc_admin_transacao() entra na mesma
     * transação; fora dela, grava sozinha (com a trava).
     * @param array $campos NOMES dos campos alterados (os valores nunca são gravados)
     * @return bool false se não foi possível gravar (o erro vai para o log)
     */
    function jc_admin_auditar(mysqli $conn, ?int $admin_id, string $acao, ?string $recurso_tipo, ?int $recurso_id,
                              string $resultado, ?string $motivo = null, ?string $justificativa = null, array $campos = []): bool {
        $acao      = substr(preg_replace('/[^a-z0-9_.\-]/i', '', $acao) ?: 'acao', 0, 60);
        $resultado = in_array($resultado, ['sucesso', 'falha', 'negado'], true) ? $resultado : 'falha';
        $motivo    = $motivo === null ? null : substr(preg_replace('/[^a-z0-9_.\-]/i', '', $motivo) ?? '', 0, 60);
        $campos_txt = $campos ? substr(implode(',', array_map(fn($c) => preg_replace('/[^a-z0-9_.]/i', '', (string) $c), $campos)), 0, 300) : null;
        $linha = [
            'admin_id'      => $admin_id,
            'acao'          => $acao,
            'recurso_tipo'  => $recurso_tipo === null ? null : substr(preg_replace('/[^a-z_]/', '', $recurso_tipo) ?? '', 0, 30),
            'recurso_id'    => $recurso_id,
            'resultado'     => $resultado,
            'motivo_codigo' => $motivo,
            'justificativa' => jc_admin_mascarar_texto($justificativa),
            'campos'        => $campos_txt,
            'req_id'        => function_exists('jc_req_id') ? jc_req_id() : null,
            'ip_anon'       => (PHP_SAPI === 'cli') ? 'cli' : (function_exists('jc_log_ip') ? jc_log_ip() : null),
        ];
        $hash = null;
        try {
            jc_admin_trava($conn, true);
            try {
                $linha['criado_em'] = (string) $conn->query("SELECT DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s') AS agora")->fetch_assoc()['agora'];
                $ult = $conn->query("SELECT hash FROM admin_auditoria ORDER BY id DESC LIMIT 1")->fetch_assoc();
                $anterior = $ult['hash'] ?? JC_AUDITORIA_GENESE;
                $hash = jc_admin_auditoria_hash($anterior, $linha);
                $stmt = $conn->prepare(
                    "INSERT INTO admin_auditoria
                        (criado_em, admin_id, acao, recurso_tipo, recurso_id, resultado, motivo_codigo,
                         justificativa, campos, req_id, ip_anon, hash_anterior, hash)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param("sississssssss", $linha['criado_em'], $linha['admin_id'], $linha['acao'],
                    $linha['recurso_tipo'], $linha['recurso_id'], $linha['resultado'], $linha['motivo_codigo'],
                    $linha['justificativa'], $linha['campos'], $linha['req_id'], $linha['ip_anon'], $anterior, $hash);
                $stmt->execute();
                $registro = intval($stmt->insert_id);
                $stmt->close();
            } finally {
                jc_admin_trava($conn, false);
            }
        } catch (Throwable $e) {
            if (function_exists('jc_log_excecao')) {
                jc_log_excecao($e, 'admin.auditoria_erro');
            }
            // dentro de uma transação: a falha precisa desfazer a ação também
            if (jc_admin_em_transacao($conn)) {
                throw $e;
            }
            return false;
        }
        jc_admin_espelho('adicionar', [
            'evento' => 'admin.' . $acao,
            'nivel'  => $resultado === 'sucesso' ? 'info' : 'aviso',
            'dados'  => [
                'admin'     => $admin_id,
                'recurso'   => $linha['recurso_tipo'],
                'id'        => $recurso_id,
                'resultado' => $resultado,
                'motivo'    => $motivo,
                'campos'    => $campos_txt,
                'registro'  => $registro,                       // id da linha em admin_auditoria
                'elo'       => substr((string) $hash, 0, 16),   // começo do hash dessa linha
            ],
        ]);
        return true;
    }

    /** Há uma transação aberta por jc_admin_transacao()? */
    function jc_admin_em_transacao(mysqli $conn): bool {
        return jc_admin_transacao_nivel() > 0;
    }

    /**
     * Confere a cadeia inteira (em blocos, sem carregar tudo na memória).
     * @return array{ok:bool, total:int, primeira_falha:?int, detalhe:?string, ultimo_hash:?string}
     */
    function jc_admin_auditoria_verificar(mysqli $conn): array {
        $anterior = JC_AUDITORIA_GENESE;
        $total = 0;
        $ultimo_id = 0;
        $bloco = 500;
        while (true) {
            $stmt = $conn->prepare(
                "SELECT id, DATE_FORMAT(criado_em, '%Y-%m-%d %H:%i:%s') AS criado_em, admin_id, acao, recurso_tipo,
                        recurso_id, resultado, motivo_codigo, justificativa, campos, req_id, ip_anon, hash_anterior, hash
                 FROM admin_auditoria WHERE id > ? ORDER BY id ASC LIMIT {$bloco}"
            );
            $stmt->bind_param("i", $ultimo_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $n = 0;
            while ($l = $res->fetch_assoc()) {
                $n++;
                $total++;
                $ultimo_id = intval($l['id']);
                if (!hash_equals($anterior, (string) $l['hash_anterior'])) {
                    $stmt->close();
                    return ['ok' => false, 'total' => $total, 'primeira_falha' => $ultimo_id,
                            'detalhe' => 'A ligação com a linha anterior não confere (linha removida ou alterada antes desta).',
                            'ultimo_hash' => null];
                }
                $esperado = jc_admin_auditoria_hash($anterior, $l);
                if (!hash_equals($esperado, (string) $l['hash'])) {
                    $stmt->close();
                    return ['ok' => false, 'total' => $total, 'primeira_falha' => $ultimo_id,
                            'detalhe' => 'O conteúdo desta linha foi alterado depois de gravado.', 'ultimo_hash' => null];
                }
                $anterior = (string) $l['hash'];
            }
            $stmt->close();
            if ($n < $bloco) {
                break;
            }
        }
        return ['ok' => true, 'total' => $total, 'primeira_falha' => null, 'detalhe' => null,
                'ultimo_hash' => $total ? $anterior : null];
    }

    /**
     * Confere o espelho do histórico em logs/seguranca.log (arquivo atual e os
     * mais recentes da rotação) contra a tabela: cada linha "admin.*" do log
     * traz o id do registro e o começo do hash. Registro citado no log que
     * não existe mais no banco = removido (inclusive no FIM da tabela, que a
     * cadeia sozinha não denuncia) — ou o banco foi restaurado de um backup
     * mais antigo que o log. Hash diferente = registro trocado.
     * @return array{ok:bool, arquivos:int, conferidos:int, ausentes:int[], diferentes:int[]}
     */
    function jc_admin_auditoria_conferir_log(mysqli $conn, int $max_arquivos = 5): array {
        $arquivos = [];
        if (function_exists('jc_log_dir')) {
            $dir = jc_log_dir();
            if (is_file($dir . '/seguranca.log')) {
                $arquivos[] = $dir . '/seguranca.log';
            }
            $antigos = glob($dir . '/seguranca-*.log') ?: [];
            rsort($antigos);   // nomes com data: mais novo primeiro
            $arquivos = array_merge($arquivos, array_slice($antigos, 0, max(0, $max_arquivos - count($arquivos))));
        }
        $elos = [];   // id do registro => começo do hash
        foreach ($arquivos as $arquivo) {
            $h = @fopen($arquivo, 'rb');
            if (!$h) {
                continue;
            }
            while (($linha = fgets($h, 65536)) !== false) {
                if (strpos($linha, '"elo":"') !== false
                    && preg_match('/"registro":(\d+)/', $linha, $mr) && preg_match('/"elo":"([0-9a-f]{16})"/', $linha, $me)) {
                    $elos[intval($mr[1])] = $me[1];
                }
            }
            fclose($h);
        }
        $ausentes = [];
        $diferentes = [];
        foreach (array_chunk(array_keys($elos), 500) as $lote) {
            $lista = implode(',', array_map('intval', $lote));
            $r = $conn->query("SELECT id, LEFT(hash, 16) AS elo FROM admin_auditoria WHERE id IN ({$lista})");
            $achados = [];
            while ($l = $r->fetch_assoc()) {
                $achados[intval($l['id'])] = (string) $l['elo'];
            }
            foreach ($lote as $id) {
                if (!isset($achados[$id])) {
                    $ausentes[] = $id;
                } elseif (!hash_equals($elos[$id], $achados[$id])) {
                    $diferentes[] = $id;
                }
            }
        }
        sort($ausentes);
        sort($diferentes);
        return ['ok' => !$ausentes && !$diferentes, 'arquivos' => count($arquivos), 'conferidos' => count($elos),
                'ausentes' => $ausentes, 'diferentes' => $diferentes];
    }

    /** Lista curta de ids para mensagens ("#4, #5 e mais 3"). */
    function jc_admin_lista_ids(array $ids, int $max = 10): string {
        $txt = implode(', ', array_map(fn($i) => '#' . intval($i), array_slice($ids, 0, $max)));
        return count($ids) > $max ? $txt . ' e mais ' . (count($ids) - $max) : $txt;
    }

    /** Nomes legíveis das ações (para as telas). */
    function jc_admin_acoes(): array {
        return [
            'login'                 => 'Entrada na Central',
            'logout'                => 'Saída da Central',
            'reautenticacao'        => 'Confirmação de identidade',
            'sessao.encerrada'      => 'Sessão encerrada',
            'sessoes.encerradas'    => 'Outras sessões encerradas',
            'senha.alterada'        => 'Senha administrativa alterada',
            'acesso.negado'         => 'Acesso negado',
            'csrf.recusado'         => 'Envio recusado (CSRF)',
            'conta.editada'         => 'Conta corrigida',
            'conta.suspensa'        => 'Conta suspensa',
            'conta.reativada'       => 'Conta reativada',
            'vaga.editada'          => 'Vaga corrigida',
            'vaga.moderada'         => 'Moderação de vaga',
            'moderacao.decidida'    => 'Item da fila decidido',
            'ia.consulta'           => 'Análise complementar por IA',
            'auditoria.verificada'  => 'Integridade do histórico conferida',
            'cli.admin_criado'      => 'Administrador criado (linha de comando)',
            'cli.admin_desativado'  => 'Administrador desativado (linha de comando)',
            'cli.admin_reativado'   => 'Administrador reativado (linha de comando)',
            'cli.senha_redefinida'  => 'Senha redefinida (linha de comando)',
            'cli.mfa_redefinido'    => 'Autenticador redefinido (linha de comando)',
        ];
    }
}
