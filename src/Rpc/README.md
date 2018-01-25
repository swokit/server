# tcp RPC (TODO)

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
    
    $data = "Rpc-Service: $service\r\n" .
            "Rpc-Params: $params\r\n" .
            "Rpc-Meta: $meta\r\n\r\n";
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
    
    $data = "Rpc-Service: $service\r\n" .
            "Rpc-Result: $result\r\n" .
            "Rpc-Meta: $meta\r\n\r\n";
TAG;
```
