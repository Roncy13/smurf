<?php 
	
	define("SITE_ROOT", getcwd());
	define("PUBLIC_FOLDER", "http://localhost/project-ecommerce/public/");
	define("API_SERVICE", SITE_ROOT."/api/Core/Service.php");
	define("API_MODEL", SITE_ROOT."/api/Core/Model.php");
	define("MODELS", SITE_ROOT."/api/Models/");
	define("SERVICE_FOLDER", SITE_ROOT."/api/Services/");

	require './api/routes.php';

	function url() {
	    if(isset($_SERVER['HTTPS'])){
	        $protocol = ($_SERVER['HTTPS'] && $_SERVER['HTTPS'] != "off") ? "https" : "http";
	    }
	    else{
	        $protocol = 'http';
	    }
	    return $protocol . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	}

	function getUrlVar() {
		$current_url = explode("/", $_SERVER['REQUEST_URI']);
		$current_url = $current_url[end(array_keys($current_url))];
		return $current_url;
	}

	$apiDir = 'index.php/api';
	$publicDir = 'index.php/public';

	$requestType = $_SERVER["REQUEST_METHOD"];
	$pathInfo = substr($_SERVER["PATH_INFO"], 1);
	
	if (strpos(url(), $apiDir)) {
		$GET = array();
		$body = array();
		$params = array();
			
		if ($requestType === "GET") {
		    $GET = $_GET;
		} else if ($requestType === "POST") {
			$body = $_POST;
			parse_str($_SERVER["QUERY_STRING"], $GET);
		}
		else {
			parse_str(file_get_contents('php://input'), $body);
		}

		foreach($routes as $key => $value) {
			$route = explode("/:", $key);
			$path = $route[0];
			if (strpos($pathInfo, $path) !== false) {
				$pathExplode = array_filter(array_values(explode($path, $pathInfo)));
				$requestMethod = explode(" ", $value);
				$pathRoute = array();
				
				if ($requestType === $requestMethod[0]) {
					if (count($pathExplode) >= 1) {
						$pathExplode = array_values(array_filter(explode($path, $pathInfo)));
						$pathExplode = array_values(array_filter(explode("/", $pathExplode[0])));
						$pathRoute = $route;
						unset($pathRoute[0]);
						$pathRoute = array_values($pathRoute);
						if (count($pathRoute) === count($pathExplode)) {
							foreach($pathRoute as $_index => $_value) {
								$params[$_value] = $pathExplode[$_index];
							}
						}
					}
					
					if (count($pathRoute) === count($pathExplode)) {
						require "./api/Services/{$requestMethod[1]}.php";
						//header('Content-Type: application/json');
						$requestedController = new $controller($body, $params, $GET);
						exit;
					}
				}
			}
		}
		header('Content-Type: application/json');
		http_response_code(500);
		echo json_encode(array("message" => "NO API FOUND"));
	}
	else {
		header('Content-Type: application/json');
		$uri = $_SERVER['REQUEST_URI'];
		$response = array(
			"message" => "Request Not Found {$uri}"
		);
		http_response_code(500);
		echo json_encode($response);
	}
?>