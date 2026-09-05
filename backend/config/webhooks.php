<?php
return ['allow_http'=>env('WEBHOOK_ALLOW_HTTP',in_array(env('APP_ENV','production'),['local','testing'],true)),'connect_timeout'=>3,'timeout'=>10,'max_attempts'=>5,'response_excerpt_bytes'=>2048,'allowed_ports'=>[80,443]];
