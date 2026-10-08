<?php
/* ============================================================
   includes/formacao.php  (Stage 5)

   Formação estruturada: NÍVEL → CURSO/ANO → SITUAÇÃO.
   Antes o jovem digitava qualquer texto em "Ano Escolar / Conclusão" e
   em "Curso / Nível" — o texto livre era tratado como curso.

   Sem alterar o banco (nenhuma migração): usa as colunas que já existem
     jovens.formacao            → texto padronizado, ex. "Ensino Médio — 3º ano"
     jovens.escolaridade_status → cursando | completo        (situação)
     jovens.escolaridade_tipo   → tecnico | regular          (usado pelo Match)
     jovens.escolaridade_curso  → nome do curso técnico      (usado pelo Match)
     formacao_jovem.titulo / conclusao / area                (formações adicionais)
   O Match continua recebendo exatamente os mesmos campos (tipo, curso e
   status) — nenhum peso ou regra mudou.

   Dados antigos: nada é apagado nem convertido no banco. Ao editar, o
   formulário reconhece os textos padronizados; texto antigo que não dá
   para reconhecer aparece em "Outro" (o jovem confere e salva).
   POST no formato antigo (sem "formacao_nivel") segue o caminho antigo.
   ============================================================ */

const JC_FORMACAO_SEP = ' — ';

const JC_FORMACAO_NIVEIS = [
    'medio'              => 'Ensino Médio',
    'tecnico'            => 'Ensino Técnico',
    'superior'           => 'Ensino Superior',
    'profissionalizante' => 'Curso profissionalizante',
    'outro'              => 'Outro',
];

const JC_FORMACAO_ANOS = [1 => '1º ano', 2 => '2º ano', 3 => '3º ano'];

// Ensino Técnico: as MESMAS áreas que o cadastro já oferecia (valores mantidos — o Match lê o curso)
const JC_CURSOS_TECNICOS = [
    'Administração' => 'Administração',
    'TI'            => 'Tecnologia da Informação (TI)',
    'Logística'     => 'Logística',
    'Enfermagem'    => 'Enfermagem',
    'Mecânica'      => 'Mecânica',
    'Contabilidade' => 'Contabilidade',
    'Marketing'     => 'Marketing',
];

const JC_CURSOS_SUPERIORES = [
    'Administração'                         => 'Administração',
    'Análise e Desenvolvimento de Sistemas' => 'Análise e Desenvolvimento de Sistemas',
    'Ciência da Computação'                 => 'Ciência da Computação',
    'Ciências Contábeis'                    => 'Ciências Contábeis',
    'Direito'                               => 'Direito',
    'Enfermagem'                            => 'Enfermagem',
    'Engenharia Civil'                      => 'Engenharia Civil',
    'Engenharia de Produção'                => 'Engenharia de Produção',
    'Engenharia de Software'                => 'Engenharia de Software',
    'Engenharia Elétrica'                   => 'Engenharia Elétrica',
    'Engenharia Mecânica'                   => 'Engenharia Mecânica',
    'Gestão de Recursos Humanos'            => 'Gestão de Recursos Humanos',
    'Logística'                             => 'Logística',
    'Marketing'                             => 'Marketing',
    'Pedagogia'                             => 'Pedagogia',
    'Psicologia'                            => 'Psicologia',
    'Sistemas de Informação'                => 'Sistemas de Informação',
];

const JC_CURSOS_PROFISSIONALIZANTES = [
    'Assistente Administrativo' => 'Assistente Administrativo',
    'Atendimento ao Cliente'    => 'Atendimento ao Cliente',
    'Auxiliar de Logística'     => 'Auxiliar de Logística',
    'Informática'               => 'Informática',
    'Marketing Digital'         => 'Marketing Digital',
    'Recursos Humanos'          => 'Recursos Humanos',
    'Vendas e Varejo'           => 'Vendas e Varejo',
];

