<?php
/* ============================================================
   includes/locais.php

   Local de trabalho das vagas.

   - Endereço principal da empresa = colunas da tabela `empresas`
     (cep, logradouro, numero, complemento, bairro, cidade, estado).
     É a sede e o local padrão das vagas. Não é copiado
     para outra tabela.
   - Outros locais da empresa (escritório, filial, unidade ou outro)
     ficam em `empresa_locais`, com o mesmo formato de endereço.
   - Cada vaga diz onde é o trabalho:
       vagas.local_trabalho = 'empresa' → endereço principal da empresa
       vagas.local_trabalho = 'local'   → vagas.local_id (empresa_locais)
       vagas.local_trabalho = NULL      → vaga antiga: usa a
                                          cidade/UF/CEP digitados na vaga;
                                          sem eles, o endereço da empresa.
     vagas.cidade/estado/cep continuam preenchidos (cópia do local),
     porque a busca e as listas de vagas usam essas colunas.

   Local efetivo (usado no Match, na análise e na página da vaga):
     local da vaga → endereço principal da empresa → indisponível.
   Nada é inventado: partes que faltam ficam de fora.

   Depende da migração ADITIVA
   migrations/2026-10-08_stage51_locais_trabalho.sql.
   Sem ela, a criação de vagas continua como antes e o local efetivo
   é a cidade da vaga (ou o endereço da empresa, se a vaga não tiver).
   ============================================================ */
require_once __DIR__ . '/endereco_empresa.php';

