<?php declare(strict_types=1);

namespace FiiSoft\Jackdaw\Mapper;

final class ToArray extends Internal\BaseMapper
{
    private bool $appendKey;
    
    public function __construct(bool $appendKey = false)
    {
        $this->appendKey = $appendKey;
    }
    
    /**
     * @inheritDoc
     */
    public function map($value, $key)
    {
        if ($value instanceof \Traversable) {
            return \iterator_to_array($value);
        }
        
        if (\is_array($value)) {
            return $value;
        }
    
        return $this->appendKey ? [$key => $value] : [$value];
    }
}