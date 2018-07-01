<?php 
class DB
{
	function __construct()
	{
		$this->pdo = new PDO('mysql:host=localhost;dbname=test', '', '');
		$this->_reset();
	}

	// insert into $table ($fields) values ($values) on duplicate key update $setValues
	// update $table $setValues $where
	// delete from $table $where
	// select $fields from $table $where $limit

	private $table;
	private $fields; //',,'
	private $data; //{}
	private $where; //{} ''
	private $limit; // ','

	private $isExec = true;
	private $isLog = false;
	private $isCreate = true;

	private function _reset()
	{
		$this->fields = '';
		$this->data = array();
		$this->where = array();
		$this->limit = '';
	}

	public function table($table)
	{
		$this->table = $table;
		return $this;
	}
	public function fields($fields)
	{
		$this->fields = $fields;
		return $this;
	}
	public function data($data)
	{
		$this->data = $data;
		return $this;
	}
	public function where($where)
	{
		// todo string=>array
		$this->where = $where;
		return $this;
	}
	public function limit($start, $size)
	{
		$this->limit = "$start,$size";
		return $this;
	}
	public function page($index=1, $size=10)
	{
		$start = ($index-1) * $size;
		$this->limit = "$start,$size";
		return $this;
	}



	public function getSql($type='')
	{
		if($type) $this->type = $type;

		$type = $this->type;
		$table = "`$this->table`";

		$fields = '`'.implode('`, `', array_keys($this->data)).'`'; // `id`, `name`
		$values = ':'.implode(', :', array_keys($this->data)); // :id, :name
		$_tmp = array();
		foreach ($this->data as $key => $value) {
			array_push($_tmp, "`$key`=:$key");
		}
		$setValues = implode(', ', $_tmp); // `id`=:id, `name`=:name

		$where = $this->where; // `id`=:id
		if (is_array($where)) {
			$_tmp = array();
			foreach ($where as $key => $value) {
				array_push($_tmp, "`$key`='$value'");
			}
			$where = implode(' and ', $_tmp);
		}

		$sql = '';
		switch ($type) {
			case 'save':
				$sql = "insert into $table ($fields) values ($values)"
					."  on duplicate key update $setValues";
				break;
			case 'insert':
				$sql = "insert into $table ($fields) values ($values)";
				break;
			case 'update':
				$sql = "update $table set $setValues";
				if ($where) $sql.= ' where '.$where;
				break;
			case 'delete':
				$sql = "delete from $table";
				if ($where) $sql.= ' where '.$where;
				break;
			case 'select':
				$fields = $this->fields;
				if (!$fields) $fields = '*'; // todo ``
				$limit = $this->limit;
				$sql = "select $fields from $table";
				if ($where) $sql.= ' where '.$where;
				if ($limit) $sql.= ' limit '.$limit;
				break;
			default:
				break;
		}
		return $sql;
	}


	public function query($sql, $args=array())
	{
		$statement = $this->pdo->prepare($sql);
		$executeResult = $statement->execute($args);

		$errorCode = $statement->errorCode();
		$errorInfo = $statement->errorInfo();
		$rowCount = $statement->rowCount(); // todo select 有的数据库可能不正确返回查询行数
		// var_dump($errorInfo);

		$data = $statement->fetchAll(PDO::FETCH_CLASS);
		return array(
			'sql' => $sql,
			'args' => $args,
			'data' => $data,
			// 'json' => json_encode($data),
			'rowCount' => $rowCount,
			'errorCode' => $errorCode,
			'errorInfo' => $errorInfo,
		);
	}

	public function createTable($table)
	{
		$sql = "create table if not exists `$table` (
			id int primary key not null auto_increment
		)";
		$rs =  $this->query($sql);

		if ($this->isLog) {
			echo "*************createTable**************";
			var_dump($rs);
		}

