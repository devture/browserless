<?php
namespace Devture\Component\Browserless;

class Util {

	static public function generateUrl(string $baseUrl, string $relativePath, ?string $token): string {
		$url = sprintf('%s/%s', rtrim($baseUrl, '/'), ltrim($relativePath, '/'));
		if ($token) {
			$url .= '?token=' . $token;
		}
		return $url;
	}

}
