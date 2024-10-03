<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Workplan extends MY_Controller {
    public $tablename = 'oa_work_plan';
    public $tablekey = 'id';
    public $tablename_sumup = 'oa_work_sumup';
    public function __construct() {
        parent::__construct();
    }
    
    public function index(){
        $today=strtotime(date('Ymd'));
        $N = (date('w')==0?7:date('w'))-1;
        $monday = strtotime('-' . $N . ' days',$today);
        $time=$this->input->get("time")?$this->input->get("time"):$today;     
        $cycle=$this->input->get("cycle")?$this->input->get("cycle"):"day";     
        //if($cycle=="week") $time=$monday;
        $is_plan_edit=($cycle=="day"&&$time>=$today)||($cycle=="week"&&$time>=$monday)?true:false;
        $is_sump_edit=($cycle=="day"&&$time==$today)||($cycle=="week"&&$time==$monday)?true:false;
        $uid=$this->input->get("uid")?$this->input->get("uid"): $this->user->id;
        $where=array("cycle"=>$cycle,'time'=>$time);
        $plans = $this->db->where($where)->where('module', "plan")->where('(uid = ' .$uid . ' OR from_uid = ' . $uid. ')')->get($this->tablename)->result();
        foreach($plans as $plan){
            //$plan->sump=$this->db->where("parent",$plan->id)->where("module","sumup")->get($this->tablename)->row();
        }
        $meetings=$this->db->where($where)->where('module', "meeting")->where('(uid = ' . $uid . ' OR from_uid = ' .$uid. ')')->get($this->tablename)->result();
        $sump_q = $this->db->where($where)->where("module","question")->where('uid',$uid)->get($this->tablename_sumup)->row();
        $sump_get = $this->db->where($where)->where("module","get")->where('uid',$uid)->get($this->tablename_sumup)->row();
        json_success(array(
            "plans" => $plans,
            "meetings" => $meetings,
            'sump_q' => $sump_q,
            'sump_get' => $sump_get,
            'is_plan_edit'=>$uid== $this->user->id&&$is_plan_edit?true:false,
            'is_sump_edit'=>$uid== $this->user->id&&$is_sump_edit?true:false,
            'uid'=>$this->user->id,
            'monday'=>date('Y-m-d',$monday)
        ));
    }
    
    //保存 计划、写夕会后安排
    public function save_plan() {
        $cycle=$this->input->post("cycle");
        $today=strtotime(date('Ymd'));
        $N = (date('w')==0?7:date('w'))-1;
        $monday = strtotime('-' . ($N) . ' days',$today);
        $time=$this->input->post("time");
        if(!$time){
            if($cycle=="day") $time=$today;
            else if($cycle=="week") $time=$monday;
        }
        $data=array(
            "parent"=>$this->input->post("parent")?$this->input->post("parent"):0,
            "uid" => $this->input->post("to_uid")?$this->input->post("to_uid"):$this->user->id,
            "from_uid"=>$this->user->id,
            "time" => $time,
            "cycle" => $this->input->post("cycle"),
            "module" => $this->input->post("module"),
            "content" => $this->input->post("content")
        );
        if ($this->input->post($this->tablekey)) {
            $id = $this->input->post($this->tablekey);
            $this->db->set($data)->where("id",$id)->update($this->tablename);
        }else{
            $this->db->set($data)->set(array("insert_time" => time()))->insert($this->tablename);
        }
        json_success(lang('success'));
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