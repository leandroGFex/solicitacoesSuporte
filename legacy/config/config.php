<?php
// config/config.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configurar Fuso Horário Local
date_default_timezone_set('America/Sao_Paulo');

define('APP_NAME', 'GRUPO FLEX');
define('APP_SUBTITLE', 'Kanban Operacional');
define('APP_VERSION', '1.0.0');

// Token de segurança para integrações (ex: Google Apps Script)
define('WEBHOOK_API_KEY', 'FlexWebhook2026!');

// Configuração do Middleware (Node.js no Render)
define('INTERNAL_TOKEN', 'FlexInterno2026!');
define('MIDDLEWARE_URL', 'https://flex-middleware.onrender.com/api/send-email');

// IMAP Config (Gmail App Pass)
define('IMAP_HOST', 'imap.gmail.com');
define('IMAP_PORT', 993);
define('IMAP_USER', 'leandro@flexgrupo.com');
define('IMAP_PASS', 'xbye nrny zlke iklz');
define('IMAP_FOLDER', 'INBOX');
define('IMAP_SSL', true);
define('EMAIL_ENABLED', true);

// Filtros de Assunto
define('IMAP_KW_CARTAO', 'Cartão, Cartao');
define('IMAP_KW_TAG', 'Tag');
define('IMAP_KW_POS', 'POS, Máquina, Maquina');
define('IMAP_KW_RASTREIO', 'Rastreador, Rastreio');

// Correios Oficial API
define('CORREIOS_USER', 'flexfrota2020');
define('CORREIOS_API_KEY', 'yNodf3aD5pgJaot9JsIodNaizKHSoMjQP9S0j3DU');
define('CORREIOS_CARD', '0074130064');
define('CORREIOS_CONTRACT', '9912441998');

// Dados do Remetente (FLEX / PRATICA)
define('CORREIOS_SENDER', [
    'nome' => 'PRATICA ADMINISTRADORA BENEFICIOS LTDA',
    'cnpj' => '17159339000138',
    'cep' => '13631045',
    'logradouro' => 'Avenida Newton Prado',
    'numero' => '3697',
    'complemento' => 'Piso Superior',
    'bairro' => 'Centro',
    'cidade' => 'Pirassununga',
    'uf' => 'SP'
]);

// Serviço Padrão (SEDEX contrato: 03220 é o mais comum, mas pode variar)
define('CORREIOS_SERVICE_SEDEX', '03220');

// Banco de Dados
function getDB()
{
    $host = 'sql308.infinityfree.com';
    $db = 'if0_41623476_banco_soli';
    $user = 'if0_41623476';
    $pass = 'GrupoFlex2026';

    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '-03:00'"
    ];

    try {
        return new PDO($dsn, $user, $pass, $options);
    } catch (\PDOException $e) {
        die(json_encode(['success' => false, 'message' => 'Erro na conexão com o banco de dados: ' . $e->getMessage()]));
    }
}
?>