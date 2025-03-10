<?php declare(strict_types=1);

namespace FiiSoft\Jackdaw\Filter;

use FiiSoft\Jackdaw\Internal\Check;
use FiiSoft\Jackdaw\Internal\Helper;

final class FilterBy implements Filter
{
    private Filter $filter;
    
    /** @var string|int */
    private $field;
    
    /**
     * @param string|int $field
     * @param Filter $filter
     */
    public function __construct($field, Filter $filter)
    {
        if (Helper::isFieldValid($field)) {
            $this->field = $field;
        } else {
            throw new \InvalidArgumentException('Invalid param field');
        }
        
        $this->filter = $filter;
    }
    
    public function isAllowed($value, $key, int $mode = Check::VALUE): bool
    {
        if (\is_array($value)) {
            if (!\array_key_exists($this->field, $value)) {
                throw new \RuntimeException('Field '.$this->field.' does not exist in value');
            }
        } elseif ($value instanceof \ArrayAccess) {
            if (!isset($value[$this->field])) {
                throw new \RuntimeException('Field '.$this->field.' does not exist in value');
            }
        } else {
            throw new \LogicException(
                'Unable to filter by '.$this->field.' because value is '.Helper::typeOfParam($value)
            );
        }
    
        return $this->filter->isAllowed($value[$this->field], $key, $mode);
    }
}