# swoole_process

swoole-1.7.2增加了一个进程管理模块，用来替代PHP的pcntl扩展。
more see [https://wiki.swoole.com/wiki/page/p-process.html](https://wiki.swoole.com/wiki/page/p-process.html)

### PHP自带的pcntl，存在很多不足，如

- pcntl 没有提供进程间通信的功能
- pcntl 不支持重定向标准输入和输出
- pcntl 只提供了fork这样原始的接口，容易使用错误
- swoole_process 提供了比 pcntl 更强大的功能，更易用的API，使PHP在多进程编程方面更加轻松

### swoole_process提供了如下特性：

- swoole_process 提供了基于unixsock的进程间通信，使用很简单只需调用 `write/read` 或者 `push/pop` 即可
- swoole_process 支持重定向标准输入和输出，在子进程内echo不会打印屏幕，而是写入管道，读键盘输入可以重定向为管道读取数据
- 配合 `swoole_event` 模块，创建的PHP子进程可以异步的事件驱动模式
- swoole_process 提供了 `exec` 接口，创建的进程可以执行其他程序，与原PHP父进程之间可以方便的通信


### 用法

```
<?php
$process = new swoole_process(function (swoole_process $worker)
{
    echo "Worker: start. PID=" . $worker->pid . "\n";
    sleep(2);
    $worker->write("hello master\n");
    $worker->exit(0);
}, false);

$pid = $process->start();
$r = array($process);
$write = $error = array();
$ret = swoole_select($r, $write, $error, 1.0);//swoole_select是swoole_client_select的别名
var_dump($ret);
var_dump($process->read());

```

#### 一个同步实例:

- 子进程异常退出时,自动重启
- 主进程异常退出时,子进程在干完手头活后退出

```
(new class{
    public $mpid=0;
    public $works=[];
    public $max_precess=1;
    public $new_index=0;

    public function __construct(){
        try {
            swoole_set_process_name(sprintf('php-ps:%s', 'master'));
            $this->mpid = posix_getpid();
            $this->run();
            $this->processWait();
        }catch (\Exception $e){
            die('ALL ERROR: '.$e->getMessage());
        }
    }

    public function run(){
        for ($i=0; $i < $this->max_precess; $i++) {
            $this->CreateProcess();
        }
    }

    public function CreateProcess($index=null){
        $process = new swoole_process(function(swoole_process $worker)use($index){
            if(is_null($index)){
                $index=$this->new_index;
                $this->new_index++;
            }
            swoole_set_process_name(sprintf('php-ps:%s',$index));
            for ($j = 0; $j < 16000; $j++) {
                $this->checkMpid($worker);
                echo "msg: {$j}\n";
                sleep(1);
            }
        }, false, false);
        $pid=$process->start();
        $this->works[$index]=$pid;
        return $pid;
    }
    public function checkMpid(&$worker){
        if(!swoole_process::kill($this->mpid,0)){
            $worker->exit();
            // 这句提示,实际是看不到的.需要写到日志中
            echo "Master process exited, I [{$worker['pid']}] also quit\n";
        }
    }

    public function rebootProcess($ret){
        $pid=$ret['pid'];
        $index=array_search($pid, $this->works);
        if($index!==false){
            $index=intval($index);
            $new_pid=$this->CreateProcess($index);
            echo "rebootProcess: {$index}={$new_pid} Done\n";
            return;
        }
        throw new \Exception('rebootProcess Error: no pid');
    }

    public function processWait(){
        while(1) {
            if(count($this->works)){
                $ret = swoole_process::wait();
                if ($ret) {
                    $this->rebootProcess($ret);
                }
            }else{
                break;
            }
        }
    }
});
```

