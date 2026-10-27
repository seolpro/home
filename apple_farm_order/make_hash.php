<?php
// CLI 전용: php make_hash.php '강력한비밀번호'
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
if(empty($argv[1]))exit("Usage: php make_hash.php 'password'\n");echo password_hash($argv[1],PASSWORD_DEFAULT).PHP_EOL;
