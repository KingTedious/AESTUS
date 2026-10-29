# Origem dos ativos

> **Classificação: INTERNO.** Versão v4.60 · verificação e respostas do responsável de 29/10/2026 (PI-10 do `RELATORIO_AUDITORIA_PI.md`).
> Registra a origem conhecida e a licença de cada imagem, logotipo, ícone, fonte, biblioteca e serviço de
> terceiros. **Quando a origem não é conhecida, está escrito "desconhecida"**: nenhuma licença foi presumida e
> nenhuma imagem foi trocada. Os avisos de licença completos estão em [`THIRD_PARTY_NOTICES.md`](../THIRD_PARTY_NOTICES.md).
> Não é parecer jurídico.

## Como foi verificado

- **Imagens:** leitura dos metadados de cada arquivo (EXIF, XMP e o manifesto C2PA, que registra a
  procedência do arquivo) e dimensões. O SHA-256 identifica exatamente o arquivo examinado (primeiros 16
  caracteres; os arquivos do projeto são iguais aos do PC).
- **Ícones:** avisos de licença dentro de `assets/css/style.css` e `assets/css/admin.css`, conferidos com a
  página oficial de licença do Lucide.
- **Bibliotecas Python:** metadados dos pacotes instalados no ambiente de verificação e, para os dois sem
  metadados ali, páginas públicas de distribuição (referências na tabela).
- **Bibliotecas PHP:** `composer.json` e Packagist.

## Imagens e logotipos

| Arquivo | Dimensões · bytes · SHA-256 | Metadados encontrados | Origem | Licença | Pendência |
|---|---|---|---|---|---|
| `assets/img/home/pessoa-computador.jpg` | 5331×3648 · 1.273.497 · `1c354430926c9cde` | nenhum (sem EXIF, XMP ou C2PA) | copiada de resultado do Google Imagens (busca "pessoa usando notebook") [Declarado]. A mesma foto aparece no blog da Agilize (artigo "CNAE para prestação de serviço", publicado em 20/04/2026, sem crédito de imagem; o site declara "© 2026 Direitos Reservados"). Fonte original não identificada | **nenhuma licença obtida** (uso sem autorização). Mostra uma pessoa: também há direito de imagem | **decisão do responsável (29/10/2026): manter por enquanto**; recomendação: trocar por foto própria ou com licença documentada antes de qualquer uso fora da apresentação |
| `assets/img/home/edificio-empresa.jpg` | 683×1024 · 152.893 · `130452bcddebd190` | C2PA assinado pela Anthropic: "Claude provided this file at the request of a user and may have created or modified the file contents"; origem declarada no manifesto: *unknown*; ação: arquivo aberto e fornecido | copiada de resultado do Google Imagens (busca "edifício empresarial") e ajustada pelo Claude para o formato da página [Declarado]; fonte original não identificada | **nenhuma licença obtida** (uso sem autorização) | idem |
| `assets/img/marca/jovemconnect-horizontal.png` | 848×291 · 33.025 · `26f1979a310f1d03` | C2PA (mesmo texto, origem *unknown*) | logotipo do projeto, **criação da equipe**: desenho de Renato Braga de Mello, no Canva e no Photoshop, só com formas geométricas e letras, **sem elementos gráficos do acervo do Canva** [Declarado] | marca própria; todos os direitos reservados ([`LICENSE`](../LICENSE)); titularidade: [TITULARIDADE_E_LICENCA.md](TITULARIDADE_E_LICENCA.md) | guardar os arquivos editáveis (projeto do Canva, PSD) e anotar as fontes usadas |
| `assets/img/marca/jovemconnect-horizontal-claro.png` | 848×291 · 33.383 · `35edf646c668cc2f` | C2PA (idem) | idem; versão para o tema claro | idem | idem |
| `assets/img/marca/jovemconnect-vertical.png` | 878×689 · 53.967 · `f6de10fae2e6d9ff` | C2PA (idem) | idem | idem | idem |
| `assets/img/marca/jovemconnect-vertical-claro.png` | 878×689 · 54.546 · `cbfaa755e351277a` | C2PA (idem) | idem | idem | idem |
| `assets/img/marca/jovemconnect-simbolo.png` | 910×910 · 52.565 · `a40f5d1372242810` | C2PA (idem) | idem | idem | idem |
| `assets/img/marca/jovemconnect-simbolo-claro.png` | 910×910 · 51.336 · `67997fba00174b70` | C2PA (idem) | idem | idem | idem |
| `assets/img/marca/jovemconnect-icone-180.png` | 180×180 · 17.258 · `846b5d2db31b9801` | C2PA (idem) | ícone derivado do símbolo | idem | idem |
| `assets/img/marca/aestus-horizontal.png` | 760×296 · 128.710 · `9a38ae8979d351fe` | C2PA (mesmo texto dos demais), acrescentado automaticamente na entrega do arquivo pelo Claude; os pixels são idênticos aos do recorte. Recortado e reduzido do PNG enviado pelo responsável em 29/10/2026 (3000×3000, fundo transparente) | logotipo da **Aestus**, criação da equipe (mesmo autor dos logotipos do JovemConnect) [Declarado]; usado no rodapé da página inicial ("Criado por") | marca própria; todos os direitos reservados | guardar os arquivos editáveis |
| `assets/img/marca/jovemconnect-icone-32.png` | 32×32 · 7.501 · `189850b750aba328` | C2PA (idem) | ícone derivado do símbolo | idem | idem |

