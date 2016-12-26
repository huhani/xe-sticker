<?php

namespace ImcStream\Exception;

use TranslatedException\TranslatedException as BaseTranslatedException;

/**
 * @author Xianghan Wang <coldume@gmail.com>
 * @since  1.0.0
 */
class TranslatedException extends BaseTranslatedException
{
    /**
     * @param string          $id
     * @param string[]        $parameters
     * @param null|int        $number
     * @param int             $code
     * @param null|\Exception $previous
     */
    public function __construct(
        $id,
        array $parameters = [],
        $number = null,
        $code = 0,
        \Exception $previous = null
    ) {
        parent::__construct('imc_stream', $id, $parameters, $number, $code, $previous);
    }

}
