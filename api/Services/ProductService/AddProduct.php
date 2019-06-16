<?php
    require API_SERVICE;
    
    class AddProduct extends Controller {
        
		function __construct($body = array(), $params = array(), $get = array()) {
            parent::__construct($body, $params, $get);
        }
        
        function run() {
            $this->send(
                $this->body,
                "Products Added Successfully...!",
                201
            );
        }
    }
    
    $controller = "AddProduct";
?>