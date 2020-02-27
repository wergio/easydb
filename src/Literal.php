<?php
namespace ParagonIE\EasyDB;

/**
 * Class Literal
 *
 * Example usage:
 *
 * $fields = [
 *     'name' => $name,
 *     'ip' => new Literal('INET_ATON(?)', $ip)
 * ];
 * $db->insert('table', $fields);
 *
 * @package ParagonIE\EasyDB
 */
class Literal
{
    protected $values = [];
    protected $literal = '';

    public function __construct($literal, ...$args) {
        $paramCount = substr_count($literal, "?");
        if($paramCount !== count($args)) {
            throw new \InvalidArgumentException("Wrong number of parameters in literal");
        }
        $this->literal = $literal;
        $this->values = $args;
    }

    public function getLiteral() {
        return $this->literal;
    }

    public function getValues() {
        return $this->values;
    }
}