<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Plan extends MY_Controller {
    public $tablename = 'oa_plan';
    public $tablekey = 'id';
    public function __construct() {
        parent::__construct();
    }
    
    /*
    DESC：日报记录
    Author：shihaijuan
    Date：20211201
    */
    public function index(){
        $today=strtotime(date('Ymd'));
        $N = (date('w')==0?7:date('w'))-1;
        $monday = strtotime('-' . $N . ' days',$today);
        $monday = $this->input->get("time")?$this->input->get("time"):$monday;  
        //$time=$this->input->get("time")?$this->input->get("time"):$today;     
        $cycle=$this->input->get("cycle")?$this->input->get("cycle"):"day";     

        //$is_plan_edit=($cycle=="day"&&$time>=$today)||($cycle=="week"&&$time>=$monday)?true:false;
        $uid=$this->input->get("uid")?$this->input->get("uid"): $this->user->id;
        

        $list=[];
        for($i=0; $i<7; $i++){
            $time=$monday + 86400*$i;
            $where=array("cycle"=>$cycle,'time'=>$time,'insert_uid'=>$uid);
            $plan = $this->db->where($where)->get($this->tablename)->row();
            $list[]=array(
                'time'=>$time,
                'week'=>element(date('w',$time),config_item('week')),
                'plan'=>$plan,
                'active'=>strtotime(date('Ymd'))==$time?true:false,
                'is_edit'=>strtotime(date('Ymd'))<=$time&&$uid==$this->user->id?true:false
            );
        }

        json_success(array(
            "list" => $list,
            "monday" => $monday,
            "today"=>strtotime(date('Ymd')),
            "sunday"=> $monday+(86400*6),
        ));
    }
    
    /*
    Anthor：shihaijuan
    Date：2021-10-11
    Desc：保存
    */
    public function save() {
        $params=file_get_contents("php://input");
        $params = json_decode($params,true);

        $cycle=$params['cycle'];
        $today=strtotime(date('Ymd'));
        $N = (date('w')==0?7:date('w'))-1;
        $monday = strtotime('-' . ($N) . ' days',$today);
        $time=$params['time'];
        if(!$time){
            if($cycle=="day") $time=$today;
            else if($cycle=="week") $time=$monday;
        }
        $data=array(
            'time' => $time,
            'cycle' => $cycle,
            'content' => $params['content'],
            'summary' => isset($params['summary'])?$params['summary']:"",
            'update_time'=>time()
        );
        if (isset($params[$this->tablekey])&&$params[$this->tablekey]) {
            $this->db->set($data)->where(array($this->tablekey => $params[$this->tablekey]))->update($this->tablename);
        }else{
            $this->db->set($data)->set(array("insert_time" => time(),"insert_uid" => $this->user->id,'insert_user' => $this->user->name))->insert($this->tablename);
            echo $this->db->last_query();
        }
        json_success(lang('success'));
    }


    /*
    Anthor：shihaijuan
    Date：20220104
    Desc：获取某一个时间点的周次
    */
    public function weekline(){
        $weeks=array();
        $year=$this->input->get('year')?$this->input->get('year'):date('Y');
        $month=$this->input->get('month')?$this->input->get('month'):($year==date('Y')?date('m'):1);
        $firstday = strtotime($year.'-'.$month.'-01');
        //$current_week=$this->input->get('week')?$this->input->get('week'):($year==date('Y')&&$month==date('m')?intval(date('W')):intval(date('W',$firstday)));
        $current_week=$this->input->get('week')?$this->input->get('week'):0;
        
        if(!$this->input->get('year')&&!$this->input->get('month')){
            $current_week=intval(date('W'));
        }
       
        $lastday=strtotime(date('Y-m-t',$firstday));
        //计算第一个周一的日期
        $monday=$firstday-86400*(date('N',$firstday)-1);
        $first_sunday=$monday+86400*7-1;//第一个周日
        $last_monday=$monday+(4*86400*7);//最后一个周一
        for ($i=1; $i <= 5; $i++) {
            $start=date("Y-m-d",$monday+($i-1)*86400*7);//起始周一
            $end=date("Y-m-d",$monday+$i*86399*7);//结束周日
            if(date('m',strtotime($start))!=$month&&date('m',strtotime($end))!=$month)
            {
                continue;
            }
            if($i==1&&$first_sunday-$firstday<=(86400*3)){
                continue;
            }
            if($i==5&&$lastday-$last_monday<=(86400*3)){
                continue;
            }
            $weeks[]=array(
                'week'=>intval(date('W',strtotime($start))),
                'start'=>date('m/d',strtotime($start)),
                'end'=>date('m/d',strtotime($end)),
            );
        }
        json_success(array(
            'weeks' => $weeks,
            'year'=>$year,
            'month'=>$month,
            'current_month'=>$month,
            'current_week'=>$current_week
        ));
    }
    
    
     /*
    DESC：计划报告
    Author：shihaijuan
    Date：20210105
    */
    public function report(){
        $year=$this->input->get("year");
        $month=$this->input->get("month");
        $week=$this->input->get("week");
        $cycle='week';
        $time='';
        if(!$month){
            $cycle='year';
            $time=strtotime($year.'-01-01');
        }else if(!$week){
            $cycle='month';
            $time=strtotime($year.'-'.$month.'-01');
        }else{
            $days=7*$week-1;
            $last=strtotime($year.'-01-01')+86400*$days-1;
            $N = (date('w',$last)==0?7:date('w',$last))-1;
            $time = strtotime('-' . $N . ' days',$last);
            $time = strtotime(date('Y-m-d',$time));
        }
        $uid=$this->input->get("uid")?$this->input->get("uid"): $this->user->id;
        $where=array("cycle"=>$cycle,'time'=>$time,'insert_uid'=>$uid);
        $plan = $this->db->where($where)->get($this->tablename)->row();
        $row=array(
            'time'=>$time,
            'plan'=>$plan,
            //'active'=>strtotime(date('Ymd'))==$time?true:false,
            'is_edit'=>$uid==$this->user->id?true:false
         );
        json_success(array("row" => $row));
    }
    
    //计划对应的总结
    public function save_summary() {
        $row = $this->db->where($this->tablekey,$this->input->post($this->tablekey))->get($this->tablename)->row();
        $this->permission($row);
        $this->db->where($this->tablekey,$this->input->post($this->tablekey))->set(array("sump" => $this->input->post('sump')))->update($this->tablename);
        json_success(lang('success'));
    }
    //问题、收获
    public function save_problem() {
        $module = $this->input->post("module");
        $content = $this->input->post("content");
        $cycle=$this->input->post("cycle");
        $today=strtotime(date('Ymd'));
        $N = (date('w')==0?7:date('w'))-1;
        $monday = strtotime('-' . ($N) . ' days',$today);
        $time=$this->input->post("time");
        if(!$time){
            if($cycle=="day") $time=$today;
            else if($cycle=="week") $time=$monday;
        }
        $where = array(
            "uid" => $this->user->id,
            "time" => $time,
            "cycle" => $cycle,
            'module'=>$module
        );
        $row = $this->db->where($where)->get($this->tablename_sumup)->row();
        $content = $this->input->post("content");
        $content=str_replace(' ', '',$content);
        $count = mb_strlen($content, "utf-8");
        if ($count < 20) {
            json(array(
                'status' => 0,
                'msg' => lang('msg_work_sumup_less'),
                'data'=> $row
            ));
        }else {
            $check = $this->db->where(array(
                'time <' => $time,
                'time >' => strtotime('-1 month'),
                'content' => strip_tags($content)
            ))->count_all_results($this->tablename_sumup);
            if ($check) {
                json(array(
                    'status' => 0,
                    'msg' => lang('msg_work_sumup_same'),
                    'data'=> $row
                ));
            } else {
                if ($row) {
                    $this->db->set(array("content" => $content))->where("id", $row->id)->update($this->tablename_sumup);
                    $id=$row->id;
                } else {
                    $where["content"] = $content;
                    $where["insert_time"] = time();
                    $this->db->set($where)->insert($this->tablename_sumup);
                    $id=$this->db->insert_id();
                }
                $row = $this->db->where("id",$id)->get($this->tablename_sumup)->row();
                json(array(
                    'status' => 1,
                    'msg' => lang('success'),
                    'data'=> $row
                ));
            }
        } 
    }
    //删除plan数据
    public function delete() {
        $row = $this->db->where($this->tablekey,$this->input->post($this->tablekey))->get($this->tablename)->row();
        $this->permission($row);
        $this->db->where($this->tablekey,$this->input->post($this->tablekey))->delete($this->tablename);
        json_success(lang('success'));
    }
    
    public function finish() {
        $row = $this->db->where($this->tablekey,$this->input->post($this->tablekey))->get($this->tablename)->row();
        $this->permission($row);
        $this->db->where($this->tablekey,$this->input->post($this->tablekey))->set(array("finish_time" => time()))->update($this->tablename);
        json_success(lang('success'));
    }
    
    public function permission($row) {
        $result = false;
        if (!$row) json_fail (lang('undefined'));
        if ($this->user->id == $row->from_uid) {
            $result = true;
        }        
        if (!$result) json_fail (lang('permission_fail'));
    }
}