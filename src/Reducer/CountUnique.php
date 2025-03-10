<?php declare(strict_types=1);

namespace FiiSoft\Jackdaw\Reducer;

use FiiSoft\Jackdaw\Condition\Condition;
use FiiSoft\Jackdaw\Discriminator\Discriminator;
use FiiSoft\Jackdaw\Discriminator\Discriminators;
use FiiSoft\Jackdaw\Filter\Filter;
use FiiSoft\Jackdaw\Internal\Check;
use FiiSoft\Jackdaw\Internal\Helper;
use FiiSoft\Jackdaw\Mapper\Mapper;
use FiiSoft\Jackdaw\Predicate\Predicate;
use FiiSoft\Jackdaw\Reducer\Internal\BaseReducer;

final class CountUnique extends BaseReducer
{
    private ?Discriminator $discriminator = null;
    
    /** @var int[]|string[]  */
    private array $values = [];
    
    /**
     * @param Discriminator|Condition|Predicate|Filter|Mapper|callable|array|null $discriminator
     */
    public function __construct($discriminator = null, int $mode = Check::VALUE)
    {
        if ($discriminator !== null) {
            $this->discriminator = Discriminators::getAdapter($discriminator, $mode);
        }
    }
    
    /**
     * @inheritDoc
     */
    public function consume($value): void
    {
        if ($this->discriminator !== null) {
            $classifier = $this->discriminator->classify($value, null);
            
            if (\is_bool($classifier)) {
                $classifier = (int) $classifier;
            } elseif (!\is_string($classifier) && !\is_int($classifier)) {
                throw new \UnexpectedValueException(
                    'Unsupported value was returned from discriminator (got '.Helper::typeOfParam($classifier).')'
                );
            }
        } elseif (\is_string($value) || \is_int($value)) {
            $classifier = $value;
        } elseif (\is_bool($value)) {
            $classifier = $value ? 'true' : 'false';
        } elseif (\is_float($value)) {
            $classifier = (string) $value;
        } elseif (\is_array($value)) {
            $classifier = \md5(\json_encode($value, \JSON_THROW_ON_ERROR));
        } elseif (\is_object($value)) {
            $classifier = \spl_object_id($value);
        } elseif (\is_null($value)) {
            $classifier = 'null';
        } elseif (\is_resource($value)) {
            $classifier = (string) $value;
        } else {
            //@codeCoverageIgnoreStart
            throw new \UnexpectedValueException('Unsupported type of value: '.Helper::describe($value));
            //@codeCoverageIgnoreEnd
        }
        
        if (isset($this->values[$classifier])) {
            ++$this->values[$classifier];
        } else {
            $this->values[$classifier] = 1;
        }
    }
    
    /**
     * @inheritDoc
     */
    public function result(): array
    {
        return $this->values;
    }
    
    public function reset(): void
    {
        $this->values = [];
    }
    
    public function hasResult(): bool
    {
        return true;
    }
}