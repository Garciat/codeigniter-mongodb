<?php

/**
 * CodeIgniter MongoDB Active Record driver
 * 
 * A library that interfaces with MongoDB through Active Record functions.
 * 
 * @author		Gabriel Garcia
 * @link		http://gbrlgrct.com/
 */
class Mongo_db
{
	private $CI;
	private $required = array('Mongo_db_result');
	private $errors;
	
	private $config;
	
	private $db_info = array(
		'host' => NULL,
		'port' => NULL,
		'user' => NULL,
		'pass' => NULL,
		'database' => NULL,
		'persist' => NULL,
		'persist_key' => NULL,
		'debug' => NULL
	);
	private $connection_string;
	
	private $connection;
	private $db;
	
	private $all_tables;
	
	private $table;
	private $selects = array();
	private $wheres = array();
	private $sorts = array();
	private $limit;
	private $offset;
	private $data = array();
	
	function __construct($config=NULL)
	{
		$this->CI =& get_instance();
		
		//Load required libraries
		foreach($this->required as $library)
		{
			if(!in_array($library, array_keys($this->CI->load->_ci_classes)))
				$this->CI->load->library($library);
		}
		
		$this->config = $config;
	}
	
	/**
	 * ==============================
	 *         PUBLIC FUNCTIONS
	 * ==============================
	 */
	
	/**
	 * Similar to $CI->load->database()
	 * Connects to database.
	 * 
	 * @param $param Mixed
	 * DSN, group name, or config array
	 * 
	 * @param $multi Bool
	 * Return a new instance (true) or existing (false)
	 */
	public function load($param='default', $multi=FALSE)
	{
		if(!is_null($this->connection) && !$multi)
			$this->_error('Database already loaded. If you need to connect to more than one database simultaneously, you can set the <u>second parameter</u> for the <i>load()</i> method as TRUE.');
		
		//Get $db_info from $param
		switch(gettype($param))
		{
			case 'string':
				//DSN (Data Source Name)
				if(substr($param, 0, 10) == 'mongodb://')
				{
					$db_info = $this->_validate_dsn($param);
					if($db_info === FALSE)
						$this->_error('Invalid DB connection string. Format: mongodb://[<u>user</u>:<u>password</u>@]<u>host</u>[:<u>port</u>]/<u>database</u>', FALSE);
				}
				//Group name
				else
				{
					if(!isset($this->config[$param]))
						$this->_error('You have specified an invalid database connection group.', FALSE);
					$db_info = isset($this->config[$param]) ? $this->config[$param] : FALSE;
				}
			break;
			
			case 'array':
				$db_info = $param;
			break;
		}
		
		//Simultaneous connection requested
		if($multi)
		{
			$new = new $this($this->config);
			$new->load($db_info);
			return $new;
		}
		
		$this->_db_connect($db_info);
	}
	
	/**
	 * ====================
	 * MongoDB Data
	 * ====================
	 */
	
	/**
	 * Returns 'MongoDB'
	 * 
	 * @return String
	 */
	public function platform()
	{
		return 'MongoDB';
	}
	
	/**
	 * Returns MongoDB's Version
	 * 
	 * @return String
	 */
	public function version()
	{
		return Mongo::VERSION;
	}
	
	/**
	 * ====================
	 * Selecting Data
	 * ====================
	 */
	
	/**
	 * Gets a table or finalizes chain.
	 * 
	 * @param $table String
	 * Table name
	 * 
	 * @param $limit Integer >= 0
	 * Limits the amount of rows selected
	 * 
	 * @param $offset Integer >= 0
	 * Jumps X number of rows
	 * 
	 * @return Object (Mongo_db_result)
	 */
	public function get($table=NULL, $limit=NULL, $offset=NULL)
	{
		$this->_debug();
		
		$this->from($table)->limit($limit, $offset);
		
		return $this->_query();
	}
	
	/**
	 * Get method mask + conditions
	 * 
	 * @param $table String
	 * Table name
	 * 
	 * @param $where Array
	 * Where statements in Field=>Value format
	 * 
	 * @param $limit Integer >= 0
	 * Limits the amount of rows selected
	 * 
	 * @param $offset Integer >= 0
	 * Jumps X number of rows
	 * 
	 * @return Object (Mongo_db_result)
	 */
	public function get_where($table=NULL, $where=NULL, $limit=NULL, $offset=NULL)
	{
		$this->where($where);
		return $this->get($table, $limit, $offset);
	}
	
