<?php

declare(strict_types=1);

namespace Zaherg\OpenRouterAiProvider\Provider;

use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use Zaherg\OpenRouterAiProvider\Metadata\OpenRouterModelMetadataDirectory;
use Zaherg\OpenRouterAiProvider\Models\OpenRouterTextGenerationModel;

/**
 * Class for the AI Provider for OpenRouter.
 *
 * @since 0.1.0
 */
class OpenRouterProvider extends AbstractApiProvider
{
    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected static function baseUrl(): string
    {
        if (defined('OPENROUTER_BASE_URL')) {
            $configuredBaseUrl = constant('OPENROUTER_BASE_URL');
            if (is_scalar($configuredBaseUrl) && (string) $configuredBaseUrl !== '') {
                return rtrim((string) $configuredBaseUrl, '/');
            }
        }

        return 'https://openrouter.ai/api/v1';
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected static function createModel(
        ModelMetadata $modelMetadata,
        ProviderMetadata $providerMetadata
    ): ModelInterface {
        $capabilities = $modelMetadata->getSupportedCapabilities();
        foreach ($capabilities as $capability) {
            if ($capability->isTextGeneration()) {
                return new OpenRouterTextGenerationModel($modelMetadata, $providerMetadata);
            }
        }

        throw new RuntimeException(
            'OpenRouter provider currently supports text generation models via the Responses API only.'
        );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected static function createProviderMetadata(): ProviderMetadata
    {
        return new ProviderMetadata(
            'openrouter',
            'OpenRouter',
            ProviderTypeEnum::cloud(),
            'https://openrouter.ai/settings/keys',
            RequestAuthenticationMethod::apiKey()
        );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected static function createProviderAvailability(): ProviderAvailabilityInterface
    {
        // Check valid API access by attempting to list models.
        return new ListModelsApiBasedProviderAvailability(
            static::modelMetadataDirectory()
        );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface
    {
        return new OpenRouterModelMetadataDirectory();
    }
}