const JC_FORMACAO_OUTRO_MAX = 70;   // "Curso profissionalizante — " + 70 cabe nos 100 de jovens.formacao
const JC_FORMACAO_LIVRE_MAX = 90;   // texto de "Outro" (nível)

/** Listas de curso por nível: [campo do formulário, opções, nome usado nas mensagens] */
function jc_formacao_cursos(string $nivel): ?array
{
    switch ($nivel) {
        case 'tecnico':            return ['curso_tecnico',  JC_CURSOS_TECNICOS,            'a área do curso técnico'];
        case 'superior':           return ['curso_superior', JC_CURSOS_SUPERIORES,          'o curso superior'];
        case 'profissionalizante': return ['curso_prof',     JC_CURSOS_PROFISSIONALIZANTES, 'o curso profissionalizante'];
    }
    return null;
}

/** Texto digitado: sem caracteres de controle, espaços normalizados, tamanho limitado */
function jc_formacao_texto($valor, int $max): string
{
    $t = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string)$valor);
    $t = trim(preg_replace('/\s+/u', ' ', $t ?? ''));
    return mb_substr($t, 0, $max);
}

/** Texto padronizado gravado em jovens.formacao e formacao_jovem.titulo */
function jc_formacao_rotulo(string $nivel, ?int $ano, string $curso, string $outro): string
{
    switch ($nivel) {
        case 'medio':              return 'Ensino Médio' . JC_FORMACAO_SEP . JC_FORMACAO_ANOS[$ano];
        case 'tecnico':            return 'Ensino Técnico' . JC_FORMACAO_SEP . $curso;
        case 'superior':           return 'Ensino Superior' . JC_FORMACAO_SEP . $curso;
        case 'profissionalizante': return 'Curso profissionalizante' . JC_FORMACAO_SEP . $curso;
        case 'outro':              return $outro;
    }
    return '';
}

/**
 * Lê o formulário estruturado.
 *   $p        prefixo dos campos ('' no cadastro/perfil, 'form_' na formação adicional)
 *   $campoSit nome do grupo de situação ('escolaridade_status' ou 'form_situacao')
 * Retorna null quando o POST não é do formulário novo (sem "{$p}formacao_nivel").
 * Senão: ['nivel','ano','curso','outro','em_tecnico','curso_tecnico','situacao','rotulo','tipo','valores']
 * ('valores' repopula o formulário quando há erro). Erros vão para $erros.
 */
