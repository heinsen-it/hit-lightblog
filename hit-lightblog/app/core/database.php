<?php
namespace hitbugsreader\app\core;

use Exception;
use mysqli;
use mysqli_stmt;
use mysqli_result;

/**
 * Benutzerdefinierte Exception-Klassen für besseres Exception-Handling
 */
class DatabaseException extends Exception {}
class QueryException extends DatabaseException {}
class ConnectionException extends DatabaseException {}

/**
 * Verbesserte Datenbankklasse mit Sicherheitsverbesserungen und Typensicherheit
 */
class Database extends mysqli
{
    private mysqli $link;
    public static int $counter = 0;
    private static ?Database $instance = null;

    /**
     * Konstruktor - erstellt eine neue Datenbankverbindung
     *
     * @param string $DB_HOST Hostname des Datenbankservers
     * @param string $DB_USER Benutzername für die Datenbankverbindung
     * @param string $DB_PASS Passwort für die Datenbankverbindung
     * @param string $DB_NAME Name der Datenbank
     * @throws ConnectionException Wenn die Verbindung nicht hergestellt werden kann
     */
    public function __construct(string $DB_HOST, string $DB_USER, string $DB_PASS, string $DB_NAME)
    {
        try {
            $this->link = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

            if ($this->link->connect_error) {
                throw new ConnectionException("Verbindung fehlgeschlagen: " . $this->link->connect_error);
            }

            // UTF8MB4 für volle Unicode-Unterstützung inkl. Emojis
            $this->link->set_charset("utf8mb4");
        } catch (Exception $e) {
            // Sicheres Logging des Fehlers
            error_log("Datenbankverbindungsfehler: " . $e->getMessage());
            throw new ConnectionException("Verbindung zur Datenbank konnte nicht hergestellt werden");
        }
    }

    /**
     * Destruktor - schließt die Datenbankverbindung
     */
    public function __destruct()
    {
        if ($this->link) {
            $this->disconnect();
        }
    }

    /**
     * Loggt Datenbankfehler abhängig von der Konfiguration
     *
     * @param string $error Fehlermeldung
     * @param string $query SQL-Abfrage, die den Fehler verursacht hat
     */
    /**
     * Loggt Datenbankfehler abhängig von der Konfiguration
     *
     * @param string $error Fehlermeldung
     * @param string $query SQL-Abfrage, die den Fehler verursacht hat
     */
    private function log_db_errors(string $error, string $query): void
    {
        $message = '<p>Fehler am ' . date('Y-m-d H:i:s') . ':</p>';
        $message .= '<p>Abfrage: ' . htmlspecialchars($query, ENT_QUOTES, 'UTF-8') . '<br />';
        $message .= 'Fehler: ' . $error;
        $message .= '</p>';

        // Log immer in die Fehlerprotokolldatei
        error_log("Datenbankfehler: " . strip_tags($message));

        // E-Mail senden, wenn konfiguriert
        if (defined('SEND_ERRORS_TO')) {
            // Sichere E-Mail-Generierung ohne Header-Injection-Risiko
            $to = filter_var(SEND_ERRORS_TO, FILTER_SANITIZE_EMAIL);
            $subject = 'Datenbankfehler';

            // Sichere Header-Erstellung
            $headers = [
                'MIME-Version: 1.0',
                'Content-type: text/html; charset=UTF-8',
                'To: Admin <' . $to . '>'
            ];

            // Domain validieren und für From-Header verwenden
            $domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
            if (preg_match('/^[a-z0-9.-]+$/i', $domain)) {
                $headers[] = 'From: System <system@' . $domain . '>';
            } else {
                $headers[] = 'From: System <system@example.com>';
            }

            // Sicherer E-Mail-Versand
            mail($to, $subject, $message, implode("\r\n", $headers));
        }

        // Debug-Ausgabe nur in Entwicklungsumgebungen
        if (defined('DISPLAY_DEBUG') && DISPLAY_DEBUG &&
            (defined('DEVELOPMENT_ENVIRONMENT') && DEVELOPMENT_ENVIRONMENT)) {
            echo $message;
        }
    }

