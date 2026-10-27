<?php
require_once __DIR__ . '/compat.php';   // PHP 7.4/8.0: str_contains etc. (includes/compat.php)
/* ============================================================
   includes/validacao.php — validação estrutural de textos (Stage 7)

   Camada 1 de 2. Esta camada só verifica se o dado é ESTRUTURALMENTE
   aceitável: tipo, codificação UTF-8, caracteres de controle, tamanho e
   formato. Não julga o significado do texto — isso é a camada 2, a
   moderação de conteúdo (includes/moderacao.php).

   Mapa de campos: jc_campos() associa cada campo editável pelo usuário
   ("area.campo", ex.: "vaga.descricao") a uma POLÍTICA de validação
   (tipo, obrigatório, mínimo, máximo) e a uma política de moderação.

     $r = jc_validar_campo('vaga.titulo', $_POST['titulo'] ?? '');
     // $r = ['ok' => bool, 'valor' => texto normalizado, 'erro' => mensagem|null]

   O VALOR NORMALIZADO é o que deve ser gravado (é o que foi validado).

   Normalização segura (não muda o que a pessoa escreveu):
   - UTF-8 obrigatório; Unicode NFC quando a extensão intl existe;
   - "\r\n" e "\r" viram "\n"; tabulação vira espaço em campos de uma linha;
   - removidos: espaços invisíveis (U+200B, U+2060, U+FEFF, U+00AD) e
     controles de direção (U+202A–U+202E, U+2066–U+2069) — não aparecem
     na tela, mas servem para burlar filtros e disfarçar o texto exibido;
   - recusados: outros caracteres de controle (NUL, ESC...).
   Acentos, cedilha, nomes estrangeiros, letras de outros alfabetos e a
   pontuação comum são aceitos; nada é traduzido nem "corrigido".

   O escape na SAÍDA (htmlspecialchars / textContent) continua
   obrigatório: validar não substitui escapar. As consultas continuam
   parametrizadas (mysqli prepared statements).
   ============================================================ */

