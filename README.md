# Website AI Assistant

[![License: GPL v2](https://img.shields.io/badge/License-GPL_v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)

**Plugin Name:** Website AI Assistant  
**Plugin URI:** https://github.com/bcardi0427/website-ai-assistant/  
**Description:** An AI-powered chat assistant for WordPress websites using Google's Gemini API  
**Version:** 3.0.0
**Author:** Gerald Haygood  
**Author URI:** https://github.com/bcardi0427/website-ai-assistant/  
**License:** GPL v2 or later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html  
**Text Domain:** website-ai-assistant  
**Domain Path:** /languages  

- [Website AI Assistant](#website-ai-assistant)
  - [Features](#features)
  - [Installation](#installation)
  - [Configuration](#configuration)
    - [Required API Keys](#required-api-keys)
    - [Settings](#settings)
  - [Usage](#usage)
  - [Security](#security)
  - [Contributing](#contributing)
  - [License](#license)
  - [Support](#support)
  - [Changelog](#changelog)
    - [3.0.0](#300)
    - [2.2.5](#225)
    - [2.2.0](#220)


## Features

- Multi-provider AI integration:
  - Google Gemini AI (with models like Gemini 2.0, 1.5 Flash, 1.5 Pro)
  - OpenAI (with dynamic model selection)
  - Deepseek (with configurable models and endpoint)
- Website-specific knowledge base
- Advanced lead generation and management:
  - Configurable form timing (immediate, after first message, after two messages, or at end)
  - Customizable form heading and description
  - Skip option for users
  - FluentCRM integration for automated lead management
- Customizable system prompts
- Search integration (Google Custom Search & Algolia)
- Privacy-focused design

## Installation

1. Download the plugin zip file
2. Go to WordPress Admin → Plugins → Add New → Upload Plugin
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure your API keys in the plugin settings

## Configuration

### Required API Keys

The plugin supports multiple AI providers. You'll need at least one of these API keys:

1. **Gemini API Key** - Get from Google AI Studio (default provider)
2. **OpenAI API Key** - Get from OpenAI dashboard
3. **Deepseek API Key** - Get from Deepseek platform

For search functionality (optional):
1. **Google Search API Key** - For Google Custom Search integration
2. **Algolia API Keys** - For Algolia search integration (requires app ID, search key, and admin key)

### Settings

- System message customization
- Lead Generation Configuration:
  - Enable/disable lead collection
  - Form timing options (immediate, after first message, after two messages, or end)
  - Customizable form heading and description
  - FluentCRM integration settings:
    - List selection
    - Tag assignment
    - Contact status configuration
- Display settings
- Privacy policy configuration
- Debug mode

## Usage

1. Configure your API keys in the plugin settings
2. Customize the chat interface appearance
3. Set up your system message and prompts
4. Configure lead collection settings
5. Add the chat widget to your website

## Security

- All API keys are stored securely in WordPress options
- Nonce verification for all AJAX requests
- Input/output sanitization
- HTTPS enforced for all API communication
- Debug mode controlled through settings

## Contributing

We welcome contributions! Please follow these guidelines:

1. Fork the repository
2. Create a feature branch
3. Submit a pull request
4. Follow WordPress coding standards

## License

This project is licensed under the GPL v2 License - see the [LICENSE](LICENSE) file for details.

## Support

For support, please open an issue on GitHub or contact the maintainers.

## Changelog

### 3.0.0
- Added multiple AI provider support:
  - Google Gemini AI integration:
    - Support for Gemini 2.0 Flash Experimental
    - Support for Gemini 1.5 Flash and Flash 8B
    - Support for Gemini 1.5 Pro and 1.0 Pro
  - OpenAI integration:
    - Dynamic model selection
    - Model caching with refresh option
    - Improved API key management
  - Deepseek integration:
    - Configurable API endpoint
    - Dynamic model loading
    - Enhanced error handling
- Enhanced lead generation functionality:
  - Added configurable form timing options (immediate, after first message, after two messages, or end)
  - Implemented dynamic FluentCRM integration with list and tag selection
  - Added customizable form heading and description
  - Added skip option for users who don't want to provide contact info
  - Improved form display controls and validation
- Improved FluentCRM integration:
  - Dynamic loading of lists and tags
  - Better error handling and validation
  - Enhanced admin UI for FluentCRM settings
- Improved search capabilities:
  - Added Algolia integration
  - Enhanced Google Custom Search support
- Added comprehensive settings sanitization
- Updated documentation with detailed provider and lead generation configuration

### 2.2.5
- Added enhanced security measures
- Improved error handling
- Updated documentation

### 2.2.0
- Initial public release
