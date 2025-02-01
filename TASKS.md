# Admin Settings Page Restoration Plan

## 1. API Settings Section
### OpenAI Configuration
- [x] Add OpenAI API Key field
- [x] ~~Add OpenAI Endpoint field~~ (Converted to constant: https://api.openai.com/v1/)
- [x] Implement OpenAI model fetching via API (Added proper class loading and error handling)
- [x] Add OpenAI model dropdown with placeholder and loading states

### Deepseek Configuration
- [x] Add Deepseek API Key field (Added with description)
- [x] Add Deepseek Endpoint field (Added with default value)
- [x] Implement Deepseek model fetching via API (Using Deepseek_Service class)
- [x] Add Deepseek model dropdown (Dynamically populated when API key provided)

### Gemini Configuration
- [x] Add Gemini API Key field (Added with description)
- [x] Add Gemini model dropdown with all required options:
  * gemini-2.0-flash-exp
  * gemini-1.5-flash
  * gemini-1.5-flash-8b
  * gemini-1.5-pro
  * gemini-1.0-pro
  * text-embedding-004

## 2. Search Configuration Section
### Algolia Settings
- [ ] Add Algolia Application ID field
- [ ] Add Algolia Search API Key field
- [ ] Add Algolia Admin API Key field

### Google Search Settings
- [ ] Add Google Search API Key field
- [ ] Add Search Engine ID field

## 3. Display Settings Section
### Widget Display Location
- [ ] Add checkbox for "All Pages"
- [ ] Add checkbox for "Homepage Only"
- [ ] Add checkbox for "Posts"
- [ ] Add checkbox for "Pages"
- [ ] Add checkbox for "Products"
- [ ] Add Debug Logging Switch

## 4. Lead Collection Settings Section
### Basic Settings
- [ ] Add "Enable Lead Collection" switch
- [ ] Add "Ask for Contact Info" timing selector:
  * At the start of conversation
  * Other timing options

### FluentCRM Integration
- [ ] Add FluentCRM list dropdown
- [ ] Add FluentCRM tag dropdown
- [ ] Add Contact Status field

### Lead Collection UI
- [ ] Add Lead Collection Heading field
- [ ] Add Lead Collection Description field

## Implementation Steps
1. First restore basic form structure
2. Implement field rendering methods
3. Add API endpoints and credentials
4. Implement model fetching functionality
5. Add lead collection settings
6. Test and validate each section
7. Ensure proper sanitization of all fields
8. Implement proper error handling
9. Add inline documentation
10. Test integration with FluentCRM

## Files to Modify
1. admin/class-admin-settings.php
   - Add new fields
   - Implement field rendering methods
   - Add sanitization for new fields
   - Implement AJAX handlers for model fetching

2. admin/views/settings-page.php
   - Update form structure
   - Add new sections
   - Improve layout

3. admin/js/admin-settings.js
   - Add dynamic model fetching
   - Implement field visibility toggling
   - Add validation

4. admin/css/admin-settings.css
   - Update styles for new sections
   - Improve responsive design

## Testing Plan
1. Test each API integration separately
2. Verify model fetching for each provider
3. Test lead collection flow
4. Verify FluentCRM integration
5. Test all form validation
6. Verify settings are saved correctly
7. Test display locations functionality
8. Verify debug logging

## Note
All changes should preserve existing functionality while adding missing features. Each feature should be implemented incrementally and tested thoroughly before moving to the next one.