<?php declare(strict_types=1);

namespace FiiSoft\Jackdaw\Producer;

use FiiSoft\Jackdaw\Consumer\Consumer;
use FiiSoft\Jackdaw\Internal\Item;
use FiiSoft\Jackdaw\Producer\Tech\CountableProducer;

final class QueueProducer extends CountableProducer implements Consumer
{
    /** @var Item[] */
    private array $buffer = [];
    private int $autoKey = 0;
    
    public function __construct(array $elements = [])
    {
        $this->appendMany($elements);
    }
    
    public function feed(Item $item): \Generator
    {
        \reset($this->buffer);
        
        while (!empty($this->buffer)) {
            $first = \array_shift($this->buffer);
            
            $item->key = $first->key;
            $item->value = $first->value;
            
            yield;
        }
    }
    
    /**
     * @param array $elements
     * @return $this fluent interface
     */
    public function appendMany(array $elements): self
    {
        foreach ($elements as $key => $value) {
            $this->append($value, $key);
        }
    
        return $this;
    }
    
    /**
     * @param mixed $value
     * @param mixed|null $key
     * @return $this fluent interface
     */
    public function append($value, $key = null): self
    {
        $this->buffer[] = new Item($key ?? $this->autoKey++, $value);
        
        return $this;
    }
    
    /**
     * @param array $elements
     * @param bool $reverse
     * @return $this fluent interface
     */
    public function prependMany(array $elements, bool $reverse = false): self
    {
        if (!$reverse) {
            $elements = \array_reverse($elements, true);
        }
    
        foreach ($elements as $key => $value) {
            $this->prepend($value, $key);
        }
        
        return $this;
    }
    
    /**
     * @param mixed $value
     * @param mixed|null $key
     * @return $this fluent interface
     */
    public function prepend($value, $key = null): self
    {
        \array_unshift($this->buffer, new Item($key ?? $this->autoKey++, $value));
        
        return $this;
    }
    
    public function consume($value, $key): void
    {
        $this->buffer[] = new Item($key ?? $this->autoKey++, $value);
    }
    
    public function count(): int
    {
        return \count($this->buffer);
    }
    
    public function getLast(): ?Item
    {
        if (empty($this->buffer)) {
            return null;
        }
        
        $last = \array_key_last($this->buffer);
        
        return $this->buffer[$last]->copy();
    }
    
    public function destroy(): void
    {
        $this->buffer = [];
    }
}