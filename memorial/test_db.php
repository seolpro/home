<?php
require_once __DIR__ . '/db.php';
echo "DB 연결 OK. charset=" . $conn->character_set_name();