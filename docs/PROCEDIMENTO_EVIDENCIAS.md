# Procedimento de evidências: assinatura, hashes e backups

> **Classificação: INTERNO.** Versão v4.60 (29/10/2026). PI-04 e PI-17 do `RELATORIO_AUDITORIA_PI.md`.
>
> Roteiro para registrar, **daqui para frente**, provas de conteúdo, de data e de quem aprovou cada versão.
> Ele **não** corrige o passado: as datas antigas do Git continuam como estão e nada deve ser reescrito.
> Quem cria e guarda a chave de assinatura é o responsável: nenhuma chave foi gerada, exportada ou
> configurada por esta correção, e nenhum commit, tag ou push foi feito.

## O que cada evidência prova

| Evidência | Prova | Não prova |
|---|---|---|
| Autor e data no commit | nada por si só: são **declarados** por quem faz o commit | — |
| Assinatura (SSH ou GPG) no commit ou na tag | que **quem tinha a chave** aprovou exatamente aquele conteúdo | quem escreveu o código; quem é o titular |
| Manifesto SHA-256 | o conteúdo exato de cada arquivo de uma versão | a data, se ficar só no seu computador |
| Hash guardado por terceiro (e-mail recebido, armazenamento de outra pessoa ou instituição, carimbo de tempo) | que aquele conteúdo existia **até** aquela data | autoria |
| `git bundle` | permite reconstruir todo o repositório, com histórico e referências | nada sozinho; serve de cópia de segurança |

## 1. Configurar a assinatura (uma vez por computador)

Recomendado: **assinatura com chave SSH** (Git 2.34 ou mais novo; o Git for Windows atual atende).

**Jeito mais fácil:** dê dois cliques em `scripts\seguranca\configurar_assinatura.bat`. Ele faz os passos
abaixo um a um, pedindo confirmação: confere o Git, cria a chave (você digita a frase-senha; nunca sobrescreve
uma chave existente), configura o Git, cria o `allowed_signers`, **testa** assinar e verificar um arquivo
temporário (fora do repositório) e copia a chave pública para você colar no GitHub. Não faz commit nem tag.

Passo a passo manual, no **Prompt de Comando** do Windows (cmd), na sua conta:

```bat
git --version
if not exist "%USERPROFILE%\.ssh" mkdir "%USERPROFILE%\.ssh"
ssh-keygen -t ed25519 -C "assinatura JovemConnect" -f "%USERPROFILE%\.ssh\jc_assinatura"
```

- O `ssh-keygen` pede uma **frase-senha**: use uma forte e guarde-a. O arquivo `jc_assinatura` é a chave
  **privada**: nunca a envie a ninguém, nunca a coloque no projeto, no Git, no Project ou em mensagens.
- O arquivo `jc_assinatura.pub` é a chave **pública**: pode ser compartilhada.

Configurar o Git para assinar (vale para todos os repositórios desta conta):

```bat
git config --global gpg.format ssh
git config --global user.signingkey "%USERPROFILE%\.ssh\jc_assinatura"
git config --global commit.gpgsign true
git config --global tag.gpgsign true
```

Permitir que o próprio computador **verifique** as assinaturas (arquivo de assinantes autorizados):

```bat
git config --global gpg.ssh.allowedSignersFile "%USERPROFILE%\.ssh\allowed_signers"
```

Crie o arquivo `%USERPROFILE%\.ssh\allowed_signers` com **uma linha**: o seu e-mail do Git, um espaço e o
conteúdo do `jc_assinatura.pub`. (O e-mail é o de `git config user.email`.)

No GitHub: *Settings → SSH and GPG keys → New SSH key*, tipo **Signing Key**, cole o conteúdo do
`jc_assinatura.pub`. Assim o GitHub mostra "Verified" nos commits e tags assinados.

Alternativa com GPG (Gpg4win no Windows): `gpg --full-generate-key`, depois
`git config --global gpg.format openpgp` e `git config --global user.signingkey <ID-da-chave>`; a chave
pública vai para o GitHub como **GPG key**.

**Se a chave for perdida:** gere outra e cadastre-a; as assinaturas antigas continuam verificáveis pela chave
pública antiga (guarde uma cópia do `.pub` junto das evidências). **Se a chave vazar:** retire-a do GitHub,
gere outra e registre a data do ocorrido.

## 2. A cada versão

1. **Commit assinado.** Com `commit.gpgsign true`, todo `git commit` sai assinado (o Git pede a frase-senha).
   Conferir: `git log --show-signature -1` ou `git verify-commit HEAD`.
2. **Tag assinada** (nunca recriar uma tag que já existe):
   ```bat
   git tag -s v4.60 -m "JovemConnect v4.60 - correcao de seguranca e propriedade intelectual"
   git tag -v v4.60
   git push origin main
   git push origin v4.60
   ```
   O script `scripts\seguranca\git_correcao_seguranca.bat` faz esses passos com confirmação a cada um e usa
   `-s` só se a assinatura estiver configurada (senão, cria uma tag anotada comum e avisa).
