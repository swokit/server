<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-09-04
 * Time: 9:29
 */

namespace Inhere\Server\Components;

use Swoole\Http\Request;
use Swoole\Http\Response;

/**
 * Class StaticResourceProcessor - Static resource processing
 * @package Inhere\Server\Components
 */
class StaticResourceProcessor
{
    /**
     * 静态文件类型
     * @var array
     */
    public static $mimeTypes = [
        'js' => 'application/x-javascript',
        'css' => 'text/css',
        'bmp' => 'image/bmp',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'ico' => 'image/x-icon',
        'json' => 'application/json',
        'svg' => 'image/svg+xml',
        'woff' => 'application/font-woff',
        'woff2' => 'application/font-woff2',
        'ttf' => 'application/x-font-ttf',
        'eot' => 'application/vnd.ms-fontobject',
        'htm' => 'text/html',
        'html' => 'text/html',
    ];

    /**
     * @var array
     */
    private static $allowedExt;

    /**
     * @var bool
     */
    private $enable = true;

    /**
     * the absolution base path
     * @var string
     */
    private $basePath;

    /**
     * @var array
     */
    private $ext;

    /**
     * @var array
     */
    private $dirMap = [
        // 'url prefix' => 'assets dir(is relative the basePath)',
        '/assets' => 'web/assets',
        '/uploads' => 'web/uploads'
    ];

    /**
     * @var string
     */
    private $error;

    /**
     * @var string
     */
    private $file;

    public function __construct($basePath, array $ext = [], array $dirMap = [])
    {
        $this->basePath = $basePath;
        $this->ext = $ext;

        if ($dirMap) {
            $this->dirMap = $dirMap;
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param null $uri
     * @return bool
     */
    public function __invoke(Request $request, Response $response, $uri = null)
    {
        return $this->handle($request, $response, $uri);
    }

    /**
     * handle Static Access 处理静态资源请求
     * @param Request $request
     * @param Response $response
     * @param string $uri
     * @return bool
     */
    public function handle(Request $request, Response $response, $uri = null)
    {
        $uri = $uri ?: $request->server['request_uri'];
        $path = parse_url($uri, PHP_URL_PATH);

        // 没有资源处理配置 || 没有任何后缀 返回交给php继续处理
        if (!$this->enable || !$this->dirMap || false === strrpos($path, '.')) {
            return false;
        }

        if (!$basePath = $this->basePath) {
            throw new \LogicException('Must define the property [basePath] for handle static assets.');
        }

        $extReg = implode('|', self::getAllowedExt());

//         $this->log("begin match ext for the asset $uri, result: " . preg_match("/\.($extReg)/i", $uri, $matches), $exts);

        // 资源后缀匹配失败 返回交给php继续处理
        if (1 !== preg_match("/.($extReg)/i", $path, $matches)) {
            return false;
        }

        // asset ext name. e.g $matches = [ '.css', 'css' ];
        $ext = $matches[1];

        // e.g 'assets/css/site.css'
        $arr = explode('/', ltrim($path, '/'), 2);

        $assetDir = '';
        $urlBegin = '/' . $arr[0]; // e.g /assets
        $matched = false;

        foreach ($this->dirMap as $urlMatch => $assetDir) {
            // match success
            if ($urlBegin === $urlMatch) {
                $matched = true;
                break;
            }
        }

        // url匹配失败 返回交给php继续处理
        if (!$matched) {
            return false;
        }

        $urlOther = $arr[1];

        // $assetDir is absolute path ?
        $this->file = $assetDir{0} === '/' ? "$assetDir/$urlOther" : "$basePath/$assetDir/$urlOther";

        if (is_file($this->file)) {
            // 必须要有内容类型
            $response->header('Content-Type', static::$mimeTypes[$ext]);

            // 设置缓存头信息
            $time = 86400;
            $response->header('Cache-Control', 'max-age=' . $time);
            $response->header('Pragma', 'cache');
            $response->header('Last-Modified', date('D, d M Y H:i:s \G\M\T', filemtime($this->file)));
            $response->header('Expires', date('D, d M Y H:i:s \G\M\T', time() + $time));

            // 直接发送文件 不支持gzip
            $response->sendfile($this->file);
        } else {
            $this->error = "Assets $uri file not exists: $this->file";

            $response->status(404);
            $response->end("Assets not found: $uri\n");
        }

        return true;
    }

    /**
     * @return bool
     */
    public function isEnable(): bool
    {
        return $this->enable;
    }

    /**
     * @param bool $enable
     */
    public function setEnable($enable)
    {
        $this->enable = (bool)$enable;
    }

    /**
     * @return array
     */
    public static function getAllowedExt()
    {
        if (null === self::$allowedExt) {
            self::$allowedExt = array_keys(self::$mimeTypes);
        }

        return self::$allowedExt;
    }

    /**
     * @return array
     */
    public static function getMimeTypes(): array
    {
        return self::$mimeTypes;
    }

    /**
     * @param array $mimeTypes
     */
    public static function setMimeTypes(array $mimeTypes)
    {
        self::$mimeTypes = $mimeTypes;
    }

    /**
     * @return mixed
     */
    public function getBasePath()
    {
        return $this->basePath;
    }

    /**
     * @param mixed $basePath
     */
    public function setBasePath($basePath)
    {
        $this->basePath = $basePath;
    }

    /**
     * @return array
     */
    public function getExt(): array
    {
        return $this->ext;
    }

    /**
     * @param array $ext
     */
    public function setExt(array $ext)
    {
        $this->ext = $ext;
    }

    /**
     * @return array
     */
    public function getDirMap(): array
    {
        return $this->dirMap;
    }

    /**
     * @param array $dirMap
     */
    public function setDirMap(array $dirMap)
    {
        $this->dirMap = $dirMap;
    }

    /**
     * @return string
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * @return string
     */
    public function getFile(): ?string
    {
        return $this->file;
    }

}