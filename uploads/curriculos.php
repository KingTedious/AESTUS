<?php
/**
 * VALIDAÇÃO DE TIPO DE FICHEIRO (CURRÍCULO)
 * Este bloco deve ser executado apenas se um ficheiro foi enviado.
 */

// 1. Verifica se a variável $_FILES existe e se não há erros no envio
if(isset($_FILES['curriculo']) && $_FILES['curriculo']['error'] == 0) {
    
    // 2. Captura o tipo do ficheiro enviado
    $tipo = $_FILES['curriculo']['type'];

    // 3. Validação: Apenas aceita PDF
    if($tipo != "application/pdf"){
        echo "<p style='color: red;'>Erro: Apenas ficheiros no formato PDF são permitidos.</p>";
        // Em vez de exit, costumamos usar uma variável para impedir o upload
        $upload_permitido = false;
    } else {
        $upload_permitido = true;
    }
    
} else {
    // Caso nenhum ficheiro tenha sido selecionado ou ocorra erro de tamanho
    $upload_permitido = false;
}
?>