	/**
	 * Adds fields to be selected
	 * 
	 * @param $string String
	 * Comma-separated list of fields
	 * 
	 * @return Object (Mongo_db)
	 */
	public function select($string='')
	{
		$selects = explode(',', $string);
		foreach($selects as $select)
			$this->selects[trim($select)] = 1;
		
		return $this;
	}
	
	/**
	 * 
	 */
	public function select_min($field, $alias=NULL)
	{
		
	}
	
	/**
	 * 
	 */
	public function select_max($field, $alias=NULL)
	{
		
	}
	
	/**
	 * 
	 */
	public function select_avg($field, $alias=NULL)
	{
		
	}
	
	/**
	 * 
	 */
	public function select_sum($field, $alias=NULL)
	{
		
	}
	
	/**
	 * Adds table to select from
	 * 
	 * @param $table String
	 * Table name
	 * 
	 * @return Object (Mongo_db)
	 */
	public function from($table=NULL)
	{
		$this->_debug();
		
		if(is_null($table))
			return $this;
		
		$this->table = $table;
		
		return $this;
	}
	
	/**
	 * 
	 */
	public function join($table, $where, $type)
	{
		
	}
	
	/**
	 * Adds conditions
	 * 
	 * @param Mixed
	 * String - The condition's field + operator (separated by a space).
	 * Array - Field=>Value format.
	 * 
	 * @param String
	 * The condition's value.
	 * Required only if first parameter is a string.
	 * 
	 * @return Object (Mongo_db)
	 */
	public function where()
	{
		$args = func_get_args();
		
		if(is_null($args[0]))
			return;
		
		$wheres = array();
		if(is_array($args[0]))
			$wheres = $args[0];
		else
			$wheres[$args[0]] = $args[1];
		
		$add = array();
		
		foreach($wheres as $field=>$value)
		{
			$field = explode(' ', $field, 2);
			if(count($field)==1)
			{
				$add[$field[0]] = $value;
				continue;
			}
			switch($field[1])
			{
				case '=':
					$add[$field[0]] = $value;
				break;
				
				case 'like':
				case 'LIKE':
					$this->like($field[0], $value);
				break;
				
				case '>':
					$add[$field[0]]['$gt'] = $value;
				break;
				
				case '>=':
					$add[$field[0]]['$gte'] = $value;
				break;
				
				case '<':
					$add[$field[0]]['$lt'] = $value;
				break;
				
				case '<=':
					$add[$field[0]]['$lte'] = $value;
				break;
				
				case '<>':
				case '!=':
					$add[$field[0]]['$ne'] = $value;
				break;
				
				case 'in':
				case 'IN':
					$add[$field[0]]['$in'] = $value;
				break;
				
				case 'not in':
				case 'NOT IN':
					$add[$field[0]]['$nin'] = $value;
				break;
			}
		}
		
		if(isset($args[3]))
			$this->wheres = array('$or'=>array($this->wheres, $add));
		else
			$this->wheres = array_merge($this->wheres, $add);
		
		return $this;
	}
	
	/**
	 * Turns conditions into: current OR new.
	 * 
	 * @param $field String
	 * 
	 * @param $value String
	 * 
	 * @return Object (Mongo_db)
	 */
	public function or_where($field=NULL, $value=NULL)
	{
		return $this->where($field, $value, true);
	}
	
	/**
	 * Adds a new IN condition
	 * 
	 * @param $field String
	 * 
	 * @param $array Array
	 * Array of possible values
	 * 
	 * @return Object (Mongo_db)
	 */
	public function where_in($field, $array)
	{
		return $this->where(array($field.' IN' => $array));
	}
	
	/**
	 * 
	 */
	public function or_where_in($field, $array)
	{
		return $this->or_where(array($field.' IN' => $array));
	}
	
	/**
	 * 
	 */
	public function where_not_in($field, $array)
	{
		return $this->where(array($field.' NOT IN' => $array));
	}
	
