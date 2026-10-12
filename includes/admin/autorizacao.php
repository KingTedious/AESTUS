<?php
/* ============================================================
   includes/admin/autorizacao.php — autorização administrativa (Stage 7)

   UM ponto de entrada para toda página, ação e endpoint da Central:

     $admin = jc_admin_exigir('contas.suspender');           // página
     $admin = jc_admin_exigir('ia.consultar', ['json' => true]); // AJAX

   jc_admin_exigir() faz, no servidor e nesta ordem:
     1. cabeçalhos de segurança (CSP, no-store, anti-iframe);
     2. confere se a migração da Stage 7 foi aplicada;
     3. sessão administrativa válida (prazos, navegador);
     4. a conta administrativa ainda existe, está ATIVA, tem papel
        conhecido e a mesma versão de sessão (troca de senha ou
        desativação derrubam as sessões abertas);
     5. o papel tem a permissão pedida;
     6. em POST: token CSRF e origem.
   Qualquer falha encerra a requisição (redirecionamento para o login,
   403 ou JSON) e fica registrada.

   PAPÉIS (menor privilégio): por ora existe UM papel, "administrador",
   com todas as permissões abaixo. A matriz deixa explícito o que cada
   permissão libera; um papel mais restrito (ex.: "moderador") pode ser
   criado acrescentando uma linha em jc_admin_papeis() — nenhum papel
   é atribuído por migração, por cadastro público ou pela própria
   Central: só pelo provisionamento na linha de comando
   (scripts/admin.php). Não existe tela para mudar papel.
   ============================================================ */