3. **Pacote de evidências** (fora do `htdocs`):
   ```bat
   scripts\seguranca\gerar_evidencias.bat -Versao v4.60
   ```
   Gera em `Documentos\JovemConnect_evidencias\v4.60_<data>\`:
   - `codigo_v4.60.zip` — cópia exata dos arquivos da versão (`git archive`);
   - `MANIFESTO_v4.60.csv` — caminho, bytes, SHA-256 e id do Git de cada arquivo;
   - `jovemconnect_<data>.bundle` — backup completo (todas as referências, inclusive `refs/original`,
     `refs/cline` e `backup-*`), conferido com `git bundle verify`;
   - `SHA256SUMS.txt` — hash dos três arquivos acima;
   - `EVIDENCIA.txt` — commit, tag (e se está assinada e verificada), datas e como conferir.
   O script recusa gravar dentro do `htdocs` (o Apache serviria o backup).
4. **Data externa:** envie o `SHA256SUMS.txt` (só ele: hashes não revelam o conteúdo) por e-mail para você
   e para o orientador, ou registre-o num serviço de carimbo de tempo (RFC 3161 ou OpenTimestamps), se a
   orientação recomendar.
5. **Duas cópias independentes** da pasta inteira, sob controles diferentes: por exemplo, um disco externo
   seu e um armazenamento de outra pessoa ou da instituição. Mantenha também a cópia do computador.

**Atenção — o pacote é confidencial.** O `bundle` e o `zip` contêm todo o código e a documentação interna, e
o `bundle` contém o histórico inteiro, inclusive a chave antiga (revogada, segundo o responsável) e o
registro com aparência de dado pessoal (PI-06). Antes de entregar uma cópia a outra pessoa ou a um serviço de
nuvem, **cifre a pasta** (por exemplo, arquivo 7-Zip com criptografia AES-256 e senha forte, ou um pendrive
com BitLocker To Go) e não envie a senha pelo mesmo canal.

**Decisão do responsável (29/10/2026):** por enquanto, o pacote fica só no PC pessoal do desenvolvedor
principal. Riscos: se o PC parar, perdem-se o pacote **e** as pastas das versões antigas (única cópia), e sem
um terceiro guardando o hash não há data externa. Mínimo recomendado, sem custo e sem revelar o conteúdo:
mandar o `SHA256SUMS.txt` (e o do inventário abaixo) para o seu próprio e-mail; quando possível, uma cópia
cifrada num pendrive.

### Versões anteriores ao Git (pastas locais)

As versões de antes de 06/08/2026 estão em pastas locais (Documentos do PC do desenvolvedor principal), com
as datas originais. Para não perder essa prova:

- **não abra, edite, renomeie nem mova** os arquivos dessas pastas (mover para outro disco ou copiar muda a
  data de criação);
- rode `scripts\seguranca\inventariar_versoes_antigas.bat` e escolha a pasta: ele **só lê** e grava, fora da
  pasta e fora do `htdocs` (em `Documentos\JovemConnect_evidencias\versoes_antigas_<data>\`), um
  `INVENTARIO.csv` (caminho, bytes, data de criação, data de modificação, SHA-256 de cada arquivo) e um
  `SHA256SUMS.txt`;
- mande o `SHA256SUMS.txt` para o seu e-mail: fixa, por um terceiro, que aquele conteúdo existia naquela data.

As datas do sistema de arquivos podem ser alteradas por quem tem o PC; o inventário não as prova sozinho,
mas registra o estado atual e permite mostrar depois que nada mudou.

## 3. Como alguém confere depois

Com a pasta de evidências e a chave **pública** do autor:

```bat
certutil -hashfile codigo_v4.60.zip SHA256
certutil -hashfile jovemconnect_<data>.bundle SHA256
```

Os valores devem ser iguais aos do `SHA256SUMS.txt` (e aos do e-mail de data conhecida). No PowerShell:
`Get-FileHash <arquivo> -Algorithm SHA256`.

```bat
git bundle verify jovemconnect_<data>.bundle
git clone jovemconnect_<data>.bundle conferencia
cd conferencia
git rev-parse "v4.60^{commit}"
git tag -v v4.60
```

- `git rev-parse` deve mostrar o commit registrado no `EVIDENCIA.txt`.
- `git tag -v` precisa da chave pública do autor no `allowed_signers` (SSH) ou importada (GPG).
- Para conferir um arquivo isolado: o SHA-256 dele deve ser igual à linha correspondente do manifesto.

## 4. O que nunca fazer (preservação das evidências antigas)

- Não reescrever o histórico (`filter-branch`, `filter-repo`, `rebase` de commits publicados, `commit --amend`
  de commits enviados) sem decisão registrada **e** um pacote de evidências completo guardado antes.
- Não apagar `refs/original/`, `refs/cline/`, branches `backup-*`, tags ou o *reflog*.
- Não usar `git gc --prune=now`, `git reflog expire` nem `git push --force`.
- Não recriar tags existentes.

## 5. Gancho `pre-commit` (opcional, PI-17)

`scripts\seguranca\pre-commit` recusa commits que levariam ao Git `.env`, `*.env` (menos o `.env.example`),
`repomix-output.*`, chaves (`.pem`, `.key`, `.p12`, `.pfx`), `*.bundle` ou linhas novas com formato de chave
conhecida (Google/Gemini, GitHub, Twilio, chave privada). Mostra só o **nome** do arquivo, nunca o valor.
Instalação: `copy scripts\seguranca\pre-commit .git\hooks\pre-commit` (o `git_correcao_seguranca.bat`
oferece fazer isso e nunca sobrescreve um gancho existente). É uma conferência local simples: não substitui
olhar o que vai em cada commit.

## Registro de execuções

| Data | Versão | Commit | Tag assinada? | Pacote gerado | Cópias (onde) | Data externa |
|---|---|---|---|---|---|---|
| | | | | | | |