	/**
	 * 
	 */
	public function or_where_not_in($field, $array)
	{
		return $this->or_where(array($field.' NOT IN' => $array));
	}
	
	/**
	 * 
	 */
	public function like($field, $match, $position='both', $extra=NULL)
	{
		if(is_array($field))
		{
			foreach($field as $k=>$v)
				$tmp[$k.' LIKE'] = $v;
			return $this->where($tmp);
		}
		
		$match = quotemeta($match);
		
		switch($position)
		{
			case 'before':
				$regex = "/{$match}$/";
			break;
			case 'after':
				$regex = "/^{$match}/";
			break;
			case 'both':
				$regex = "/{$match}/";
			break;
		}
		
		$add[$field] = new MongoRegex($regex);
		
		if($extra)
		{
			if(strpos($extra, 'not') !== FALSE)
				$add[$field] = array('not' => $add[$field]);
			
			if(strpos($extra, 'or') !== FALSE)
				$this->wheres = array('$or' => array($this->wheres, $add));
		}
		else
			$this->wheres = array_merge($this->wheres, $add);
		
		return $this;
	}
	
	/**
	 * 
	 */
	public function or_like($field, $match, $position='both')
	{
		return $this->like($field, $match, $position, 'or');
	}
	
	/**
	 * 
	 */
	public function not_like($field, $match, $position='both')
	{
		return $this->like($field, $match, $position, 'not');
	}
	
	/**
	 * 
	 */
	public function or_not_like($field, $match, $position='both')
	{
		return $this->like($field, $match, $position, 'or not');
	}
	
	/**
	 * 
	 */
	public function group_by()
	{
		
	}
	
	/**
	 * 
	 */
	public function distinct()
	{
		
	}
	
	/**
	 * 
	 */
	public function having()
	{
		
	}
	
	/**
	 * 
	 */
	public function or_having()
	{
		
	}
	
	/**
	 * 
	 */
	public function order_by()
	{
		
	}
	
	/**
	 * 
	 */
	public function limit($limit=NULL, $offset=NULL)
	{
		$this->_debug();
		
		if(!is_null($limit))
			$this->limit = $limit;
		if(!is_null($offset))
			$this->offset = $offset;
		
		return $this;
	}
	
	/**
	 * 
	 */
	public function count_all_results()
	{
		return $this->get()->num_rows();
	}
	
	/**
	 * 
	 */
	public function count_all($table)
	{
		return $this->load($this->db_info, TRUE)->get($table)->num_rows();
	}
	
	/**
	 * ====================
	 * Inserting Data
	 * ====================
	 */
	
	/**
	 * 
	 */
	public function set()
	{
		$args = func_get_args();
		
		if(is_null($args[0]))
			return;
		
		$data = array();
		if(is_array($args[0]))
			$data = $args[0];
		else
			$data[$args[0]] = $args[1];
		
		$this->data = array_merge($this->data, $data);
		
		return $this;
	}
	
	/**
	 * 
	 */
	public function insert($table=NULL, $data=NULL)
	{
		$this->_debug();
		
		$this->from($table)->set($data);
		
		if($this->db_info['debug']===TRUE)
		{
			try
			{
				$this->db->{$this->table}->insert($this->data, array('safe'=>TRUE));
			}
			catch(Exception $e)
			{
				$this->_error('Unable to insert: '.$e->message());
			}
		}
		else
			$this->db->{$this->table}->insert($this->data);
		
		return true;
	}
	
	/**
	 * 
	 */
	public function update($table=NULL, $data=NULL, $where=NULL)
	{
		$this->_debug();
		
		$this->from($table)->set($data)->where($where);
		
		if($this->db_info['debug']===TRUE)
		{
			try
			{
				$this->db->{$this->table}->update($this->wheres, $this->data, array('safe'=>TRUE));
			}
			catch(Exception $e)
			{
				$this->_error('Unable to update: '.$e->message());
			}
		}
		else
			$this->db->{$this->table}->update($this->wheres, $this->data);
		
		return true;
	}
	
