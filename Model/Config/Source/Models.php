<?php
/**
 * Copyright © MagedIn. All rights reserved.
 */
declare(strict_types=1);

namespace MagedIn\ChatGpt\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * ChatGPT Models Source Model
 * 
 * Provides available ChatGPT models for system configuration
 */
class Models implements OptionSourceInterface
{
    /**
     * Available ChatGPT models
     */
    private const MODELS = [
        'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
        'gpt-3.5-turbo-16k' => 'GPT-3.5 Turbo 16K',
        'gpt-4' => 'GPT-4',
        'gpt-4-32k' => 'GPT-4 32K',
        'gpt-4-turbo' => 'GPT-4 Turbo',
        'gpt-4o' => 'GPT-4o',
        'gpt-4o-mini' => 'GPT-4o Mini'
    ];

    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        $options = [];
        
        foreach (self::MODELS as $value => $label) {
            $options[] = [
                'value' => $value,
                'label' => $label
            ];
        }

        return $options;
    }
}