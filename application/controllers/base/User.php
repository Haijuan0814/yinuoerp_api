<?php

defined('BASEPATH') OR exit('No direct script access allowed');

//base.user侧重于全局服务； 
//hr.user是员工管理

class User extends MY_Controller {
    
    public $tablename = 'user';
    public $tablekey = 'id';

    public function __construct() {
        parent::__construct();
        
        
    }
    private function mode($param=""){
        $select = 'id,name,avatar,department_name,tags';
        $select_card = '' ;
        $select_all = '' ;
        if ($param == 'card') $select .= $select_card;
        if ($param == 'all') $select .= $select_card. $select_all;
        return $select;
        //这边all的情况，后期需要增加身份验证或者log记录
    }
    
    public function index() {
        if ($this->input->get("department_id")){
            $department_ids=$this->department_model->get_children_ids($this->input->get("department_id"));
            $this->db->where_in('department_id',$department_ids);
        }
        if ($this->input->get("keyword")){
            $this->db->like('name',$this->input->get("keyword"));
        }
        $mode = $this->input->get('mode');
        $select = $this->mode($mode);
        $this->db->select($select)->where('is_leave',0)->order_by('id');//->where('area !=','factory')
        $total = $this->db->count_all_results($this->tablename, false);
        $rows = $this->db->get()->result();
        foreach($rows as $row){
            $row = $this->detail_extend($row);
        }
        json_success(array(
            "rows" => $rows,
            "total" => $total,
        ));
    }

    //下拉菜单，不支持分页，必须带入keyword
    public function select() {
        if (!$this->input->get("keyword")) json_fail(lang('required_fail'));
        $this->db->like('realname',$this->input->get("keyword"));
        $this->db->select('id,realname,photo,department_name')->where('is_exit',0)->where('area !=','factory')->order_by('id');
        $total = $this->db->count_all_results($this->tablename, false);
        $rows = $this->db->get()->result();
        foreach($rows as $row){
            $row = $this->detail_extend($row);
        }
        json_success(array(
            "rows" => $rows,
            "total" => $total,
        ));
    }
    
    //卡片
    public function detail() {
        $mode = $this->input->get('mode');
        $select = $this->mode($mode);
        $id = $this->input->get('id')?$this->input->get('id'):2;
        $data = array();
        if ($id && strpos($id,',')){
            foreach (explode(',',$id) as $_id){
                if ($_id) $data[] = $this->user_model->row($_id,$select);
            }
        }elseif ($id){
            $data[] = $this->user_model->row($id,$select);
        }else{
            $this->user_model->row($this->user->id,$select);
        }
        json_success($data);
    }
    
    public function detail_extend($row) {
        if (!$row) return ;
        //$row->avatar = str_replace('http://','//', $row->avatar) . '_50p';
        return $row;
    }
}