<?php
namespace hitlightblog\app\exception;
/**
* ValidationException - Ausnahmen bei Datenfeldvalidierung
*/
class ValidationException extends BaseException
{
/**
* Fehler nach Feld gruppiert
*
* @var array
*/
protected $errors = [];

/**
* Konstruktor
*
* @param string $message Die Fehlermeldung
* @param array $errors Die Validierungsfehler
* @param int $code Der Fehlercode
* @param \Throwable|null $previous Die vorherige Exception
*/
public function __construct(string $message = "Validierungsfehler", array $errors = [], int $code = 400, \Throwable $previous = null)
{
parent::__construct($message, $code, $previous);
$this->errors = $errors;
$this->addData('validation_errors', $errors);
}

/**
* Gibt alle Validierungsfehler zurück
*
* @return array
*/
public function getErrors(): array
{
return $this->errors;
}

/**
* Prüft, ob ein bestimmtes Feld Fehler hat
*
* @param string $field Der Feldname
* @return bool
*/
public function hasError(string $field): bool
{
return isset($this->errors[$field]);
}

/**
* Gibt die Fehler für ein bestimmtes Feld zurück
*
* @param string $field Der Feldname
* @return array|null
*/
public function getFieldErrors(string $field): ?array
{
return $this->errors[$field] ?? null;
}

/**
* Fügt einen Validierungsfehler hinzu
*
* @param string $field Der Feldname
* @param string $message Die Fehlermeldung
* @return $this
*/
public function addError(string $field, string $message): self
{
if (!isset($this->errors[$field])) {
$this->errors[$field] = [];
}

$this->errors[$field][] = $message;
$this->data['validation_errors'] = $this->errors;

return $this;
}

/**
* Erstellt eine ValidationException aus einem Array von Fehlern
*
* @param array $errors Die Validierungsfehler
* @return ValidationException
*/
public static function withErrors(array $errors): self
{
return new self("Validierungsfehler", $errors);
}

/**
* Erzeugt eine ValidationException für ein erforderliches Feld
*
* @param string $field Der Feldname
* @return ValidationException
*/
public static function requiredField(string $field): self
{
$exception = new self("Das Feld '$field' ist erforderlich");
$exception->addError($field, "Dieses Feld ist erforderlich");
return $exception;
}

/**
* Erzeugt eine ValidationException für ein ungültiges Format
*
* @param string $field Der Feldname
* @param string $format Das erwartete Format
* @return ValidationException
*/
public static function invalidFormat(string $field, string $format): self
{
$exception = new self("Das Feld '$field' hat ein ungültiges Format");
$exception->addError($field, "Ungültiges Format. Erwartet: $format");
return $exception;
}
}
