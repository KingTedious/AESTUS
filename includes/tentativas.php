<?php
/* ============================================================
   includes/tentativas.php — limite de tentativas e de envios
   (correção de segurança SEG-02 / SEG-07, sem migração de banco)

   Antes, o login de jovens e empresas, os códigos de 6 dígitos
   (recuperação de senha, verificação de e-mail e de telefone) e os
   envios de e-mail/SMS não tinham limite: senha e código podiam ser
   testados à vontade e um e-mail ou SMS podia ser disparado sem parar.

   Como funciona:
   - Cada contador é um arquivo pequeno com os HORÁRIOS das últimas
     tentativas (nada mais: nem e-mail, nem IP, nem senha). O nome do
     arquivo é um hash da chave (ex.: "jovem|pessoa@exemplo.com").
   - Os arquivos ficam FORA da pasta pública: na pasta temporária que o
     PHP usa (sys_get_temp_dir(); no Windows, em geral a pasta TEMP de
     quem roda o Apache), numa subpasta própria deste projeto. Para
     mudar: JC_TENTATIVAS_DIR no .env.
   - Janela deslizante: só contam as tentativas dentro da janela.
   - Se a pasta não puder ser gravada, o site CONTINUA funcionando (sem
     limite) e o problema vai para o log — o limite nunca derruba o login.
   - Para liberar alguém bloqueado antes do prazo (ex.: na apresentação):
       php scripts/jovemconnect.php limpar-tentativas

   Limites (mesmos números que a remediação planejada no Lote D):
     login         5 falhas por conta / 15 min · 30 falhas por IP / 15 min
     códigos       5 erros invalidam o código · 50 erros por IP / 15 min
     envios        1 a cada 30 s e 5 por hora por conta · 20 por hora por IP
   ============================================================ */

