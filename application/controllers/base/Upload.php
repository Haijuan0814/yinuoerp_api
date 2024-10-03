<?php
//header('Access-Control-Allow-Origin:*');  //支持全域名访问，不安全，部署后需要固定限制为客户端网址
//header('Access-Control-Allow-Methods:POST,GET,OPTIONS,DELETE'); //支持的http 动作
header('Access-Control-Allow-Headers:x-requested-with,content-type,token');  //响应头 请按照自己需求添加。
defined('BASEPATH') OR exit('No direct script access allowed');

use OSS\OssClient;
use OSS\Core\OssException;

class Upload extends CI_Controller {

    public function __construct() {
        parent::__construct();
    } 

    //参数是字段名称
    public function index() {     
        $field = 'upload';
        $upload_path = './upload/';
        if (!is_dir($upload_path)) {
            mkdir($upload_path);
        }
        if ($this->input->post('type') == 'img'){
            $allowed_types = 'gif|jpg|png';
        }else{
            $allowed_types = '*';
        }
        $config=array(
            'upload_path' => $upload_path,
            'allowed_types' => $allowed_types,
            'file_name' => md5(time() . rand(100, 999)),
            'file_ext_tolower' => true,
            
        );
        $this->load->library('upload',$config);
        $this->upload->initialize($config);

        if (!$this->upload->do_upload($field)) {
            return $this->upload->display_errors();
        } else {
            $upload = $this->upload->data();
            $local_url = $upload_path . $upload['file_name'];
            require APPPATH . "third_party/OSS/index.php";
            $ossClient = new OssClient(config_item('oss_id'),config_item('oss_secret'), 'oss-cn-beijing.aliyuncs.com');
            $oss_patch = 'upload/' . date('Ym') . '/' . $upload['file_name'].'jpg';
            $ossClient->uploadFile('yinuo-erp', $oss_patch, $local_url);
            $oss_url = config_item('oss_url') . $oss_patch;

            //删除本地文件
            @unlink($local_url);
            json_success(array(
                'name' => $upload['client_name'],
                'src' => $oss_url,
            )) ;
        }
    }

}
