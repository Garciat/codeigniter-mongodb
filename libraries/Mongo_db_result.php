<?php

/**
 * CodeIgniter MongoDB Active Record driver
 * 
 * A library that interfaces with MongoDB through Active Record functions.
 * 
 * @author		Gabriel Garcia
 * @link		http://gbrlgrct.com/
 */
class Mongo_db_result
{
	protected $rows;
	protected $current_row;
	protected $num_rows;
	
	function __construct($rows=NULL)
	{
		$this->rows = $rows;
		$this->current_row = 0;
		$this->num_rows = count($rows);
	}
	
	/**
	 * 
	 */
	public function load($rows)
	{
		return (new $this($rows));
	}
	
	/*
	 * ====================
	 * Generating Results
	 * ====================
	 */
	
	/**
	 * 
	 */
	public function result()
	{
		return $this->result_object();
	}
	
	/**
	 * 
	 */
	public function result_object()
	{
		$rows = array();
		foreach($this->rows as $row)
			$rows[] = (object)$row;
		return $rows;
	}
	
	/**
	 * 
	 */
	public function result_array()
	{
		return $this->rows;
	}
	
	/**
	 * 
	 */
	public function row($n=1)
	{
		return (object)$this->row_array($n);
	}
	
	/**
	 * 
	 */
	public function row_array($n=1)
	{
		if($n >= $this->num_rows) $n = $this->num_rows;
		return $this->rows[$n-1];
	}
	
	/**
	 * 
	 */
	public function first_row($array=NULL)
	{
		$this->current_row = 1;
		return $this->_x_row($array);
	}
	
	/**
	 * 
	 */
	public function previous_row($array=NULL)
	{
		if($this->current_row-1!=0) $this->current_row--;
		return $this->_x_row($array);
	}
	
	/**
	 * 
	 */
	public function next_row($array=NULL)
	{
		if($this->current_row+1<=$this->num_rows) $this->current_row++;
		return $this->_x_row($array);
	}
	
	/**
	 * 
	 */
	public function last_row($array=NULL)
	{
		$this->current_row = $this->num_rows;
		return $this->_x_row($array);
	}
	
	/*
	 * ====================
	 * Result Helpers
	 * ====================
	 */
	
	/**
	 * 
	 */
	public function num_rows()
	{
		return $this->num_rows;
	}
	
	/**
	 * 
	 */
	public function num_fields()
	{
		$fields = array();
		foreach($this->rows as $row)
			foreach(array_keys($row) as $field)
				$fields[$field] = 1;
		return count($fields);
	}
	
	/**
	 * 
	 */
	public function free_result()
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
	private function _x_row($array)
	{
		$row = $this->row_array($this->current_row);
		return $array=='array' ? $row : (object)$row;
	}
}