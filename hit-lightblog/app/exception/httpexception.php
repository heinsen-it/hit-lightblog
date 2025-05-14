<?php
namespace hitlightblog\app\exception;
/**
* HttpException - Ausnahmen für HTTP-Fehler
*/
class HttpException extends BaseException
{
/**
* HTTP-Status-Code
*
* @var int
*/
protected $statusCode;

/**
* HTTP-Header
*
* @var array
*/
protected $headers = [];

/**
* Konstruktor
*
* @param int $statusCode Der HTTP-Status-Code
* @param string $message Die Fehlermeldung
* @param array $headers Die HTTP-Header
* @param int $code Der interne Fehlercode
* @param \Throwable|null $previous Die vorherige Exception
* @param array $data Zusätzliche Daten
*/
public function __construct(
int $statusCode,
string $message = "",
array $headers = [],
int $code = 0,
\Throwable $previous = null,
array $data = []
) {
parent::__construct($message, $code, $previous, $data);
$this->statusCode = $statusCode;
$this->headers = $headers;
}

/**
* Gibt den HTTP-Status-Code zurück
*
* @return int
*/
public function getStatusCode(): int
{
return $this->statusCode;
}

/**
* Gibt die HTTP-Header zurück
*
* @return array
*/
public function getHeaders(): array
{
return $this->headers;
}

/**
* Erzeugt eine 404 Not Found Exception
*
* @param string $resource Die gesuchte Ressource
* @return HttpException
*/
public static function notFound(string $resource = "Ressource"): self
{
return new self(
404,
"$resource wurde nicht gefunden",
[],
404,
null,
['resource' => $resource]
);
}

/**
* Erzeugt eine 403 Forbidden Exception
*
* @param string $action Die verbotene Aktion
* @return HttpException
*/
public static function forbidden(string $action = ""): self
{
$message = "Zugriff verweigert";
if ($action) {
$message .= ": $action";
}

return new self(
403,
$message,
[],
403,
null,
['action' => $action]
);
}

/**
* Erzeugt eine 401 Unauthorized Exception
*
* @return HttpException
*/
public static function unauthorized(): self
{
return new self(
401,
"Nicht authentifiziert",
['WWW-Authenticate' => 'Bearer'],
401
);
}

/**
* Erzeugt eine 400 Bad Request Exception
*
* @param string $message Die Fehlermeldung
* @return HttpException
*/
public static function badRequest(string $message = "Ungültige Anfrage"): self
{
return new self(
400,
$message,
[],
400
);
}

/**
* Erzeugt eine 429 Too Many Requests Exception
*
* @param int $retryAfter Zeit in Sekunden bis zum nächsten Versuch
* @return HttpException
*/
public static function tooManyRequests(int $retryAfter = 60): self
{
return new self(
429,
"Zu viele Anfragen",
['Retry-After' => $retryAfter],
429,
null,
['retry_after' => $retryAfter]
);
}
}