	/**
	 * 
	 */
	public function delete($table=NULL, $where=NULL, $all=FALSE)
	{
		$this->_debug();
		
		$this->from($table)->where($where);
		
		if($this->db_info['debug']===TRUE)
		{
			try
			{
				$this->db->{$this->table}->remove($this->wheres, array('safe'=>TRUE));
			}
			catch(Exception $e)
			{
				$this->_error('Unable to remove: '.$e->message());
			}
		}
		else
			$this->db->{$this->table}->remove($this->wheres);
		
		return true;
	}
	
	/**
	 * 
	 */
	public function empty_table($table=NULL)
	{
		return $this->delete($table, NULL, TRUE);
	}
	
	/**
	 * 
	 */
	public function truncate($table=NULL)
	{
		$this->_debug();
		
		$this->from($table);
		
		$result = $this->db->{$this->table}->drop();
		
		if(empty($result['ok']) && $this->db_info['debug']===TRUE)
			$this->_error('Could not delete table "'.$this->table.'".');
		
		$this->db->createCollection($table);
		
		return true;
	}
	
	/**
	 * ====================
	 * Table Data
	 * ====================
	 */
	
	/**
	 * 
	 */
	public function list_tables()
	{
		$tables = array();
		foreach($this->db->listCollections() as $table)
			$tables[] = $table->getName();
		//Save tables
		$this->all_tables = $tables;
		return $tables;
	}
	
	/**
	 * 
	 */
	public function table_exists($table)
	{
		if(!$this->all_tables)
			$this->list_tables();
		return in_array($table, $this->all_tables);
	}
	
	/**
	 * ====================
	 * Field Data
	 * ====================
	 */
	
	/**
	 * 
	 */
	public function list_fields()
	{
		
	}
	
	/**
	 * 
	 */
	public function field_exists($field)
	{
		
	}
	
	/**
	 * 
	 */
	public function field_data($field)
	{
		
	}
	
	/**
	 * ==============================
	 *        PRIVATE FUNCTIONS
	 * ==============================
	 */
	
	/**
	 * 
	 */
	private function _query()
	{
		$rows = array();
		
		$cursor = $this->db->{$this->table}
						->find($this->wheres)
						->fields($this->selects)
						->skip($this->offset)
						->limit($this->limit);
		
		while($row = $cursor->getNext())
		{
			unset($row['_id']);
			$rows[] = $row;
		}
		
		$this->_reset();
		
		return $this->CI->mongo_db_result->load($rows);
	}
	
	/**
	 * 
	 */
	private function _db_connect($db_info)
	{
		$this->db_info = array_merge($this->db_info, $db_info);
		
		//Build connection string
		$connection_string = 'mongodb://';
		if($this->db_info['user'] && $this->db_info['pass'])
			$connection_string .= $this->db_info['user'].':'.$this->db_info['pass'].'@';
		$connection_string .= $this->db_info['host'];
		if($this->db_info['port'])
			$connection_string .= ':'.$this->db_info['port'];
		$this->connection_string = $connection_string;
		
		$options = array();
		if(isset($this->db_info['persist']) && $this->db_info['persist'] === TRUE)
			$options['persist'] = isset($this->db_info['persist_key']) && !empty($this->db_info['persist_key']) ? $this->db_info['persist_key'] : 'ci_mongo_persist';
		
		try
		{
			$this->connection = new Mongo($this->connection_string, $options);
		}
		catch(MongoConnectionException $e)
		{
			if($this->db_info['debug']===TRUE)
				$this->_error('Unable to connect to your database server using the provided settings.');
		}
		
		$this->_db_select($this->db_info['database']);
	}
	
	private function _db_select($database)
	{
		$dbs = array();
		foreach(array_shift($this->connection->listDBs()) as $db)
			$dbs[] = $db['name'];
		
		if($this->db_info['debug']===TRUE)
			if(!in_array($database, $dbs))
				$this->_error('Unable to select the specified database: "'.$database.'"');
		
		try
		{
			$this->db = $this->connection->{$database};
		}
		catch(Exception $e)
		{
			if($this->db_info['debug']===TRUE)
				$this->_error('Unable to select the specified database: "'.$database.'"');
		}
	}
	
