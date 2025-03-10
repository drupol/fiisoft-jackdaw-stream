<?php declare(strict_types=1);

namespace FiiSoft\Jackdaw\Discriminator;

use FiiSoft\Jackdaw\Condition\Condition;
use FiiSoft\Jackdaw\Discriminator\Adapter\ConditionAdapter;
use FiiSoft\Jackdaw\Discriminator\Adapter\FilterAdapter;
use FiiSoft\Jackdaw\Discriminator\Adapter\MapperAdapter;
use FiiSoft\Jackdaw\Discriminator\Adapter\PredicateAdapter;
use FiiSoft\Jackdaw\Filter\Filter;
use FiiSoft\Jackdaw\Internal\Check;
use FiiSoft\Jackdaw\Mapper\Mapper;
use FiiSoft\Jackdaw\Predicate\Predicate;

final class Discriminators
{
    /**
     * @param Discriminator|Condition|Predicate|Filter|Mapper|callable|array $discriminator
     */
    public static function getAdapter($discriminator, int $mode = Check::VALUE): Discriminator
    {
        if ($discriminator instanceof Discriminator) {
            return $discriminator;
        }
        
        if (\is_callable($discriminator)) {
            return self::generic($discriminator);
        }
        
        if (\is_array($discriminator) && !empty($discriminator)) {
            return self::alternately($discriminator);
        }
    
        if ($discriminator instanceof Mapper) {
            return new MapperAdapter($discriminator);
        }
    
        if ($discriminator instanceof Filter) {
            return new FilterAdapter($discriminator, $mode);
        }
    
        if ($discriminator instanceof Predicate) {
            return new PredicateAdapter($discriminator, $mode);
        }
    
        if ($discriminator instanceof Condition) {
            return new ConditionAdapter($discriminator);
        }
        
        throw new \InvalidArgumentException('Invalid param discriminator');
    }
    
    /**
     * @param Discriminator|Condition|Predicate|Filter|Mapper|callable|array|string|int $discriminator
     */
    public static function prepare($discriminator, int $mode = Check::VALUE): Discriminator
    {
        return (\is_string($discriminator) && !\is_callable($discriminator)) || \is_int($discriminator)
            ? self::byField($discriminator)
            : self::getAdapter($discriminator, $mode);
    }
    
    public static function generic(callable $discriminator): Discriminator
    {
        return new GenericDiscriminator($discriminator);
    }
    
    public static function evenOdd(int $mode = Check::VALUE): Discriminator
    {
        return new EvenOdd($mode);
    }
    
    /**
     * @param string|int $field
     * @param string|int|null $orElse
     */
    public static function byField($field, $orElse = null): Discriminator
    {
        return new ByField($field, $orElse);
    }
    
    public static function byKey(): Discriminator
    {
        return new ByKey();
    }
    
    public static function byValue(): Discriminator
    {
        return new ByValue();
    }
    
    public static function alternately(array $classifiers): Discriminator
    {
        return new Alternately($classifiers);
    }
    
    /**
     * @param Discriminator|Condition|Predicate|Filter|Mapper|callable|string|int $discriminator
     * @param string|int $yes
     * @param string|int $no value of it must be different than value of $yes
     */
    public static function yesNo($discriminator, $yes = 'yes', $no = 'no'): Discriminator
    {
        return new YesNo($discriminator, $yes, $no);
    }
}