if (!function_exists('jc_tent_registrar')) {

    define('JC_TENT_JANELA', 900);           // 15 minutos
    define('JC_TENT_LOGIN_CONTA', 5);
    define('JC_TENT_LOGIN_IP', 30);
    define('JC_TENT_CODIGO_CONTA', 5);
    define('JC_TENT_CODIGO_IP', 50);
    define('JC_TENT_ENVIO_INTERVALO', 30);   // segundos entre dois envios para a mesma conta
    define('JC_TENT_ENVIO_HORA_CONTA', 5);
    define('JC_TENT_ENVIO_HORA_IP', 20);

    /** Pasta dos contadores (criada se não existir); '' se não der para usar. */
    function jc_tent_dir(): string {
        static $dir = null;
        if ($dir !== null) {
            return $dir;
        }
        $cfg = getenv('JC_TENTATIVAS_DIR');
        $d = (is_string($cfg) && trim($cfg) !== '')
            ? rtrim(trim($cfg), '/\\')
            : rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'jovemconnect_tentativas_'
              . substr(hash('sha256', dirname(__DIR__)), 0, 12);
        if (!is_dir($d)) {
            @mkdir($d, 0700, true);
        }
        $dir = (is_dir($d) && is_writable($d)) ? $d : '';
        if ($dir === '' && function_exists('jc_log')) {
            jc_log('tentativas.sem_pasta', 'aviso', ['motivo' => 'pasta_sem_gravacao']);
        }
        return $dir;
    }

    /** Arquivo do contador: hash da chave (o valor original não fica gravado em lugar nenhum). */
    function jc_tent_arquivo(string $escopo, string $valor): string {
        $dir = jc_tent_dir();
        if ($dir === '') {
            return '';
        }
        $escopo = preg_replace('/[^a-z0-9_]/', '', strtolower($escopo));
        $chave = hash('sha256', $escopo . '|' . mb_strtolower(trim($valor), 'UTF-8') . '|' . dirname(__DIR__));
        return $dir . DIRECTORY_SEPARATOR . $escopo . '_' . substr($chave, 0, 40) . '.json';
    }

    /**
     * Lê os horários do contador (só os de dentro da janela) e, se $novo,
     * acrescenta o horário atual — tudo com trava de arquivo, para duas
     * requisições ao mesmo tempo não se perderem. Devolve a lista resultante.
     */
    function jc_tent_mexer(string $escopo, string $valor, int $janela, bool $novo, bool $zerar = false): array {
        $arq = jc_tent_arquivo($escopo, $valor);
        if ($arq === '') {
            return [];
        }
        if (!$novo && !$zerar && !is_file($arq)) {
            return [];
        }
        $h = @fopen($arq, 'c+');
        if ($h === false) {
            return [];
        }
        $lista = [];
        if (flock($h, LOCK_EX)) {
            $agora = time();
            $dados = json_decode((string) stream_get_contents($h), true);
            if (!$zerar && is_array($dados)) {
                foreach ($dados as $t) {
                    if (is_int($t) && $t > $agora - $janela && $t <= $agora + 60) {
                        $lista[] = $t;
                    }
                }
            }
            if ($novo) {
                $lista[] = $agora;
            }
            $lista = array_slice($lista, -200);   // nunca cresce sem limite
            ftruncate($h, 0);
            rewind($h);
            fwrite($h, json_encode(array_values($lista)));
            fflush($h);
            flock($h, LOCK_UN);
        }
        fclose($h);
        if ($zerar || $lista === []) {
            @unlink($arq);
        }
        return $lista;
    }

    /** Registra uma tentativa (falha ou envio) e devolve quantas há na janela. */
    function jc_tent_registrar(string $escopo, string $valor, int $janela = JC_TENT_JANELA): int {
        if (mt_rand(1, 100) === 1) {
            jc_tent_faxina();
        }
        return count(jc_tent_mexer($escopo, $valor, $janela, true));
    }

    /**
     * Apaga contadores parados há mais de um dia (a maior janela usada é de
     * 1 hora). Roda de vez em quando, no máximo 500 arquivos por vez, para a
     * pasta não acumular arquivos de chaves que nunca mais aparecem.
     */
    function jc_tent_faxina(int $idade = 86400): int {
        $dir = jc_tent_dir();
        if ($dir === '') {
            return 0;
        }
        $limite = time() - $idade;
        $n = 0;
        foreach (array_slice(glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [], 0, 500) as $arq) {
            $m = @filemtime($arq);
            if ($m !== false && $m < $limite && @unlink($arq)) {
                $n++;
            }
        }
        return $n;
    }

    /** Quantas tentativas há na janela (sem registrar). */
    function jc_tent_contar(string $escopo, string $valor, int $janela = JC_TENT_JANELA): int {
        return count(jc_tent_mexer($escopo, $valor, $janela, false));
    }

    /** Apaga o contador (ex.: login certo, código conferido). */
    function jc_tent_zerar(string $escopo, string $valor): void {
        jc_tent_mexer($escopo, $valor, 1, false, true);
    }

    /**
     * Segundos que ainda faltam para liberar (0 = liberado): bloqueia quando
     * já há $limite tentativas na janela; libera quando a mais antiga sai dela.
     */
    function jc_tent_espera(string $escopo, string $valor, int $limite, int $janela = JC_TENT_JANELA): int {
        $lista = jc_tent_mexer($escopo, $valor, $janela, false);
        if (count($lista) < $limite) {
            return 0;
        }
        sort($lista);
        $libera = $lista[count($lista) - $limite] + $janela;
        return max(1, $libera - time());
    }

    /** Segundos até poder enviar de novo para a mesma conta (intervalo mínimo entre envios). */
    function jc_tent_intervalo(string $escopo, string $valor, int $intervalo): int {
        $lista = jc_tent_mexer($escopo, $valor, max($intervalo, 3600), false);
        if (!$lista) {
            return 0;
        }
        $falta = max($lista) + $intervalo - time();
        return $falta > 0 ? $falta : 0;
    }

    /**
     * IP de quem fez a requisição. Só REMOTE_ADDR: cabeçalhos como
     * X-Forwarded-For são escritos pelo próprio cliente e serviriam para
     * fugir do limite.
     */
    function jc_tent_ip(): string {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        return $ip !== '' ? $ip : 'sem-ip';
    }

    /** Pequeno atraso depois de uma falha (cresce com as falhas, no máximo 2 s). */
    function jc_tent_atraso(int $falhas): void {
        if ($falhas > 1 && PHP_SAPI !== 'cli') {
            usleep(min(2000, 250 * ($falhas - 1)) * 1000);
        }
    }

    /** Tempo em texto ("2 minutos", "40 segundos"). */
    function jc_tent_tempo(int $segundos): string {
        if ($segundos < 60) {
            return $segundos . ($segundos === 1 ? ' segundo' : ' segundos');
        }
        $min = (int) ceil($segundos / 60);
        return $min . ($min === 1 ? ' minuto' : ' minutos');
    }

    /** Mensagem padrão de bloqueio (não diz se a conta existe). */
    function jc_tent_msg(int $segundos): string {
        return 'Muitas tentativas seguidas. Por segurança, aguarde ' . jc_tent_tempo($segundos)
             . ' e tente novamente.';
    }

    /** Mensagem padrão de "envio cedo demais" (neutra: não diz se houve envio nem se a conta existe). */
    function jc_tent_msg_envio(int $segundos): string {
        return 'Por segurança, há um intervalo mínimo entre os pedidos de código. Aguarde '
             . jc_tent_tempo($segundos) . ' e tente novamente.';
    }

    /**
     * Confere se um ENVIO (e-mail/SMS) pode sair agora para $conta (texto que
     * identifica a conta) e, se puder, já o registra. Devolve 0 (pode enviar)
     * ou os segundos de espera.
     */
    function jc_tent_envio_permitido(string $conta): int {
        $ip = jc_tent_ip();
        $espera = max(
            jc_tent_intervalo('envio_conta', $conta, JC_TENT_ENVIO_INTERVALO),
            jc_tent_espera('envio_conta', $conta, JC_TENT_ENVIO_HORA_CONTA, 3600),
            jc_tent_espera('envio_ip', $ip, JC_TENT_ENVIO_HORA_IP, 3600)
        );
        if ($espera > 0) {
            return $espera;
        }
        jc_tent_registrar('envio_conta', $conta, 3600);
        jc_tent_registrar('envio_ip', $ip, 3600);
        return 0;
    }

    /** Apaga todos os contadores deste projeto (comando "limpar-tentativas"). Devolve quantos. */
    function jc_tent_limpar_tudo(): int {
        $dir = jc_tent_dir();
        if ($dir === '') {
            return 0;
        }
        $n = 0;
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $arq) {
            if (@unlink($arq)) {
                $n++;
            }
        }
        return $n;
    }
}
