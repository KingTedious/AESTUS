<?php
/* ============================================================
   scripts/admin.php — provisionamento da Central administrativa
   (Stage 7). SÓ pela linha de comando.

   É a ÚNICA forma de criar um administrador. Não existe cadastro
   público, senha padrão, conta "admin" pronta, URL secreta nem
   promoção de conta de jovem/empresa: administradores ficam numa
   tabela separada (administradores).

   Uso (na pasta do projeto, ex.: C:\xampp\htdocs\JovemConnect):
     php scripts/admin.php status
     php scripts/admin.php criar --email=voce@exemplo.com --nome="Seu Nome"
     php scripts/admin.php listar
     php scripts/admin.php desativar --email=...
     php scripts/admin.php reativar --email=...
     php scripts/admin.php redefinir-senha --email=...
     php scripts/admin.php redefinir-mfa --email=...
     php scripts/admin.php verificar-auditoria

   - "criar" só cria o PRIMEIRO administrador direto. Com a tabela já
     preenchida, exige --adicional e a confirmação digitada
     "CRIAR ADMINISTRADOR" (ou --confirmar="CRIAR ADMINISTRADOR").
   - A senha é gerada aqui (ou lida com --senha-stdin) e mostrada UMA
     vez; o segredo do autenticador (TOTP) também. Nenhum dos dois vai
     para logs ou para o histórico.
   - --sem-mfa cria/deixa a conta sem o segundo fator: não recomendado;
     fica registrado e a Central mostra um aviso permanente.
   - Toda operação entra no histórico (admin_auditoria) como ação de
     linha de comando.
   ============================================================ */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/admin/nucleo.php';

set_exception_handler(function (Throwable $e) {
    fwrite(STDERR, "Erro: " . ($e instanceof mysqli_sql_exception ? 'falha no banco de dados (código ' . $e->getCode() . ').' : $e->getMessage()) . PHP_EOL);
    if (function_exists('jc_log_excecao')) {
        jc_log_excecao($e, 'cli.admin_erro');
    }
    exit(1);
});

/** Lê --opcao=valor e --flag. */
function cli_opcoes(array $argv): array {
    $o = [];
    foreach (array_slice($argv, 2) as $a) {
        if (preg_match('/^--([a-z\-]+)(?:=(.*))?$/s', $a, $m)) {
            $o[$m[1]] = $m[2] ?? true;
        }
    }
    return $o;
}

function cli_sair(string $msg, int $codigo = 1): void {
    fwrite($codigo === 0 ? STDOUT : STDERR, $msg . PHP_EOL);
    exit($codigo);
}

function cli_ler_linha(string $pergunta): string {
    fwrite(STDOUT, $pergunta);
    $l = fgets(STDIN);
    return $l === false ? '' : trim($l);
}

/** Lê a senha da entrada padrão (sem eco quando possível). */
function cli_ler_senha(): string {
    $interativo = function_exists('posix_isatty') ? @posix_isatty(STDIN) : false;
    if ($interativo && stripos(PHP_OS, 'WIN') !== 0) {
        @shell_exec('stty -echo');
        $s = cli_ler_linha('Senha (não aparece na tela): ');
        @shell_exec('stty echo');
        fwrite(STDOUT, PHP_EOL);
        return $s;
    }
    if ($interativo) {
        fwrite(STDOUT, "Atenção: no Windows a senha digitada aparece na tela.\n");
    }
    return cli_ler_linha($interativo ? 'Senha: ' : '');
}

