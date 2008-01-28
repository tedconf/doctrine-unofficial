<?php
class FooBarRecord extends Doctrine_Record
{
    public static function initMetadata($class)
    {
        $class->setColumn('fooId', 'integer', null, array('primary' => true));
        $class->setColumn('barId', 'integer', null, array('primary' => true));
    }
}
