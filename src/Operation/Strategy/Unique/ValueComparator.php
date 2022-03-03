<?php declare(strict_types=1);

namespace FiiSoft\Jackdaw\Operation\Strategy\Unique;

use FiiSoft\Jackdaw\Comparator\Comparator;
use FiiSoft\Jackdaw\Internal\Item;

final class ValueComparator implements Strategy
{
    private Comparator $comparator;
    private array $values = [];
    
    public function __construct(Comparator $comparator)
    {
        $this->comparator = $comparator;
    }
    
    public function check(Item $item): bool
    {
        $value = $item->value;
        
        foreach ($this->values as $val) {
            if ($this->comparator->compare($val, $value) === 0) {
                return false;
            }
        }
    
        $this->values[] = $value;
        
        return true;
    }
}