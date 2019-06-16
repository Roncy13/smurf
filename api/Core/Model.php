<?php
// Ensure reporting is setup correctly 
mysqli_report(MYSQLI_REPORT_STRICT); 
require SITE_ROOT.'/api/core/db.php';
require_once SITE_ROOT."/api/Libraries/Response.php";
require_once SITE_ROOT."/api/Libraries/constants.php";

Class Model extends Response {
	private $_columns = [];
	public $_conn;
	private $_connString;
	public  $_table;
	private $_where = [];
	private $_values = [];
	private $_joins = [];
	private $_select = null;
	private $_limit = "";
	public $_additional = "";
	public $_orderBy = "";

	function __construct($tableName = "") {
		global $CONFIG;
		$this->_table = ($tableName === "") ? str_replace("Model", "" , get_class($this)) : $tableName;
		$this->_connString = $CONFIG;
		$this->_setConnection();
	}

	function showTableName() {
		return $this->_table;
	}

	function mysqlError($message) {
		$this->send(array("MY_SQL_ERROR" => $message), "You have Error in MYSQL", 500);
	}

	function page($page, $number = 8) {
		$OFFSET = $number * ($page - 1);
		$this->_limit = "LIMIT {$number} OFFSET {$OFFSET}";
		$this->_orderBy = " ORDER BY ID DESC ";
	}

	function countRows() {
		$this->select('count(1) as rows');
        return $this->getOne()["rows"];
	}

	function create($data) {
		$params = [];
		$columns = [];
		$values = [];
		$qs = [];

		foreach($data as $key => $value) {
			$params[] = $this->getType($value);
			$columns[] = $key;
			$values[] = $value;
			$qs[] = "?";
		}

		$colStmt = implode(", ", $columns);
		$valStmt = implode(", ", $qs);
		$insertStmt = "INSERT INTO {$this->_table} ({$colStmt}) values ({$valStmt})";
		$stmt = $this->_conn->prepare($insertStmt);
		$stmt->bind_param(implode("", $params), ...$values);
		$stmt->execute();
		$insertId = $stmt->insert_id;

		if ($insertId === 0) {
			$this->mysqlError($this->_conn->error);
		}
		
		return $stmt->insert_id;
	}

	function update($data) {
		$whereStmt = $this->whereStmt();
		$values = [];
		$params = [];

		foreach($data as $key => $value) {
			$columns[] = "{$key} = ?";
			$values[] = $value;
			$params[] = $this->getType($value);
		}

		foreach($this->_values as $value) {
			$values[] = $value;
			$params[] = $this->getType($value);
		}

		$colStmt = implode(",", $columns);
		$stmt = "UPDATE {$this->_table} set {$colStmt} {$whereStmt}";
		$updateStmt = $this->_conn->prepare($stmt);
		$updateStmt->bind_param(implode("", $params), ...$values);

		$res = $updateStmt->execute();

		if ($res === false) {
			$this->mysqlError($this->_conn->error);
		}
		
		return $updateStmt->execute();
	}

	function clear() {
		 $this->_columns = [];
		 $this->_where = [];
		 $this->_values = [];
		 $this->_joins = [];
		 $this->_select = null;
		 $this->_limit = "";
		 $this->_additional = "";
		 $this->_orderBy = "";
	}

	function _setConnection() {
		$conn = $this->_connString;
		try {
			$this->_conn = new mysqli(
				$conn["localhost"],
				$conn["user"],
				$conn["password"],
				$conn["db"]
			);

			if ($this->_conn->connect_error) {
				die("MYSQL ERROR {mysqli_error()}");
			}
		} catch (Exception $e) {
			die($e->getMessage());
		}
	}

	function _setColumns() {
		$columns = [];
		$result = $this->_conn->query("
			SHOW COLUMNS FROM {$this->_table}
		");

		while($row = $result->fetch_assoc()) {
		    $columns[] = "{$this->_table}.{$row['Field']}";
		}

		$this->_columns = $columns;
	}

	function _getColumns() {
		return $this->_columns;
	}

	function where($col, $value, $operator = "=") {
		$question = ($operator === "IN") ? "({$value})" : '?';
		$this->_where[] = array( "key" => "AND" , "value" => "{$col} ${operator} {$question}" );

		if($operator !== "IN") $this->_values[] = $value;
	}

	function whereNull($col) {
		$this->_where[] = array( "key" => "AND" , "value" => "{$col} IS NULL" );
	}

	function search($col, $value) {
		$this->_where[] = array( "key" => "AND" , "value" => "{$col} LIKE ?" );
		$this->_values[] = "%{$value}%";
	}

	function orWhere($col, $value, $operator = "=") {
		$question = ($operator === "IN") ? '(?)' : '?';
		$this->_where[] = array( "key" => "OR" , "value" => "{$col} ${operator} {$question}" );
		$this->_values[] = $value;
	}

	function join($table, $colA, $colB, $operator = "=") {
		$this->_joins[] = array( "join" => "INNER JOIN {$table} ON", "value" => "${colA} ${operator} {$colB}" );
	}

	function leftJoin($table, $colA, $colB, $operator = "=") {
		$this->_joins[] = array( "join" => "LEFT JOIN {$table} ON", "value" => "${colA} ${operator} {$colB}" );
	}

	function select($cols = "*") {
		if ($cols !== "") {
			$this->_columns = explode(",", $cols);
		} else {
			$this->_setColumns();
		}

		$cols = implode(", ", $this->_columns);
		$tbl = strtolower($this->_table);
		$this->_select = "SELECT {$cols} FROM {$tbl} ";
	}

	function get() {
		$rows = $this->getRows();
		return $rows;
	}

	function getRows() {
		if ($this->_select === null) {
			$this->select();
		}

		$result = [];
		$joinStr = "";
		$whereStr = $this->whereStmt();

		if (!empty($this->_joins)) {
			foreach( $this->_joins as $join ) {
				$joinStr .= " {$join["join"]} {$join["value"]}";
			}
		}

		$this->_select .= " {$joinStr} {$whereStr} {$this->_additional} {$this->_orderBy} {$this->_limit}";
		$stmt = $this->_conn->prepare($this->_select);

		//adds value
		/*
			i - integer
			d - double
			s - string
			b - BLOB
		*/

		$params = [];

		if (!empty($this->_values)) {
			foreach($this->_values as $value ) {
				$val = $this->getType($value);
				$params[] = $val;
			}
			$stmt->bind_param(implode("", $params), ...$this->_values);
		}

		$stmt->execute();
		$queryRes = $stmt->get_result();

		while ($data = $queryRes->fetch_assoc()) {
		    $result[] = $data;
		}

		$this->clear();
		
		return $result;
	}

	function getOne() {
		$rows = $this->getRows();
		if (empty($rows)) {
			return array();
		}

		return $rows[0];
	}

	function inactive($id, $col = "id") {
		$this->where($col, $id);
		$this->update(array(
			"status" => INACTIVE
		));
	}

	function getActive($id, $col = "id", $active = ACTIVE) {
		$this->where($col, $id);
		$this->where("status", $active);
		return $this->getOne();
	}

	function active($id, $col = "id") {
		$this->where($col, $id);
		$this->update(array(
			"status" => ACTIVE
		));
	}
	

	function whereStmt() {
		if (!empty($this->_where)) {
			$whereStr = "";
			foreach( $this->_where as $where ) {
				if ($whereStr === "") {
					$whereStr .= " WHERE {$where["value"]}";
				} else {
					$whereStr .= " {$where["key"]} {$where["value"]}";
				}
			}
			return $whereStr;
		}
		return "";
	}

	function getType($value) {
		return strtolower(gettype($value)[0]);
	}
}
?>