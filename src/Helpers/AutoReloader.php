<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/3/5
 * Time: 17:50
 */

namespace Inhere\Server\Helpers;

/*

// 单独使用

//设置服务器程序的主进程PID
$kit = new AutoReloader(26527);
//设置要监听的源码目录
$kit->watch(dirname(__DIR__).'/bootstrap');
//监听后缀为.php的文件
$kit->addFileType('php');
$kit->run();

//
$kit = new AutoReloader(26527);
$kit
    ->addWatches($dirs)
    ->setReloadHandler(function($pid) use (\swoole_server $server, $onlyReloadTask) {
        $server->reload($onlyReloadTask);
    })
    ->run();
*/
use inhere\exceptions\NotFoundException;

/**
 * Class AutoReloader
 * @package Inhere\Server
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
    protected $pid = 0;

    /**
     * 监控到文件变动后延迟5秒后执行reload
     * @var integer
     */
    protected $afterNSeconds = 5;

    /**
     * 正在reload
     */
    protected $reloading = false;

    /**
     * 设置自定义的回调处理reload
     * @var callable
     */
    protected $reloadHandler;

    /**
     * 监控的文件类型
     * @var array
     */
    protected $watchedTypes = [
        '.php' => true
    ];

    /**
     * 添加的监控目录列表
     * @var array
     */
    protected $watchedDirs = [];

    /**
     * 监控目录列表中所有被监控的文件列表
     * @var array
     */
    protected $watchedFiles = [];

    public static function make($mPid)
    {
        return new self($mPid);
    }

    /**
     * @param $serverPid
     */
    public function     __construct($serverPid)
    {
        $this->pid = (int)$serverPid;
        $this->inotify = inotify_init();
        $this->eventMask = IN_MODIFY | IN_DELETE | IN_CREATE | IN_MOVE;

        $this->addWatchEvent();
    }

    public function run()
    {
        swoole_event_wait();
    }

    protected function addWatchEvent()
    {
        swoole_event_add($this->inotify, function ($ifd) {
            if (!$events = inotify_read($this->inotify)) {
                return;
            }

            //var_dump($events);

            foreach ($events as $ev) {
                if ($ev['mask'] === IN_IGNORED) {
                    continue;
                }

                if (in_array($ev['mask'], [IN_CREATE, IN_DELETE, IN_MODIFY, IN_MOVED_TO, IN_MOVED_FROM], true)) {
                    $fileType = strrchr($ev['name'], '.');

                    //非重启类型
                    if (!isset($this->watchedTypes[$fileType])) {
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

    /**
     * reload
     */
    public function reload()
    {
        // 调用自定义的回调处理reload
        if ($cb = $this->reloadHandler) {
            $cb($this->pid);

            // 直接向主进程发送 SIGUSR1 信号
        } else {
            $this->putLog('begin reloading ... ...');

            // 检查进程
            if (posix_kill($this->pid, 0) === false) {
                throw new NotFoundException("The process #$this->pid not found.");
            }

            posix_kill($this->pid, SIGUSR1);
        }

        // 清理所有监听
        $this->clearWatched();

        // 重新监听
        $this->addWatches($this->watchedDirs);

        // 继续进行reload
        $this->reloading = false;
    }

    /**
     * 添加监控文件类型
     * @param $type
     * @return $this
     */
    public function addFileType($type)
    {
        $type = '.' . trim($type, '. ');

        if (!isset($this->watchedTypes[$type])) {
            $this->watchedTypes[$type] = true;
        }

        return $this;
    }

    /**
     * 添加 inotify 事件
     * @param $inotifyEvent
     */
    public function addInotifyEvent($inotifyEvent)
    {
        $this->eventMask |= $inotifyEvent;
    }

    /**
     * add Watches
     * @param array $dirs
     * @param string $basePath
     * @return $this
     * @throws \RuntimeException
     */
    public function addWatches(array $dirs, $basePath = '')
    {
        $basePath = $basePath ? rtrim($basePath, '/') . '/' : '';

        foreach ($dirs as $dir) {
            $this->addWatch($basePath . $dir);
        }

        return $this;
    }

    /**
     * @param string $target The file or dir path
     * @param bool $root
     * @return bool
     * @throws \RuntimeException
     */
    public function addWatch($target, $root = true)
    {
        //
        if (!$target) {
            return false;
        }

        //目录/文件不存在
        if (!file_exists($target)) {
            throw new \RuntimeException("[$target] is not a directory or file.");
        }

        //避免重复监听
        if (isset($this->watchedFiles[$target])) {
            return false;
        }

        $wd = inotify_add_watch($this->inotify, $target, $this->eventMask);
        $this->watchedFiles[$target] = $wd;

        if (!is_dir($target)) {
            return true;
        }

        // 根目录
        if ($root) {
            $this->watchedDirs[] = $target;
        }

        $files = scandir($target, 0);
        foreach ($files as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }

            $path = $target . '/' . $f;

            //递归目录
            if (is_dir($path)) {
                $this->addWatch($path, false);
            }

            //检测文件类型
            $fileType = strrchr($f, '.');

            if (isset($this->watchedTypes[$fileType])) {
                $wd = inotify_add_watch($this->inotify, $path, $this->eventMask);
                $this->watchedFiles[$path] = $wd;
            }
        }

        return true;
    }

    /**
     * 清理所有inotify监听
     * @return $this
     */
    public function clearWatched()
    {
        foreach ($this->watchedFiles as $wd) {
            inotify_rm_watch($this->inotify, $wd);
        }

        $this->watchedFiles = [];

        return $this;
    }

    /**
     * set Reload Handler
     * @param callable $cb
     */
    public function setReloadHandler(callable $cb)
    {
        $this->reloadHandler = $cb;
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

    public function putLog($log)
    {
        echo "[" . date('Y-m-d H:i:s') . "]\t" . $log . "\n";
    }
}
