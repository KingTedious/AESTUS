<?php
/* ============================================================
   includes/recuperacao.php — estado do fluxo "Esqueci minha senha"
   (remediação, Lote A — AUD-001 / SEG-01)

   Usado só por recuperar_senha.php. Três etapas:
     1. pedido   — a pessoa informa um e-mail; se houver conta, um código de
                   6 dígitos (15 min) é gravado nela e enviado por e-mail.
                   TODO pedido novo apaga o estado anterior da sessão.
     2. código   — o código é CONSUMIDO no banco na mesma instrução que o
                   confere (uso único, à prova de envio duplo). Só então a
                   sessão fica autorizada — e somente para a conta do pedido
                   (tabela + id + e-mail lidos do banco, nunca do formulário).
     3. senha    — a nova senha é gravada SÓ nessa conta, numa instrução que
                   confere de novo o prazo e que não houve pedido mais novo;
                   a autorização acaba ali (uso único).

   As funções de sessão recebem o array por referência (na página é o
   $_SESSION; nos testes, um array comum). Nada de código, senha ou nome
   fica guardado na sessão.
   ============================================================ */

if (!function_exists('jc_rec_limpar')) {

    /** Validade do código (etapa 2) e da autorização para trocar a senha (etapa 3). */
    define('JC_REC_VALIDADE_SEG', 900);   // 15 minutos

    /** Chaves da versão anterior do fluxo (até a v4.50). */
    function jc_rec_chaves_legadas(): array {
        return ['recuperacao_etapa', 'recuperacao_email_mascarado', 'recuperacao_email_raw',
                'recuperacao_tabela', 'recuperacao_codigo_ok'];
    }

    /** Remove as chaves antigas: um fluxo aberto antes da atualização recomeça do início. */
    function jc_rec_limpar_legado(array &$sessao): void {
        foreach (jc_rec_chaves_legadas() as $k) {
            unset($sessao[$k]);
        }
    }

    /** Apaga TODO o estado temporário da recuperação (novo pedido, conclusão, erro, sessão perdida). */
    function jc_rec_limpar(array &$sessao): void {
        unset($sessao['jc_rec']);
        jc_rec_limpar_legado($sessao);
    }

    /** E-mail mascarado para mostrar na tela (ex.: ga***l@exemplo.com). */
    function jc_rec_mascarar_email(string $email): string {
        $partes = explode('@', $email);
        if (count($partes) !== 2) {
            return '***';
        }
        [$usuario, $dominio] = $partes;
        $mascarado = strlen($usuario) <= 2
            ? substr($usuario, 0, 1) . '***'
            : substr($usuario, 0, 2) . '***' . substr($usuario, -1);
        return $mascarado . '@' . $dominio;
    }

    /** Conta no formato guardado na sessão (só o necessário para vincular a autorização). */
    function jc_rec_conta_valida($c): bool {
        return is_array($c)
            && in_array($c['tabela'] ?? '', ['jovens', 'empresas'], true)
            && is_int($c['id'] ?? null) && $c['id'] > 0
            && is_string($c['email'] ?? null) && $c['email'] !== '';
    }

    /**
     * Etapa 1 concluída: começa um fluxo NOVO. $conta = null quando o e-mail não
     * tem cadastro (a tela é a mesma; o código digitado depois sempre falha).
     */
    function jc_rec_iniciar(array &$sessao, string $email_digitado, ?array $conta, ?int $agora = null): void {
        jc_rec_limpar($sessao);
        $sessao['jc_rec'] = [
            'etapa'     => 2,
            'mascara'   => jc_rec_mascarar_email($email_digitado),
            'conta'     => $conta ? ['tabela' => (string) $conta['tabela'], 'id' => intval($conta['id']),
                                     'email' => (string) $conta['email']] : null,
            'pedido_em' => $agora ?? time(),
        ];
    }

    /** Conta do pedido aberto (etapa 2) ou null (sem pedido, e-mail sem cadastro ou estado inválido). */
    function jc_rec_conta_pendente(array $sessao): ?array {
        $r = $sessao['jc_rec'] ?? null;
        if (!is_array($r) || ($r['etapa'] ?? 0) !== 2 || !jc_rec_conta_valida($r['conta'] ?? null)) {
            return null;
        }
        return $r['conta'];
    }

    /** Código conferido e consumido no banco: autoriza a etapa 3 só para a conta do pedido. */
    function jc_rec_autorizar(array &$sessao, ?int $agora = null): bool {
        if (jc_rec_conta_pendente($sessao) === null) {
            return false;
        }
        $sessao['jc_rec']['etapa'] = 3;
        $sessao['jc_rec']['autorizada_ate'] = ($agora ?? time()) + JC_REC_VALIDADE_SEG;
        return true;
    }

    /** Conta autorizada a trocar a senha agora (etapa 3, dentro do prazo) ou null. */
    function jc_rec_conta_autorizada(array $sessao, ?int $agora = null): ?array {
        $r = $sessao['jc_rec'] ?? null;
        if (!is_array($r) || ($r['etapa'] ?? 0) !== 3 || !jc_rec_conta_valida($r['conta'] ?? null)) {
            return null;
        }
        if (!is_int($r['autorizada_ate'] ?? null) || $r['autorizada_ate'] < ($agora ?? time())) {
            return null;
        }
        return $r['conta'];
    }

    /** Etapa a mostrar: 1 (pedir código), 2 (digitar código) ou 3 (nova senha). */
    function jc_rec_etapa(array $sessao, ?int $agora = null): int {
        $r = $sessao['jc_rec'] ?? null;
        if (!is_array($r)) {
            return 1;
        }
        if (($r['etapa'] ?? 0) === 3) {
            return jc_rec_conta_autorizada($sessao, $agora) !== null ? 3 : 1;
        }
        return ($r['etapa'] ?? 0) === 2 ? 2 : 1;
    }

    /** Autorização da etapa 3 que venceu (para avisar a pessoa e recomeçar). */
    function jc_rec_autorizacao_vencida(array $sessao, ?int $agora = null): bool {
        $r = $sessao['jc_rec'] ?? null;
        return is_array($r) && ($r['etapa'] ?? 0) === 3 && jc_rec_conta_autorizada($sessao, $agora) === null;
    }

    function jc_rec_mascara(array $sessao): string {
        return (string) ($sessao['jc_rec']['mascara'] ?? '');
    }

    function jc_rec_msg_indisponivel(): string {
        $contato = defined('JC_CONTATO_EMAIL') ? ' ou fale com a equipe pelo e-mail ' . JC_CONTATO_EMAIL : '';
        return 'A recuperação de senha por e-mail está indisponível no momento. Tente novamente mais tarde' . $contato . '.';
    }

    // ── Banco ──────────────────────────────────────────────────────────

    /** Tabela da conta, só da lista fixa (o nome entra no SQL). */
    function jc_rec_tabela(array $conta): string {
        $t = $conta['tabela'] ?? '';
        if (!in_array($t, ['jovens', 'empresas'], true)) {
            throw new InvalidArgumentException('tabela de recuperação inválida');
        }
        return $t;
    }

    /**
     * Procura a conta pelo e-mail (primeiro jovens, depois empresas — a mesma
     * ordem de antes; e-mail repetido nas duas tabelas é o AUD-015).
     * @return array{tabela:string,id:int,email:string,nome:string}|null
     */
    function jc_rec_buscar_conta(mysqli $conn, string $email): ?array {
        foreach (['jovens' => 'nome', 'empresas' => 'nome_empresa'] as $tabela => $col_nome) {
            $stmt = $conn->prepare("SELECT id, email, {$col_nome} AS nome FROM {$tabela} WHERE email = ? LIMIT 1");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $linha = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($linha) {
                return ['tabela' => $tabela, 'id' => intval($linha['id']),
                        'email' => (string) $linha['email'], 'nome' => (string) $linha['nome']];
            }
        }
        return null;
    }

    /** Grava um código novo na conta (substitui qualquer código ou autorização anterior dela). */
    function jc_rec_gravar_codigo(mysqli $conn, array $conta, string $codigo, string $expira): void {
        $t = jc_rec_tabela($conta);
        $id = intval($conta['id']);
        $stmt = $conn->prepare("UPDATE {$t} SET codigo_recuperacao = ?, expiracao_recuperacao = ? WHERE id = ?");
        $stmt->bind_param('ssi', $codigo, $expira, $id);
        $stmt->execute();
        $stmt->close();
    }

    /** Apaga o código e a autorização pendentes da conta (ex.: o e-mail não foi enviado). */
    function jc_rec_invalidar(mysqli $conn, array $conta): void {
        $t = jc_rec_tabela($conta);
        $id = intval($conta['id']);
        $stmt = $conn->prepare("UPDATE {$t} SET codigo_recuperacao = NULL, expiracao_recuperacao = NULL WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Confere e CONSOME o código numa única instrução: só uma requisição
     * consegue (uso único), e só dentro do prazo. Abre a janela da etapa 3
     * ($nova_expiracao). true = código certo, no prazo, desta conta.
     */
    function jc_rec_consumir_codigo(mysqli $conn, array $conta, string $codigo, string $agora, string $nova_expiracao): bool {
        if (!preg_match('/^\d{6}$/', $codigo)) {
            return false;
        }
        $t = jc_rec_tabela($conta);
        $id = intval($conta['id']);
        $email = (string) $conta['email'];
        $stmt = $conn->prepare(
            "UPDATE {$t} SET codigo_recuperacao = NULL, expiracao_recuperacao = ?
             WHERE id = ? AND email = ? AND codigo_recuperacao = ? AND expiracao_recuperacao >= ?"
        );
        $stmt->bind_param('sisss', $nova_expiracao, $id, $email, $codigo, $agora);
        $stmt->execute();
        $ok = $stmt->affected_rows === 1;
        $stmt->close();
        return $ok;
    }

    /**
     * Grava a nova senha SÓ na conta autorizada, numa única instrução que exige:
     * mesmo id e e-mail, código já consumido (nenhum pedido mais novo depois da
     * validação) e prazo da etapa 3 em aberto. Encerra a autorização (uso único).
     */
    function jc_rec_trocar_senha(mysqli $conn, array $conta, string $hash_senha, string $agora): bool {
        $t = jc_rec_tabela($conta);
        $id = intval($conta['id']);
        $email = (string) $conta['email'];
        $stmt = $conn->prepare(
            "UPDATE {$t} SET senha = ?, codigo_recuperacao = NULL, expiracao_recuperacao = NULL
             WHERE id = ? AND email = ? AND codigo_recuperacao IS NULL
               AND expiracao_recuperacao IS NOT NULL AND expiracao_recuperacao >= ?"
        );
        $stmt->bind_param('siss', $hash_senha, $id, $email, $agora);
        $stmt->execute();
        $ok = $stmt->affected_rows === 1;
        $stmt->close();
        return $ok;
    }
}
