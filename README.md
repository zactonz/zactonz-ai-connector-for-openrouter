# Zactonz AI Connector for OpenRouter

Adds an OpenRouter connector to Settings > Connectors for the WordPress AI Client, with access to hundreds of routed models.

OpenRouter is a routing gateway that exposes hundreds of models from dozens of labs behind one OpenAI-compatible API and one bill. This connector brings that catalogue into WordPress: every model your key can reach is discovered automatically, with vision, tool calling, and structured output detected per model from the OpenRouter catalogue rather than guessed.

This plugin is part of the Zactonz AI Connector family, a set of sibling plugins that add third-party AI providers to the WordPress AI Client. They share one codebase and one set of conventions, so every connector behaves the same way in the admin.

## Requirements

- WordPress 7.0 or higher
- PHP 7.4 or higher
- An OpenRouter account and API key

## Installation

1. Copy this directory to `wp-content/plugins/zactonz-ai-connector-for-openrouter/`.
2. Activate **Zactonz AI Connector for OpenRouter** from the Plugins screen.
3. Add the API key on **Settings > Connectors**, or on **Settings > OpenRouter**.

## Configuration

| Setting | Purpose |
| --- | --- |
| API key | The credential sent with every request. Stored in its own option and never rendered back. |
| Default models | One default per capability: text, and whichever of vision, images, embeddings, and tool calling this provider supports. |
| Reasoning effort | Applied to the default text model when the model reports reasoning support. |
| API base URL | Overrides the default endpoint for a proxy or private gateway. |
| Timeouts | Separate request windows for text, embeddings, and images. |

Defining `OPENROUTER_API_KEY` as a PHP constant in `wp-config.php` or as an environment variable overrides the stored key and disables the field.

## Model discovery

Models are listed from the OpenRouter catalogue your API key can reach.

Capabilities are read from the provider's own model listing rather than guessed. A model is advertised as vision, tool-calling, structured-output, or embedding capable only when the provider reports it, which keeps the WordPress model picker honest.

## Diagnostics

**Settings > OpenRouter** has a diagnostics button that reports the endpoint, HTTP status, latency, number of discovered models, any configured defaults missing from the current model list, and the AI Client version. Credentials never appear in the report. The same check is registered as a WordPress Site Health test.

## Development

```bash
composer install
composer lint
```

`composer lint` runs PHP_CodeSniffer against the WordPress standards, PHPStan at level 6, and the PHPUnit suite.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
