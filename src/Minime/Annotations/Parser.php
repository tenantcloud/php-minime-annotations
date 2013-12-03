<?php

namespace Minime\Annotations;

use Minime\Annotations\Interfaces\ParserInterface;
use Minime\Annotations\Interfaces\ParserRulesInterface;
use StrScan\StringScanner;

/**
 *
 * An Annotation Parser
 *
 * @package Annotations
 *
 */
class Parser implements ParserInterface
{

    /**
     * The Doc block to parse
     *
     * @var string
     */
    private $raw_doc_block;

    /**
     * The ParserRules object
     *
     * @var ParserRulesInterface
     */
    private $rules;

    /**
     * The parsable type in a given docblock
     *
     * @var array
     */
    protected $types = ['string', 'integer', 'float', 'json', 'eval'];

    /**
     * Parser constructor
     *
     * @param string $raw_doc_block the doc block to parse
     */
    public function __construct($raw_doc_block, ParserRulesInterface $rules)
    {
        $this->raw_doc_block = $raw_doc_block;
        $this->rules = $rules;
    }

    /**
     * Parse a given docblock
     * @return AnnotationsBag an AnnotationBag Object
     */
    public function parse()
    {
        $parameters = [];
        $identifier = $this->rules->getAnnotationIdentifier();
        $pattern = '/\\'.$identifier.$this->rules->getAnnotationNameRegex().'/';
        $types_pattern = '/('.implode('|', $this->types).')/';
        $lines = array_map('rtrim', explode("\n", $this->raw_doc_block));
        array_walk(
            $lines,
            function ($line) use (&$parameters, $identifier, $pattern, $types_pattern) {
                $line = new StringScanner($line);
                $line->skip('/\s+\*\s+/');
                while (! $line->hasTerminated()) {
                    $key = $line->scan($pattern);
                    if (! $key) { // next line when no annotation is found
                        $line->terminate();
                        continue;
                    }

                    $key = substr($key, strlen($identifier));
                    if (! array_key_exists($key, $parameters)) {
                        $parameters[$key] = [];
                    }

                    $line->skip('/\s+/');
                    if ('' == $line->peek() || $line->check('/\\'.$identifier.'/')) { // if implicit boolean
                        $parameters[$key][] = true;
                        continue;
                    }

                    $type = 'dynamic';
                    if ($line->check($types_pattern)) { //if strong typed
                        $type = $line->scan('/\w+/');
                        $line->skip('/\s+/');
                    }
                    $parameters[$key][] = self::parseValue($line->getRemainder(), $type);
                }
            }
        );

        return self::condense($parameters);
    }

    /**
     * Filter an array to converted to string a single value array
     * @param array $parameters
     *
     * @return array
     */
    protected static function condense(array $parameters)
    {
        return array_map(function ($value) {
            if (is_array($value) && 1 == count($value)) {
                $value = $value[0];
            }

            return $value;
        }, $parameters);
    }

    /**
     * Parse a given value against a specific type
     * @param string $value
     * @param string $type  the type to parse the value against
     *
     * @throws ParserException If the type is not recognized
     *
     * @return scalar|object
     */
    protected static function parseValue($value, $type = 'string')
    {
        $method = 'parse'.ucfirst(strtolower($type));

        return self::{$method}($value);
    }

    /**
     * Parse a given undefined type value
     * @param string $value
     *
     * @return scalar|object
     */
    protected static function parseDynamic($value)
    {
        try {
            return static::parseJson($value);
        } catch (ParserException $e) {
            return $value;
        }
    }

    /**
     * Parse a given value
     * @param string $value
     *
     * @return scalar|object
     */
    protected static function parseString($value)
    {
        return $value;
    }

    /**
     * Filter a value to be an Integer
     * @param string $value
     *
     * @throws ParserException If $value is not an integer
     *
     * @return integer
     */
    protected static function parseInteger($value)
    {
        $value = filter_var($value, FILTER_VALIDATE_INT);
        if (false === $value) {
            throw new ParserException("Raw value must be integer. Invalid value '{$value}' given.");
        }

        return $value;
    }

    /**
     * Filter a value to be a Float
     * @param string $value
     *
     * @throws ParserException If $value is not a float
     *
     * @return float
     */
    protected static function parseFloat($value)
    {
        $value = filter_var($value, FILTER_VALIDATE_FLOAT);
        if (false === $value) {
            throw new ParserException("Raw value must be float. Invalid value '{$value}' given.");
        }

        return $value;
    }

    /**
     * Filter a value to be a Json
     * @param string $value
     *
     * @throws ParserException If $value is not a Json
     *
     * @return scalar|object
     */
    protected static function parseJson($value)
    {
        $json = json_decode($value);
        $error = json_last_error();
        if (JSON_ERROR_NONE != $error) {
            throw new ParserException("Raw value must be a valid JSON string. Invalid value '{$value}' given.");
        }

        return $json;
    }

    /**
     * Filter a value to be a PHP eval
     * @param string $value
     *
     * @throws ParserException If $value is not a valid PHP code
     *
     * @return mixed
     */
    protected static function parseEval($value)
    {
        $output = @eval("return {$value};");
        if (false === $output) {
            throw new ParserException("Raw value should be valid PHP. Invalid code '{$value}' given.");
        }

        return $output;
    }
}
