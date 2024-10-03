<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Account extends MY_Controller {
    
    public $tablename = 'finance_account';
    public $tablekey = 'id';

    public function __construct() {
        parent::__construct();
    }
    
    public function index() {
        //$page = $this->input->get("page") ? $this->input->get("page") : 0;    
        if($this->input->get('title')){
            $this->db->like('title',$this->input->get('title'));
        }
        if($this->input->get('books_id')){
            $this->db->where("(FIND_IN_SET('{$this->input->get('books_id')}',`books_ids`) and 1=1)");
        }
        if($this->input->get('subject_id')){
            $this->db->where('subject_id',$this->input->get('subject_id'));
        }
        $this->db->select('id,title')->where('is_delete',0);
        $rows = $this->db->get($this->tablename)->result();
        json_success($rows);
    }
    
    public function save() {
        $data = array();
        foreach (array('title','account_no','books_ids','subject_id','balance') as $field) {
            $data[$field] = $this->input->post($field);
        }
        if ($this->input->post($this->tablekey)) {
            $this->db->set($data)->where(array($this->tablekey => $this->input->post($this->tablekey)))->update($this->tablename);
        }else{
            $data = array_merge($data,array(
                'insert_time' => time()
            ));
            $this->db->set($data)->insert($this->tablename);
        }        
        json_success(lang('success'));
    }
    
    public function delete() {
        $this->db->where($this->tablekey,$this->input->post($this->tablekey))->set(array(
            'is_delete'=>1
        ))->update($this->tablename);
        json_success(lang('success'));
    }
}