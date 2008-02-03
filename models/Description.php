<?php
class Description extends Doctrine_Record {
    public static function initMetadata($class) {
        $class->setColumn('description', 'string',3000);
        $class->setColumn('file_md5', 'string',32);
    }
}

