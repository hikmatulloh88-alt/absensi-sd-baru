# Magic Edit AI

Magic Edit AI is an isolated photo-editing module for staff accounts. It adds a brush-selection workspace, text instructions, undo/redo, and downloadable AI edit variants.

## Files added

- \`modules/magic_edit/index.php\` — editor page
- \`modules/magic_edit/edit.php\` — authenticated server-side image-edit endpoint
- \`assets/css/magic-edit.css\` — isolated editor styles
- \`assets/js/magic-edit.js\` — upload, brush, mask, undo/redo, and results UI
- \`config/magic_edit.example.php\` — configuration template

## Configure the API key

1. Copy \`config/magic_edit.example.php\` to \`config/magic_edit.local.php\` on the server.
2. Edit \`config/magic_edit.local.php\` and replace the placeholder value with the OpenAI API key.
3. Keep \`magic_edit.local.php\` on the server only. It is ignored by Git through the existing \`config/*.local.php\` rule.
4. Alternatively, provide the key as the server environment variable \`OPENAI_API_KEY\`.

The editor never sends the API key to the browser. The PHP endpoint uses the key server-side.

## PHP and server requirements

- PHP 8.0 or later
- PHP cURL extension enabled
- PHP Fileinfo extension enabled
- Apache access to the \`config/.htaccess\` protection rule
- A valid OpenAI API key with image editing access and available API billing/credits

For XAMPP, verify that \`extension=curl\` and \`extension=fileinfo\` are enabled in the active \`php.ini\`, then restart Apache.

Suggested PHP upload/runtime values:

- \`upload_max_filesize = 20M\`
- \`post_max_size = 25M\`
- \`max_execution_time = 300\`
- \`memory_limit = 256M\`

## Use

After login as an administrator or teacher, open:

\`/modules/magic_edit/index.php\`

Upload a JPG, PNG, or WebP photo. The browser optimizes it to a maximum 2048-pixel edge before sending it to the AI service. Paint over the part to change; the transparent mask indicates the selected edit area. Use the eraser to remove parts of the selection. If no area is selected, the prompt is applied to the whole image.

The default model is \`gpt-image-2.5-sunburst\`. The output is returned to the browser for comparison and download. This module does not save source or generated photos into the school database or permanent server storage.

## Safety and privacy notes

- Access is available to authenticated administrator and teacher sessions; student sessions are denied.
- A CSRF token is checked for edit requests.
- The API key is held in server configuration, not browser JavaScript.
- Uploaded photos are sent to the configured AI provider for the requested edit. Do not upload photos unless the school has an appropriate legal basis and required permission for that processing.
- Generated images are returned to the current browser session and are not archived by this module.
