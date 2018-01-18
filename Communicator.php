<?php

namespace BankId\Merchant\Library;

/**
 * Description of Communicator
 */
class Communicator {
    /**
     * XmlProcessor instance, used to process XMLs (signing, verifying, validating signature)
     */
    protected $xmlProcessor;
    /**
     * IMessenger instance, to be used for sending messages to external URIs
     */
    protected $messenger;
    /**
     * ILogger instance, to be used for logging messages.
     */
    protected $logger;

    /**
     * Configuration instance, to allow merchants use different configurations.
     * @var Configuration $config
     */
    protected $config;

    /** Creates a new Communicator instance
     * Communicator constructor.
     * @param Configuration|null $config
     */
    public function __construct(Configuration $config = null)
    {

        if (is_null($config)) {
            $this->config = Configuration::defaultInstance();
        } else {
            $this->config = $config;
        }

        // Initialize
        $this->xmlProcessor = new XmlProcessor();
        $this->logger = $this->config->getLogger();
        $this->messenger = $this->config->getMessenger();
    }

    /**
     * Sends a directory request to the URL specified in Configuration.AcquirerUrl_DirectoryReq
     */
    public function getDirectory() {
        try {
            $idx = new IdxMessageBuilder();
            $xml = $idx->getDirectoryRequest($this->config, new Internal\DirectoryRequestBase());
            $xml = $this->xmlProcessor->addSignature($this->config, $xml);

            $response = $this->performRequest($xml, $this->config->AcquirerDirectoryUrl);

            $result = DirectoryResponse::parse($response);
            return $result;
        }
        catch (\Exception $exception) {
            $result = DirectoryResponse::getException($exception);
            return $result;
        }
    }
    
    /**
     * Sends a new authentication request to the URL specified in Configuration.AcquirerUrl_TransactionReq
     */
    public function newAuthenticationRequest($authenticationRequest) {
        try {
            $bankid = new BankIdMessageBuilder();
            $xml = $bankid->getTransaction($this->config, $authenticationRequest);

            $idx = new IdxMessageBuilder();
            $xml = $idx->getTransactionRequest($this->config, $authenticationRequest, $xml);

            $xml = $this->xmlProcessor->addSignature($this->config, $xml);

            $response = $this->performRequest($xml, $this->config->AcquirerTransactionUrl);

            $result = AuthenticationResponse::Parse($response);
            return $result;
        }
        catch (\Exception $exception) {
            $result = AuthenticationResponse::getException($exception);
            return $result;
        }
    }
    
    /**
     * Sends a transaction status request to the URL specified in Configuration.AcquirerUrl_TransactionReq
     */
    public function getResponse($statusRequest) {
        try {
            $idx = new IdxMessageBuilder();
            $xml = $idx->getStatusRequest($this->config, $statusRequest);
            $xml = $this->xmlProcessor->addSignature($this->config, $xml);

            $response = $this->performRequest($xml, $this->config->AcquirerStatusUrl);

            $result = StatusResponse::parse($this->config, $response);
            return $result;
        }
        catch (\Exception $exception) {
            $result = StatusResponse::getException($exception);
            return $result;
        }
    }

    protected function performRequest($xml, $url) {
        $this->xmlProcessor->verifySchema($xml);
        
        $this->logger->logXmlMessage($this->config, $xml);
        
        $response = $this->messenger->sendMessage($xml, $url);
        
        $this->logger->logXmlMessage($this->config, $response);
        
        $this->xmlProcessor->verifySchema($response);
        
        $this->xmlProcessor->verifySignature($this->config, $response);
        
        return $response;
    }
}
