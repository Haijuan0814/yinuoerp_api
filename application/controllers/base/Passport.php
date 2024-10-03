<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Passport extends CI_Controller {

    public function __construct() {
        parent::__construct();
        if ($this->input->method() == 'options') exit();        
    }

    /*
    Anthor：shihaijuan
    Date：2021-12-15
    Desc：当前用户
    */
    public function currentUser1(){
        $id = $this->input->get('id')?$this->input->get('id'):1001;
        $data = array();
        if ($id){
            $data = $this->user_model->row($id,'id,avatar,department_name,job_name,name,tags,roles,signature,mobile,address');
            if($data){
                $data->tags=explode(',',$data->tags);
                $data->roles=explode(',',$data->roles);
            }
        }else{
            $this->user_model->row($this->user->id,$select);
        }
        //json($data);
        json_success($data);

    }

    /*
    Anthor：shihaijuan
    Date：20211215
    Desc：登录
    */
    public function login() {

        //$this->cache->memcached->save("test", '111', 60);
        //echo $this->cache->memcached->get("test");
        //exit();
        $params=file_get_contents("php://input");
        $params = json_decode($params,true);
        $name = $params["username"];
        $password = $params["password"];
        //$name = $this->input->post('username');
        //$password = $this->input->post('password');
        //信息不完整
        if (!$name || !$password) {
            json_fail('请输入账户信息');
        }
        $row = $this->db->select('id,name,department_id,department_name,job_name,avatar,mobile,mix,password,token,tags,address,signature,roles')
        ->where(array("name"=>$name,"is_leave" => 0))->get('user')->row();

        if (!$row) {
            json_fail('用户不存在或已离职');
        }
        if (md5(md5($password) . $row->mix) == $row->password) {
            json_success($row);
        } else {
            json_fail('密码错误');
        }
        $row->tags=explode(',',$row->tags);
        $row->roles=explode(',',$row->roles);
        json_success($row);
        
    }

    /*
    Anthor：shihaijuan
    Date：20211215
    Desc：忘记密码+设置密码
    */
    public function forget() {
        $params=file_get_contents("php://input");
        $params = json_decode($params,true);
        $mobile = $params["mobile"];
        $password = $params["password"];
        $repeat_password = $params["repeat_password"];
        if ($password!=$repeat_password) {
            json_fail("两次密码不一致");
        }
        //正常用户
        $user = $this->db->select('id')->where(array("mobile" => $mobile, "is_leave" => 0))->get("user")->row();
        if (!$user) {
            json_fail("用户不存在或已离职");
        }
        $mix = rand(1000, 9999);
        $this->db->set(array(
            'password' => md5(md5($password) . $mix),
            'mix' => $mix,
            'token' => md5('token-'.$mix.$user->id)
        ))->where("id", $user->id)->update("user");
        //直接授权登陆
        
        $row = $this->db->select('id,name,department_id,department_name,job_name,avatar,mobile,mix,password,token,tags,address,signature,roles')
        ->where(array("mobile" => $mobile, "is_leave" => 0))->get('user')->row();
        $row->tags=explode(',',$row->tags);
        $row->roles=explode(',',$row->roles);

        json_success($row);
    }


    //注册，结合验证码（暂时没用）
    public function register() {
        $mobiphone = $this->input->post("mobiphone");
        $verifycode = $this->input->post("verifycode");
        $password = $this->input->post("password");
        //验证手机验证码
        $cache_key = 'verifycode' . $mobiphone;
        $cache_data = $this->cache->memcached->get($cache_key);
        if (!$cache_data || $cache_data != $verifycode) { //
            json_fail("手机验证码不正确");
        }
        //正常用户
        $user = $this->db->select('id')->where(array("mobiphone" => $mobiphone, "is_delete" => 0, "is_exit" => 0))->get("base_user")->row();
        if (!$user) {
            json_fail("用户不存在");
        }
        $mix = rand(1000, 9999);
        $this->db->set(array(
            'password' => md5(md5($password) . $mix),
            'mix' => $mix,
            'token' => md5('token-'.$mix.$user->id)
        ))->where("id", $user->id)->update("user");
        //直接授权登陆
        $user = $this->db->select('id,name,token,photo')->where('id', $user->id)->get('name')->row();
        if($user){
            //$user->avatar = str_replace('http://','//', $user->photo) . '_50p';
        }
        json_success($user);
    }

    //获取验证码
    public function verifycode() {
        $mobiphone = $this->input->post("mobiphone");
        //利用memcache防止短期内重复发送
        $cache_key = 'verifycode' . $mobiphone;
        if (!$mobiphone || !preg_match("/^1[345789]{1}\d{9}$/", $mobiphone)) {
            json_fail("手机号码格式错误");
        }
        //防止公开注册
        $row = $this->db->where(array("mobiphone" => $mobiphone, "is_delete" => 0, "is_exit" => 0))->get('base_user')->row();
        if (!$row) {
            json_fail('手机号在公司名单内不存在');
        }        
        if ($this->cache->memcached->get($cache_key)) {
            json_fail('验证码已发送了哦，请耐心等待，如果没有收到请一分钟后重新点击发送');
        } else {
            //短期内没发送，则发送
            $code = rand(1000, 9999);
            $sms_return = send_sms($mobiphone, '您的验证码是' . $code);
            $this->cache->memcached->save($cache_key, $code, 600);
            json_success('操作成功！验证码已发送');
        }
    }


    public function outLogin(){

    }
}