function jc_formacao_ler_post(array $post, array &$erros, string $p = '', string $campoSit = 'escolaridade_status',
                              bool $obrigatorio = true, bool $comEmTecnico = true): ?array
{
    if (!array_key_exists($p . 'formacao_nivel', $post)) {
        return null;
    }
    $nivel    = (string)$post[$p . 'formacao_nivel'];
    $situacao = in_array($post[$campoSit] ?? '', ['cursando', 'completo'], true) ? $post[$campoSit] : 'cursando';

    $r = ['nivel' => '', 'ano' => null, 'curso' => '', 'outro' => '', 'em_tecnico' => false,
          'curso_tecnico' => '', 'situacao' => $situacao, 'rotulo' => '', 'tipo' => 'regular'];

    // valores digitados/escolhidos (para repopular o formulário)
    $v = jc_formacao_valores_vazios();
    $v['nivel']      = isset(JC_FORMACAO_NIVEIS[$nivel]) ? $nivel : '';
    $v['ano']        = (int)($post[$p . 'formacao_ano'] ?? 0) ?: null;
    $v['em_tecnico'] = $comEmTecnico && !empty($post[$p . 'em_tecnico']);
    $v['situacao']   = $situacao;
    $v['outro']      = jc_formacao_texto($post[$p . 'formacao_outro'] ?? '', JC_FORMACAO_LIVRE_MAX);
    foreach (['curso_tecnico', 'curso_superior', 'curso_prof'] as $c) {
        $v[$c]            = (string)($post[$p . $c] ?? '');
        $v[$c . '_outro'] = jc_formacao_texto($post[$p . $c . '_outro'] ?? '', JC_FORMACAO_OUTRO_MAX);
    }
    $r['valores'] = $v;

    if ($nivel === '') {
        if ($obrigatorio) {
            $erros[] = 'Selecione o nível de formação.';
        }
        return $r;
    }
    if (!isset(JC_FORMACAO_NIVEIS[$nivel])) {
        $erros[] = 'Nível de formação inválido.';
        return $r;
    }
    $r['nivel'] = $nivel;

    $lerCurso = function (string $campo, array $lista, string $nome) use ($v, &$erros): string {
        $escolha = $v[$campo];
        if ($escolha === 'Outro') {
            if ($v[$campo . '_outro'] === '') {
                $erros[] = 'Informe ' . $nome . ' (campo "Qual?").';
            }
            return $v[$campo . '_outro'];
        }
        if (!isset($lista[$escolha])) {
            $erros[] = 'Selecione ' . $nome . '.';
            return '';
        }
        return $escolha;
    };

    if ($nivel === 'medio') {
        if (!isset(JC_FORMACAO_ANOS[$v['ano'] ?? 0])) {
            $erros[] = 'Selecione o ano do Ensino Médio.';
        } else {
            $r['ano'] = $v['ano'];
        }
        if ($v['em_tecnico']) {
            $r['em_tecnico']    = true;
            $r['curso_tecnico'] = $lerCurso('curso_tecnico', JC_CURSOS_TECNICOS, 'a área do curso técnico');
        }
    } elseif ($nivel === 'outro') {
        if ($v['outro'] === '') {
            $erros[] = 'Descreva a sua formação em "Outro".';
        }
        $r['outro'] = $v['outro'];
    } else {
        [$campo, $lista, $nome] = jc_formacao_cursos($nivel);
        $r['curso'] = $lerCurso($campo, $lista, $nome);
        if ($nivel === 'tecnico') {
            $r['curso_tecnico'] = $r['curso'];
        }
    }

    switch ($nivel) {
        case 'medio': $completo = ($r['ano'] !== null);   break;
        case 'outro': $completo = ($r['outro'] !== '');   break;
        default:      $completo = ($r['curso'] !== '');
    }
    $r['rotulo'] = $completo ? jc_formacao_rotulo($nivel, $r['ano'], $r['curso'], $r['outro']) : '';
    $r['tipo']   = ($r['curso_tecnico'] !== '') ? 'tecnico' : 'regular';
    return $r;
}

function jc_formacao_valores_vazios(): array
{
    return ['nivel' => '', 'ano' => null, 'em_tecnico' => false, 'situacao' => 'cursando', 'outro' => '',
            'curso_tecnico' => '', 'curso_tecnico_outro' => '', 'curso_superior' => '', 'curso_superior_outro' => '',
            'curso_prof' => '', 'curso_prof_outro' => '', 'legado' => ''];
}

/** Coloca um curso no par select/"Qual?" (curso fora da lista → "Outro") */
function jc_formacao_por_curso(array &$v, string $campo, array $lista, string $curso): void
{
    if ($curso === '') {
        return;
    }
    if (isset($lista[$curso])) {
        $v[$campo] = $curso;
    } else {
        $v[$campo] = 'Outro';
        $v[$campo . '_outro'] = jc_formacao_texto($curso, JC_FORMACAO_OUTRO_MAX);
    }
}

