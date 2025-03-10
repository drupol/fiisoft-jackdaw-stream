<?php

namespace FiiSoft\Jackdaw\Operation\Strategy\Unique;

use FiiSoft\Jackdaw\Internal\Destroyable;
use FiiSoft\Jackdaw\Internal\Item;

interface UniquenessChecker extends Destroyable
{
    public function check(Item $item): bool;
}