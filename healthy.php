<?php
header("Content-Type: application/json");

// 健康检查逻辑
$response = [
    "status" => "healthy",
    "timestamp" => time()
];

// 返回 JSON 格式的响应
echo json_encode($response);
?>