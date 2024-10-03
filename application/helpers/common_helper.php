<?php

//json
function json($param) {
    exit(json_encode($param));
}

//json success
function json_success($param = null , $withMessage=1 , $debug = false) {
    $CI = & get_instance();
    $arr1 = array('success'=>true);
    $arr2 = is_string($param) ? array('errorMessage' => $param) : array('data' => $param);
    if (isset($CI->db) && $debug) $arr2['debug'] = $CI->db->last_query();
    json(array_merge($arr1, $arr2));
}

//json fail
function json_fail($msg = null) {
    json(array('success'=>false,'errorMessage'=>$msg));
}

//user
function cache_user($id) {
    $CI = & get_instance();
    if (!$id){
        return ;
    }else if (strstr($id,',')){
        $ret = array();
        foreach (explode(',', $id) as $id) $ret[] = $CI->user_model->row($id);
        return $ret;
    }else{
        return $CI->user_model->row($id);
    }
}

//department
function cache_department($id) {
    $CI = & get_instance();
    return $CI->department_model->row($id);
}

//comment
function cache_comment($tablename,$parent) {
    $CI = & get_instance();
    $CI->load->model('base/comment_model');
    return $CI->comment_model->total($tablename,$parent);
}

//发短信
function send_sms($mobile, $content) {
    $yunpian = config_item('yunpian');
    return request_curl(config_item('yunpian_url'), array(
        'apikey' => config_item('yunpian_apikey'),
        'mobile' => $mobile,
        'text' => config_item('yunpian_signature') . $content,
            ), false);
}

// 抓取底层。参数1：访问的URL，参数2：post数据(不填则为GET)
function request_curl($url, $postdata = null, $postencode = true) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_TIMEOUT, 10);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
    if ($postdata) {
        $data = $postencode ? json_encode($postdata) : http_build_query($postdata);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    }
    $output = curl_exec($curl);
    curl_close($curl);
    return $output;
}

//金额转大写
function money_chinese($number = 0, $int_unit = '元', $is_round = TRUE, $is_extra_zero = FALSE) {
    $parts = explode('.', $number, 2);
    $int = isset($parts[0]) ? strval($parts[0]) : '0';
    $dec = isset($parts[1]) ? strval($parts[1]) : '';
    $dec_len = strlen($dec);
    if (isset($parts[1]) && $dec_len > 2) {
        $dec = $is_round ? substr(strrchr(strval(round(floatval("0." . $dec), 2)), '.'), 1) : substr($parts[1], 0, 2);
    }
    if (empty($int) && empty($dec)) {
        return '零';
    }
    $chs = array('0', '壹', '贰', '叁', '肆', '伍', '陆', '柒', '捌', '玖');
    $uni = array('', '拾', '佰', '仟');
    $dec_uni = array('角', '分');
    $exp = array('', '万');
    $res = '';
    for ($i = strlen($int) - 1, $k = 0; $i >= 0; $k++) {
        $str = '';
        for ($j = 0; $j < 4 && $i >= 0; $j++, $i--) {
            $u = $int{$i} > 0 ? $uni[$j] : ''; 
            $str = $chs[$int{$i}] . $u . $str;
        }
        $str = rtrim($str, '0'); 
        $str = preg_replace("/0+/", "零", $str); 
        if (!isset($exp[$k])) {
            $exp[$k] = $exp[$k - 2] . '亿'; 
        }
        $u2 = $str != '' ? $exp[$k] : '';
        $res = $str . $u2 . $res;
    }
    $dec = rtrim($dec, '0');
    if (!empty($dec)) {
        $res .= $int_unit;
        if ($is_extra_zero) {
            if (substr($int, -1) === '0') {
                $res.= '零';
            }
        }
        for ($i = 0, $cnt = strlen($dec); $i < $cnt; $i++) {
            $u = $dec{$i} > 0 ? $dec_uni[$i] : ''; 
            $res .= $chs[$dec{$i}] . $u;
        }
        $res = rtrim($res, '0'); 
        $res = preg_replace("/0+/", "零", $res); 
    } else {
        $res .= $int_unit . '整';
    }
    return $res;
}

/**
 * 生成人性化日期
 * @param unknown_type $timestamp
 */
function time_convert($timestamp, $timestamp2 = null, $beforeafter = true, $is_length = false) {
    /* $is_length=true时  计算市场，保留2位小数 */
    $format = array('秒钟', '分钟', '小时', '天', '个月', '年');
    if (!$timestamp || $timestamp == $timestamp2)
        return '';
    $timestamp2 = $timestamp2 ? $timestamp2 : time();
    if (is_numeric($timestamp)) {
        
        $i = abs($timestamp2 - $timestamp);
        if($i<=0) return '刚刚';
        switch ($i) {
            case!$i : $str = '-';
                break;
            case 0 > $i: $str = round($i / 86400) . $format[3];
                break;
            case 60 > $i: $str = $i . $format[0];
                break;
            case 3600 > $i:
                $str = round($i / 60) . $format[1];
                break;
            case 86400 > $i:
                if ($is_length) {
                    $str = floor($i / 3600) . $format[2];
                    $min = $i - (floor($i / 3600) * 3600);
                    if ($min && round($min / 60)) {
                        $str.=round($min / 60) . $format[1];
                    }
                } else
                    $str = round($i / 3600) . $format[2];
                break;
            case 2592000 > $i:
                $str = round($i / 86400) . $format[3];
                break;
            case 31104000 > $i: $str = round($i / 2592000) . $format[4];
                break;
            case 3110400000 > $i: $str = round($i / 31104000) . $format[5];
                break;
            case $i > 31104000: $str = date('Y-m-d', $timestamp2);
                break;
            default : $str = '';
                break;
        }
    }
    $beforeafterstring = $beforeafter ? ($timestamp2 > $timestamp ? '前' : '后' ) : '';
    return $str . $beforeafterstring;
}

/*将扁平数据转换成tree型数据,index为下标，部分情况需要id做为数组的下标*/
function json_foreach($rows, $parent = 0 , $index="") {
    $ret = array();
    foreach ($rows as $k=>$row) {
        if ($row['parent'] == $parent) {
            $_this['title'] = $row['title'];
            $_this['children'] = json_foreach($rows, $row['id'],$index);
            if(!$_this['children']){
                unset($_this['children']);
            }
            if(!$index){
                $ret[] = array_merge((array) $row, $_this);
            }else{
                $ret[$row[$index]] = array_merge((array) $row, $_this);
            }
            
        }
    }
    return $ret;
}

/*将扁平数据转换成tree型数据,index为下标，部分情况需要id做为数组的下标*/
function region_json_foreach($rows, $parent = 0 , $index="") {
    $ret = array();
    foreach ($rows as $k=>$row) {
        if ($row['parent'] == $parent) {
            $_this['title'] = $row['title'];
            $_this['children'] = $this->region_json_foreach($rows, $row['id'],$index);
            if(count($_this['children'])==0 && $row['lvl']==2){
                $_this['children'] = array($row);
            }
            if(!$_this['children']){
                unset($_this['children']);
            }
            if(!$index){
                $ret[] = array_merge((array) $row, $_this);
            }else{
                $ret[$row[$index]] = array_merge((array) $row, $_this);
            }
            
        }
    }
    return $ret;
}
