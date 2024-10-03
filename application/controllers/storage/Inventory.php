<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Inventory extends MY_Controller {
    
    public $tablename = 'storage_inventory';
    public $tablekey = 'id';
    public function __construct() {
        parent::__construct();
    }
    
    /*
    Author:shihaijuan
    Date:20211008 
    Desc:出入库列表数据（不分页）
    */
    public function index() {
        if($this->input->get('goods_id')){
            $this->db->where('goods_id',$this->input->get('goods_id'));
            //$this->db->where('(`name` like "%'.$this->input->get('name').'%")');
        }
        if(strlen($this->input->get('status'))>0){
            $this->db->where('status',$this->input->get('status'));
        }
        $rows = $this->db->get($this->tablename)->result();
        foreach($rows as $row){
            $this->detail_extend($row);

        }
        json_success($rows);
    }


    /*
    Author:shihaijuan
    Date:20211008 
    Desc:物资列表数据（分页）
    */
    public function all() {
        $current = $this->input->get("current") ? $this->input->get("current") : 1;
        $pageSize = $this->input->get("pageSize") ? $this->input->get("pageSize") : config_item('page_per');   

        if($this->input->get('name')){
            $this->db->where('(`name` like "%'.$this->input->get('name').'%")');
        }
        
        if(strlen($this->input->get('status'))>0){
            $this->db->where('status',$this->input->get('status'));
        }
        $this->db->order_by('id desc');
        $total = $this->db->count_all_results($this->tablename, false);
        $rows = $this->db->limit($pageSize, ($current-1) * $pageSize)->get()->result();
        json_success(array(
            "list" => $rows,
            "total" => $total,
            'current' => $current,
            'pageSize' => $pageSize,
        ));
    }


    public function form(){
        //$method=$this->input->post("method");
        $params=file_get_contents("php://input");
        $params = json_decode($params,true);
        $method=$params['method'];
        if($method=='post'){

        }else if($method=='update'){

        }else if($method=='delete'){
            $this->db->where($this->tablekey,$params[$this->tablekey])->delete($this->tablename);
        }
        $this->all();
    }


    public function detail_extend($row) {
        if (!$row) return ;
        $goods=$row->goods_id?$this->db->select("name,avatar")->where("id",$row->goods_id)->get("storage_goods")->row():array();
        $row->goods=$goods;
        return $row;
    }
}