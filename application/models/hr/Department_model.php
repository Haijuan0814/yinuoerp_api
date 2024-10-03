<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Department_model extends CI_Model {

    public $tablename = 'user_department';
    public $tablekey = 'id';

    public function __construct() {
        parent::__construct();
    }
    
    public function row($id,$select = '') {
        if (!$select) $select = 'id,name';
        $row = $this->db->select($select)->where(array('id' => $id))->get($this->tablename)->row();
        return $row;
        /*
        $key = __CLASS__.__FUNCTION__ . $id . $select ;
        if (!$this->cache->memcached->get($key)) { 
            if (!$select) $select = 'id,name';
            $row = $this->db->select($select)->where(array('id' => $id))->get($this->tablename)->row();
            $this->cache->memcached->save($key, $row, 3600);        
        }
        return $this->cache->memcached->get($key);*/
    }
    
    public function name($id) {
        //$row = $this->row($id);
        $row = $this->db->where(array('id' => $id))->get($this->tablename)->row();
        return $row ? $row->name : "";
    }
    
    //SEARCH的时候，搜索的部门是含下级部门IDS的
    public function get_children_ids($id = 0,$is_not_project=0) {
        $cache_key = 'Department_model::get_children_ids::A::' . $id;
        $data = $this->cache->memcached->get($cache_key);
        if (!$data||true) {
            /*查询子部门非项目部门*/
            if($is_not_project){
                $this->db->where('is_project',0);
            }
            $rows = $this->db->select("id,name,parent")->get("base_department")->result();
            $data = $this->children_foreach($rows, $id);
            array_push($data, $id);
        }
        return $data;
    }
    
    private function children_foreach($rows, $parent) {
        $ret = array();
        foreach ($rows as $row) {
            if ($row->parent == $parent) {
                array_push($ret, $row->id);
                $ret = array_merge($ret, $this->children_foreach($rows, $row->id));
            }
        }
        return $ret;
    }
    
    //获取指定模块的审批人，目前模块有行政审批hr+财务审批finance
    public function get_leader($id){
        $admin_uid=1002;//终极审批人：王浩洋
        $row = $this->row($id,"id,name,parent,uid");
        if($row){
            if(!$row->uid){
                if ($row->parent>0) {
                    return $this->get_leader($row->parent);
                }else{
                    return $admin_uid;
                }
            }else{
                return $row->uid;
            }
            
        }else{
            return $admin_uid;
        }
    }
}