	/**
	 * 
	 */
	private function _reset()
	{
		$this->table = '';
		$this->selects = array();
		$this->wheres = array();
		$this->sorts = array();
		$this->limit;
		$this->offset;
		$this->data = array();
	}
	
	/**
	 * Validates a MongoDB DSN string
	 */
	private function _validate_dsn($string)
	{
		//Heh, my first successful regex
		if(!preg_match('/mongodb:\/\/([^:]+[:][^@]+[@])?([^:]+)([:][0-9]+)?\/(.+)/', $string, $matches))
			return false;
		
		$connection = array(
			'host' => $matches[2],
			'port' => NULL,
			'user' => NULL,
			'pass' => NULL,
			'database' => $matches[4]
		);
		
		//user:pass@
		if(!empty($matches[1]))
		{
			$tmp = explode(':', substr($matches[1], 0, -1));
			$connection['user'] = $tmp[0];
			$connection['pass'] = $tmp[1];
		}
		
		//:port
		if(!empty($matches[3]))
			$connection['port'] = intval(substr($matches[3], 1));
		
		return $connection;
	}
	
	/**
	 * 'Fixes' an array by defining the number of keys specified as NULL
	 */
	private function _array_fix(&$array, $n)
	{
		for($i=0;$i<$n;$i++)
			$array[$i] = isset($array[$i]) ? $array[$i] : NULL;
	}
	
	/**
	 * 
	 */
	private function _debug()
	{
		if($this->db_info['debug']!==TRUE) return;
		
		//Get method name and arguments
		$method = next(debug_backtrace(false));
		$action = $method['function'];
		$args = $method['args'];
		
		if(is_null($this->connection))
			$this->_error('Database not loaded. Use <i>load()</i> method.');
		
		switch($action)
		{
			case 'from':
				$this->_array_fix($args, 1);
				list($table) = $args;
				
				if(is_null($table))
					return;
				
				$this->list_tables();
				
				if(!in_array($table, $this->all_tables))
					$this->_error("Table '{$this->db_info['database']}.{$table}' doesn't exist.");
			break;
			
			case 'limit':
				$this->_array_fix($args, 2);
				list($limit, $offset) = $args;
				
				if(!is_null($limit) && (!is_int($limit) OR $limit<0))
					$this->_error('Limit must be a nonnegative integer.');
				
				if(!is_null($offset) && (!is_int($offset) OR $offset<0))
					$this->_error('Offset must be a nonnegative integer.');
			break;
			
			case 'insert':
			case 'update':
			case 'truncate':
				$this->_array_fix($args, 4);
				list($table, $data, $where, $all) = $args;
				
				if(empty($table) && empty($this->table))
					$this->_error('You must set the database table to be used with your query.');
				
				if(($action=='insert' OR $action=='update') && empty($data) && empty($this->data))
					$this->_error('You must use the "set" method to update an entry.');
			break;
			
			case 'delete':
				$this->_array_fix($args, 3);
				list($table, $where, $all) = $args;
				
				if(empty($table) && empty($this->table))
					$this->_error('You must set the database table to be used with your query.');
				
				if(!$all && empty($where) && empty($this->wheres))
					$this->_error('Deletes are not allowed unless they contain a "where" or "like" clause.');
			break;
		}
	}
	
	/**
	 * 
	 */
	private function _error($message, $info=TRUE)
	{
		//This just works. Don't ask why.
		$debug = debug_backtrace(false);
		while(true) {
			$current = current($debug);
			if(!isset($current['file']) && !isset($current['line']))
				break;
			next($debug);
		}
		$callee = prev($debug);
		$method = $callee['function'];
		
		$error = '<h2>MongoDB Error</h2>';
		if($info)
			$error .= "<p>Method: <b>{$method}</b>.</p>";
		
		$error .= '<p style="padding:10px;border:1px solid #C88;background:#FFE0E0">';
		if($message === TRUE)
			$error .= implode('<br/><br/>', $this->errors);
		else
			$error .= $message;
		$error .= '</p>';
		
		if($info)
			$error .= "<p>Error in: <b>{$callee['file']}</b> on line <b>{$callee['line']}</b>.</p>";
		
		show_error($error);
	}
}