/** Valores do formulário a partir do registro do jovem (perfil) — reconhece o texto padronizado */
function jc_formacao_de_jovem(array $j): array
{
    $v = jc_formacao_valores_vazios();
    $f        = trim((string)($j['formacao'] ?? ''));
    $tipo     = (string)($j['escolaridade_tipo'] ?? 'regular');
    $cursoTec = trim((string)($j['escolaridade_curso'] ?? ''));
    $v['situacao'] = (($j['escolaridade_status'] ?? '') === 'completo') ? 'completo' : 'cursando';

    $prefixos = ['tecnico' => 'Ensino Técnico', 'superior' => 'Ensino Superior',
                 'profissionalizante' => 'Curso profissionalizante'];
    if (preg_match('/^Ensino Médio — ([123])º ano$/u', $f, $m)) {
        $v['nivel'] = 'medio';
        $v['ano']   = (int)$m[1];
        if ($tipo === 'tecnico') {
            $v['em_tecnico'] = true;
            jc_formacao_por_curso($v, 'curso_tecnico', JC_CURSOS_TECNICOS, $cursoTec);
        }
        return $v;
    }
    foreach ($prefixos as $nivel => $nome) {
        $pre = $nome . JC_FORMACAO_SEP;
        if (mb_strpos($f, $pre) === 0) {
            [$campo, $lista] = jc_formacao_cursos($nivel);
            $v['nivel'] = $nivel;
            jc_formacao_por_curso($v, $campo, $lista, ($nivel === 'tecnico' && $cursoTec !== '') ? $cursoTec : mb_substr($f, mb_strlen($pre)));
            return $v;
        }
    }

    // ── texto antigo (livre) ──────────────────────────────────────────────
    $v['legado'] = $f;
    $semAcento = strtolower(strtr($f, ['é' => 'e', 'É' => 'e', 'ê' => 'e', 'º' => 'o', '°' => 'o']));
    $pareceEM  = (bool)preg_match('/\bmedio\b|\b[123]\s*o?\s*(ano|serie)\b/u', $semAcento);
    if ($f === '' && $tipo !== 'tecnico') {
        return $v;                                    // não informado
    }
    if ($pareceEM) {
        $v['nivel'] = 'medio';
        if (preg_match('/\b([123])\s*o?\s*(ano|serie)\b/u', $semAcento, $m)) {
            $v['ano'] = (int)$m[1];
        } elseif (preg_match('/complet|conclu/u', $semAcento)) {
            $v['ano'] = 3;                            // "Ensino Médio Completo" = concluiu o 3º ano
        }
        if ($tipo === 'tecnico') {
            $v['em_tecnico'] = true;
            jc_formacao_por_curso($v, 'curso_tecnico', JC_CURSOS_TECNICOS, $cursoTec);
        }
        return $v;
    }
    if ($tipo === 'tecnico') {
        $v['nivel'] = 'tecnico';
        jc_formacao_por_curso($v, 'curso_tecnico', JC_CURSOS_TECNICOS, $cursoTec);
        return $v;
    }
    $v['nivel'] = 'outro';
    $v['outro'] = jc_formacao_texto($f, JC_FORMACAO_LIVRE_MAX);
    return $v;
}

/**
 * true quando o jovem não mexeu na formação ao salvar o perfil (os campos enviados são
 * os mesmos que o formulário mostrou a partir do banco). Nesse caso o registro fica como
 * está — inclusive texto antigo — e salvar só o resumo/cidade não reescreve nem bloqueia nada.
 */
function jc_formacao_inalterado(array $enviado, array $mostrado): bool
{
    foreach (['nivel', 'ano', 'em_tecnico', 'situacao', 'outro', 'curso_tecnico', 'curso_tecnico_outro',
              'curso_superior', 'curso_superior_outro', 'curso_prof', 'curso_prof_outro'] as $k) {
        $a = $enviado[$k] ?? null;
        $b = $mostrado[$k] ?? null;
        if (in_array($k, ['curso_superior', 'curso_superior_outro', 'curso_prof', 'curso_prof_outro', 'curso_tecnico', 'curso_tecnico_outro', 'outro'], true)) {
            // listas que nem aparecem no nível mostrado não contam
            $usado = ['curso_tecnico' => in_array($mostrado['nivel'], ['tecnico', 'medio'], true),
                      'curso_superior' => $mostrado['nivel'] === 'superior',
                      'curso_prof' => $mostrado['nivel'] === 'profissionalizante',
                      'outro' => $mostrado['nivel'] === 'outro'];
            $base = preg_replace('/_outro$/', '', $k);
            if (empty($usado[$base])) {
                continue;
            }
            if ($base === 'curso_tecnico' && $mostrado['nivel'] === 'medio' && empty($mostrado['em_tecnico'])) {
                continue;
            }
        }
        if ((string)($a ?? '') !== (string)($b ?? '')) {
            return false;
        }
    }
    return true;
}

