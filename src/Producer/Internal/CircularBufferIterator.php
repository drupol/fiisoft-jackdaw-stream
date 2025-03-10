<?php declare(strict_types=1);

namespace FiiSoft\Jackdaw\Producer\Internal;

use FiiSoft\Jackdaw\Internal\Item;
use FiiSoft\Jackdaw\Producer\Tech\CountableProducer;

final class CircularBufferIterator extends CountableProducer
{
    /** @var \ArrayAccess|array|Item[] */
    private $buffer;
    
    private int $count; //number of elements in buffer
    private int $index; //index of first element
    
    private int $initial;
    
    /**
     * @param \ArrayAccess|array|Item[] $buffer
     */
    public function __construct($buffer, int $count, int $index)
    {
        if (\is_array($buffer) || $buffer instanceof \ArrayAccess) {
            $this->buffer = $buffer;
        } else {
            throw new \InvalidArgumentException('Invalid param buffer');
        }
    
        if ($count >= 0) {
            $this->count = $count;
        } else {
            throw new \InvalidArgumentException('Invalid param count '.$count.' with index '.$index);
        }
        
        if ($index >= 0 && $index <= $count) {
            $this->index = $index;
        } else {
            throw new \InvalidArgumentException('Invalid param index '.$index.' with count '.$count);
        }
        
        $this->initial = $this->index;
    }
    
    public function feed(Item $item): \Generator
    {
        for ($i = 0; $i < $this->count; ++$i) {
            if ($this->index === $this->count) {
                $this->index = 0;
            }
    
            $x = $this->buffer[$this->index++];
            
            $item->key = $x->key;
            $item->value = $x->value;
            
            yield;
        }
    
        $this->buffer = [];
        $this->count = 0;
    }
    
    public function count(): int
    {
        return \is_countable($this->buffer) ? \count($this->buffer) : $this->count;
    }
    
    public function getLast(): ?Item
    {
        if ($this->count === 0) {
            return null;
        }
        
        $index = $this->initial - 1;
        
        if ($index < 0) {
            $index = $this->count - 1;
        }
        
        return $this->buffer[$index]->copy();
    }
    
    public function destroy(): void
    {
        $this->count = 0;
        $this->buffer = [];
    }
}