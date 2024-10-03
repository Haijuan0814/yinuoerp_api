<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class App extends MY_Controller {

    public $tablename = 'base_lego';
    public $tablekey = 'id';
    public $detailtablename = 'base_lego_field';
    public $multitypes = array("checkbox", "multiselect", "departments", "users", "multitree");

    public function __construct() {
        parent::__construct();
    }
    //init-专门输出主表和字段表  
    public function init() {
        $tablename = $this->input->get("tablename");
        $lego = $this->db->select("id,name,tablename,page_size,page_setting,approval_rules")->where("tablename", $tablename)->get($this->tablename)->row();
        if (!$lego) {
            json_fail(lang('lego_undefined') . '主表记录不存在');
        }
        $lego->page_setting = json_decode($lego->page_setting, true);
        //审批规则
        $approval_rules = array();
        if ($lego->approval_rules && explode(',', $lego->approval_rules)) {
            $rules = $this->db->where_in("key",explode(',',$lego->approval_rules))->get("base_approval_rule")->result();
            foreach ($rules as $rule) {
                $approval_rules[] = array('label' => $rule->name, 'value' => $rule->key);
            }
        }
        //字段数据
        $where = array('parent' => $lego->id,'is_delete' => 0);
        $lego_fields = $this->db->where($where)->order_by("listorder desc")->get($this->detailtablename)->result();
        foreach ($lego_fields as $lego_field) {
            $lego_field->page = json_decode($lego_field->page, true);
        }

        json_success(array(
            "lego" => $lego,
            "approval_rules" => $approval_rules,
            "lego_fields" => $lego_fields
        ));
    }
    
    //获取页面button
    public function buttons(){
        $tablename = $this->input->get("tablename");
        $parent = $this->input->get("parent");
        $page = $this->input->get("page")?$this->input->get("page"):"index" ;
        $buttons=$this->db->where(array(
            "table_name"=>$tablename,
            "page"=>$page,
            "is_delete"=>0
        ))->get("base_menu_button")->result();
        $_buttons=array();
        foreach($buttons as $button){
            $auth=$button->auth?json_decode($button->auth, true):array();
            $auth_check=false;
            foreach($auth as $k=>$v){
                if($k=="role"){
                    $result=array_intersect(explode(',',$this->user->roles),explode(',',$v));
                    if(count($result)>0) 
                        $auth_check=true;
                 
                }else if($k=="user"){
                    if(in_array($this->user->id, explode(',', $v))) 
                        $auth_check=true;
                }else if($k=="self"&&$parent){
                    $business=$this->db->where("id",$parent)->get($tablename)->row();
                    if($business&&isset($business->insert_uid)&&$business->insert_uid==$this->user->id){
                        $auth_check=true;
                    }
                }
            }
            if($auth_check){
                $_buttons[$button->name]=array(
                    "name"=>$button->name_cn,
                    "url"=>$button->url,
                    "is_pc"=>$button->is_pc,
                    "is_m"=>$button->is_m,
                );
            }
        }
        json_success($_buttons?$_buttons:(object)null); 
    }

    //list页面预览接口
    public function index($divice="pc") {
        $tablename = $this->input->get("tablename");
        $app_page = $this->input->get("tab") ? "tab=" . $this->input->get("tab") : "";
        $tab = $this->input->get("tab") ? $this->input->get("tab") : "all";
        /* step1权限验证 */
        /*$menus = $this->db->where(array(
                    "table_name" => $tablename,
                    "app_page" => $app_page,
                    "is_delete" => 0))->get("base_menu")->result();

        $menu_ids = array();
        foreach ($menus as $menu) {
            $menu_ids[] = $menu->id;
        }*/
        /* 业务数据获取 */
        $search = $this->input->get("search");
        $search = json_decode($search);
        $order = $this->input->get("order");
        $order = json_decode($order, true);
        $lego = $this->db->select("id,name,tablename,page_size")->where("tablename", $tablename)->get($this->tablename)->row();
        if (!$lego) {
            json_fail(lang('lego_undefined') . '主表记录不存在');
        }
        $where = array(
            'parent' => $lego->id,
            'is_delete' => 0,
        );
        $lego_fields = $this->db->select('id,name,name_cn,form_type,data_type,form_source,page')->where($where)->get($this->detailtablename)->result();
        if (!$lego_fields)
            json_fail(lang('lego_field_undefined'));

        $fields = array("id");
        $default_order = ""; /* 默认排序字段 */
        foreach ($lego_fields as $lego_field) {
            $key = $lego_field->name;
            $_page = json_decode($lego_field->page, true);
            //列表页需要展示的字段
            $_list_check=false;
            if($divice=="pc"){
                $_list_check = $_page && isset($_page["list"]) && $_page["list"];
            }else if($divice=="mobile"){
                $_list_check = $_page && isset($_page["list_m"]) && $_page["list_m"];
            }
            if ($_list_check)
                $fields[] = $lego_field->name;
            //获取默认排序字段
            if ($_page && isset($_page["default_order"]) && $_page["default_order"]){
                $default_order = $lego_field->name;
            }
            //Where Like 搜索开始
            if ($search && isset($search->$key) && $search->$key) {
                if ($lego_field->form_type == "input") {
                    $this->db->like($lego_field->name, $search->$key);
                } else if (in_array($lego_field->form_type, $this->multitypes)) {
                    $this->db->where('(1=1 and FIND_IN_SET(' . $search->$key . ',`' . $lego_field->name . '` ))');
                } else {
                    $this->db->where($lego_field->name, $search->$key);
                }
            }
        }
        /* index/dep/all/area分类 */
        if ($tab == "index")
            $this->db->where("insert_uid", $this->user->id);
        else if ($tab == "department")
            $this->db->where("department_id", $this->user->department_id);
        else if ($tab == "departments")
            $this->db->where("(1=1 and FIND_IN_SET(".$this->user->department_id.",`department_ids`))");
        else if ($tab == "area")
            $this->db->where("area", $this->user->area);
        //----------------------------过滤开始----------------------------
        $this->db->select(implode(',', $fields));
        $page = $this->input->get("page") ? $this->input->get("page") : 0;
        //从前端载入page_size
        if ($this->input->get("page_size")) {
            $page_size = $this->input->get("page_size");
        } else {//从数据库
            $page_size = $lego->page_size ? $lego->page_size : 0;
        }
        //----------------------------过滤结束----------------------------
        $this->db->where("is_delete", 0);
        $total = $this->db->count_all_results($tablename, false);
        //排序
        if ($order) {
            foreach ($lego_fields as $lego_field) {
                $key = $lego_field->name;
                if (isset($order[$key]) && $order[$key]) {
                    if ($order[$key] == "ascend")
                        $this->db->order_by($key . " asc");
                    else if ($order[$key] == "descend")
                        $this->db->order_by($key . " desc");
                }
            }
        }
        if ($default_order) {
            $this->db->order_by($default_order . " desc");
        }
        $this->db->order_by("id desc");
        if ($page_size) {
            $rows = $this->db->limit($page_size, $page * $page_size)->get()->result();
        } else {
            $rows = $this->db->get()->result();
        }
        foreach ($rows as $row) {
            $row = $this->detail_extend($row, $lego_fields);
        }
        json_success(array(
            "rows" => $rows,
            "total" => $total,
            'page' => $page,
            'page_size' => $page_size,
        ));
    }

    //保存数据
    public function save() {
        $tablename = $this->input->post("tablename");
        $lego = $this->db->where("tablename", $tablename)->get($this->tablename)->row();
        if (!$lego)
            json_fail(lang('lego_undefined'));
        $where = array(
            'parent' => $lego->id,
            'is_delete' => 0,
        );
        $lego_fields = $this->db->where($where)->get($this->detailtablename)->result();
        if (!$lego_fields)
            json_fail(lang('lego_undefined'));
        $fields = array();
        $data = array();
        $form=$this->input->post();
        foreach ($lego_fields as $lego_field) {
            if (isset($form[$lego_field->name])) {
                $data[$lego_field->name] = $form[$lego_field->name];
                //bool数据，后端转成1 or 0
                if ($lego_field->form_type == "switch") {
                    $data[$lego_field->name] = $data[$lego_field->name] ? 1 : 0;
                }
            } else {
                /* 系统接管的部分，字段名要统一才适用 */
                if ($lego_field->form_type == "system") {
                    switch ($lego_field->name) {
                        case "insert_uid":
                            if (!$this->input->post("id")) {
                                $data[$lego_field->name] = $this->user->id;
                            }
                            break;
                        case "department_id":
                            if (!$this->input->post("id")) {
                                $data[$lego_field->name] = $this->user->department_id;
                            }
                            break;
                        case "insert_time":
                            if (!$this->input->post("id")) {
                                $data[$lego_field->name] = time();
                            }
                            break;
                        case "update_uid":
                            $data[$lego_field->name] = $this->user->id;
                            break;
                        case "update_time":
                            $data[$lego_field->name] = time();
                            break;
                        default:
                            break;
                    }
                }
            }
        }
        
        if (!$data)
            json_fail(lang('fail'));
        $id = $this->input->post("id");
        if ($this->input->post("id")) {
            $this->db->set($data)->where(array("id" => $this->input->post("id")))->update($tablename);
        } else {
            $this->db->set($data)->insert($tablename);
            $id = $this->db->insert_id();
        }

        //有审批流的处理审批流
        if ($lego->approval_rules&&!$this->input->post("id")) {
            $approval_rule = $this->input->post("approval_rule");
            if (!$approval_rule)
                json_fail(lang('fail'));
            //审批
            $this->approval_model->insert(array(
                'rule' => $approval_rule,
                'tablename' => $tablename,
                'parent' => $id,
            ));
        }
        json_success(lang('success'));
    }

    //switch开关类接口
    public function switch_save() {
        $tablename = $this->input->post("tablename");
        $field = $this->input->post("field");
        $id = $this->input->post("id");
        $row = $this->db->where("id", $id)->get($tablename)->row();
        if (!$row)
            json_fail(lang('undefined'));
        $this->db->where("id", $id)
                ->set(array($field => $row->$field ? 0 : 1))
                ->update($tablename);
        json_success(lang('success'));
    }

    //detail页面预览接口
    public function detail() {
        $tablename = $this->input->get("tablename"); //业务表名
        $id = $this->input->get("id"); //业务ID
        $page_type = $this->input->get("page_type"); //数据用途 detail or form
        $lego = $this->db->select("id,name,tablename,page_size")->where("tablename", $tablename)->get($this->tablename)->row();
        if (!$lego)
            json_fail(lang('lego_undefined'));
        $where = array(
            'parent' => $lego->id,
            'is_delete' => 0
        );
        $lego_fields = $this->db->select('id,name,name_cn,form_type,data_type,form_source')->where($where)->get($this->detailtablename)->result();
        if (!$lego_fields)
            json_fail(lang('lego_undefined'));
        $fields = array();
        foreach ($lego_fields as $lego_field) {
            $fields[] = $lego_field->name;
        }
        $this->db->select(implode(',', $fields));
        $row = $this->db->where('id', $id)->get($tablename)->row();
        $row = $this->detail_extend($row, $lego_fields, $page_type);
        $row ? json_success($row) : json_fail(lang('undefined'));
    }

    public function detail_extend($row, $lego_fields, $page_type = "index") {
        if (!$row)
            return;
        if (!$lego_fields)
            return;
        /* 编辑转义 */
        if ($page_type == "form") {
            //编辑时，多选数据，后端将字符串转成数组给前端
            foreach ($lego_fields as $lego_field) {
                $name = $lego_field->name;
                if (in_array($lego_field->form_type, $this->multitypes)) {
                    $row->$name = explode(',', $row->$name);
                }else if ($lego_field->form_type == "switch") { //bool数据，输出true or false
                    $row->$name = $row->$name ? true : false;
                }else if ($lego_field->form_type == "user") { //user组件，需要同时把id和realname给前端，手机端需要使用
                    if ($row->$name) {        
                        $user_row= cache_user($row->$name);
                        $text=$lego_field->name.'_realname';
                        $row->$text=$user_row?$user_row->realname:"";
                    } 
                }else {
                    $row->$name = $row->$name ? $row->$name : ""; //确保没有0输出
                }               
            }
        } else {/* 非编辑转义 */
            foreach ($lego_fields as $field) {
                $name = $field->name;
                if (isset($row->$name)) {
                    //审批状态单独保留英文名称，目的是根据英文判断前端状态显示的颜色
                    if($name=="approval_status"){
                        $row->approval_status_en = $row->$name;
                    }
                    //列表页，有父级概念的肯定是tree展示，不需要转义parent字段
                    if ($page_type = "index" && $name == "parent")
                        continue;
                    else {
                        switch ($field->form_type) {
                            case "select":
                            case "radio":
                            case "tree":
                                if($field->form_source&&!(strpos($field->form_source,'url') !== false)){
                                    $row->$name = $this->get_source($row->$name, $field->form_source);
                                }
                                break;
                            case "multiselect":
                            case "multitree":
                            case "checkbox":
                                if($field->form_source&&!(strpos($field->form_source,'url') !== false)){
                                    $value = array();
                                    foreach (explode(',', $row->$name) as $k => $v) {
                                        $_value = $this->get_source($v, $field->form_source);
                                        if ($_value)
                                            $value[] = $_value;
                                    }
                                    $row->$name = $value ? implode(',', $value) : "";
                                }
                                
                                break;
                            case "user":
                                if ($row->$name) {
                                    $row->$name=$this->cache_data("base_user",$row->$name);
                                } 
                                break;
                            case "users":
                                $value = array();
                                foreach (explode(',', $row->$name) as $k => $v) {
                                   $_value=$v?$this->cache_data("base_user",$v):"";
                                    if ($_value){
                                        $value[] = $_value;
                                    }
                                }
                                $row->$name = $value ? implode(',', $value) : "";
                                break;
                            case "system"://system接管字段转义
                                if ($field->name == "uid") {
                                    $user = cache_user($row->uid);
                                    $row->uid = $user ? $user->realname : "";
                                } else if ($field->name == "insert_uid") {
                                    $user = cache_user($row->insert_uid);
                                    $row->insert_uid = $user ? $user->realname : "";
                                } else if ($field->name == "insert_time") {
                                    $row->insert_time = $row->insert_time ? date('Y-m-d H:i', $row->insert_time) : "";
                                } else if ($field->name == "department_id") {
                                    $department = cache_department($row->department_id);
                                    $row->department_id = $department ? $department->name : "";
                                }
                                break;
                            default:
                                break;
                        }
                        
                        
                    }
                }
            }
            $row->trueId = $row->id;
        }
        return $row;
    }

    //获取业务中需要的配置信息
    public function setting() {
        $tablename = $this->input->get("tablename");
        $lego = $this->db->select("id,name,tablename,page_size")->where("tablename", $tablename)->get($this->tablename)->row();
        if (!$lego) json_fail(lang('lego_undefined') . '主表记录不存在');
        
        $where = array('parent' => $lego->id,'is_delete' => 0);
        $lego_fields = $this->db->select('id,name,form_type,form_source,data_type')->where($where)->get($this->detailtablename)->result();
        if (!$lego_fields) json_fail(lang('lego_field_undefined'));
        json_success($this->_setting($lego_fields));
    }

    private function _setting($lego_fields) {
        $ret = array();
        foreach ($lego_fields as $field) {
            $key = "";
            $value = $field->form_source;
            $source = json_decode($field->form_source);
            if ($source) {
                foreach ($source as $k => $v) {
                    $key = $k;
                    $value = $v;
                }
            }
            switch ($field->form_type) {
                case "radio":
                case "checkbox":
                case "select":
                case "multiselect":
                    if ($key == "tablename") { /* tablename */
                        $ret[$field->name] = array("url" => 'base/app/url_kv_source/' . $value);
                    }else if ($key == "url") { /* url */
                        $ret[$field->name] = array("url" => $value);
                    } else if ($key == "setting") { /* setting */
                        $form_source = array();
                        $data = $this->setting_model->item($value);
                        if ($data) {
                            foreach ($data as $k => $v) {
                                $form_source[] = array('label' => $v, 'value' => strval($k));
                            }
                        }
                        $ret[$field->name] = array("array" => $form_source);
                    } else if ($key == "value") { /* 自定义 */
                        $data = explode(',', $value);
                        $form_source = array();
                        foreach ($data as $k => $v) {
                            $form_source[] = array('label' => $v, 'value' => $v);
                        }
                        $ret[$field->name] = array("array" => $form_source);
                    }
                    break;
                case "tree":
                case "multitree":
                    if ($key == "tablename") { /* url */
                        $source = $this->url_tree_source($value);
                        $ret[$field->name] = array("arrayTree" => $source);
                    } else {
                        $ret[$field->name] = array();
                    }
                    break;
                default:
                    break;
            }
        }
        return $ret;
    }

    //有数据源的情况，根据实际值+数据源配置，获取对应的实际数据,$data数据库存储值，$form_source配置的数据源
    public function get_source($data, $form_source) {
        //数据源转换
        $form_source = json_decode($form_source);
        $source_key = $source_value = "";
        if ($form_source) {
            foreach ($form_source as $k => $v) {
                $source_key = $k;
                $source_value = $v;
            }
        }
        $ret = "";
        if ($source_key == "tablename") {
            if ($data) $ret=$this->cache_data($source_value,$data);
        } else if ($source_key == "setting") { /* setting */
            $ret = element($data, $this->setting_model->item($source_value));
        } else if ($source_key == "value") { /* setting */
            $ret = $data;
        }
        return $ret;
    }

    //根据url-tablename获取数据
    public function url_kv_source($tablename) {
        $lego = $this->db->select("id")->where("tablename", $tablename)->get($this->tablename)->row();
        if (!$lego)
            json_fail(lang('lego_undefined'));
        $where = array(
            'parent' => $lego->id,
            'is_delete' => 0,
            'restore_text' => 1
        );
        $restore_fields = $this->db->select('id,name')->where($where)->get($this->detailtablename)->result();
        if (!$restore_fields)
            json_fail(lang('lego_undefined'));
        $fields = array("id");
        foreach ($restore_fields as $lego_field) {
            $fields[] = $lego_field->name;
        }
        $this->db->select(implode(',', $fields));
        $rows = $this->db->get($tablename)->result();
        $ret = array();
        $_ret = array();
        foreach ($rows as $row) {
            $text = array();
            foreach ($fields as $k => $field) {
                if ($field == "id")
                    continue;
                $text[] = $row->$field;
            }
            $text = implode(',', $text);
            $ret[] = array('label' => $text, 'value' => $row->id);
            $_ret[$row->id] = $text;
        }
        json_success($ret);
    }

    //url_tree_source，此方法不再给前端二次调用，因为tree不存在分页
    public function url_tree_source($tablename) {
        $lego = $this->db->select("id")->where("tablename", $tablename)->get($this->tablename)->row();
        if (!$lego)
            json_fail(lang('lego_undefined'));
        $where = array(
            'parent' => $lego->id,
            'is_delete' => 0
        );
        $lego_fields = $this->db->select('id,name,restore_text,page')->where($where)->get($this->detailtablename)->result();
        if (!$lego_fields)
            json_fail(lang('lego_undefined'));
        //默认排序字段 & 转义字段
        $default_order = "";
        $restore_fields = array("id", "parent");
        foreach ($lego_fields as $lego_field) {
            if ($lego_field->restore_text == 1) {
                $restore_fields[] = $lego_field->name;
            }
            $_page = json_decode($lego_field->page, true);
            if ($_page && isset($_page["default_order"]) && $_page["default_order"])
                $default_order = $lego_field->name;
        }
        if ($default_order)
            $this->db->order_by($default_order . " desc");
        if ($tablename == "base_department") {
            $this->db->where("is_key", 1);
        }
        $rows = $this->db->select(implode(',', $restore_fields))->where("is_delete", 0)->get($tablename)->result();
        $ret = array();
        foreach ($rows as $row) {
            $text = array();
            foreach ($restore_fields as $k => $field) {
                if ($field == "id" || $field == "parent")
                    continue;
                $text[] = $row->$field;
            }
            $text = implode(',', $text);
            $ret[] = array(
                'key' => $row->id,
                'value' => $row->id,
                'parent' => $row->parent,
                'title' => $text
            );
        }
        return $ret;
    }

    //业务中-逻辑删除
    public function delete() {
        $id = $this->input->post("id");
        $tablename = $this->input->post("tablename");
        $this->db->where("id", $id)->set(array('is_delete' => 1))->update($tablename);
        json_success(lang('success'));
    }
    
    //lego-打印回调（暂时没用）
    public function legoprint(){
        $tablename = $this->input->get("tablename"); //业务表名
        $id = $this->input->get("id"); //业务ID
        $url="/base/app/print_template?tablename=".$tablename."&id=".$id;
        /*$data=array(
            "tablename"=>$tablename,
            "parent"=>$id,
            "url"=>$url
        );
        if(!$this->db->where($data)->count_all_results("oa_print")){
            $data["insert_uid"]=$this->user->id;
            $data["insert_time"]=time();
            $this->db->set($data)->insert("oa_print");
        };*/
        $ret=array(
            'url'=>$url
        );
        json_success($ret);
    }
    
    //业务中-通用打印模板
    public function print_template(){
        $tablename = $this->input->get("tablename"); //业务表名
        $id = $this->input->get("id"); //业务ID
        $lego = $this->db->select("id,name,tablename,page_size")->where("tablename", $tablename)->get($this->tablename)->row();
        if (!$lego)
            json_fail(lang('lego_undefined'));
        $where = array(
            'parent' => $lego->id,
            'is_delete' => 0
        );
        $lego_fields = $this->db->select('id,name,name_cn,form_type,data_type,form_unit,form_source,page')->where($where)->get($this->detailtablename)->result();
        if (!$lego_fields)
            json_fail(lang('lego_undefined'));
        $fields = array();
        foreach ($lego_fields as $lego_field) {
            $fields[] = $lego_field->name;
        }
        $this->db->select(implode(',', $fields));
        $row = $this->db->where('id', $id)->get($tablename)->row();
        $row = $this->detail_extend($row, $lego_fields, "detail");
        
        $html=$this->load->view('print/app',array('row'=>$row,'lego'=>$lego,'lego_fields'=>$lego_fields),true);
        json_success($html); 
    }
    
    //缓存数据，返回id对应的retore_text值
    private function cache_data($tablename,$id){
        $key = $tablename.'_' .$id.'_2';
        if (!$this->cache->memcached->get($key) && $id) { 
            $lego = $this->db->select("id")->where("tablename", $tablename)->get($this->tablename)->row();
            if (!$lego) json_fail(lang('lego_undefined').'1');
            $where = array(
                'parent' => $lego->id,
                'is_delete' => 0,
                'restore_text' => 1
            );
            $restore_fields = $this->db->select('id,name')->where($where)->get($this->detailtablename)->result();
            if (!$restore_fields) json_fail(lang('lego_undefined').'2');
            $fields = array("id");
            foreach ($restore_fields as $lego_field) {
                $fields[] = $lego_field->name;
            }
            $row=$this->db->select(implode(',', $fields))->where("id", $id)->get($tablename)->row();
            $text = array();
            foreach ($fields as $k => $field) {
                if ($field == "id") continue;
                if($row) $text[] = $row->$field;
            }
            $text = $text?implode(',', $text):"";
            $this->cache->memcached->save($key, $text,300);
        }
        return $this->cache->memcached->get($key);
    }
}
