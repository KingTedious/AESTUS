<?php
/* ============================================================
   includes/institucional.php

   Modais do rodapé e do cadastro: Termos de Uso, Política de
   Privacidade e Contato.
   - Abertos por qualquer elemento com data-jc-modal-abrir="modalTermos"
     (ou modalPrivacidade / modalContato). Se for um <a href="documentos.php?doc=...">,
     sem JavaScript o link abre a página própria do documento.
   - Esc, ×, clique no fundo, foco preso e foco devolvido: includes/assistente.php.
   - Duas camadas: resumo curto + botão "Saiba mais" com o texto completo.
   - Texto em includes/documentos.php (minutas para revisão jurídica).
   Incluir uma vez por página (incluir de novo não duplica os modais).
   ============================================================ */
if (defined('JC_INSTITUCIONAL_INCLUIDO')) {
    return;
}
define('JC_INSTITUCIONAL_INCLUIDO', true);
require_once __DIR__ . '/documentos.php';

$_jc_docs    = jc_documentos();
$_jc_contato = filter_var(JC_CONTATO_EMAIL, FILTER_VALIDATE_EMAIL) ? JC_CONTATO_EMAIL : '';
// caminho até a raiz do projeto: '' na página inicial, '../' nas páginas de subpastas
$_jc_raiz    = (realpath(dirname($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(dirname(__DIR__))) ? '' : '../';

foreach (['termos' => 'modalTermos', 'privacidade' => 'modalPrivacidade'] as $_jc_chave => $_jc_id):
    $_jc_doc = $_jc_docs[$_jc_chave];
?>
<div class="jc-modal" id="<?= $_jc_id ?>" role="dialog" aria-modal="true" aria-labelledby="<?= $_jc_id ?>Titulo">
    <div class="jc-modal-caixa doc-modal">
        <div class="jc-modal-topo">
            <h2 id="<?= $_jc_id ?>Titulo"><span class="i i-<?= $_jc_doc['icone'] ?>" aria-hidden="true"></span> <?= htmlspecialchars($_jc_doc['titulo']) ?></h2>
            <button type="button" class="jc-modal-fechar" data-jc-modal-fechar aria-label="Fechar <?= htmlspecialchars($_jc_doc['titulo']) ?>"><span class="i i-x" aria-hidden="true"></span></button>
        </div>
        <div class="jc-modal-corpo">
            <?= jc_documento_versao() ?>
            <h3 class="doc-resumo-titulo">Resumo</h3>
            <?= jc_documento_resumo($_jc_doc) ?>
            <button type="button" class="btn btn-ghost btn-sm doc-saiba-mais" aria-expanded="false"
                    aria-controls="<?= $_jc_id ?>Completo" data-jc-expandir="<?= $_jc_id ?>Completo">
                <span class="i i-chevron-down" aria-hidden="true"></span> <span data-jc-expandir-rotulo>Saiba mais: ler o texto completo</span>
            </button>
            <div class="doc-completo" id="<?= $_jc_id ?>Completo" hidden>
                <?= jc_documento_secoes($_jc_doc, 3) ?>
            </div>
        </div>
        <div class="jc-modal-rodape">
            <a class="btn btn-ghost btn-sm" href="<?= $_jc_raiz ?>documentos.php?doc=<?= $_jc_chave ?>" target="_blank" rel="noopener">
                Abrir em página própria <span class="sr-only">(abre em nova aba)</span>
            </a>
            <button type="button" class="btn btn-secondary btn-sm" data-jc-modal-fechar>Fechar</button>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- ============================================================
     MODAL — CONTATO
     ============================================================ -->
<div class="jc-modal" id="modalContato" role="dialog" aria-modal="true" aria-labelledby="modalContatoTitulo">
    <div class="jc-modal-caixa" style="max-width:520px;">
        <div class="jc-modal-topo">
            <h2 id="modalContatoTitulo"><span class="i i-mail" aria-hidden="true"></span> Contato</h2>
            <button type="button" class="jc-modal-fechar" data-jc-modal-fechar aria-label="Fechar Contato"><span class="i i-x" aria-hidden="true"></span></button>
        </div>
        <div class="jc-modal-corpo">
            <?php if ($_jc_contato !== ''): ?>
            <p>Fale com a equipe do JovemConnect pelo e-mail:</p>
            <p class="doc-contato-email">
                <a href="mailto:<?= htmlspecialchars($_jc_contato) ?>"><?= htmlspecialchars($_jc_contato) ?></a>
            </p>
            <p>Use o mesmo endereço para dúvidas sobre seus dados pessoais e privacidade.
               Para dúvidas rápidas sobre como usar a plataforma, experimente o Assistente JovemConnect.</p>
            <?php else: ?>
            <p>O endereço de contato não está configurado. Para dúvidas sobre como usar a plataforma,
               o Assistente JovemConnect (botão no canto inferior direito) pode ajudar.</p>
            <?php endif; ?>
        </div>
        <div class="jc-modal-rodape">
            <?php if ($_jc_contato !== ''): ?>
            <a class="btn btn-primary btn-sm" href="mailto:<?= htmlspecialchars($_jc_contato) ?>"><span class="i i-send" aria-hidden="true"></span> Enviar e-mail</a>
            <?php endif; ?>
            <button type="button" class="btn btn-secondary btn-sm" data-jc-modal-fechar data-jc-abrir-assistente><span class="i i-sparkles" aria-hidden="true"></span> Abrir o assistente</button>
            <button type="button" class="btn btn-secondary btn-sm" data-jc-modal-fechar>Fechar</button>
        </div>
    </div>
</div>

<script>
/* "Saiba mais" dos documentos: mostra/esconde o texto completo, sem sair do modal. */
document.addEventListener('click', e => {
    const btn = e.target.closest?.('[data-jc-expandir]');
    if (!btn) return;
    const alvo = document.getElementById(btn.getAttribute('data-jc-expandir'));
    if (!alvo) return;
    const abrir = alvo.hidden;
    alvo.hidden = !abrir;
    btn.setAttribute('aria-expanded', String(abrir));
    btn.classList.toggle('aberto', abrir);
    const rot = btn.querySelector('[data-jc-expandir-rotulo]');
    if (rot) rot.textContent = abrir ? 'Mostrar só o resumo' : 'Saiba mais: ler o texto completo';
    if (abrir) alvo.querySelector('h3, h2')?.scrollIntoView({ block: 'nearest' });
});
</script>
<?php unset($_jc_docs, $_jc_doc, $_jc_chave, $_jc_id, $_jc_contato, $_jc_raiz); ?>