Observações:
- Segundo o relatório da versão final do TCC (22/10/2026), os seis PNG de logotipo (horizontal, vertical e
  símbolo, nas versões escura e clara) foram **enviados pelo responsável** e gravados no projeto naquela
  etapa. O manifesto C2PA foi acrescentado quando o arquivo passou pelo Claude (preparo dos arquivos); o
  desenho é da equipe, segundo o responsável (29/10/2026). Os logotipos da Aestus têm a mesma autoria.
- Para um registro de marca, guarde junto: os arquivos editáveis, as fontes usadas (e a licença delas) e a
  data de criação. As fontes de texto do Canva e do Photoshop têm licenças próprias; confira se permitem uso
  em logotipo.
- **Fotos sem licença:** encontrar onde uma foto aparece não dá permissão para usá-la. A Lei 9.610/1998 não
  tem uma exceção geral para uso educacional que cubra reproduzir uma foto numa página; numa apresentação
  restrita a alunos o risco prático é baixo, mas o uso continua sem autorização. Antes de qualquer uso
  público (site no ar, vídeo, divulgação), troque por fotos próprias ou com licença documentada.
- **De onde vem o C2PA (verificado em 29/10/2026):** ao entregar `aestus-horizontal.png`, o ambiente do Claude
  acrescentou o manifesto C2PA ao arquivo automaticamente, sem mudar nenhum pixel (o recorte local não tinha
  manifesto). Isso confirma que o C2PA dos logotipos indica só que o arquivo **passou** pelo Claude na entrega,
  não que o Claude desenhou.
- **Correção do relatório PI:** ele registrou "sem metadados" nos logotipos horizontal, vertical e símbolo.
  A verificação de 29/10/2026 encontrou o manifesto C2PA em **todos os oito** PNG (arquivos sem alteração
  desde 22/10).
- Marca e nome ("JovemConnect", "Aestus") são tratados em [TITULARIDADE_E_LICENCA.md](TITULARIDADE_E_LICENCA.md).

## Ícones da interface

| Item | Onde | Licença | Referência | Situação |
|---|---|---|---|---|
| Ícones Lucide (máscaras SVG `.i-*`) | `assets/css/style.css` (54 ícones) e `assets/css/admin.css` (33) | ISC — "Copyright (c) 2026 Lucide Icons and Contributors" | https://lucide.dev/license (conferido em 29/10/2026: mesmo texto do aviso) | aviso completo nos **dois** arquivos (em `admin.css` desde a v4.60; antes havia só uma menção) |
| Ícones derivados do Feather (21, listados no aviso) | idem | MIT — "Copyright (c) 2013-present Cole Bemis" | aviso de licença do Lucide para as partes do Feather | aviso completo nos dois arquivos |

## Fontes

| Fonte | Como é usada | Licença | Referência | Observação |
|---|---|---|---|---|
| Inter (Rasmus Andersson) | carregada do Google Fonts (`style.css` `@import` e `includes/header.php`); **não** vem com o projeto | SIL Open Font License 1.1 (OFL-1.1) | https://fontsource.org/fonts/inter/about (conferido em 29/10/2026) | cada visita envia o IP do visitante ao Google (SEG-27; citado na Política de Privacidade) |
| OpenDyslexic | só o **nome** na lista de fontes do painel de acessibilidade (`includes/assistente.php`); o arquivo da fonte **não** vem com o projeto | não verificada (não se aplica enquanto não for distribuída) | — | só funciona se estiver instalada no computador do visitante |

## Bibliotecas Python (servidor de IA)

Instaladas pelo `iniciar_ia.bat`/`.sh` no `venv` local, a partir de `IA/requirements-ia.txt`. **Não são
distribuídas** com o projeto (`venv/` fica fora do Git).

