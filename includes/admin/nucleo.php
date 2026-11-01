<?php
/* ============================================================
   includes/admin/nucleo.php — carrega a Central administrativa
   (Stage 7)

   Toda página de admin/ começa com:
     require_once dirname(__DIR__) . '/includes/admin/nucleo.php';
     $admin = jc_admin_exigir('permissao.aqui');

   Módulos:
     sessao.php      sessão JCADMIN, CSRF, cabeçalhos de segurança
     limite.php      limite de tentativas de login
     totp.php        segundo fator (código do autenticador)
     auditoria.php   histórico encadeado por hash + transações
     autorizacao.php papéis, permissões, jc_admin_exigir, reautenticação
     servicos.php    consultas e operações (contas, vagas, fila, histórico)
     layout.php      estrutura visual e utilitários de tela
   Regras compartilhadas com o site público:
     ../esquema.php, ../conta_status.php, ../validacao.php, ../moderacao.php
   ============================================================ */

require_once dirname(__DIR__) . '/config.php';        // $conn, logger, .env
require_once dirname(__DIR__) . '/esquema.php';
require_once dirname(__DIR__) . '/conta_status.php';
require_once dirname(__DIR__) . '/moderacao.php';     // inclui validacao.php
require_once __DIR__ . '/sessao.php';
require_once __DIR__ . '/limite.php';
require_once __DIR__ . '/totp.php';
require_once __DIR__ . '/auditoria.php';
require_once __DIR__ . '/autorizacao.php';
require_once __DIR__ . '/servicos.php';
require_once __DIR__ . '/layout.php';

// Erro inesperado numa página da Central: a transação já foi desfeita por
// jc_admin_transacao() (nada gravado, nem a ação nem o histórico). O
// administrador vê uma mensagem clara com o código da requisição, que
// localiza o erro em logs/site/app.log — sem detalhes técnicos na tela.
if (PHP_SAPI !== 'cli') {
    set_exception_handler(function (Throwable $e): void {
        if (function_exists('jc_log_excecao')) {
            jc_log_excecao($e, 'admin.erro_inesperado');
        }
        $json = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
        $texto = jc_admin_gravou()
            ? 'Ocorreu um erro inesperado depois de uma gravação. Confira o Histórico antes de repetir a operação.'
            : 'Ocorreu um erro inesperado e a operação NÃO foi concluída: nada foi alterado. Tente novamente em instantes.';
        jc_admin_pagina_erro(500, 'Operação não concluída',
            $texto . ' Se persistir, informe o código ' . jc_req_id() . ' (registrado no log).', $json);
    });
}
