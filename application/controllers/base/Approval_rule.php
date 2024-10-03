<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Approval_rule extends MY_Controller {
    
    public $tablename = 'base_approval_rule';
    public $tablekey = 'key';

    public function __construct() {
        parent::__construct();
    }
    
    public function index() {
        $page = $this->input->post_get("page") ? $this->input->post_get("page") : 0;
        $per = $this->input->post_get("per") ? $this->input->post_get("per") : 20;
        if ($this->input->get("keyword")) $this->db->like('name',$this->input->get("keyword"));
        $module=$this->input->get("module")?explode(',', $this->input->get("module")):array();
        if ($module) $this->db->where_in('module',$module);
        $this->db->select('key,name,module')->order_by('module');
        $total = $this->db->count_all_results($this->tablename, false);
        $rows = $this->db->limit($per, $page * $per)->get()->result();
        json_success(array(
            "rows" => $rows,
            "total" => $total,
            'page' => $page,
        ));
    }

    public function detail() {
        $row = $this->db->where($this->tablekey,$this->input->post_get($this->tablekey))->get($this->tablename)->row();
        $this->detail_extend($row);
        json_success($row);
    }
    
    public function detail_extend($row) {
        if (!$row)
            return;
        $settings=$row->settings?json_decode($row->settings, true):array();
        $select=$this->_setting();
        foreach($settings as $k=>$setting){
            $settings[$k]['type']=element($setting['type'],$select['types']);
            if(is_numeric ($setting['user'])){
                $settings[$k]['user']=cache_user($setting['user']);
            }else{
                $settings[$k]['user']=element($setting['user'],$select['users']);
            }
        }
        $row->_settings = $settings;
        return $row;
    }
    
    public function save() {
        $data = array();
        $key = $this->input->post($this->tablekey);
        $row = $this->db->where($this->tablekey,$key)->get($this->tablename)->row();
        foreach (array('key','name','settings','module') as $field) {
            $data[$field] = $this->input->post($field);
        }
        if ($row) {
            $this->db->set($data)->where(array($this->tablekey => $row->key))->update($this->tablename);
        }else{
            $this->db->set($data)->insert($this->tablename);
        }
        json_success(lang('success'));
    }
    
    //保存某个流程下的user，各个模块使用
    public function save_user(){
        $row = $this->db->where($this->tablekey,$this->input->post($this->tablekey))->get($this->tablename)->row();
        $type=$this->input->post('type');
        $user=$this->input->post('user');
        $settings=$row&&$row->settings?json_decode($row->settings, true):array();
        foreach($settings as $k=>$setting){
            if($setting['type']==$type){
                $settings[$k]['user']=$user;
            }
        }
        $settings= json_encode($settings);
        $this->db->set(array('settings'=>$settings))->where($this->tablekey,$this->input->post($this->tablekey))->update($this->tablename);
        json_success(lang('success'));
    }
    
    public function delete() {
        $this->db->where($this->tablekey,$this->input->post($this->tablekey))->delete($this->tablename);
        json_success(lang('success'));
    }
    
    public function setting() {
        json_success($this->_setting());
    }

    private function _setting() {
        return array(
            'modules' => array('oa' => 'oa',  'hr' => 'hr','finance' => 'finance','crm' => 'crm'),
            'types' => $this->setting_model->item('base_approval_type1',true),
            'users' => array('is_hr' => '部门行政审批人',  'is_finance' => '部门财务审批人','user' => '指定人员','self' => '申请人','system'=>"系统"),
        );
    }
    
    public function kv_source(){
        $this->db->select("key,name");
        $rows = $this->db->order_by("key asc")->get($this->tablename)->result();
        $ret=array();
        foreach($rows as $row){
            $ret[]=array('label'=>$row->name,'value'=>$row->key);
        }
        json_success($ret);
    }
}