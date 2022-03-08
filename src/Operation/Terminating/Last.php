<?php declare(strict_types=1);

namespace FiiSoft\Jackdaw\Operation\Terminating;

use FiiSoft\Jackdaw\Internal\Item;
use FiiSoft\Jackdaw\Internal\ResultProvider;
use FiiSoft\Jackdaw\Internal\Signal;
use FiiSoft\Jackdaw\Operation\Internal\FinalOperation;
use FiiSoft\Jackdaw\Stream;

final class Last extends FinalOperation implements ResultProvider
{
    private ?Item $item = null;
    private bool $found = false;
    
    /**
     * @param Stream $stream
     * @param mixed $default
     */
    public function __construct(Stream $stream, $default)
    {
        parent::__construct($stream, $this, $default);
    }
    
    public function handle(Signal $signal): void
    {
        if ($this->found === false) {
            $this->found = true;
        }
    }
    
    public function streamingFinished(Signal $signal): bool
    {
        if ($this->found) {
            $this->item = $signal->item->copy();
        }
        
        return $this->next->streamingFinished($signal);
    }
    
    public function hasResult(): bool
    {
        return $this->found;
    }
    
    public function getResult(): Item
    {
        return $this->item;
    }
}