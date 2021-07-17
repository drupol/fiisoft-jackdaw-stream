<?php declare(strict_types=1);

namespace FiiSoft\Jackdaw\Operation;

use FiiSoft\Jackdaw\Internal\Item;
use FiiSoft\Jackdaw\Internal\Signal;
use FiiSoft\Jackdaw\Operation\Internal\BaseOperation;

final class Shuffle extends BaseOperation
{
    /** @var Item[] */
    private array $items = [];
    
    public function handle(Signal $signal): void
    {
        $this->items[] = $signal->item->copy();
    }
    
    public function streamingFinished(Signal $signal): void
    {
        \shuffle($this->items);
        
        $signal->restartFrom($this->next, $this->items);
    }
}