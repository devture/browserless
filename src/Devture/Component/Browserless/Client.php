<?php
namespace Devture\Component\Browserless;

use Devture\Component\Browserless\Model\PdfCreationRequest;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Client {

	public function __construct(
		private \GuzzleHttp\Client $guzzleClient,
		private string $endpoint,
		private ?string $token,
		private ?int $timeoutSeconds,
	) {

	}

	/**
	 * @throws Exception\AuthFailure
	 * @throws Exception
	 */
	public function createPdfFromHtmlRequestUsingFileProtocol(PdfCreationRequest $request): string {
		$html = $request->getHtml();
		if ($html === null) {
			throw new Exception('html is a required attribute');
		}

		$workspaceFile = $this->createWorkspaceFile($html, 'html');

		$requestModified = clone $request;
		$requestModified->setHtml(null);
		$requestModified->setUrl('file://' . $workspaceFile->getPath());

		$pdf = $this->createPdfFromRequest($requestModified);

		try {
			$this->deleteWorkspaceFileAsync($workspaceFile);
		} catch (Exception $e) {
			// Swallow
		}

		return $pdf;
	}

	/**
	 * @throws Exception\AuthFailure
	 * @throws Exception
	 */
	public function createPdfFromRequest(PdfCreationRequest $creationRequest): string {
		if ($creationRequest->getUrl() === null && $creationRequest->getHtml()) {
			throw new Exception('You need to specify html or url');
		}

		$request = new \GuzzleHttp\Psr7\Request('POST', $this->createUrl('/pdf'));
		$request = $request->withHeader('Content-Type', 'application/json');
		$request = $request->withHeader('Accept', 'application/json');
		$request = $request->withBody(\GuzzleHttp\Psr7\Utils::streamFor(\json_encode($creationRequest->export())));

		$response = $this->sendRequest($request);

		return (string) $response->getBody();
	}

	public function createWorkspaceFile(string $bytes, string $fileExtension): Model\WorkspaceFile {
		$fileName = sprintf('%s.%s', \Ramsey\Uuid\Uuid::uuid4()->toString(), $fileExtension);

		$request = new \GuzzleHttp\Psr7\Request('POST', $this->createUrl('/workspace'));
		$request = $request->withBody(new \GuzzleHttp\Psr7\MultipartStream([
			[
				'name' => 'file',
				'contents' => $bytes,
				'filename' => $fileName,
			],
		]));
		$request = $request->withHeader('Accept', 'application/json');

		$response = $this->sendRequest($request);

		$body = (string) $response->getBody();
		$response = json_decode($body, true);
		if (!is_array($response)) {
			throw new Exception\BadResponse('Expected array response, got something else');
		}
		if (!isset($response[0])) {
			throw new Exception\BadResponse('Expected an indexed array with 1 item, but index 0 is unset');
		}
		if (!array_key_exists('path', $response[0])) {
			throw new Exception\BadResponse('Expected 0-indexed entry to be a map containing a "path" key, but did not find it');
		}

		return new Model\WorkspaceFile($response[0], $this->endpoint, $this->token);
	}

	/**
	 * @throws Exception\AuthFailure
	 * @throws Exception
	 */
	public function deleteWorkspaceFile(Model\WorkspaceFile $file): void {
		$promise = $this->deleteWorkspaceFileAsync($file);

		$response = $promise->wait();

		if ($response->getStatusCode() === 204) {
			return;
		}

		throw new Exception\BadResponse(sprintf('Expected a 204 response, but got %d', $response->getStatusCode()));
	}

	/**
	 * @throws Exception\AuthFailure
	 * @throws Exception
	 */
	public function deleteWorkspaceFileAsync(Model\WorkspaceFile $file): ?PromiseInterface {
		$request = new \GuzzleHttp\Psr7\Request('DELETE', $this->createUrl(sprintf('/workspace/%s', $file->getName())));
		$request = $request->withHeader('Accept', 'application/json');

		try {
			$promise = $this->sendRequestAsync($request);
		} catch (Exception $e) {
			$previous = $e->getPrevious();
			if ($previous instanceof \GuzzleHttp\Exception\ClientException) {
				if ($previous->getResponse()->getStatusCode() === 404) {
					// File likely deleted already. Suppress.
					return null;
				}
			}

			throw $e;
		}

		return $promise;
	}

	private function createUrl(string $relativePath): string {
		return Util::generateUrl($this->endpoint, $relativePath, $this->token);
	}

	private function sendRequest(RequestInterface $request): ResponseInterface {
		return $this->sendRequestAsync($request)->wait();
	}

	private function sendRequestAsync(RequestInterface $request): PromiseInterface {
		try {
			return $this->guzzleClient->sendAsync($request, [
				'timeout' => $this->timeoutSeconds,
			]);
		} catch (\GuzzleHttp\Exception\ClientException $e) {
			$response = $e->getResponse();

			if ($response->getStatusCode() === 403) {
				throw new Exception\AuthFailure('Not authenticated', 0, $e);
			}

			throw new Exception($e->getMessage(), 0, $e);
		} catch (\GuzzleHttp\Exception\TransferException $e) {
			throw new Exception($e->getMessage(), 0, $e);
		}
	}

}
