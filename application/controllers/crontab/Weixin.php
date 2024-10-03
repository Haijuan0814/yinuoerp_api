<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Weixin extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->library('weixinapi');
    }

    public function index() {
        $this->sync_department();
        $this->sync_user(); 
        exit('crontab weixin success');
    }
    
    public function sync_department() {
        $rows = $this->db->select("id,name,parent,listorder")->where("is_key", 1)->where("is_delete", 0)->where('id !=',1)->order_by("listorder desc")->get("user_department")->result();
       
        $arrs=array();
        foreach($rows as $row){
            $arrs[] = array(
                'name' => $row->name,
                "id" => $row->id,
                "parentid" => $row->parent,
                "order" => $row->listorder,
            );
        }
        $fp = fopen(APPPATH . "department.csv", "w+");
        fwrite($fp, chr(239) . chr(187) . chr(191) . "部门名称,部门ID,父部门ID,排序" . PHP_EOL);
        foreach ($arrs as $arr) {
            fwrite($fp, implode(",", $arr) . PHP_EOL);
        }
        fclose($fp);
        $this->weixinapi->access_token();
        $res = $this->weixinapi->upload_media_csv(APPPATH . "department.csv");
        if ($res->errcode == 0) {
            $res = $this->weixinapi->send('/batch/replaceparty', array("media_id" => $res->media_id), 'post');
        }
        @unlink(APPPATH . "department.csv");
        exit(json_encode(array(
            'message' => lang('success'),
            'data' => $res
        )));
    }

    public function sync_user() {
        $rows = $this->db->select("name,id,mobile,email,department_id,job_name,sex,address,age")
                ->where(array("is_leave" => 0))
                ->order_by("id asc")->get("user")->result_array();
        $pattern = "/^([0-9A-Za-z\\-_\\.]+)@([0-9a-z]+\\.[a-z]{2,3}(\\.[a-z]{2})?)$/i";
        $fp = fopen(APPPATH . "user.csv", "w+");
        fwrite($fp, chr(239) . chr(187) . chr(191) . "姓名,帐号,手机号,邮箱,所在部门,职位,性别,地址,年龄" . PHP_EOL);
        foreach ($rows as $key => $one) {
            if (!preg_match($pattern, $one['email'])) {
                $one['email'] = '';
            }
            $one['age'] = $one['age'] . '岁';
            fwrite($fp, implode(",", $one) . PHP_EOL);
        }
        fclose($fp);
        $this->weixinapi->access_token();
        $res = $this->weixinapi->upload_media_csv(APPPATH . "user.csv");
        if ($res->errcode == 0) {
            $res = $this->weixinapi->send('/batch/replaceuser', array('media_id' => $res->media_id), 'post');
            exit(json_encode(array(
                'message' => lang('success'),
                'data' => $res
            )));
        }
        //@unlink(APPPATH . "user.csv");
    }

}
