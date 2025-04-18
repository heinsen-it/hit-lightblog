<?php
namespace hitlightblog\app\core;

/**
 * Hauptanwendungsklasse für das Routing und Controller-Management
 */
class App
{
    /** @var array Liste gültiger Controller-Namen (Whitelist) */
    private array $_allowedControllers = ['start', 'post', 'login', 'admin', 'impressum','datenschutz'];

    /** @var array Verarbeitete URL-Komponenten */
    private array $_url = [];

    /** @var string|null Aktueller Controller-Name */
    private ?string $_controllerName = null;

    /** @var string|null Aktuelle Aktion/Methode */
    private ?string $_actionName = null;

    /** @var array Parameter für den Controller */
    private array $_params = [];

    /** @var string Standard-Controller, der verwendet wird, wenn kein Controller angegeben wurde */
    private string $_defaultController = 'start';

    /** @var string Standard-Aktion, die verwendet wird, wenn keine Aktion angegeben wurde */
    private string $_defaultAction = 'index';

    /** @var string Namespace-Präfix für Controller-Klassen */
    private string $_namespacePrefix = 'hitlightblog\\app\\controllers\\';

    /**
     * Konstruktor - initialisiert die Anwendung
     */
    public function __construct()
    {
        // Session initialisieren
        Session::init();

        // URL verarbeiten
        $this->_processUrl();
    }

    /**
     * Setzt den Standard-Controller
     *
     * @param string $name Name des Standard-Controllers
     * @return void
     */
    public function setDefaultController(string $name): void
    {
        // Prüfen, ob der Controller in der Whitelist steht
        if (in_array($name, $this->_allowedControllers, true)) {
            $this->_defaultController = $name;
        }
    }

    /**
     * Setzt die Liste der erlaubten Controller
     *
     * @param array $controllers Array mit erlaubten Controller-Namen
     * @return void
     */
    public function setAllowedControllers(array $controllers): void
    {
        $this->_allowedControllers = array_map(
            fn($name) => preg_replace('/[^a-z0-9_]/', '', strtolower($name)),
            $controllers
        );
    }

    /**
     * Initialisiert die Anwendung und lädt den entsprechenden Controller
     *
     * @return void
     */
    public function init(): void
    {
        try {
            $this->_loadController();
        } catch (\Throwable $e) {
            $this->_handleError('Anwendungsfehler: ' . $e->getMessage());
        }
    }

    /**
     * Verarbeitet und validiert die URL
     *
     * @return void
     */
    private function _processUrl(): void
    {
        // URL aus GET-Parameter holen und bereinigen
        $url = isset($_GET['url']) ? filter_var(rtrim($_GET['url'], '/'), FILTER_SANITIZE_URL) : '';

        // URL in Komponenten aufteilen
        if (!empty($url)) {
            $this->_url = explode('/', $url);

            // Controller-Name extrahieren und validieren
            $controllerName = strtolower($this->_url[0] ?? '');
            $controllerName = preg_replace('/[^a-z0-9_]/', '', $controllerName);

            // Nur erlaubte Controller akzeptieren
            if (!empty($controllerName) && in_array($controllerName, $this->_allowedControllers, true)) {
                $this->_controllerName = $controllerName;
                array_shift($this->_url);
            } else {
                $this->_controllerName = $this->_defaultController;
            }

            // Aktion/Methode extrahieren und validieren
            if (!empty($this->_url[0])) {
                $actionName = strtolower($this->_url[0]);
                $actionName = preg_replace('/[^a-z0-9_]/', '', $actionName);

                if (!empty($actionName)) {
                    $this->_actionName = $actionName;
                    array_shift($this->_url);
                }
            }

            // Verbleibende URL-Komponenten als Parameter speichern
            $this->_params = array_map(
                fn($param) => htmlspecialchars($param, ENT_QUOTES, 'UTF-8'),
                $this->_url
            );
        } else {
            // Standardwerte verwenden, wenn keine URL vorhanden ist
            $this->_controllerName = $this->_defaultController;
        }

        // Standardaktion, wenn keine angegeben wurde
        if (empty($this->_actionName)) {
            $this->_actionName = $this->_defaultAction;
        }
    }

    /**
     * Lädt und instanziiert den Controller und ruft die angegebene Aktion auf
     *
     * @return void
     * @throws \RuntimeException Wenn der Controller oder die Aktion nicht gefunden wird
     */
    private function _loadController(): void
    {
        // Vollständiger Klassenname des Controllers
        $controllerClassName = $this->_namespacePrefix . $this->_controllerName;

        // Prüfen, ob die Klasse existiert
        if (!class_exists($controllerClassName)) {
            throw new \RuntimeException("Controller nicht gefunden: {$this->_controllerName}");
        }

        // Controller instanziieren
        $controller = new $controllerClassName();

        // Prüfen, ob die Methode existiert und aufrufbar ist
        if (!method_exists($controller, $this->_actionName) || !is_callable([$controller, $this->_actionName])) {
            throw new \RuntimeException("Aktion nicht gefunden: {$this->_actionName}");
        }

        // Prüfen, ob die Methode öffentlich ist (Reflexion verwenden)
        $reflectionMethod = new \ReflectionMethod($controller, $this->_actionName);
        if (!$reflectionMethod->isPublic()) {
            throw new \RuntimeException("Aktion ist nicht öffentlich: {$this->_actionName}");
        }

        // Controller-Aktion mit Parametern aufrufen
        call_user_func_array([$controller, $this->_actionName], $this->_params);
    }

    /**
     * Gibt eine Fehlerseite aus
     *
     * @param string $message Fehlermeldung
     * @return void
     */
    private function _handleError(string $message): void
    {
        // HTTP-Statuscode setzen
        http_response_code(404);

        // Klassen- und Dateinamen für die Fehlerbehandlung
        $errorControllerClassName = $this->_namespacePrefix . 'error';

        // Wenn ein spezifischer Error-Controller existiert, diesen verwenden
        if (class_exists($errorControllerClassName)) {
            $errorController = new $errorControllerClassName();
            if (method_exists($errorController, 'index')) {
                $errorController->index($message);
                return;
            }
        }

        // Fallback: Einfache Fehlerseite ausgeben
        echo '<!DOCTYPE html>
              <html>
              <head>
                  <title>404 - Seite nicht gefunden</title>
                  <style>
                      body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; }
                      .error-container { max-width: 600px; margin: 0 auto; padding: 20px; }
                      h1 { color: #d9534f; }
                  </style>
              </head>
              <body>
                  <div class="error-container">
                      <h1>404 - Seite nicht gefunden</h1>
                      <p>Die angeforderte Seite konnte nicht gefunden werden.</p>
                  </div>
              </body>
              </html>';
    }

    /**
     * Gibt die aktuelle URL-Struktur zurück
     *
     * @return array URL-Komponenten
     */
    public function getUrl(): array
    {
        return $this->_url;
    }

    /**
     * Gibt den Namen des aktuellen Controllers zurück
     *
     * @return string Controller-Name
     */
    public function getControllerName(): string
    {
        return $this->_controllerName ?? $this->_defaultController;
    }

    /**
     * Gibt den Namen der aktuellen Aktion zurück
     *
     * @return string Aktionsname
     */
    public function getActionName(): string
    {
        return $this->_actionName ?? $this->_defaultAction;
    }
}