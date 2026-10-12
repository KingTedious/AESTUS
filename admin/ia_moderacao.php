<?php
/* ============================================================
   admin/ia_moderacao.php — análise complementar por IA (Stage 7)

   POST (CSRF) pedido por um administrador na ficha da vaga.
   - Monta só os textos PÚBLICOS do recurso, mascara e-mails, números
     longos (telefone, CPF, cartão) e reduz links ao domínio.
   - Envia ao serviço Flask local (/moderacao), que consulta o Gemini
     se houver chave configurada no servidor.
   - Devolve uma ORIENTAÇÃO (risco, categorias, resumo) + o resultado
     das regras automáticas no texto atual. NADA é alterado: nem a vaga,
     nem a fila, nem a conta. A IA nunca executa ações.
   - Limite: 10 consultas a cada 5 minutos por sessão.
   - Sem JavaScript, responde com uma página simples; com fetch, JSON.
   ============================================================ */
require_once dirname(__DIR__) . '/includes/admin/nucleo.php';

$json = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
if (!jc_admin_post()) {
    jc_admin_redirecionar('vagas.php');
}
$admin = jc_admin_exigir('ia.consultar', ['json' => $json]);

$tipo = in_array($_POST['tipo'] ?? '', ['vaga', 'jovem', 'empresa'], true) ? $_POST['tipo'] : '';
$id   = ctype_digit((string) ($_POST['id'] ?? '')) ? intval($_POST['id']) : 0;

/** Responde em JSON (fetch) ou numa página simples (sem JavaScript). */
function ia_responder(bool $json, array $r, string $tipo, int $id): void {
    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($r, JSON_UNESCAPED_UNICODE);
        exit;
    }
    jc_admin_layout_abrir_simples('Análise complementar por IA');
    echo '<h1 class="adm-titulo">Análise complementar por IA</h1>';
    if (!$r['ok']) {
        echo '<div class="adm-alerta adm-alerta-aviso" role="status">' . jc_admin_icone('info') . '<div>' . jc_e($r['erro'] ?? 'Indisponível.') . '</div></div>';
    } else {
        echo '<div class="adm-alerta adm-alerta-info" role="status">' . jc_admin_icone('sparkles') . '<div><p><strong>Orientação da IA (não altera nada):</strong> risco '
           . jc_e($r['risco_rotulo']) . '.</p><p>' . jc_e($r['resumo']) . '</p>';
        if ($r['categorias']) {
            echo '<ul>';
            foreach ($r['categorias'] as $c) {
                echo '<li>' . jc_e($c) . '</li>';
            }
            echo '</ul>';
        }
        if (!empty($r['do_cache'])) {
            echo '<p class="adm-ajuda">Resposta reaproveitada de uma consulta recente sobre o mesmo texto (até 1 hora).</p>';
        }
        echo '</div></div>';
    }
    echo '<p class="adm-ajuda">Regras automáticas no texto atual: ' . jc_e($r['regras_resumo'] ?: 'nenhuma') . '</p>';
    $volta = $tipo === 'vaga' ? 'vaga.php?id=' . $id : 'conta.php?tipo=' . $tipo . '&id=' . $id;
    echo '<p><a class="adm-btn adm-btn-sec" href="' . jc_e($volta) . '">Voltar</a></p>';
    jc_admin_layout_fechar_simples();
    exit;
}

/** Remove dados pessoais óbvios e reduz links ao domínio. */
function ia_mascarar(string $t): string {
    $t = preg_replace('/[^\s@"\'<>]+@[^\s@"\'<>]+\.[^\s@"\'<>]+/u', '[e-mail]', $t) ?? '';
    $t = preg_replace('~\bhttps?://([^/\s?#]+)\S*~iu', '$1/…', $t) ?? '';            // link → só o domínio
    $t = preg_replace('~\b((?:[a-z0-9-]+\.)+[a-z]{2,})/\S+~iu', '$1/…', $t) ?? '';    // bit.ly/abc → bit.ly/…
    $t = preg_replace('/\d[\d.\-\/ ()]{7,}\d/u', '[número]', $t) ?? '';
    return mb_substr($t, 0, 4000, 'UTF-8');
}

if ($tipo === '' || $id <= 0) {
    ia_responder($json, ['ok' => false, 'erro' => 'Recurso inválido.', 'regras_resumo' => ''], $tipo, $id);
}

// limite por sessão
$agora = time();
$_SESSION['adm_ia'] = array_values(array_filter((array) ($_SESSION['adm_ia'] ?? []), fn($t) => is_int($t) && $t > $agora - 300));
if (count($_SESSION['adm_ia']) >= 10) {
    jc_admin_auditar($conn, intval($admin['id']), 'ia.consulta', $tipo, $id, 'negado', 'limite_sessao');
    ia_responder($json, ['ok' => false, 'erro' => 'Muitas consultas em pouco tempo. Aguarde alguns minutos.', 'regras_resumo' => ''], $tipo, $id);
}
$_SESSION['adm_ia'][] = $agora;

