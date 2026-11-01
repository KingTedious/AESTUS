# Dia da apresentação

> **Classificação: INTERNO.** Versão v4.60 (29/10/2026). Roteiro para a apresentação no estande: montar,
> apresentar e limpar. Decisões do responsável de 29/10/2026: demonstração com banco novo (`schema.sql` atual +
> `database/seed_demo.sql`), acesso só pelos computadores da equipe (dois notebooks e um PC da escola),
> limpeza dos dados no fim e o site da Aestus abrindo o JovemConnect por link.

## Como os computadores vão acessar

O site roda no PC de casa (XAMPP) e é acessado pelos computadores da apresentação. Há dois jeitos de usar o
Tailscale, e eles são muito diferentes:

| | **Tailscale Serve** (recomendado) | **Tailscale Funnel** |
|---|---|---|
| Quem acessa | só aparelhos conectados à **sua** conta Tailscale | **qualquer pessoa na internet** |
| O que fica exposto | o que o Apache serve, só para os seus aparelhos | tudo o que o Apache serve: o JovemConnect, o site da Aestus, o `.git` da raiz do `htdocs`, o phpMyAdmin (no padrão do XAMPP, o usuário root sem senha abre o banco sem login) e as demais pastas do `htdocs` |
| Descoberta | — | o endereço `….ts.net` é publicado num registro público de certificados, varrido por robôs; não divulgar não esconde |
| Travas "só acesso local" | — | não funcionam: o Funnel entrega as visitas como se viessem do próprio PC |

**Plano:**

- **Os dois notebooks:** instalar o Tailscale, entrar na mesma conta e acessar pelo endereço do **Serve**.
- **O PC da escola:** se der para instalar o Tailscale e entrar na conta, igual aos notebooks; se não der,
  rodar o JovemConnect **localmente** nele, como nos testes da escola (`iniciar_jovemconnect.bat` /
  `parar_jovemconnect.bat`), com o mesmo banco de demonstração.
- **Funnel:** só se não houver outra saída, e **depois** de pedir a configuração específica (uma porta só com
  o JovemConnect e o site da Aestus, sem phpMyAdmin nem outras pastas), conferida pelo verificador. Nunca
  ligue o Funnel com a configuração padrão do XAMPP.

## Antes (na véspera)

No PC de casa:

1. Apagar as pastas de teste do `htdocs` (`cline_feats`, `usavel`) e descobrir o que é o `.git` da raiz do
   `htdocs` (SEG-35).
2. No `C:\xampp\apache\conf\httpd.conf`, trocar `Listen 80` por `Listen 127.0.0.1:80` e reiniciar o Apache:
   o Tailscale continua alcançando o site (ele chega por `127.0.0.1`), mas a rede de casa não
   ([INSTALACAO_E_EXECUCAO.md](INSTALACAO_E_EXECUCAO.md), seção 13).
   No mesmo espírito: `bind-address=127.0.0.1` na seção `[mysqld]` do `C:\xampp\mysql\bin\my.ini` (reinicie o
   MySQL) e desative as regras de entrada "Apache HTTP Server" e "mysqld" do Firewall do Windows, que hoje liberam
   esses programas em redes Públicas (diagnóstico de 01/11/2026). Vale também para o notebook que for à escola.
3. **Banco novo:** `parar_jovemconnect.bat` → responder **S** só na pergunta do banco (digitando o nome) →
   `iniciar_jovemconnect.bat` → aceitar criar o banco (`schema.sql`) e carregar a demonstração (seed). O banco
   antigo pode ter dados reais (PI-06); não use o antigo na apresentação. Faça o mesmo nos computadores onde o
   JovemConnect já rodou com o banco antigo, inclusive os da escola.
4. `scripts\seguranca\verificar_protecao.bat` → só APROVADO.
5. Ligar o Serve: `tailscale serve --bg 80`. Num notebook, abrir o endereço mostrado (`https://<nome>.ts.net/`)
   e conferir o site da Aestus, o link para o JovemConnect, o login e a análise com IA.
6. Do PC de casa, rodar o verificador pelo endereço do Tailscale:
   `scripts\seguranca\verificar_protecao.bat -Base https://<nome>.ts.net/JovemConnect`.
7. Conferir que a IA está ligada (`iniciar_ia.bat`) e que a chave do Gemini está no `.env`.

## Durante

- **Contas de demonstração** (senha em [SEED_DEMO.md](SEED_DEMO.md)): use-as para mostrar o jovem e a empresa.
  As análises com IA (Gemini) devem ser feitas pela equipe, com essas contas (dados fictícios): os termos da API
  do Gemini não permitem o uso por menores de 18 anos e, no plano gratuito, o Google pode ler o que é enviado
  (TER-01, [ORIGEM_DOS_ATIVOS.md](ORIGEM_DOS_ATIVOS.md)).
- **Visitantes que quiserem criar conta:** só dados inventados. O cadastro aceita:
  - nome: só o primeiro nome ou um apelido;
  - CPF: quaisquer 11 números, sem repetir entre visitantes (o sistema não confere os dígitos);
  - e-mail inventado com o domínio reservado `.example`, por exemplo `visitante7@estande.example`;
  - verificação: clicar em "Verificar depois e ir para o meu painel".
  Peça para não digitar dados pessoais no assistente nem enviar currículo de verdade.
- **"Muitas tentativas"**: todos os acessos do estande contam como o mesmo endereço; 30 senhas erradas em 15
  minutos (somando todos) travam o login de todos, e 5 erros travam uma conta. Dê dois cliques em
  `liberar_logins.bat` (na pasta do projeto, no PC que roda o site) para liberar na hora. Teste o atalho na
  véspera: se o Apache rodar como serviço do Windows, ele usa outra pasta temporária e o atalho não alcança os
  contadores; nesse caso, ponha `JC_TENTATIVAS_DIR=C:\xampp\tmp\jovemconnect_tentativas` no `.env` e
  reinicie o Apache.

## Depois

No PC que rodou o site (e em cada computador que rodou localmente):

1. Desligar o Tailscale Serve: `tailscale serve reset`.
2. `parar_jovemconnect.bat` → apagar o banco (com o nome digitado). Nos PCs da escola, se quiser, também a
   pasta do projeto.
3. Apagar os currículos enviados: `del uploads\curriculos\*.pdf` (o `.htaccess` fica).
4. Apagar os logs da apresentação: `del /s /q logs\*.log*` (apaga os `.log` de todas as subpastas; o
   `.htaccess`, o `LEIAME.txt` e o estado da IA ficam).
5. Apagar as sessões do PHP: `del C:\xampp\tmp\sess_*`.
6. `liberar_logins.bat` (zera os contadores de tentativas).
7. Quando for usar de novo: `iniciar_jovemconnect.bat` recria o banco com a demonstração.

## Fotos da página inicial

As duas fotos (`pessoa-computador.jpg`, `edificio-empresa.jpg`) foram copiadas do Google Imagens, sem
licença; o responsável decidiu mantê-las por enquanto. Numa apresentação restrita a alunos o risco prático é
baixo, mas o uso continua sem autorização: antes de qualquer uso público, troque-as
([ORIGEM_DOS_ATIVOS.md](ORIGEM_DOS_ATIVOS.md)).
