<?php
require_once __DIR__ . '/compat.php';   // PHP 7.4/8.0: str_contains etc. (includes/compat.php)
/* ============================================================
   includes/moderacao.php — moderação de conteúdo (Stage 7)

   Camada 2 de 2 (a camada 1, validação estrutural, fica em
   includes/validacao.php). Avalia se um texto JÁ VÁLIDO apresenta
   RISCO ou viola as regras da plataforma. Identifica indícios — não
   decide sobre a intenção de ninguém.

   Regras determinísticas e centralizadas (jc_mod_regras()). Cada regra
   tem código, categoria, nível de risco, as políticas de campo em que
   vale e um texto para a equipe de moderação. A política de cada campo
   vem do mapa de includes/validacao.php (chave "mod").

   Comparação resistente a disfarces: além do texto normal, as regras de
   palavras rodam sobre um "esqueleto" do texto (minúsculas, sem acento,
   0→o, 1→i, 3→e, 4→a, 5→s, 7→t, @→a, $→s, letras separadas por espaço
   ou ponto juntadas, letras repetidas reduzidas). Quando a regra só casa
   no esqueleto, entra também a regra "evasao.filtro" e o risco sobe.
   Negações próximas ("sem taxa", "não pedimos senha") desarmam as
   regras de cobrança e de dados pessoais.

   Decisões proporcionais (jc_moderar):
     aceitar    → sem sinais relevantes;
     sinalizar  → risco baixo/médio: grava normalmente e cria (ou
                  atualiza) um item na fila de moderação;
     revisar    → risco alto: VAGA fica "pendente" (fora do ar até a
                  revisão humana); em perfis, item de prioridade alta;
     bloquear   → só tentativa de exploração técnica (HTML/JavaScript
                  executável): o envio é recusado com explicação.
   Nenhuma regra suspende contas: a suspensão é sempre uma decisão
   humana na Central administrativa.

   A fila NÃO copia o texto: guarda o hash (SHA-256) do conteúdo
   avaliado; o moderador abre o recurso original por uma página
   autorizada da Central.
   ============================================================ */
require_once __DIR__ . '/validacao.php';
require_once __DIR__ . '/esquema.php';

