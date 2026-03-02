# AI Provider for OpenRouter

An AI Provider for OpenRouter for the [PHP AI Client](https://github.com/WordPress/php-ai-client) SDK. Works as both a Composer package and a WordPress plugin.

This package is based on the [WordPress AI Provider for OpenAI](https://github.com/WordPress/ai-provider-for-openai) package and adapts its provider implementation for OpenRouter.

## Requirements

- PHP 7.4 or higher
- When using with WordPress, targets WordPress 6.9+
- Requires the [wordpress/php-ai-client](https://github.com/WordPress/php-ai-client) SDK/plugin

## Installation

### As a Composer Package

First, add the GitHub repository to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/zaherg/ai-provider-for-openrouter"
        }
    ]
}
```

Then install the package:
```bash
composer require zaherg/ai-provider-for-openrouter:^0.1.0
```

### As a WordPress Plugin

1. Download the plugin files
2. Upload to `/wp-content/plugins/ai-provider-for-openrouter/`
3. Ensure the PHP AI Client plugin is installed and activated
4. Activate the plugin through the WordPress admin

## Usage

### With WordPress

The provider automatically registers itself with the PHP AI Client on the `init` hook. Simply ensure both plugins are active and configure your API key in `wp-config.php`:

```php
define('OPENROUTER_API_KEY', 'your-api-key');
define('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'); // Optional override

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

// Configure constants before using the provider (e.g. in wp-config.php for WordPress)
define('OPENROUTER_API_KEY', 'your-api-key');
define('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'); // Optional override

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

Configure the provider via PHP constants (typically in `wp-config.php`):

```php
define('OPENROUTER_API_KEY', 'your-api-key');
define('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'); // Optional override
```

OpenRouter also recommends optional attribution headers such as `HTTP-Referer` and `X-OpenRouter-Title`. If you need those, set them through your HTTP transport/request options in the PHP AI Client stack.

## License

GPL-2.0-or-later
