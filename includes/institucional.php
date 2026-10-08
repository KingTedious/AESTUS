<?php
/* ============================================================
   includes/institucional.php  (Stage 5)

   Modais do rodapé: Termos de Uso, Política de Privacidade e Contato.
   Abertos por <button data-jc-modal-abrir="modalTermos"> etc.; o
   comportamento (Esc, ×, clique no fundo, foco preso, foco devolvido)
   vem de includes/assistente.php.

   IMPORTANTE — conteúdo oficial:
   - Os textos oficiais de Termos e Privacidade ainda não foram
     fornecidos. Os modais mostram um aviso de CONTEÚDO PENDENTE, sem
     texto jurídico inventado. Para publicar, substitua os blocos
     marcados com "TEXTO OFICIAL".
   - JC_CONTATO_EMAIL fica vazio até o endereço oficial ser confirmado
     (não inventar). Preenchido com um e-mail válido, o modal de Contato
     passa a mostrar o endereço e o botão "Enviar e-mail" (mailto:).
   ============================================================ */
if (!defined('JC_CONTATO_EMAIL')) {
    define('JC_CONTATO_EMAIL', '');   // ex.: 'contato@exemplo.com' — somente o endereço OFICIAL confirmado
}
$_jc_contato = filter_var(JC_CONTATO_EMAIL, FILTER_VALIDATE_EMAIL) ? JC_CONTATO_EMAIL : '';
?>
<!-- ============================================================
     MODAL — TERMOS DE USO
     ============================================================ -->
<div class="jc-modal" id="modalTermos" role="dialog" aria-modal="true" aria-labelledby="modalTermosTitulo">
    <div class="jc-modal-caixa">
        <div class="jc-modal-topo">
            <h2 id="modalTermosTitulo"><span class="i i-file-text" aria-hidden="true"></span> Termos de Uso</h2>
            <button type="button" class="jc-modal-fechar" data-jc-modal-fechar aria-label="Fechar Termos de Uso"><span class="i i-x" aria-hidden="true"></span></button>
        </div>
        <div class="jc-modal-corpo">
            <p><span class="pendente-selo"><span class="i i-alert-triangle" aria-hidden="true"></span> Conteúdo pendente</span></p>
            <p>O texto oficial dos Termos de Uso do JovemConnect ainda não foi publicado.
               Esta janela está reservada para ele.</p>
            <!-- TEXTO OFICIAL: substituir este bloco pelo conteúdo aprovado dos Termos de Uso -->
            <div class="bloco-pendente">Espaço reservado para o texto oficial dos Termos de Uso.</div>
        </div>
        <div class="jc-modal-rodape">
            <button type="button" class="btn btn-secondary btn-sm" data-jc-modal-fechar>Fechar</button>
        </div>
    </div>
</div>

<!-- ============================================================
     MODAL — POLÍTICA DE PRIVACIDADE
     ============================================================ -->
<div class="jc-modal" id="modalPrivacidade" role="dialog" aria-modal="true" aria-labelledby="modalPrivacidadeTitulo">
    <div class="jc-modal-caixa">
        <div class="jc-modal-topo">
            <h2 id="modalPrivacidadeTitulo"><span class="i i-shield" aria-hidden="true"></span> Política de Privacidade</h2>
            <button type="button" class="jc-modal-fechar" data-jc-modal-fechar aria-label="Fechar Política de Privacidade"><span class="i i-x" aria-hidden="true"></span></button>
        </div>
        <div class="jc-modal-corpo">
            <p><span class="pendente-selo"><span class="i i-alert-triangle" aria-hidden="true"></span> Conteúdo pendente</span></p>
            <p>O texto oficial da Política de Privacidade do JovemConnect ainda não foi publicado.
               Esta janela está reservada para ele.</p>
            <!-- TEXTO OFICIAL: substituir este bloco pelo conteúdo aprovado da Política de Privacidade -->
            <div class="bloco-pendente">Espaço reservado para o texto oficial da Política de Privacidade.</div>
        </div>
        <div class="jc-modal-rodape">
            <button type="button" class="btn btn-secondary btn-sm" data-jc-modal-fechar>Fechar</button>
        </div>
    </div>
</div>

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
            <p style="font-size:16px;font-weight:600;margin-top:6px;">
                <a href="mailto:<?= htmlspecialchars($_jc_contato) ?>"><?= htmlspecialchars($_jc_contato) ?></a>
            </p>
            <?php else: ?>
            <p><span class="pendente-selo"><span class="i i-alert-triangle" aria-hidden="true"></span> E-mail oficial pendente</span></p>
            <p>O endereço oficial de contato ainda não foi confirmado e, por isso, não aparece aqui.</p>
            <p>Para dúvidas sobre como usar a plataforma, o Assistente JovemConnect
               (botão no canto inferior direito) responde às perguntas mais comuns.</p>
            <?php endif; ?>
        </div>
        <div class="jc-modal-rodape">
            <?php if ($_jc_contato !== ''): ?>
            <a class="btn btn-primary btn-sm" href="mailto:<?= htmlspecialchars($_jc_contato) ?>"><span class="i i-send" aria-hidden="true"></span> Enviar e-mail</a>
            <?php else: ?>
            <button type="button" class="btn btn-primary btn-sm" data-jc-modal-fechar data-jc-abrir-assistente><span class="i i-sparkles" aria-hidden="true"></span> Abrir o assistente</button>
            <?php endif; ?>
            <button type="button" class="btn btn-secondary btn-sm" data-jc-modal-fechar>Fechar</button>
        </div>
    </div>
</div>
<?php unset($_jc_contato); ?>
