<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Mock extends CI_Controller {
    
    public $tablename = 'it_project';
    public $tablekey = 'id';

    public function __construct() {
        parent::__construct();
    }

    public function index() {
        print('api works')
    }
    
    public function _index() {
        $page = $this->input->get("page") ? $this->input->get("page") : 0;    
        if($this->input->get('title')){
            $this->db->where('(`title` like "%'.$this->input->get('title').'%")');
        }
        if($this->input->get('product_id')){
            $this->db->where('product_id',$this->input->get('product_id'));
        }
        if(strlen($this->input->get('status'))>0){
            $this->db->where('status',$this->input->get('status'));
        }
        $this->db->order_by('id desc');
        $rows = $this->db->get($this->tablename)->result();
        foreach($rows as $k=>$row){
            $row->user=array('name'=>'Qu Li Li','avatar'=>'');
            $row->template='在 @{group} 新建项目 @{project}';
        }
        json_success($rows);
    }

    public function all() {
        $page = $this->input->get("page") ? $this->input->get("page") : 0;    
        if($this->input->get('title')){
            $this->db->where('(`title` like "%'.$this->input->get('title').'%")');
        }
        if($this->input->get('product_id')){
            $this->db->where('product_id',$this->input->get('product_id'));
        }
        if(strlen($this->input->get('status'))>0){
            $this->db->where('status',$this->input->get('status'));
        }
        $this->db->order_by('id desc');
        $total = $this->db->count_all_results($this->tablename, false);
        $rows = $this->db->limit(config_item('page_per'), $page * config_item('page_per'))->get()->result();
        json_success(array(
            "list" => $rows,
            "total" => $total,
            'current' => $page,
            'pageSize' => config_item('page_per'),
        ));
    }
    
    public function delete() {
        $row = $this->db->where($this->tablekey,$this->input->post($this->tablekey))->get($this->tablename)->row();
        $this->permission($row);
        $this->db->where($this->tablekey,$this->input->post($this->tablekey))->delete($this->tablename);
        json_success(lang('success'));
    }
    
}