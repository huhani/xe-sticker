<?php

namespace TranslatedException;

use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Translation\Loader\XliffFileLoader;

/**
 * @author Xianghan Wang <coldume@gmail.com>
 * @since  1.0.0
 */
class TranslatedException extends \Exception
{
    /**
     * @var TranslatorInterface
     */
    static protected $translator;

    /**
     * @var string[]
     */
    static protected $dirs = [];

    /**
     * @var string
     */
    protected $verboseMessage;

    /**
     * @param null|string     $domain
     * @param string          $id
     * @param string[]        $parameters
     * @param null|int        $number
     * @param int             $code
     * @param null|\Exception $previous
     */
    public function __construct(
        $domain,
        $id,
        array $parameters = [],
        $number = null,
        $code = 0,
        \Exception $previous = null
    ) {
        parent::__construct('', $code, $previous);
        $this->setMessage($domain, $id, $parameters, $number);
        $this->setVerboseMessage($domain, $id, $parameters, $number);
    }

    /**
     * @param mixed[] $options
     */
    public static function init(array $options = [])
    {
        if (static::$translator) {
            return;
        }
        $locale   = isset($options['locale']) ? $options['locale'] : 'en';
        $cacheDir = isset($options['cache_dir']) ? $options['cache_dir'] . '/translated_exception' : null;
        $debug    = isset($options['debug']) ? $options['debug'] : false;
        static::$translator = new Translator($locale, null, $cacheDir, $debug);
        static::$translator->addLoader('xlf', new XliffFileLoader());
    }

    /**
     * @param string $dir
     */
    public static function addResourceDir($dir)
    {
        if (in_array($dir, static::$dirs)) {
            return;
        }
        $iterator = new \FilesystemIterator($dir, \FilesystemIterator::SKIP_DOTS);
        foreach ($iterator as $value) {
            if (!$iterator->isFile()) {
                continue;
            }
            list($domain, $locale, $format) = explode('.', $iterator->getBasename(), 3);
            static::$translator->addResource($format, $iterator->getRealPath(), $locale, $domain);
        }
        static::$dirs[] = $dir;
    }

    /**
     * @param string   $domain
     * @param string   $id
     * @param string[] $parameters
     * @param null|int $number
     */
    protected function setMessage($domain, $id, array $parameters = [], $number = null)
    {
        foreach ($parameters as $key => &$value) {
            if (preg_match('/\\A%cp_/', $key)) {
                $value = $this->compactString($value);
            }
        }
        if (null === $number) {
            $this->message = static::$translator->trans($id, $parameters, $domain);
        } else {
            $this->message = static::$translator->transChoice($id, $number, $parameters, $domain);
        }
    }

    /**
     * @param string   $domain
     * @param string   $id
     * @param string[] $parameters
     * @param null|int $number
     */
    protected function setVerboseMessage($domain, $id, array $parameters = [], $number = null)
    {
        if (null === $number) {
            $message = static::$translator->trans($id, $parameters, $domain, 'en');
        } else {
            $message = static::$translator->transChoice($id, $number, $parameters, $domain, 'en');
        }
        $this->verboseMessage  = '[Exception] '.get_class($this).PHP_EOL;
        $this->verboseMessage .= '[Message] '.$message.PHP_EOL;
        $this->verboseMessage .= '[File] '.$this->file.PHP_EOL;
        $this->verboseMessage .= '[Line] '.$this->line.PHP_EOL;
        $this->verboseMessage .= '[Stack Trace]'.PHP_EOL.$this->getTraceAsString().PHP_EOL;
    }

    /**
     * @return string
     */
    public function getVerboseMessage()
    {
        return $this->verboseMessage;
    }

    /**
     * @param  string $string
     * @return string
     */
    protected function compactString($string)
    {
        if (60 < strlen($string)) {
            $string = substr($string, 0, 20).'...'.substr($string, -20);
        }

        return $string;
    }
}