if (!function_exists('jc_campos')) {

    /**
     * Mapa de campos editáveis → política.
     * tipo: nome | nome_org | linha | texto | url | url_livre
     * mod : política de moderação (includes/moderacao.php); 'tecnico' = só
     *       bloqueia código executável (campos que não vão para a fila)
     */
    function jc_campos(): array {
        static $m = null;
        if ($m !== null) {
            return $m;
        }
        $m = [
            // ── Jovem (cadastro e perfil) ─────────────────────────────────────
            'jovem.nome'            => ['rotulo' => 'Nome', 'tipo' => 'nome', 'obrig' => true, 'min' => 2, 'max' => 100, 'mod' => 'nome'],
            'jovem.area_interesse'  => ['rotulo' => 'Área de interesse', 'tipo' => 'linha', 'max' => 100, 'mod' => 'curto'],
            'jovem.resumo'          => ['rotulo' => 'Resumo profissional', 'tipo' => 'texto', 'max' => 2000, 'mod' => 'perfil_texto'],
            'jovem.habilidade'      => ['rotulo' => 'Habilidade', 'tipo' => 'linha', 'max' => 80, 'mod' => 'curto'],
            'jovem.cidade'          => ['rotulo' => 'Cidade', 'tipo' => 'linha', 'max' => 100, 'mod' => 'tecnico'],
            'jovem.formacao_titulo' => ['rotulo' => 'Formação', 'tipo' => 'linha', 'max' => 150, 'mod' => 'curto'],
            'jovem.formacao_instituicao' => ['rotulo' => 'Instituição', 'tipo' => 'linha', 'max' => 150, 'mod' => 'curto'],
            'jovem.formacao_conclusao'   => ['rotulo' => 'Conclusão', 'tipo' => 'linha', 'max' => 20, 'mod' => 'tecnico'],
            'jovem.formacao_area'   => ['rotulo' => 'Área do curso', 'tipo' => 'linha', 'max' => 100, 'mod' => 'curto'],
            'jovem.formacao_livre'  => ['rotulo' => 'Formação (texto digitado)', 'tipo' => 'linha', 'max' => 100, 'mod' => 'curto'],
            'jovem.exp_titulo'      => ['rotulo' => 'Título da experiência', 'tipo' => 'linha', 'obrig' => true, 'max' => 150, 'mod' => 'curto'],
            'jovem.exp_local'       => ['rotulo' => 'Local da experiência', 'tipo' => 'linha', 'max' => 150, 'mod' => 'curto'],
            'jovem.exp_periodo'     => ['rotulo' => 'Período', 'tipo' => 'linha', 'max' => 50, 'mod' => 'tecnico'],
            'jovem.exp_descricao'   => ['rotulo' => 'Descrição da experiência', 'tipo' => 'texto', 'max' => 1000, 'mod' => 'perfil_texto'],
            // ── Empresa (cadastro e perfil) ───────────────────────────────────
            'empresa.nome'          => ['rotulo' => 'Nome da empresa', 'tipo' => 'nome_org', 'obrig' => true, 'min' => 2, 'max' => 150, 'mod' => 'nome'],
            'empresa.area_atuacao'  => ['rotulo' => 'Área de atuação', 'tipo' => 'linha', 'max' => 100, 'mod' => 'curto'],
            'empresa.descricao'     => ['rotulo' => 'Sobre a empresa', 'tipo' => 'texto', 'max' => 2000, 'mod' => 'empresa_texto'],
            'empresa.site'          => ['rotulo' => 'Site oficial', 'tipo' => 'url', 'max' => 255, 'mod' => 'link'],
            'empresa.linkedin'      => ['rotulo' => 'LinkedIn', 'tipo' => 'url_livre', 'max' => 255, 'mod' => 'link'],
            'empresa.cidade'        => ['rotulo' => 'Cidade', 'tipo' => 'linha', 'max' => 100, 'mod' => 'tecnico'],
            // ── Endereço e locais de trabalho (empresa) ───────────────────────
            'local.nome'            => ['rotulo' => 'Nome do local', 'tipo' => 'linha', 'max' => 100, 'mod' => 'tecnico'],
            'local.logradouro'      => ['rotulo' => 'Logradouro', 'tipo' => 'linha', 'max' => 150, 'mod' => 'tecnico'],
            'local.numero'          => ['rotulo' => 'Número', 'tipo' => 'linha', 'max' => 10, 'mod' => 'tecnico'],
            'local.complemento'     => ['rotulo' => 'Complemento', 'tipo' => 'linha', 'max' => 100, 'mod' => 'tecnico'],
            'local.bairro'          => ['rotulo' => 'Bairro', 'tipo' => 'linha', 'max' => 100, 'mod' => 'tecnico'],
            'local.cidade'          => ['rotulo' => 'Cidade', 'tipo' => 'linha', 'max' => 100, 'mod' => 'tecnico'],
            // ── Vaga ──────────────────────────────────────────────────────────
            'vaga.titulo'           => ['rotulo' => 'Título da vaga', 'tipo' => 'linha', 'obrig' => true, 'min' => 2, 'max' => 150, 'mod' => 'vaga_titulo'],
            'vaga.descricao'        => ['rotulo' => 'Descrição da vaga', 'tipo' => 'texto', 'obrig' => true, 'max' => 5000, 'mod' => 'vaga_texto'],
            'vaga.salario'          => ['rotulo' => 'Salário', 'tipo' => 'linha', 'max' => 50, 'mod' => 'curto'],
            'vaga.carga_horaria'    => ['rotulo' => 'Carga horária', 'tipo' => 'linha', 'max' => 20, 'mod' => 'tecnico'],
            // requisitos/benefícios: quebra de linha tolerada (o formulário antigo mandava um por linha
            // e o servidor sempre separou só por vírgula; o conteúdo gravado continua igual)
            'vaga.requisito'        => ['rotulo' => 'Requisito', 'tipo' => 'texto', 'max' => 150, 'mod' => 'vaga_item'],
            'vaga.beneficio'        => ['rotulo' => 'Benefício', 'tipo' => 'texto', 'max' => 100, 'mod' => 'vaga_item'],
            'vaga.cidade'           => ['rotulo' => 'Cidade', 'tipo' => 'linha', 'max' => 100, 'mod' => 'tecnico'],
            // ── Denúncia e justificativas da administração ────────────────────
            'denuncia.detalhe'      => ['rotulo' => 'Detalhes da denúncia', 'tipo' => 'texto', 'max' => 500, 'mod' => 'tecnico'],
            'admin.justificativa'   => ['rotulo' => 'Justificativa', 'tipo' => 'texto', 'obrig' => true, 'min' => 10, 'max' => 500, 'mod' => null],
            'admin.nome'            => ['rotulo' => 'Nome', 'tipo' => 'nome', 'obrig' => true, 'min' => 2, 'max' => 100, 'mod' => null],
        ];
        return $m;
    }

    /**
     * Campos validados por regras próprias (formato), já existentes antes da
     * Stage 7 — ficam aqui para o inventário completo (docs e Central).
     */
    function jc_campos_formato(): array {
        return [
            'email'            => 'filter_var(FILTER_VALIDATE_EMAIL), até 100 caracteres (cadastros, perfis, login, recuperação)',
            'senha'            => 'mínimo de 8 caracteres; guardada só como hash (password_hash)',
            'cpf'              => '11 dígitos, gravado como 000.000.000-00 (cadastro do jovem); nunca exibido a empresas nem à administração',
            'cnpj'             => '14 dígitos, gravado como 00.000.000/0000-00 (cnpj_formatado)',
            'cep'              => '8 dígitos (cadastros, perfis, locais)',
            'estado'           => 'sigla de 2 letras',
            'data_nascimento'  => 'AAAA-MM-DD, não futura',
            'telefone'         => 'verificacoes/verificar_telefone.php (formato E.164 para o SMS)',
            'codigo'           => '6 dígitos (verificação e recuperação)',
            'formacao'         => 'listas fechadas de includes/formacao.php (nível, curso, ano, situação)',
            'tipo_contrato'    => 'lista fechada do formulário de vaga',
            'area'             => 'lista fechada do formulário de vaga',
            'status_candidatura' => 'lista fechada (Recebido, Visualizado, Em análise, Aprovado, Reprovado)',
            'mensagem_assistente' => 'até 500 caracteres; dados pessoais mascarados; não é gravada (IA/assistente_ajax.php)',
        ];
    }

    /** Política de um campo (null se a chave não existir). */
    function jc_campo(string $chave): ?array {
        return jc_campos()[$chave] ?? null;
    }

    /** Normalização segura: devolve [texto, erro|null]. */
    function jc_normalizar_texto($valor, bool $multilinha): array {
        if ($valor === null) {
            return ['', null];
        }
        if (is_int($valor) || is_float($valor)) {
            $valor = (string) $valor;
        }
        if (!is_string($valor)) {
            return ['', 'Valor inválido.'];
        }
        if ($valor !== '' && !mb_check_encoding($valor, 'UTF-8')) {
            return ['', 'O texto tem caracteres inválidos. Digite o texto de novo, sem colar de outro programa.'];
        }
        $valor = str_replace(["\r\n", "\r"], "\n", $valor);
        // invisíveis e controles de direção: removidos (não mudam o que se lê)
        $valor = preg_replace('/[\x{200B}\x{2060}\x{FEFF}\x{00AD}\x{180E}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $valor) ?? '';
        if ($multilinha) {
            $valor = str_replace(["\x0B", "\x0C"], "\n", $valor);
        } else {
            $valor = str_replace("\t", ' ', $valor);
        }
        // demais caracteres de controle (C0, DEL, C1): recusados
        $permitidos = $multilinha ? '\x{0009}\x{000A}' : '';
        $padrao = '/[^\P{Cc}' . $permitidos . ']/u';
        if (preg_match($padrao, $valor)) {
            return ['', 'O texto contém caracteres de controle que não são permitidos.'];
        }
        if (class_exists('Normalizer')) {
            $n = Normalizer::normalize($valor, Normalizer::FORM_C);
            if (is_string($n)) {
                $valor = $n;
            }
        }
        return [trim($valor), null];
    }

    /** URL http(s) válida? Aceita sem esquema (completa com https://). */
    function jc_url_valida(string $url): ?string {
        $u = $url;
        if (!preg_match('~^[a-z][a-z0-9+.\-]*:~i', $u)) {
            $u = 'https://' . $u;
        }
        $p = parse_url($u);
        if (!$p || !in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true) || empty($p['host'])) {
            return null;
        }
        if (!str_contains($p['host'], '.') || preg_match('/\s/', $u) || !filter_var($u, FILTER_VALIDATE_URL)) {
            return null;
        }
        return $u;
    }

    /**
     * Valida um campo do mapa.
     * @return array{ok: bool, valor: string, erro: ?string}
     */
    function jc_validar_campo(string $chave, $valor): array {
        $c = jc_campo($chave);
        if ($c === null) {
            return ['ok' => false, 'valor' => '', 'erro' => 'Campo desconhecido.'];
        }
        $rotulo = $c['rotulo'];
        $max    = intval($c['max'] ?? 255);
        $min    = intval($c['min'] ?? 0);
        $multi  = ($c['tipo'] === 'texto');

        // limite bruto antes de qualquer processamento (entradas gigantes)
        if (is_string($valor) && strlen($valor) > max(1024, $max * 4 + 256)) {
            return ['ok' => false, 'valor' => '', 'erro' => "{$rotulo}: texto longo demais (máximo de " . number_format($max, 0, ',', '.') . " caracteres)."];
        }
        if (!$multi && is_string($valor) && preg_match('/[\r\n]/', trim($valor))) {
            return ['ok' => false, 'valor' => '', 'erro' => "{$rotulo}: use uma linha só."];
        }
        [$texto, $erro] = jc_normalizar_texto($valor, $multi);
        if ($erro !== null) {
            return ['ok' => false, 'valor' => '', 'erro' => "{$rotulo}: {$erro}"];
        }
        if (!$multi && str_contains($texto, "\n")) {
            return ['ok' => false, 'valor' => '', 'erro' => "{$rotulo}: use uma linha só."];
        }
        $n = mb_strlen($texto, 'UTF-8');
        if ($n === 0) {
            return !empty($c['obrig'])
                ? ['ok' => false, 'valor' => '', 'erro' => "Preencha o campo {$rotulo}."]
                : ['ok' => true, 'valor' => '', 'erro' => null];
        }
        if ($min > 0 && $n < $min) {
            return ['ok' => false, 'valor' => $texto, 'erro' => "{$rotulo}: use pelo menos {$min} caracteres."];
        }
        if ($n > $max) {
            return ['ok' => false, 'valor' => $texto,
                    'erro' => "{$rotulo}: use no máximo " . number_format($max, 0, ',', '.') . " caracteres (agora: " . number_format($n, 0, ',', '.') . ")."];
        }

        switch ($c['tipo']) {
            case 'nome':
                // letras de qualquer alfabeto (com acentos), espaço, apóstrofo, hífen e ponto;
                // números são tolerados (ex.: "Pedro II", contas antigas), mas exige 2 letras
                if (!preg_match("/^[\p{L}\p{M}][\p{L}\p{M}\p{N}' \x{2019}.\-]*$/u", $texto)
                    || preg_match_all('/\p{L}/u', $texto) < 2) {
                    return ['ok' => false, 'valor' => $texto,
                            'erro' => "{$rotulo}: use letras (acentos são aceitos), espaços, apóstrofo, hífen ou ponto."];
                }
                break;
            case 'nome_org':
                // nomes de empresa variam muito ("C&A", "Lojas X S.A.", "7-Eleven", "Café #1"):
                // só ficam de fora sinais de marcação/código (< > { } [ ] \ | `) e é preciso ter 2 letras/números
                if (preg_match('/[<>{}\[\]\\\\|`]/u', $texto) || !preg_match('/^[\p{L}\p{N}"\'(]/u', $texto)
                    || preg_match_all('/[\p{L}\p{N}]/u', $texto) < 2) {
                    return ['ok' => false, 'valor' => $texto,
                            'erro' => "{$rotulo}: comece com uma letra ou número e não use os sinais < > { } [ ] \\ | `."];
                }
                break;
            case 'url':
                if (jc_url_valida($texto) === null) {
                    return ['ok' => false, 'valor' => $texto, 'erro' => "{$rotulo}: informe um endereço de site válido (ex.: www.suaempresa.com.br)."];
                }
                break;
            case 'url_livre':
                // perfil ("linkedin.com/company/...", "@empresa"): texto livre, mas
                // se tiver esquema (algo:) tem de ser http(s) — nunca javascript:, data: etc.
                if (preg_match('~^\s*[a-z][a-z0-9+.\-]*:~i', $texto) && jc_url_valida($texto) === null) {
                    return ['ok' => false, 'valor' => $texto, 'erro' => "{$rotulo}: use um endereço que comece com https:// ou só o nome do perfil."];
                }
                break;
        }
        return ['ok' => true, 'valor' => $texto, 'erro' => null];
    }

    /**
     * Valida vários campos de uma vez.
     * @param array<string, mixed> $campos  chave do mapa => valor recebido
     * @return array{ok: bool, valores: array<string,string>, erros: array<string,string>}
     */
    function jc_validar_campos(array $campos): array {
        $valores = [];
        $erros   = [];
        foreach ($campos as $chave => $valor) {
            $r = jc_validar_campo($chave, $valor);
            $valores[$chave] = $r['valor'];
            if (!$r['ok']) {
                $erros[$chave] = $r['erro'];
            }
        }
        return ['ok' => !$erros, 'valores' => $valores, 'erros' => $erros];
    }
}