// textos públicos do recurso (chave do mapa → texto)
$textos = [];
if ($tipo === 'vaga') {
    $v = jc_admin_vaga_carregar($conn, $id);
    if ($v) {
        $textos = ['vaga.titulo' => (string) $v['titulo'], 'vaga.descricao' => (string) $v['descricao'],
                   'vaga.salario' => (string) $v['salario']];
        foreach ($v['requisitos'] as $i => $x) {
            $textos['vaga.requisito#' . $i] = (string) $x;
        }
        foreach ($v['beneficios'] as $i => $x) {
            $textos['vaga.beneficio#' . $i] = (string) $x;
        }
    }
    $tipo_ia = 'vaga';
} else {
    $c = jc_admin_conta_carregar($conn, $tipo, $id);
    if ($c && $tipo === 'jovem') {
        $textos = ['jovem.area_interesse' => (string) $c['area_interesse'], 'jovem.resumo' => (string) $c['resumo_profissional']];
        foreach ($c['habilidades'] as $i => $x) {
            $textos['jovem.habilidade#' . $i] = (string) $x;
        }
        foreach ($c['experiencias'] as $i => $x) {
            $textos['jovem.exp_titulo#' . $i] = (string) $x['titulo'];
            $textos['jovem.exp_descricao#' . $i] = (string) $x['descricao'];
        }
    } elseif ($c) {
        $textos = ['empresa.area_atuacao' => (string) $c['area_atuacao'], 'empresa.descricao' => (string) $c['descricao']];
    }
    $tipo_ia = $tipo === 'jovem' ? 'perfil_jovem' : 'perfil_empresa';
}
$textos = array_filter($textos, fn($t) => trim($t) !== '');
if (!$textos) {
    ia_responder($json, ['ok' => false, 'erro' => 'Não há texto público para analisar.', 'regras_resumo' => ''], $tipo, $id);
}

// regras determinísticas no texto atual (servem de base mesmo sem IA)
$regras = [];
foreach ($textos as $chave => $t) {
    $pol = jc_campo(explode('#', $chave)[0])['mod'] ?? null;
    if ($pol) {
        foreach (jc_moderar($t, $pol)['regras'] as $rg) {
            $regras[$rg['cod']] = true;
        }
    }
}
$regras_resumo = implode(', ', array_keys($regras));

// pedido ao Flask (dados mínimos e mascarados)
$campos = [];
$total = 0;
foreach ($textos as $chave => $t) {
    $m = ia_mascarar($t);
    $total += mb_strlen($m);
    if ($total > 9000) {
        break;
    }
    $campos[] = ['nome' => preg_replace('/^[a-z]+\.|#\d+$/', '', $chave), 'texto' => $m];
}
$corpo = json_encode(['tipo' => $tipo_ia, 'campos' => $campos], JSON_UNESCAPED_UNICODE);
$resp = null;
$do_cache = false;
$motivo = 'indisponivel';
$ch = curl_init('http://127.0.0.1:5000/moderacao');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $corpo,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 2,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Request-Id: ' . jc_req_id()],
]);
$bruto = curl_exec($ch);
$http = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
curl_close($ch);
if (is_string($bruto) && $http === 200) {
    $d = json_decode($bruto, true);
    if (is_array($d) && !empty($d['disponivel']) && is_array($d['analise'] ?? null)) {
        $resp = $d['analise'];
        $do_cache = !empty($d['do_cache']);   // o Flask reaproveita a análise do mesmo texto por até 1 hora
    } elseif (is_array($d)) {
        $motivo = preg_replace('/[^a-z_]/', '', (string) ($d['motivo'] ?? 'indisponivel')) ?: 'indisponivel';
    }
} elseif ($bruto === false) {
    $motivo = 'servico_fora';
}

$rotulos = ['nenhum' => 'nenhum', 'baixo' => 'baixo', 'medio' => 'médio', 'alto' => 'alto'];
if ($resp === null || !isset($rotulos[$resp['risco'] ?? ''])) {
    jc_admin_auditar($conn, intval($admin['id']), 'ia.consulta', $tipo, $id, 'falha', $motivo);
    $msgs = [
        'sem_chave'    => 'A análise por IA não está configurada neste servidor (sem chave do Gemini).',
        'servico_fora' => 'O serviço de IA (Flask) não está rodando.',
        'limite'       => 'O limite de uso da IA foi atingido. Tente mais tarde.',
        'timeout'      => 'A IA demorou demais para responder.',
    ];
    ia_responder($json, ['ok' => false, 'erro' => ($msgs[$motivo] ?? 'A IA não respondeu de forma válida.') . ' As regras automáticas continuam valendo.',
                         'regras_resumo' => $regras_resumo], $tipo, $id);
}

jc_admin_auditar($conn, intval($admin['id']), 'ia.consulta', $tipo, $id, 'sucesso', 'risco_' . $resp['risco']);
ia_responder($json, [
    'ok'            => true,
    'risco'         => $resp['risco'],
    'risco_rotulo'  => $rotulos[$resp['risco']],
    'categorias'    => array_values(array_map('strval', (array) ($resp['categorias'] ?? []))),
    'resumo'        => (string) ($resp['resumo'] ?? ''),
    'confianca'     => (string) ($resp['confianca'] ?? ''),
    'do_cache'      => $do_cache,
    'regras_resumo' => $regras_resumo,
], $tipo, $id);
