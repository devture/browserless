# Browserless

This is a library for interacting with the [Browserless.io](https://www.browserless.io) APIs.

For the time being, this library only supports these APIs:

- [/pdf](https://docs.browserless.io/docs/pdf.html) - for generating PDFs from a URL or inline HTML (like [wkhtmltopdf](https://wkhtmltopdf.org/), but better -- more up-to-date browser engine, etc.)


## Prerequisites

You either need to use your own self-hosted Browserless instance (see how to do it with [Docker](https://docs.browserless.io/docker/quickstart)) or their hosted offering (see [Pricing](https://www.browserless.io/pricing/)).

You could use the following `compose.yml` setup:

```yaml
version: '2.1'

services:
  browserless:
    image: ghcr.io/browserless/chromium:v2.23.0
    restart: unless-stopped
    # Matches the owner (`blessuser:blessuser`) of `/usr/src/app`
    user: 999:999
    environment:
      CONCURRENT: 10
      TOKEN: SOME_TOKEN_HERE
    # Not exposing the port is recommended, if PHP is running in a container alongisde this one
    ports:
      - "127.0.0.1:3000:3000"
    tmpfs:
      - /tmp
```


## Usage

### Creating a Browserless API client

```php
$browserlessApiUrl = 'http://localhost:3000'; // Or 'http://browserless:3000', etc.
$browserlessToken = 'SOME_TOKEN_HERE';
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
```

## Alternatives

- [gosuperscript/browserless-php](https://packagist.org/packages/gosuperscript/browserless-php)

- the [SynergiTech/chrome-pdf-php](https://github.com/SynergiTech/chrome-pdf-php) library can also render PDFs via Browserless

- [wkhtmltopdf](https://wkhtmltopdf.org/) invoked via [knplabs/knp-snappy](https://github.com/KnpLabs/snappy)
