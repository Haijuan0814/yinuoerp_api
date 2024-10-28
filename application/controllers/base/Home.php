<?php

defined('BASEPATH') OR exit('No direct script access allowed');

//base.user侧重于全局服务； 
//hr.user是员工管理

class Home extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->library('weixinapi');
        
        
    }
    
    /*最新的营销海报*/
    public function getUI(){
        $row = $this->db->where('date >=',strtotime(date('Y-m-d')))->order_by('date asc')->get('it_ui')->row();
        $ret=array('row'=>$row);
        json_success($ret);
    }
    
    
    /*团队成员*/
    public function user() {
        $this->db->select('id,avatar,tags,name,sex,mobile,job_name,department_name,birthday')->where('is_leave',0)->order_by('department_id asc');//->where('area !=','factory')
        $rows = $this->db->get('user')->result();
        foreach($rows as $row){
            $row->birthday_m=date('m',$row->birthday);
        }
        json_success($rows);
    }
    
     /*用户个人资料*/
    public function me() {
        
        $row = $this->db->select('id,name,department_id,department_name,job_id,job_name,avatar,mobile,mix,password,token,tags,address,signature,roles')
        ->where(array("id"=>$this->user->id,"is_leave" => 0))->get('user')->row();
        $job=$this->db->select('require,introduce')->where('id',$row->job_id)->get('user_job')->row();
        $row->job_require = $job?$job->require:'';
        
        json_success($row);
    }
    
     /*新员工*/
    public function newUser() {
        
        $rows = $this->db->select('id,name,department_id,department_name,job_id,job_name,avatar,join_time')
        ->where('join_time >=',strtotime('-7 day'))->get('user')->result();
        
        json_success($rows);
    }

    //首页推送的通知
    public function notice() {
        $this->db->select('id,title,content')
                ->where('is_delete',0)->where('is_send',1)
                ->where('start_date <=',strtotime(date('Ymd')))->where('end_date >=',strtotime(date('Ymd')))
                ->order_by('id desc');
        $rows = $this->db->get('oa_notice')->result();
        json_success($rows);
    }
    
    //首页我今天的计划
    public function plan() {
        $today=strtotime(date('Y-m-d'));
        $plan=$this->db->where('time',$today)->where('cycle','day')
                ->where('insert_uid',$this->user->id)->get('oa_plan')->row();
        json_success($plan);
    }
    
    //首页个人培训奖励
    public function peixunPrize() {
        $rows = $this->db->where('uid',$this->user->id)->order_by('time desc')->get('user_peixun_jiang')->result();
        json_success($rows);
    }
    
    //首页关于IT相关的统计数据
    public function it(){
        $projects_num=$this->db->where("(start_time >=".strtotime(date('Y-m-01'))." and start_time<".strtotime(date('Y-m-01',strtotime('+1 month'))).")")->count_all_results('it_project');
        
        $where="((FIND_IN_SET('{$this->user->id}',`deal_uids`) or FIND_IN_SET('{$this->user->id}',`related_uids`) ) and status>-1)";
        $demand_num=$this->db->where($where)->where('status <',3)->count_all_results('it_demand');
        $code_num=$this->db->where($where)->where('status <',3)->count_all_results('it_coding');
        $bug_num=$this->db->where($where)->where('status <',2)->count_all_results('it_bug');
        $bug_num_all=$this->db->where($where)->count_all_results('it_bug');
        json_success(array(
            'project_num'=>$projects_num,
            'demand_num'=>$demand_num,
            'code_num'=>$code_num,
            'bug_num'=>$bug_num,
            'bug_num_all'=>$bug_num_all
        ));
    }
    
    //个人打卡记录
    public function daka(){
        $year = $this->input->get('year') ? $this->input->get('year') : date('Y');
        $month = $this->input->get('month') ? $this->input->get('month') : date('m');
        
        
        $days=date('t',strtotime($year.'-'.$month.'-01'));
        $start = strtotime($year.'-'.$month.'-01');
        $end = strtotime($year.'-'.$month.'-'.$days);
        
        //$dakadata = $this->getWeixinDaka($year,$month,$days);
         
        $list=array();
        /*if($dakadata){
            foreach($dakadata as $k=>$item){
                $day=date('n-j',$item['checkin_time']);
                if(!isset($list[$day])){
                   $list[$day]=array();
                }
                array_push($list[$day],$item);
            }
        }*/
        
        //数据格式优化
        $_list=array();
        for($i=$start;$i<=$end;$i=$i+86400){
            $key=date('n-j',$i);
            if(!isset($_list[$key])){
               $_list[$key]=array();
            }
                
            //查询当日是否有请假记录
            $_start=$i;
            $_end=$i+86400-1;
            $qingjia=$this->db->where('(start_time <= '. $_end .' and end_time >= '.$_start.')')
            ->where('insert_uid',$this->user->id)
            ->where('status !=','fail')->where('thing !=','jiaban')->where('(thing != "buka" or (thing = "buka" and status !="success"))')->get('oa_leave')->result();
            
            $jiaban=$this->db->where('(start_time <= '. $_end .' and end_time >= '.$_start.')')
            ->where('insert_uid',$this->user->id)
            ->where('status !=','fail')
            ->where('thing','jiaban')->get('oa_leave')->result();

            $qingjia_length=0;
            if($qingjia){
                foreach($qingjia as $item1){
                    $_length =$item1->unit=='天' ? ($item1->length*7):$item1->length;
                    $qingjia_length += $_length;
                    array_push($_list[$key],array(
                        'type'=>($item1->status=="success"?"success":"warning"),
                        'typecolor'=>'#91d5ff',
                        'content'=>element($item1->thing,config_item('thing')) . ($_length?(floatval($_length).' Hours'):'')
                        ));
                }
            }
            
            
            if($qingjia_length<7){
                $status=0;
                $msg="";
                
                if($jiaban){
                    foreach($jiaban as $item2){
                        $_length =$item2->unit=='天' ? ($item2->length*7):$item2->length;
                        array_push($_list[$key],array(
                            'type'=>($item2->status=="success"?"success":"warning"),
                            'typecolor'=>'#91d5ff',
                            'content'=>'Overtime'. ($_length?(floatval($_length).' Hours'):'')));
                    }
                }
            
                if(isset($list[$key])){
                    foreach($list[$key] as $k=>$item){
                        if($item['checkin_type']== "上班打卡"&& $item['exception_type']=="时间异常"){
                            $status=1;
                            $msg.="上班迟到";
                            array_push($_list[$key],array(
                                'type'=>'error',
                                'typecolor'=>'#ffe58f',
                                'content'=>date('H:i',$item['checkin_time']).'迟到'));
                            
                        }else if($item['checkin_type']== "上班打卡"&& $item['exception_type']=="未打卡"){
                            $status=1;
                            $msg.="上班缺卡";
                            array_push($_list[$key],array(
                                'type'=>'error',
                                'typecolor'=>'#ffccc7',
                                'content'=>'上班缺卡'));
                        }else if($item['checkin_type']== "下班打卡"&& $item['exception_type']=="未打卡"){
                            $status=1;
                            $msg.="下班缺卡";
                            array_push($_list[$key],array('type'=>'error',
                                'typecolor'=>'#ffccc7','content'=>'下班缺卡'));
                        }else if(date('Ymd',$item['checkin_time'])>=date('Ymd',strtotime('-30 day'))){
                            //今天的打卡，直接显示
                            if($item['checkin_type']== "上班打卡"){
                                array_push($_list[$key],array(
                                    'type'=>'success',
                                    'typecolor'=>$item['notes']?'#91fff0':'#b7eb8f',
                                    'content'=>date('H:i',$item['checkin_time']).($item['notes']?"补卡":"上班")));
                            }else if($item['checkin_type']== "下班打卡"){
                                array_push($_list[$key],array(
                                    'type'=>'success',
                                    'typecolor'=>$item['notes']?'#91fff0':'#b7eb8f',
                                    'content'=>date('H:i',$item['checkin_time']).($item['notes']?"补卡":"下班")));
                            }
                             
                        }
                    }
                }else{
                    $today=strtotime(date('Y-m-d'));
                    if($i==$today){
                        if(time()>strtotime(date('Y-m-d').' 09:01:00')){
                            array_push($_list[$key],array(
                                'type'=>'error',
                                'typecolor'=>'#ffccc7',
                                'content'=>'Punch Error'));
                        }
                    }
                }
                
            }
            
        }
        
        
        json_success(array('list'=>$_list));
    }
    
    
    public function getWeixinDaka($year,$month,$days){

        $_param=array(
           'opencheckindatatype'=>3,
           'starttime'=>strtotime($year.'-'.$month.'-01'),
           'endtime'=>strtotime($year.'-'.$month.'-'.$days) +86400,
           'useridlist'=>array($this->user->id)
        );

        $ret = $this->weixinapi->get_daka( $_param);
        $ret = json_decode($ret,true);
        if($ret['errcode']==0){
            return $ret['checkindata'];
        }else{
            return null;
        }
    }
}