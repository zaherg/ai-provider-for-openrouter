<?php

declare(strict_types=1);

namespace Tests\Provider;

use Tests\TestCase;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use Zaherg\OpenRouterAiProvider\Metadata\OpenRouterModelMetadataDirectory;
use Zaherg\OpenRouterAiProvider\Provider\OpenRouterProvider;

class OpenRouterProviderTest extends TestCase
{
    public function test_base_url_returns_default(): void
    {
        $url = OpenRouterProvider::url('');
        $this->assertSame('https://openrouter.ai/api/v1', $url);
    }

    public function test_url_with_path(): void
    {
        $url = OpenRouterProvider::url('responses');
        $this->assertSame('https://openrouter.ai/api/v1/responses', $url);
    }

    public function test_provider_metadata(): void
    {
        $metadata = OpenRouterProvider::metadata();
        $this->assertSame('openrouter', $metadata->getId());
        $this->assertSame('OpenRouter', $metadata->getName());
        $this->assertTrue($metadata->getType()->isCloud());
        $authMethod = $metadata->getAuthenticationMethod();
        $this->assertNotNull($authMethod);
        $this->assertTrue($authMethod->isApiKey());
    }

    public function test_model_metadata_directory_returns_correct_instance(): void
    {
        $directory = OpenRouterProvider::modelMetadataDirectory();
        $this->assertInstanceOf(OpenRouterModelMetadataDirectory::class, $directory);
    }

    public function test_create_model_with_text_generation_capability(): void
    {
        $modelMetadata = new \WordPress\AiClient\Providers\Models\DTO\ModelMetadata(
            'openai/gpt-4',
            'GPT-4',
            [CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory()],
            []
        );

        // Use reflection to call the protected static createModel method.
        $reflection = new \ReflectionMethod(OpenRouterProvider::class, 'createModel');
        $reflection->setAccessible(true);

        $providerMetadata = OpenRouterProvider::metadata();
        $model = $reflection->invoke(null, $modelMetadata, $providerMetadata);

        $this->assertInstanceOf(
            \Zaherg\OpenRouterAiProvider\Models\OpenRouterTextGenerationModel::class,
            $model
        );
    }

    public function test_create_model_throws_for_unsupported_capability(): void
    {
        $modelMetadata = new \WordPress\AiClient\Providers\Models\DTO\ModelMetadata(
            'some/image-model',
            'Image Model',
            [CapabilityEnum::imageGeneration()],
            []
        );

        $reflection = new \ReflectionMethod(OpenRouterProvider::class, 'createModel');
        $reflection->setAccessible(true);

        $this->expectException(\WordPress\AiClient\Common\Exception\RuntimeException::class);
        $reflection->invoke(null, $modelMetadata, OpenRouterProvider::metadata());
    }
}
