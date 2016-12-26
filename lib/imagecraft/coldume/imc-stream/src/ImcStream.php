<?php

namespace ImcStream;

use ImcStream\Exception\TranslatedException;
use ImcStream\Exception\IOException;
use ImcStream\Exception\RuntimeException;

/**
 * @author Xianghan Wang <coldume@gmail.com>
 * @since  1.0.0
 */
class ImcStream
{
    /**
     * @var ImcStream[]
     */
    protected static $globals = [];

    /**
     * @var string
     */
    protected $path;

    /**
     * @var string
     */
    protected $mode;

    /**
     * @var string
     */
    protected $uri;

    /**
     * @var bool
     */
    protected $local;

    /**
     * @var bool
     */
    protected $global = false;

    /**
     * @var resource|null
     */
    protected $fp;

    /**
     * @var bool
     */
    protected $seek;

    /**
     * @var bool
     */
    protected $seekable;

    /**
     * @var null|resource
     */
    protected $sfp;

    /**
     * @var int
     */
    protected $position = 0;

    /**
     * @var int
     */
    protected $length = 0;

    /**
     * @var int
     */
    protected $limit = -1;

    /**
     * @var int|float
     */
    protected $timeout = -1;

    /**
     * @var int|float
     */
    protected $timeleft;

    /**
     * @var bool
     */
    protected $eof = false;

    /**
     * @param mixed[] $options
     */
    public static function register(array $options = [])
    {
        TranslatedException::init($options);
        TranslatedException::addResourceDir(__DIR__.'/Resources/translations');
        $existed = in_array('imc', stream_get_wrappers());
        if (!$existed) {
            stream_register_wrapper('imc', 'ImcStream\\ImcStream');
        }
    }

    /**
     * @param  string $path
     * @param  string $mode
     * @return true
     */
    public function stream_open($path, $mode)
    {
        if (array_key_exists($key = substr(md5($path), 0, 5), static::$globals)) {
            foreach (get_object_vars(static::$globals[$key]) as $key => $value) {
                $this->$key = $value;
            }

            return true;
        }

        $arr = unserialize(preg_replace('/\\A[[:alnum:]]++:\/\//', '', $path));
        $this->path  = $path;
        $this->mode  = (false === strstr($mode, 'b')) ? 'r' : 'rb';
        $this->uri   = $arr['uri'];
        $this->seek  = isset($arr['seek']) && $arr['seek'];
        $this->local = @stream_is_local($this->uri);
        if ($this->local) {
            $this->fp = $this->local_open($this->uri, $this->mode);
        } else {
            if (isset($arr['timeout'])) {
                $this->timeout  = $arr['timeout'];
                $this->timeleft = $arr['timeout'];
            }
            if (isset($arr['global']) && $arr['global'] && $this->seek) {
                $this->global = true;
            }
            $this->fp = $this->network_open($this->uri, $this->mode);
            if (isset($arr['data_limit']) && (-1 !== $arr['data_limit'])) {
                $this->limit = $arr['data_limit'] * 1024;
            }
        }
        if ($this->seek) {
            if(-1 === @fseek($this->fp, 0)) {
                $this->seekable = false;
                $this->sfp      = fopen('php://temp', 'rb+');
            } else {
                $this->seekable = true;
            }
        }

        return true;
    }

    /**
     * @param  string $uri
     * @param  string $mode
     * @return resource
     * @throws IOException
     */
    protected function local_open($uri, $mode)
    {
        $fp = @fopen($uri, $mode);
        if (!$fp) {
            error_reporting(error_reporting() & ~E_WARNING);
            throw new IOException(
                'file.not.found.or.access.denied.%cp_filename%',
                ['%cp_filename%' => '"'.$uri.'"']
            );
        }

        return $fp;
    }

    /**
     * @param  string $uri
     * @param  string $mode
     * @return resource
     * @throws RuntimeException
     * @throws IOException
     */
    protected function network_open($uri, $mode)
    {
        if (!ini_get('allow_url_fopen')) {
            error_reporting(error_reporting() & ~E_WARNING);
            throw new RuntimeException(
                'php.setting.disabled.%setting%',
                ['%setting%' => '"allow_url_fopen"']
            );
        }
        $fp = @fopen($uri, $mode);
        if (!$fp) {
            error_reporting(error_reporting() & ~E_WARNING);
            throw new IOException(
                'network.resource.not.accessible.%cp_url%',
                ['%cp_url%' => '"'.$uri.'"']
            );
        }

        return $fp;
    }

