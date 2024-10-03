<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Approval extends MY_Controller {
    public $tablename = 'base_approval';
    public $tablekey = 'id';
    public function __construct() {
        parent::__construct();
        //$modules=$this->setting_model->item("base_module");
        //$this->approval_types=$this->setting_model->item('base_approval_type1',true);
        $this->approval_types=array(
            'check'=>'审核',
            'recheck'=>'复审',
            'print'=>'打印',
            'buy'=>'采购',
            'feedback'=>'反馈',
        );
    }
    
    //我的审批中心
    public function index() {
        $page_per = 10; 
        $page = $this->input->get("page") ? $this->input->get("page") : 0;
        $this->db->where('forward_uid',$this->user->id)->where("status","TODO")->order_by('id desc');
        $total = $this->db->count_all_results($this->tablename, false);
        $rows = $this->db->limit($page_per, $page * $page_per)->get()->result();
        $approval_type=$this->setting_model->item("base_approval");
        foreach($rows as $i=>$row){
            $business_row=$this->db->where("id",$row->parent)->get($row->tablename)->row();
            if($business_row){
                $menu=$this->db->where("table_name",$row->tablename)->get("base_menu")->row();
                $title=$menu?$menu->name:"";
                $title.= element($row->type, $this->approval_types);
                $row->title=$title;
                $description="";
                if(isset($business_row->insert_time)&&isset($business_row->insert_uid)){
                    $user= cache_user($business_row->insert_uid);
                    $description=$user?$user->realname."于".date("Y-m-d H:i",$business_row->insert_time)."提交一份".($menu?$menu->name:""):"";
                }
                $row->description=$description;
                    
            }else{
                unset($rows[$i]);
                $total--;
            }
        }
        json_success(array(
            "rows" => $rows,
            "total" => $total,
            'page' => $page,
        ));
    }
    
    //审批跳转
    public function go() {
        $url = '';
        $id = $this->input->post('id');
        $row = $this->db->where(array('id' => $id))->get($this->tablename)->row();
        if (!$row) json_fail('approval deleted');
        $route = $this->db->where(array('table_name' => $row->tablename))->get('base_menu')->row();
        if (!$route) json_fail ('找不到匹配的路由url');
        $url = $route->url_message;
        //多参数和单参数
        $url = str_replace('{q}',$row->parent,$url);    
        json_success(array(
            "url" => $url,
        ));
    }
    
    //审批流水
    public function admin() {
        $page_per = 20; 
        $page = $this->input->get("page") ? $this->input->get("page") : 0;
        if ($this->input->get("tablename")) $this->db->like('tablename',$this->input->get("tablename"));
        if ($this->input->get("parent")) $this->db->where('parent',$this->input->get("parent"));
        $this->db->order_by('id desc');
        $total = $this->db->count_all_results($this->tablename, false);
        $rows = $this->db->limit($page_per, $page * $page_per)->get()->result();
        $settings=$this->_setting();
        foreach ($rows as $row) {
            $row->status_name=element($row->status,$settings['status']);
            $row->user=cache_user($row->uid);
            $row->forward_user=cache_user($row->forward_uid);
            $menu=$this->db->where("table_name",$row->tablename)->where("is_delete",0)->get("base_menu")->row();
            $row->tablename=$menu?$menu->name:$row->tablename;
            $row->type=element($row->type,$this->approval_types);
        }
        json_success(array(
            "rows" => $rows,
            "total" => $total,
            'page' => $page,
        ));
    }
    
    /*
    DESC:详情页准备的审批时间轴
    Author:shihaijuan
    Date:20211222
    */
    public function timeline() {
        $tablename = $this->input->get("tablename");
        $parent = $this->input->get("parent");
        $field="insert_uid,insert_user,insert_time";
        //if($tablename=="finance_baoxiao") $field="uid as insert_uid,create_time as insert_time";
        //if($tablename=="finance_chuchai") $field="uid as insert_uid,create_time as insert_time";
        $business_row=$this->db->select($field)->where('id',$parent)->get($tablename)->row();  
        if(!$business_row) json_fail(lang('undefined'));
        $flows[]=array(
            'id'=>0,
            'step'=>-1,
            'type'=>'insert',
            'type_text'=>$tablename=="hr_contract"?"签订合同":'提交申请',
            'status'=>'SUCCESS',
            'forward_uid'=>$business_row->insert_uid,
            //'user'=>cache_user($business_row->insert_uid),
            'user'=>$this->user_model->row($business_row->insert_uid),
            'update_time'=>$business_row->insert_time
        );
        $rows = $this->db->select('id,step,type,status,forward_uid,update_time')->where(array(
            'tablename' => $tablename,'parent' => $parent
        ))->order_by('step asc')->get($this->tablename)->result();     
        $step=0;
        $current_step=0;
        $status="SUCCESS";
        $update_time=$business_row->insert_time;
        foreach ($rows as $k=>$row) {
            if($row->forward_uid){
                //$row->user = cache_user($row->forward_uid);
                $row->user = $this->user_model->row($row->forward_uid);
            }else{
                $row->user = array(
                    "id"=>0,
                    "avatar"=>"//bosiju.xiyogo.com/static/user_default.png_50p",
                    "name"=>"系统"
                );
            }
            if($row->status=="TODO") $current_step=$row->step;
            $row->type_text=element($row->type,$this->approval_types);
            $flows[]=$row;
            if($k==count($rows)-1){
                $step=$row->step+1;
                $status=$row->status=="SUCCESS"?"SUCCESS":"WAIT";
                if($row->status=="SUCCESS"){
                    $current_step=$row->step+1;
                }else if($row->status=="FAIL"){
                    $current_step=$row->step;
                }
                $update_time=$row->update_time;
            }
            
        }
        
        $flows[]=array(
            'id'=>0,
            'step'=>$step,
            'type'=>'finish',
            'type_text'=>'完成',
            'status'=>$status,
            'forward_uid'=>"",
            'user'=>"",
            'update_time'=>$update_time
        );
        
        json_success(array('list'=>$flows,'current_step'=>$current_step+1)); 
    }
    
    public function timeline_buttons(){
        $tablename=$this->input->post('tablename');
        $parent=$this->input->post('parent');
        $buttons=$this->approval_model->get_buttons($tablename,$parent);
        json_success($buttons); 
    }
    

    /*
    Author:Shihaijuan
    Date:20211222
    DESC:处理：可通过1/可拒绝-1/忽略-2/转处理-3\
    */
    public function reply() {
        $id=$this->input->get("id");
        $row = $this->db->where('id',$id)->where('status','TODO')->get($this->tablename)->row();
        if (!$row) json_fail ($this->tablename . lang('undefined'));
        $tablename=$row->tablename;
        $parent=$row->parent;
        $menu=$this->db->where("table_name",$tablename)->get("base_menu")->row();
        $business_row=$this->db->where('id',$parent)->get($tablename)->row();    
        $description=($business_row->insert_user)."于".date("Y-m-d H:i",$business_row->insert_time)."提交一份".$menu->name."申请";

        $approval_status="check";
        $_status=$this->input->get("status");
        //更新当前审批流SUCCESS or FAIL
        $this->db->where("id",$id)->set(array(
            'status'=>$_status,
            'update_time'=>time()
        ))->update($this->tablename);
        //如果同意的话，判断是否生成下一步审核，如果到终点了，那么就执行回调，否则继续审批中状态
        if($_status=='FAIL') {
            $approval_status="fail";// $IS_END_STEP=TRUE;//审批拒绝，审批流结束
        }else if($_status == 'SUCCESS'){//审批通过，判断有没有下一个流程，没有流程了，name审批流结束
            $next= $this->db->where(array(
                'tablename' => $tablename,
                'parent' => $row->parent,
                'step' => $row->step+1,
            ))->order_by("step asc")->get($this->tablename)->row();   
            if($next){
                //如果下一流程是WAIT，去通知，有可能超时打印，下一流程不是WAIT，这种情况不需要多余操作
                if($next->status=="WAIT"){
                    //下一个审批流为TODO
                    $this->db->where("id",$next->id)->set(array('status'=>"TODO"))->update($this->tablename);
                    $approval_status=$next->type;
                    //通知下一个审批人
                    $message = array(
                        "module" => $tablename,
                        "param" => $parent,
                        "title" => "您有一条".$menu->name."申请待".element($next->type,$this->approval_types),
                        "description" => $description,
                        'to_uid'=>$next->forward_uid
                    );
                    $this->message_model->insert($message);
                }
            }else{
                $approval_status="success";
            } 
        }
        //更新业务中的approval_status
        $this->db->where("id",$parent)->set(array('status'=>$approval_status))->update($tablename);
        //通知业务申请人
        if($menu&&$menu->url_message&&($approval_status=="success"||$approval_status=="fail")){
            $text = '你的'.$menu->name.'申请已经'.element($row->type,$this->approval_types). ($_status=='SUCCESS'?'通过':'拒绝');
            //提醒格式
            $message = array(
                "module" => $tablename,
                "param" => $parent,
                "title" => $text,
                "description" => $description,
                'to_uid'=>$business_row->insert_uid
            );
            $this->message_model->insert($message);
        }
        
        //触发回调，将回调结果再存回主表，以供核对
        if ($row->business_url){
            //$business_callback = modules::run($row->business_url,$this->input->post());
            //$this->db->where('id',$id)->set('business_callback',$business_callback)->update($this->tablename);
        }
        //处理完毕
        json_success(lang('success'));
    }
    
    //审批人转让
    public function transfer(){
        $id=$this->input->post("id");
        $row = $this->db->where('id',$id)->where('status','TODO')->get($this->tablename)->row();
        if (!$row) json_fail ($this->tablename . lang('undefined'));
        $tablename = $row->tablename;
        $parent =  $row->parent;
        $this->db->set(array(
            'forward_uid'=>$this->input->post("forward_uid")
        ))->where("id", $row->id)->update($this->tablename);
        //通知新的审批人
        $menu=$this->db->where("table_name",$tablename)->get("base_menu")->row();
        if($menu&&$menu->url_message){
            $business_row=$this->db->where('id',$parent)->get($tablename)->row();    
            $insert_user= cache_user($business_row->insert_uid);
            $description=($insert_user?$insert_user->realname:"")."于".date("Y-m-d H:i",$business_row->insert_time)."提交一份".$menu->name."申请";
            $message = array(
                "module" => $tablename,
                "param" => $parent,
                "title" => "您有一条".$menu->name."申请待".element($row->type,$this->approval_types),
                "description" => $description,
                'to_uid'=>$this->input->post("forward_uid")
            );
            $this->message_model->insert($message);
        }
        json_success(lang('success'));
    }
    
    public function setting() {
        json_success($this->_setting());
    }

    private function _setting() {
        return array(
            'status' => array(
                '' => '全部',       
                'WAIT' => '等待中',
                'TODO' => '进行中',
                'SUCCESS' => '处理成功',
                'FAIL' => '已驳回',
            ),
        );
    }
}