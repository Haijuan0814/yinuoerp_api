<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class User_model extends CI_Model {

    public $tablename = 'user';
    public $tablekey = 'id';

    public function __construct() {
        parent::__construct();
    }

    //获取单行数据
    public function row($id,$select = '') {        
        if (!$select) $select = 'id,name,avatar,department_name';
        $row = $this->db->select($select)->where(array('id' => $id))->get($this->tablename)->row();
        //if ($row&&strpos($select,'avatar') !== false) $row->avatar = str_replace('http://','//', $row->avatar) . '_50p';
        return $row;

        //这里缓存暂时用不起来，需要继续研究一下
        $key = __CLASS__.__FUNCTION__ . $id . $select .'_4';
        if (!$this->cache->memcached->get($key) && $id) { 
            if (!$select) $select = 'id,name,avatar,department_name';
            $row = $this->db->select($select)->where(array('id' => $id))->get($this->tablename)->row();
            //if ($row&&strpos($select,'avatar') !== false) $row->avatar = str_replace('http://','//', $row->avatar) . '_50p';
            $this->cache->memcached->save($key, $row, 3600);
        }
        return $this->cache->memcached->get($key);
    }
    
    //获取多行数据
    public function rows($where='',$select='',$detail_select=''){
        //目前只考虑where，不考虑like等情况
        if($where){
            $where= json_decode($where, true);
            foreach($where as $k=>$v){
                $this->db->where($k,$v);
            }
        }
        if (!$select) $select = 'id,realname,photo,department_name';
        $rows = $this->db->select($select)->get($this->tablename)->result();

        foreach($rows as $row){
            //$row->avatar = str_replace('http://','//', $row->photo) . '_50p';
            if($detail_select){
                $detail=$this->db->select($detail_select)->where("uid",$row->id)->get("base_user_detail")->row();
                $row->detail=$detail;
            }
        }
        return $rows;
    }



    /*
    Author:shihaijuan
    Date:20211008 
    Desc:获取uid对应的name
    */
    public function name($uid){
        if(!$uid) return '';
        $select = 'id,name';
        $row = $this->row($uid,$select);
        return $row?$row->name:"";
    }
    

    /*
    Author:shihaijuan
    Date:20211008 
    Desc:获取uids对应的name集合
    */
    public function names($uids){
        if(!$uids) return '';
        $select = 'id,name';
        $rows = $this->db->select($select)->where_in("id",explode(",",$uids))->get($this->tablename)->result();
        $names=array();
        foreach($rows as $row){
            $names[]=$row->name;
        }
        return $names?implode(",",$names):"";
    }
}
