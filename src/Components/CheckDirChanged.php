<?php
/**
 * Created by PhpStorm.
 * User: Inhere
 * Date: 2017/12/21 0021
 * Time: 20:56
 */

namespace Inhere\Server\Components;

/**
 * Class CheckDirChanged
 * @package Inhere\Server\Components
 */
class CheckDirChanged
{
    /** @var array */
    public $suffixes = ['php'];

    /** @var string */
    private $idFile;

    /** @var string */
    private $watchDir;

    /** @var string */
    private $dirMd5;

    /** @var string */
    private $md5s;

    /** @var int */
    private $fileCounter = 0;

    /**
     * @param string|null $idFile
     * @return bool
     */
    public function isChanged(string $idFile = null)
    {
        if ($idFile) {
            $this->setIdFile($idFile);
        }

        if (!($old = $this->dirMd5) && (!$old = $this->getMd5ByIdFile())) {
            $this->calcDirMd5();

            return false;
        }

        $this->calcDirMd5();

        return $this->dirMd5 !== $old;
    }

    /**
     * @return bool|string
     */
    public function getMd5ByIdFile()
    {
        if (!$file = $this->idFile) {
            return false;
        }

        if (!is_file($file)) {
            return false;
        }

        return trim(file_get_contents($file));
    }

    /**
     * @param string $watchDir
     * @return string
     */
    public function calcDirMd5(string $watchDir = null)
    {
        $this->setWatchDir($watchDir);
        $this->collectDirMd5($this->watchDir);

        $this->dirMd5 = md5($this->md5s);
        $this->md5s = null;

        if ($this->idFile) {
            file_put_contents($this->idFile, $this->dirMd5);
        }

        return $this->dirMd5;
    }

    /**
     * @param string $watchDir
     */
    private function collectDirMd5(string $watchDir)
    {
        $files = scandir($watchDir, 0);

        foreach ($files as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }

            $path = $watchDir . '/' . $f;

            //递归目录
            if (is_dir($path)) {
                $this->collectDirMd5($path);
            }

            //检测文件类型
            $suffix = trim(strrchr($f, '.'), '.');

            if ($suffix && \in_array($suffix, $this->suffixes, true)) {
                $this->md5s .= md5_file($path);
                $this->fileCounter++;
            }
        }
    }

    /**
     * @return string
     */
    public function getIdFile()
    {
        return $this->idFile;
    }

    /**
     * @param string $idFile
     * @return $this
     */
    public function setIdFile(string $idFile)
    {
        $this->idFile = $idFile;

        return $this;
    }

    /**
     * @return string
     */
    public function getWatchDir()
    {
        return $this->watchDir;
    }

    /**
     * @param string $watchDir
     * @return $this
     */
    public function setWatchDir($watchDir)
    {
        if ($watchDir) {
            $this->watchDir = $watchDir;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getDirMd5()
    {
        return $this->dirMd5;
    }

    /**
     * @return int
     */
    public function getFileCounter(): int
    {
        return $this->fileCounter;
    }
}
