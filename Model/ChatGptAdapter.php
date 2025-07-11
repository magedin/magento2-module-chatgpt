<?php
/**
 * Copyright © MagedIn. All rights reserved.
 */
declare(strict_types=1);

namespace MagedIn\ChatGpt\Model;

use MagedIn\Ai\Api\AiProviderAdapterInterface;
use MagedIn\Ai\Api\Data\AiRequestInterface;
use MagedIn\Ai\Api\Data\AiResponseInterface;
use MagedIn\Ai\Api\Data\AiResponseInterfaceFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\HTTP\ClientInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * ChatGPT AI Provider Adapter
 *
 * Adapter for communicating with OpenAI's ChatGPT API
 * Implements the AI provider interface for ChatGPT integration
 */
class ChatGptAdapter implements AiProviderAdapterInterface
{
    private const PROVIDER_NAME = 'chatgpt';
    private const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    private const CONFIG_API_KEY = 'magedin_ai/chatgpt/api_key';
    private const CONFIG_MODEL = 'magedin_ai/chatgpt/model';
    private const CONFIG_ENABLED = 'magedin_ai/chatgpt/enabled';

    private const DEFAULT_MODEL = 'gpt-3.5-turbo';
    private const DEFAULT_MAX_TOKENS = 1000;
    private const DEFAULT_TEMPERATURE = 0.7;

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var ClientInterface
     */
    private ClientInterface $httpClient;

    /**
     * @var Json
     */
    private Json $jsonSerializer;

    /**
     * @var AiResponseInterfaceFactory
     */
    private AiResponseInterfaceFactory $aiResponseFactory;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;
    private EncryptorInterface $encryptor;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param ClientInterface $httpClient
     * @param Json $jsonSerializer
     * @param AiResponseInterfaceFactory $aiResponseFactory
     * @param LoggerInterface $logger
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ClientInterface $httpClient,
        Json $jsonSerializer,
        AiResponseInterfaceFactory $aiResponseFactory,
        LoggerInterface $logger,
        EncryptorInterface $encryptor
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->httpClient = $httpClient;
        $this->jsonSerializer = $jsonSerializer;
        $this->aiResponseFactory = $aiResponseFactory;
        $this->logger = $logger;
        $this->encryptor = $encryptor;
    }

    /**
     * @inheritDoc
     */
    public function query(AiRequestInterface $request): AiResponseInterface
    {
        $response = $this->aiResponseFactory->create();
        $response->setProvider(self::PROVIDER_NAME);

        try {
            $apiKey = $this->getApiKey();
            if (!$apiKey) {
                throw new \Exception('ChatGPT API key is not configured');
            }

            $requestData = $this->prepareRequestData($request);
            $apiResponse = $this->sendApiRequest($apiKey, $requestData);

            $content = $this->extractContentFromResponse($apiResponse);
            $tokensUsed = $this->extractTokensFromResponse($apiResponse);

            $response->setContent($content);
            $response->setTokensUsed($tokensUsed);
            $response->setSuccess(true);
            $response->setMetadata($apiResponse);

        } catch (\Exception $e) {
            $this->logger->error('ChatGPT API request failed', [
                'message' => $e->getMessage(),
                'exception' => $e
            ]);

            $response->setSuccess(false);
            $response->setErrorMessage($e->getMessage());
            $response->setContent('');
        }

        return $response;
    }

    /**
     * @inheritDoc
     */
    public function getProviderName(): string
    {
        return self::PROVIDER_NAME;
    }

    /**
     * @inheritDoc
     */
    public function isAvailable(): bool
    {
        return $this->isEnabled() && !empty($this->getApiKey());
    }

    /**
     * @inheritDoc
     */
    public function getSupportedFeatures(): array
    {
        return [
            'text_generation',
            'conversation',
            'context_aware',
            'temperature_control',
            'max_tokens_control'
        ];
    }

    /**
     * Check if ChatGPT is enabled in configuration
     *
     * @return bool
     */
    private function isEnabled(): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::CONFIG_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get API key from configuration
     *
     * @return string|null
     */
    private function getApiKey(): ?string
    {
        $encryptedValue = $this->scopeConfig->getValue(
            self::CONFIG_API_KEY,
            ScopeInterface::SCOPE_STORE
        );
        $apiKey = $this->encryptor->decrypt($encryptedValue);
        return !empty($apiKey) ? $apiKey : null;
    }

    /**
     * Get configured model or default
     *
     * @return string
     */
    private function getModel(): string
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_MODEL,
            ScopeInterface::SCOPE_STORE
        ) ?: self::DEFAULT_MODEL;
    }

    /**
     * Prepare request data for ChatGPT API
     *
     * @param AiRequestInterface $request
     * @return array
     */
    private function prepareRequestData(AiRequestInterface $request): array
    {
        $data = [
            'model' => $this->getModel(),
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $request->getMessage()
                ]
            ],
            'max_tokens' => $request->getMaxTokens() ?: self::DEFAULT_MAX_TOKENS,
            'temperature' => $request->getTemperature() ?: self::DEFAULT_TEMPERATURE
        ];

        // Add context if provided
        $context = $request->getContext();
        if (!empty($context['system_message'])) {
            array_unshift($data['messages'], [
                'role' => 'system',
                'content' => $context['system_message']
            ]);
        }

        return $data;
    }

    /**
     * Send API request to ChatGPT
     *
     * @param string $apiKey
     * @param array $requestData
     * @return array
     * @throws \Exception
     */
    private function sendApiRequest(string $apiKey, array $requestData): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json'
        ];

        $this->httpClient->setHeaders($headers);
        $this->httpClient->post(self::API_ENDPOINT, $this->jsonSerializer->serialize($requestData));

        $responseBody = $this->httpClient->getBody();
        $statusCode = $this->httpClient->getStatus();

        if ($statusCode !== 200) {
            throw new \Exception("ChatGPT API request failed with status code: {$statusCode}. Response: {$responseBody}");
        }

        return $this->jsonSerializer->unserialize($responseBody);
    }

    /**
     * Extract content from API response
     *
     * @param array $apiResponse
     * @return string
     */
    private function extractContentFromResponse(array $apiResponse): string
    {
        return $apiResponse['choices'][0]['message']['content'] ?? '';
    }

    /**
     * Extract tokens used from API response
     *
     * @param array $apiResponse
     * @return int|null
     */
    private function extractTokensFromResponse(array $apiResponse): ?int
    {
        return $apiResponse['usage']['total_tokens'] ?? null;
    }
}
