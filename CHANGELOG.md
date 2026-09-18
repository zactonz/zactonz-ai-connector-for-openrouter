# Changelog

## 1.0.0 - 2026-09-17

- Initial release.
- Registers OpenRouter with the WordPress AI Client and the Settings > Connectors screen.
- Discovers models from the provider and advertises only the capabilities the provider reports.
- Adds per-capability default model selection, reasoning effort, configurable timeouts, and an API base URL override.
- Adds a redacted diagnostics report and a WordPress Site Health connectivity test.
- Supports an API key supplied by a PHP constant or an environment variable.
