<?php
/* ============================================================
   includes/locais_campos.php  (Stage 5.1)

   Campos de endereço de um local de trabalho (mesmo formato do
   endereço da empresa): CEP (busca no ViaCEP) → rua, bairro, cidade,
   UF; número e complemento à mão.

   Variáveis esperadas (definidas antes do include):
     $form            valores atuais (cep, logradouro, numero, complemento,
                      bairro, cidade, estado)
     $campos_prefixo  prefixo dos ids (ex.: 'local')
     $campos_nome     prefixo dos names (padrão: '')
   ============================================================ */
$_p  = $campos_prefixo ?? 'local';
$_n  = $campos_nome    ?? '';
$_v  = fn($k) => htmlspecialchars((string) ($form[$k] ?? ''), ENT_QUOTES);
$_cepFmt = preg_replace('/^(\d{5})(\d{3})$/', '$1-$2', preg_replace('/\D/', '', (string) ($form['cep'] ?? '')));
?>
<div class="locais-campos" data-locais-campos="<?= $_p ?>">
    <div class="form-group">
        <label class="form-label" for="<?= $_p ?>Cep">CEP</label>
        <input type="text" name="<?= $_n ?>cep" id="<?= $_p ?>Cep" class="form-control" data-local-cep
               placeholder="00000-000" maxlength="9" inputmode="numeric" autocomplete="postal-code"
               value="<?= htmlspecialchars($_cepFmt, ENT_QUOTES) ?>" aria-describedby="<?= $_p ?>CepStatus">
        <small id="<?= $_p ?>CepStatus" class="text-muted" style="font-size:12px;" aria-live="polite" data-local-cep-status></small>
    </div>
    <div class="form-group">
        <label class="form-label" for="<?= $_p ?>Logradouro">Rua / Logradouro</label>
        <input type="text" name="<?= $_n ?>logradouro" id="<?= $_p ?>Logradouro" class="form-control" maxlength="150"
               data-local-campo="logradouro" value="<?= $_v('logradouro') ?>">
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label" for="<?= $_p ?>Numero">Número</label>
            <input type="text" name="<?= $_n ?>numero" id="<?= $_p ?>Numero" class="form-control" maxlength="10"
                   placeholder="Ex: 123 ou S/N" data-local-campo="numero" value="<?= $_v('numero') ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="<?= $_p ?>Complemento">Complemento</label>
            <input type="text" name="<?= $_n ?>complemento" id="<?= $_p ?>Complemento" class="form-control" maxlength="100"
                   placeholder="Ex: Sala 4" value="<?= $_v('complemento') ?>">
        </div>
    </div>
    <div class="form-group">
        <label class="form-label" for="<?= $_p ?>Bairro">Bairro</label>
        <input type="text" name="<?= $_n ?>bairro" id="<?= $_p ?>Bairro" class="form-control" maxlength="100"
               data-local-campo="bairro" value="<?= $_v('bairro') ?>">
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label" for="<?= $_p ?>Cidade">Cidade *</label>
            <input type="text" name="<?= $_n ?>cidade" id="<?= $_p ?>Cidade" class="form-control" maxlength="100"
                   data-local-campo="cidade" value="<?= $_v('cidade') ?>" required>
        </div>
        <div class="form-group">
            <label class="form-label" for="<?= $_p ?>Estado">UF *</label>
            <input type="text" name="<?= $_n ?>estado" id="<?= $_p ?>Estado" class="form-control" maxlength="2"
                   placeholder="SP" data-local-campo="estado" value="<?= $_v('estado') ?>" required>
        </div>
    </div>
</div>
<?php if (empty($GLOBALS['_jc_locais_campos_js'])): $GLOBALS['_jc_locais_campos_js'] = true; ?>
<script>
/* Stage 5.1: CEP do local de trabalho → rua, bairro, cidade e UF (ViaCEP).
   Número e complemento são digitados; nada é preenchido sem resposta do ViaCEP. */
(function () {
    const ultimo = new WeakMap();
    async function buscar(campo) {
        const bloco  = campo.closest('[data-locais-campos]');
        const status = bloco?.querySelector('[data-local-cep-status]');
        const raw    = campo.value.replace(/\D/g, '');
        if (raw.length !== 8 || ultimo.get(campo) === raw) return;
        ultimo.set(campo, raw);
        if (status) status.textContent = 'Buscando endereço...';
        try {
            const res = await fetch(`https://viacep.com.br/ws/${raw}/json/`);
            const d   = await res.json();
            if (d.erro) { if (status) status.textContent = 'CEP não encontrado — preencha o endereço manualmente.'; return; }
            const por = { logradouro: d.logradouro, bairro: d.bairro, cidade: d.localidade, estado: d.uf };
            bloco.querySelectorAll('[data-local-campo]').forEach(el => {
                const k = el.getAttribute('data-local-campo');
                if (k in por) el.value = por[k] || '';
            });
            if (status) status.replaceChildren(jcIcone('check-circle'), ' Endereço preenchido. Informe o número e o complemento.');
        } catch {
            if (status) status.textContent = 'Não foi possível buscar o CEP agora — preencha manualmente.';
        }
    }
    document.addEventListener('input', e => {
        const campo = e.target.closest?.('[data-local-cep]');
        if (!campo) return;
        let v = campo.value.replace(/\D/g, '').slice(0, 8);
        if (v.length > 5) v = v.slice(0, 5) + '-' + v.slice(5);
        campo.value = v;
        if (v.length === 9) buscar(campo);
    });
    document.addEventListener('focusout', e => {
        const campo = e.target.closest?.('[data-local-cep]');
        if (campo) buscar(campo);
    });
    // começa sem rebuscar o CEP já salvo (não sobrescreve o que foi digitado à mão)
    document.querySelectorAll('[data-local-cep]').forEach(c => ultimo.set(c, c.value.replace(/\D/g, '')));
})();
</script>
<?php endif; ?>
