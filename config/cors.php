<?php

/*
 * No cross-origin reading of anything on this site.
 *
 * Laravel's own default answers every path under api/* with
 * `Access-Control-Allow-Origin: *`. The only thing under /api is Samira's
 * feed — the owner's day, read by a program on his own computer — and no web
 * page anywhere has any business reading it, key or no key. So the list of
 * paths is empty, and the browser's same-origin rule stays the last word.
 */
return [

    'paths' => [],

    'allowed_methods' => [],

    'allowed_origins' => [],

    'allowed_origins_patterns' => [],

    'allowed_headers' => [],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
