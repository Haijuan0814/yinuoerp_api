<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Comment extends MY_Controller {
    
    public $tablename = 'base_comment';
    public $tablekey = 'id';

    public function __construct() {
        parent::__construct();
    }
    
    /*
    Anthor：shihaijuan
    Date：2021-11-26
    Desc：列表，不分页
    */
    public function index() {        
        $this->db->where(array(
            'tablename' => $this->input->get("tablename"),
            'tableid' => $this->input->get("tableid"),
        ))->order_by('id desc');
        $total = $this->db->count_all_results($this->tablename, false);
        $rows = $this->db->get()->result();
        foreach ($rows as $row) {
            $row->user = $this->user_model->row($row->insert_uid,'id,name,avatar');
            $row->insert_time = time_convert($row->insert_time);
            $row->key=$row->id;
            $row->title=$row->content;
            $row->children=array();
            //cache_user($row->uid);
        }
        $rows=json_foreach(json_decode(json_encode($rows),true),0);
        json_success(array("rows" => $rows));

    }
    

    /*
    Anthor：shihaijuan
    Date：2021-11-26
    Desc：保存
    */
    public function save() {
        $data = array();
        $params=file_get_contents("php://input");
        $params = json_decode($params,true);
        foreach (array('content','tablename','tableid','parent') as $field) {
            $data[$field] = $params[$field];
        }
            $data = array_merge($data,array(
                'insert_time' => time(),
                'insert_uid' => $this->user->id,
                'insert_name' => $this->user->name
            ));
       
        $this->db->set($data)->insert($this->tablename);

        $tablename=$params['tablename'];
        $tablename1=$params['tablename']=="oa_plan_report"?'oa_plan':$params['tablename'];
        $parent=$params['tableid'];
        $business_row=$this->db->where('id',$parent)->get($tablename1)->row();    
        $menu=$this->db->where("table_name",$tablename)->get("base_menu")->row();
        if($menu&&$business_row){
            $message = array(
                "module" => $tablename,
                "param" => $parent,
                "title" => $this->user->name."评论了您的一条".$menu->name,
                "description" => $params['content'],
                'to_uid'=>$business_row->insert_uid
            );
            $this->message_model->insert($message);
        }
        
        json_success(lang('success'));
    }
    

    /*
    Anthor：shihaijuan
    Date：2021-11-26
    Desc：删除
    */
    public function delete() {
        $row=$this->db
                ->where($this->tablekey,$this->input->get($this->tablekey))
                ->where('insert_uid',$this->user->id)
                ->get($this->tablename)->row();
        if($row){
            //删除回复
            $this->db->where("parent",$this->input->get($this->tablekey))->delete($this->tablename);
            //删除主评论
            $this->db->where($this->tablekey,$this->input->get($this->tablekey))->delete($this->tablename);
            json_success(lang('success'));
        }else{
            json_fail (lang('permission_fail'));
        }
        
    }
    
    //结合具体业务实现通知
    public function send_message($tablename, $parent, $content) {
        $route = $this->db->where("key", $tablename)->get('base_menu')->row();
        $route_name = $route?$route->name:'';
        $data = array(
            'title' => $route_name . lang('comment_new'),
            'module' => $tablename,
            'from_uid' => $this->user->id,
        );        
        switch ($tablename) {
            case 'oa_memo':
                $row = $this->db->where("id", $parent)->get($tablename)->row();
                if (!$row) return ;
                $data = array_merge($data,array(
                    'to_uid' => $row->uid,
                    'param' => $row->id,
                    'description' => $content.'<br><div class="gray">原文：'.$row->content .'</div>'
                )) ;
                break;
            case 'oa_order':
                $row = $this->db->where("id", $parent)->get($tablename)->row();
                if (!$row) return ;
                $data = array_merge($data,array(                    
                    'to_uid' => $row->insert_uid,
                    'param' => $row->id,
                    'description' => $content.'<br><div class="gray">原文：'.$row->content .'</div>'
                ));
                break;
            case 'oa_docs':
                $row = $this->db->where("id", $parent)->get($tablename)->row();
                if (!$row) return ;
                $data = array_merge($data,array(
                    'to_uid' => $row->uid,
                    'param' => $row->openid,
                    'description' => $content.'<br><div class="gray">原文：'.$row->title .'</div>'
                ));
                break;
            case 'finance_caigou':
                $row = $this->db->where("id", $parent)->get($tablename)->row();
                if (!$row) return ;
                $data = array_merge($data,array(
                    'to_uid' => $row->uid,
                    'param' => $row->id,
                    'description' => $content.'<br><div class="gray">相关：'.$row->title .'</div>'
                ));
                break;
            case 'app_chifan':
                $data = array_merge($data,array(
                    'to_uid' => 160,//王颖
                    'module' => $tablename,
                    'description' => $content
                ));                
                break;
            default:
                return;
        }
        if (isset($data['to_uid'])) $this->message_model->insert($data);
    }
}