/** Resumo legível da escolaridade principal (perfil, visão da empresa) */
function jc_formacao_resumo(array $j): string
{
    $sit = (($j['escolaridade_status'] ?? '') === 'completo') ? 'Concluído' : 'Cursando';
    $v   = jc_formacao_de_jovem($j);
    if ($v['legado'] === '' && $v['nivel'] !== '') {
        $txt = trim((string)$j['formacao']) . ' · ' . $sit;
        if ($v['nivel'] === 'medio' && ($j['escolaridade_tipo'] ?? '') === 'tecnico') {
            $txt .= ' · Curso técnico' . (!empty($j['escolaridade_curso']) ? ': ' . $j['escolaridade_curso'] : '');
        }
        return $txt;
    }
    // registro antigo: mesmo resumo da Stage 3
    $txt = $sit;
    if (($j['escolaridade_tipo'] ?? '') === 'tecnico') {
        $txt .= ' • Curso técnico' . (!empty($j['escolaridade_curso']) ? ': ' . $j['escolaridade_curso'] : '');
    }
    if (!empty($j['formacao'])) {
        $txt .= ' • ' . $j['formacao'];
    }
    return $txt;
}

/** Segunda linha de uma formação adicional (já escapada para HTML) */
function jc_formacao_detalhe(array $f): string
{
    $partes = [];
    if (!empty($f['instituicao'])) {
        $partes[] = htmlspecialchars($f['instituicao']);
    }
    $c = trim((string)($f['conclusao'] ?? ''));
    if ($c !== '') {
        // novo formato ("Cursando (2026)" / "Concluído (2024)") aparece como está
        $partes[] = preg_match('/^(Cursando|Concluído)\b/u', $c) ? htmlspecialchars($c) : 'Conclusão: ' . htmlspecialchars($c);
    }
    $area = trim((string)($f['area'] ?? ''));
    if ($area !== '' && mb_stripos((string)$f['titulo'], $area) === false) {
        $partes[] = 'Área: ' . htmlspecialchars($area);
    }
    return implode(' • ', $partes);
}

/**
 * Campos do formulário (HTML).
 *   id          prefixo único dos ids na página ('cad', 'perf', 'add')
 *   nome        prefixo dos names ('' ou 'form_')
 *   form        id do <form> quando os campos ficam fora dele (perfil) — senão ''
 *   situacao    name do grupo de situação
 *   em_tecnico  mostra "Também faço/fiz um curso técnico" no Ensino Médio
 *   obrigatorio marca o nível como obrigatório (*)
 *   vazio       texto da primeira opção do nível
 *   valores     jc_formacao_de_jovem() / jc_formacao_ler_post()['valores']
 */