if (!function_exists('jc_admin_exigir')) {

    /** Permissões (código → o que libera). */
    function jc_admin_permissoes(): array {
        return [
            'painel.ver'          => 'Ver o painel com os indicadores',
            'contas.ver'          => 'Buscar e ver contas de jovens e empresas (sem dados sensíveis)',
            'contas.editar'       => 'Corrigir campos não sensíveis de uma conta (nome, textos do perfil)',
            'contas.suspender'    => 'Suspender e reativar contas (com justificativa e reautenticação)',
            'vagas.ver'           => 'Buscar e ver vagas, com o histórico de moderação',
            'vagas.editar'        => 'Corrigir título, descrição, salário e carga horária de uma vaga',
            'vagas.moderar'       => 'Aprovar, colocar em revisão, rejeitar, suspender e reativar vagas',
            'moderacao.ver'       => 'Ver a fila de moderação e as denúncias',
            'moderacao.decidir'   => 'Registrar a decisão sobre um item da fila',
            'ia.consultar'        => 'Pedir a análise complementar por IA (só orientação)',
            'auditoria.ver'       => 'Ver o histórico de ações administrativas',
            'auditoria.verificar' => 'Conferir a integridade do histórico (cadeia de hashes)',
            'seguranca.ver'       => 'Ver eventos de segurança e tentativas de login',
            'configuracoes.ver'   => 'Ver o estado do ambiente e gerenciar a própria conta',
        ];
    }

    /** Papéis → permissões. */
    function jc_admin_papeis(): array {
        return [
            'administrador' => array_keys(jc_admin_permissoes()),
        ];
    }

    function jc_admin_pode(array $admin, string $permissao): bool {
        $papeis = jc_admin_papeis();
        $papel  = (string) ($admin['papel'] ?? '');
        return isset($papeis[$papel]) && in_array($permissao, $papeis[$papel], true);
    }

    /** Carrega a conta administrativa (sem senha_hash e sem o segredo MFA). */
    function jc_admin_carregar(mysqli $conn, int $id): ?array {
        $stmt = $conn->prepare(
            "SELECT id, email, nome, papel, ativo, sessao_versao, (mfa_segredo IS NOT NULL) AS mfa_ativo,
                    ultimo_login_em, senha_alterada_em, criado_em
             FROM administradores WHERE id = ? LIMIT 1"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $a = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $a ?: null;
    }

    /** Página simples de erro (sem dados internos). */
    function jc_admin_pagina_erro(int $codigo, string $titulo, string $texto, bool $json = false): void {
        if (!headers_sent()) {
            http_response_code($codigo);
        }
        if ($json) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode(['ok' => false, 'erro' => $texto], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (function_exists('jc_admin_layout_simples')) {
            jc_admin_layout_simples($titulo, $texto);
        } else {
            echo '<!doctype html><meta charset="utf-8"><title>' . htmlspecialchars($titulo) . '</title><p>'
               . htmlspecialchars($texto) . '</p>';
        }
        exit;
    }

    /**
     * Exige sessão administrativa válida e a permissão pedida.
     * @param array $opcoes ['json' => bool (endpoint AJAX), 'csrf' => bool (padrão: true em POST)]
     * @return array a conta administrativa (sem segredos)
     */
    function jc_admin_exigir(string $permissao, array $opcoes = []): array {
        global $conn;
        $json = !empty($opcoes['json']);
        jc_admin_cabecalhos();

        if (!isset(jc_admin_permissoes()[$permissao])) {
            // erro de programação: nunca liberar por engano
            jc_admin_pagina_erro(500, 'Erro de configuração', 'Permissão desconhecida.', $json);
        }
        if (!($conn instanceof mysqli) || !jc_stage7_disponivel($conn)) {
            jc_admin_pagina_erro(503, 'Central indisponível',
                'A Central administrativa ainda não foi instalada neste banco (migração da Stage 7 pendente). '
                . 'Veja docs/ADMINISTRACAO_E_MODERACAO.md.', $json);
        }

        jc_admin_sessao_iniciar();
        $motivo = jc_admin_sessao_problema();
        $admin  = null;
        if ($motivo === null) {
            $admin = jc_admin_carregar($conn, intval($_SESSION['adm_id']));
            if (!$admin || intval($admin['ativo']) !== 1) {
                $motivo = 'inativo';
            } elseif (intval($admin['sessao_versao']) !== intval($_SESSION['adm_versao'] ?? -1)) {
                $motivo = 'versao';
            } elseif (!isset(jc_admin_papeis()[(string) $admin['papel']])) {
                $motivo = 'inativo';
            }
        }

        if ($motivo !== null) {
            if ($motivo !== 'sem_login') {
                $id_sessao = intval($_SESSION['adm_id'] ?? 0) ?: null;
                jc_admin_auditar($conn, $id_sessao, 'sessao.encerrada', 'administrador', $id_sessao, 'sucesso', $motivo);
            }
            jc_admin_sessao_destruir();
            if ($json) {
                jc_admin_pagina_erro(401, 'Sessão encerrada', jc_admin_msg_fim_sessao($motivo), true);
            }
            jc_admin_redirecionar('login.php', $motivo !== 'sem_login' ? ['motivo' => $motivo] : []);
        }

        $_SESSION['adm_ultimo'] = time();

        if (!jc_admin_pode($admin, $permissao)) {
            jc_admin_auditar($conn, intval($admin['id']), 'acesso.negado', null, null, 'negado', $permissao);
            jc_admin_pagina_erro(403, 'Acesso negado', 'Sua conta administrativa não tem permissão para esta ação.', $json);
        }

        $exigir_csrf = $opcoes['csrf'] ?? jc_admin_post();
        if ($exigir_csrf && !jc_admin_csrf_valido()) {
            jc_admin_auditar($conn, intval($admin['id']), 'csrf.recusado', null, null, 'negado', $permissao);
            jc_admin_pagina_erro(403, 'Envio recusado',
                'A página ficou desatualizada ou o envio não veio da Central. Nada foi alterado: abra a página de novo e repita.', $json);
        }

        return $admin;
    }

    /**
     * Regras da senha administrativa: no mínimo 12 caracteres, sem ser
     * repetitiva, sem conter o e-mail e fora da lista das mais comuns.
     * @return string|null  null = aceita; senão o motivo
     */
    function jc_admin_senha_problema(string $senha, string $email = ''): ?string {
        if (mb_strlen($senha, 'UTF-8') < 12) {
            return 'Use pelo menos 12 caracteres.';
        }
        if (strlen($senha) > 200) {
            return 'Use no máximo 200 caracteres.';
        }
        if (count(array_unique(preg_split('//u', $senha, -1, PREG_SPLIT_NO_EMPTY))) < 5) {
            return 'A senha é repetitiva demais.';
        }
        $local = strtolower(explode('@', $email)[0] ?? '');
        if ($local !== '' && strlen($local) >= 4 && str_contains(strtolower($senha), $local)) {
            return 'A senha não pode conter o seu e-mail.';
        }
        $comuns = ['123456789012', 'senhasenha12', 'jovemconnect', 'administrador', 'password1234', 'qwertyuiop12', 'admin1234567'];
        foreach ($comuns as $c) {
            if (str_contains(strtolower($senha), $c)) {
                return 'Essa senha é fácil de adivinhar.';
            }
        }
        return null;
    }

    /** Senha aleatória forte (sem caracteres ambíguos). */
    function jc_admin_gerar_senha(int $tam = 20): string {
        $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789-_.!@#%';
        $s = '';
        $n = strlen($alfabeto);
        for ($i = 0; $i < $tam; $i++) {
            $s .= $alfabeto[random_int(0, $n - 1)];
        }
        return $s;
    }

    // ── Reautenticação para operações críticas ──────────────────────────

    /** A senha (e o código) foram confirmados há pouco? */
    function jc_admin_reauth_recente(): bool {
        return intval($_SESSION['adm_reauth_ate'] ?? 0) >= time();
    }

    /**
     * Confirma a identidade de novo (senha + código do autenticador, quando ativo).
     * Falhas contam no limite de tentativas. @return bool
     */
    function jc_admin_reautenticar(mysqli $conn, array $admin, string $senha, string $codigo): bool {
        $id = intval($admin['id']);
        $estado = jc_admin_limite_estado($conn, (string) $admin['email']);
        if ($estado['bloqueado']) {
            return false;
        }
        $stmt = $conn->prepare("SELECT senha_hash, mfa_segredo, mfa_ultimo_passo FROM administradores WHERE id = ? AND ativo = 1 LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $linha = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $ok = $linha && $senha !== '' && password_verify($senha, (string) $linha['senha_hash']);
        if ($ok && !empty($linha['mfa_segredo'])) {
            $passo = jc_totp_verificar((string) $linha['mfa_segredo'], $codigo,
                                       $linha['mfa_ultimo_passo'] !== null ? intval($linha['mfa_ultimo_passo']) : null);
            $ok = ($passo !== null) && jc_totp_gravar_passo($conn, $id, $passo);
        }
        jc_admin_limite_registrar($conn, (string) $admin['email'], $ok);
        jc_admin_auditar($conn, $id, 'reautenticacao', 'administrador', $id, $ok ? 'sucesso' : 'falha');
        if ($ok) {
            $_SESSION['adm_reauth_ate'] = time() + jc_admin_reauth_seg();
        } else {
            jc_admin_atraso_progressivo(intval($estado['falhas']) + 1);
        }
        return $ok;
    }

    /**
     * Para formulários de operação crítica: se a reautenticação não é recente,
     * confere os campos reauth_senha/reauth_codigo enviados junto.
     * @return string|null  null = ok; senão a mensagem de erro
     */
    function jc_admin_exigir_reauth(mysqli $conn, array $admin): ?string {
        if (jc_admin_reauth_recente()) {
            return null;
        }
        $senha  = (string) ($_POST['reauth_senha'] ?? '');
        $codigo = (string) ($_POST['reauth_codigo'] ?? '');
        if ($senha === '') {
            return 'Confirme sua senha para concluir esta operação.';
        }
        if (!jc_admin_reautenticar($conn, $admin, $senha, $codigo)) {
            return 'Não foi possível confirmar sua identidade. Confira a senha e o código do autenticador.';
        }
        return null;
    }
}
