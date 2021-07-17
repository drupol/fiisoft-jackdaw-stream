<?php declare(strict_types=1);

namespace FiiSoft\Jackdaw\Filter\Internal;

use FiiSoft\Jackdaw\Filter\Length;

final class LengthFactory
{
    private static ?LengthFactory $instance = null;
    
    public static function instance(): LengthFactory
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct()
    {
    }

    public function eq(int $length): Length
    {
        return new Length($length, 'eq');
    }
    
    public function ne(int $length): Length
    {
        return new Length($length, 'ne');
    }
    
    public function lt(int $length): Length
    {
        return new Length($length, 'lt');
    }
    
    public function le(int $length): Length
    {
        return new Length($length, 'le');
    }
    
    public function gt(int $length): Length
    {
        return new Length($length, 'gt');
    }
    
    public function ge(int $length): Length
    {
        return new Length($length, 'ge');
    }
}