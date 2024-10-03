<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Comment_model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->tablename = 'base_comment';
    }

    //缓存评论总数
    public function total($tablename, $parent) {
        $key = __CLASS__ . __FUNCTION__ . $tablename . $parent . '_1';
        if (!$this->cache->memcached->get($key)) {
            $t = $this->db->where(array(
                        "tablename" => $tablename,
                        "parent" => $parent,
                        "is_hidden" => 0,
                    ))->count_all_results($this->tablename);
            $this->cache->memcached->save($key, $t, 3600);
        }
        return $this->cache->memcached->get($key);
    }

}
