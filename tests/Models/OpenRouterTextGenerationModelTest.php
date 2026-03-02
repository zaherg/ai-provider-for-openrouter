<?php

declare(strict_types=1);

namespace Tests\Models;

use Tests\TestCase;
use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;
use WordPress\AiClient\Tools\DTO\FunctionCall;
use WordPress\AiClient\Tools\DTO\FunctionDeclaration;
use WordPress\AiClient\Tools\DTO\FunctionResponse;
use WordPress\AiClient\Tools\DTO\WebSearch;
use Zaherg\OpenRouterAiProvider\Models\OpenRouterTextGenerationModel;

/**
 * @phpstan-type PrepareParamsCallable callable(list<Message>): array<string, mixed>
 */
class OpenRouterTextGenerationModelTest extends TestCase
{
    private OpenRouterTextGenerationModel $model;
    private ModelMetadata $modelMetadata;
    private ProviderMetadata $providerMetadata;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modelMetadata = new ModelMetadata(
            'openai/gpt-4',
            'GPT-4',
            [CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory()],
            []
        );

        $this->providerMetadata = new ProviderMetadata(
            'openrouter',
            'OpenRouter',
            ProviderTypeEnum::cloud(),
            'https://openrouter.ai/settings/keys',
            RequestAuthenticationMethod::apiKey()
        );

