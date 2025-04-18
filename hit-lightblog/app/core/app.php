<?php
namespace hitlightblog\app\core;

class app {

    public  $_url;
    private $_controller = null;
    private $_defaultController;

    public function __construct() {
        session::init();
        $this->_getUrl();
    }

    public function setController($name) {
        $this->_defaultController = $name;
    }

    public function init() {
        $this->_loadExistingController();
    }

    private function _getUrl() {
        $url = isset($_GET['url']) ? rtrim($_GET['url'], '/') : NULL;
        $url = urlencode($url ?? '');
        $url = urldecode(htmlspecialchars($url));
        $this->_url = explode('/', $url);
    }



    private function _loadExistingController() {

        if(empty($this->_url[0])){
            $controllerName = 'start';
        } else {
            $controllerName = $this->_url[0] ?? 'start';
        }
         $actionName = $this->_url[1] ?? 'index';

        $namespacePrefix = 'hitlightblog\\app\\controllers\\';

        // Vollqualifizierter Klassenname des Controllers
        $controllerClassName = $namespacePrefix . $controllerName;

        // Überprüfe, ob der Controller existiert
        if (class_exists($controllerClassName)) {
            $controller = new $controllerClassName();

            // Überprüfe, ob die Aktionsmethode existiert
            if (method_exists($controller, $actionName)) {
                $controller->$actionName();
            } else {
                // Aktionsmethode nicht gefunden
                echo '404 - 1Seite nicht gefunden';
            }
        } else {
            // Controller nicht gefunden
            echo '404 - 2Seite nicht gefunden';
        }

    }


    private function _callControllerMethod()
    {
        unset($this->_url[0]);
        $method = 'index';

        if (is_callable(array($this->_controller, $this->_url[1]))) {
            $method = array_shift($this->_url);
        }

        $parameter = array_map("htmlspecialchars", $this->_url);
        call_user_func_array(array($this->_controller, $method), $parameter);
    }

    private function _error($error) {
        $this->_controller = new myerror($error);
        $this->_controller->index();
        die;
    }

}
