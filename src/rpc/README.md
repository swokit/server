# tcp RPC 

## request data structure

```php
    $mTime = microtime(1);
    $params = json_encode($args);
    $meta = json_encode(array_merge([
        'id' => md5($mTime . $service),
        'time' => $mTime,
        'key' => 'sec key',
        'token' => 'request token',
    ], $options));
    
    $data = "RPC-S: $service\r\n" .
            "RPC-P: $params\r\n" .
            "RPC-M: $meta\r\n\r\n";
```

## response data structure

```php
    $mTime = microtime(1);
    $result = json_encode($result);
    $meta = json_encode(array_merge([
        'id' => md5($mTime . $service),
        'time' => $mTime,
        'key' => 'sec key',
        'token' => 'request token',
    ], $options));
    $data = "RPC-S: $service\r\n" .
            "RPC-R: $result\r\n" .
            "RPC-M: $meta\r\n\r\n";
TAG;
```
