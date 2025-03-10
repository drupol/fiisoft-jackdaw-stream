<?php declare(strict_types=1);

namespace FiiSoft\Jackdaw\Operation\Strategy\Unique\ComparisonStrategy;

use FiiSoft\Jackdaw\Operation\Strategy\Unique\ComparisonStrategy;

final class StandardComparator extends ComparisonStrategy
{
    private array $intsMap = [];
    private array $stringsMap = [];
    private array $otherValues = [];
    
    /**
     * @inheritDoc
     */
    public function isUnique($value): bool
    {
        if (\is_string($value)) {
            return !isset($this->stringsMap[$value]);
        }
        
        if (\is_int($value)) {
            return !isset($this->intsMap[$value]);
        }
        
        return !\in_array($value, $this->otherValues, true);
    }
    
    /**
     * @inheritDoc
     */
    public function remember($value): void
    {
        if (\is_string($value)) {
            $this->stringsMap[$value] = true;
        } elseif (\is_int($value)) {
            $this->intsMap[$value] = true;
        } else {
            $this->otherValues[] = $value;
        }
    }
    
    public function destroy(): void
    {
        $this->intsMap = [];
        $this->stringsMap = [];
        $this->otherValues = [];
    }
}