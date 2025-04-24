<?php
namespace hitlightblog\app\exception;
/**
 * BaseException - Basisklasse für alle benutzerdefinierten Exceptions
 *
 * Diese Klasse erweitert die Standard-Exception und dient als Basis
 * für alle anwendungsspezifischen Ausnahmen.
 */
class BaseException extends \Exception
{
    /**
     * Zusätzliche Daten zur Exception
     *
     * @var array
     */
    protected $data = [];

    /**
     * Konstruktor
     *
     * @param string $message Die Fehlermeldung
     * @param int $code Der Fehlercode
     * @param \Throwable|null $previous Die vorherige Exception
     * @param array $data Zusätzliche Kontextdaten
     */
    public function __construct(string $message = "", int $code = 0, \Throwable $previous = null, array $data = [])
    {
        parent::__construct($message, $code, $previous);
        $this->data = $data;
    }

    /**
     * Gibt die zusätzlichen Daten zurück
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Fügt zusätzliche Daten hinzu
     *
     * @param string $key Der Schlüssel
     * @param mixed $value Der Wert
     * @return $this
     */
    public function addData(string $key, $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }

    /**
     * Gibt eine formatierte Fehlerdarstellung zurück
     *
     * @return string
     */
    public function getFormattedError(): string
    {
        $output = sprintf(
            "[%s] %s (Code: %d) in %s on line %d",
            get_class($this),
            $this->getMessage(),
            $this->getCode(),
            $this->getFile(),
            $this->getLine()
        );

        if (!empty($this->data)) {
            $output .= "\nAdditional Data: " . json_encode($this->data, JSON_PRETTY_PRINT);
        }

        $output .= "\nStack Trace:\n" . $this->getTraceAsString();

        return $output;
    }
}

