<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Guide extends MY_Controller {
    
    public $tablename = 'oa_article';
    public $tablekey = 'id';

    public function __construct() {
        parent::__construct();
    }
    
    public function index() {
        $this->db->where('parent !=',-1);
        $this->db->select('id,title,parent')->order_by('listorder');
        $rows = $this->db->get($this->tablename)->result();
        json_success(array(
            "rows" => $rows,
            'origin'=>139
        ));
    }
    
    public function detail() {
        $row = $this->db->where($this->tablekey,$this->input->get($this->tablekey))->get($this->tablename)->row();
        json_success($row);
    }
    
    public function save() {
        $this->permission();
        $data = array();
        foreach (array('title','parent') as $field) {
            $data[$field] = $this->input->post($field);
        }
        if($this->input->post("content")){
           $data["content"] = $this->input->post("content"); 
        }
        $data["update_time"]=time();
        if ($this->input->post($this->tablekey)) {
            $id = $this->input->post($this->tablekey);
            $this->db->set($data)->where(array($this->tablekey => $this->input->post($this->tablekey)))->update($this->tablename);
        }else{
            //新增的时候排在最后面
            $last_child=$this->db->select('listorder')->where("parent",$this->input->post("parent"))->order_by('listorder desc')->limit(1)->get($this->tablename)->row();
            $data = array_merge($data,array(
                'listorder'=>$last_child?$last_child->listorder+1:0,
                'insert_time' => time(),
                'uid' => $this->user->id
            ));
            $this->db->set($data)->insert($this->tablename);
            $id = $this->db->insert_id();
        }
        
        $row = $this->db->where($this->tablekey,$id)->get($this->tablename)->row();
        json_success(array('row' => $row,'msg'=>lang('success')));
    }
    
    public function delete() {
        $this->permission();
        $id=$this->input->post($this->tablekey);
        $check=$this->db->where("parent",$this->input->post($this->tablekey))->count_all_results($this->tablename);
        if($check){
            json_fail(lang('fail'));
        }else{
            $this->db->set('parent',-1)->where($this->tablekey,$this->input->post($this->tablekey))->update($this->tablename);
            json_success(lang('success'));
        }
        
    }
    
    public function listorder(){
        $this->permission();
        $ids=$this->input->post("ids");
        $ids= explode(',', $ids);
        $parent=$this->input->post("parent");
        if($ids){
            foreach($ids as $k=>$id){
                $this->db->set(array(
                    'listorder'=>$k+1,
                    'parent'=>$parent
                ))->where('id',$id)->update($this->tablename);
            }
        }
        json_success(lang('success'));
        
        /*
        $data = array();
        foreach (array('parent','listorder') as $field) {
            $data[$field] = $this->input->post($field);
        }
        if ($this->input->post($this->tablekey)) {
            $id = $this->input->post($this->tablekey);
            $this->db->set($data)->where(array($this->tablekey => $this->input->post($this->tablekey)))->update($this->tablename);
        }
        json_success(lang('success'));
        */
    }
    
    public function permission(){
        $authority = in_array($this->user->id, $this->setting_model->item('about_user'));
        $result = false;
        if ($authority) {
            $result = true;
        }        
        if (!$result) json_fail (lang('permission_fail'));
    }
    
    public function setting() {
        json_success($this->_setting());
    }

    private function _setting() {
        return array(
            'is_edit' => in_array($this->user->id, $this->setting_model->item('about_user'))
        );
    }
}