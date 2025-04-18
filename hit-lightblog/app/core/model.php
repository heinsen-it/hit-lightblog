<?php
namespace hitlightblog\app\core;

class model
{
    protected $_db;

    public function __construct()
    {
         $this->_db = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    }

}