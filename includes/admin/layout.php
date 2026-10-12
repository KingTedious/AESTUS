<?php
/* ============================================================
   includes/admin/layout.php — estrutura visual da Central
   administrativa (Stage 7)

   - HTML semântico, "pular para o conteúdo", foco visível, rótulos
     em todos os campos, tabelas com <caption>/<th scope>;
   - sem estilos ou scripts embutidos (a CSP só permite arquivos do
     próprio site e o script com nonce);
   - confirmações por formulário (caixa "Confirmo" + justificativa),
     sem alert()/confirm() do navegador;
   - tudo escapado na saída com jc_e().
   ============================================================ */

if (!function_exists('jc_admin_inicio')) {

    function jc_e($v): string {
        return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** "2026-10-11 14:05:00" → "11/10/2026 14:05". */
    function jc_admin_data(?string $dt, bool $hora = true): string {
        if (!$dt) {
            return '—';
        }
        $t = strtotime($dt);
        return $t ? date($hora ? 'd/m/Y H:i' : 'd/m/Y', $t) : '—';
    }

    function jc_admin_num($n): string {
        return number_format(intval($n), 0, ',', '.');
    }

    /** E-mail parcialmente oculto para listas (o completo só na ficha da conta). */
    function jc_admin_email_mascarado(?string $email): string {
        $email = (string) $email;
        if (!str_contains($email, '@')) {
            return '—';
        }
        [$u, $d] = explode('@', $email, 2);
        $pu = mb_substr($u, 0, 2, 'UTF-8');
        $pontos = explode('.', $d);
        $dom = mb_substr($pontos[0], 0, 1, 'UTF-8') . '•••';
        $resto = count($pontos) > 1 ? '.' . implode('.', array_slice($pontos, 1)) : '';
        return $pu . '•••@' . $dom . $resto;
    }

    function jc_admin_selo(string $classe, string $texto): string {
        return '<span class="adm-selo adm-selo-' . jc_e($classe) . '">' . jc_e($texto) . '</span>';
    }

    function jc_admin_selo_conta(?string $status): string {
        return $status === 'suspensa' ? jc_admin_selo('erro', 'Suspensa') : jc_admin_selo('ok', 'Ativa');
    }

    function jc_admin_selo_vaga(?string $status): string {
        $mapa = ['aprovada' => ['ok', 'Aprovada'], 'sinalizada' => ['aviso', 'Sinalizada'], 'pendente' => ['aviso', 'Em revisão'],
                 'rejeitada' => ['erro', 'Rejeitada'], 'suspensa' => ['erro', 'Suspensa']];
        [$c, $t] = $mapa[(string) $status] ?? ['neutro', (string) $status];
        return jc_admin_selo($c, $t);
    }

    function jc_admin_selo_risco(?string $risco): string {
        $mapa = ['alto' => ['erro', 'Risco alto'], 'medio' => ['aviso', 'Risco médio'], 'baixo' => ['neutro', 'Risco baixo']];
        [$c, $t] = $mapa[(string) $risco] ?? ['neutro', 'Sem risco'];
        return jc_admin_selo($c, $t);
    }

    function jc_admin_selo_resultado(?string $r): string {
        $mapa = ['sucesso' => ['ok', 'Sucesso'], 'falha' => ['aviso', 'Falha'], 'negado' => ['erro', 'Negado']];
        [$c, $t] = $mapa[(string) $r] ?? ['neutro', (string) $r];
        return jc_admin_selo($c, $t);
    }

    function jc_admin_icone(string $nome): string {
        return '<span class="i i-' . jc_e($nome) . '" aria-hidden="true"></span>';
    }

    /** Parâmetros de GET com validação simples. */
    function jc_admin_get_int(string $nome, int $padrao = 0): int {
        $v = $_GET[$nome] ?? null;
        return (is_string($v) && ctype_digit($v)) ? intval($v) : $padrao;
    }

    function jc_admin_get_str(string $nome, int $max = 100): string {
        $v = $_GET[$nome] ?? '';
        if (!is_string($v)) {
            return '';
        }
        $v = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $v) ?? '');
        return mb_substr($v, 0, $max, 'UTF-8');
    }

    function jc_admin_get_enum(string $nome, array $permitidos, string $padrao = ''): string {
        $v = $_GET[$nome] ?? '';
        return (is_string($v) && in_array($v, $permitidos, true)) ? $v : $padrao;
    }

    /** Navegação da Central. */
    function jc_admin_menu(): array {
        return [
            'index.php'         => ['Painel', 'painel', 'painel.ver'],
            'contas.php'        => ['Contas', 'users', 'contas.ver'],
            'vagas.php'         => ['Vagas', 'briefcase', 'vagas.ver'],
            'moderacao.php'     => ['Fila de moderação', 'flag', 'moderacao.ver'],
            'auditoria.php'     => ['Histórico', 'history', 'auditoria.ver'],
            'seguranca.php'     => ['Segurança', 'shield', 'seguranca.ver'],
            'configuracoes.php' => ['Configurações', 'settings', 'configuracoes.ver'],
        ];
    }

    /** Abre a página (cabeçalho, menu, avisos, mensagens). */
    function jc_admin_inicio(string $titulo, string $ativo, array $admin, array $migalhas = []): void {
        global $conn;
        $pendentes = 0;
        try {
            $pendentes = intval($conn->query("SELECT COUNT(*) AS n FROM moderacao_itens WHERE estado = 'pendente'")->fetch_assoc()['n'] ?? 0);
        } catch (Throwable $e) {
            $pendentes = 0;
        }
        $nonce = jc_admin_nonce();
        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">'
           . '<meta name="viewport" content="width=device-width, initial-scale=1">'
           . '<meta name="robots" content="noindex, nofollow">'
           . '<title>' . jc_e($titulo) . ' · Central administrativa · JovemConnect</title>'
           . '<link rel="stylesheet" href="../assets/css/admin.css?v=7.0">'
           . '<link rel="icon" type="image/png" sizes="32x32" href="../assets/img/marca/jovemconnect-icone-32.png">'
           . '</head><body class="adm">';
        echo '<a class="adm-pular" href="#conteudo">Pular para o conteúdo</a>';
        echo '<header class="adm-topo"><div class="adm-topo-marca">'
           . '<span class="adm-logo" aria-hidden="true">JC</span>'
           . '<span class="adm-topo-nome">JovemConnect <span>Central administrativa</span></span></div>'
           . '<div class="adm-topo-usuario"><span class="adm-topo-quem">' . jc_admin_icone('user') . ' ' . jc_e($admin['nome']) . '</span>'
           . '<form method="post" action="logout.php" class="adm-form-inline">' . jc_admin_csrf_campo()
           . '<button type="submit" class="adm-btn adm-btn-sec adm-btn-p">' . jc_admin_icone('log-out') . ' Sair</button></form></div></header>';

        echo '<div class="adm-corpo"><nav class="adm-nav" aria-label="Seções da Central"><ul>';
        foreach (jc_admin_menu() as $pagina => [$rotulo, $icone, $perm]) {
            if (!jc_admin_pode($admin, $perm)) {
                continue;
            }
            $atual = ($pagina === $ativo) ? ' aria-current="page"' : '';
            $extra = ($pagina === 'moderacao.php' && $pendentes > 0)
                ? ' <span class="adm-contador" aria-label="' . $pendentes . ' itens pendentes">' . jc_admin_num($pendentes) . '</span>' : '';
            echo '<li><a href="' . jc_e($pagina) . '"' . $atual . '>' . jc_admin_icone($icone) . '<span>' . jc_e($rotulo) . '</span>' . $extra . '</a></li>';
        }
        echo '</ul></nav><main id="conteudo" class="adm-main" tabindex="-1">';

        if ($migalhas) {
            echo '<nav class="adm-migalhas" aria-label="Você está em"><ol>';
            foreach ($migalhas as $rot => $url) {
                echo $url ? '<li><a href="' . jc_e($url) . '">' . jc_e($rot) . '</a></li>' : '<li aria-current="page">' . jc_e($rot) . '</li>';
            }
            echo '</ol></nav>';
        }
        echo '<h1 class="adm-titulo">' . jc_e($titulo) . '</h1>';

        if (!jc_admin_https() && !jc_admin_local()) {
            echo '<div class="adm-alerta adm-alerta-erro" role="alert">' . jc_admin_icone('alert-triangle')
               . ' <div><strong>Conexão sem HTTPS.</strong> A Central só deve ser usada com HTTPS fora do computador local.</div></div>';
        }
        if (empty($admin['mfa_ativo'])) {
            echo '<div class="adm-alerta adm-alerta-aviso" role="note">' . jc_admin_icone('key')
               . ' <div><strong>Sua conta está sem o segundo fator (código do autenticador).</strong> '
               . 'Ative pela linha de comando: <code>php scripts/admin.php redefinir-mfa --email=…</code></div></div>';
        }
        foreach (jc_admin_flash_pegar() as $f) {
            $tipo = in_array($f['tipo'] ?? '', ['sucesso', 'erro', 'aviso', 'info'], true) ? $f['tipo'] : 'info';
            $papel = $tipo === 'erro' ? 'alert' : 'status';
            echo '<div class="adm-alerta adm-alerta-' . $tipo . '" role="' . $papel . '">'
               . jc_admin_icone($tipo === 'sucesso' ? 'check-circle' : ($tipo === 'erro' ? 'x-circle' : 'info'))
               . ' <div>' . jc_e($f['texto'] ?? '') . '</div></div>';
        }
    }

    /** Fecha a página. */
    function jc_admin_fim(): void {
        $min = intdiv(jc_admin_ocioso_seg(), 60);
        echo '</main></div><footer class="adm-rodape"><p>Sessão encerrada após ' . $min . ' min sem uso. '
           . 'Toda ação administrativa fica registrada no histórico.</p></footer>';
        echo '<script src="../assets/js/admin.js?v=7.0" nonce="' . jc_e(jc_admin_nonce()) . '"></script>';
        echo '</body></html>';
    }

    /** Página mínima (login, erro), sem menu. */
    function jc_admin_layout_abrir_simples(string $titulo): void {
        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">'
           . '<meta name="viewport" content="width=device-width, initial-scale=1">'
           . '<meta name="robots" content="noindex, nofollow">'
           . '<title>' . jc_e($titulo) . ' · Central administrativa · JovemConnect</title>'
           . '<link rel="stylesheet" href="../assets/css/admin.css?v=7.0">'
           . '<link rel="icon" type="image/png" sizes="32x32" href="../assets/img/marca/jovemconnect-icone-32.png">'
           . '</head><body class="adm adm-simples"><main id="conteudo" class="adm-cartao-central">'
           . '<div class="adm-topo-marca adm-marca-central"><span class="adm-logo" aria-hidden="true">JC</span>'
           . '<span class="adm-topo-nome">JovemConnect <span>Central administrativa</span></span></div>';
    }

    function jc_admin_layout_fechar_simples(): void {
        echo '</main><script src="../assets/js/admin.js?v=7.0" nonce="' . jc_e(jc_admin_nonce()) . '"></script></body></html>';
    }

    function jc_admin_layout_simples(string $titulo, string $texto): void {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }
        jc_admin_layout_abrir_simples($titulo);
        echo '<h1 class="adm-titulo">' . jc_e($titulo) . '</h1><p>' . jc_e($texto) . '</p>'
           . '<p><a class="adm-btn adm-btn-sec" href="index.php">Ir para o painel</a></p>';
        jc_admin_layout_fechar_simples();
    }

    /** Paginação acessível. */
    function jc_admin_paginacao(int $total, int $pagina, int $por, string $pagina_php, array $params): string {
        $paginas = max(1, (int) ceil($total / max(1, $por)));
        if ($paginas <= 1) {
            return '';
        }
        $url = function (int $p) use ($pagina_php, $params) {
            return jc_e($pagina_php . '?' . http_build_query(array_merge(array_filter($params, fn($v) => $v !== '' && $v !== null && $v !== 0), ['p' => $p])));
        };
        $h = '<nav class="adm-paginacao" aria-label="Páginas"><ul>';
        if ($pagina > 1) {
            $h .= '<li><a href="' . $url($pagina - 1) . '" rel="prev">' . jc_admin_icone('arrow-left') . ' Anterior</a></li>';
        }
        $h .= '<li><span aria-current="page">Página ' . $pagina . ' de ' . $paginas . '</span></li>';
        if ($pagina < $paginas) {
            $h .= '<li><a href="' . $url($pagina + 1) . '" rel="next">Próxima ' . jc_admin_icone('arrow-right') . '</a></li>';
        }
        return $h . '</ul></nav>';
    }

    /** Estado vazio. */
    function jc_admin_vazio(string $texto, string $dica = ''): string {
        return '<div class="adm-vazio">' . jc_admin_icone('info') . '<p>' . jc_e($texto) . '</p>'
             . ($dica !== '' ? '<p class="adm-dica">' . jc_e($dica) . '</p>' : '') . '</div>';
    }

    /** <select> com opções. */
    function jc_admin_select_html(string $nome, string $id, array $opcoes, string $atual, string $vazio = '', bool $obrig = false): string {
        $h = '<select name="' . jc_e($nome) . '" id="' . jc_e($id) . '"' . ($obrig ? ' required' : '') . '>';
        if ($vazio !== '') {
            $h .= '<option value="">' . jc_e($vazio) . '</option>';
        }
        foreach ($opcoes as $v => $rot) {
            $h .= '<option value="' . jc_e($v) . '"' . ((string) $v === $atual ? ' selected' : '') . '>' . jc_e($rot) . '</option>';
        }
        return $h . '</select>';
    }

    /** Campos de justificativa + confirmação (+ senha quando a operação é crítica). */
    function jc_admin_campos_decisao(string $prefixo, array $admin, bool $critica, string $confirmacao): string {
        $h = '<div class="adm-campo"><label for="' . $prefixo . '-just">Justificativa <span class="adm-obrig">(obrigatória)</span></label>'
           . '<textarea id="' . $prefixo . '-just" name="justificativa" rows="3" minlength="10" maxlength="500" required '
           . 'aria-describedby="' . $prefixo . '-just-ajuda" data-contador></textarea>'
           . '<p class="adm-ajuda" id="' . $prefixo . '-just-ajuda">De 10 a 500 caracteres. Fica no histórico; '
           . 'não escreva dados pessoais (e-mail, telefone, documentos).</p></div>';
        if ($critica && !jc_admin_reauth_recente()) {
            $h .= '<fieldset class="adm-reauth"><legend>Confirme sua identidade</legend>'
                . '<p class="adm-ajuda">Operação sensível: digite sua senha'
                . (!empty($admin['mfa_ativo']) ? ' e o código do autenticador' : '') . '. Vale por 5 minutos.</p>'
                . '<div class="adm-campo"><label for="' . $prefixo . '-senha">Sua senha</label>'
                . '<input type="password" id="' . $prefixo . '-senha" name="reauth_senha" autocomplete="current-password" required></div>';
            if (!empty($admin['mfa_ativo'])) {
                $h .= '<div class="adm-campo"><label for="' . $prefixo . '-cod">Código do autenticador</label>'
                    . '<input type="text" id="' . $prefixo . '-cod" name="reauth_codigo" inputmode="numeric" pattern="[0-9 ]{6,7}" '
                    . 'autocomplete="one-time-code" maxlength="7" required></div>';
            }
            $h .= '</fieldset>';
        }
        $h .= '<div class="adm-campo adm-campo-check"><input type="checkbox" id="' . $prefixo . '-conf" name="confirmo" value="1" required>'
            . '<label for="' . $prefixo . '-conf">' . jc_e($confirmacao) . '</label></div>';
        return $h;
    }

    /** Confere a caixa "Confirmo" (o navegador já exige, mas a regra vale no servidor). */
    function jc_admin_confirmou(): bool {
        return ($_POST['confirmo'] ?? '') === '1';
    }

    /** Lista de regras (código → descrição) para a tela. */
    function jc_admin_regras_html(?string $regras): string {
        $cods = array_filter(explode(',', (string) $regras));
        if (!$cods) {
            return '—';
        }
        $h = '<ul class="adm-regras">';
        foreach ($cods as $c) {
            $h .= '<li><code>' . jc_e($c) . '</code> <span>' . jc_e(jc_mod_descricao_regra($c)) . '</span></li>';
        }
        return $h . '</ul>';
    }

    /** Rótulo legível de um campo do mapa. */
    function jc_admin_rotulo_campo(string $campo): string {
        $especiais = ['denuncia' => 'Denúncia', 'vaga.flood' => 'Ritmo de publicação da empresa'];
        if (isset($especiais[$campo])) {
            return $especiais[$campo];
        }
        return jc_campo($campo)['rotulo'] ?? $campo;
    }

    /** Link para o recurso de um item/linha de histórico. */
    function jc_admin_link_recurso(?string $tipo, $id, ?string $nome = null): string {
        $id = intval($id);
        if ($id <= 0 || !$tipo) {
            return '—';
        }
        $rot = $nome !== null && $nome !== '' ? $nome : ucfirst($tipo) . ' #' . $id;
        return match ($tipo) {
            'vaga'           => '<a href="vaga.php?id=' . $id . '">' . jc_e($rot) . '</a>',
            'jovem', 'empresa' => '<a href="conta.php?tipo=' . $tipo . '&amp;id=' . $id . '">' . jc_e($rot) . '</a>',
            'moderacao_item' => '<a href="moderacao.php?item=' . $id . '">Item #' . $id . '</a>',
            default          => jc_e(ucfirst($tipo) . ' #' . $id),
        };
    }
}