        $this->model = new OpenRouterTextGenerationModel($this->modelMetadata, $this->providerMetadata);
    }

    // ---------------------------------------------------------------
    // Helper: call protected methods via reflection
    // ---------------------------------------------------------------

    /**
     * @param list<Message> $prompt
     * @return array<string, mixed>
     */
    private function callPrepareParams(array $prompt): array
    {
        $reflection = new \ReflectionMethod($this->model, 'prepareGenerateTextParams');
        $reflection->setAccessible(true);
        /** @var array<string, mixed> */
        $result = $reflection->invoke($this->model, $prompt);
        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function callGetMessageInputItem(Message $message): ?array
    {
        $reflection = new \ReflectionMethod($this->model, 'getMessageInputItem');
        $reflection->setAccessible(true);
        /** @var array<string, mixed>|null */
        $result = $reflection->invoke($this->model, $message);
        return $result;
    }

    private function callGetMessageRoleString(MessageRoleEnum $role): string
    {
        $reflection = new \ReflectionMethod($this->model, 'getMessageRoleString');
        $reflection->setAccessible(true);
        /** @var string */
        $result = $reflection->invoke($this->model, $role);
        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function callGetMessagePartData(MessagePart $part, MessageRoleEnum $role): array
    {
        $reflection = new \ReflectionMethod($this->model, 'getMessagePartData');
        $reflection->setAccessible(true);
        /** @var array<string, mixed> */
        $result = $reflection->invoke($this->model, $part, $role);
        return $result;
    }

    /**
     * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult
     */
    private function callParseResponse(Response $response): \WordPress\AiClient\Results\DTO\GenerativeAiResult
    {
        $reflection = new \ReflectionMethod($this->model, 'parseResponseToGenerativeAiResult');
        $reflection->setAccessible(true);
        /** @var \WordPress\AiClient\Results\DTO\GenerativeAiResult */
        $result = $reflection->invoke($this->model, $response);
        return $result;
    }

    private function callParseStatus(string $status, bool $hasFunctionCalls): FinishReasonEnum
    {
        $reflection = new \ReflectionMethod($this->model, 'parseStatusToFinishReason');
        $reflection->setAccessible(true);
        /** @var FinishReasonEnum */
        $result = $reflection->invoke($this->model, $status, $hasFunctionCalls);
        return $result;
    }

    /**
     * @param list<Message> $messages
     */
    private function callValidateMessages(array $messages): void
    {
        $reflection = new \ReflectionMethod($this->model, 'validateMessages');
        $reflection->setAccessible(true);
        $reflection->invoke($this->model, $messages);
    }

    // ---------------------------------------------------------------
    // Param preparation tests
    // ---------------------------------------------------------------

    public function test_prepare_params_basic(): void
    {
        $messages = [new Message(MessageRoleEnum::user(), [new MessagePart('Hello')])];
        $params = $this->callPrepareParams($messages);

        $this->assertSame('openai/gpt-4', $params['model']);
        $this->assertArrayHasKey('input', $params);
        /** @var list<array<string, mixed>> $input */
        $input = $params['input'];
        $this->assertCount(1, $input);
        $this->assertSame('user', $input[0]['role']);
    }

    public function test_prepare_params_with_system_instruction(): void
    {
        $config = \WordPress\AiClient\Providers\Models\DTO\ModelConfig::fromArray([
            'systemInstruction' => 'You are helpful.',
        ]);
        $this->model->setConfig($config);

        $messages = [new Message(MessageRoleEnum::user(), [new MessagePart('Hi')])];
        $params = $this->callPrepareParams($messages);

        $this->assertSame('You are helpful.', $params['instructions']);
    }

    public function test_prepare_params_with_max_tokens_temperature_top_p(): void
    {
        $config = \WordPress\AiClient\Providers\Models\DTO\ModelConfig::fromArray([
            'maxTokens' => 100,
            'temperature' => 0.5,
            'topP' => 0.9,
        ]);
        $this->model->setConfig($config);

        $messages = [new Message(MessageRoleEnum::user(), [new MessagePart('Hi')])];
        $params = $this->callPrepareParams($messages);

        $this->assertSame(100, $params['max_output_tokens']);
        $this->assertSame(0.5, $params['temperature']);
        $this->assertSame(0.9, $params['top_p']);
    }

    public function test_prepare_params_with_json_schema_output(): void
    {
        $schema = ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]];
        $config = \WordPress\AiClient\Providers\Models\DTO\ModelConfig::fromArray([
            'outputMimeType' => 'application/json',
            'outputSchema' => $schema,
        ]);
        $this->model->setConfig($config);

        $messages = [new Message(MessageRoleEnum::user(), [new MessagePart('Hi')])];
        $params = $this->callPrepareParams($messages);

        /** @var array<string, mixed> $text */
        $text = $params['text'];
        /** @var array<string, mixed> $format */
        $format = $text['format'];
        $this->assertSame('json_schema', $format['type']);
        $this->assertSame($schema, $format['schema']);
        $this->assertTrue($format['strict']);
    }

    public function test_prepare_params_with_function_declarations(): void
    {
        $config = \WordPress\AiClient\Providers\Models\DTO\ModelConfig::fromArray([
            'functionDeclarations' => [
                ['name' => 'get_weather', 'description' => 'Get weather', 'parameters' => ['type' => 'object']],
            ],
        ]);
        $this->model->setConfig($config);

        $messages = [new Message(MessageRoleEnum::user(), [new MessagePart('Hi')])];
        $params = $this->callPrepareParams($messages);

        $this->assertArrayHasKey('tools', $params);
        /** @var list<array<string, mixed>> $tools */
        $tools = $params['tools'];
        $this->assertSame('function', $tools[0]['type']);
        $this->assertSame('get_weather', $tools[0]['name']);
    }

    public function test_prepare_params_with_web_search(): void
    {
        $config = \WordPress\AiClient\Providers\Models\DTO\ModelConfig::fromArray([
            'webSearch' => [],
        ]);
        $this->model->setConfig($config);

        $messages = [new Message(MessageRoleEnum::user(), [new MessagePart('Hi')])];
        $params = $this->callPrepareParams($messages);

        $this->assertArrayHasKey('tools', $params);
        /** @var list<array<string, mixed>> $tools */
        $tools = $params['tools'];
        $this->assertSame('web_search', $tools[0]['type']);
    }

    public function test_prepare_params_with_custom_options(): void
    {
        $config = \WordPress\AiClient\Providers\Models\DTO\ModelConfig::fromArray([
            'customOptions' => ['presence_penalty' => 0.6],
        ]);
        $this->model->setConfig($config);

        $messages = [new Message(MessageRoleEnum::user(), [new MessagePart('Hi')])];
        $params = $this->callPrepareParams($messages);

        $this->assertSame(0.6, $params['presence_penalty']);
    }

    public function test_prepare_params_custom_option_conflict_throws(): void
    {
        $config = \WordPress\AiClient\Providers\Models\DTO\ModelConfig::fromArray([
            'customOptions' => ['model' => 'override'],
        ]);
        $this->model->setConfig($config);

        $messages = [new Message(MessageRoleEnum::user(), [new MessagePart('Hi')])];

        $this->expectException(InvalidArgumentException::class);
        $this->callPrepareParams($messages);
    }

    // ---------------------------------------------------------------
    // Message handling tests
    // ---------------------------------------------------------------

    public function test_message_role_mapping(): void
    {
        $this->assertSame('user', $this->callGetMessageRoleString(MessageRoleEnum::user()));
        $this->assertSame('assistant', $this->callGetMessageRoleString(MessageRoleEnum::model()));
    }

    public function test_text_message_part_user(): void
    {
        $part = new MessagePart('Hello');
        $data = $this->callGetMessagePartData($part, MessageRoleEnum::user());

        $this->assertSame('input_text', $data['type']);
        $this->assertSame('Hello', $data['text']);
    }

    public function test_text_message_part_model(): void
    {
        $part = new MessagePart('Hi there');
        $data = $this->callGetMessagePartData($part, MessageRoleEnum::model());

        $this->assertSame('output_text', $data['type']);
        $this->assertSame('Hi there', $data['text']);
    }

    public function test_function_call_part(): void
    {
        $fc = new FunctionCall('call_1', 'get_weather', ['city' => 'NYC']);
        $part = new MessagePart($fc);

        $data = $this->callGetMessagePartData($part, MessageRoleEnum::model());

        $this->assertSame('function_call', $data['type']);
        $this->assertSame('call_1', $data['call_id']);
        $this->assertSame('get_weather', $data['name']);
        $this->assertSame('{"city":"NYC"}', $data['arguments']);
    }

    public function test_function_response_part(): void
    {
        $fr = new FunctionResponse('call_1', 'get_weather', ['temp' => 72]);
        $part = new MessagePart($fr);

        $data = $this->callGetMessagePartData($part, MessageRoleEnum::user());

        $this->assertSame('function_call_output', $data['type']);
        $this->assertSame('call_1', $data['call_id']);
        $this->assertSame('{"temp":72}', $data['output']);
    }

    public function test_function_call_returns_top_level_item(): void
    {
        $fc = new FunctionCall('call_1', 'get_weather', ['city' => 'NYC']);
        $message = new Message(MessageRoleEnum::model(), [new MessagePart($fc)]);

        $item = $this->callGetMessageInputItem($message);
        $this->assertNotNull($item);

        // Should be a top-level function_call item, not wrapped in role+content.
        $this->assertSame('function_call', $item['type']);
        $this->assertArrayNotHasKey('role', $item);
    }

    public function test_function_response_returns_top_level_item(): void
    {
        $fr = new FunctionResponse('call_1', 'get_weather', ['temp' => 72]);
        $message = new Message(MessageRoleEnum::user(), [new MessagePart($fr)]);

        $item = $this->callGetMessageInputItem($message);
        $this->assertNotNull($item);

        $this->assertSame('function_call_output', $item['type']);
        $this->assertArrayNotHasKey('role', $item);
    }

    public function test_validate_messages_rejects_multi_part_function_call(): void
    {
        // We can't use Message constructor directly since it validates roles.
        // Use reflection to bypass and test validateMessages.
        $fc = new FunctionCall('call_1', 'get_weather', null);
        $fcPart = new MessagePart($fc);
        $textPart = new MessagePart('some text');

        // Build a message with multiple parts including a function call.
        // The Message constructor for model role may not allow this, so let's
        // create a minimal mock.
        $message = $this->createMock(Message::class);
        $message->method('getParts')->willReturn([$fcPart, $textPart]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Function call parts must be the only part');
        $this->callValidateMessages([$message]);
    }

    // ---------------------------------------------------------------
    // Response parsing tests
    // ---------------------------------------------------------------

    public function test_parse_simple_text_response(): void
    {
        $responseBody = (string) json_encode([
            'id' => 'resp_001',
            'status' => 'completed',
            'output' => [
                [
                    'type' => 'message',
                    'role' => 'assistant',
                    'content' => [
                        ['type' => 'output_text', 'text' => 'Hello!'],
                    ],
                ],
            ],
            'usage' => [
                'input_tokens' => 10,
                'output_tokens' => 5,
                'total_tokens' => 15,
            ],
        ]);

        $response = new Response(200, ['Content-Type' => 'application/json'], $responseBody);
        $result = $this->callParseResponse($response);

        $this->assertSame('resp_001', $result->getId());
        $candidates = $result->getCandidates();
        $this->assertCount(1, $candidates);
        $this->assertSame('Hello!', $candidates[0]->getMessage()->getParts()[0]->getText());
    }

    public function test_parse_function_call_response(): void
    {
        $responseBody = (string) json_encode([
            'id' => 'resp_002',
            'status' => 'completed',
            'output' => [
                [
                    'type' => 'function_call',
                    'call_id' => 'call_abc',
                    'name' => 'get_weather',
                    'arguments' => '{"city":"NYC"}',
                ],
            ],
            'usage' => ['input_tokens' => 5, 'output_tokens' => 3, 'total_tokens' => 8],
        ]);

        $response = new Response(200, ['Content-Type' => 'application/json'], $responseBody);
        $result = $this->callParseResponse($response);

        $candidates = $result->getCandidates();
        $this->assertCount(1, $candidates);
        $part = $candidates[0]->getMessage()->getParts()[0];
        $this->assertTrue($part->getType()->isFunctionCall());
        $functionCall = $part->getFunctionCall();
        $this->assertNotNull($functionCall);
        $this->assertSame('get_weather', $functionCall->getName());
        $this->assertSame(['city' => 'NYC'], $functionCall->getArgs());
    }

    public function test_parse_response_with_usage(): void
    {
        $responseBody = (string) json_encode([
            'id' => 'resp_003',
            'status' => 'completed',
            'output' => [
                [
                    'type' => 'message',
                    'role' => 'assistant',
                    'content' => [['type' => 'output_text', 'text' => 'Ok']],
                ],
            ],
            'usage' => ['input_tokens' => 20, 'output_tokens' => 10, 'total_tokens' => 30],
        ]);

        $response = new Response(200, ['Content-Type' => 'application/json'], $responseBody);
        $result = $this->callParseResponse($response);

        $this->assertSame(20, $result->getTokenUsage()->getPromptTokens());
        $this->assertSame(10, $result->getTokenUsage()->getCompletionTokens());
        $this->assertSame(30, $result->getTokenUsage()->getTotalTokens());
    }

    public function test_parse_response_without_usage(): void
    {
        $responseBody = (string) json_encode([
            'id' => 'resp_004',
            'status' => 'completed',
            'output' => [
                [
                    'type' => 'message',
                    'role' => 'assistant',
                    'content' => [['type' => 'output_text', 'text' => 'Ok']],
                ],
            ],
        ]);

        $response = new Response(200, ['Content-Type' => 'application/json'], $responseBody);
        $result = $this->callParseResponse($response);

        $this->assertSame(0, $result->getTokenUsage()->getPromptTokens());
        $this->assertSame(0, $result->getTokenUsage()->getCompletionTokens());
        $this->assertSame(0, $result->getTokenUsage()->getTotalTokens());
    }

    public function test_parse_status_completed(): void
    {
        $reason = $this->callParseStatus('completed', false);
        $this->assertTrue($reason->isStop());
    }

    public function test_parse_status_incomplete(): void
    {
        $reason = $this->callParseStatus('incomplete', false);
        $this->assertTrue($reason->isLength());
    }

    public function test_parse_status_failed(): void
    {
        $reason = $this->callParseStatus('failed', false);
        $this->assertTrue($reason->isError());
    }

    public function test_parse_status_with_function_calls(): void
    {
        $reason = $this->callParseStatus('completed', true);
        $this->assertTrue($reason->isToolCalls());
    }

    // ---------------------------------------------------------------
    // Integration test (end-to-end with mocked HTTP)
    // ---------------------------------------------------------------

    public function test_generate_text_result(): void
    {
        $responseBody = (string) json_encode([
            'id' => 'resp_e2e',
            'status' => 'completed',
            'output' => [
                [
                    'type' => 'message',
                    'role' => 'assistant',
                    'content' => [
                        ['type' => 'output_text', 'text' => 'Test response'],
                    ],
                ],
            ],
            'usage' => ['input_tokens' => 5, 'output_tokens' => 2, 'total_tokens' => 7],
        ]);

        $httpResponse = new Response(200, ['Content-Type' => 'application/json'], $responseBody);

        $transporter = $this->createMock(\WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface::class);
        $transporter->method('send')->willReturn($httpResponse);

        $auth = $this->createMock(\WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface::class);
        $auth->method('authenticateRequest')->willReturnArgument(0);

        $this->model->setHttpTransporter($transporter);
        $this->model->setRequestAuthentication($auth);

        $prompt = [new Message(MessageRoleEnum::user(), [new MessagePart('Hello')])];
        $result = $this->model->generateTextResult($prompt);

        $this->assertSame('resp_e2e', $result->getId());
        $this->assertSame('Test response', $result->toText());
        $this->assertSame(5, $result->getTokenUsage()->getPromptTokens());
    }
}
