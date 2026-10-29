# Avisos de terceiros (Third-party notices)

O JovemConnect inclui ou usa os componentes de terceiros abaixo. A origem, a data de verificação e as
pendências de cada item estão em [`docs/ORIGEM_DOS_ATIVOS.md`](docs/ORIGEM_DOS_ATIVOS.md). Este arquivo não
define a licença do próprio JovemConnect, que é **todos os direitos reservados** ([`LICENSE`](LICENSE)).

## Incluídos no repositório

### Lucide — ícones (desenhos em `assets/css/style.css` e `assets/css/admin.css`)

https://lucide.dev

```
ISC License

Copyright (c) 2026 Lucide Icons and Contributors

Permission to use, copy, modify, and/or distribute this software for any
purpose with or without fee is hereby granted, provided that the above
copyright notice and this permission notice appear in all copies.

THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
```

### Feather — ícones derivados (alert-triangle, arrow-left, arrow-right, calendar, check, chevron-down, clock, download, external-link, info, key, loader, lock, log-out, plus, search, smartphone, trash, type, x-circle, x)

```
The MIT License (MIT)

Copyright (c) 2013-present Cole Bemis

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

## Usados, mas não incluídos no repositório

| Componente | Licença | Como chega ao usuário |
|---|---|---|
| Fonte Inter (Rasmus Andersson) | SIL Open Font License 1.1 | carregada do Google Fonts pelo navegador |
| Flask, Werkzeug, Jinja2, itsdangerous, click, MarkupSafe | BSD-3-Clause | instalados do PyPI no `venv` local (`IA/requirements-ia.txt`) |
| blinker, flask-cors | MIT | idem |
| colorama (só Windows) | BSD-3-Clause | idem |
| PHPMailer (opcional) | LGPL-2.1-only | `composer install` (o projeto não traz `vendor/`) |
| Twilio PHP SDK (opcional) | MIT | `composer install` |

Se algum desses componentes passar a ser distribuído junto com o projeto (por exemplo, `vendor/`, `venv/`
ou o arquivo da fonte), inclua aqui o aviso de licença completo dele.

## Imagens

As fotos de `assets/img/home/` foram copiadas de resultados do Google Imagens e **não têm licença**; estão
mantidas por enquanto, por decisão do responsável, e devem ser trocadas antes de qualquer uso público. Os
logotipos de `assets/img/marca/` são criação da equipe (ver `docs/ORIGEM_DOS_ATIVOS.md`). Nenhuma licença de
terceiros foi presumida.
