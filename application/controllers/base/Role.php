<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Role extends MY_Controller {
    
    public $tablename = 'base_role';
    public $tablekey = 'id';

    public function __construct() {
        parent::__construct();
    }
    //列表
    public function index() {
        $this->db->where("is_delete",0)->order_by("listorder");
        $rows = $this->db->get($this->tablename)->result();
        json_success(array(
            "rows" => $rows,
        ));
        json_success(lang('success'));
    }
    
    //新的
    public function save() {
        $data = array();
        foreach (array('name','menus','listorder','remark') as $field) {
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
}