<?php
namespace hitlightblog\app\core;
class Session
{
    /** @var bool Gibt an, ob die Session aktuell geöffnet ist */
    private static bool $_sessionOpen = false;

    /** @var string Fallback-Präfix falls SESSION_PREFIX nicht definiert ist */
    private static string $_defaultPrefix = 'hitlightblog_';

    /**
     * Öffnet die Session mit Sicherheitseinstellungen
     *
     * @return void
     */
    public static function open(): void
    {
        if (self::$_sessionOpen === false) {
            // Sichere Session-Konfiguration
            ini_set('session.cookie_httponly', '1');
            ini_set('session.use_strict_mode', '1');

            // Secure Cookie für HTTPS
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
                ini_set('session.cookie_secure', '1');
            }

            session_start();
            self::$_sessionOpen = true;

            // Session-Timeout auf 30 Minuten setzen
            if (!isset($_SESSION['LAST_ACTIVITY'])) {
                $_SESSION['LAST_ACTIVITY'] = time();
            } elseif (time() - $_SESSION['LAST_ACTIVITY'] > 1800) {
                self::destroy(false);
                session_start();
                self::$_sessionOpen = true;
            }
            $_SESSION['LAST_ACTIVITY'] = time();

            // Session-ID alle 30 Minuten regenerieren
            if (!isset($_SESSION['CREATED'])) {
                $_SESSION['CREATED'] = time();
            } elseif (time() - $_SESSION['CREATED'] > 1800) {
                self::regenerateId(false);
            }
        }
    }

    /**
     * Schließt die Session, wenn sie geöffnet ist
     *
     * @return void
     */
    public static function close(): void
    {
        if (self::$_sessionOpen === true) {
            session_write_close();
            self::$_sessionOpen = false;
        }
    }

    /**
     * Initialisiert die Session (für Rückwärtskompatibilität)
     *
     * @return void
     */
    public static function init(): void
    {
        self::open();
        // Sofort schließen, um Session-Blocking zu vermeiden
        self::close();
    }

    /**
     * Regeneriert die Session-ID zum Schutz gegen Session-Fixation
     *
     * @param bool $closeSession Nach der Operation die Session schließen?
     * @param bool $deleteOldSession Alte Session-Daten löschen?
     * @return void
     */
    public static function regenerateId(bool $closeSession = true, bool $deleteOldSession = false): void
    {
        self::open();
        session_regenerate_id($deleteOldSession);
        $_SESSION['CREATED'] = time();

        if ($closeSession) {
            self::close();
        }
    }

    /**
     * Setzt einen Wert in der Session
     *
     * @param string $key Session-Schlüssel
     * @param mixed $value Session-Wert
     * @param bool $closeSession Nach der Operation die Session schließen?
     * @return mixed Der gesetzte Wert
     */
    public static function set(string $key, mixed $value, bool $closeSession = true): mixed
    {
        self::open();
        $prefix = self::getPrefix();
        $_SESSION[$prefix . $key] = $value;

        if ($closeSession) {
            self::close();
        }

        return $value;
    }

    /**
     * Holt einen Wert aus der Session
     *
     * @param string $key Session-Schlüssel
     * @param string|null $secondkey Optional: Zweiter Schlüssel für verschachtelte Arrays
     * @param mixed $default Standardwert, wenn der Schlüssel nicht existiert
     * @param bool $closeSession Nach der Operation die Session schließen?
     * @return mixed Session-Wert oder Standardwert
     */
    public static function get(string $key, ?string $secondkey = null, mixed $default = null, bool $closeSession = true): mixed
    {
        self::open();
        $prefix = self::getPrefix();
        $result = $default;

        if ($secondkey !== null) {
            if (isset($_SESSION[$prefix . $key][$secondkey])) {
                $result = $_SESSION[$prefix . $key][$secondkey];
            }
        } else {
            if (isset($_SESSION[$prefix . $key])) {
                $result = $_SESSION[$prefix . $key];
            }
        }

        if ($closeSession) {
            self::close();
        }

        return $result;
    }

    /**
     * Prüft, ob ein Schlüssel in der Session existiert
     *
     * @param string $key Session-Schlüssel
     * @param string|null $secondkey Optional: Zweiter Schlüssel für verschachtelte Arrays
     * @param bool $closeSession Nach der Operation die Session schließen?
     * @return bool True, wenn der Schlüssel existiert
     */
    public static function has(string $key, ?string $secondkey = null, bool $closeSession = true): bool
    {
        self::open();
        $prefix = self::getPrefix();
        $result = false;

        if ($secondkey !== null) {
            $result = isset($_SESSION[$prefix . $key][$secondkey]);
        } else {
            $result = isset($_SESSION[$prefix . $key]);
        }

        if ($closeSession) {
            self::close();
        }

        return $result;
    }

    /**
     * Gibt Session-Daten zurück (gefiltert für mehr Sicherheit)
     *
     * @param bool $includeSensitive Ob sensible Daten einbezogen werden sollen
     * @param bool $closeSession Nach der Operation die Session schließen?
     * @return array Session-Daten
     */
    public static function display(bool $includeSensitive = false, bool $closeSession = true): array
    {
        self::open();

        $result = $_SESSION;

        if (!$includeSensitive) {
            // Kopie ohne sensible Daten erstellen
            $sensitiveKeys = ['csrf_token', 'LAST_ACTIVITY', 'CREATED'];

            foreach ($sensitiveKeys as $key) {
                if (isset($result[$key])) {
                    unset($result[$key]);
                }
            }
        }

        if ($closeSession) {
            self::close();
        }

        return $result;
    }

    /**
     * Löscht einen Session-Wert
     *
     * @param string $key Session-Schlüssel
     * @param string|null $secondkey Optional: Zweiter Schlüssel für verschachtelte Arrays
     * @param bool $closeSession Nach der Operation die Session schließen?
     * @return void
     */
    public static function clear(string $key, ?string $secondkey = null, bool $closeSession = true): void
    {
        self::open();
        $prefix = self::getPrefix();

        if ($secondkey !== null) {
            if (isset($_SESSION[$prefix . $key][$secondkey])) {
                unset($_SESSION[$prefix . $key][$secondkey]);
            }
        } else {
            if (isset($_SESSION[$prefix . $key])) {
                unset($_SESSION[$prefix . $key]);
            }
        }

        if ($closeSession) {
            self::close();
        }
    }

    /**
     * Zerstört die aktuelle Session vollständig
     *
     * @param bool $closeSession Nach der Operation die Session schließen?
     * @return void
     */
    public static function destroy(bool $closeSession = true): void
    {
        self::open();

        // Alle Session-Variablen löschen
        session_unset();

        // Session-Cookie löschen
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        // Session zerstören
        session_destroy();
        self::$_sessionOpen = false;
    }

    /**
     * Generiert und gibt ein CSRF-Token zurück
     *
     * @param bool $closeSession Nach der Operation die Session schließen?
     * @return string CSRF-Token
     */
    public static function getCsrfToken(bool $closeSession = true): string
    {
        self::open();

        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $token = $_SESSION['csrf_token'];

        if ($closeSession) {
            self::close();
        }

        return $token;
    }

    /**
     * Validiert ein CSRF-Token
     *
     * @param string $token Das zu validierende Token
     * @param bool $closeSession Nach der Operation die Session schließen?
     * @return bool True, wenn das Token gültig ist
     */
    public static function validateCsrfToken(string $token, bool $closeSession = true): bool
    {
        self::open();

        $valid = isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);

        if ($closeSession) {
            self::close();
        }

        return $valid;
    }

    /**
     * Gibt das Session-Präfix zurück (mit Fallback)
     *
     * @return string Session-Präfix
     */
    private static function getPrefix(): string
    {
        return defined('SESSION_PREFIX') ? SESSION_PREFIX : self::$_defaultPrefix;
    }

    /**
     * Führt mehrere Session-Operationen in einem einzigen Session-Zugriff aus
     *
     * @param callable $callback Funktion, die ausgeführt werden soll
     * @return mixed Rückgabewert der Callback-Funktion
     */
    public static function batch(callable $callback): mixed
    {
        self::open();
        $result = $callback();
        self::close();
        return $result;
    }
}