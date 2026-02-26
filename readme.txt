=== AI Provider for OpenRouter ===
Contributors: zaher
Tags: ai, openrouter, gpt, artificial-intelligence, connector
Requires at least: 6.9
Tested up to: 7.0
Stable tag: 0.1.0
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI Provider for OpenRouter for the PHP AI Client SDK.

This package is based on the WordPress AI Provider for OpenAI package and adapts its provider implementation for OpenRouter.

== Description ==

This plugin provides OpenRouter integration for the PHP AI Client SDK. It enables WordPress sites to use OpenRouter-hosted models for text generation through the OpenAI-compatible Responses API.

**Features:**

* Text generation with GPT models
* Function calling support
* Automatic provider registration

Available models are dynamically discovered from the OpenRouter `/models` endpoint. This package currently registers text-output models only.

**Requirements:**

* PHP 7.4 or higher
* For WordPress 6.9, the [wordpress/php-ai-client](https://github.com/WordPress/php-ai-client) package must be installed
* For WordPress 7.0 and above, no additional changes are required
* OpenRouter API key constant (`OPENROUTER_API_KEY`)

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/ai-provider-for-openrouter/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure your OpenRouter API key in `wp-config.php` via the `OPENROUTER_API_KEY` constant

== Frequently Asked Questions ==

= How do I get an OpenRouter API key? =

Visit [OpenRouter](https://openrouter.ai/settings/keys) to create an API key.

= Does this plugin work without the PHP AI Client? =

No, this plugin requires the PHP AI Client plugin to be installed and activated. It provides the OpenRouter-specific implementation that the PHP AI Client uses.

== Changelog ==

= 0.1.0 =

* Initial OpenRouter package based on the WordPress OpenAI provider implementation
* Support for text generation models via the OpenRouter Responses API
* Function calling support
