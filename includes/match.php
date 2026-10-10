<?php
/* ============================================================
   includes/match.php

   Match tradicional em PHP — CÓPIA FIEL do algoritmo de
   IA/recomendar.py (rota /avaliar): mesmos critérios, pesos, regras,
   textos dos motivos e limites (0–100). Nenhum peso foi alterado.

   Para que serve:
   1. Fallback: com o Flask fora do ar, o Match continua calculando a
      nota.
   2. Composição da nota: os pontos de cada critério já eram calculados
      separadamente no algoritmo; aqui eles são devolvidos para a
      análise detalhada (pontos ÷ peso = nota da categoria). Nada é
      estimado: categorias sem critério no algoritmo (habilidades,
      experiência, distância, transporte) não recebem nota.
   A igualdade com o Python é conferida por um teste de paridade.

   Gemini (opcional) só ESCREVE a análise contextual — não altera a nota.
   ============================================================ */

require_once __DIR__ . '/locais.php';   // local efetivo da vaga

if (!function_exists('jc_match_calcular')) {

    /* ── Helpers com a mesma semântica do Python ─────────────────────────── */

    /** Classe de caracteres que o Python considera espaço (str.isspace). */
    function jc_py_ws(): string {
        return '\x{0009}-\x{000D}\x{001C}-\x{001F}\x{0020}\x{0085}\x{00A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}';
    }

    /** _str() do Python: (valor or "").lower().strip() */
    function jc_py_str($valor): string {
        if ($valor === null || $valor === false || $valor === '' || is_array($valor)) {
            return '';
        }
        $s = mb_strtolower((string) $valor, 'UTF-8');
        return preg_replace('/^[' . jc_py_ws() . ']+|[' . jc_py_ws() . ']+$/u', '', $s) ?? '';
    }

    /** str.split() do Python (sem argumentos). */
    function jc_py_split(string $s): array {
        return preg_split('/[' . jc_py_ws() . ']+/u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /** str.title() do Python (letra "com caixa" = propriedade Unicode Cased, como no Python). */
    function jc_py_title(string $s): string {
        static $temCased = null;
        if ($temCased === null) {
            $temCased = (@preg_match('/^\p{Cased}$/u', 'a') === 1);   // PCRE2 >= 10.40
        }
        $out = '';
        $anteriorTemCaixa = false;
        foreach (mb_str_split($s, 1, 'UTF-8') as $c) {
            $out .= $anteriorTemCaixa ? mb_strtolower($c, 'UTF-8') : mb_convert_case($c, MB_CASE_TITLE, 'UTF-8');
            $anteriorTemCaixa = $temCased
                ? (preg_match('/^\p{Cased}$/u', $c) === 1)
                : (mb_strtolower($c, 'UTF-8') !== mb_strtoupper($c, 'UTF-8'));
        }
        return $out;
    }

    /* ── Algoritmo (espelho de recomendar.py /avaliar) ───────────────────── */

    /** Pesos máximos de cada critério — os mesmos do Python. */
    function jc_match_pesos(): array {
        return ['localizacao' => 25, 'area' => 25, 'tecnico' => 25, 'escolaridade' => 15, 'contrato' => 10];
    }

    /**
     * $d: cidade_jovem, area_interesse, escolaridade_tipo, escolaridade_curso,
     *     escolaridade_status, cidade_vaga, area_vaga, tipo_contrato, descricao
     * Retorna score (0–100), pontos por critério, situação de cada critério e
     * os motivos (mesmos textos do Python).
     */
    function jc_match_calcular(array $d): array {
        $cidade_jovem   = jc_py_str($d['cidade_jovem']        ?? null);
        $cidade_vaga    = jc_py_str($d['cidade_vaga']         ?? null);
        $area_interesse = jc_py_str($d['area_interesse']      ?? null);
        $area_vaga      = jc_py_str($d['area_vaga']           ?? null);
        $tipo_ensino    = jc_py_str($d['escolaridade_tipo']   ?? null);
        $curso          = jc_py_str($d['escolaridade_curso']  ?? null);
        $status         = jc_py_str($d['escolaridade_status'] ?? null);
        $tipo_contrato  = jc_py_str($d['tipo_contrato']       ?? null);
        $descricao      = jc_py_str($d['descricao']           ?? null);

        $pontos  = ['localizacao' => 0, 'area' => 0, 'tecnico' => 0, 'escolaridade' => 0, 'contrato' => 0, 'ajuste' => 0];
        $sit     = [];
        $motivos = [];

        // 1. Localização (peso 25)
        if ($cidade_jovem !== '' && $cidade_vaga !== '') {
            if ($cidade_jovem === $cidade_vaga) {
                $pontos['localizacao'] = 25;
                $sit['localizacao'] = 'mesma_cidade';
                $motivos[] = 'A vaga está localizada na sua cidade';
            } else {
                $parecida = false;
                foreach (jc_py_split($cidade_jovem) as $p) {
                    if (mb_strlen($p, 'UTF-8') > 3 && str_contains($cidade_vaga, $p)) {
                        $parecida = true;
                        break;
                    }
                }
                if ($parecida) {
                    $pontos['localizacao'] = 12;
                    $sit['localizacao'] = 'nome_parecido';
                    $motivos[] = 'A vaga está em uma cidade próxima da sua';
                } else {
                    $sit['localizacao'] = 'outra_cidade';
                }
            }
        } else {
            $sit['localizacao'] = $cidade_jovem === '' ? ($cidade_vaga === '' ? 'sem_cidades' : 'sem_cidade_jovem') : 'sem_cidade_vaga';
        }

        // 2. Área de interesse × área da vaga (peso 25)
        if ($area_interesse !== '' && $area_vaga !== '') {
            if ($area_interesse === $area_vaga) {
                $pontos['area'] = 25;
                $sit['area'] = 'igual';
                $motivos[] = 'Área de interesse (' . jc_py_title($area_vaga) . ') compatível com a vaga';
            } elseif (str_contains($area_vaga, $area_interesse) || str_contains($area_interesse, $area_vaga)) {
                $pontos['area'] = 13;
                $sit['area'] = 'relacionada';
                $motivos[] = 'Área da vaga tem relação com seu interesse';
            } else {
                $sit['area'] = 'diferente';
            }
        } else {
            $sit['area'] = $area_interesse === '' ? 'sem_area_jovem' : 'sem_area_vaga';
        }

        // 3. Ensino técnico alinhado (peso 25)
        if ($tipo_ensino === 'tecnico' && $curso !== '') {
            $palavras = array_values(array_filter(jc_py_split($curso), fn($p) => mb_strlen($p, 'UTF-8') > 3));
            $hits     = count(array_filter($palavras, fn($p) => str_contains($descricao, $p) || str_contains($area_vaga, $p)));
            if ($hits >= 2 || str_contains($descricao, $curso) || str_contains($area_vaga, $curso)) {
                $pontos['tecnico'] = 25;
                $sit['tecnico'] = 'alinhado';
                $motivos[] = 'Seu curso técnico em ' . jc_py_title($curso) . ' é um diferencial para esta vaga';
            } elseif ($hits === 1) {
                $pontos['tecnico'] = 12;
                $sit['tecnico'] = 'parcial';
                $motivos[] = 'Seu curso técnico tem relação parcial com a vaga';
            } else {
                $pontos['tecnico'] = 5;
                $sit['tecnico'] = 'sem_relacao';
                $motivos[] = 'Ensino técnico é valorizado pelos empregadores';
            }
        } else {
            $sit['tecnico'] = 'sem_tecnico';
        }

        // 4. Status de escolaridade × tipo de contrato (peso 15)
        $eh_aprendiz = str_contains($tipo_contrato, 'jovem aprendiz') || str_contains($tipo_contrato, 'jovem_aprendiz');
        if ($eh_aprendiz) {
            if ($status === 'cursando') {
                $pontos['escolaridade'] = 15;
                $sit['escolaridade'] = 'aprendiz_cursando';
                $motivos[] = 'Você está no perfil ideal para Jovem Aprendiz (EM em curso)';
            } elseif ($status === 'completo') {
                $pontos['escolaridade'] = 8;
                $sit['escolaridade'] = 'aprendiz_completo';
                $motivos[] = 'Ensino Médio completo é aceito em vagas de Jovem Aprendiz';
            } else {
                $sit['escolaridade'] = 'sem_status';
            }
        } elseif ($status === 'completo') {
            $pontos['escolaridade'] = 10;
            $sit['escolaridade'] = 'outro_completo';
            $motivos[] = 'Ensino Médio concluído é um diferencial';
        } else {
            $sit['escolaridade'] = $status === '' ? 'sem_status' : 'outro_cursando';
        }

        // 5. Tipo de contrato (peso 10)
        if ($eh_aprendiz || str_contains($tipo_contrato, 'estágio')) {
            $pontos['contrato'] = 10;
            $sit['contrato'] = 'inicio_carreira';
            $motivos[] = 'Contrato adequado para início de carreira';
        } else {
            $sit['contrato'] = $tipo_contrato === '' ? 'sem_contrato' : 'outro_contrato';
        }

        // Penalidade: vaga exige conclusão, jovem ainda cursa
        if (str_contains($descricao, 'concluído') && $status === 'cursando') {
            $pontos['ajuste'] = -10;
            $motivos[] = 'Atenção: esta vaga pode preferir candidatos com EM concluído';
        }

        $soma  = array_sum($pontos);
        $score = max(0, min(100, $soma));

        return [
            'score'       => $score,
            'soma'        => $soma,
            'pontos'      => $pontos,
            'situacao'    => $sit,
            'motivos'     => $motivos,
            'eh_aprendiz' => $eh_aprendiz,
        ];
    }

    /** analise_estatica() do Python — mesmo texto para a mesma faixa. */
    function jc_match_texto_estatico(int $score): string {
        if ($score >= 80) {
            return 'Excelente compatibilidade! Seu perfil está muito alinhado com os requisitos desta vaga. Não perca esta oportunidade!';
        }
        if ($score >= 60) {
            return 'Boa compatibilidade! Vale muito a pena se candidatar. Você atende os principais critérios da vaga.';
        }
        if ($score >= 40) {
            return 'Compatibilidade razoável. Você atende parte dos requisitos — considere se candidatar e destaque seus pontos fortes.';
        }
        return 'Compatibilidade baixa com seu perfil atual. Explore outras vagas disponíveis que combinam melhor com você.';
    }

    /** Rótulo curto da faixa (mesmos limites do texto estático). */
    function jc_match_rotulo(int $score): string {
        return $score >= 80 ? 'Excelente compatibilidade'
             : ($score >= 60 ? 'Boa compatibilidade'
             : ($score >= 40 ? 'Compatibilidade razoável' : 'Compatibilidade baixa'));
    }

    /* ── Composição da nota (análise detalhada) ──────────────────────────── */

    /**
     * Categorias = os 5 critérios do algoritmo, com pontos reais.
     * $ctx (textos originais, para as explicações):
     *   quem ('jovem'|'empresa'), cidade_jovem, uf_jovem, local (jc_local_efetivo),
     *   area_interesse, area_vaga, curso, tipo_contrato
     */
    function jc_match_categorias(array $calc, array $ctx): array {
        $eu   = ($ctx['quem'] ?? 'jovem') === 'jovem';
        $t    = fn(string $voce, string $candidato) => $eu ? $voce : $candidato;
        $q    = fn($s) => trim((string) $s);
        $pesos = jc_match_pesos();
        $sit  = $calc['situacao'];
        $local = $ctx['local'] ?? [];
        $cv   = $q($local['cidade'] ?? '');
        $ufv  = $q($local['estado'] ?? '');
        $cvUf = $cv . ($ufv !== '' ? '/' . $ufv : '');
        $cj   = $q($ctx['cidade_jovem'] ?? '');
        $cjUf = $cj . ($q($ctx['uf_jovem'] ?? '') !== '' ? '/' . $q($ctx['uf_jovem']) : '');
        $ai   = $q($ctx['area_interesse'] ?? '');
        $av   = $q($ctx['area_vaga'] ?? '');
        $curso = $q($ctx['curso'] ?? '');
        $tc   = $q($ctx['tipo_contrato'] ?? '');

        $expl = [
            'localizacao' => [
                'mesma_cidade'     => $t("Você e o local de trabalho ficam em {$cvUf}.", "O candidato e o local de trabalho ficam em {$cvUf}."),
                'nome_parecido'    => "Cidades diferentes ({$cjUf} e {$cvUf}) com parte do nome em comum — isso não indica a distância entre elas.",
                'outra_cidade'     => $t("O local de trabalho fica em {$cvUf}; você informou {$cjUf}.", "O local de trabalho fica em {$cvUf}; o candidato informou {$cjUf}."),
                'sem_cidade_jovem' => $t('Você não informou sua cidade no perfil.', 'O candidato não informou a cidade no perfil.'),
                'sem_cidade_vaga'  => 'A vaga não tem cidade informada (nem no endereço da empresa).',
                'sem_cidades'      => 'Faltam a cidade do perfil e a cidade da vaga.',
            ],
            'area' => [
                'igual'          => $t("Sua área de interesse ({$ai}) é a mesma da vaga.", "A área de interesse do candidato ({$ai}) é a mesma da vaga."),
                'relacionada'    => $t("Sua área de interesse ({$ai}) tem relação com a área da vaga ({$av}).", "A área de interesse do candidato ({$ai}) tem relação com a área da vaga ({$av})."),
                'diferente'      => $t("Sua área de interesse ({$ai}) é diferente da área da vaga ({$av}).", "A área de interesse do candidato ({$ai}) é diferente da área da vaga ({$av})."),
                'sem_area_jovem' => $t('Você não informou uma área de interesse no perfil.', 'O candidato não informou área de interesse.'),
                'sem_area_vaga'  => 'A vaga não tem área definida.',
            ],
            'tecnico' => [
                'alinhado'    => $t("Seu curso técnico ({$curso}) aparece na descrição ou na área da vaga.", "O curso técnico do candidato ({$curso}) aparece na descrição ou na área da vaga."),
                'parcial'     => $t("Seu curso técnico ({$curso}) tem relação parcial com a vaga.", "O curso técnico do candidato ({$curso}) tem relação parcial com a vaga."),
                'sem_relacao' => $t("Seu curso técnico ({$curso}) não aparece na vaga, mas ainda conta pontos.", "O curso técnico do candidato ({$curso}) não aparece na vaga, mas ainda conta pontos."),
                'sem_tecnico' => 'Este critério só pontua para quem informou um curso técnico.',
            ],
            'escolaridade' => [
                'aprendiz_cursando' => 'Estar estudando é o perfil esperado em vagas de Jovem Aprendiz.',
                'aprendiz_completo' => 'Ensino concluído é aceito em vagas de Jovem Aprendiz, com pontuação parcial.',
                'outro_completo'    => 'Formação concluída conta pontos nesta vaga.',
                'outro_cursando'    => 'Nesta vaga (que não é de Jovem Aprendiz), só formação concluída pontua.',
                'sem_status'        => 'A situação da escolaridade não foi informada.',
            ],
            'contrato' => [
                'inicio_carreira' => "O contrato ({$tc}) é voltado para início de carreira.",
                'outro_contrato'  => "O contrato ({$tc}) não é de Jovem Aprendiz nem de Estágio.",
                'sem_contrato'    => 'A vaga não informou o tipo de contrato.',
            ],
        ];
        $nomes = ['localizacao' => 'Localização', 'area' => 'Área de interesse', 'tecnico' => 'Curso técnico',
                  'escolaridade' => 'Escolaridade', 'contrato' => 'Tipo de contrato'];
        $icones = ['localizacao' => 'map-pin', 'area' => 'briefcase', 'tecnico' => 'graduation-cap',
                   'escolaridade' => 'file-text', 'contrato' => 'clipboard-list'];

        $cats = [];
        foreach ($pesos as $id => $max) {
            $p = intval($calc['pontos'][$id] ?? 0);
            $cats[] = [
                'id'         => $id,
                'nome'       => $nomes[$id],
                'icone'      => $icones[$id],
                'pontos'     => $p,
                'maximo'     => $max,
                'percentual' => (int) round($p * 100 / $max),
                'explicacao' => $expl[$id][$sit[$id] ?? ''] ?? '',
            ];
        }
        return $cats;
    }

    /** Ajuste (penalidade) do algoritmo, se houver. */
    function jc_match_ajuste(array $calc, string $quem = 'jovem'): ?array {
        if (intval($calc['pontos']['ajuste'] ?? 0) === 0) {
            return null;
        }
        $explicacao = $quem === 'jovem'
            ? 'A descrição da vaga pede formação concluída e você está cursando.'
            : 'A descrição da vaga pede formação concluída e o candidato está cursando.';
        if (intval($calc['soma'] ?? 0) < 0) {
            $explicacao .= ' Como a soma ficou abaixo de zero, a nota final é 0 (o mínimo).';
        }
        return [
            'nome'       => 'Requisito de conclusão',
            'pontos'     => intval($calc['pontos']['ajuste']),
            'explicacao' => $explicacao,
        ];
    }

    /** Pontos fortes e o que reduziu a nota — derivados só das categorias. */
    function jc_match_destaques(array $cats, ?array $ajuste): array {
        $fortes = [];
        $reduz  = [];
        foreach ($cats as $c) {
            if ($c['pontos'] >= $c['maximo']) {
                $fortes[] = $c['nome'] . ': ' . $c['explicacao'];
            } else {
                $reduz[] = $c['nome'] . ' (' . $c['pontos'] . ' de ' . $c['maximo'] . '): ' . $c['explicacao'];
            }
        }
        if ($ajuste) {
            $reduz[] = $ajuste['nome'] . ' (' . $ajuste['pontos'] . ' pontos): ' . $ajuste['explicacao'];
        }
        return ['fortes' => $fortes, 'reduziram' => $reduz];
    }

    /* ── Análise contextual (Gemini via Flask) ───────────────────────────── */

    /** Categorias que a análise contextual pode comentar (lista permitida). */
    function jc_match_categorias_ia(): array {
        return ['Localização', 'Área de interesse', 'Curso técnico', 'Escolaridade', 'Tipo de contrato',
                'Habilidades', 'Experiência'];
    }

    /** Texto seguro: sem caracteres de controle, espaços normalizados, tamanho limitado. */
    function jc_match_texto_ia($v, int $max): string {
        if (!is_string($v)) {
            return '';
        }
        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
        $v = trim(preg_replace('/\s+/u', ' ', $v) ?? '');
        $v = str_replace(['**', '__', '##'], '', $v);
        return mb_substr($v, 0, $max, 'UTF-8');
    }

    /**
     * Valida a análise contextual devolvida pelo Flask (defesa em profundidade —
     * o Flask já valida). Retorna null se o formato não for o esperado.
     * Campos de nota/score são ignorados: a IA não altera a nota.
     */
    function jc_match_validar_ia($a): ?array {
        if (!is_array($a)) {
            return null;
        }
        $resumo = jc_match_texto_ia($a['resumo'] ?? null, 600);
        if ($resumo === '') {
            return null;
        }
        $lista = function ($v, int $n, int $max): array {
            if (!is_array($v)) {
                return [];
            }
            $out = [];
            foreach ($v as $item) {
                $t = jc_match_texto_ia($item, $max);
                if ($t !== '' && !in_array($t, $out, true)) {
                    $out[] = $t;
                }
                if (count($out) >= $n) {
                    break;
                }
            }
            return $out;
        };
        $conf = jc_match_texto_ia($a['confianca'] ?? '', 10);
        $conf = strtr(mb_strtolower($conf, 'UTF-8'), ['é' => 'e']);
        $permitidas = jc_match_categorias_ia();
        $cats = [];
        foreach ((is_array($a['categorias'] ?? null) ? $a['categorias'] : []) as $c) {
            if (!is_array($c)) {
                continue;
            }
            $nome = jc_match_texto_ia($c['nome'] ?? '', 40);
            $exp  = jc_match_texto_ia($c['explicacao'] ?? '', 280);
            if (in_array($nome, $permitidas, true) && $exp !== '' && !isset($cats[$nome])) {
                $cats[$nome] = ['nome' => $nome, 'explicacao' => $exp];
            }
        }
        return [
            'resumo'        => $resumo,
            'pontos_fortes' => $lista($a['pontos_fortes'] ?? null, 4, 200),
            'pontos_fracos' => $lista($a['pontos_fracos'] ?? null, 4, 200),
            'observacoes'   => $lista($a['observacoes'] ?? null, 3, 240),
            'confianca'     => in_array($conf, ['alta', 'media', 'baixa'], true) ? $conf : 'baixa',
            'categorias'    => array_values($cats),
        ];
    }

    /* ── Dados (banco) ───────────────────────────────────────────────────── */

    /** POST JSON para o Flask local. Devolve o JSON (array) se HTTP 200; senão null. */
    function jc_flask_post(string $rota, array $payload, int $timeout): ?array {
        if (!function_exists('curl_init')) {
            return null;
        }
        $corpo = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($corpo === false) {
            return null;
        }
        $inicio = microtime(true);
        $ch = curl_init('http://127.0.0.1:5000' . $rota);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $corpo,
            // X-Request-Id: o mesmo identificador aparece nos logs do PHP e do Flask
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json',
                                       'X-Request-Id: ' . (function_exists('jc_req_id') ? jc_req_id() : '')],
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        $result = curl_exec($ch);
        $errno  = curl_errno($ch);
        $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($errno || $result === false || $codigo !== 200) {
            if (function_exists('jc_log')) {
                jc_log('flask.falha', 'aviso', [
                    'rota'    => $rota,
                    'motivo'  => $errno === CURLE_OPERATION_TIMEDOUT ? 'timeout' : ($errno ? 'conexao' : 'http_' . $codigo),
                    'ms'      => intval((microtime(true) - $inicio) * 1000),
                ]);
            }
            return null;
        }
        $dec = json_decode($result, true);
        return is_array($dec) ? $dec : null;
    }

    /**
     * Dados que o Match usa. A cidade da vaga é a do LOCAL EFETIVO
     * (local da vaga → endereço principal da empresa).
     */
    function jc_match_carregar(mysqli $conn, int $jovem_id, int $vaga_id): ?array {
        $stmt = $conn->prepare(
            "SELECT cidade, estado, area_interesse, escolaridade_tipo, escolaridade_curso,
                    escolaridade_status, formacao
             FROM jovens WHERE id = ? LIMIT 1"
        );
        $stmt->bind_param("i", $jovem_id);
        $stmt->execute();
        $jovem = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $stmt = $conn->prepare(
            "SELECT titulo, area, tipo_contrato, carga_horaria, descricao FROM vagas WHERE id = ? LIMIT 1"
        );
        $stmt->bind_param("i", $vaga_id);
        $stmt->execute();
        $vaga = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$jovem || !$vaga) {
            return null;
        }
        $local = jc_local_efetivo($conn, $vaga_id);

        return [
            'jovem'   => $jovem,
            'vaga'    => $vaga,
            'local'   => $local,
            // mesmos campos que o PHP sempre enviou ao Flask (rota /avaliar)
            'entrada' => [
                'cidade_jovem'        => $jovem['cidade']              ?? '',
                'area_interesse'      => $jovem['area_interesse']      ?? '',
                'escolaridade_tipo'   => $jovem['escolaridade_tipo']   ?? '',
                'escolaridade_curso'  => $jovem['escolaridade_curso']  ?? '',
                'escolaridade_status' => $jovem['escolaridade_status'] ?? '',
                'cidade_vaga'         => $local['cidade']              ?? '',
                'area_vaga'           => $vaga['area']                 ?? '',
                'tipo_contrato'       => $vaga['tipo_contrato']        ?? '',
                'descricao'           => $vaga['descricao']            ?? '',
            ],
        ];
    }

    /**
     * Dados enviados à análise contextual — só o necessário:
     * SEM nome, e-mail, CPF, telefone, data de nascimento, CEP/rua do jovem,
     * resumo, currículo, IDs, nome da empresa, salário ou outros endereços.
     * Local: só o local efetivo da vaga (tipo, bairro, cidade e UF).
     */
    function jc_match_payload_ia(mysqli $conn, int $jovem_id, int $vaga_id, array $dados, array $calc,
                                 array $cats, ?array $ajuste, string $leitor = 'jovem'): array {
        $corta = fn($s, int $n) => mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $s) ?? ''), 0, $n, 'UTF-8');
        $lista = function (string $sql, int $id, callable $mapa, int $limite) use ($conn): array {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $linhas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return array_slice(array_values(array_filter(array_map($mapa, $linhas), fn($x) => $x !== '')), 0, $limite);
        };
        $j = $dados['jovem'];
        $v = $dados['vaga'];
        $l = $dados['local'];
        return [
            'leitor' => $leitor === 'empresa' ? 'empresa' : 'jovem',
            'jovem'  => [
                'cidade'               => $corta($j['cidade'] ?? '', 100),
                'uf'                   => $corta($j['estado'] ?? '', 2),
                'area_de_interesse'    => $corta($j['area_interesse'] ?? '', 100),
                'formacao'             => $corta($j['formacao'] ?? '', 100),
                'situacao_escolar'     => ($j['escolaridade_status'] ?? '') === 'completo' ? 'concluído' : 'cursando',
                'curso_tecnico'        => ($j['escolaridade_tipo'] ?? '') === 'tecnico' ? $corta($j['escolaridade_curso'] ?? '', 80) : '',
                'habilidades'          => $lista("SELECT habilidade FROM habilidades_jovem WHERE jovem_id = ? ORDER BY id",
                                                 $jovem_id, fn($r) => $corta($r['habilidade'] ?? '', 60), 15),
                'experiencias'         => $lista("SELECT titulo, periodo FROM experiencias_jovem WHERE jovem_id = ? ORDER BY id",
                                                 $jovem_id, fn($r) => trim($corta($r['titulo'] ?? '', 80)
                                                     . (!empty($r['periodo']) ? ' (' . $corta($r['periodo'], 40) . ')' : '')), 5),
                'formacoes_adicionais' => $lista("SELECT titulo, conclusao FROM formacao_jovem WHERE jovem_id = ? ORDER BY id",
                                                 $jovem_id, fn($r) => trim($corta($r['titulo'] ?? '', 100)
                                                     . (!empty($r['conclusao']) ? ' — ' . $corta($r['conclusao'], 20) : '')), 5),
            ],
            'vaga' => [
                'titulo'            => $corta($v['titulo'] ?? '', 150),
                'area'              => $corta($v['area'] ?? '', 100),
                'tipo_de_contrato'  => $corta($v['tipo_contrato'] ?? '', 50),
                'carga_horaria'     => $corta($v['carga_horaria'] ?? '', 20),
                'descricao'         => $corta($v['descricao'] ?? '', 1500),
                'requisitos'        => $lista("SELECT requisito FROM requisitos_vaga WHERE vaga_id = ? ORDER BY id",
                                              $vaga_id, fn($r) => $corta($r['requisito'] ?? '', 100), 15),
                'local_de_trabalho' => [
                    'tipo'              => $corta($l['rotulo'] ?? '', 120),
                    'bairro'            => $corta($l['bairro'] ?? '', 100),
                    'cidade'            => $corta($l['cidade'] ?? '', 100),
                    'uf'                => $corta($l['estado'] ?? '', 2),
                    'endereco_completo' => ($l['nivel'] ?? '') === 'completo',
                ],
            ],
            'resultado_do_match' => [
                'nota'      => intval($calc['score']),
                'criterios' => array_map(fn($c) => ['nome' => $c['nome'], 'pontos' => $c['pontos'],
                                                    'maximo' => $c['maximo'], 'explicacao' => $c['explicacao']], $cats),
                'ajuste'    => $ajuste ? ['nome' => $ajuste['nome'], 'pontos' => $ajuste['pontos'],
                                          'explicacao' => $ajuste['explicacao']] : null,
            ],
            'dados_indisponiveis' => ['distância em km', 'transporte público', 'tempo de deslocamento'],
        ];
    }
}
