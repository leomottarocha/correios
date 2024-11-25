<?php

namespace Source\Models;

use \SoapClient;
use \SoapFault;

class Correios
{
    private $cartao_postagem;
    private $url_cartao_postagem;
    private $cartao_postagem_token;
    private $contrato;
    private $usuario_api;
    private $cod_administrativo;
    private $servico_postagem;
    private $api_key;
    private $url_wsdl_logistica_reversa;
    private $codigo_postagem;
    private $codigo_rastreio;
    private $tipo_postagem;

    public function __construct($cartao_postagem, $contrato, $usuario_api, $cod_administrativo, $servico_postagem, $api_key, $url_wsdl_logistica_reversa, $url_cartao_postagem, $cartao_postagem_token)
    {
        $this->cartao_postagem              = $cartao_postagem;
        $this->contrato                     = $contrato;
        $this->usuario_api                  = $usuario_api;
        $this->cod_administrativo           = $cod_administrativo;
        $this->servico_postagem             = $servico_postagem;
        $this->api_key                      = $api_key;
        $this->url_wsdl_logistica_reversa   = $url_wsdl_logistica_reversa;
        $this->url_cartao_postagem          = $url_cartao_postagem;
        $this->cartao_postagem_token        = $cartao_postagem_token;
    }

    private function __clone() {}

    public function solicitarPostagemReversa($dados_postagem)
    {

        try {
            $dados_coleta = $this->autenticarSoap()->solicitarPostagemReversa($dados_postagem);
            return
                $dados_coleta;
        } catch (SoapFault $e) {
            var_dump($e->getMessage());
        }
    }

    public function cancelarCodigoPostagem(int $codigo_postagem, string $tipo_postagem)
    {
        $data = [
            "codAdministrativo" => $this->cod_administrativo,
            "numeroPedido" => $codigo_postagem,
            "tipo" => $tipo_postagem
        ];

        try {
            $dados_cancelamento = $this->autenticarSoap()->cancelarPedido($data);

            return
                $dados_cancelamento;
        } catch (SoapFault $e) {
            echo "Erro: " . $e->getMessage();
        }
    }

    public function consultarCodigoPostagem(int $numero_postagem, $tipo_busca = "U", $tipo_solicitacao = "A")
    {
        $data_postagem_reversa = [
            "codAdministrativo" => $this->cod_administrativo,
            "numeroPedido" => $numero_postagem,
            "tipoBusca" => $tipo_busca,
            "tipoSolicitacao" => $tipo_solicitacao
        ];

        try {
            $dados_coleta = $this->autenticarSoap()->acompanharPedido($data_postagem_reversa);
        } catch (SoapFault $e) {
            return "Erro: " . $e->getMessage();
        }

        return
            $dados_coleta;
    }

    public function consultarCodigoRastreio(string $numero_rastreio)
    {
        $curlHandler = curl_init();
        curl_setopt_array($curlHandler, [
            CURLOPT_URL => "https://api.correios.com.br/srorastro/v1/objetos/{$numero_rastreio}?resultado=U",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Expires: 0',
                'Authorization: Bearer ' . $this->tokenConsultaRastreio()
            ],
        ]);
        $response = curl_exec($curlHandler);
        curl_close($curlHandler);
        $data = json_decode($response);

        return $data;
    }

    public function consultarCEP(int $cep)
    {
        $curlHandler = curl_init();
        curl_setopt_array($curlHandler, [
            CURLOPT_URL => "https://api.correios.com.br/cep/v2/enderecos/{$cep}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Expires: 0',
                'Authorization: Bearer ' . $this->tokenConsultaRastreio()
            ],
        ]);
        $response = curl_exec($curlHandler);
        curl_close($curlHandler);
        $data = json_decode($response);
        if (!isset($data->logradouro)) {
            echo "Endereço sem logradouro";
            return false;
        }

        return $data;
    }

    private function tokenConsultaRastreio()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url_cartao_postagem);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'accept: application/json',
            'Content-Type: application/json',
            'Authorization: ' . $this->cartao_postagem_token
        ));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array("numero" => $this->cartao_postagem)));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = json_decode(curl_exec($ch));

        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
        curl_close($ch);
        return $response->token;
    }

    private function autenticarSoap()
    {

        // Contexto de stream com cabeçalho de autenticação
        $stream_context = stream_context_create(array(
            'http' => array(
                'header' => "Authorization: Basic " . base64_encode("$this->usuario_api:$this->api_key")
            ),
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        ));

        // Opções do cliente SOAP
        $options = array(
            'cache_wsdl' => 0,
            'trace' => 1,
            'stream_context' => $stream_context
        );

        // Criação do cliente SOAP
        try {
            $client = new SoapClient($this->url_wsdl_logistica_reversa, $options);
            return $client;
        } catch (SoapFault $e) {
            echo "Erro ao criar o cliente SOAP: " . $e->getMessage();
            return null;
        }
    }
}