function jc_formacao_campos(array $o): string
{
    static $script = false;
    $id   = $o['id'];
    $n    = $o['nome'] ?? '';
    $form = !empty($o['form']) ? ' form="' . htmlspecialchars($o['form']) . '"' : '';
    $v    = array_merge(jc_formacao_valores_vazios(), $o['valores'] ?? []);
    $sit  = $o['situacao'] ?? 'escolaridade_status';
    $ob   = !empty($o['obrigatorio']) ? ' *' : '';
    $h    = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $sel  = fn($a, $b) => ((string)$a === (string)$b) ? ' selected' : '';
    $oculto = fn(bool $ver) => $ver ? '' : ' hidden';

    $nivel  = $v['nivel'];
    $verTec = ($nivel === 'tecnico') || ($nivel === 'medio' && !empty($v['em_tecnico']));

    $out  = '<div class="formacao-bloco" data-formacao>' . "\n";
    // 1. nível
    $out .= '<div class="form-group">'
          . '<label class="form-label" for="' . $id . 'Nivel">Nível de formação' . $ob . '</label>'
          . '<select class="form-control" id="' . $id . 'Nivel" name="' . $n . 'formacao_nivel" data-formacao-nivel'
          . ($ob !== '' ? ' required' : '') . $form . '>'
          . '<option value="">' . $h($o['vazio'] ?? 'Selecione o nível') . '</option>';
    foreach (JC_FORMACAO_NIVEIS as $valor => $rotulo) {
        $out .= '<option value="' . $valor . '"' . $sel($nivel, $valor) . '>' . $h($rotulo) . '</option>';
    }
    $out .= '</select></div>' . "\n";

    // 2a. Ensino Médio: ano / série (+ curso técnico junto, opcional)
    $out .= '<div data-formacao-se="medio"' . $oculto($nivel === 'medio') . '>'
          . '<div class="form-group">'
          . '<label class="form-label" for="' . $id . 'Ano">Ano / série *</label>'
          . '<select class="form-control" id="' . $id . 'Ano" name="' . $n . 'formacao_ano"' . $form . '>'
          . '<option value="">Selecione o ano</option>';
    foreach (JC_FORMACAO_ANOS as $valor => $rotulo) {
        $out .= '<option value="' . $valor . '"' . $sel($v['ano'], $valor) . '>' . $rotulo . '</option>';
    }
    $out .= '</select></div>';
    if (!empty($o['em_tecnico'])) {
        $out .= '<div class="form-group"><label class="form-check">'
              . '<input type="checkbox" name="' . $n . 'em_tecnico" value="1" data-formacao-em-tecnico'
              . (!empty($v['em_tecnico']) ? ' checked' : '') . $form . '>'
              . '<span class="form-check-label">Também faço (ou já fiz) um curso técnico</span>'
              . '</label></div>';
    }
    $out .= '</div>' . "\n";

    // 2b. listas de curso (técnico, superior, profissionalizante) + "Qual?"
    $blocos = [
        ['tecnico em-tecnico', 'curso_tecnico',  'CursoTecnico',  'Área do curso técnico', JC_CURSOS_TECNICOS,            $verTec],
        ['superior',           'curso_superior', 'CursoSuperior', 'Curso superior',        JC_CURSOS_SUPERIORES,          $nivel === 'superior'],
        ['profissionalizante', 'curso_prof',     'CursoProf',     'Curso profissionalizante', JC_CURSOS_PROFISSIONALIZANTES, $nivel === 'profissionalizante'],
    ];
    foreach ($blocos as [$se, $campo, $sufixo, $rotulo, $lista, $ver]) {
        $escolha = $v[$campo];
        $out .= '<div data-formacao-se="' . $se . '"' . $oculto($ver) . '>'
              . '<div class="form-group">'
              . '<label class="form-label" for="' . $id . $sufixo . '">' . $rotulo . ' *</label>'
              . '<select class="form-control" id="' . $id . $sufixo . '" name="' . $n . $campo . '" data-formacao-curso' . $form . '>'
              . '<option value="">Selecione</option>';
        foreach ($lista as $valor => $texto) {
            $out .= '<option value="' . $h($valor) . '"' . $sel($escolha, $valor) . '>' . $h($texto) . '</option>';
        }
        $out .= '<option value="Outro"' . $sel($escolha, 'Outro') . '>Outro (não está na lista)</option>'
              . '</select></div>'
              . '<div class="form-group" data-formacao-outro-de="' . $id . $sufixo . '"' . $oculto($ver && $escolha === 'Outro') . '>'
              . '<label class="form-label" for="' . $id . $sufixo . 'Outro">Qual? *</label>'
              . '<input type="text" class="form-control" id="' . $id . $sufixo . 'Outro" name="' . $n . $campo . '_outro"'
              . ' maxlength="' . JC_FORMACAO_OUTRO_MAX . '" value="' . $h($v[$campo . '_outro']) . '" placeholder="Nome do curso"' . $form . '>'
              . '</div>'
              . '</div>' . "\n";
    }

    // 2c. Outro (texto livre só quando o nível não está nas opções)
    $out .= '<div data-formacao-se="outro"' . $oculto($nivel === 'outro') . '>'
          . '<div class="form-group">'
          . '<label class="form-label" for="' . $id . 'Outro">Descreva a sua formação *</label>'
          . '<input type="text" class="form-control" id="' . $id . 'Outro" name="' . $n . 'formacao_outro"'
          . ' maxlength="' . JC_FORMACAO_LIVRE_MAX . '" value="' . $h($v['outro']) . '"'
          . ' aria-describedby="' . $id . 'OutroAjuda" placeholder="Ex.: curso livre de idiomas"' . $form . '>'
          . '<small id="' . $id . 'OutroAjuda" class="text-secondary" style="font-size:12px;">Use só se a sua formação não estiver nas opções de nível.</small>'
          . '</div></div>' . "\n";

    // 3. situação
    $out .= '<fieldset class="form-group formacao-situacao-grupo">'
          . '<legend class="form-label">Situação</legend>'
          . '<div class="formacao-situacao">'
          . '<label class="form-check"><input type="radio" name="' . $sit . '" value="cursando"'
          . ($v['situacao'] !== 'completo' ? ' checked' : '') . $form . '><span class="form-check-label">Cursando</span></label>'
          . '<label class="form-check"><input type="radio" name="' . $sit . '" value="completo"'
          . ($v['situacao'] === 'completo' ? ' checked' : '') . $form . '><span class="form-check-label">Concluído</span></label>'
          . '</div></fieldset>' . "\n";

    if ($v['legado'] !== '') {
        $out .= '<p class="text-secondary" style="font-size:12px;margin:-8px 0 16px;">Informado antes: “'
              . $h($v['legado']) . '”. Confira o nível e salve.</p>' . "\n";
    }
    $out .= '</div>' . "\n";

    if (!$script) {
        $script = true;
        $out .= <<<'JS'
<script>
/* Stage 5: formação — mostra só os campos do nível escolhido */
(function () {
    function atualizar(bloco) {
        const nivel  = bloco.querySelector('[data-formacao-nivel]')?.value || '';
        const emTec  = bloco.querySelector('[data-formacao-em-tecnico]');
        const verTec = nivel === 'tecnico' || (nivel === 'medio' && !!emTec?.checked);
        bloco.querySelectorAll('[data-formacao-se]').forEach(el => {
            const alvos = el.getAttribute('data-formacao-se').split(' ');
            el.hidden = !(alvos.includes(nivel) || (alvos.includes('em-tecnico') && verTec));
        });
        bloco.querySelectorAll('[data-formacao-curso]').forEach(sel => {
            const qual = bloco.querySelector('[data-formacao-outro-de="' + sel.id + '"]');
            if (qual) qual.hidden = sel.value !== 'Outro' || !!sel.closest('[data-formacao-se]')?.hidden;
        });
    }
    // delegação: vale também para blocos que aparecem depois deste script na página
    document.addEventListener('change', e => {
        const bloco = e.target.closest?.('[data-formacao]');
        if (bloco) atualizar(bloco);
    });
    function iniciar() { document.querySelectorAll('[data-formacao]').forEach(atualizar); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar);
    else iniciar();
})();
</script>
JS;
    }
    return $out;
}
