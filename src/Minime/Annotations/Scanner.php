<?php

namespace Minime\Annotations;

use StrScan\StringScanner;

/**
 * Represents a specialized StrScan\StringScanner. This class contains top level
 * scan methods to facilitate annotations parsing.
 *
 * @package Annotations
 * @author  Márcio Almada and the Minime Community
 * @license MIT
 *
 */
class Scanner extends StringScanner
{

    public function skipBlankSpace()
    {
        $this->skip('/\s+/');
    }

    public function scanKey($pattern)
    {
        $key = $this->scan('/'.$pattern.'/');
        if ($key) {
            $this->skipBlankSpace();

            return $key;
        }
    }

    public function scanImplicitBoolean($identifier)
    {
        return ('' == $this->peek() || $this->check('/\\'.$identifier.'/'));
    }

    public function scanType($types_pattern, $fallback)
    {
        $type = $fallback;
        if ($this->check($types_pattern)) { // if strong typed
            $type = $this->scan('/\w+/');
            $this->skipBlankSpace();
        }

        return $type;
    }

}