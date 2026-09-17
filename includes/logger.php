<?php
// Garante que a sessão está ativa para conseguirmos identificar quem gerou o log
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Função global para registar eventos técnicos no sistema.
 * 
 * @param string $mensagem Texto descritivo sobre o que aconteceu.
 * @param string $nivel Categoria do evento (INFO, WARNING, ERROR).
 */
function registrar_log($mensagem, $nivel = 'INFO') {
    // 1. Define o caminho da pasta e do arquivo de log de forma absoluta
    $diretorio_logs = __DIR__ . '/../logs';
    $arquivo_log = $diretorio_logs . '/sistema.log';

    // 2. Cria a pasta "logs" automaticamente se ela ainda não existir no servidor
    if (!is_dir($diretorio_logs)) {
        mkdir($diretorio_logs, 0755, true);
    }

    // 3. Captura os metadados do utilizador atual para auditoria.
    //    Chaves reais da sessão nesta versão: jovem_id/jovem_nome (jovem) e
    //    empresa_id/empresa_nome (empresa) — não existe 'usuario_id'/'usuario_tipo'
    //    em nenhum outro ponto do sistema. Corrigido na Etapa 4 (V3.10).
    $data_hora    = date('Y-m-d H:i:s');
    $ip_usuario   = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    if (isset($_SESSION['jovem_id'])) {
        $usuario_id   = $_SESSION['jovem_id'];
        $usuario_tipo = 'JOVEM';
    } elseif (isset($_SESSION['empresa_id'])) {
        $usuario_id   = $_SESSION['empresa_id'];
        $usuario_tipo = 'EMPRESA';
    } else {
        $usuario_id   = 'ANONIMO';
        $usuario_tipo = 'VISITANTE';
    }

    // 4. Formata a linha de texto de forma padronizada e limpa
    // PHP_EOL adiciona uma quebra de linha correta no fim (Enter)
    $linha_log = sprintf(
        "[%s] [%s] [IP: %s] [USER: %s (%s)] - %s" . PHP_EOL,
        $data_hora,
        strtoupper($nivel), // Garante que o nível fica em letras maiúsculas
        $ip_usuario,
        $usuario_id,
        $usuario_tipo,
        $mensagem
    );

    // 5. Grava o texto no arquivo. 
    // FILE_APPEND faz com que o texto seja adicionado no final do arquivo, sem apagar o que já lá estava.
    file_put_contents($arquivo_log, $linha_log, FILE_APPEND);
}