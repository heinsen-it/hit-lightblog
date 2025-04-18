<?php

namespace hitlightblog\app\core;

class view
{
    function __construct()
    {

    }


    public function render(string $path, array $data) {
        $path = DOCROOT."/app/views/$path.php";
        if(file_exists($path))
        {
            require $path;
        }
        else
        {
         file_put_contents($path, 'Dieses View('.$path.') ist leer und automatisch generiert.</br>');
         require $path;
         unlink($path);
        }
    }


}