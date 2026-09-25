=== Zactonz AI Connector for OpenRouter ===
Contributors:      zactonz
Tags:              connector, openrouter, ai, ai-client, llm
Requires at least: 7.0
Tested up to:      7.1
Stable tag:        1.0.0
Requires PHP:      7.4
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Adds an OpenRouter connector to Settings > Connectors for the WordPress AI Client, with access to hundreds of routed models.

== Description ==

This plugin by [Zactonz Technologies](https://zactonz.com/) adds [OpenRouter](https://openrouter.ai/) as an AI provider for the WordPress AI Client. It is built for the WordPress **Settings > Connectors** screen and for the plugin directory connector search, so a site owner who needs OpenRouter can find it, install it, paste a key, and have every AI feature in WordPress start using it.

OpenRouter is a routing gateway that exposes hundreds of models from dozens of labs behind one OpenAI-compatible API and one bill. This connector brings that catalogue into WordPress: every model your key can reach is discovered automatically, with vision, tool calling, and structured output detected per model from the OpenRouter catalogue rather than guessed.

**Disclaimer:** this connector is developed by Zactonz Technologies and is not affiliated with, endorsed by, or sponsored by OpenRouter. OpenRouter is a trademark of OpenRouter, Inc.

**Features:**

* Text generation with every OpenRouter chat model your credentials can reach
* Automatic model discovery, with capabilities read from the provider rather than guessed
* Separate default model per capability, or automatic selection by the WordPress AI Client
* Vision input on models that accept images
* Tool calling on models that report function support
* Structured output with a JSON schema on models that support it
* Reasoning effort control for the default text model
* Cancellable server-sent event streaming for content, reasoning, and tool-call chunks
* Configurable request timeouts for text, embeddings, and images
* API key supplied by a PHP constant or an environment variable instead of the database
* An API base URL override for a proxy or a private gateway
* Redacted diagnostics and a WordPress Site Health connectivity test

**Requirements:**

* PHP 7.4 or higher
* WordPress 7.0 or higher
* An OpenRouter account and API key

== External services ==

This plugin sends requests to OpenRouter, a third-party service, and does nothing without it. Requests go to the OpenRouter API at `https://openrouter.ai/api/v1`, or to the API base URL you set in the connector settings.

**What is sent and when:** the API key you configure, and the prompts, images, tool definitions and generation settings that a WordPress AI feature passes to the AI Client, each time such a feature runs a request through this connector. The connector also requests the model catalogue when its settings screen loads, when a WordPress AI feature asks which models are available, and when you run diagnostics or the Site Health test. It also sends your site URL and site name in the HTTP-Referer and X-Title headers OpenRouter uses for attribution, so requests appear under your site in the OpenRouter activity view. No other data is sent.

**Service provider:** OpenRouter ([terms of service](https://openrouter.ai/terms), [privacy policy](https://openrouter.ai/privacy)).

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/zactonz-ai-connector-for-openrouter/`, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Settings > Connectors** and add your OpenRouter API key.
4. Go to **Settings > OpenRouter** to review the discovered models and choose a default model per capability.

== Frequently Asked Questions ==

= Where do I get an API key? =

Create one on the OpenRouter dashboard: https://openrouter.ai/keys

= Can I keep the key out of the database? =

Yes. Define `OPENROUTER_API_KEY` in `wp-config.php`, or set an environment variable of the same name. When either is present the connector uses it, the settings field is disabled, and nothing is written to the database.

= Which models will I see? =

Models are listed from the OpenRouter catalogue your API key can reach. The connector reads the capabilities the provider reports for each model, so a model only appears as vision, tool-calling, structured-output, or embedding capable when OpenRouter says it is.

= Does this replace the connectors in WordPress core? =

No. WordPress core ships its own connectors. This plugin adds OpenRouter alongside them, and you can keep several connectors active at once and pick a default model per capability.

= Is my key ever displayed or logged? =

No. The key is stored in its own option, is never rendered back into the settings screen, and is excluded from the diagnostics report and from Site Health debug information.

== Changelog ==

= 1.0.0 =

* Initial release.
* Streams text responses token by token through the WordPress HTTP API, aggregating content, reasoning, tool calls, and usage.

== Upgrade Notice ==

= 1.0.0 =

Initial release.
