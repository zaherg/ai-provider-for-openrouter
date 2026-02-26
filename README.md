# AI Provider for OpenRouter

An AI Provider for OpenRouter for the [PHP AI Client](https://github.com/WordPress/php-ai-client) SDK. Works as both a Composer package and a WordPress plugin.

This package is based on the [WordPress AI Provider for OpenAI](https://github.com/WordPress/ai-provider-for-openai) package and adapts its provider implementation for OpenRouter.

## Requirements

- PHP 7.4 or higher
- When using with WordPress, targets WordPress 6.9+
- Requires the [wordpress/php-ai-client](https://github.com/WordPress/php-ai-client) SDK/plugin

## Installation

### As a Composer Package

```bash
composer require zaher/ai-provider-for-openrouter
```

### As a WordPress Plugin

1. Download the plugin files
2. Upload to `/wp-content/plugins/ai-provider-for-openrouter/`
3. Ensure the PHP AI Client plugin is installed and activated
4. Activate the plugin through the WordPress admin

## Usage

### With WordPress

The provider automatically registers itself with the PHP AI Client on the `init` hook. Simply ensure both plugins are active and configure your API key:

```php
// Set your OpenRouter API key (or use the OPENROUTER_API_KEY environment variable)
putenv('OPENROUTER_API_KEY=your-api-key');

// Use the provider
$result = AiClient::prompt('Hello, world!')
    ->usingProvider('openrouter')
    ->generateTextResult();
```

### As a Standalone Package

```php
use WordPress\AiClient\AiClient;
use Zaherg\OpenRouterAiProvider\Provider\OpenRouterProvider;

// Register the provider
$registry = AiClient::defaultRegistry();
$registry->registerProvider(OpenRouterProvider::class);

// Set your API key
putenv('OPENROUTER_API_KEY=your-api-key');

// Generate text
$result = AiClient::prompt('Explain quantum computing')
    ->usingProvider('openrouter')
    ->generateTextResult();

echo $result->toText();
```

## Supported Models

Available models are dynamically discovered from the OpenRouter `/models` endpoint.

This package currently registers text-output models and uses the OpenRouter OpenAI-compatible `/responses` endpoint for text generation (including function/tool calls where supported by the selected model).

Image generation and text-to-speech are intentionally not implemented in this package yet because OpenRouter's image workflows differ from the OpenAI `/images/generations` endpoint used by the upstream OpenAI provider.

## Configuration

The provider uses the `OPENROUTER_API_KEY` environment variable for authentication. You can set this in your environment or via PHP:

```php
putenv('OPENROUTER_API_KEY=your-api-key');
```

OpenRouter also recommends optional attribution headers such as `HTTP-Referer` and `X-OpenRouter-Title`. If you need those, set them through your HTTP transport/request options in the PHP AI Client stack.

## License

GPL-2.0-or-later
