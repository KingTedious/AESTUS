<?php
/* ============================================================
   includes/card_vaga.php — card de vaga do jovem (padrão único)

   Modelo: o card do painel do jovem (dashboard/jovem.php), agora com a
   estrela de favoritar que existia só em Vagas/listar.php. Usado no painel
   e na lista completa para os cards não divergirem mais.

   - O card inteiro abre o detalhe da vaga (Vagas/listar.php?id=...); o
     título é um link de verdade, para quem navega pelo teclado.
   - A estrela e o botão de IA NÃO disparam a navegação do card.
   - Favoritar reaproveita Vagas/favoritar.php sem mudanças (sessão do
     jovem, CSRF, vaga visível, UNIQUE no banco). O JavaScript é o mesmo
     que estava em listar.php, só com o endereço como parâmetro.
   - O botão de IA chama a função avaliarMatch() de cada página, que
     continua a mesma.

   Campos esperados em $vaga: id, titulo, nome_empresa, area, salario,
   carga_horaria, cidade, estado, eh_favorito (0/1).
   ============================================================ */

/**
 * Imprime um card de vaga.
 * @param array  $vaga        linha da consulta (ver campos acima)
 * @param string $pasta_vagas caminho até a pasta Vagas/ a partir da página ('../Vagas/' ou '')
 * @param string $voltar      chave de retorno do detalhe ('painel', ...) ou '' (volta para a lista)
 */
function jc_card_vaga(array $vaga, string $pasta_vagas, string $voltar = ''): void
{
    $id          = intval($vaga['id']);
    $eh_favorito = !empty($vaga['eh_favorito']);
    $detalhe     = $pasta_vagas . 'listar.php?id=' . $id . ($voltar !== '' ? '&voltar=' . rawurlencode($voltar) : '');
    $detalhe_h   = htmlspecialchars($detalhe);
    $detalhe_js  = htmlspecialchars(json_encode($detalhe, JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG));
    $rotulo_fav  = $eh_favorito ? 'Remover dos favoritos' : 'Salvar nos favoritos';
    ?>
            <div class="job-card"
                 onclick="location.href=<?= $detalhe_js ?>"
                 style="cursor:pointer;"
                 title="Ver detalhes da vaga">

                <!-- Título + badge de área + favorito -->
                <div class="job-card-top">
                    <h3 class="job-card-title">
                        <!-- Link de verdade: pelo teclado também se chega aos detalhes -->
                        <a href="<?= $detalhe_h ?>"
                           onclick="event.stopPropagation()"
                           style="color:inherit;text-decoration:none;">
                            <?= htmlspecialchars($vaga['titulo']) ?>
                        </a>
                    </h3>
                    <div class="d-flex align-center gap-8">
                        <?php if (!empty($vaga['area'])): ?>
                        <span class="badge badge-area"><?= htmlspecialchars($vaga['area']) ?></span>
                        <?php endif; ?>
                        <!-- Favoritar (AJAX POST em Vagas/favoritar.php) — não abre o detalhe -->
                        <button
                            type="button"
                            class="btn-fav"
                            id="fav-<?= $id ?>"
                            onclick="event.stopPropagation(); toggleFavorito(<?= $id ?>, this)"
                            title="<?= $rotulo_fav ?>"
                            aria-label="<?= $rotulo_fav ?>"
                            aria-pressed="<?= $eh_favorito ? 'true' : 'false' ?>"
                            style="
                                background:none;border:none;cursor:pointer;
                                font-size:18px;line-height:1;
                                color:<?= $eh_favorito ? 'var(--jc-favorito, #facc15)' : 'var(--text-muted)' ?>;
                                transition:color var(--dur),transform var(--dur);
                            ">
                            <span class="i <?= $eh_favorito ? 'i-star-cheia' : 'i-star' ?>" aria-hidden="true"></span>
                        </button>
                    </div>
                </div>

                <!-- Empresa -->
                <div class="job-card-company">
                    <span class="i i-building" aria-hidden="true"></span>
                    <?= htmlspecialchars($vaga['nome_empresa']) ?>
                </div>

                <!-- Salário + carga horária -->
                <div class="job-card-meta">
                    <?php if (!empty($vaga['salario'])): ?>
                    <div class="job-card-meta-item">
                        <span class="i i-banknote" aria-hidden="true"></span>
                        <span>R$ <?= htmlspecialchars($vaga['salario']) ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($vaga['carga_horaria'])): ?>
                    <div class="job-card-meta-item">
                        <span class="i i-clock" aria-hidden="true"></span>
                        <span><?= htmlspecialchars($vaga['carga_horaria']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Localização -->
                <?php if (!empty($vaga['cidade'])): ?>
                <div class="job-card-location">
                    <span class="i i-map-pin" aria-hidden="true"></span>
                    <span>
                        <?= htmlspecialchars($vaga['cidade']) ?>
                        <?= !empty($vaga['estado']) ? ', ' . htmlspecialchars($vaga['estado']) : '' ?>
                    </span>
                </div>
                <?php endif; ?>

                <!-- Botão IA (rodapé do card) — não abre o detalhe -->
                <div class="job-card-footer" onclick="event.stopPropagation()">
                    <button
                        type="button"
                        class="btn btn-ia"
                        onclick="avaliarMatch(<?= $id ?>, this)"
                        data-vaga="<?= $id ?>">
                        <span class="i i-sparkles" aria-hidden="true"></span> Avaliar Match com IA
                    </button>
                </div>

            </div><!-- /.job-card -->
    <?php
}

/**
 * Imprime o JavaScript da estrela (o mesmo de Vagas/listar.php, com o endereço como parâmetro).
 * @param string $url_favoritar endereço de Vagas/favoritar.php a partir da página
 */
function jc_card_vaga_script(string $url_favoritar): void
{
    ?>
<script>
/* ── Toggle favorito (AJAX POST) — includes/card_vaga.php ──── */
async function toggleFavorito(vagaId, btn) {
    if (btn.disabled) return;                  // Ignora cliques repetidos durante a requisição
    btn.disabled = true;
    btn.setAttribute('aria-busy', 'true');
    btn.style.transform = 'scale(.8)';
    const fd = new FormData();
    fd.append('vaga_id', vagaId);
    try {
        const res = await fetch(<?= json_encode($url_favoritar, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd,
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || typeof data.favorito !== 'boolean')   // resposta inesperada também é erro
            throw Object.assign(new Error(data.error || ''), { amigavel: !!data.error });
        const rotulo = data.favorito ? 'Remover dos favoritos' : 'Salvar nos favoritos';
        btn.replaceChildren(jcIcone(data.favorito ? 'star-cheia' : 'star'));   // Ícone
        btn.style.color = data.favorito ? 'var(--jc-favorito, #facc15)' : 'var(--text-muted)';
        btn.title       = rotulo;
        btn.setAttribute('aria-label', rotulo);  // O leitor de tela acompanha o estado
        btn.setAttribute('aria-pressed', data.favorito ? 'true' : 'false');
    } catch (err) {
        console.error('Erro ao favoritar:', err);
        window.jcAviso?.(err?.amigavel ? err.message : 'Não foi possível atualizar os favoritos. Tente de novo.');
    } finally {
        btn.style.transform = '';
        btn.disabled = false;
        btn.removeAttribute('aria-busy');
    }
}
</script>
    <?php
}
