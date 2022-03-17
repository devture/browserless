<?php
namespace Devture\Component\Browserless\Model;

class WorkspaceFile {

	public function __construct(private array $record, private string $baseUrl, private ?string $token) {

	}

	public function getPath(): string {
		return $this->record['path'];
	}

	public function getName(): string {
		return $this->record['filename'];
	}

	public function getFullUrl(): string {
		return \Devture\Component\Browserless\Util::generateUrl($this->baseUrl, $this->getPath(), $this->token);
	}

}
