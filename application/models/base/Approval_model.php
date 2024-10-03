<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Approval_model extends CI_Model {

    public $tablename = 'base_approval';
    public $tablekey = 'id';
    public $_settings=array(
        'check'=>'审核',
        'recheck'=>'复审',
        'print'=>'打印',
        'buy'=>'采购',
        'feedback'=>'反馈',
    );

    public function __construct() {
        parent::__construct();
    }
    
    /*获取审批流程*/
    public function flow($tablename,$parent) {
        $rows = $this->db->where(array(
            'tablename' => $tablename,
            'parent' => $parent
        ))->get($this->tablename)->result();        
        foreach ($rows as $row) {
            $row->user = cache_user($row->uid);
            $row->type_text = lang($row->type);
            unset($row->forward_uid);
        }        
        return $rows;
    }
    
    /*
    Author:Shihaijuan
    Date:20211222
    DESC:添加审批流程
    */
    public function insert($param) {
        $business_row=$this->db->where("id",$param["parent"])->get($param["tablename"])->row();
        if(!isset($business_row->department_id)){
            $user= $this->user_model->row($business_row->insert_uid,'id,name,avatar,department_name,department_id');
            $business_row->department_id=$user->department_id;
        }
        $rule= $this->db->where("key",$param['rule'])->get("base_approval_rule")->row();
        $flows=$rule&&$rule->settings? json_decode($rule->settings,true):array();
        $approval_status="";

       
        if($flows){
            //$menu=$this->db->where("table_name",$param["tablename"])->get("base_menu")->row();
            //$approval_types=$this->setting_model->item('base_approval_type1',true);     
            //通过循环，把所有待审批记录的都生成出来   
            foreach($flows as $k=>$flow){
                if($flow['user']=="is_hr"||$flow['user']=="is_finance"){
                    $approval_uid=$this->department_model->get_leader($business_row->department_id);
                }else if($flow['user']=="self"){//申请人自己
                    $approval_uid=$business_row->insert_uid;
                }else if($flow['user']=="system"){//系统操作
                    $approval_uid=0;
                }else{
                    $approval_uid=$flow['user'];//指定人
                }
                $data=array(
                    "tablename"=>$param["tablename"],
                    "parent"=>$param["parent"],
                    "type"=>$flow['type'],
                    "step"=>$this->db->where(array("tablename"=>$param["tablename"],"parent"=>$param["parent"]))->count_all_results($this->tablename),
                    "uid"=>$approval_uid,
                    "forward_uid"=>$approval_uid,
                    "business_url"=>$flow['business_url'],
                    "insert_time"=>time(),
                    "update_time"=>time(),
                    'status'=>($k==0)?"TODO":"WAIT"
                );
                
                $this->db->set($data)->insert($this->tablename);
                if($k==0){
                    //业务的初始状态
                    $approval_status=$flow['type'];
                    //提通知第一个人去处理
                    if($approval_uid){
                        //$insert_user= cache_user($business_row->insert_uid);
                        $insert_user= $business_row->insert_user;
                        $description=($insert_user)."于".date("Y-m-d H:i",$business_row->insert_time)."提交一份".$param["tablename_zh_cn"]."申请";
                     
                        $message = array(
                            "module" => $param["tablename"],
                            "param" => $param["parent"],
                            "title" => "您有一条".$param["tablename_zh_cn"]."待".element($flow['type'],$this->_settings),
                            "description" => $description,
                            'to_uid'=>$approval_uid,
                        );
                        $this->message_model->insert($message);
                       
                    }
                    
                    
                }
            }
        }
        //永久合同签订，强制定义状态，没有流程
        if($param["tablename"]=="hr_contract"&&$param['rule']=="contract_long"){
            $approval_status="normal";
        }
        $this->db->where("id",$param["parent"])->set(array('approval_status'=>$approval_status))->update($param["tablename"]);
        return ;
    }
    
    //获取规则row
    public function rule_row($key){
        $row=$this->db->where("key",$key)->get("base_approval_rule")->row();
        return $row;
    }
    
    //按审批规则，获取审批人，然后生成审批记录
    public function get_user() {
        return ;
    }
    
    public function get_buttons($tablename,$parent){
        $buttons=array("tablename"=>$tablename,"parent"=>$parent);
        //$buttons['edit']=false;
       // $buttons['delete']=false;
        //$buttons['back']=false;
        
        $button_types= $this->setting_model->item("base_approval_type1",true);
        //foreach($button_types as $k=>$v){
            //$buttons[$k]=false;
        //}
        $business_row=$this->db->where('id',$parent)->get($tablename)->row();
        if($business_row){
            //这里要求必须有insert_uid字段
            $insert_uid=$business_row?$business_row->insert_uid:0;
            $rows = $this->db->where(array(
                'tablename' => $tablename,
                'parent' => $parent
            ))->order_by('step')->get($this->tablename)->result(); 

            $todo_uid=0;$print_uid=0;$todo_approval_id=0;$todo_url="";
            $todo_step=0;$print_step=0;$print_approval_id=0;
            $step1=$step2=0;
            foreach($rows as $k=>$row){
                if($row->type==$business_row->approval_status){
                    $todo_step=$step1;
                    $todo_approval_id=$row->id;
                    $todo_uid=$row->forward_uid;
                    $todo_url=$row->business_url;
                }else{
                    $step1++;
                }
                if($row->type=="print"){
                    $print_step=$step2;
                    $print_approval_id=$row->id;
                    $print_uid=$row->forward_uid;
                }else{
                    $step2++;
                }
            }

            if($todo_uid&&$todo_uid==$this->user->id) {
                $buttons[$business_row->approval_status]=$todo_url?$todo_url:true;
                $buttons['transfer']=$todo_approval_id;
            }
            //打印只要到了这个阶段，超过这个阶段也可以打印
            if($print_uid&&$print_uid==$this->user->id&&$todo_step>=$print_step){
                $buttons["print"]=$print_approval_id;
            }

            //一旦进入了审批流，那么就存在回滚，管理员可以删除
            if($todo_step>0&&in_array($this->user->id,$this->setting_model->item('finance_admin'))){
                $buttons['back']=true;
                $buttons['delete']=true;
            }
            //初审状态下，申请人可以编辑或删除
            if($business_row&&$business_row->approval_status=="check"&&$insert_uid==$this->user->id){
                $buttons['edit']=true;
                $buttons['delete']=true;
            }
        }
        return $buttons;
    }
    
}
