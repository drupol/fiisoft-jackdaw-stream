<?php

namespace FiiSoft\Jackdaw\Internal;

interface ResultCaster
{
    public function toString(string $separator = ','): string;
    
    public function toJson(int $flags = 0, bool $preserveKeys = false): string;
    
    /**
     * It works in the same way as toJson($flags, true).
     */
    public function toJsonAssoc(int $flags = 0): string;
    
    public function toArray(bool $preserveKeys = false): array;
    
    /**
     * It works in the same way as toArray(true).
     */
    public function toArrayAssoc(): array;
}