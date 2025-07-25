<?php
/**
 * MagedIn Technology
 *
 * Do not edit this file if you want to update this module for future new versions.
 *
 * @category  MagedIn
 * @copyright Copyright (c) 2025 MagedIn Technology.
 *
 * @author    MagedIn Support <support@magedin.com>
 */
declare(strict_types=1);

namespace MagedIn\ChatGpt\Model;

use MagedIn\ChatGpt\Api\ConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * ChatGPT Configuration Class
 *
 * Centralized configuration management for ChatGPT module
 * Handles all system configuration retrieval and processing
 */
class Config implements ConfigInterface
{
    /**
     * Configuration paths
     */
    private const CONFIG_API_KEY = 'magedin_ai/chatgpt/api_key';
    private const CONFIG_MODEL = 'magedin_ai/chatgpt/model';
    private const CONFIG_ENABLED = 'magedin_ai/chatgpt/enabled';

    /**
     * Default values
     */
    private const DEFAULT_MODEL = 'gpt-3.5-turbo';
    private const DEFAULT_MAX_TOKENS = 1000;
    private const DEFAULT_TEMPERATURE = 0.7;

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var EncryptorInterface
     */
    private EncryptorInterface $encryptor;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
    }

    /**
     * Check if ChatGPT is enabled
     *
     * @param string|null $scopeType
     * @param string|int|null $scopeCode
     * @return bool
     */
    public function isEnabled(?string $scopeType = ScopeInterface::SCOPE_STORE, $scopeCode = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::CONFIG_ENABLED,
            $scopeType,
            $scopeCode
        );
    }

    /**
     * Get decrypted API key
     *
     * @param string|null $scopeType
     * @param string|int|null $scopeCode
     * @return string|null
     */
    public function getApiKey(?string $scopeType = ScopeInterface::SCOPE_STORE, $scopeCode = null): ?string
    {
        $encryptedValue = $this->scopeConfig->getValue(
            self::CONFIG_API_KEY,
            $scopeType,
            $scopeCode
        );

        if (empty($encryptedValue)) {
            return null;
        }

        $apiKey = $this->encryptor->decrypt($encryptedValue);
        return !empty($apiKey) ? $apiKey : null;
    }

    /**
     * Get configured ChatGPT model
     *
     * @param string|null $scopeType
     * @param string|int|null $scopeCode
     * @return string
     */
    public function getModel(?string $scopeType = ScopeInterface::SCOPE_STORE, $scopeCode = null): string
    {
        $model = $this->scopeConfig->getValue(
            self::CONFIG_MODEL,
            $scopeType,
            $scopeCode
        );

        return !empty($model) ? $model : self::DEFAULT_MODEL;
    }

    /**
     * Get default maximum tokens
     *
     * @return int
     */
    public function getDefaultMaxTokens(): int
    {
        return self::DEFAULT_MAX_TOKENS;
    }

    /**
     * Get default temperature
     *
     * @return float
     */
    public function getDefaultTemperature(): float
    {
        return self::DEFAULT_TEMPERATURE;
    }

    /**
     * Check if ChatGPT is properly configured
     *
     * @param string|null $scopeType
     * @param string|int|null $scopeCode
     * @return bool
     */
    public function isConfigured(?string $scopeType = ScopeInterface::SCOPE_STORE, $scopeCode = null): bool
    {
        return $this->isEnabled($scopeType, $scopeCode) && !empty($this->getApiKey($scopeType, $scopeCode));
    }

    /**
     * Get all configuration as array
     *
     * @param string|null $scopeType
     * @param string|int|null $scopeCode
     * @return array
     */
    public function getConfiguration(?string $scopeType = ScopeInterface::SCOPE_STORE, $scopeCode = null): array
    {
        return [
            'enabled' => $this->isEnabled($scopeType, $scopeCode),
            'api_key' => $this->getApiKey($scopeType, $scopeCode),
            'model' => $this->getModel($scopeType, $scopeCode),
            'default_max_tokens' => $this->getDefaultMaxTokens(),
            'default_temperature' => $this->getDefaultTemperature(),
            'is_configured' => $this->isConfigured($scopeType, $scopeCode)
        ];
    }
}