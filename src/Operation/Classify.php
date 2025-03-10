<?php declare(strict_types=1);

namespace FiiSoft\Jackdaw\Operation;

use FiiSoft\Jackdaw\Condition\Condition;
use FiiSoft\Jackdaw\Discriminator\Discriminator;
use FiiSoft\Jackdaw\Discriminator\Discriminators;
use FiiSoft\Jackdaw\Filter\Filter;
use FiiSoft\Jackdaw\Internal\Helper;
use FiiSoft\Jackdaw\Internal\Signal;
use FiiSoft\Jackdaw\Mapper\Mapper;
use FiiSoft\Jackdaw\Operation\Internal\BaseOperation;
use FiiSoft\Jackdaw\Predicate\Predicate;

final class Classify extends BaseOperation
{
    private Discriminator $discriminator;
    
    /**
     * @param Discriminator|Condition|Predicate|Filter|Mapper|callable|array $discriminator
     */
    public function __construct($discriminator)
    {
        $this->discriminator = Discriminators::getAdapter($discriminator);
    }
    
    public function handle(Signal $signal): void
    {
        $classifier = $this->discriminator->classify($signal->item->value, $signal->item->key);
        
        if (\is_string($classifier) || \is_bool($classifier) || \is_int($classifier)) {
            $signal->item->key = $classifier;
            $this->next->handle($signal);
        } else {
            throw new \UnexpectedValueException(
                'Unsupported value was returned from discriminator (got '.Helper::typeOfParam($classifier).')'
            );
        }
    }
}