function cli_admin_por_email(mysqli $conn, string $email): ?array {
    $stmt = $conn->prepare("SELECT id, email, nome, papel, ativo, (mfa_segredo IS NOT NULL) AS mfa FROM administradores WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $a = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $a ?: null;
}

function cli_mostrar_credenciais(string $email, ?string $senha, ?string $segredo): void {
    echo PHP_EOL . str_repeat('=', 64) . PHP_EOL;
    echo " GUARDE AGORA (estes dados não serão mostrados de novo)" . PHP_EOL;
    echo str_repeat('=', 64) . PHP_EOL;
    echo " E-mail de acesso : {$email}" . PHP_EOL;
    if ($senha !== null) {
        echo " Senha            : {$senha}" . PHP_EOL;
    }
    if ($segredo !== null) {
        echo " Autenticador     : cadastre no app (Google/Microsoft Authenticator, Aegis, 2FAS...)" . PHP_EOL;
        echo "   Chave manual   : " . trim(chunk_split($segredo, 4, ' ')) . PHP_EOL;
        echo "   Endereço       : " . jc_totp_uri($segredo, $email) . PHP_EOL;
    }
    echo str_repeat('=', 64) . PHP_EOL;
    echo " Entre em: <endereço do site>/admin/login.php" . PHP_EOL . PHP_EOL;
}

$comando = $argv[1] ?? '';
$opc = cli_opcoes($argv);

if ($comando === '' || $comando === 'ajuda' || isset($opc['ajuda'])) {
    cli_sair("Uso: php scripts/admin.php <status|criar|listar|desativar|reativar|redefinir-senha|redefinir-mfa|verificar-auditoria> [opções]\n"
           . "Veja docs/ADMINISTRACAO_E_MODERACAO.md.", 0);
}

if (!jc_stage7_disponivel($conn)) {
    cli_sair("A migração da Stage 7 ainda não foi aplicada neste banco.\n"
           . "Aplique migrations/2026-10-11_stage7_admin_moderacao.sql (veja docs/MIGRACOES.md) e rode de novo.\n"
           . "Falta: " . implode(', ', jc_stage7_faltando($conn)));
}

$total = intval($conn->query("SELECT COUNT(*) AS n FROM administradores")->fetch_assoc()['n'] ?? 0);

switch ($comando) {

    case 'status':
        $ativos = intval($conn->query("SELECT COUNT(*) AS n FROM administradores WHERE ativo = 1")->fetch_assoc()['n'] ?? 0);
        $sem_mfa = intval($conn->query("SELECT COUNT(*) AS n FROM administradores WHERE ativo = 1 AND mfa_segredo IS NULL")->fetch_assoc()['n'] ?? 0);
        echo "Migração da Stage 7: aplicada" . PHP_EOL;
        echo "Administradores: {$total} (ativos: {$ativos}; ativos sem autenticador: {$sem_mfa})" . PHP_EOL;
        exit(0);

    case 'listar':
        $r = $conn->query("SELECT id, email, nome, papel, ativo, (mfa_segredo IS NOT NULL) AS mfa,
                                  DATE_FORMAT(ultimo_login_em, '%d/%m/%Y %H:%i') AS ultimo
                           FROM administradores ORDER BY id");
        if ($r->num_rows === 0) {
            cli_sair("Nenhum administrador. Crie o primeiro com: php scripts/admin.php criar --email=... --nome=\"...\"", 0);
        }
        while ($a = $r->fetch_assoc()) {
            printf("#%d  %-32s  %-24s  %s  %s  MFA:%s  último acesso: %s\n", $a['id'], $a['email'], $a['nome'], $a['papel'],
                   $a['ativo'] ? 'ativo' : 'DESATIVADO', $a['mfa'] ? 'sim' : 'NÃO', $a['ultimo'] ?? '—');
        }
        exit(0);

    case 'criar':
        $email = strtolower(trim((string) ($opc['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            cli_sair('Informe um e-mail válido: --email=voce@exemplo.com');
        }
        $nome = jc_validar_campo('admin.nome', (string) ($opc['nome'] ?? ''));
        if (!$nome['ok']) {
            cli_sair('Nome inválido: ' . $nome['erro'] . ' Use --nome="Seu Nome".');
        }
        if ($total > 0) {
            if (empty($opc['adicional'])) {
                cli_sair("Já existe administrador cadastrado. A criação direta só vale para o PRIMEIRO.\n"
                       . "Para criar outro, use --adicional e confirme digitando CRIAR ADMINISTRADOR.");
            }
            $conf = is_string($opc['confirmar'] ?? null) ? $opc['confirmar'] : cli_ler_linha('Digite CRIAR ADMINISTRADOR para confirmar: ');
            if ($conf !== 'CRIAR ADMINISTRADOR') {
                cli_sair('Confirmação não conferiu. Nada foi criado.');
            }
        }
        if (cli_admin_por_email($conn, $email)) {
            cli_sair('Já existe um administrador com esse e-mail.');
        }
        foreach (['jovens', 'empresas'] as $t) {
            $stmt = $conn->prepare("SELECT 1 FROM `{$t}` WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            if ($stmt->get_result()->fetch_row()) {
                echo "Aviso: este e-mail também é de uma conta de " . ($t === 'jovens' ? 'jovem' : 'empresa')
                   . ". As contas continuam separadas (nenhum privilégio é dado a ela). Prefira um e-mail exclusivo." . PHP_EOL;
            }
            $stmt->close();
        }
        if (!empty($opc['senha-stdin'])) {
            $senha = cli_ler_senha();
            if ($p = jc_admin_senha_problema($senha, $email)) {
                cli_sair('Senha recusada: ' . $p);
            }
            $mostrar_senha = null;
        } else {
            $senha = jc_admin_gerar_senha();
            $mostrar_senha = $senha;
        }
        $segredo = empty($opc['sem-mfa']) ? jc_totp_novo_segredo() : null;
        $hash = password_hash($senha, PASSWORD_DEFAULT);
        $papel = 'administrador';
        $id = jc_admin_transacao($conn, function () use ($conn, $email, $nome, $hash, $papel, $segredo, $total) {
            $stmt = $conn->prepare(
                "INSERT INTO administradores (email, nome, senha_hash, papel, ativo, mfa_segredo, sessao_versao, senha_alterada_em)
                 VALUES (?, ?, ?, ?, 1, ?, 1, NOW())"
            );
            $stmt->bind_param("sssss", $email, $nome['valor'], $hash, $papel, $segredo);
            $stmt->execute();
            $novo = intval($conn->insert_id);
            $stmt->close();
            jc_admin_auditar($conn, null, 'cli.admin_criado', 'administrador', $novo, 'sucesso',
                             ($total === 0 ? 'primeiro' : 'adicional') . ($segredo ? '' : '_sem_mfa'), null,
                             ['email', 'nome', 'papel', 'mfa']);
            return $novo;
        });
        echo "Administrador #{$id} criado ({$papel})." . PHP_EOL;
        if ($segredo === null) {
            echo "ATENÇÃO: conta criada SEM o segundo fator (--sem-mfa). Ative depois com: php scripts/admin.php redefinir-mfa --email={$email}" . PHP_EOL;
        }
        cli_mostrar_credenciais($email, $mostrar_senha, $segredo);
        exit(0);

    case 'desativar':
    case 'reativar':
        $a = cli_admin_por_email($conn, strtolower(trim((string) ($opc['email'] ?? ''))));
        if (!$a) {
            cli_sair('Administrador não encontrado.');
        }
        $novo = $comando === 'reativar' ? 1 : 0;
        if (intval($a['ativo']) === $novo) {
            cli_sair('Nada a fazer: a conta já está ' . ($novo ? 'ativa.' : 'desativada.'), 0);
        }
        if ($novo === 0) {
            $outros = intval($conn->query("SELECT COUNT(*) AS n FROM administradores WHERE ativo = 1 AND id <> " . intval($a['id']))->fetch_assoc()['n'] ?? 0);
            if ($outros === 0) {
                echo "Aviso: este é o último administrador ativo; a Central ficará sem acesso até alguém ser reativado." . PHP_EOL;
            }
        }
        jc_admin_transacao($conn, function () use ($conn, $a, $novo, $comando) {
            $id = intval($a['id']);
            $stmt = $conn->prepare("UPDATE administradores SET ativo = ?, sessao_versao = sessao_versao + 1 WHERE id = ?");
            $stmt->bind_param("ii", $novo, $id);
            $stmt->execute();
            $stmt->close();
            jc_admin_auditar($conn, null, $comando === 'reativar' ? 'cli.admin_reativado' : 'cli.admin_desativado',
                             'administrador', $id, 'sucesso', null, null, ['ativo', 'sessao_versao']);
        });
        cli_sair('Administrador #' . $a['id'] . ($novo ? ' reativado.' : ' desativado (sessões abertas encerradas).'), 0);

    case 'redefinir-senha':
        $a = cli_admin_por_email($conn, strtolower(trim((string) ($opc['email'] ?? ''))));
        if (!$a) {
            cli_sair('Administrador não encontrado.');
        }
        if (!empty($opc['senha-stdin'])) {
            $senha = cli_ler_senha();
            if ($p = jc_admin_senha_problema($senha, (string) $a['email'])) {
                cli_sair('Senha recusada: ' . $p);
            }
            $mostrar = null;
        } else {
            $senha = jc_admin_gerar_senha();
            $mostrar = $senha;
        }
        $hash = password_hash($senha, PASSWORD_DEFAULT);
        jc_admin_transacao($conn, function () use ($conn, $a, $hash) {
            $id = intval($a['id']);
            $stmt = $conn->prepare("UPDATE administradores SET senha_hash = ?, senha_alterada_em = NOW(), sessao_versao = sessao_versao + 1 WHERE id = ?");
            $stmt->bind_param("si", $hash, $id);
            $stmt->execute();
            $stmt->close();
            jc_admin_auditar($conn, null, 'cli.senha_redefinida', 'administrador', $id, 'sucesso', null, null, ['senha_hash', 'sessao_versao']);
        });
        echo "Senha redefinida; as sessões abertas dessa conta foram encerradas." . PHP_EOL;
        cli_mostrar_credenciais((string) $a['email'], $mostrar, null);
        exit(0);

    case 'redefinir-mfa':
        $a = cli_admin_por_email($conn, strtolower(trim((string) ($opc['email'] ?? ''))));
        if (!$a) {
            cli_sair('Administrador não encontrado.');
        }
        $segredo = empty($opc['sem-mfa']) ? jc_totp_novo_segredo() : null;
        jc_admin_transacao($conn, function () use ($conn, $a, $segredo) {
            $id = intval($a['id']);
            $stmt = $conn->prepare("UPDATE administradores SET mfa_segredo = ?, mfa_ultimo_passo = NULL, sessao_versao = sessao_versao + 1 WHERE id = ?");
            $stmt->bind_param("si", $segredo, $id);
            $stmt->execute();
            $stmt->close();
            jc_admin_auditar($conn, null, 'cli.mfa_redefinido', 'administrador', $id, 'sucesso', $segredo ? 'novo_segredo' : 'removido', null,
                             ['mfa_segredo', 'sessao_versao']);
        });
        if ($segredo === null) {
            cli_sair('Segundo fator REMOVIDO (não recomendado). Sessões abertas encerradas.', 0);
        }
        echo "Novo segredo do autenticador gerado; o anterior deixou de valer e as sessões abertas foram encerradas." . PHP_EOL;
        cli_mostrar_credenciais((string) $a['email'], null, $segredo);
        exit(0);

    case 'verificar-auditoria':
        $r = jc_admin_auditoria_verificar($conn);
        $e = jc_admin_auditoria_conferir_log($conn, 60);
        $linhas = [];
        $linhas[] = $r['ok']
            ? "Cadeia íntegra: {$r['total']} registros conferidos." . ($r['ultimo_hash'] ? ' Último elo: ' . substr($r['ultimo_hash'], 0, 16) : '')
            : "PROBLEMA na cadeia a partir do registro #{$r['primeira_falha']}: {$r['detalhe']}";
        if ($e['conferidos'] === 0) {
            $linhas[] = 'Espelho no log: nenhum registro encontrado em logs/seguranca*.log para comparar.';
        } elseif ($e['ok']) {
            $linhas[] = "Espelho no log: {$e['conferidos']} registros citados em {$e['arquivos']} arquivo(s) — todos presentes e iguais no banco.";
        } else {
            if ($e['ausentes']) {
                $linhas[] = 'PROBLEMA: o log cita registros que não existem no banco: ' . jc_admin_lista_ids($e['ausentes'], 20)
                          . ' (removidos — ou o banco foi restaurado de um backup mais antigo que o log).';
            }
            if ($e['diferentes']) {
                $linhas[] = 'PROBLEMA: registros com hash diferente do citado no log: ' . jc_admin_lista_ids($e['diferentes'], 20) . '.';
            }
        }
        cli_sair(implode(PHP_EOL, $linhas), ($r['ok'] && $e['ok']) ? 0 : 1);

    default:
        cli_sair("Comando desconhecido: {$comando}. Use: php scripts/admin.php ajuda");
}
