<?php

namespace hitlightblog\app\core;




class controller extends main
{
    protected $_view;
    protected $_model;
    protected $_classname;

    function __construct()
    {
        parent::__construct();
        $this->_view = new view();
        $this->_classname = get_class($this);
        $class_parts = explode('\\', $this->_classname);
        $this->_classname = end($class_parts);

        try {
            $this->_classname = 'hitlightblog\app\models\\' . $this->_classname;
            if(class_exists($this->_classname)) {
                $this->_model = new $this->_classname();
            }
        } catch(\Exception $e){

        }

    }




}
