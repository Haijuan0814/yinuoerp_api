<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Department extends MY_Controller {
    
    public $tablename = 'user_department';
    public $tablekey = 'id';
    public function __construct() {
        parent::__construct();
    }

    /*
    Anthor：shihaijuan
    Date：2021-10-14
    Desc：列表，不分页
    */
    public function all() {
        /*按照岗位名称搜索*/
        if($this->input->get('name')){
            $this->db->where('(`name` like "%'.$this->input->get('name').'%")');
        }
        $this->db->select('id,name as title,parent,uid,is_key')->where('is_delete',0)->order_by('listorder asc,id asc');
        $rows = $this->db->get($this->tablename)->result();
        foreach($rows as $row){
            $this->detail_extend($row);
        }
        $rows=json_foreach(json_decode(json_encode($rows),true),0);
        json_success($rows);
    }


    /*
    Anthor：shihaijuan
    Date：2021-10-24
    Desc：增改
    */
    public function save(){
        $params=file_get_contents("php://input");
        $params = json_decode($params,true);
        foreach (array('name','parent','uid') as $field) {
            $data[$field] = $params[$field];
        }
        $data['is_key'] = $params['is_key']?$params['is_key']:0;
        if (isset($params[$this->tablekey])&&$params[$this->tablekey]) {
            $this->db->set($data)->where(array($this->tablekey => $params[$this->tablekey]))->update($this->tablename);
        }else{
            $this->db->set($data)->insert($this->tablename);
        }
        echo $this->db->last_query();
        json_success(lang('success'));
    }

    /*
    Anthor：shihaijuan
    Date：2021-12-24
    Desc：逻辑删除
    */
    public function delete() {
        $id=$this->input->get($this->tablekey);
        $this->db->set(array('is_delete'=>1))->where(array($this->tablekey => $id))->update($this->tablename);
        json_success(lang('success'));
    }
    

    /*
    Anthor：shihaijuan
    Date：2021-10-11
    Desc：数据格式化
    */
    public function detail_extend($row) {
        if (!$row) return ;
        $row->children=array();
        $row->uid_name=$this->user_model->name($row->uid);
        $row->user_num=$this->db->where(array('department_id'=>$row->id,'is_leave'=>0))->count_all_results('user');
        return $row;
    }

    /*
    Anthor：shihaijuan
    Date：2021-11-26
    Desc：tree列表，不分页
    */
    public function tree() {
        $this->db->select('id,name as title,parent,listorder')->where('is_delete',0)->order_by('listorder asc,id asc');
        $rows = $this->db->get($this->tablename)->result();
        $parent_keys=array();
        foreach($rows as $row){
            $row->key=$row->id;
            $row->children=array();
            if($row->parent==0) $parent_keys[]=$row->id;
        }
        $rows=json_foreach(json_decode(json_encode($rows),true),0);
        json_success(array('rows'=>$rows,'parent_keys'=>$parent_keys));//$rows
    }
    
}