    /**
     * @param  int $offset
     * @param  int $whence
     * @return bool
     */
    public function stream_seek($offset, $whence = SEEK_SET)
    {
        if (!$this->seek || SEEK_SET !== $whence) {
            return false;
        }
        $this->eof = false;
        if ($this->seekable) {
            fseek($this->fp, $offset, $whence);
        } else {
            $this->position = $offset;
        }

        return true;
    }

    /**
     * @return int
     */
    public function stream_tell()
    {
        if ($this->seekable) {
            return ftell($this->fp);
        } else {
            return ftell($this->sfp);
        }
    }

    /**
     * @param  int $count
     * @return string
     */
    public function stream_read($count)
    {
        if (!$this->seek || $this->seekable) {
            $data = $this->read($count);
            if (feof($this->fp)) {
                $this->eof = true;
            }

            return $data;
        } else {
            $offset = $this->position + $count - $this->length;
            while ($offset > 0 && $this->fp) {
                fwrite($this->sfp, $data = $this->read($offset));
                $len = strlen($data);
                $offset -= $len;
                $this->length += $len;
                if (feof($this->fp)) {
                    @fclose($this->fp);
                    $this->fp = null;
                    break;
                }
            }
            if ($this->length <= $this->position) {
                $this->eof = true;

                return '';
            } else {
                fseek($this->sfp, $this->position);
                $data = @fread($this->sfp, $count);
                if ($this->position < ftell($this->sfp)) {
                    $this->position = ftell($this->sfp);
                }
                if (!$this->fp && feof($this->sfp)) {
                    $this->eof = true;
                }

                return $data;
            }
        }
    }

    /**
     * @param  int $count
     * @return string
     * @throws IOException
     */
    protected function read($count)
    {
        if (-1 !== $this->limit && ($this->length + $count) > $this->limit) {
            $this->stream_close();
            if ($this->limit < 1024 * 1024) {
                $limit = number_format($this->limit / 1024, 2) . ' KB';
            } else {
                $limit = number_format($this->limit / (1024 * 1024), 2) . ' MB';
            }
            error_reporting(error_reporting() & ~E_WARNING);
            throw new IOException(
                'network.resource.exceeds.size.limit.%limit%',
                ['%limit%' => '"'.$limit.'"']
            );
        }

        if (-1 !== $this->timeout) {
            $seconds = (int) $this->timeleft;
            $microseconds = fmod($this->timeleft, 1) * 1000000;
            stream_set_timeout($this->fp, $seconds, $microseconds);
            list($usec, $sec) = explode(' ', microtime());
            $time = -$usec - $sec;
        }

        $data = @fread($this->fp, $count);

        if (false === $data) {
            error_reporting(error_reporting() & ~E_WARNING);
            throw new IOException('file.read.error');
        }

        if (-1 !== $this->timeout) {
            list($usec, $sec) = explode(' ', microtime());
            $time += $usec + $sec;
            $this->timeleft = $this->timeleft - $time;
            if (stream_get_meta_data($this->fp)['timed_out'] || 0 >= $this->timeleft) {
                $this->stream_close();
                error_reporting(error_reporting() & ~E_WARNING);
                throw new IOException(
                    'network.stream.read.timeout.%timeout%',
                    ['%timeout%' => '"'.number_format($this->timeout, 2).' s"']
                );
            }
        }

        return $data;
    }

    /**
     * @return true
     */
    public function stream_close()
    {
        if ($this->global) {
            $this->stream_seek(0);
            static::$globals[substr(md5($this->path), 0, 5)] = $this;

            return true;
        }

        @fclose($this->sfp);
        @fclose($this->fp);

        return true;
    }

    /**
     * @return bool
     */
    public function stream_eof()
    {
        return $this->eof;
    }

    /**
     * @return false
     */
    public function stream_stat()
    {
        return false;
    }

    /**
     * @param null|string $path
     */
    public static function fclose($path = null)
    {
        if (null === $path) {
            foreach (static::$globals as $key => $imc) {
                @fclose($imc->sfp);
                @fclose($imc->fp);
                unset(static::$globals[$key]);
            }
        } else {
            $key = substr(md5($path), 0, 5);
            if (!isset(static::$globals[$key])) {
                return;
            }
            $imc = static::$globals[$key];
            @fclose($imc->sfp);
            @fclose($imc->fp);
            unset(static::$globals[$key]);
        }
    }
}
