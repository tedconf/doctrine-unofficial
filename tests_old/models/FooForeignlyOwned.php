<?php
class FooForeignlyOwned extends Doctrine_Entity
{
    public static function initMetadata($class)
    {
        $class->setColumn('name', 'string', 200);
        $class->setColumn('fooId', 'integer');
    }
}
