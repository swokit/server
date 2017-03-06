<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/3/5
 * Time: 17:50
 */

namespace inhere\server;

/*

// 单独使用

//设置服务器程序的主进程PID
$kit = new AutoReloader(26527);

//设置要监听的源码目录
$kit->watch(dirname(__DIR__).'/bootstrap');

//监听后缀为.php的文件
$kit->addFileType('php');

$kit->run();


*/

/**
 * Class AutoReloader
 * @package inhere\server
 */
class AutoReloader
{
    /**
     * @var resource
     */
    protected $inotify;

    /**
     * inotify events
     * @var int
     */
    protected $events;

    /**
     * swoole server master process id
     * @var int
     */
    protected $pid;

    protected $reloadFileTypes = [
        '.php' => true
    ];

    /**
     * 添加的监控目录列表
     * @var array
     */
    protected $watchedDirs = array();

    /**
     * 监控目录列表中所有被监控的文件列表
     * @var array
     */
    protected $watchedFiles = [];

    /**
     * 监控到文件变动后延迟5秒后执行reload
     * @var integer
     */
    protected $afterNSeconds = 5;

    /**
     * 正在reload
     */
    protected $reloading = false;

    public function putLog($log)
    {
        echo "[".date('Y-m-d H:i:s')."]\t".$log."\n";
    }

    /**
     * @param $serverPid
     * @throws NotFound
     */
    public function __construct($serverPid)
    {
        $this->pid = $serverPid;

        if ( posix_kill($serverPid, 0) === false ) {
            throw new NotFound("Process #$serverPid not found.");
        }

        $this->inotify = inotify_init();
        $this->events = IN_MODIFY | IN_DELETE | IN_CREATE | IN_MOVE;

        $this->addWatchEvent();
    }

    public function run()
    {
        swoole_event_wait();
    }

    protected function addWatchEvent()
    {
        swoole_event_add($this->inotify, function ($ifd)
        {
            if (!$events = inotify_read($this->inotify)) {
                return ;
            }

            //var_dump($events);

            foreach($events as $ev) {
                if ($ev['mask'] == IN_IGNORED) {
                    continue;
                }

                if ( in_array( $ev['mask'], [IN_CREATE, IN_DELETE, IN_MODIFY, IN_MOVED_TO, IN_MOVED_FROM]) ) {
                    $fileType = strrchr($ev['name'], '.');

                    //非重启类型
                    if (!isset($this->reloadFileTypes[$fileType])) {
                        continue;
                    }
                }

                //正在reload，不再接受任何事件，冻结10秒
                if (!$this->reloading) {
                    $this->putLog("After {$this->afterNSeconds} seconds reload the server");
                    //有事件发生了，进行重启
                    swoole_timer_after($this->afterNSeconds * 1000, array($this, 'reload'));
                    $this->reloading = true;
                }
            }
        });
    }

    public function reload()
    {
        $this->putLog("reloading");

        // 向主进程发送信号
        posix_kill($this->pid, SIGUSR1);

        // 清理所有监听
        $this->clearWatch();

        // 重新监听
        foreach($this->watchedDirs as $root) {
            $this->addWatch($root);
        }

        // 继续进行reload
        $this->reloading = false;
    }

    /**
     * 添加监控文件类型
     * @param $type
     */
    public function addFileType($type)
    {
        $type = '.' . trim($type, '.');

        if ( isset($this->reloadFileTypes[$type]) ) {
            $this->reloadFileTypes[$type] = true;
        }

        return $this;
    }

    /**
     * 添加 inotify 事件
     * @param $inotifyEvent
     */
    public function addInotifyEvent($inotifyEvent)
    {
        $this->events |= $inotifyEvent;
    }

    /**
     * 清理所有inotify监听
     */
    public function clearWatch()
    {
        foreach($this->watchedFiles as $wd) {
            inotify_rm_watch($this->inotify, $wd);
        }

        $this->watchedFiles = [];
    }

    /**
     * @param string $target file or dir path
     * @param bool $root
     * @return bool
     */
    public function addWatch($target, $root = true)
    {
        //目录/文件不存在
        if ( !file_exists($target) ) {
            throw new \RuntimeException("[$target] is not a directory or file.");
        }

        //避免重复监听
        if (isset($this->watchedFiles[$target])) {
            return false;
        }

        $isDir = is_dir($target);

        $wd = inotify_add_watch($this->inotify, $target, $this->events);
        $this->watchedFiles[$target] = $wd;

        if (!is_dir($target)) {
            return true;
        }

        // 根目录
        if ( $root ) {
            $this->watchedDirs[] = $target;
        }

        $files = scandir($target);
        foreach ($files as $f) {
            if ($f == '.' or $f == '..') {
                continue;
            }

            $path = $target . '/' . $f;

            //递归目录
            if (is_dir($path)) {
                $this->addWatch($path, false);
            }

            //检测文件类型
            $fileType = strrchr($f, '.');

            if ( isset($this->reloadFileTypes[$fileType]) ) {
                $wd = inotify_add_watch($this->inotify, $path, $this->events);
                $this->watchedFiles[$path] = $wd;
            }
        }

        return true;
    }

    /**
     * getWatchedDirs
     * @return array
     */
    public function getWatchedDirs()
    {
        return $this->watchedDirs;
    }

    /**
     * getWatchedFiles
     * @return array
     */
    public function getWatchedFiles()
    {
        return $this->watchedFiles;
    }
}