    /**
     * Bereinigt Daten für die sichere Verwendung in Datenbankabfragen
     *
     * @param mixed $data Zu bereinigende Daten (Zeichenkette oder Array)
     * @return mixed Bereinigte Daten
     */
    public function escape_db(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $this->link->real_escape_string((string)$data);
        } else {
            return array_map([$this, 'escape_db'], $data);
        }
    }

    /**
     * Bereinigt Daten für die sichere Ausgabe in HTML
     *
     * @param mixed $data Zu bereinigende Daten (Zeichenkette oder Array)
     * @return mixed Bereinigte Daten
     */
    public function escape_html(mixed $data): mixed
    {
        if (!is_array($data)) {
            return htmlspecialchars((string)$data, ENT_QUOTES, 'UTF-8');
        } else {
            return array_map([$this, 'escape_html'], $data);
        }
    }

    /**
     * Legacy-Methode für die Kompatibilität, verwendet nun escape_db und escape_html getrennt
     *
     * @deprecated Verwende stattdessen escape_db oder escape_html
     * @param mixed $data Zu bereinigende Daten
     * @return mixed Bereinigte Daten
     */
    public function filter(mixed $data): mixed
    {
        if (!is_array($data)) {
            $data = $this->escape_db($data);
            $data = trim($this->escape_html($data));
        } else {
            $data = array_map([$this, 'filter'], $data);
        }
        return $data;
    }

    /**
     * Legacy-Methode für Escape-Funktion
     *
     * @deprecated Verwende stattdessen escape_db
     * @param mixed $data Zu bereinigende Daten
     * @return mixed Bereinigte Daten
     */
    public function escape(mixed $data): mixed
    {
        return $this->escape_db($data);
    }

    /**
     * Dekodiert HTML-Entitäten und Sonderzeichen für die Ausgabe
     * ACHTUNG: Nur für vertrauenswürdige Daten verwenden, die bereits gesäubert wurden!
     * Diese Methode entfernt Sicherheitsmaßnahmen und sollte mit Vorsicht verwendet werden.
     *
     * @param string $data Zu dekodierende Zeichenkette
     * @return string Dekodierte Zeichenkette
     */
    public function clean(string $data): string
    {
        $data = html_entity_decode($data, ENT_QUOTES, 'UTF-8');
        $data = nl2br($data);
        return $data;
    }


    /**
     * Prüft, ob ein Wert bestimmte MySQL-Funktionen enthält
     * Diese Methode sollte NICHT mehr für direkte SQL-Einbettung verwendet werden
     *
     * @param mixed $value Zu prüfender Wert
     * @return bool True, wenn der Wert MySQL-Funktionen enthält
     * @deprecated Verwende stattdessen immer Prepared Statements
     */
    private function db_common(mixed $value = ''): bool
    {
        if (is_array($value)) {
            foreach ($value as $v) {
                if (preg_match('/AES_DECRYPT|AES_ENCRYPT|now\(\)/i', $v)) {
                    return true;
                }
            }
            return false;
        } else {
            return preg_match('/AES_DECRYPT|AES_ENCRYPT|now\(\)/i', (string)$value);
        }
    }

    /**
     * Führt eine SQL-Abfrage aus (Kompatibilität mit mysqli)
     *
     * @param string $query SQL-Abfrage
     * @param int $resultmode Ergebnismodus (optional)
     * @return mysqli_result|bool Ergebnis der Abfrage oder false bei Fehler
     * @throws QueryException Wenn die Abfrage fehlschlägt
     */
    public function query(string $query, int $resultmode = MYSQLI_STORE_RESULT): mysqli_result|bool
    {
        self::$counter++;

        $result = $this->link->query($query, $resultmode);

        if ($this->link->error) {
            $this->log_db_errors($this->link->error, $query);
            throw new QueryException("Abfragefehler: " . $this->link->error);
        }

        return $result;
    }

    /**
     * Führt eine SQL-Abfrage mit Prepared Statements aus
     *
     * @param string $query SQL-Abfrage
     * @param array $params Parameter für die Abfrage (optional)
     * @param string $types Typen der Parameter (optional)
     * @return mysqli_result|bool Ergebnis der Abfrage
     * @throws QueryException Wenn die Abfrage fehlschlägt
     */
    /**
     * Führt eine SQL-Abfrage mit Prepared Statements aus
     * mit verbesserter Typenbehandlung
     *
     * @param string $query SQL-Abfrage
     * @param array $params Parameter für die Abfrage (optional)
     * @param string $types Typen der Parameter (optional)
     * @return mysqli_result|bool Ergebnis der Abfrage
     * @throws QueryException Wenn die Abfrage fehlschlägt
     */
    public function prepare_query(string $query, array $params = [], string $types = ''): mysqli_result|bool
    {
        self::$counter++;

        if (empty($params)) {
            // Direkte Abfrage ohne Parameter
            return $this->query($query);
        }

        // Prepared Statement mit Parametern
        $stmt = $this->link->prepare($query);

        if (!$stmt) {
            $this->log_db_errors($this->link->error, $query);
            throw new QueryException("Fehler beim Vorbereiten der Abfrage: " . $this->link->error);
        }

        // Verbesserte Typenbehandlung
        if (empty($types)) {
            // Automatische Typerkennung
            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i'; // Integer
                } elseif (is_float($param)) {
                    $types .= 'd'; // Double
                } elseif (is_string($param)) {
                    $types .= 's'; // String
                } elseif (is_null($param)) {
                    $types .= 's'; // NULL als String behandeln
                } else {
                    $types .= 's'; // Standardmäßig als String behandeln
                }
            }
        }

        // Parameter binden
        if (count($params) > 0) {
            $stmt->bind_param($types, ...$params);
        }

        // Abfrage ausführen
        $success = $stmt->execute();

        if (!$success) {
            $this->log_db_errors($stmt->error, $query);
            $stmt->close();
            throw new QueryException("Fehler beim Ausführen der Abfrage: " . $stmt->error);
        }

        $result = $stmt->get_result();
        $stmt->close();

        return $result ?: true;
    }

    /**
     * Prüft, ob eine Tabelle existiert
     *
     * @param string $table Name der Tabelle
     * @return bool True, wenn die Tabelle existiert
     * @throws QueryException Bei Abfragefehlern
     */
    public function table_exists(string $table): bool
    {
        self::$counter++;

        // Validiere den Tabellennamen
        if (!$this->validate_table_name($table)) {
            throw new QueryException("Ungültiger Tabellenname: " . $table);
        }

        try {
            // Korrekte Verwendung von prepare_query statt query mit Parametern
            $result = $this->prepare_query("SHOW TABLES LIKE ?", [$table]);
            return $result instanceof mysqli_result && $result->num_rows > 0;
        } catch (QueryException $e) {
            return false;
        }
    }

    /**
     * Gibt die Anzahl der Zeilen zurück, die einer Abfrage entsprechen
     *
     * @param string $query SQL-Abfrage
     * @param array $params Parameter für die Abfrage (optional)
     * @return int Anzahl der Zeilen
     * @throws QueryException Bei Abfragefehlern
     */
    public function num_rows(string $query, array $params = []): int
    {
        self::$counter++;

        $result = empty($params) ? $this->query($query) : $this->prepare_query($query, $params);

        if ($result instanceof mysqli_result) {
            return $result->num_rows;
        }

        return 0;
    }

    /**
     * Prüft, ob ein Eintrag in einer Tabelle existiert
     *
     * @param string $table Tabellenname
     * @param string $check_val Zu prüfendes Feld
     * @param array $params Parameter für die WHERE-Klausel
     * @return bool True, wenn der Eintrag existiert
     * @throws QueryException Bei Abfragefehlern
     */
    public function exists(string $table, string $check_val, array $params = []): bool
    {
        self::$counter++;

        if (empty($table) || empty($check_val) || empty($params)) {
            return false;
        }

        // Tabellennamen validieren
        if (!$this->validate_table_name($table)) {
            throw new QueryException("Ungültiger Tabellenname: " . $table);
        }

        // Check_val validieren
        if (!preg_match('/^[a-zA-Z0-9_*]+$/', $check_val)) {
            throw new QueryException("Ungültiger Feldname: " . $check_val);
        }

        $where_clauses = [];
        $query_params = [];

        foreach ($params as $field => $value) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
                throw new QueryException("Ungültiger Feldname: " . $field);
            }

            // Immer Prepared Statements verwenden
            $where_clauses[] = "`$field` = ?";
            $query_params[] = $value;
        }

        $where = implode(' AND ', $where_clauses);
        $query = "SELECT `$check_val` FROM `$table` WHERE $where LIMIT 1";

        $result = $this->prepare_query($query, $query_params);

        return $result instanceof mysqli_result && $result->num_rows > 0;
    }



    /**
     * Gibt eine einzelne Zeile aus der Datenbank zurück
     *
     * @param string $query SQL-Abfrage
     * @param array $params Parameter für die Abfrage (optional)
     * @param bool $object Gibt ein Objekt zurück, wenn true, sonst ein Array
     * @return array|object|null Datenbankeintrag
     * @throws QueryException Bei Abfragefehlern
     */
    public function get_row(string $query, array $params = [], bool $object = false): array|object|null
    {
        self::$counter++;

        $result = empty($params) ? $this->query($query) : $this->prepare_query($query, $params);

        if (!$result instanceof mysqli_result) {
            throw new QueryException("Ungültiges Abfrageergebnis");
        }

        if ($result->num_rows === 0) {
            return null;
        }

        return $object ? $result->fetch_object() : $result->fetch_assoc();
    }

    /**
     * Gibt mehrere Zeilen aus der Datenbank zurück
     *
     * @param string $query SQL-Abfrage
     * @param array $params Parameter für die Abfrage (optional)
     * @param bool $object Gibt Objekte zurück, wenn true, sonst Arrays
     * @return array|null Array von Datenbankeinträgen
     * @throws QueryException Bei Abfragefehlern
     */
    public function get_results(string $query, array $params = [], bool $object = false): ?array
    {
        self::$counter++;

        $result = empty($params) ? $this->query($query) : $this->prepare_query($query, $params);

        if (!$result instanceof mysqli_result) {
            throw new QueryException("Ungültiges Abfrageergebnis");
        }

        if ($result->num_rows === 0) {
            return null;
        }

        $rows = [];

        if ($object) {
            while ($row = $result->fetch_object()) {
                $rows[] = $row;
            }
        } else {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Fügt einen Datensatz in die Datenbank ein
     *
     * @param string $table Tabellenname
     * @param array $variables Einzufügende Daten
     * @return bool True bei Erfolg
     * @throws QueryException Bei Abfragefehlern
     */
    public function insert(string $table, array $variables = []): bool
    {
        self::$counter++;

        if (empty($variables)) {
            throw new QueryException("Keine Daten zum Einfügen angegeben");
        }

        // Tabellennamen validieren
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new QueryException("Ungültiger Tabellenname: " . $table);
        }

        $fields = [];
        $placeholders = [];
        $values = [];

        foreach ($variables as $field => $value) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
                throw new QueryException("Ungültiger Feldname: " . $field);
            }

            $fields[] = "`$field`";
            $placeholders[] = "?";
            $values[] = $value;
        }

        $fields_str = implode(', ', $fields);
        $placeholders_str = implode(', ', $placeholders);

        $query = "INSERT INTO `$table` ($fields_str) VALUES ($placeholders_str)";

        $this->query($query, $values);

        return true;
    }

    /**
     * Fügt mehrere Datensätze in die Datenbank ein
     *
     * @param string $table Tabellenname
     * @param array $columns Spaltennamen
     * @param array $records Einzufügende Datensätze
     * @return int Anzahl der eingefügten Datensätze
     * @throws QueryException Bei Abfragefehlern
     */
    public function insert_multi(string $table, array $columns = [], array $records = []): int
    {
        self::$counter++;

        if (empty($columns) || empty($records)) {
            throw new QueryException("Keine Spalten oder Datensätze zum Einfügen angegeben");
        }

        // Tabellennamen validieren
        if (!$this->validate_table_name($table)) {
            throw new QueryException("Ungültiger Tabellenname: " . $table);
        }

        // Spaltennamen validieren
        $fields = [];
        foreach ($columns as $column) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
                throw new QueryException("Ungültiger Spaltenname: " . $column);
            }
            $fields[] = "`$column`";
        }

        $fields_str = implode(', ', $fields);
        $number_columns = count($columns);

        // Multi-insert mit Prepared Statement
        $placeholders = rtrim(str_repeat('?,', $number_columns), ',');
        $records_placeholder = rtrim(str_repeat("($placeholders),", count($records)), ',');

        $query = "INSERT INTO `$table` ($fields_str) VALUES $records_placeholder";

        // Parameter flach machen
        $params = [];
        foreach ($records as $record) {
            if (count($record) != $number_columns) {
                continue;
            }
            foreach ($record as $value) {
                $params[] = $value;
            }
        }

        $this->query($query, $params);

        return count($records);
    }

    /**
     * Aktualisiert Datensätze in der Datenbank
     *
     * @param string $table Tabellenname
     * @param array $variables Zu aktualisierende Daten
     * @param array $where WHERE-Bedingungen
     * @param string $limit Limit (optional)
     * @return bool True bei Erfolg
     * @throws QueryException Bei Abfragefehlern
     */
    public function update(string $table, array $variables = [], array $where = [], string $limit = ''): bool
    {
        self::$counter++;

        if (empty($variables)) {
            throw new QueryException("Keine Daten zum Aktualisieren angegeben");
        }

        // Tabellennamen validieren
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new QueryException("Ungültiger Tabellenname: " . $table);
        }

        $updates = [];
        $params = [];

        foreach ($variables as $field => $value) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
                throw new QueryException("Ungültiger Feldname: " . $field);
            }

            $updates[] = "`$field` = ?";
            $params[] = $value;
        }

        $where_clauses = [];

        foreach ($where as $field => $value) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
                throw new QueryException("Ungültiger Feldname in WHERE-Klausel: " . $field);
            }

            $where_clauses[] = "`$field` = ?";
            $params[] = $value;
        }

        $query = "UPDATE `$table` SET " . implode(', ', $updates);

        if (!empty($where_clauses)) {
            $query .= " WHERE " . implode(' AND ', $where_clauses);
        }

        if (!empty($limit)) {
            // Limit validieren
            if (!preg_match('/^\d+$/', $limit)) {
                throw new QueryException("Ungültiges Limit: " . $limit);
            }
            $query .= " LIMIT $limit";
        }

        $this->query($query, $params);

        return true;
    }

    /**
     * Validiert einen Tabellennamen und unterstützt auch Schema-Präfixe
     *
     * @param string $table Zu validierender Tabellenname
     * @return bool True, wenn der Tabellenname gültig ist
     */
    private function validate_table_name(string $table): bool
    {
        // Erlaubt Schema.Tabelle Format sowie Bindestriche in Tabellennamen
        return preg_match('/^([a-zA-Z0-9_-]+\.)?[a-zA-Z0-9_-]+$/', $table);
    }

    /**
     * Löscht Datensätze aus der Datenbank
     *
     * @param string $table Tabellenname
     * @param array $where WHERE-Bedingungen
     * @param string $limit Limit (optional)
     * @return bool True bei Erfolg
     * @throws QueryException Bei Abfragefehlern
     */
    public function delete(string $table, array $where = [], string $limit = ''): bool
    {
        self::$counter++;

        if (empty($where)) {
            throw new QueryException("Keine WHERE-Bedingungen zum Löschen angegeben");
        }

        // Verbesserte Tabellennamen-Validierung
        if (!$this->validate_table_name($table)) {
            throw new QueryException("Ungültiger Tabellenname: " . $table);
        }

        $where_clauses = [];
        $params = [];

        foreach ($where as $field => $value) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
                throw new QueryException("Ungültiger Feldname in WHERE-Klausel: " . $field);
            }

            $where_clauses[] = "`$field` = ?";
            $params[] = $value;
        }

        $query = "DELETE FROM `$table` WHERE " . implode(' AND ', $where_clauses);

        if (!empty($limit)) {
            // Limit validieren
            if (!preg_match('/^\d+$/', $limit)) {
                throw new QueryException("Ungültiges Limit: " . $limit);
            }
            $query .= " LIMIT $limit";
        }

        $this->query($query, $params);

        return true;
    }

    /**
     * Gibt die ID des zuletzt eingefügten Datensatzes zurück
     *
     * @return int ID des zuletzt eingefügten Datensatzes
     */
    public function lastid(): int
    {
        self::$counter++;
        return $this->link->insert_id;
    }

    /**
     * Gibt die Anzahl der betroffenen Zeilen zurück
     *
     * @return int Anzahl der betroffenen Zeilen
     */
    public function affected(): int
    {
        return $this->link->affected_rows;
    }

    /**
     * Gibt die Anzahl der Felder in einer Abfrage zurück
     *
     * @param string $query SQL-Abfrage
     * @param array $params Parameter für die Abfrage (optional)
     * @return int Anzahl der Felder
     * @throws QueryException Bei Abfragefehlern
     */
    public function num_fields(string $query, array $params = []): int
    {
        self::$counter++;

        $result = $this->query($query, $params);

        if (!$result instanceof mysqli_result) {
            throw new QueryException("Ungültiges Abfrageergebnis");
        }

        return $result->field_count;
    }

    /**
     * Gibt eine Liste der Felder in einer Abfrage zurück
     *
     * @param string $query SQL-Abfrage
     * @param array $params Parameter für die Abfrage (optional)
     * @return array Liste der Felder
     * @throws QueryException Bei Abfragefehlern
     */
    public function list_fields(string $query, array $params = []): array
    {
        self::$counter++;

        $result = $this->query($query, $params);

        if (!$result instanceof mysqli_result) {
            throw new QueryException("Ungültiges Abfrageergebnis");
        }

        return $result->fetch_fields();
    }

    /**
     * Leert mehrere Tabellen
     *
     * @param array $tables Liste der zu leerenden Tabellen
     * @return int Anzahl der geleerten Tabellen
     * @throws QueryException Bei Abfragefehlern
     */
    public function truncate(array $tables = []): int
    {
        if (empty($tables)) {
            return 0;
        }

        $truncated = 0;

        foreach ($tables as $table) {
            // Tabellennamen validieren
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
                throw new QueryException("Ungültiger Tabellenname: " . $table);
            }

            $truncate = "TRUNCATE TABLE `" . trim($table) . "`";
            $this->query($truncate);
            $truncated++;
            self::$counter++;
        }

        return $truncated;
    }

    /**
     * Zeigt eine Variable an oder gibt sie zurück
     *
     * @param mixed $variable Anzuzeigende Variable
     * @param bool $echo Gibt die Variable aus, wenn true, sonst wird sie zurückgegeben
     * @return string|void Formatierte Variable, wenn $echo false ist
     */
    public function display(mixed $variable, bool $echo = true): ?string
    {
        $out = '';

        if (!is_array($variable)) {
            $out .= htmlspecialchars((string)$variable, ENT_QUOTES, 'UTF-8');
        } else {
            $out .= '<pre>';
            $out .= htmlspecialchars(print_r($variable, true), ENT_QUOTES, 'UTF-8');
            $out .= '</pre>';
        }

        if ($echo) {
            echo $out;
            return null;
        } else {
            return $out;
        }
    }

    /**
     * Gibt die Gesamtzahl der ausgeführten Abfragen zurück
     *
     * @return int Anzahl der Abfragen
     */
    public function total_queries(): int
    {
        return self::$counter;
    }

    /**
     * Gibt eine Singleton-Instanz der Datenbankklasse zurück
     *
     * @return Database Instanz der Datenbankklasse
     * @throws ConnectionException Wenn keine Instanz vorhanden ist
     */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            throw new ConnectionException("Es wurde keine Datenbankinstanz erstellt");
        }

        return self::$instance;
    }

    /**
     * Erstellt eine Singleton-Instanz der Datenbankklasse
     *
     * @param string $DB_HOST Hostname des Datenbankservers
     * @param string $DB_USER Benutzername für die Datenbankverbindung
     * @param string $DB_PASS Passwort für die Datenbankverbindung
     * @param string $DB_NAME Name der Datenbank
     * @return Database Instanz der Datenbankklasse
     */
    public static function createInstance(string $DB_HOST, string $DB_USER, string $DB_PASS, string $DB_NAME): Database
    {
        if (self::$instance === null) {
            self::$instance = new Database($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
        }

        return self::$instance;
    }

    /**
     * Schließt die Datenbankverbindung
     */
    public function disconnect(): void
    {
        $this->link->close();
    }
}