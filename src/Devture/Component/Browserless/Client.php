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
	public function createPdfFromRequest(PdfCreationRequest $creationRequest): string {
		if ($creationRequest->getUrl() === null && $creationRequest->getHtml()) {
			throw new Exception('You need to specify html or url');
		}

		$request = new \GuzzleHttp\Psr7\Request('POST', $this->createUrl('/pdf'));
		$request = $request->withHeader('Content-Type', 'application/json');
		$request = $request->withBody(\GuzzleHttp\Psr7\Utils::streamFor(\json_encode($creationRequest->export())));

		$response = $this->sendRequest($request);

		return (string) $response->getBody();
	}

	private function createUrl(string $relativePath): string {
		return Util::generateUrl($this->endpoint, $relativePath, $this->token);
	}

	private function sendRequest(RequestInterface $request): ResponseInterface {
		try {
			return $this->sendRequestAsync($request)->wait();
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

	private function sendRequestAsync(RequestInterface $request): PromiseInterface {
		return $this->guzzleClient->sendAsync($request, [
			'timeout' => $this->timeoutSeconds,
		]);
	}

}
