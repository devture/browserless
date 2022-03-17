# Browserless

This is a library for interacting with the [Browserless.io](https://www.browserless.io) APIs.

For the time being, this library only supports these APIs:

- [/pdf](https://docs.browserless.io/docs/pdf.html) - for generating PDFs from a URL or inline HTML (like [wkhtmltopdf](https://wkhtmltopdf.org/), but better -- more up-to-date browser engine, etc.)

- [/workspace](https://docs.browserless.io/docs/workspace.html) - for persisting (HTML and other) files into the Browserless workspace


## Prerequisites

You either need to use your own self-hosted Browserless instance (see how to do it with [Docker](https://docs.browserless.io/docs/docker-quickstart.html)) or their hosted offering (see [Pricing](https://www.browserless.io/pricing/)).

You could use the following `docker-compose.yml` setup:

```yaml
version: '2.1'

services:
  browserless:
    # Using a tag other than latest is recommended
    image: docker.io/browserless/chrome:latest
    restart: unless-stopped
    # Matches the owner (`blessuser:blessuser`) of `/usr/src/app`
    user: 999:999
    environment:
      MAX_CONCURRENT_SESSIONS: 10
      WORKSPACE_DIR: "/workspace"
      WORKSPACE_DELETE_EXPIRED: "true"
      # To render PDFs from HTML via a `file://` protocol (using `createPdfFromHtmlRequestUsingFileProtocol()`),
      # we enable ALLOW_FILE_PROTOCOL.
      # If you don't need this, it's better to disable it (remove the line below).
      ALLOW_FILE_PROTOCOL: "true"
      TOKEN: SOME_TOKEN_HERE
    # Not exposing the port is recommended, if PHP is running in a sidecar container
    ports:
      - "127.0.0.1:3000:3000"
    tmpfs:
      - /tmp
      - /workspace
      - /home/blessuser/.cache
```


## Usage

### Creating a Browserless API client

```php
$browserlessApiUrl = 'http://localhost:3000'; // Or 'http://browserless:3000', etc.
$browserlessToken = 'SOME_TOKEN_HERE'; // Can be null for unsecured instances
$browserlessTimeoutSeconds = 15;

$client = new \Devture\Component\Browserless\Client(
	new \GuzzleHttp\Client(),
	$browserlessApiUrl,
	$browserlessToken,
	$browserlessTimeoutSeconds,
);
```


### Generating a PDF from a URL

```php
$url = 'https://devture.com';

$pdfCreationRequest = new \Devture\Component\Browserless\Model\PdfCreationRequest();
$pdfCreationRequest->setUrl($url);
$pdfCreationRequest->setOptions([
	'printBackground' => true,
	'format' => 'A4',
	'landscape' => true,
]);

$pdfBytes = $client->createPdfFromRequest($pdfCreationRequest);
```


### Generating a PDF from inline HTML

```php
$html = '<html><body>Some <strong>HTML</strong> here</body></html>';

$pdfCreationRequest = new \Devture\Component\Browserless\Model\PdfCreationRequest();
$pdfCreationRequest->setHtml($html);
$pdfCreationRequest->setOptions([
	'printBackground' => true,
	'format' => 'A4',
	'margin' => [
		'top' => '20mm',
		'bottom' => '10mm',
		'left' => '10mm',
		'right' => '10mm',
	],
]);

$pdfBytes = $client->createPdfFromRequest($pdfCreationRequest);

// Alternatively, save this as a local workspace file and load it from there using the `file://` protocol.
// (This allows you to access other files you may have mounted on the filesystem).
// $pdfBytes = $client->createPdfFromHtmlRequestUsingFileProtocol($pdfCreationRequest);
```


### Generating a PDF from a bunch of workspace-saved files

This requires Browserless running with `ALLOW_FILE_PROTOCOL: "true"` (see the sample `docker-compose.yml` file above).

```php
// These calls save files into the Browserless workspace directory (e.g. `/workspace/<UUID>.<extension>`).
$workspaceFileLogo = $client->createWorkspaceFile(file_get_contents('/path/to/logo.jpg'), 'jpg');
$workspaceFileStyles = $client->createWorkspaceFile(file_get_contents('/path/to/styles.css'), 'css');

$html = '
<html>
	<head>
		<link rel="stylesheet" href="file://' . $workspaceFileStyles->getPath() . '" />
	</head>
	<body>
		<img src="' . $workspaceFileLogo->getPath() . '" alt="Logo" />

		Some <strong>HTML</strong> here
	</body>
</html>';

$pdfCreationRequest = new \Devture\Component\Browserless\Model\PdfCreationRequest();
$pdfCreationRequest->setHtml($html);
$pdfCreationRequest->setOptions([
	'printBackground' => true,
	'format' => 'A4',
]);

// We need to use `createPdfFromHtmlRequestUsingFileProtocol()` here,
// because we can only access files via the `file://` protocol
// if the HTML is also served from a `file://`-accessed file.
$pdfBytes = $client->createPdfFromHtmlRequestUsingFileProtocol($pdfCreationRequest);

// Optionally, clean up. Because we have `WORKSPACE_DELETE_EXPIRED` enabled,
// workspace files will be auto-cleaned at some point anyway, but..
$client->deleteWorkspaceFile($workspaceFileLogo);
$client->deleteWorkspaceFile($workspaceFileStyles);
```
