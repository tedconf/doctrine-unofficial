<?php
abstract class BaseSymfonyRecord extends Doctrine_Record
{
    public static function initMetadata($class)
    {
        $class->setInheritanceType(Doctrine::INHERITANCE_TYPE_TABLE_PER_CLASS);
        $class->setColumn('name', 'string', 30);
    }

}
