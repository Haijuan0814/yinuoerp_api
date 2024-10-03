<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Docs extends MY_Controller {
    
    public $tablename = 'oa_docs';
    public $tablekey = 'openid';

    public function __construct() {
        parent::__construct();
    }
    
    /*
    Anthor：shihaijuan
    Date：2021-10-22
    Desc：列表，分页
    */
    public function index() {
        $current = $this->input->get("current") ? $this->input->get("current") : 1;
        $pageSize = $this->input->get("pageSize") ? $this->input->get("pageSize") : config_item('page_per');   
        $this->db->where("(`insert_uid`={$this->user->id} OR FIND_IN_SET({$this->user->id},`edit_uids`) OR FIND_IN_SET({$this->user->id},`related_uids`) OR is_public=1 )");

        $this->db->where(array('parent'=>0,'is_delete'=>0));
        switch ($this->input->get("tab")) {
            case 'write': 
                $this->db->where('is_delete', 0)->where('insert_uid', $this->user->id);
                break;
            case 'get':
                $this->db->where('is_delete', 0)->where("(is_public=0 AND FIND_IN_SET({$this->user->id},related_uids))");
                break;
            case 'edit':
                $this->db->where('is_delete', 0)->where("(1=1 AND FIND_IN_SET({$this->user->id},edit_uids))");
                break;
            case 'public':
                $this->db->where('is_delete', 0)->where('is_public', 1);
                break;
            case 'delete':
                $this->db->where('is_delete', 1)->where('insert_uid', $this->user->id);
                break;
            default:
                $this->db->where('is_delete', 0);
                break;
        }
        if ($this->input->get("keywords")){
            $this->db->like('title',$this->input->get("keywords"));
        }
        if ($this->input->get("title")){
            $this->db->like('title',$this->input->get("title"));
        }
        if ($this->input->get("tags")){
            $this->db->like('tags',$this->input->get("tags"));
        }
        if ($this->input->get("insert_uid")) {
            $this->db->where("insert_uid", $this->input->get("insert_uid"));
        }
        $this->db->select('id,openid,title,insert_user,insert_uid,update_time,tags,is_public,is_delete,read_num,comment_num')->order_by('update_time desc,id desc');
        $total = $this->db->count_all_results($this->tablename, false);
        $rows = $this->db->limit($pageSize, ($current-1) * $pageSize)->get()->result();
        foreach($rows as $row){
            $this->detail_extend($row);
        }
        json_success(array(
            "list" => $rows,
            "total" => $total,
            'current' => $current,
            'pageSize' => $pageSize,
        ));
    }

    /*
    Anthor：shihaijuan
    Date：2021-10-22
    Desc：详情
    */
    public function detail() {
        $row = $this->db->where($this->tablekey,$this->input->post_get($this->tablekey))->get($this->tablename)->row();
        $this->permission($row);
        $row = $this->detail_extend($row,'detail');   
        $this->db->set(array('read_num'=>($row->read_num+1)))->where($this->tablekey,$this->input->post_get($this->tablekey))->update($this->tablename);
        if($row&&$row->parent==0){
            $children=$this->db->where('parent',$row->id)->get($this->tablename)->result();
            foreach($children as $v){
                $v=$this->detail_extend($v,'detail');   
            }
            $row->children=$children?$children:null;
        }   
        $row ? json_success(array("row" => $row)) : json_fail(lang('undefined'));
    }

    public function detail_extend($row,$mode="list") {
        if (!$row) return ;
        if($mode=="detail"){
            $row->edit_users = $this->user_model->names($row->edit_uids);
            $row->related_users=$this->user_model->names($row->related_uids);
            $row->edit_uids = $row->edit_uids?explode(',',$row->edit_uids):array();
            $row->related_uids = $row->related_uids?explode(',',$row->related_uids):array();
        }
        $insert_user=$this->user_model->row($row->insert_uid);
        $row->insert_avatar=$insert_user?$insert_user->avatar:"";
        $row->tags=explode(',',$row->tags);
        return $row;
    }

    /*
    Anthor：shihaijuan
    Date：2021-10-11
    Desc：增删改
    */
    public function form(){
        $params=file_get_contents("php://input");
        $params = json_decode($params,true);
        $method=$_SERVER['REQUEST_METHOD'];
        if($method=='POST'){
            $data = array();
            foreach (array('title','content','is_public','parent') as $field) {
                $data[$field] = $params[$field];
            }
            foreach (array('related_uids','edit_uids','tags') as $field) {
                $data[$field] = isset($params[$field])&&$params[$field]?implode(',',$params[$field]):"";
            }
            $data['update_time']=time();
            if (isset($params[$this->tablekey])&&$params[$this->tablekey]) {
                $row = $this->db->where($this->tablekey,$params[$this->tablekey])->get($this->tablename)->row();
                $this->permission($row);
                $openid=$params[$this->tablekey];
                $this->db->set($data)->where(array($this->tablekey => $params[$this->tablekey]))->update($this->tablename);
            }else{
                $openid = md5(time() . $this->user->id);
                $data = array_merge($data,array(
                    'openid' => $openid,
                    'insert_time' => time(),
                    'insert_uid' => $this->user->id,
                    'insert_user' => $this->user->name
                ));
                $this->db->set($data)->insert($this->tablename);
            }
        }
        json_success(lang('success'));
    }
    
    /*
    Anthor：shihaijuan
    Date：2021-12-14
    Desc：逻辑删除
    */
    public function delete() {
        $row = $this->db->where($this->tablekey,$this->input->get($this->tablekey))->get($this->tablename)->row();
        $this->permission($row);
        $this->db->set('is_delete',1)->where($this->tablekey,$this->input->get($this->tablekey))->update($this->tablename);
        json_success(lang('success'));
    }


    //物理删除
    public function shanchu() {
        $row = $this->db->where($this->tablekey,$this->input->post($this->tablekey))->get($this->tablename)->row();
        $this->permission($row);
        $this->db->where($this->tablekey,$this->input->post($this->tablekey))->delete($this->tablename);
        json_success(lang('success'));
    }
    //还原
    public function huanyuan() {
        $row = $this->db->where($this->tablekey,$this->input->post($this->tablekey))->get($this->tablename)->row();
        $this->permission($row);
        $this->db->set('is_delete',0)->where($this->tablekey,$this->input->post($this->tablekey))->update($this->tablename);
        json_success(lang('success'));
    }


    /*
    Anthor：shihaijuan
    Date：2021-11-16
    Desc：判断权限
    */
    public function permission($row) {
        $result = false;
        if (!$row) json_fail (lang('undefined'));
        if ($this->user->id == $row->insert_uid || in_array($this->user->id,explode(',', $row->edit_uids)) ||  in_array($this->user->id,explode(',', $row->related_uids))||$row->is_public==1) {
            $result = true;
        }        
        if (!$result) json_fail (lang('permission_fail'));
    }
    
    //分享
    public function share() {
        $row = $this->db->where($this->tablekey,$this->input->post($this->tablekey))->get($this->tablename)->row();
        $this->permission($row);

        $uids = explode(',', trim($row->uids, ','));
        foreach (explode(',', $this->input->post('uids')) as $uid) {
            if (!$uid || in_array($uid, $uids)) {
                continue;
            }
            $uids[] = $uid;
        }
        $this->db->where($this->tablekey,$this->input->post($this->tablekey))->set(array(
            'uids' => implode(',', $uids),
        ))->update($this->tablename);
        //提醒
        foreach ($uids as $k => $uid) {
            $this->message_model->insert(array(
                "module" => $this->tablename,
                "to_uid" => $uid,
                "param" => $row->openid,
                "title" => lang('msg_docs_new'),
                "description" => $row->title
            ));
        }
        json_success(lang('success'));
    }
    
    //置顶
    public function top() {
        $is_top = $this->input->post("is_top");
        if ($is_top) {
            //置顶
            //查询已经设为置顶的数量
            $total = $this->db->where(array("uid" => $this->user->id, "is_delete" => 0, "is_top" => 1))->count_all_results($this->tablename);
            $max = 3;
            if ($total >= $max) {
                json_fail(lang('msg_docs_top'));
            }
            $this->db->set(array("is_top" => 1))->where($this->tablekey,$this->input->post($this->tablekey))->update($this->tablename);
        } else {
            $this->db->set(array("is_top" => 0))->where($this->tablekey,$this->input->post($this->tablekey))->update($this->tablename);
        }
        json_success(lang('success'));
    }
    
    //授权编辑
    public function hezuo() {
        $edit_uid = $this->input->post('edit_uid');
        $row = $this->db->where($this->tablekey,$this->input->post($this->tablekey))->get($this->tablename)->row();
        if (!$row) json_fail (lang('undefined'));
        $this->db->where($this->tablekey,$this->input->post($this->tablekey))->set(array(
            'edit_uid' => $edit_uid,
        ))->update($this->tablename);
        //新版方案提醒
        $this->message_model->insert(array(
            "module" => $this->tablename,
            "to_uid" => $edit_uid,
            "param" => $row->openid,
            "title" => lang('msg_docs_new'),
            "description" => $row->title
        ));
        json_success(lang('success'));
    }
    
    public function setting() {
        json_success($this->_setting());
    }

    private function _setting() {
        return array(
            'tabs' => array(
                    '' => '全部',            
                    'WRITE' => '我写的',
                    'RECEIVE' => '关联我的',
                    'PUBLIC' => '公开的',
                    'EDIT' => '授权编辑',
                    'DELETE' => '回收站',
                )
        );
    }
}