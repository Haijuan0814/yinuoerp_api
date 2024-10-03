<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Menu extends MY_Controller {
    
    public $tablename = 'base_menu';
    public $tablekey = 'id';

    public function __construct() {
        parent::__construct();
    }
    //列表
    public function index() {
        if($this->input->get("tab")=="key"){
            $this->db->where("table_name !=","");
        }
        $this->db->where("is_delete",0)->order_by("listorder");
        $rows = $this->db->get($this->tablename)->result();
        json_success(array(
            "rows" => $rows,
        ));
    }
    
    //新的
    public function save() {
        $data = array();
        foreach (array('parent','name','key','url','url_message','listorder','icon','is_fixed','type') as $field) {
            $data[$field] = $this->input->post($field);
        }
        if ($this->input->post($this->tablekey)) {
            $this->db->set($data)->where(array($this->tablekey => $this->input->post($this->tablekey)))->update($this->tablename);
        }else{
            $this->db->set($data)->insert($this->tablename);
        }
        json_success(lang('success'));
    }
    
    //删除
    public function delete() {
        $row = $this->db->where($this->tablekey,$this->input->post($this->tablekey))->get($this->tablename)->row();
        $this->db->set('is_delete',1)->where($this->tablekey,$this->input->post($this->tablekey))->update($this->tablename);
        json_success(lang('success'));
    }
    

    /*
    Anthor：shihaijuan
    Date：2021-12-23
    DESC：菜单权限
    */
    public function visible() {
        $roles = $this->db->where_in('id', explode(',', $this->user->roles))->get('base_role')->result();
        $array = $this->user->menus?explode(',',$this->user->menus):array();
        foreach ($roles as $role) {
            if (!$role->menus)
                continue;
            foreach (explode(',', $role->menus) as $m)
                $array[] = $m;
        }
        $ids = array_unique($array); //去重合并    
        $rows=array();
        if($ids){
            $this->db->where_in('id', $ids);
            $rows = $this->db->select('id,parent,name,url,app_page,icon,is_fixed,type')->where('is_delete',0)->order_by('listorder desc')->get($this->tablename)->result();
        }
        
        foreach ($rows as $row) {
            
        }
        json_success($rows);
    }
}