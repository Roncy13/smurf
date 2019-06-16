<?php 

	
	interface Api {
		public function sanitazion();
		public function middleware();
		public function run();
	}

	require_once SITE_ROOT."/api/Libraries/GUMP-master/gump.class.php";
	require_once SITE_ROOT."/api/Libraries/Response.php";
	require_once SITE_ROOT."/api/Libraries/constants.php";

	class Controller extends Response implements Api {
		public $data;
		public $body;
		public $params;
		public $get;
		
        public function sanitazion() {

        }

		public function middleware() {

		}

		function run() {
			echo "Controller";
		}
		
		function __construct($body = array(), $params = array(), $get= array(), ...$args) {
			$this->gump = new GUMP();
			$this->body = $body;
			$this->params = $params;
			$this->get = $get;

			foreach($args as $arg) {
				$this->{str_replace("_", "", $arg->showTableName()."Model")} = $arg;
			}

			$this->sanitazion();
			$this->middleware();
			$this->run();
		}

		function validationErr($rules, $data = array()) {
			$gump = $this->gump;

			$this->body = $gump->sanitize($this->body);
			$data = (empty($data)) ? $this->body : $gump->sanitize($data);
			$data = $gump->sanitize($data);
            $gump->validation_rules($rules);
            $validated_data = $gump->run($data);
            
            if($validated_data === false) {
                $this->send($gump->get_errors_array(), "Validation Error...!", 400);
			}
			$this->setBody($rules, $data);
		}

		function setBody($rules, $data = array()) {
			$body = array();

			foreach($rules as $index => $value) {
				$body[$index] = (empty($data)) ? $this->body[$index] : $data[$index];
			}

			$this->body = $body;
		}
	}
?>