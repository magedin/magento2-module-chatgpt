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
use MagedIn\ChatGpt\Api\ConfigInterface;
use Magento\Framework\HTTP\ClientInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

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

    /**
     * @var ConfigInterface
     */
    private ConfigInterface $config;

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

    /**
     * @param ConfigInterface $config
     * @param ClientInterface $httpClient
     * @param Json $jsonSerializer
     * @param AiResponseInterfaceFactory $aiResponseFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        ConfigInterface $config,
        ClientInterface $httpClient,
        Json $jsonSerializer,
        AiResponseInterfaceFactory $aiResponseFactory,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->httpClient = $httpClient;
        $this->jsonSerializer = $jsonSerializer;
        $this->aiResponseFactory = $aiResponseFactory;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function query(AiRequestInterface $request): AiResponseInterface
    {
        $response = $this->aiResponseFactory->create();
        $response->setProvider(self::PROVIDER_NAME);

        try {
            $apiKey = $this->config->getApiKey();
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
        return $this->config->isConfigured();
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
     * Prepare request data for ChatGPT API
     *
     * @param AiRequestInterface $request
     * @return array
     */
    private function prepareRequestData(AiRequestInterface $request): array
    {
        $data = [
            'model' => $this->config->getModel(),
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $request->getMessage()
                ]
            ],
            'max_tokens' => $request->getMaxTokens() ?: $this->config->getDefaultMaxTokens(),
            'temperature' => $request->getTemperature() ?: $this->config->getDefaultTemperature()
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