if (!function_exists('jc_locais_disponiveis')) {

    /** Tipos de local além da sede (endereço principal). */
    function jc_local_tipos(): array {
        return [
            'escritorio' => 'Escritório',
            'filial'     => 'Filial',
            'unidade'    => 'Unidade',
            'outro'      => 'Outro local',
        ];
    }

    /** true quando as migrações de locais de trabalho e de endereço da empresa já foram aplicadas. */
    function jc_locais_disponiveis(mysqli $conn): bool {
        static $ok = null;
        if ($ok === null) {
            $t  = $conn->query("SHOW TABLES LIKE 'empresa_locais'");
            $c  = $conn->query("SHOW COLUMNS FROM vagas LIKE 'local_trabalho'");
            $ok = ($t instanceof mysqli_result) && $t->num_rows > 0
               && ($c instanceof mysqli_result) && $c->num_rows > 0
               && empresa_tem_endereco($conn);
        }
        return $ok;
    }

    /** Texto do POST como string, cortado no tamanho da coluna (ignora arrays). */
    function jc_post_texto(array $post, string $chave, int $max): string {
        $v = $post[$chave] ?? '';
        return is_string($v) ? mb_substr(trim($v), 0, $max) : '';
    }

    /** Campos de um local vindos de um formulário (prefixo opcional, ex. "local_"). */
    function jc_local_do_post(array $post, string $p = ''): array {
        $tipo = jc_post_texto($post, $p . 'tipo', 20);
        return [
            'tipo'        => array_key_exists($tipo, jc_local_tipos()) ? $tipo : 'escritorio',
            'nome'        => jc_post_texto($post, $p . 'nome', 100),
            'cep'         => substr(preg_replace('/\D/', '', jc_post_texto($post, $p . 'cep', 20)), 0, 8),
            'logradouro'  => jc_post_texto($post, $p . 'logradouro', 150),
            'numero'      => jc_post_texto($post, $p . 'numero', 10),
            'complemento' => jc_post_texto($post, $p . 'complemento', 100),
            'bairro'      => jc_post_texto($post, $p . 'bairro', 100),
            'cidade'      => jc_post_texto($post, $p . 'cidade', 100),
            'estado'      => strtoupper(jc_post_texto($post, $p . 'estado', 2)),
        ];
    }

    /** Mensagem de erro do local (null = válido). Exige ao menos cidade e UF. */
    function jc_local_erro(array $l): ?string {
        if ($l['cep'] !== '' && strlen($l['cep']) !== 8) {
            return 'CEP inválido: use os 8 dígitos ou deixe em branco.';
        }
        if ($l['cidade'] === '' || !preg_match('/^[A-Z]{2}$/', $l['estado'])) {
            return 'Informe pelo menos a cidade e a UF do local de trabalho.';
        }
        return null;
    }

    /** "Escritório — Unidade Augusta" (ou só o tipo, se não tiver nome). */
    function jc_local_rotulo(string $tipo, ?string $nome): string {
        $t = jc_local_tipos()[$tipo] ?? 'Local';
        $n = trim((string) $nome);
        return $n !== '' ? $t . ' — ' . $n : $t;
    }

    /** CEP só com dígitos → "00000-000" (null se não tiver 8 dígitos). */
    function jc_cep_formatado(?string $cep): ?string {
        $d = preg_replace('/\D/', '', (string) $cep);
        return strlen($d) === 8 ? substr($d, 0, 5) . '-' . substr($d, 5) : null;
    }

    /** Locais da empresa (fora a sede), com quantas vagas usam cada um. */
    function jc_locais_da_empresa(mysqli $conn, int $empresa_id): array {
        if (!jc_locais_disponiveis($conn)) {
            return [];
        }
        $stmt = $conn->prepare(
            "SELECT l.id, l.tipo, l.nome, l.cep, l.logradouro, l.numero, l.complemento,
                    l.bairro, l.cidade, l.estado,
                    (SELECT COUNT(*) FROM vagas v WHERE v.local_id = l.id AND v.local_trabalho = 'local') AS em_uso
             FROM empresa_locais l
             WHERE l.empresa_id = ?
             ORDER BY l.criado_em, l.id"
        );
        $stmt->bind_param("i", $empresa_id);
        $stmt->execute();
        $linhas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $linhas;
    }

    /** Endereço principal (sede) da empresa no mesmo formato de um local. */
    function jc_endereco_principal(mysqli $conn, int $empresa_id): array {
        $cols = empresa_tem_endereco($conn) ? ', cep, logradouro, numero, complemento, bairro' : '';
        $stmt = $conn->prepare("SELECT cidade, estado{$cols} FROM empresas WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $empresa_id);
        $stmt->execute();
        $e = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
        return jc_local_partes($e, '');
    }

    /** Extrai as 7 partes de endereço de uma linha (com prefixo de coluna). */
    function jc_local_partes(array $r, string $pre): array {
        $s = fn(string $k) => trim((string) ($r[$pre . $k] ?? ''));
        return [
            'cep'         => preg_replace('/\D/', '', $s('cep')),
            'logradouro'  => $s('logradouro'),
            'numero'      => $s('numero'),
            'complemento' => $s('complemento'),
            'bairro'      => $s('bairro'),
            'cidade'      => $s('cidade'),
            'estado'      => strtoupper($s('estado')),
        ];
    }

    /**
     * Resolve o local efetivo a partir de uma linha com:
     *   cidade, estado, cep (da vaga) · e_* (endereço principal da empresa)
     *   local_trabalho, local_id, l_* (local associado, se existir)
     * Retorna as partes do endereço + origem, rótulo, nível e texto formatado.
     */
    function jc_local_resolver(array $r): array {
        $lt = $r['local_trabalho'] ?? null;
        $temLocal = $lt === 'local' && !empty($r['local_id']) && array_key_exists('l_tipo', $r) && $r['l_tipo'] !== null;

        if ($temLocal) {
            $end    = jc_local_partes($r, 'l_');
            $origem = 'local';
            $rotulo = jc_local_rotulo((string) $r['l_tipo'], $r['l_nome'] ?? '');
        } elseif ($lt === 'empresa') {
            $end    = jc_local_partes($r, 'e_');
            $origem = 'empresa';
            $rotulo = 'Endereço principal da empresa';
        } else {
            // Vaga antiga (ou local removido): campos da própria vaga
            $proprio = jc_local_partes($r, '');
            $proprio['logradouro'] = $proprio['numero'] = $proprio['complemento'] = $proprio['bairro'] = '';
            if ($proprio['cidade'] !== '' || $proprio['cep'] !== '') {
                $end    = $proprio;
                $origem = 'vaga';
                $rotulo = 'Endereço informado na vaga';
            } else {
                $end    = jc_local_partes($r, 'e_');
                $origem = 'empresa';
                $rotulo = 'Endereço principal da empresa';
            }
        }

        $temCidade = $end['cidade'] !== '';
        $temRua    = $end['logradouro'] !== '';
        if ($temRua && $end['numero'] !== '' && $temCidade && $end['estado'] !== '') {
            $nivel = 'completo';
        } elseif ($temCidade || $end['cep'] !== '' || $temRua) {
            $nivel = 'parcial';
        } else {
            $nivel = 'indisponivel';
        }

        $faltando = [];
        foreach (['logradouro' => 'rua', 'numero' => 'número', 'bairro' => 'bairro',
                  'cidade' => 'cidade', 'estado' => 'UF', 'cep' => 'CEP'] as $k => $nome) {
            if ($end[$k] === '') {
                $faltando[] = $nome;
            }
        }

        return $end + [
            'origem'   => $nivel === 'indisponivel' ? 'indisponivel' : $origem,
            'rotulo'   => $nivel === 'indisponivel' ? 'Local de trabalho não informado' : $rotulo,
            'nivel'    => $nivel,
            'faltando' => $faltando,
            'texto'    => endereco_empresa_formatado($end),
        ];
    }

    /** Local efetivo de uma vaga (consulta o banco). [] se a vaga não existir. */
    function jc_local_efetivo(mysqli $conn, int $vaga_id): array {
        $temE = empresa_tem_endereco($conn);
        $temL = jc_locais_disponiveis($conn);
        $colsE = $temE ? ', e.cep AS e_cep, e.logradouro AS e_logradouro, e.numero AS e_numero,
                           e.complemento AS e_complemento, e.bairro AS e_bairro' : '';
        $colsL = $temL ? ', v.local_trabalho, v.local_id, l.tipo AS l_tipo, l.nome AS l_nome, l.cep AS l_cep,
                           l.logradouro AS l_logradouro, l.numero AS l_numero, l.complemento AS l_complemento,
                           l.bairro AS l_bairro, l.cidade AS l_cidade, l.estado AS l_estado' : '';
        $join  = $temL ? ' LEFT JOIN empresa_locais l ON l.id = v.local_id AND l.empresa_id = v.empresa_id' : '';
        $stmt = $conn->prepare(
            "SELECT v.cidade, v.estado, v.cep, e.cidade AS e_cidade, e.estado AS e_estado {$colsE} {$colsL}
             FROM vagas v JOIN empresas e ON e.id = v.empresa_id {$join}
             WHERE v.id = ? LIMIT 1"
        );
        $stmt->bind_param("i", $vaga_id);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $r ? jc_local_resolver($r) : [];
    }

    /** Atualiza a cópia de cidade/UF/CEP das vagas que usam o endereço principal. */
    function jc_vagas_sincronizar_empresa(mysqli $conn, int $empresa_id): void {
        if (!jc_locais_disponiveis($conn)) {
            return;
        }
        $e   = jc_endereco_principal($conn, $empresa_id);
        $cep = jc_cep_formatado($e['cep']);
        $cid = $e['cidade'] !== '' ? $e['cidade'] : null;
        $uf  = $e['estado'] !== '' ? $e['estado'] : null;
        $stmt = $conn->prepare(
            "UPDATE vagas SET cidade = ?, estado = ?, cep = ? WHERE empresa_id = ? AND local_trabalho = 'empresa'"
        );
        $stmt->bind_param("sssi", $cid, $uf, $cep, $empresa_id);
        $stmt->execute();
        $stmt->close();
    }

    /** Atualiza a cópia de cidade/UF/CEP das vagas que usam um local da empresa. */
    function jc_vagas_sincronizar_local(mysqli $conn, int $empresa_id, int $local_id, array $l): void {
        if (!jc_locais_disponiveis($conn)) {
            return;
        }
        $cep = jc_cep_formatado($l['cep']);
        $cid = $l['cidade'] !== '' ? $l['cidade'] : null;
        $uf  = $l['estado'] !== '' ? $l['estado'] : null;
        $stmt = $conn->prepare(
            "UPDATE vagas SET cidade = ?, estado = ?, cep = ?
             WHERE empresa_id = ? AND local_id = ? AND local_trabalho = 'local'"
        );
        $stmt->bind_param("sssii", $cid, $uf, $cep, $empresa_id, $local_id);
        $stmt->execute();
        $stmt->close();
    }

    /** Grava um local novo da empresa e devolve o id (0 se falhar). */
    function jc_local_inserir(mysqli $conn, int $empresa_id, array $l): int {
        $cep = $l['cep'] !== '' ? $l['cep'] : null;
        $stmt = $conn->prepare(
            "INSERT INTO empresa_locais
                 (empresa_id, tipo, nome, cep, logradouro, numero, complemento, bairro, cidade, estado)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $nome = $l['nome'] !== '' ? $l['nome'] : null;
        $stmt->bind_param("isssssssss", $empresa_id, $l['tipo'], $nome, $cep, $l['logradouro'],
                          $l['numero'], $l['complemento'], $l['bairro'], $l['cidade'], $l['estado']);
        $ok = $stmt->execute();
        $id = $ok ? intval($conn->insert_id) : 0;
        $stmt->close();
        return $id;
    }
}