| Pacote (versão fixada) | Licença | Fonte da verificação |
|---|---|---|
| flask 3.1.3 | BSD-3-Clause | metadados do pacote (3.1.3) |
| werkzeug 3.1.8 | BSD-3-Clause | metadados do pacote (3.1.9 no ambiente de verificação; mesma licença) |
| jinja2 3.1.6 | BSD (3 cláusulas) | metadados do pacote (3.1.6) |
| itsdangerous 2.2.0 | BSD (3 cláusulas) | metadados do pacote (2.2.0) |
| click 8.3.3 | BSD-3-Clause | metadados do pacote (8.5.0 no ambiente de verificação; mesma licença) |
| markupsafe 3.0.3 | BSD-3-Clause | metadados do pacote (3.0.3) |
| blinker 1.9.0 | MIT | metadados do pacote (1.9.0) |
| flask-cors 6.0.2 | MIT | pacote 6.0.2 do Arch Linux (https://archlinux.org/packages/extra-staging/any/python-flask-cors), projeto https://github.com/corydolphin/flask-cors. **Sem uso no código desde a v4.60** (o CORS foi retirado); continua na lista para não invalidar os `venv` existentes |
| colorama 0.4.6 (só Windows) | BSD-3-Clause | README de terceiros do Chromium para a versão 0.4.6 (https://chromium.googlesource.com/chromium/src/+/lkgr/third_party/colorama/README.chromium) |

`requirements.txt` (raiz) é uma lista antiga e informativa; o que vale para a IA é o `IA/requirements-ia.txt`.

## Bibliotecas PHP (opcionais)

| Pacote | Licença | Situação |
|---|---|---|
| phpmailer/phpmailer ^6.9 | LGPL-2.1-only (Packagist, conferido em 29/10/2026) | **não distribuído**: não há `vendor/` no projeto. Se um dia o `vendor/` for entregue junto, a LGPL exige manter o aviso e permitir a substituição da biblioteca |
| twilio/sdk ^8.3 | MIT (metadados do repositório twilio/twilio-php) | não distribuído |

## Serviços externos usados em tempo de execução

| Serviço | Para quê | Chamado por | Termos |
|---|---|---|---|
| Google Gemini API | análise contextual, assistente, moderação consultiva (opcional) | servidor (`IA/recomendar.py`) | ver **TER-01** abaixo: o uso atual **não atende** aos termos da API. O que o Gemini gera em tempo de uso é texto para o usuário, não código do projeto |
| Google Fonts | fonte Inter | navegador do visitante | termos do Google Fonts |
| ViaCEP | preencher endereço pelo CEP | navegador do visitante | serviço gratuito, sem termos formais; avisa que uso massivo (validar bases inteiras) bloqueia o acesso por tempo indeterminado e que não distribui nem vende bases (conferido em 29/10/2026). O uso do projeto (uma consulta por cadastro) é compatível |
| BrasilAPI | consultar CNPJ no cadastro de empresa | navegador do visitante | projeto gratuito e experimental, "em beta e ainda elaborando os Termos de Uso"; pede para não abusar e que o volume tenha "a natureza de uma pessoa real" (conferido em 29/10/2026). Compatível com o uso do projeto; sem garantia de disponibilidade |
| SMTP (PHPMailer) e Twilio | e-mail e SMS de verificação (opcionais) | servidor | contas do responsável |

### TER-01 — Termos da API do Gemini (achado de 29/10/2026)

Termos adicionais da API do Gemini, versão de 28/04/2026 (https://ai.google.dev/terms):

- "You must be 18 years of age or older to use the APIs", e a API não pode ser usada em site ou aplicativo
  "directed towards or is likely to be accessed by individuals under the age of 18";
- nos serviços **gratuitos**, o Google usa o conteúdo enviado e as respostas para melhorar os produtos, e
  pessoas podem ler esse conteúdo; nos pagos, não.

Situação [Declarado]: o público do JovemConnect começa aos **14 anos**; a chave é do plano **gratuito**; a
conta Google titular da chave é de pessoa maior de idade (quem aceitou os termos e responde por eles). O uso
**não atende** aos termos. **Decisão do responsável (29/10/2026): manter até depois da apresentação**, porque a
análise contextual faz parte da demonstração; corrigir depois. Mitigação na apresentação: análises com IA
feitas pela equipe, com as contas de demonstração (dados fictícios); visitantes orientados a não digitar dados
pessoais no assistente ([APRESENTACAO.md](APRESENTACAO.md)). O sistema já mascara e-mail, CPF, CNPJ, telefone,
CEP e números longos antes do envio.

Caminhos para depois (escolha do responsável; nenhum implementado): usar o Gemini só para contas de 18 anos ou
mais (as demais recebem as respostas prontas, que já existem); trocar de provedor, conferindo os termos dele
para público menor de idade; ou desligar a IA generativa. O plano pago resolve o uso dos dados para
treinamento, **não** a regra de idade.

## Código e textos produzidos com ferramentas de IA

Parte do código, dos textos e da documentação foi produzida ou alterada com ferramentas de IA (ChatGPT,
Gemini — inclusive pela extensão Cline — e Claude), a pedido e sob decisão do responsável. Detalhes, períodos e limitações em
[AUTORIA_E_ORIGEM.md](AUTORIA_E_ORIGEM.md); efeitos sobre a titularidade em
[TITULARIDADE_E_LICENCA.md](TITULARIDADE_E_LICENCA.md).

## Pendências

- [ ] Trocar `pessoa-computador.jpg` e `edificio-empresa.jpg` por imagens próprias ou com licença documentada
      antes de qualquer uso público (mantidas por enquanto, por decisão de 29/10/2026).
- [ ] Guardar os arquivos editáveis dos logotipos e anotar as fontes usadas.
- [ ] TER-01: adequar o uso do Gemini aos termos depois da apresentação.
- [ ] Ao incluir qualquer imagem, fonte ou biblioteca nova: registrar aqui antes do commit (origem, licença,
      referência, data).
