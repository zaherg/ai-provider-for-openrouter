<?php

declare(strict_types=1);

namespace Tests\Metadata;

use Tests\TestCase;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use Zaherg\OpenRouterAiProvider\Metadata\OpenRouterModelMetadataDirectory;

class OpenRouterModelMetadataDirectoryTest extends TestCase
{
    private OpenRouterModelMetadataDirectory $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = new OpenRouterModelMetadataDirectory();
    }

    // ---------------------------------------------------------------
    // Helper: call protected methods via reflection
    // ---------------------------------------------------------------

    /**
     * @param array<string, mixed> $modelData
     */
    private function callParseModelData(array $modelData): ?ModelMetadata
    {
        $reflection = new \ReflectionMethod($this->directory, 'parseModelDataToMetadata');
        $reflection->setAccessible(true);
        /** @var ModelMetadata|null */
        $result = $reflection->invoke($this->directory, $modelData);
        return $result;
    }

    /**
     * @param array<string, mixed> $modelData
     * @return list<string>
     */
    private function callGetSupportedParameters(array $modelData): array
    {
        $reflection = new \ReflectionMethod($this->directory, 'getSupportedParameters');
        $reflection->setAccessible(true);
        /** @var list<string> */
        $result = $reflection->invoke($this->directory, $modelData);
        return $result;
    }

    /**
     * @param list<string> $supportedParameters
     * @param list<string> $inputModalities
     * @return list<\WordPress\AiClient\Providers\Models\DTO\SupportedOption>
     */
    private function callCreateTextOptions(array $supportedParameters, array $inputModalities): array
    {
        $reflection = new \ReflectionMethod($this->directory, 'createTextOptions');
        $reflection->setAccessible(true);
        /** @var list<\WordPress\AiClient\Providers\Models\DTO\SupportedOption> */
        $result = $reflection->invoke($this->directory, $supportedParameters, $inputModalities);
        return $result;
    }

    /**
     * @param list<string> $inputModalities
     * @return \WordPress\AiClient\Providers\Models\DTO\SupportedOption
     */
    private function callCreateInputModalitiesOption(array $inputModalities): \WordPress\AiClient\Providers\Models\DTO\SupportedOption
    {
        $reflection = new \ReflectionMethod($this->directory, 'createInputModalitiesOption');
        $reflection->setAccessible(true);
        /** @var \WordPress\AiClient\Providers\Models\DTO\SupportedOption */
        $result = $reflection->invoke($this->directory, $inputModalities);
        return $result;
    }

    private function callModelSortCallback(ModelMetadata $a, ModelMetadata $b): int
    {
        $reflection = new \ReflectionMethod($this->directory, 'modelSortCallback');
        $reflection->setAccessible(true);
        /** @var int */
        $result = $reflection->invoke($this->directory, $a, $b);
        return $result;
    }

    /**
     * @return list<ModelMetadata>
     */
    private function callParseResponseToModelMetadataList(Response $response): array
    {
        $reflection = new \ReflectionMethod($this->directory, 'parseResponseToModelMetadataList');
        $reflection->setAccessible(true);
        /** @var list<ModelMetadata> */
        $result = $reflection->invoke($this->directory, $response);
        return $result;
    }

    // ---------------------------------------------------------------
    // Helpers: create minimal model data arrays
    // ---------------------------------------------------------------

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function makeModelData(array $overrides = []): array
    {
        return array_merge([
            'id' => 'openai/gpt-4',
            'name' => 'GPT-4',
            'architecture' => [
                'input_modalities' => ['text'],
                'output_modalities' => ['text'],
            ],
            'supported_parameters' => ['max_output_tokens', 'temperature', 'top_p', 'tools'],
        ], $overrides);
    }

    private function makeModelMetadata(string $id, string $name = ''): ModelMetadata
    {
        return new ModelMetadata(
            $id,
            $name ?: $id,
            [\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::textGeneration()],
            []
        );
    }

    // ---------------------------------------------------------------
    // Model parsing tests
    // ---------------------------------------------------------------

    public function test_parse_text_model(): void
    {
        $result = $this->callParseModelData($this->makeModelData());

        $this->assertNotNull($result);
        $this->assertSame('openai/gpt-4', $result->getId());
        $this->assertSame('GPT-4', $result->getName());
    }

    public function test_skip_non_text_model(): void
    {
        $data = $this->makeModelData([
            'architecture' => [
                'input_modalities' => ['text'],
                'output_modalities' => ['image'],
            ],
        ]);

        $result = $this->callParseModelData($data);
        $this->assertNull($result);
    }

    public function test_parse_model_with_legacy_modality_field(): void
    {
        $data = [
            'id' => 'legacy/model',
            'name' => 'Legacy Model',
            'architecture' => [
                'modality' => 'text->text',
            ],
            'supported_parameters' => [],
        ];

        $result = $this->callParseModelData($data);
        $this->assertNotNull($result);
        $this->assertSame('legacy/model', $result->getId());
    }

    public function test_parse_model_without_id_returns_null(): void
    {
        $data = ['name' => 'No ID Model'];
        $result = $this->callParseModelData($data);
        $this->assertNull($result);
    }

    public function test_display_name_falls_back_to_id(): void
    {
        $data = $this->makeModelData();
        unset($data['name']);

        $result = $this->callParseModelData($data);
        $this->assertNotNull($result);
        $this->assertSame('openai/gpt-4', $result->getName());
    }

    // ---------------------------------------------------------------
    // Supported parameters & options tests
    // ---------------------------------------------------------------

    public function test_supported_parameters_extraction(): void
    {
        $data = $this->makeModelData([
            'supported_parameters' => ['temperature', 'top_p', 'max_tokens'],
        ]);

        $params = $this->callGetSupportedParameters($data);
        $this->assertContains('temperature', $params);
        $this->assertContains('top_p', $params);
        $this->assertContains('max_tokens', $params);
    }

    public function test_empty_supported_parameters(): void
    {
        $data = $this->makeModelData(['supported_parameters' => []]);
        $params = $this->callGetSupportedParameters($data);
        $this->assertSame([], $params);
    }

    public function test_options_with_temperature_max_tokens(): void
    {
        $options = $this->callCreateTextOptions(['temperature', 'max_output_tokens'], ['text']);
        $optionNames = array_map(fn($opt) => (string) $opt->getName(), $options);

        $this->assertContains('temperature', $optionNames);
        $this->assertContains('maxTokens', $optionNames);
    }

    public function test_options_with_tools_web_search(): void
    {
        $options = $this->callCreateTextOptions(['tools', 'web_search'], ['text']);
        $optionNames = array_map(fn($opt) => (string) $opt->getName(), $options);

        $this->assertContains('functionDeclarations', $optionNames);
        $this->assertContains('webSearch', $optionNames);
    }

    public function test_default_options_when_no_supported_parameters(): void
    {
        $options = $this->callCreateTextOptions([], ['text']);
        $optionNames = array_map(fn($opt) => (string) $opt->getName(), $options);

        // When no supported_parameters, defaults should include maxTokens, temperature, etc.
        $this->assertContains('maxTokens', $optionNames);
        $this->assertContains('temperature', $optionNames);
        $this->assertContains('topP', $optionNames);
        $this->assertContains('functionDeclarations', $optionNames);
    }

    // ---------------------------------------------------------------
    // Input modalities tests
    // ---------------------------------------------------------------

    public function test_input_modalities_text_only(): void
    {
        $option = $this->callCreateInputModalitiesOption(['text']);
        $values = $option->getSupportedValues();
        $this->assertNotNull($values);

        $this->assertCount(1, $values);
        // First value should be [text]
        $this->assertIsArray($values[0]);
        $this->assertCount(1, $values[0]);
    }

    public function test_input_modalities_with_image(): void
    {
        $option = $this->callCreateInputModalitiesOption(['text', 'image']);
        $values = $option->getSupportedValues();
        $this->assertNotNull($values);

        // Should have [text] and [text, image]
        $this->assertCount(2, $values);
    }

    public function test_input_modalities_with_image_and_audio(): void
    {
        $option = $this->callCreateInputModalitiesOption(['text', 'image', 'audio']);
        $values = $option->getSupportedValues();
        $this->assertNotNull($values);

        // [text], [text, image], [text, audio], [text, image, audio]
        $this->assertCount(4, $values);
    }

    public function test_input_modalities_with_document(): void
    {
        $option = $this->callCreateInputModalitiesOption(['text', 'document']);
        $values = $option->getSupportedValues();
        $this->assertNotNull($values);

        // [text], [text, document]
        $this->assertCount(2, $values);
    }

    // ---------------------------------------------------------------
    // Sorting tests
    // ---------------------------------------------------------------

    public function test_sort_non_preview_before_preview(): void
    {
        $a = $this->makeModelMetadata('openai/gpt-4-preview');
        $b = $this->makeModelMetadata('openai/gpt-4');

        $this->assertGreaterThan(0, $this->callModelSortCallback($a, $b));
        $this->assertLessThan(0, $this->callModelSortCallback($b, $a));
    }

    public function test_sort_by_vendor_rank(): void
    {
        $openai = $this->makeModelMetadata('openai/gpt-4');
        $anthropic = $this->makeModelMetadata('anthropic/claude-3');
        $google = $this->makeModelMetadata('google/gemini');
        $meta = $this->makeModelMetadata('meta-llama/llama-3');
        $mistral = $this->makeModelMetadata('mistralai/mistral-large');
        $other = $this->makeModelMetadata('cohere/command-r');

        $this->assertLessThan(0, $this->callModelSortCallback($openai, $anthropic));
        $this->assertLessThan(0, $this->callModelSortCallback($anthropic, $google));
        $this->assertLessThan(0, $this->callModelSortCallback($google, $meta));
        $this->assertLessThan(0, $this->callModelSortCallback($meta, $mistral));
        $this->assertLessThan(0, $this->callModelSortCallback($mistral, $other));
    }

    public function test_sort_free_after_non_free(): void
    {
        $a = $this->makeModelMetadata('openai/gpt-4:free');
        $b = $this->makeModelMetadata('openai/gpt-4');

        $this->assertGreaterThan(0, $this->callModelSortCallback($a, $b));
        $this->assertLessThan(0, $this->callModelSortCallback($b, $a));
    }

    public function test_sort_alphabetical_fallback(): void
    {
        $a = $this->makeModelMetadata('openai/aaa');
        $b = $this->makeModelMetadata('openai/zzz');

        $this->assertLessThan(0, $this->callModelSortCallback($a, $b));
        $this->assertGreaterThan(0, $this->callModelSortCallback($b, $a));
    }

    // ---------------------------------------------------------------
    // Response parsing tests
    // ---------------------------------------------------------------

    public function test_parse_response_to_model_list(): void
    {
        $responseBody = (string) json_encode([
            'data' => [
                [
                    'id' => 'openai/gpt-4',
                    'name' => 'GPT-4',
                    'architecture' => [
                        'input_modalities' => ['text'],
                        'output_modalities' => ['text'],
                    ],
                    'supported_parameters' => ['temperature', 'max_output_tokens'],
                ],
                [
                    'id' => 'anthropic/claude-3',
                    'name' => 'Claude 3',
                    'architecture' => [
                        'input_modalities' => ['text', 'image'],
                        'output_modalities' => ['text'],
                    ],
                    'supported_parameters' => ['temperature', 'top_p'],
                ],
            ],
        ]);

        $response = new Response(200, ['Content-Type' => 'application/json'], $responseBody);
        $models = $this->callParseResponseToModelMetadataList($response);

        $this->assertCount(2, $models);
        // openai should sort before anthropic
        $this->assertSame('openai/gpt-4', $models[0]->getId());
        $this->assertSame('anthropic/claude-3', $models[1]->getId());
    }

    public function test_parse_response_missing_data_throws(): void
    {
        $response = new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([]));

        $this->expectException(ResponseException::class);
        $this->callParseResponseToModelMetadataList($response);
    }

    public function test_parse_response_invalid_data_throws(): void
    {
        $response = new Response(
            200,
            ['Content-Type' => 'application/json'],
            (string) json_encode(['data' => 'not-an-array'])
        );

        $this->expectException(ResponseException::class);
        $this->callParseResponseToModelMetadataList($response);
    }
}
