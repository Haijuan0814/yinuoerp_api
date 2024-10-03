<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Bug extends MY_Controller {
    
    public $tablename = 'it_bug';
    public $tablekey = 'id';
    public function __construct() {
        parent::__construct();
    }

    /*
    Anthor：shihaijuan
    Date：2021-10-11
    Desc：列表，分页
    */
    public function index() {
        $current = $this->input->get("current") ? $this->input->get("current") : 1;
        $pageSize = $this->input->get("pageSize") ? $this->input->get("pageSize") : config_item('page_per');   

        /*按照标题搜索*/
        if($this->input->get('title')){
            $this->db->where('(`title` like "%'.$this->input->get('title').'%")');
        }
        if($this->input->get('parent')){
            $this->db->where('parent',$this->input->get('parent'));
        }
        /*按照产品搜索*/
        if($this->input->get('product_id')){
            $this->db->where('product_id',$this->input->get('product_id'));
        }
        /*按照标题搜索*/
        if($this->input->get('insert_user')){
            $this->db->where('(`insert_user` like "%'.$this->input->get('insert_user').'%")');
        }
        /*安装状态搜索*/
        if(strlen($this->input->get('status'))>0){
            $this->db->where('status',$this->input->get('status'));
        }
        /*按照指定标签搜索*/
        $tab=$this->input->get('tab');
        if($tab=="created"){
            $this->db->where('insert_uid',$this->user->id);
        }else if($tab=="related"){
            $this->db->where("(FIND_IN_SET('{$this->user->id}',`deal_uids`) and 1=1)");
        }
        $this->db->order_by('id desc');
        $total = $this->db->count_all_results($this->tablename, false);
        $rows = $this->db->limit($pageSize, ($current-1) * $pageSize)->get()->result();
        foreach($rows as $row){
            $this->detail_extend($row);
        }
        json_success(array(
            "data" => $rows,
            "total" => $total,
            'current' => $current,
            'pageSize' => $pageSize,
        ));
    }


    /*
    Anthor：shihaijuan
    Date：2021-10-11
    Desc：增删改
    */
    public function form(){
        $method=$_SERVER['REQUEST_METHOD'];
        $params=file_get_contents("php://input");
        $params = json_decode($params,true);
        if($method=='POST'){
            foreach (array('title','content','status') as $field) {
                if(isset($params[$field]))
                    $data[$field] = $params[$field];
            }
            foreach (array('start_time','end_time') as $field) {
                if(isset($params[$field]))
                    $data[$field] = strtotime($params[$field]);
            }
            foreach (array('product_ids','deal_uids','related_uids') as $field) {
                if(isset($params[$field]))
                    $data[$field] = implode(',',$params[$field]);
            }
            $data['update_time']=time();
            if (isset($params[$this->tablekey])&&$params[$this->tablekey]) {
                $this->db->set($data)->where(array($this->tablekey => $params[$this->tablekey]))->update($this->tablename);
                $id=$params[$this->tablekey];
            }else{
                $data = array_merge($data,array(
                    'parent'=>$params['parent'],
                    'insert_time' => time(),
                    'insert_uid' => $this->user->id,
                    'insert_user' => $this->user->name
                ));
                $this->db->set($data)->insert($this->tablename);
                $id= $this->db->insert_id();
            }
            $_uids=array_merge($params['deal_uids'],$params['related_uids']);
            if($_uids){
                foreach($_uids as $k=>$uid){
                    $message = array(
                        "module" => $this->tablename,
                        "param" => $id,
                        "title" => isset($params[$this->tablekey])&&$params[$this->tablekey]?$this->user->name."更新了缺陷":$this->user->name."关联你一个缺陷",
                        "description" => $params['title'],
                        'to_uid'=>$uid
                    );
                    $this->message_model->insert($message);
                }
            }
        }else if($method=='DELETE'){
            $this->db->where($this->tablekey,$params[$this->tablekey])->delete($this->tablename);
        }
        json_success(lang('success'));
    }

    public function detail() {
        $id = $this->input->get('id');
        //$id=4;
        $row = $id?$this->db->where("id",$id)->get($this->tablename)->row():array();
        if($row){
            $row=$this->detail_extend($row);
        }
        json_success(array(
            "row" => $row
        ));
    }
    
    /*
    Anthor：shihaijuan
    Date：2021-10-11
    Desc：数据格式化
    */
    public function detail_extend($row) {
        if (!$row) return ;
        $products=$row->product_ids?$this->db->select("id,name,avatar")->where_in("id",explode(',',$row->product_ids))->get("it_product")->result():array();
        $row->products=$products;
        $row->deal_user=$this->user_model->names($row->deal_uids);
        $row->related_user=$this->user_model->names($row->related_uids);
        $date=$row->start_time?date('Y-m-d',$row->start_time):'-';
        $date.=($row->start_time||$row->end_time)?" 至 ":"";
        $date.=$row->end_time?date('Y-m-d',$row->end_time):'-';
        $row->date=$date;

        $row->product_ids = $row->product_ids?explode(',',$row->product_ids):array();
        $row->deal_uids = $row->deal_uids?explode(',',$row->deal_uids):array();
        $row->related_uids = $row->related_uids?explode(',',$row->related_uids):array();

        return $row;
    }

    /*
    Anthor：shihaijuan
    Date：2021-12-08
    Desc：文档状态的更新
    */
    public function status() {
        $id = $this->input->get('id');
        $status = $this->input->get('status');
        $row = $id?$this->db->where("id",$id)->get($this->tablename)->row():array();
        if($row){
            $data=array(
                'status'=>$status
            );
            $this->db->set($data)->where(array($this->tablekey => $id))->update($this->tablename);
            json_success($status);
        }else{
            json_fail();
        }
    }
    
}