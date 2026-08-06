<?php
    /**
     * Calcula a idade de forma dinâmica
     * @param string $dataNascimento - Valor vindo do SQL (formato YYYY-MM-DD)
     * @return int - Idade em anos
     */
    function calcularIdadeDinamica($dataNascimento) {
        // 1. Criamos um objeto de data com o nascimento
        $nascimento = new DateTime($dataNascimento);
        
        // 2. Criamos um objeto com a data de hoje
        $hoje = new DateTime();
        
        // 3. Calculamos a diferença entre as duas datas
        $intervalo = $hoje->diff($nascimento);
        
        // 4. Retornamos apenas o valor dos anos (y)
        return $intervalo->y;
    }
?>