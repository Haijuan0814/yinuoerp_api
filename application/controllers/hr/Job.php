<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Job extends MY_Controller {
    
    public $tablename = 'user_job';
    public $tablekey = 'id';
    public function __construct() {
        parent::__construct();
    }

    /*
    Anthor：shihaijuan
    Date：2021-10-14
    Desc：列表，分页
    */
    public function all() {
        /*按照岗位名称搜索*/
        if($this->input->get('name')){
            $this->db->where('(`name` like "%'.$this->input->get('name').'%")');
        }
        $this->db->where('is_delete',0)->order_by('id asc');
        $rows = $this->db->get($this->tablename)->result();
        json_success(array(
            "list" => $rows,
        ));
    }


    /*
    Anthor：shihaijuan
    Date：2021-10-11
    Desc：增改
    */
    public function save(){
        $params=file_get_contents("php://input");
        $params = json_decode($params,true);
        foreach (array('name','introduce','require') as $field) {
            $data[$field] = $params[$field];
        }
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
        $product=$row->product_id?$this->db->select("name,avatar")->where("id",$row->product_id)->get("it_product")->row():array();
        $row->product=$product;
        $row->deal_users=$this->user_model->names($row->deal_uids);
        return $row;
    }


    /*
    Anthor：shihaijuan
    Date：2021-11-26
    Desc：下拉菜单，不支持分页
    */
    public function select() {
        $this->db->select('id,name')->where('is_delete',0)->order_by('id');
        $total = $this->db->count_all_results($this->tablename, false);
        $rows = $this->db->get()->result();
        $list=array();
        foreach($rows as $row){
            $list[]=array(
                'value'=>$row->id,
                'label'=>$row->name,
            );
        }
        json_success(array(
            "list" => $list,
            "total" => $total,
        ));
    }
    
    
}