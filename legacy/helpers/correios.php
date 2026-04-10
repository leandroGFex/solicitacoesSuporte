<?php
// =============================================================
// HELPER - API OFICIAL CORREIOS (v1)
// =============================================================

require_once __DIR__ . '/../config/config.php';

class CorreiosAPI
{
    private static $baseUrl = 'https://api.correios.com.br';

    /**
     * Obtém o Token de Acesso (Bearer)
     */
    public static function getToken()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Tentar recuperar do cache da sessão
        if (!empty($_SESSION['correios_token']) && !empty($_SESSION['correios_token_expiry'])) {
            if (time() < $_SESSION['correios_token_expiry'] - 300) { // 5 min de margem
                return $_SESSION['correios_token'];
            }
        }

        $user = CORREIOS_USER;
        $key = CORREIOS_API_KEY;
        $card = CORREIOS_CARD;

        $auth = base64_encode($user . ':' . $key);

        $url = self::$baseUrl . '/token/v1/autentica/cartaopostagem';
        $body = json_encode([
            'numero' => $card
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . $auth,
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);

        if ($httpCode === 201 && !empty($data['token'])) {
            $_SESSION['correios_token'] = $data['token'];
            // A API retorna expiraEm no formato ISO
            $expiry = isset($data['expiraEm']) ? strtotime($data['expiraEm']) : (time() + 3600);
            $_SESSION['correios_token_expiry'] = $expiry;
            return $data['token'];
        }

        return null;
    }

    /**
     * Rastreia um objeto
     */
    public static function track($code)
    {
        $token = self::getToken();
        if (!$token) return ['error' => 'Falha na autenticação com Correios'];

        $url = self::$baseUrl . "/srorastro/v1/objetos/" . strtoupper(trim($code)) . "?resultado=T";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);

        if ($httpCode === 200 && isset($data['objetos'])) {
            return $data;
        }

