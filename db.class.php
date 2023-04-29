<?php
/*
 * @Author: zeyudada
 * @Date: 2022-02-12 21:32:24
 * @LastEditTime: 2022-02-16 16:11:17
 * @Description: 数据库连接类库
 * @Q Q: zeyunb@vip.qq.com(1776299529)
 * @E-mail: admin@zeyudada.cn
 * 
 * Copyright (c) 2022 by zeyudada, All Rights Reserved. 
 */

// 连接数据库的框架
class DB
{
	var $link = null;
	public $queries = 0;
	private $db_host = null;
	private $db_user = null;
	private $db_pass = null;
	private $db_name = null;
	private $db_port = null;


	function __construct($db_host, $db_user, $db_pass, $db_name, $db_port)
	{
		$this->db_host = $db_host;
		$this->db_user = $db_user;
		$this->db_pass = $db_pass;
		$this->db_name = $db_name;
		$this->db_port = $db_port;
		$this->connect();
	}
	function connect()
	{
		$this->link = mysqli_connect($this->db_host, $this->db_user, $this->db_pass, $this->db_name, $this->db_port);
		

		if (!$this->link) die('Connect Error (' . mysqli_connect_errno() . ') ' . mysqli_connect_error());

		//mysqli_select_db($this->link, $db_name) or die(mysqli_error($this->link));


		mysqli_query($this->link, "SET sql_mode = ''");
		//字符转换，读库
		mysqli_query($this->link, "SET character set 'utf8'");
		mysqli_query($this->link, "SET NAMES 'utf8'");
	}
	function fetch($q)
	{
		return mysqli_fetch_assoc($q);
	}
	function get_row($q)
	{
		$this->queries++;
		$result = mysqli_query($this->link, $q);
		return mysqli_fetch_assoc($result);
	}

	//查询全部数据
	public function getAll($sql)
	{
		$res = $this->query($sql);
		if(empty($res)) return $this->error();
		$data = [];
		while ($row =  mysqli_fetch_assoc($res)) {
			$data[] = $row;
		}
		return $data;
	}
	function count($q)
	{
		$result = mysqli_query($this->link, $q);
		if(is_bool($result)) return 0;
		$count = mysqli_fetch_array($result);
		return $count[0];
	}
	function query($q)
	{
		$this->queries++;
		return mysqli_query($this->link, $q);
	}
	function escape($str)
	{
		return mysqli_real_escape_string($this->link, $str);
	}
	function insert($q)
	{
		if (mysqli_query($this->link, $q))
			return mysqli_insert_id($this->link);
		return false;
	}
	function affected()
	{
		return mysqli_affected_rows($this->link);
	}
	function insert_array($table, $array)
	{
		$q = "INSERT INTO `$table`";
		$q .= " (`" . implode("`,`", array_keys($array)) . "`) ";
		$q .= " VALUES ('" . implode("','", array_values($array)) . "') ";

		if (mysqli_query($this->link, $q))
			return mysqli_insert_id($this->link);
		return false;
	}
	function error()
	{
		$error = mysqli_error($this->link);
		$errno = mysqli_errno($this->link);
		return '[' . $errno . '] ' . $error;
	}
	function close()
	{
		$q = mysqli_close($this->link);
		return $q;
	}
}