if (!function_exists('jc_moderar')) {

    /** Ordem dos níveis de risco. */
    function jc_mod_nivel(string $risco): int {
        return ['nenhum' => 0, 'baixo' => 1, 'medio' => 2, 'alto' => 3][$risco] ?? 0;
    }

    function jc_mod_risco_de_nivel(int $n): string {
        return ['nenhum', 'baixo', 'medio', 'alto'][max(0, min(3, $n))];
    }

    /** Minúsculas e sem acentos (sem depender da extensão intl). */
    function jc_mod_sem_acento(string $t): string {
        $t = mb_strtolower($t, 'UTF-8');
        return strtr($t, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n', 'ý' => 'y', 'ÿ' => 'y',
        ]);
    }

    /**
     * Texto "plano": minúsculas, sem acento, só letras/dígitos. O fim de cada
     * oração (. ; : ! ? vírgula e quebra de linha) vira " , " para a checagem
     * de negação não atravessar frases ("Curso gratuito, pague a taxa").
     */
    function jc_mod_plano(string $t): string {
        $t = jc_mod_sem_acento($t);
        $t = preg_replace('/[.;:!?,\n]+/u', ' , ', $t) ?? '';
        $t = preg_replace('/[^a-z0-9,]+/u', ' ', $t) ?? '';
        $t = preg_replace('/\s*,[\s,]*/u', ' , ', $t) ?? '';
        return trim($t, " ,");
    }

    /** Esqueleto resistente a disfarces (ver cabeçalho). */
    function jc_mod_esqueleto(string $t): string {
        $t = jc_mod_sem_acento($t);
        // "!" e "|" só viram "i" no meio de palavra ("p!ranha"); no fim são pontuação ("idiota!")
        $t = preg_replace('/(?<=[a-z0-9])[!|](?=[a-z0-9])/u', 'i', $t) ?? $t;
        $t = strtr($t, ['0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '@' => 'a', '$' => 's']);
        // "p a g u e", "p.a.g.u.e", "p-a-g-u-e" → "pague"
        $t = preg_replace_callback('/(?<![a-z])(?:[a-z][\s.\-_*+]{1,2}){2,}[a-z](?![a-z])/u',
            fn($m) => preg_replace('/[\s.\-_*+]/', '', $m[0]), $t) ?? $t;
        $t = preg_replace('/([a-z])\1{2,}/u', '$1', $t) ?? $t;     // "paaague" → "pague"
        $t = preg_replace('/[^a-z]+/u', ' ', $t) ?? '';
        return trim($t);
    }

    /**
     * Regras. Campos:
     *   alvo    : 'texto' (texto normalizado, sem mudar maiúsculas) | 'plano' (sem acento)
     *             | 'esqueleto' (resistente a disfarces; testa também o plano)
     *   re      : expressão regular
     *   risco   : baixo | medio | alto | bloquear
     *   pol     : políticas de campo em que a regra vale
     *   neg     : true = desarmada por negação logo antes ("sem taxa", "nunca envie sua senha")
     *   excecao : expressão testada no trecho ANTES do achado; se casar, o achado é ignorado
     *   desc    : explicação para a equipe de moderação (nunca mostrada ao autor)
     *   msg     : mensagem ao autor, só nas regras de bloqueio (sem detalhes do filtro)
     *
     * Calibragem: "alto" só para assinaturas bem específicas (é o nível que tira
     * uma vaga do ar até a revisão). Indícios mais amplos ficam em "medio" ou
     * "baixo" (a vaga continua no ar e o item vai para a fila).
     */
    function jc_mod_regras(): array {
        static $r = null;
        if ($r !== null) {
            return $r;
        }
        $todos     = ['nome', 'curto', 'perfil_texto', 'empresa_texto', 'vaga_titulo', 'vaga_texto', 'vaga_item', 'tecnico', 'link'];
        $textos    = ['perfil_texto', 'empresa_texto', 'vaga_titulo', 'vaga_texto', 'vaga_item'];
        $vaga      = ['vaga_titulo', 'vaga_texto', 'vaga_item'];
        $vaga_emp  = ['vaga_titulo', 'vaga_texto', 'vaga_item', 'empresa_texto'];
        $publicos  = ['nome', 'curto', 'perfil_texto', 'empresa_texto', 'vaga_titulo', 'vaga_texto', 'vaga_item'];
        $msg_cod   = 'O texto contém código (HTML ou JavaScript) que não é permitido neste campo. '
                   . 'Escreva só texto. Para citar uma tecnologia, use o nome sem os sinais < e > (ex.: "tag script").';

        $r = [
            // ── Exploração técnica (bloqueio) ────────────────────────────────
            // Só marcação que EXECUTA ou embute conteúdo. Citar <div>, <form>,
            // SQL, JavaScript, C++ etc. é aceito (a saída é sempre escapada).
            ['cod' => 'tecnico.marcacao_ativa', 'cat' => 'Código malicioso', 'alvo' => 'texto', 'risco' => 'bloquear', 'pol' => $todos,
             're' => '/<\s*\/?\s*(?:script|iframe|object|embed|applet|frame|frameset)\b|<\s*base\s+href\b|<\s*meta\s+http-equiv\b/i',
             'desc' => 'Marcação HTML executável ou que embute conteúdo (script, iframe, object, embed...)', 'msg' => $msg_cod],
            ['cod' => 'tecnico.evento_html', 'cat' => 'Código malicioso', 'alvo' => 'texto', 'risco' => 'bloquear', 'pol' => $todos,
             're' => '/<\s*[a-z!\/][^>]*?[\s\/"\']on[a-z]{3,}\s*=/i',
             'desc' => 'Atributo de evento HTML (onload=, onerror=...)', 'msg' => $msg_cod],
            ['cod' => 'tecnico.url_script', 'cat' => 'Código malicioso', 'alvo' => 'texto', 'risco' => 'bloquear', 'pol' => $todos,
             're' => '/(?:java|vb|live)script\s*:\s*(?:[\w$.]+\s*\(|\/\/|void\b|eval\b|alert\b|document\.|window\.)|(?:href|src|action|formaction)\s*=\s*["\']?\s*(?:java|vb)script\s*:|data\s*:\s*text\/html/i',
             'desc' => 'Endereço javascript:/vbscript:/data:text/html', 'msg' => $msg_cod],
            ['cod' => 'tecnico.sql', 'cat' => 'Sondagem técnica', 'alvo' => 'texto', 'risco' => 'baixo', 'pol' => $publicos,
             're' => "/'\s*(?:or|and)\s+'?\d+'?\s*=\s*'?\d+|\bunion\s+(?:all\s+)?select\b|;\s*(?:drop|truncate|alter)\s+table\b|\bsleep\s*\(\s*\d+\s*\)\s*--/i",
             'desc' => 'Padrão típico de injeção de SQL (sem efeito: as consultas são parametrizadas)'],

            // ── Fraude em vagas ──────────────────────────────────────────────
            ['cod' => 'fraude.cobranca', 'cat' => 'Fraude / cobrança do candidato', 'alvo' => 'esqueleto', 'risco' => 'alto', 'pol' => $vaga_emp, 'neg' => true,
             're' => '/\btaxa\W+(?:de\W+|da\W+|do\W+|para\W+)?(?:inscricao|cadastro|adesao|matricula|admissao|contratacao|participacao|reserva|ativacao|entrevista|selecao|processo seletivo)\b'
                  . '|\b(?:pague|pagar|deposite|depositar|transfira|transferir|faca um pix|faz um pix|fazer um pix|envie um pix|mande um pix)\b(?:\W+\w+){0,6}?\W+(?:para|pra)\W+(?:garantir|reservar|confirmar|assegurar|liberar|efetivar)\b'
                  . '|\b(?:candidato|candidata|candidatos|voce|vc)\W+(?:\w+\W+){0,2}?(?:deve|devera|precisa|precisara|tera que|tem que|tera de)\W+(?:pagar|depositar|transferir|comprar|adquirir|investir|custear)\b'
                  . '|\bmediante\W+(?:o\W+|a\W+|um\W+|uma\W+)?(?:pagamento|deposito|taxa|transferencia|pix)\b'
                  . '|\b(?:investimento inicial|cobramos uma taxa|cobramos taxa|taxa sera cobrada|taxa e cobrada)\b'
                  . '|\b(?:compre|comprar|adquira|adquirir|pague|pagar)\W+(?:o\W+|seu\W+|um\W+)?kit\b/u',
             'desc' => 'Pede pagamento do candidato (taxa de inscrição, depósito para garantir a vaga, compra de kit)'],
            ['cod' => 'fraude.cobranca_indicio', 'cat' => 'Possível cobrança do candidato', 'alvo' => 'esqueleto', 'risco' => 'medio', 'pol' => $vaga_emp, 'neg' => true,
             'excecao' => '/\b(?:empresa|contratante|nos)\W+(?:\w+\W+)?$/u',
             're' => '/\b(?:pague|pagar|compre|comprar|adquira|adquirir|deposite|depositar)\W+(?:(?:o|a|seu|sua)\W+){1,2}(?:proprio\W+|propria\W+)?(?:uniforme|cracha|apostila|treinamento|curso|exame|material de trabalho|inscricao|cadastro)\b/u',
             'desc' => 'Pode estar pedindo que o candidato pague por uniforme, curso, crachá ou inscrição'],
            ['cod' => 'fraude.dados_sensiveis', 'cat' => 'Pedido de dados sensíveis', 'alvo' => 'esqueleto', 'risco' => 'alto', 'pol' => $vaga_emp, 'neg' => true,
             're' => '/\b(?:envie|enviar|mande|mandar|encaminhe|encaminhar|informe|informar|passe|passar|forneca|fornecer|digite|digitar|confirme|confirmar|compartilhe|compartilhar|repasse|repassar)\W+(?:\w+\W+){0,3}?(?:seu|sua|seus|suas|teu|tua)\W+(?:\w+\W+){0,2}?(?:senha|senhas|cartao|cvv|dados bancarios|conta bancaria|conta corrente|agencia|chave pix|codigo de verificacao|codigo sms|codigo de seguranca|codigo recebido|token)\b'
                  . '|\b(?:envie|enviar|mande|mandar|encaminhe|encaminhar)\W+(?:\w+\W+){0,5}?(?:cpf|rg|cnh|documento|documentos|comprovante de residencia|certidao)\W+(?:\w+\W+){0,5}?(?:whatsapp|zap|wpp|telegram|direct|dm|instagram|facebook)\b/u',
             'desc' => 'Pede senha, cartão, dados bancários, código de verificação ou documentos por fora da plataforma'],
            ['cod' => 'fraude.phishing', 'cat' => 'Possível golpe (link)', 'alvo' => 'esqueleto', 'risco' => 'medio', 'pol' => $vaga_emp,
             're' => '/\b(?:confirme|atualize|valide|regularize|verifique|desbloqueie|recadastre)\W+(?:\w+\W+){0,2}?(?:seus dados|seu cadastro|sua conta|sua senha|seu acesso|seu cpf)\b(?:\W+\w+){0,8}?\W+(?:link|clique|acesse)\b/u',
             'desc' => 'Pede para confirmar dados ou conta por um link (padrão de phishing)'],
            ['cod' => 'fraude.renda_irreal', 'cat' => 'Promessa de ganho irreal', 'alvo' => 'esqueleto', 'risco' => 'medio', 'pol' => $vaga_emp,
             're' => '/\b(?:renda extra garantida|dinheiro facil|ganho garantido|ganhos garantidos|lucro garantido|fique rico|enriqueca|sem sair de casa e ganhe|trabalhe de casa e ganhe|ganhe dinheiro rapido)\b/u',
             'desc' => 'Promete ganho fácil ou garantido, típico de golpes'],
            ['cod' => 'fraude.piramide', 'cat' => 'Pirâmide / multinível', 'alvo' => 'esqueleto', 'risco' => 'medio', 'pol' => $vaga_emp,
             're' => '/\b(?:piramide financeira|marketing multinivel|marketing de rede|indique amigos e ganhe|ganhe por indicacao|ganhe indicando|recrute pessoas|monte sua equipe e ganhe)\b/u',
             'desc' => 'Linguagem de pirâmide ou marketing multinível'],
            ['cod' => 'vaga.anuncio_comercial', 'cat' => 'Não parece vaga', 'alvo' => 'esqueleto', 'risco' => 'medio', 'pol' => ['vaga_titulo', 'vaga_texto'],
             're' => '/\b(?:compre agora|em promocao|promocao imperdivel|aproveite a promocao|frete gratis|cupom de desconto|aproveite a oferta|so hoje|ultimas unidades|clique e compre)\b/u',
             'desc' => 'Texto de propaganda/venda, não de oferta de trabalho'],
            ['cod' => 'vaga.titulo_enganoso', 'cat' => 'Título enganoso', 'alvo' => 'texto', 'risco' => 'medio', 'pol' => ['vaga_titulo'],
             're' => '/\b(?:ganhe|renda extra|imperd[ií]vel|dinheiro f[aá]cil)\b|!{3,}|\burgente\s*!{2,}/iu',
             'desc' => 'Título com promessa de dinheiro ou chamariz em vez do cargo'],

            // ── Contato e privacidade ────────────────────────────────────────
            ['cod' => 'contato.externo', 'cat' => 'Contato fora da plataforma', 'alvo' => 'texto', 'risco' => 'baixo', 'pol' => $vaga,
             're' => '/(?:\(?\b\d{2}\)?\s?)?\b9\d{4}[-.\s]?\d{4}\b|\(\d{2}\)\s?\d{4}[-.\s]?\d{4}\b|[\w.+\-]+@[\w\-]+\.[\w.\-]+'
                  . '|\b(?:chame|chama|fale|falar|contato|contate|envie|mande|enviar|mandar|chamar|ligue)\b(?:\W+\w+){0,3}?\W+(?:no|pelo|via|por)\W+(?:whats\s?app|zap|wpp|telegram)\b/iu',
             'desc' => 'Telefone, e-mail ou aplicativo de mensagem para contato no texto da vaga'],
            ['cod' => 'privacidade.contato_exposto', 'cat' => 'Dado pessoal exposto', 'alvo' => 'texto', 'risco' => 'baixo', 'pol' => ['perfil_texto', 'nome', 'curto'],
             're' => '/(?:\(?\b\d{2}\)?\s?)?\b9\d{4}[-.\s]?\d{4}\b|[\w.+\-]+@[\w\-]+\.[\w.\-]+/u',
             'desc' => 'Telefone ou e-mail escrito num texto público do perfil (visível para empresas)'],
            ['cod' => 'privacidade.documento', 'cat' => 'Documento pessoal exposto', 'alvo' => 'texto', 'risco' => 'medio', 'pol' => $publicos,
             're' => '/\b\d{3}\.\d{3}\.\d{3}-\d{2}\b|\bcpf\W{0,3}\d{11}\b/iu',
             'desc' => 'Número com formato de CPF num texto público'],

            // ── Discriminação ────────────────────────────────────────────────
            ['cod' => 'discriminacao.exclusao', 'cat' => 'Discriminação', 'alvo' => 'esqueleto', 'risco' => 'alto', 'pol' => $vaga_emp,
             're' => '/\b(?:nao|proibido|proibida|vedado|vedada)\b(?:\W+(?:aceitamos|contratamos|aceita|aceito|sera|serao|aceitos|aceitas|pode|podem|admitimos|queremos)){0,2}\W+(?:mulheres|mulher|homens|homem|gestantes|gestante|gravidas|gravida|negros|negras|negro|negra|gays|gay|lesbicas|homossexuais|transexuais|travestis|trans|idosos|deficientes|nordestinos|evangelicos|catolicos|umbandistas|candomblecistas|macumbeiros|ateus|obesos|gordos)\b/u',
             'desc' => 'Exclui candidatos por sexo, raça/cor, religião, orientação, gravidez, deficiência ou origem'],
            ['cod' => 'discriminacao.exclusividade', 'cat' => 'Possível discriminação', 'alvo' => 'esqueleto', 'risco' => 'medio', 'pol' => $vaga,
             're' => '/\b(?:somente|apenas|so|exclusivamente|preferencialmente)\W+(?:para\W+)?(?:homens|homem|mulheres|mulher|rapazes|rapaz|mocas|moca|meninos|meninas|sexo masculino|sexo feminino|solteiros|solteiras|brancos|brancas|jovens bonitos|jovens bonitas)\b/u',
             'desc' => 'Restringe a vaga a um sexo, estado civil ou aparência (vagas afirmativas legítimas são aprovadas na revisão)'],
            ['cod' => 'discriminacao.aparencia', 'cat' => 'Possível discriminação', 'alvo' => 'esqueleto', 'risco' => 'medio', 'pol' => $vaga,
             're' => '/\b(?:boa aparencia|aparencia agradavel|sem tatuagem|sem tatuagens|nao pode ter tatuagem)\b/u',
             'desc' => 'Exige aparência (prática discriminatória em seleção)'],
            ['cod' => 'discriminacao.gravidez', 'cat' => 'Discriminação', 'alvo' => 'esqueleto', 'risco' => 'alto', 'pol' => $vaga,
             're' => '/\b(?:teste de gravidez|exame de gravidez|atestado de esterilizacao|nao estar gravida|nao engravidar)\b/u',
             'desc' => 'Exigência ligada a gravidez ou esterilização (proibida pela Lei 9.029/95)'],
            ['cod' => 'discriminacao.estado_civil', 'cat' => 'Possível discriminação', 'alvo' => 'esqueleto', 'risco' => 'medio', 'pol' => $vaga,
             're' => '/\b(?:sem filhos|nao ter filhos|que nao tenha filhos|ser solteira|ser solteiro|estado civil solteiro)\b/u',
             'desc' => 'Exigência de estado civil ou de não ter filhos'],

            // ── Ofensas, assédio e ameaças ───────────────────────────────────
            ['cod' => 'ofensa.palavrao', 'cat' => 'Linguagem ofensiva', 'alvo' => 'esqueleto', 'risco' => 'medio', 'pol' => $publicos,
             're' => '/\b(?:porra|caralho|caralhos|puta|putas|putaria|merda|merdas|foda|fodase|foder|fodido|fodidos|cacete|buceta|arrombado|arrombada|arrombados|babaca|babacas|otario|otaria|otarios|imbecil|imbecis|idiota|idiotas|retardado|retardada|retardados|vagabunda|vagabundo|vagabundas|vadia|vadias|cuzao|desgracado|desgracada|lixo humano)\b/u',
             'desc' => 'Palavrão ou insulto'],
            ['cod' => 'ofensa.odio', 'cat' => 'Discurso de ódio', 'alvo' => 'esqueleto', 'risco' => 'alto', 'pol' => $publicos,
             're' => '/\b(?:viado|viados|sapatao|traveco|travecos|crioulo|criolo|macaco imundo|preto imundo|heil hitler|morte aos|morte as)\b/u',
             'desc' => 'Termo pejorativo contra grupo (raça, orientação, gênero) ou apologia de ódio'],
            ['cod' => 'ofensa.assedio', 'cat' => 'Assédio', 'alvo' => 'esqueleto', 'risco' => 'medio', 'pol' => array_merge($vaga, ['perfil_texto', 'empresa_texto']),
             're' => '/\b(?:nudes|nude|manda nudes|mande nudes|fotos sensuais|foto sensual|fotos de biquini|foto de biquini|fotos de lingerie|foto de lingerie|fotos de corpo inteiro|foto de corpo inteiro|novinha|novinhas|safada|safadas)\b/u',
             'desc' => 'Linguagem sexualizada ou pedido de fotos do corpo (padrão de assédio)'],
            ['cod' => 'ameaca', 'cat' => 'Ameaça', 'alvo' => 'esqueleto', 'risco' => 'alto', 'pol' => $publicos,
             're' => '/\b(?:vou|vamos|vai|vao)\W+te\W+(?:matar|bater|quebrar|furar|pegar|sequestrar|achar|arrebentar)\b|\b(?:te mato|sei onde voce mora|vou acabar com voce|voce vai se arrepender)\b/u',
             'desc' => 'Ameaça de violência'],

            // ── Spam e repetição ─────────────────────────────────────────────
            ['cod' => 'spam.caractere_repetido', 'cat' => 'Spam', 'alvo' => 'texto', 'risco' => 'baixo', 'pol' => $publicos,
             're' => '/(\S)\1{9,}/u', 'desc' => 'Mesmo caractere repetido 10 vezes ou mais'],
            ['cod' => 'spam.palavra_repetida', 'cat' => 'Spam', 'alvo' => 'plano', 'risco' => 'baixo', 'pol' => $textos,
             're' => '/\b(\w{3,})\b(?:[\s,]+\1\b){4,}/u', 'desc' => 'Mesma palavra repetida 5 vezes seguidas'],

            // ── Links ────────────────────────────────────────────────────────
            ['cod' => 'link.encurtador', 'cat' => 'Link suspeito', 'alvo' => 'texto', 'risco' => 'medio', 'pol' => array_merge($textos, ['link']),
             're' => '/\b(?:bit\.ly|tinyurl\.com|cutt\.ly|rb\.gy|is\.gd|ow\.ly|t\.co|shorturl\.at|encurtador\.com\.br|tiny\.cc|s\.id|goo\.gl|t\.ly|bitly\.com)\/\S+/i',
             'desc' => 'Link encurtado (esconde o destino real)'],
            ['cod' => 'link.ip', 'cat' => 'Link suspeito', 'alvo' => 'texto', 'risco' => 'medio', 'pol' => array_merge($textos, ['link']),
             're' => '/\bhttps?:\/\/\d{1,3}(?:\.\d{1,3}){3}\b/i', 'desc' => 'Link para um endereço IP em vez de um site'],
            ['cod' => 'link.punycode', 'cat' => 'Link suspeito', 'alvo' => 'texto', 'risco' => 'medio', 'pol' => array_merge($textos, ['link']),
             're' => '/\bxn--[a-z0-9-]+/i', 'desc' => 'Domínio com caracteres disfarçados (punycode)'],
            ['cod' => 'link.arquivo', 'cat' => 'Link perigoso', 'alvo' => 'texto', 'risco' => 'alto', 'pol' => array_merge($textos, ['link']),
             're' => '/\b(?:https?:\/\/|www\.)\S+\.(?:exe|apk|scr|bat|cmd|msi|vbs|jar|ps1)\b/i', 'desc' => 'Link para programa executável'],
            ['cod' => 'link.campo_indevido', 'cat' => 'Link fora de lugar', 'alvo' => 'texto', 'risco' => 'medio', 'pol' => ['nome', 'curto', 'vaga_titulo', 'vaga_item'],
             're' => '/\bhttps?:\/\/|\bwww\.\S+\.\S+/i', 'desc' => 'Link num campo que não deveria ter links'],

            // ── Personificação da equipe ─────────────────────────────────────
            ['cod' => 'personificacao.equipe', 'cat' => 'Possível personificação', 'alvo' => 'esqueleto', 'risco' => 'medio', 'pol' => ['nome'],
             're' => '/\bjovem ?connect\b|^(?:admin|administrador|administracao|moderador|moderacao|suporte|equipe|staff)(?: (?:do|da|de))?(?: jovem ?connect)?$/u',
             'desc' => 'Nome que imita a equipe ou a marca JovemConnect'],

            // ── Manipulação de IA ────────────────────────────────────────────
            ['cod' => 'ia.manipulacao', 'cat' => 'Tentativa de manipular IA', 'alvo' => 'esqueleto', 'risco' => 'baixo', 'pol' => $publicos,
             're' => '/\b(?:ignore|ignorar|ignora|desconsidere|desconsiderar|esqueca)\b(?:\W+\w+){0,4}?\W+(?:instrucoes|instrucao|regras|prompt|comandos|orientacoes)\b|\b(?:system prompt|prompt do sistema|jailbreak|ignore previous instructions|ignore all previous|modo desenvolvedor|voce agora e)\b/u',
             'desc' => 'Texto que tenta dar ordens a uma IA (sem efeito sobre as regras; só sinaliza)'],
        ];
        return $r;
    }

    /**
     * Negação logo antes do achado: "sem taxa", "não precisa pagar taxa",
     * "nunca envie sua senha", "isento de taxa". A negação só vale dentro da
     * mesma oração e se entre ela e o achado houver apenas palavras de ligação
     * (até 4) — "Sem experiência, pague a taxa" NÃO é negação.
     */
    function jc_mod_negado(string $alvo, int $pos): bool {
        $antes = substr($alvo, max(0, $pos - 70), min(70, $pos));
        $neg = '(?:sem|nao|nunca|jamais|nenhuma|nenhum|isento|isenta|isencao|gratis|gratuito|gratuita|gratuitamente|zero|proibido|proibida|vedado|vedada)';
        $lig = '(?:de|da|do|das|dos|a|o|as|os|um|uma|qualquer|nenhuma|nenhum|ha|existe|existem|havera|e|que|tipo|sua|seu|suas|seus|nossa|nosso|nos|'
             . 'cobramos|cobra|cobram|cobrado|cobrada|cobrar|cobranca|pedimos|pede|pedem|pedir|peca|solicitamos|solicita|solicitam|solicitar|solicite|'
             . 'exigimos|exige|exigem|exigir|precisa|precisam|precisara|necessario|necessaria|sera|serao|vai|vamos|deve|devem|devera|'
             . 'pague|pagar|paga|envie|enviar|informe|informar|passe|passar|forneca|fornecer|mande|mandar|compartilhe|compartilhar|'
             . 'deposite|depositar|transfira|transferir|compre|comprar|cobre|tem|tera|ter|haver|para|com|em|nem|ou|por|isso|nenhum)';
        return (bool) preg_match('/\b' . $neg . '(?: ' . $lig . '){0,4} ?$/u', $antes);
    }

    /**
     * Regras de conjunto (não cabem numa expressão só): maiúsculas, links demais,
     * repetição, valores de ganho fora da realidade.
     * @return array<int, array{cod:string,cat:string,risco:string,desc:string}>
     */
    function jc_mod_regras_conjunto(string $texto, string $pol): array {
        $achados = [];
        $publicos = ['nome', 'curto', 'perfil_texto', 'empresa_texto', 'vaga_titulo', 'vaga_texto', 'vaga_item'];
        if (in_array($pol, $publicos, true)) {
            $letras = preg_match_all('/\p{L}/u', $texto);
            $maius  = preg_match_all('/\p{Lu}/u', $texto);
            if ($letras >= 20 && $maius / max(1, $letras) > 0.7) {
                $achados[] = ['cod' => 'spam.maiusculas', 'cat' => 'Spam', 'risco' => 'baixo', 'desc' => 'Texto quase todo em maiúsculas'];
            }
        }
        if (in_array($pol, ['perfil_texto', 'empresa_texto', 'vaga_texto'], true)) {
            $links = preg_match_all('~\bhttps?://|\bwww\.~i', $texto);
            if ($links > 3) {
                $achados[] = ['cod' => 'link.excesso', 'cat' => 'Spam', 'risco' => 'medio', 'desc' => 'Mais de 3 links no mesmo texto'];
            }
            if (preg_match('~\bhttp://(?!localhost)~i', $texto)) {
                $achados[] = ['cod' => 'link.inseguro', 'cat' => 'Link', 'risco' => 'baixo', 'desc' => 'Link sem HTTPS'];
            }
            $palavras = preg_split('/[\s,]+/u', jc_mod_plano($texto)) ?: [];
            $palavras = array_values(array_filter($palavras, fn($p) => mb_strlen($p) >= 3));
            if (count($palavras) >= 20) {
                $freq = array_count_values($palavras);
                arsort($freq);
                if (reset($freq) / count($palavras) > 0.3) {
                    $achados[] = ['cod' => 'spam.repetitivo', 'cat' => 'Spam', 'risco' => 'medio', 'desc' => 'Uma palavra ocupa mais de 30% do texto'];
                }
            }
        }
        // Ganho por hora/dia/semana/mês muito acima do realista para vagas de jovens
        if (in_array($pol, ['vaga_titulo', 'vaga_texto', 'vaga_item', 'empresa_texto', 'curto'], true)
            && preg_match_all('/R\$\s?(\d{1,3}(?:\.\d{3})+|\d+)(?:,\d{1,2})?\s*(mil|k)?\s*(?:reais\s*)?(?:por|ao|na|a|\/)\s*(hora|h|dia|semana|m[eê]s)\b/iu', $texto, $mm, PREG_SET_ORDER)) {
            $limite = ['hora' => 150, 'h' => 150, 'dia' => 400, 'semana' => 3000, 'mes' => 20000, 'mês' => 20000];
            foreach ($mm as $m) {
                $valor = intval(str_replace('.', '', $m[1])) * (empty($m[2]) ? 1 : 1000);
                if ($valor > ($limite[mb_strtolower($m[3], 'UTF-8')] ?? PHP_INT_MAX)) {
                    $achados[] = ['cod' => 'fraude.ganho_desproporcional', 'cat' => 'Promessa de ganho irreal', 'risco' => 'medio',
                                  'desc' => 'Valor de ganho por hora/dia/semana/mês muito acima do realista para a plataforma'];
                    break;
                }
            }
        }
        return $achados;
    }

    /**
     * Avalia um texto para a política de moderação $pol.
     * @param array $ctx ['tipo_contrato' => ..., 'invisiveis' => int]
     * @return array{decisao:string, risco:string, regras:array, mensagem:?string}
     */
    function jc_moderar(string $texto, string $pol, array $ctx = []): array {
        $res = ['decisao' => 'aceitar', 'risco' => 'nenhum', 'regras' => [], 'mensagem' => null];
        if ($texto === '' || $pol === '') {
            return $res;
        }
        $plano     = jc_mod_plano($texto);
        $esqueleto = jc_mod_esqueleto($texto);
        // código escondido em entidades HTML (&#60;script&#62;) também conta para as regras técnicas
        $decodificado = html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $nivel     = 0;
        $evasao    = false;

        foreach (jc_mod_regras() as $regra) {
            if (!in_array($pol, $regra['pol'], true)) {
                continue;
            }
            $casou = false;
            if ($regra['alvo'] === 'texto') {
                $casou = (bool) preg_match($regra['re'], $texto)
                      || ($regra['risco'] === 'bloquear' && $decodificado !== $texto && preg_match($regra['re'], $decodificado));
            } else {
                $alvos = $regra['alvo'] === 'plano' ? [$plano] : [$plano, $esqueleto];
                foreach ($alvos as $i => $alvo) {
                    if (!preg_match_all($regra['re'], $alvo, $m, PREG_OFFSET_CAPTURE)) {
                        continue;
                    }
                    foreach ($m[0] as [$trecho, $pos]) {
                        if (!empty($regra['neg']) && jc_mod_negado($alvo, $pos)) {
                            continue;   // "sem taxa de inscrição", "nunca envie sua senha"
                        }
                        if (!empty($regra['excecao'])
                            && preg_match($regra['excecao'], substr($alvo, max(0, $pos - 40), min(40, $pos)))) {
                            continue;   // ex.: "a empresa paga o curso"
                        }
                        $casou = true;
                        if ($i === 1 && !preg_match($regra['re'], $plano)) {
                            $evasao = true;   // só apareceu depois de desfazer o disfarce
                        }
                        break 2;
                    }
                }
            }
            if (!$casou) {
                continue;
            }
            if ($regra['risco'] === 'bloquear') {
                $res['decisao']  = 'bloquear';
                $res['risco']    = 'alto';
                $res['regras'][] = ['cod' => $regra['cod'], 'cat' => $regra['cat'], 'risco' => 'alto', 'desc' => $regra['desc']];
                $res['mensagem'] = $regra['msg'] ?? 'O texto não pode ser aceito neste campo.';
                return $res;
            }
            $res['regras'][] = ['cod' => $regra['cod'], 'cat' => $regra['cat'], 'risco' => $regra['risco'], 'desc' => $regra['desc']];
            $nivel = max($nivel, jc_mod_nivel($regra['risco']));
        }

        foreach (jc_mod_regras_conjunto($texto, $pol) as $a) {
            $res['regras'][] = $a;
            $nivel = max($nivel, jc_mod_nivel($a['risco']));
        }

        // Disfarce (letras trocadas por números, espaçadas...) ou caracteres invisíveis
        // junto com outra regra: sobe um nível
        if ($nivel > 0 && ($evasao || intval($ctx['invisiveis'] ?? 0) > 0)) {
            $res['regras'][] = ['cod' => 'evasao.filtro', 'cat' => 'Tentativa de contornar filtros', 'risco' => jc_mod_risco_de_nivel(min(3, $nivel + 1)),
                                'desc' => 'O termo só foi reconhecido depois de desfazer um disfarce (números no lugar de letras, letras separadas, caracteres invisíveis)'];
            $nivel = min(3, $nivel + 1);
        }

        $res['risco'] = jc_mod_risco_de_nivel($nivel);
        if ($nivel >= 3) {
            $res['decisao'] = 'revisar';
        } elseif ($nivel >= 1) {
            $res['decisao'] = 'sinalizar';
        }
        return $res;
    }

    /** Quantos caracteres invisíveis o texto bruto tinha (contados antes da normalização). */
    function jc_mod_invisiveis($bruto): int {
        if (!is_string($bruto) || $bruto === '' || !mb_check_encoding($bruto, 'UTF-8')) {
            return 0;
        }
        return preg_match_all('/[\x{200B}\x{2060}\x{FEFF}\x{00AD}\x{180E}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', $bruto);
    }

    /**
     * Valida E modera um conjunto de campos do mapa.
     * @param array<string, mixed> $campos  chave do mapa => valor bruto
     * @param array $ctx  contexto extra para jc_moderar (ex.: tipo_contrato)
     * @return array{ok:bool, valores:array<string,string>, erros:array<string,string>,
     *               sinais:array<string,array>, risco:string, decisao:string}
     *   erros  = estruturais + bloqueios (o envio deve ser recusado)
     *   sinais = campo => resultado da moderação (só os que não foram "aceitar")
     */
    function jc_mod_analisar_campos(array $campos, array $ctx = []): array {
        $val     = jc_validar_campos($campos);
        $erros   = $val['erros'];
        $sinais  = [];
        $nivel   = 0;
        foreach ($val['valores'] as $chave => $valor) {
            if (isset($erros[$chave]) || $valor === '') {
                continue;
            }
            $pol = jc_campo($chave)['mod'] ?? null;
            if ($pol === null) {
                continue;
            }
            $r = jc_moderar($valor, $pol, $ctx + ['invisiveis' => jc_mod_invisiveis($campos[$chave] ?? '')]);
            if ($r['decisao'] === 'bloquear') {
                $erros[$chave] = jc_campo($chave)['rotulo'] . ': ' . $r['mensagem'];
                continue;
            }
            if ($r['decisao'] !== 'aceitar') {
                $sinais[$chave] = $r + ['hash' => hash('sha256', $valor)];
                $nivel = max($nivel, jc_mod_nivel($r['risco']));
            }
        }
        $decisao = $erros ? 'bloquear' : ($nivel >= 3 ? 'revisar' : ($nivel >= 1 ? 'sinalizar' : 'aceitar'));
        return ['ok' => !$erros, 'valores' => $val['valores'], 'erros' => $erros, 'sinais' => $sinais,
                'risco' => jc_mod_risco_de_nivel($nivel), 'decisao' => $decisao];
    }

    /**
     * Atalho para os formulários do site. $entrada = [chave do mapa => [valor, campo_do_formulario]].
     * Valores vazios não passam por aqui (cada formulário já trata "obrigatório"
     * com a sua própria mensagem).
     * @return array{erros: array<string,string>, valores: array<string,string>, sinais: array, risco: string, decisao: string}
     *   erros   → pelo NOME DO CAMPO DO FORMULÁRIO (estrutura inválida ou código bloqueado)
     *   valores → pela chave do mapa, já normalizados (só os campos sem erro): é o que deve ser gravado
     *   sinais  → para jc_mod_registrar() depois de gravar
     */
    function jc_mod_formulario(array $entrada, array $ctx = []): array {
        $campos = [];
        $form   = [];
        foreach ($entrada as $chave => $par) {
            [$valor, $campo_form] = $par;
            if ($valor === null || $valor === '') {
                continue;
            }
            $campos[$chave] = $valor;
            $form[$chave]   = $campo_form;
        }
        $a = jc_mod_analisar_campos($campos, $ctx);
        $erros = [];
        foreach ($a['erros'] as $chave => $msg) {
            $erros[$form[$chave] ?? $chave] = $msg;
        }
        $valores = [];
        foreach ($a['valores'] as $chave => $v) {
            if (!isset($a['erros'][$chave])) {
                $valores[$chave] = $v;
            }
        }
        $sinais = array_diff_key($a['sinais'], $a['erros']);
        return ['erros' => $erros, 'valores' => $valores, 'sinais' => $sinais, 'risco' => $a['risco'],
                'decisao' => $a['erros'] ? 'bloquear' : $a['decisao']];
    }

    /**
     * Lista de valores do mesmo campo (habilidades, requisitos, benefícios).
     * @return array{erro: ?string, valores: array, sinal: ?array}
     *   valores → mesmas chaves da entrada, normalizados (itens vazios depois da normalização saem)
     *   sinal   → um sinal só para o campo (regras somadas, risco maior)
     */
    function jc_mod_lista(string $chave, array $itens, array $ctx = []): array {
        $valores = [];
        $erro    = null;
        $sinal   = null;
        foreach ($itens as $i => $item) {
            if ($item === null || $item === '') {
                continue;
            }
            $a = jc_mod_analisar_campos([$chave => $item], $ctx);
            if ($a['erros']) {
                $erro = $erro ?? $a['erros'][$chave];
                continue;
            }
            if ($a['valores'][$chave] === '') {
                continue;
            }
            $valores[$i] = $a['valores'][$chave];
            if (isset($a['sinais'][$chave])) {
                $s = $a['sinais'][$chave];
                if ($sinal === null) {
                    $sinal = $s;
                } else {
                    $sinal['regras'] = array_merge($sinal['regras'], $s['regras']);
                    if (jc_mod_nivel($s['risco']) > jc_mod_nivel($sinal['risco'])) {
                        $sinal['risco']   = $s['risco'];
                        $sinal['decisao'] = $s['decisao'];
                        $sinal['hash']    = $s['hash'];
                    }
                }
            }
        }
        return ['erro' => $erro, 'valores' => $valores, 'sinal' => $sinal];
    }

    /**
     * Registra os sinais de moderação na fila (um item aberto por recurso + campo:
     * se já existe, soma ocorrência, junta as regras e guarda o risco maior).
     * Sem a migração da Stage 7: não faz nada. Falha ao gravar: só vai para o log
     * (a ação da pessoa não é desfeita por causa da fila).
     */
    function jc_mod_registrar(mysqli $conn, string $recurso_tipo, int $recurso_id, array $sinais,
                              ?string $autor_tipo, ?int $autor_id, string $origem = 'regra'): array {
        $ids = [];
        if (!$sinais || $recurso_id <= 0 || !jc_stage7_disponivel($conn)) {
            return $ids;
        }
        foreach ($sinais as $campo => $s) {
            $cods  = array_values(array_unique(array_column($s['regras'] ?? [], 'cod')));
            $risco = in_array($s['risco'] ?? '', ['baixo', 'medio', 'alto'], true) ? $s['risco'] : 'baixo';
            $hash  = $s['hash'] ?? null;
            try {
                $stmt = $conn->prepare(
                    "SELECT id, regras, risco FROM moderacao_itens
                     WHERE recurso_tipo = ? AND recurso_id = ? AND campo = ? AND origem = ? AND estado = 'pendente'
                     ORDER BY id DESC LIMIT 1"
                );
                $stmt->bind_param("siss", $recurso_tipo, $recurso_id, $campo, $origem);
                $stmt->execute();
                $aberto = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($aberto) {
                    $todas = array_values(array_unique(array_merge(array_filter(explode(',', (string) $aberto['regras'])), $cods)));
                    $risco_novo = jc_mod_risco_de_nivel(max(jc_mod_nivel($aberto['risco']), jc_mod_nivel($risco)));
                    $regras_txt = substr(implode(',', $todas), 0, 255);
                    $id = intval($aberto['id']);
                    $stmt = $conn->prepare(
                        "UPDATE moderacao_itens
                         SET regras = ?, risco = ?, conteudo_hash = COALESCE(?, conteudo_hash), ocorrencias = ocorrencias + 1
                         WHERE id = ?"
                    );
                    $stmt->bind_param("sssi", $regras_txt, $risco_novo, $hash, $id);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    $regras_txt = substr(implode(',', $cods), 0, 255);
                    $stmt = $conn->prepare(
                        "INSERT INTO moderacao_itens
                            (recurso_tipo, recurso_id, campo, autor_tipo, autor_id, origem, regras, risco, conteudo_hash)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    $stmt->bind_param("sississss", $recurso_tipo, $recurso_id, $campo, $autor_tipo, $autor_id,
                                      $origem, $regras_txt, $risco, $hash);
                    $stmt->execute();
                    $id = intval($conn->insert_id);
                    $stmt->close();
                }
                $ids[$campo] = $id;
                if (function_exists('jc_log')) {
                    jc_log('moderacao.sinalizado', $risco === 'alto' ? 'aviso' : 'info',
                           ['recurso' => $recurso_tipo, 'id' => $recurso_id, 'campo' => $campo, 'risco' => $risco,
                            'regras' => implode(',', $cods), 'item' => $id]);
                }
            } catch (Throwable $e) {
                if (function_exists('jc_log_excecao')) {
                    jc_log_excecao($e, 'moderacao.erro_fila');
                }
            }
        }
        return $ids;
    }

    /** Explicação de uma regra (para a Central). */
    function jc_mod_descricao_regra(string $cod): string {
        foreach (jc_mod_regras() as $r) {
            if ($r['cod'] === $cod) {
                return $r['desc'];
            }
        }
        $extras = [
            'spam.maiusculas' => 'Texto quase todo em maiúsculas',
            'link.excesso'    => 'Mais de 3 links no mesmo texto',
            'link.inseguro'   => 'Link sem HTTPS',
            'spam.repetitivo' => 'Uma palavra ocupa mais de 30% do texto',
            'fraude.ganho_desproporcional' => 'Valor de ganho por hora/dia/semana/mês muito acima do realista para a plataforma',
            'evasao.filtro'   => 'Termo reconhecido só depois de desfazer um disfarce',
            'flood.vagas'     => 'Muitas vagas publicadas pela mesma empresa em pouco tempo',
            'ia.alerta'       => 'A análise complementar por IA apontou risco (só orientação; a decisão é humana)',
        ];
        if (str_starts_with($cod, 'denuncia.')) {
            $m = jc_denuncia_motivos()[substr($cod, 9)] ?? null;
            return $m ? 'Denúncia: ' . $m : 'Denúncia de usuário';
        }
        return $extras[$cod] ?? $cod;
    }

    /** Motivos de denúncia de vaga (código → texto) e o risco inicial de cada um. */
    function jc_denuncia_motivos(): array {
        return [
            'golpe'           => 'Parece golpe ou fraude',
            'pagamento'       => 'Pede pagamento, taxa ou compra',
            'dados_pessoais'  => 'Pede documentos, senhas ou dados bancários',
            'discriminacao'   => 'Tem critério discriminatório',
            'ofensivo'        => 'Tem conteúdo ofensivo',
            'enganosa'        => 'Informações falsas ou enganosas',
            'outro'           => 'Outro motivo',
        ];
    }

    function jc_denuncia_risco(string $motivo): string {
        return in_array($motivo, ['golpe', 'pagamento', 'dados_pessoais'], true) ? 'alto'
             : (in_array($motivo, ['discriminacao', 'ofensivo'], true) ? 'medio' : 'baixo');
    }
}
