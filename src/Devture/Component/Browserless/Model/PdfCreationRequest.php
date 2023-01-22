<?php
namespace Devture\Component\Browserless\Model;

/**
 * PdfCreationRequest represents a request to the Browserless /pdf API.
 *
 * Most options (format, margin, headerTemplate, footerTemplate, printBackground, etc.) are passed
 * as part of the inner 'options' key/value hashmap. These are passed to Puppeteer's `page.pdf()`.
 * See: https://pptr.dev/#?product=Puppeteer&version=v13.5.1&show=api-pagepdfoptions
 *
 * Some options (html, url, emulateMedia, gotoOptions, etc) are passed at the top level, called context options.
 */
class PdfCreationRequest {

	private array $contextOptions = [];
	private array $options = [];

	public function getUrl(): ?string {
		return (string) $this->getContextOption('url', null);
	}

	public function setUrl(?string $value): static {
		if ($value === null) {
			unset($this->contextOptions['url']);
			return $this;
		}

		return $this->setContextOption('url', $value);
	}

	public function getHtml(): ?string {
		return (string) $this->getContextOption('html', null);
	}

	public function setHtml(?string $value): static {
		if ($value === null) {
			unset($this->contextOptions['html']);
			return $this;
		}

		return $this->setContextOption('html', $value);
	}

	public function getEmulatedMedia(): string {
		return (string) $this->getContextOption('emulateMedia', 'print');
	}

	public function setEmulatedMedia(string $value): static {
		return $this->setContextOption('emulateMedia', $value);
	}

	/**
	 * Sets the options passed to Puppeteer `page.pdf()`.
	 * See: https://pptr.dev/#?product=Puppeteer&version=v13.5.1&show=api-pagepdfoptions
	 */
	public function setOptions(array $value): static {
		$this->options = $value;
		return $this;
	}

	public function getContextOption(string $key, mixed $defaultValue): mixed {
		return (array_key_exists($key, $this->contextOptions) ? $this->contextOptions[$key] : $defaultValue);
	}

	public function setContextOption(string $key, mixed $value): static {
		$this->contextOptions[$key] = $value;
		return $this;
	}

	public function export(): array {
		$result = array_merge($this->contextOptions, [
			'options' => $this->options,
		]);

		// Specifying an empty footerTemplate ('') is a nice way to prevent the default one from being used.
		// The default one renders the file name (or URL) on the left side and the page number on the right.
		// When an empty footerTemplate is specified, Browserless errors out with:
		// > options.footerTemplate is not allowed to be empty" error
		// This is a workaround for that.
		if (array_key_exists('footerTemplate', $result['options']) && in_array($result['options']['footerTemplate'], [null, ''], true)) {
			$result['options']['footerTemplate'] = '<span></span>';
		}

		// Specifying an empty headerTemplate ('') is a nice way to prevent the default one from being used.
		// The default one renders the date on the left side.
		// When an empty footerTemplate is specified, Browserless errors out with:
		// > options.footerTemplate is not allowed to be empty" error
		// This is a workaround for that.
		if (array_key_exists('headerTemplate', $result['options']) && in_array($result['options']['headerTemplate'], [null, ''], true)) {
			$result['options']['headerTemplate'] = '<span></span>';
		}

		return $result;
	}

}
