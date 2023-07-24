<?php declare(strict_types=1);

namespace FiiSoft\Jackdaw\Mapper\Internal;

use FiiSoft\Jackdaw\Registry\RegReader;

final class RegistryAdapter extends BaseMapper
{
    private RegReader $reader;
    
    public function __construct(RegReader $reader)
    {
        $this->reader = $reader;
    }
    
    /**
     * @inheritDoc
     */
    public function map($value, $key)
    {
        return $this->reader->read();
    }
}