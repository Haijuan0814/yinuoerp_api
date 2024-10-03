<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Welcome extends MY_Controller {

    public function __construct() {
        parent::__construct();
    }

    public function index() {
        
        //通知
        $notice = $this->db->select('id,title')->where('end_time >', time())->where('is_audit', 1)->where('is_send', 1)->order_by('id desc')->get('oa_notice')->result();
        
        //新闻
        $news = $this->db->select('id,title,uid,start_time')->where('start_time >', time())->where('start_time <', strtotime('+15 day'))->order_by('start_time asc')->get('oa_news')->result();
        foreach ($news as $row) {
            $row->insert_user = cache_user($row->uid);
        }
        
        //用车
        $car = $this->db->where(array('module' => "car", 'start_time>' => strtotime(date('Ymd')), 'end_time<' => strtotime('+1 day', strtotime(date('Ymd')))))->get("app_calendar")->result();
        
        //请假
        $qingjia = $this->db->where(array(
                    'end_time >' => time(),
                    'start_time <' => strtotime('+3 day'),
                ))->order_by('end_time')->get('oa_leave')->result();
        foreach ($qingjia as $row) {
            $row->insert_user = cache_user($row->uid);
        }
        
        json_success(array(
            'notice' => $notice,
            'news' => $news,
            'car' => $car,
            'qingjia' => $qingjia,
        ));
    }
}
