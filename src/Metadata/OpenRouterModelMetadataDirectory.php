<?php

declare(strict_types=1);

namespace Zaherg\OpenRouterAiProvider\Metadata;

use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleModelMetadataDirectory;
use Zaherg\OpenRouterAiProvider\Provider\OpenRouterProvider;

/**
 * Class for the OpenRouter model metadata directory.
 *
 * @since 0.1.0
 *
 * @phpstan-type OpenRouterArchitectureData array{
 *     modality?: string|null,
 *     input_modalities?: list<string>,
 *     output_modalities?: list<string>
 * }
 * @phpstan-type OpenRouterModelData array{
 *     id: string,
 *     name?: string,
 *     architecture?: OpenRouterArchitectureData,
 *     supported_parameters?: list<string>
 * }
 * @phpstan-type ModelsResponseData array{
 *     data: list<OpenRouterModelData>
 * }
 */
class OpenRouterModelMetadataDirectory extends AbstractOpenAiCompatibleModelMetadataDirectory
{
    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected function createRequest(HttpMethodEnum $method, string $path, array $headers = [], $data = null): Request
    {
        return new Request(
            $method,
            OpenRouterProvider::url($path),
            $headers,
            $data
        );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected function parseResponseToModelMetadataList(Response $response): array
    {
        /** @var ModelsResponseData $responseData */
        $responseData = $response->getData();
        if (!isset($responseData['data']) || !$responseData['data']) {
            throw ResponseException::fromMissingData('OpenRouter', 'data');
        }
        if (!is_array($responseData['data']) || !array_is_list($responseData['data'])) {
            throw ResponseException::fromInvalidData(
                'OpenRouter',
                'data',
                'The value must be an indexed array.'
            );
        }

        $models = [];
        foreach ($responseData['data'] as $index => $modelData) {
            if (!is_array($modelData) || array_is_list($modelData)) {
                throw ResponseException::fromInvalidData(
                    'OpenRouter',
                    "data[{$index}]",
                    'The value must be an associative array.'
                );
            }

            $model = $this->parseModelDataToMetadata($modelData);
            if ($model !== null) {
                $models[] = $model;
            }
        }

        usort($models, [$this, 'modelSortCallback']);

        return $models;
    }

    /**
     * Parses an OpenRouter model entry into ModelMetadata.
     *
     * Only text-output models are included because this provider currently implements
     * text generation via the OpenRouter OpenAI-compatible Responses API.
     *
     * @since 0.1.0
     *
     * @param array<string, mixed> $modelData OpenRouter model entry data.
     * @return ModelMetadata|null Parsed metadata, or null if unsupported.
     */
    protected function parseModelDataToMetadata(array $modelData): ?ModelMetadata
    {
        if (!isset($modelData['id']) || !is_string($modelData['id'])) {
            return null;
        }

        $modelId = $modelData['id'];
        $outputModalities = $this->getArchitectureModalities($modelData, 'output_modalities');

        if (!$this->supportsTextOutput($modelData, $outputModalities)) {
            return null;
        }

        $displayName = isset($modelData['name']) && is_string($modelData['name'])
            ? $modelData['name']
            : $modelId;

        $inputModalities = $this->getArchitectureModalities($modelData, 'input_modalities');
        $supportedParameters = $this->getSupportedParameters($modelData);

        return new ModelMetadata(
            $modelId,
            $displayName,
            [
                CapabilityEnum::textGeneration(),
                CapabilityEnum::chatHistory(),
            ],
            $this->createTextOptions($supportedParameters, $inputModalities)
        );
    }

    /**
     * Returns supported parameters from an OpenRouter model entry.
     *
     * @since 0.1.0
     *
     * @param array<string, mixed> $modelData OpenRouter model entry data.
     * @return list<string> Supported parameters.
     */
    protected function getSupportedParameters(array $modelData): array
    {
        if (!isset($modelData['supported_parameters']) || !is_array($modelData['supported_parameters'])) {
            return [];
        }

        $supportedParameters = [];
        foreach ($modelData['supported_parameters'] as $parameter) {
            if (is_string($parameter)) {
                $supportedParameters[] = $parameter;
            }
        }

        return array_values(array_unique($supportedParameters));
    }

    /**
     * Returns input/output modalities from the OpenRouter architecture block.
     *
     * @since 0.1.0
     *
     * @param array<string, mixed> $modelData OpenRouter model entry data.
     * @param string               $key       Either 'input_modalities' or 'output_modalities'.
     * @return list<string> Normalized modality strings.
     */
    protected function getArchitectureModalities(array $modelData, string $key): array
    {
        if (
            !isset($modelData['architecture']) ||
            !is_array($modelData['architecture']) ||
            !isset($modelData['architecture'][$key]) ||
            !is_array($modelData['architecture'][$key])
        ) {
            return [];
        }

        $modalities = [];
        foreach ($modelData['architecture'][$key] as $modality) {
            if (is_string($modality)) {
                $modalities[] = $modality;
            }
        }

        return array_values(array_unique($modalities));
    }

    /**
     * Determines whether a model supports text output.
     *
     * @since 0.1.0
     *
     * @param array<string, mixed> $modelData       OpenRouter model entry data.
     * @param list<string>         $outputModalities Parsed output modalities.
     * @return bool True if the model can produce text.
     */
    protected function supportsTextOutput(array $modelData, array $outputModalities): bool
    {
        if (in_array('text', $outputModalities, true)) {
            return true;
        }

        if (
            isset($modelData['architecture']) &&
            is_array($modelData['architecture']) &&
            isset($modelData['architecture']['modality']) &&
            is_string($modelData['architecture']['modality'])
        ) {
            return str_ends_with($modelData['architecture']['modality'], '->text');
        }

        return false;
    }

    /**
     * Creates supported options for a text-capable OpenRouter model.
     *
     * @since 0.1.0
     *
     * @param list<string> $supportedParameters OpenRouter-supported parameters.
     * @param list<string> $inputModalities     OpenRouter input modalities.
     * @return list<SupportedOption> Supported options.
     */
    protected function createTextOptions(array $supportedParameters, array $inputModalities): array
    {
        $options = [
            new SupportedOption(OptionEnum::systemInstruction()),
            new SupportedOption(OptionEnum::outputMimeType(), ['text/plain', 'application/json']),
            new SupportedOption(OptionEnum::outputSchema()),
            $this->createInputModalitiesOption($inputModalities),
            new SupportedOption(OptionEnum::outputModalities(), [[ModalityEnum::text()]]),
            new SupportedOption(OptionEnum::customOptions()),
        ];

        if ($this->supportsAnyParameter($supportedParameters, ['max_output_tokens', 'max_tokens', 'max_completion_tokens'])) {
            $options[] = new SupportedOption(OptionEnum::maxTokens());
        }
        if ($this->supportsParameter($supportedParameters, 'temperature')) {
            $options[] = new SupportedOption(OptionEnum::temperature());
        }
        if ($this->supportsParameter($supportedParameters, 'top_p')) {
            $options[] = new SupportedOption(OptionEnum::topP());
        }
        if ($this->supportsParameter($supportedParameters, 'presence_penalty')) {
            $options[] = new SupportedOption(OptionEnum::presencePenalty());
        }
        if ($this->supportsParameter($supportedParameters, 'frequency_penalty')) {
            $options[] = new SupportedOption(OptionEnum::frequencyPenalty());
        }
        if ($this->supportsAnyParameter($supportedParameters, ['tools', 'tool_choice'])) {
            $options[] = new SupportedOption(OptionEnum::functionDeclarations());
        }
        if ($this->supportsAnyParameter($supportedParameters, ['web_search', 'web_search_options'])) {
            $options[] = new SupportedOption(OptionEnum::webSearch());
        }

        /*
         * Some OpenRouter model entries may omit `supported_parameters`.
         * In that case, expose a conservative default set for text generation.
         */
        if ($supportedParameters === []) {
            $options[] = new SupportedOption(OptionEnum::maxTokens());
            $options[] = new SupportedOption(OptionEnum::temperature());
            $options[] = new SupportedOption(OptionEnum::topP());
            $options[] = new SupportedOption(OptionEnum::presencePenalty());
            $options[] = new SupportedOption(OptionEnum::frequencyPenalty());
            $options[] = new SupportedOption(OptionEnum::functionDeclarations());
        }

        return $options;
    }

    /**
     * Creates the input modalities option from OpenRouter architecture data.
     *
     * @since 0.1.0
     *
     * @param list<string> $inputModalities OpenRouter input modalities.
     * @return SupportedOption Supported input modalities option.
     */
    protected function createInputModalitiesOption(array $inputModalities): SupportedOption
    {
        $supportedValues = [
            [ModalityEnum::text()],
        ];

        $hasImage = in_array('image', $inputModalities, true);
        $hasAudio = in_array('audio', $inputModalities, true);
        $hasFile = in_array('file', $inputModalities, true) || in_array('document', $inputModalities, true);

        if ($hasImage) {
            $supportedValues[] = [ModalityEnum::text(), ModalityEnum::image()];
        }

        if ($hasAudio) {
            $supportedValues[] = [ModalityEnum::text(), ModalityEnum::audio()];
        }

        if ($hasImage && $hasAudio) {
            $supportedValues[] = [ModalityEnum::text(), ModalityEnum::image(), ModalityEnum::audio()];
        }

        if ($hasFile) {
            $supportedValues[] = [ModalityEnum::text(), ModalityEnum::document()];
        }

        if ($hasFile && $hasImage) {
            $supportedValues[] = [ModalityEnum::text(), ModalityEnum::image(), ModalityEnum::document()];
        }

        return new SupportedOption(OptionEnum::inputModalities(), $supportedValues);
    }

    /**
     * Checks whether an OpenRouter model supports a parameter.
     *
     * @since 0.1.0
     *
     * @param list<string> $supportedParameters Supported parameter names.
     * @param string       $parameter           Parameter to check.
     * @return bool True if supported.
     */
    protected function supportsParameter(array $supportedParameters, string $parameter): bool
    {
        if ($supportedParameters === []) {
            return false;
        }

        return in_array($parameter, $supportedParameters, true);
    }

    /**
     * Checks whether an OpenRouter model supports any parameter from the given list.
     *
     * @since 0.1.0
     *
     * @param list<string> $supportedParameters Supported parameter names.
     * @param list<string> $parameters          Candidate parameters.
     * @return bool True if any parameter is supported.
     */
    protected function supportsAnyParameter(array $supportedParameters, array $parameters): bool
    {
        if ($supportedParameters === []) {
            return false;
        }

        foreach ($parameters as $parameter) {
            if (in_array($parameter, $supportedParameters, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Callback function for sorting models by ID, to be used with `usort()`.
     *
     * This method expresses preferences for certain models or model families within the provider by putting them
     * earlier in the sorted list. The objective is not to be opinionated about which models are better, but to ensure
     * that more commonly used, more recent, or flagship models are presented first to users.
     *
     * @since 0.1.0
     *
     * @param ModelMetadata $a First model.
     * @param ModelMetadata $b Second model.
     * @return int Comparison result.
     */
    protected function modelSortCallback(ModelMetadata $a, ModelMetadata $b): int
    {
        $aId = $a->getId();
        $bId = $b->getId();

        // Prefer non-preview models over preview models.
        if (str_contains($aId, '-preview') && !str_contains($bId, '-preview')) {
            return 1;
        }
        if (str_contains($bId, '-preview') && !str_contains($aId, '-preview')) {
            return -1;
        }

        // Prefer a few common providers first for a friendlier default ordering.
        $aVendorRank = $this->getVendorRank($aId);
        $bVendorRank = $this->getVendorRank($bId);
        if ($aVendorRank !== $bVendorRank) {
            return $aVendorRank <=> $bVendorRank;
        }

        // Prefer non-free aliases ahead of ':free' aliases to reduce fallback surprises.
        if (str_ends_with($aId, ':free') && !str_ends_with($bId, ':free')) {
            return 1;
        }
        if (str_ends_with($bId, ':free') && !str_ends_with($aId, ':free')) {
            return -1;
        }

        // Fallback: Sort alphabetically.
        return strcmp($a->getId(), $b->getId());
    }

    /**
     * Returns a vendor ranking for display ordering.
     *
     * @since 0.1.0
     *
     * @param string $modelId Model identifier.
     * @return int Rank (lower is preferred).
     */
    protected function getVendorRank(string $modelId): int
    {
        if (str_starts_with($modelId, 'openai/')) {
            return 0;
        }
        if (str_starts_with($modelId, 'anthropic/')) {
            return 1;
        }
        if (str_starts_with($modelId, 'google/')) {
            return 2;
        }
        if (str_starts_with($modelId, 'meta-llama/')) {
            return 3;
        }
        if (str_starts_with($modelId, 'mistralai/')) {
            return 4;
        }

        return 10;
    }
}
