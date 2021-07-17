<?php declare(strict_types=1);

namespace FiiSoft\Jackdaw\Operation;

use FiiSoft\Jackdaw\Collector\Collector;
use FiiSoft\Jackdaw\Collector\Collectors;
use FiiSoft\Jackdaw\Internal\Signal;
use FiiSoft\Jackdaw\Operation\Internal\BaseOperation;

final class Collect extends BaseOperation
{
    /** @var Collector */
    private $collector;
    
    /** @var bool */
    private $preserveKeys;
    
    /**
     * @param Collector|\ArrayAccess $collector
     * @param bool $preserveKeys
     */
    public function __construct($collector, bool $preserveKeys = false)
    {
        $this->collector = Collectors::getAdapter($collector);
        $this->preserveKeys = $preserveKeys;
    }
    
    public function handle(Signal $signal)
    {
        if ($this->preserveKeys) {
            $this->collector->set($signal->item->key, $signal->item->value);
        } else {
            $this->collector->add($signal->item->value);
        }
        
        $this->next->handle($signal);
    }
}