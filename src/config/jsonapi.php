<?php

return [
    'strict'  => false,
    'version' => 'v1',
    'json_version' => '1.0',
    'url' => 'api/*',
    'accept' =>  'application/vnd.api+json',
    'content_type' =>  'application/vnd.api+json',
    'allowed_get' => [
      'include',
      'fields',
      'page',
      'limit',
      'sort',
      'search',
    ],
];