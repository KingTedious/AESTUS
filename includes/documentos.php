<?php
/* ============================================================
   includes/documentos.php — conteúdo dos Termos de Uso e da Política
   de Privacidade (MINUTAS, sujeitas a revisão jurídica).

   O texto descreve o que o código faz hoje. Ao mudar o sistema
   (dados coletados, serviços externos, regras de visibilidade,
   retenção de logs), atualize o texto, troque JC_TERMOS_VERSAO /
   JC_TERMOS_DATA em includes/termos.php e revise com a assessoria
   jurídica antes de publicar como versão final.

   Usado em dois lugares, com o mesmo conteúdo:
     - includes/institucional.php → modais (resumo + "Saiba mais");
     - documentos.php (raiz)       → página própria (sem JavaScript,
                                     para imprimir ou ler com calma).
   Conteúdo estático escrito aqui (não vem do usuário).
   ============================================================ */
require_once __DIR__ . '/termos.php';

if (!defined('JC_CONTATO_EMAIL')) {
    define('JC_CONTATO_EMAIL', 'startupaestus@gmail.com');   // e-mail oficial de contato
}

if (!function_exists('jc_documentos')) {

    /**
     * @return array<string, array{titulo: string, icone: string, resumo: string[], secoes: array<int, array{0: string, 1: string}>}>
     *   secoes = [título, HTML do corpo]
     */
    function jc_documentos(): array
    {
        $email = filter_var(JC_CONTATO_EMAIL, FILTER_VALIDATE_EMAIL) ? htmlspecialchars(JC_CONTATO_EMAIL, ENT_QUOTES) : '';
        $mail  = $email !== '' ? '<a href="mailto:' . $email . '">' . $email . '</a>' : 'o canal indicado em “Contato”';

        return [
            'termos' => [
                'titulo' => 'Termos de Uso',
                'icone'  => 'file-text',
                'resumo' => [
                    'O JovemConnect é uma plataforma acadêmica que aproxima jovens em busca do primeiro emprego (aprendiz, estágio e vagas iniciais) de empresas.',
                    'Você é responsável pelas informações que publica: elas precisam ser verdadeiras e suas.',
                    'Empresas só podem publicar vagas reais e usar os dados dos candidatos apenas para o processo seletivo.',
                    'A nota de compatibilidade (Match) e os textos da IA são apoio à decisão, não garantia de contratação.',
                    'Os textos publicados passam por verificações automáticas e podem ser revisados pela equipe; jovens podem denunciar vagas suspeitas.',
                    'Você pode excluir sua conta a qualquer momento pelo seu perfil.',
                ],
                'secoes' => [
                    ['1. Sobre a plataforma', '<p>O JovemConnect é um projeto acadêmico (Trabalho de Conclusão de Curso) que conecta jovens a oportunidades de Jovem Aprendiz, estágio e primeiro emprego, e empresas a esses candidatos. A plataforma não é agência de empregos, não participa da contratação e não cobra dos usuários.</p>'],
                    ['2. Contas', '<p>Há dois tipos de conta: <strong>jovem</strong> (candidato) e <strong>empresa</strong>. Para criar uma conta é preciso informar dados verdadeiros, criar uma senha de pelo menos 8 caracteres e aceitar estes Termos e a Política de Privacidade. A conta é pessoal: não compartilhe sua senha. Se você tiver menos de 18 anos, leia estes documentos junto com seu responsável legal.</p>'],
                    ['3. O que é permitido', '<ul><li>Jovens: manter o perfil atualizado, enviar currículo em PDF, favoritar vagas e se candidatar.</li><li>Empresas: cadastrar locais de trabalho, publicar e remover vagas, ver os candidatos das próprias vagas e alterar o status das candidaturas.</li></ul>'],
                    ['4. O que não é permitido', '<ul><li>Informar dados falsos ou de outra pessoa.</li><li>Publicar vagas falsas, discriminatórias, que cobrem do candidato ou que não sigam a legislação trabalhista (por exemplo, a Lei do Aprendiz, nº 10.097/2000).</li><li>Usar dados de candidatos para outra finalidade que não o processo seletivo, ou repassá-los a terceiros.</li><li>Publicar conteúdo ofensivo, discriminatório, enganoso, repetitivo (spam), ameaças ou assédio, ou pedir pagamento, senhas, documentos ou dados bancários de candidatos.</li><li>Inserir código ou links maliciosos, ou tentar acessar contas, currículos ou áreas sem permissão, ou atrapalhar o funcionamento da plataforma.</li></ul>'],
                    ['5. Match e inteligência artificial', '<p>A nota de compatibilidade é calculada por regras fixas (localização, área, curso, escolaridade, tipo de contrato, habilidades e experiência). Quando o serviço de IA está configurado, textos explicativos podem ser gerados pelo Google Gemini; eles não mudam a nota e podem conter imprecisões. O Assistente JovemConnect é um programa de IA, não uma pessoa, e não realiza ações na sua conta. Nenhuma decisão de contratação deve se basear apenas nesses resultados.</p>'],
                    ['6. Disponibilidade', '<p>Por ser um projeto acadêmico, a plataforma pode ficar fora do ar, mudar ou ser encerrada sem aviso prévio. Recursos que dependem de serviços externos (envio de e-mail e SMS, consulta de CEP e CNPJ, IA) podem não estar disponíveis.</p>'],
                    ['7. Moderação e denúncias', '<p>Os textos enviados (perfis, vagas, locais de trabalho e denúncias) passam por verificações automáticas: código que possa ser executado é recusado, e indícios de golpe, cobrança do candidato, pedido de dados pessoais, discriminação, ofensa ou exposição de dados podem levar o conteúdo à revisão da equipe. Uma vaga com indício grave fica fora do ar até essa revisão. Jovens podem denunciar vagas; a empresa não fica sabendo quem denunciou, e a denúncia não tira a vaga do ar sozinha. As decisões (manter, corrigir um texto, tirar uma vaga do ar, suspender uma conta) são sempre tomadas por pessoas da equipe e ficam registradas. A equipe pode consultar uma IA como apoio, mas a IA não decide nem altera nada.</p>'],
                    ['8. Encerramento e suspensão da conta', '<p>Você pode excluir sua conta pelo seu perfil. A equipe pode suspender contas que descumpram estes Termos: a suspensão bloqueia o acesso e oculta o conteúdo da conta, não apaga dados e pode ser revertida. Para pedir esclarecimentos, escreva para ' . $mail . '.</p>'],
                    ['9. Alterações e contato', '<p>Quando estes Termos mudarem, a versão e a data abaixo serão atualizadas. Dúvidas: ' . $mail . '.</p>'],
                ],
            ],
            'privacidade' => [
                'titulo' => 'Política de Privacidade',
                'icone'  => 'shield',
                'resumo' => [
                    'Coletamos só o que a plataforma usa: dados de cadastro, perfil profissional, currículo (se você enviar) e registros técnicos de segurança.',
                    'Empresas veem seu perfil profissional quando você se candidata a uma vaga delas ou quando seu perfil está visível para empresas. CPF, CEP, endereço, telefone e data de nascimento não aparecem para empresas.',
                    'Seu e-mail só é mostrado à empresa em cuja vaga você se candidatou.',
                    'A equipe administrativa vê os dados de identificação da conta e o conteúdo público para moderação e suporte; suas ações ficam registradas.',
                    'Alguns serviços externos recebem dados quando você usa certos recursos (CEP, CNPJ, e-mail, SMS e IA) — veja a lista completa em "Saiba mais".',
                    'Você pode corrigir seus dados no perfil, esconder seu perfil das buscas ou excluir a conta.',
                ],
                'secoes' => [
                    ['1. Quem cuida dos dados', '<p>A equipe do projeto acadêmico JovemConnect. Contato para assuntos de privacidade: ' . $mail . '.</p>'],
                    ['2. Dados coletados', '<ul>'
                        . '<li><strong>Jovens:</strong> nome, e-mail, CPF, data de nascimento, CEP, cidade e UF, escolaridade e formação, se tem carteira de trabalho (CLT), área de interesse, resumo profissional, habilidades, formações adicionais, experiências, currículo em PDF (opcional) e telefone (se você usar a verificação por SMS).</li>'
                        . '<li><strong>Empresas:</strong> nome, e-mail, CNPJ, endereço principal, área de atuação, descrição, site, locais de trabalho e vagas publicadas.</li>'
                        . '<li><strong>Uso da plataforma:</strong> candidaturas, favoritos, status das candidaturas e notas de compatibilidade calculadas.</li>'
                        . '<li><strong>Aceite:</strong> versão destes documentos e data em que você os aceitou no cadastro.</li>'
                        . '<li><strong>Moderação:</strong> denúncias de vagas feitas por jovens (motivo e detalhe opcional), sinalizações automáticas de conteúdo (sem cópia do texto) e o histórico das decisões da equipe administrativa.</li>'
                        . '</ul>'],
                    ['3. Para que usamos', '<ul><li>Criar e manter sua conta e permitir o login.</li><li>Mostrar vagas e calcular a compatibilidade entre perfil e vaga.</li><li>Permitir que empresas avaliem candidatos às suas vagas.</li><li>Verificar e-mail/telefone e recuperar a senha.</li><li>Proteger a plataforma contra acessos indevidos.</li><li>Moderar conteúdos e analisar denúncias, para evitar golpes, discriminação e abusos.</li></ul>'],
                    ['4. Quem vê seus dados', '<ul>'
                        . '<li><strong>Empresas</strong> veem nome, idade, cidade/UF, área de interesse, resumo, escolaridade, formações, experiências, habilidades e o currículo em PDF — somente quando você se candidatou a uma vaga delas ou quando a opção "Permitir que empresas encontrem seu currículo nas buscas" está ligada no seu perfil.</li>'
                        . '<li>O <strong>e-mail</strong> só aparece para a empresa em cuja vaga você se candidatou.</li>'
                        . '<li><strong>CPF, CEP, endereço, telefone e data de nascimento</strong> não são mostrados a empresas.</li>'
                        . '<li>Para os jovens, aparecem o nome da empresa e os dados das vagas publicadas (título, descrição, requisitos, benefícios, salário e local de trabalho).</li>'
                        . '<li>A <strong>equipe administrativa</strong> do projeto vê, para moderação e suporte, os dados de identificação da conta (nome, e-mail, cidade/UF, datas de cadastro) e o conteúdo público do perfil e das vagas. CPF, data de nascimento, telefone, endereço completo, senha e currículo não aparecem na área administrativa. Quem denunciou uma vaga só é conhecido pela equipe.</li>'
                        . '</ul>'],
                    ['5. Serviços externos', '<p>Alguns recursos enviam dados a serviços de terceiros, que têm políticas próprias:</p><ul>'
                        . '<li><strong>ViaCEP</strong> — o CEP digitado, para preencher cidade e UF (consulta feita pelo seu navegador).</li>'
                        . '<li><strong>BrasilAPI</strong> — o CNPJ digitado no cadastro de empresa, quando você usa o botão de consulta.</li>'
                        . '<li><strong>Servidor de e-mail (SMTP)</strong> configurado pela equipe — seu e-mail, para enviar códigos de verificação e de recuperação de senha.</li>'
                        . '<li><strong>Twilio</strong> — seu telefone, se você escolher a verificação por SMS.</li>'
                        . '<li><strong>Google Gemini</strong> (quando configurado) — para os textos de IA: dados profissionais e da vaga na análise de compatibilidade (cidade/UF, formação, área, habilidades, experiências, formações adicionais e dados da vaga, sem nome, e-mail, CPF, telefone, CEP ou data de nascimento); o resumo profissional, nas sugestões de currículo; as mensagens que você digitar no Assistente; e, só quando a equipe pede uma análise de moderação, os textos públicos de uma vaga ou de um perfil, com e-mails, telefones, documentos e endereços de links mascarados.</li>'
                        . '<li><strong>Google Maps</strong> — só se você clicar no link do mapa de uma vaga: o endereço do local de trabalho é aberto no site do Google Maps.</li>'
                        . '<li><strong>Google Fonts</strong> — as páginas carregam as fontes do Google, que recebe dados técnicos da conexão (como o endereço IP).</li>'
                        . '</ul>'],
                    ['6. Armazenamento e segurança', '<p>As senhas são guardadas como <em>hash</em> (não é possível ler a senha original). O currículo em PDF só é entregue a você e às empresas com permissão (regra do item 4); o acesso direto à pasta de arquivos é bloqueado. O cookie de sessão não pode ser lido por scripts da página. A proteção da conexão (HTTPS) depende do servidor onde a plataforma for publicada.</p>'],
                    ['7. Registros técnicos (logs)', '<p>Para segurança, registramos eventos como login, falhas de acesso, cadastros e erros, com data e hora, tipo e número da conta e endereço IP parcialmente anonimizado. Senhas, códigos, CPF, e-mail, telefone e textos digitados não são gravados nesses registros. Os arquivos de log são separados por dia e apagados automaticamente depois de cerca de 30 dias (a limpeza acontece quando o sistema grava novos registros; o prazo é configurável pela equipe). As ações da equipe administrativa (por exemplo, suspender uma conta ou tirar uma vaga do ar) ficam num histórico próprio, com o motivo e a justificativa (e-mails e números longos mascarados), guardado para auditoria.</p>'],
                    ['8. Navegador', '<p>Usamos um cookie de sessão (necessário para o login). O navegador guarda localmente apenas suas preferências de tema e de acessibilidade; nenhum dado pessoal é guardado ali pela plataforma.</p>'],
                    ['9. Seus direitos', '<ul><li>Ver e corrigir seus dados na página do perfil.</li><li>Esconder seu perfil das buscas das empresas (as empresas das vagas em que você já se candidatou continuam vendo seu perfil para aquela candidatura).</li><li>Excluir a conta: o cadastro e os dados ligados a ele (candidaturas, favoritos, habilidades, formações, experiências, avaliações e, para empresas, as vagas) são removidos do banco de dados, e o arquivo do currículo é apagado. Registros técnicos seguem o prazo do item 7; cópias de segurança dependem do servidor de hospedagem.</li><li>Pedir informações sobre seus dados ou sobre uma suspensão da sua conta pelo e-mail ' . $mail . '. A suspensão não apaga dados.</li></ul>'],
                    ['10. Alterações', '<p>Quando esta Política mudar, a versão e a data abaixo serão atualizadas.</p>'],
                ],
            ],
        ];
    }

    /** Selo "minuta" + versão/data (mesmo texto no modal e na página). */
    function jc_documento_versao(): string
    {
        return '<p class="doc-versao"><span class="pendente-selo"><span class="i i-alert-triangle" aria-hidden="true"></span> Minuta — sujeita a revisão jurídica</span>'
             . '<span>Versão ' . htmlspecialchars(JC_TERMOS_VERSAO) . ' · atualizada em ' . htmlspecialchars(JC_TERMOS_DATA) . '</span></p>';
    }

    /** Seções completas (camada 2). $nivel = nível do título das seções (h3 no modal, h2 na página). */
    function jc_documento_secoes(array $doc, int $nivel = 3): string
    {
        $h = 'h' . max(2, min(4, $nivel));
        $saida = '';
        foreach ($doc['secoes'] as [$titulo, $corpo]) {
            $saida .= "<{$h}>" . htmlspecialchars($titulo) . "</{$h}>" . $corpo;
        }
        return $saida;
    }

    /** Resumo (camada 1). */
    function jc_documento_resumo(array $doc): string
    {
        $itens = '';
        foreach ($doc['resumo'] as $linha) {
            $itens .= '<li>' . htmlspecialchars($linha) . '</li>';
        }
        return '<ul class="doc-resumo">' . $itens . '</ul>';
    }
}

if (!function_exists('jc_copyright')) {
    /**
     * Aviso de direitos autorais mostrado nos rodapés (mesmo titular do arquivo LICENSE).
     * "© 2026" no primeiro ano; depois, "© 2026–<ano atual>".
     */
    function jc_copyright(): string
    {
        $inicio = 2026;
        $atual  = (int) date('Y');
        $anos   = $atual > $inicio ? $inicio . '–' . $atual : (string) $inicio;
        return '© ' . $anos . ' JovemConnect · Aestus. Todos os direitos reservados.';
    }
}
