<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Dimission extends MY_Controller {
    
    public $tablename = 'hr_dimission';
    public $tablekey = 'id';

    public function __construct() {
        parent::__construct();
    }
    public function index() {
        $page = $this->input->get("page") ? $this->input->get("page") : 0;      
        if ($this->input->get("tab")!="all") {
            $this->db->where("area", $this->input->get("area"));
        }
        if ($this->input->get("realname")) {
            $this->db->like("realname", $this->input->get("realname"));
        }
        if ($this->input->get("area")) {
            $this->db->where("area", $this->input->get("area"));
        }
        $this->db->order_by('exit_time desc');
        $total = $this->db->count_all_results($this->tablename, false);
        $rows = $this->db->limit(config_item('page_per'), $page * config_item('page_per'))->get()->result();
        json_success(array(
            "rows" => $rows,
            "total" => $total,
            'page' => $page,
        ));
    }
    
    public function save() {
        $data = array();
        foreach (array('uid','type','exit_time','remark') as $field) {
            $data[$field] = $this->input->post($field);
        }
        $user=$this->user_model->row($data['uid'],'*');
        
        $data=array_merge($data,array(
            'realname'=>$user?$user->realname:"",
            'area' =>$user?$user->area:"",
            'join_time'=>$user?$user->join_time:"",
            'work_time' => $exit_time - $user->join_time,
            'userinfo' => json_encode($user),
        ));
        $data = array_merge($data,array(
            'confirm_time' => time(),
            'confirm_uid' => $this->user->id
        ));
        $this->db->set($data)->insert($this->tablename);
        
        
        //主表冻结
        $this->db->set(array(
            'is_exit' => 1,
            'exit_time' => $data['exit_time']
        ))->where('id', $data['uid'])->update('user');

        //同步微信通讯录
        $this->load->library('Weixinapi');
        $this->weixinapi->send('/user/delete',array('userid' => $data['uid']),'get');
            
        json_success(lang('success'));
    }
    
    public function setting() {
        json_success($this->_setting());
    }

    private function _setting() {
        return array(
            'select_type' => $this->setting_model->item("exit_type")
        );
    }
}