		return $rs;
	}
	public function addColumn($table, $data)
	{
		if ($this->isLog) {
			echo "*************addColumn**************";
		}
		foreach ($data as $key => $value) {
			if ($key == 'id') {
				$column = "`$key` int primary key not null auto_increment";
			}else{
				$column = "`$key` text";
			}
			$sql = "alter table `$table` add column $column";
			$rs = $this->query($sql);

			if ($this->isLog) {
				if ($rs['errorCode'] == '42S21') {
					var_dump("$key 已存在");
				}else{
					var_dump($rs);
				}
			}

		}

	}


	public function isLog($isLog=true)
	{
		$this->isLog = $isLog;
		return $this;
	}
	public function isExec($isExec=true)
	{
		$this->isExec = $isExec;
		return $this;
	}
	public function isCreate($isCreate=true)
	{
		$this->isCreate = $isCreate;
		return $this;
	}

	// todo save insert update return obj
	public function exec($loop=0, $rs='') // select insert updadate save delete
	{
		if ($loop>10) {
			if ($this->isLog) {
				echo 'exec $loop > 10';
			}
			var_dump($rs);
			return $rs;
		}

		$sql = $this->getSql();
		$data = $this->data;
		if (!$this->isExec) {
			var_dump($sql, $data);
			return;
		}

		$rs = $this->query($sql, $data);
		if ($this->isLog) {
			echo "*************exec**************";
			var_dump($rs);
		}

		// 表不存在
		if ($this->isCreate && $rs['errorCode'] == '42S02') {
			$_rs = $this->createTable($this->table);

			if ($_rs['errorCode'] == '00000') {
				return $this->exec($loop+1, $rs);
			}
		}
		// 列不存在
		if ($this->isCreate && $rs['errorCode'] == '42S22') {
			$this->addColumn($this->table, $this->data);
			$this->addColumn($this->table, $this->where);
			return $this->exec($loop+1, $rs);
		}
		

		$this->_reset();
		return $rs;
	}

	public function save($data='')
	{
		if($data) $this->data = $data;
		$this->type = 'save';
		$rs = $this->exec();
		return $rs;
	}
	public function insert($data='')
	{
		if($data) $this->data = $data;
		$this->type = 'insert';
		$rs = $this->exec();
		return $rs;
	}
	public function update($data='')
	{
		if($data) $this->data = $data;
		$this->type = 'update';
		$rs = $this->exec();
		return $rs;
	}
	public function delete($where='')
	{
		if($where) $this->where = $where;
		$this->type = 'delete';
		$rs = $this->exec();
		return $rs;
	}
	public function select($where='')
	{
		if($where) $this->where = $where;
		$this->type = 'select';
		$rs = $this->exec();
		return $rs;
	}

}


// $pdo = new PDO('mysql:host=localhost;dbname=test', '', '');
// $statement = $pdo->prepare('insert into `xtest` (`id`,`name`, `sex`) values (:id, :name, :sex)');
// $statement->execute(array('id'=>'456', 'name'=>'fuckfuck', 'sex'=>'1'));
// var_dump($statement, $statement->errorInfo());


$db = new DB();
$rs = $db->table('t1')
	// ->isCreate(false)
	// ->isExec(false)
	->isLog()
	// ->limit(1,2)
	// ->page(1, 1)
	->fields('*')
	// ->data(array('id'=>4, 'name'=>'n4'))
	// ->where('id=2') // todo addColumn
	// ->where(array('id'=>3))
	// ->where(array('id'=>4, 'name'=>'n4'))
	// ->select(array('id'=>3, 'name'=>'wsf3'));
	// ->save(array('id'=>3, 'name'=>'new user2', 'sex'=>1))
	// ->page(1,2)
	// ->getSql('update')
	// ->createTable(null, array('id'=>3, 'name'=>'new user'))
	// ->addColumn('test', array('id'=>3, 'name'=>'new user', 'date52'=>1))
	->select(
		// array('name'=>'new name', 'addr'=>'china', 'sex2'=>1)
	)
	;
// var_dump($rs);