        $errorMsg = $data['msgs'][0] ?? $data['msg'] ?? $data['message'] ?? 'Desconhecido';
        return [
            'error' => "Erro na consulta (HTTP $httpCode): $errorMsg"
        ];
    }

    /**
     * Cria uma Pré-postagem simples (SEDEX padrão)
     */
    public static function createPrePost($cardData)
    {
        $token = self::getToken();
        if (!$token) return "Erro: Falha na obtenção do token Correios";

        $sender = CORREIOS_SENDER;
        
        // Tratar endereço do destinatário
        $destAddr = self::parseAddress($cardData['address']);
        
        // Definir peso por categoria
        $cat = $cardData['category'] ?? 'cartao';
        $peso = 500; // default
        $alt = 10; $larg = 15; $comp = 15;

        if ($cat === 'cartao' || $cat === 'tag') {
            $peso = 20;
            $alt = 1; // 1 cm
        } elseif ($cat === 'rastreador') {
            $peso = 100;
            $alt = 5;
        } elseif ($cat === 'pos') {
            $peso = 500;
            $alt = 8;
        }

        // Contar itens do extra_data
        $extraData = json_decode($cardData['extra_data'] ?? '[]', true);
        $qtd = is_array($extraData) ? count($extraData) : 1;
        if ($qtd === 0) $qtd = 1;

        // Mapear Descrição e Valor
        $desc = "Equipamento";
        $valorUnit = 50.00;
        
        switch ($cat) {
            case 'cartao':
                $desc = "Cartão de Benefícios";
                $valorUnit = 8.00;
                break;
            case 'tag':
                $desc = "Tag de Pedágio";
                $valorUnit = 10.00;
                break;
            case 'pos':
                $desc = "Máquina POS";
                $valorUnit = 700.00;
                break;
            case 'rastreador':
                $desc = "Rastreador Veicular";
                $valorUnit = 150.00;
                break;
        }

        $body = [
            'codigoServico' => CORREIOS_SERVICE_SEDEX,
            'remetente' => [
                'nome' => $sender['nome'],
                'cpfCnpj' => preg_replace('/\D/', '', $sender['cnpj']),
                'endereco' => [
                    'logradouro' => $sender['logradouro'],
                    'numero' => $sender['numero'],
                    'complemento' => $sender['complemento'],
                    'bairro' => $sender['bairro'],
                    'cep' => preg_replace('/\D/', '', $sender['cep']),
                    'cidade' => $sender['cidade'],
                    'uf' => $sender['uf']
                ]
            ],
            'destinatario' => [
                'nome' => $cardData['company_name'] ?: ($cardData['client_name'] ?: 'CLIENTE FLEX'),
                'cpfCnpj' => preg_replace('/\D/', '', $cardData['cnpj'] ?? ''),
                'endereco' => [
                    'logradouro' => $destAddr['logradouro'],
                    'numero' => $destAddr['numero'],
                    'complemento' => $destAddr['complemento'],
                    'bairro' => $destAddr['bairro'],
                    'cep' => preg_replace('/\D/', '', $destAddr['cep']),
                    'cidade' => $destAddr['cidade'],
                    'uf' => $destAddr['uf']
                ]
            ],
            'peso' => (int) $peso,
            'formato' => "1", // 1 = Formato Caixa/Pacote
            'comprimento' => (int) $comp,
            'largura' => (int) $larg,
            'altura' => (int) $alt,
            'diametro' => 0,
            'proibidos' => false,
            'declaracaoConteudo' => [
                'itensDeclaracaoConteudo' => [
                    [
                        'conteudo' => $desc,
                        'quantidade' => (int) $qtd,
                        'valor' => (float) $valorUnit
                    ]
                ]
            ],
            'contrato' => [
                'numero' => (int) CORREIOS_CONTRACT,
                'cartao' => (int) CORREIOS_CARD
            ]
        ];

        // URL v1 sugerida nos manuais e prints do Postman
        $url = self::$baseUrl . '/prepostagem/v1/prepostagens';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);
        if (($httpCode === 201 || $httpCode === 200) && !empty($data['id'])) {
            return $data['id'];
        }

        // Se falhou, retorna a mensagem detalhada da API se houver
        $errorMsg = '';
        if (isset($data['msgs']) && is_array($data['msgs'])) {
            $errorMsg = implode(' | ', array_map(function($m) {
                return is_array($m) ? ($m['texto'] ?? json_encode($m)) : $m;
            }, $data['msgs']));
        } elseif (!empty($data['msg'])) {
            $errorMsg = is_array($data['msg']) ? json_encode($data['msg']) : $data['msg'];
        } elseif (!empty($data['message'])) {
            $errorMsg = $data['message'];
        } else {
            $errorMsg = $response ?: 'Sem resposta do servidor';
        }
        
        return "Erro Correios ($httpCode): " . $errorMsg;
    }

    /**
     * Obtém PDF da Etiqueta
     */
    public static function getPdfEtiqueta($prepostId)
    {
        return self::downloadPdf("/prepostagem/v2/prepostagens/{$prepostId}/etiqueta");
    }

    /**
     * Obtém PDF da Declaração de Conteúdo
     */
    public static function getPdfDeclaracao($prepostId)
    {
        return self::downloadPdf("/prepostagem/v2/prepostagens/{$prepostId}/declaracaoConteudo");
    }

    private static function downloadPdf($endpoint)
    {
        $token = self::getToken();
        if (!$token) return null;

        $url = self::$baseUrl . $endpoint;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Accept: application/pdf'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            return $response; // Binário do PDF
        }

        return null;
    }

    /**
     * Helper para tentar extrair partes do endereço se for string plana ou JSON
     */
    private static function parseAddress($raw)
    {
        $data = [
            'logradouro' => '',
            'numero' => 's/n',
            'complemento' => '',
            'bairro' => '',
            'cep' => '',
            'cidade' => '',
            'uf' => ''
        ];

        // Se for JSON (novo formato do sistema)
        $json = json_decode($raw, true);
        if ($json && is_array($json)) {
            // Se cidade_uf vier preenchido e cidade tiver vazio, tentar quebrar
            if (!empty($json['cidade_uf'])) {
                $parts = explode(' - ', $json['cidade_uf']);
                if (count($parts) < 2) $parts = explode('-', $json['cidade_uf']);
                
                $json['cidade'] = trim($parts[0] ?? '');
                $json['uf'] = trim($parts[1] ?? '');
            }
            return array_merge($data, $json);
        }

        // Fallback: tentar extrair partes básicas de string plana limitada
        $data['logradouro'] = substr($raw, 0, 100);
        return $data;
    }
}
