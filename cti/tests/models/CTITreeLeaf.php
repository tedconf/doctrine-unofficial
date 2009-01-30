<?php
abstract class CTITreeLeafAbstract extends Doctrine_Record
{}

class CTITreeLeafParent extends CTITreeLeafAbstract
{
    public function setTableDefinition()
    {
    	$this->hasColumn('name', 'string');
        $this->hasColumn('parent_id', 'integer');
    }
    public function setUp() 
    {
        $this->hasOne('CTITreeLeafParent as Parent', 'CTITreeLeafParent.parent_id');
        $this->hasMany('CTITreeLeafParent as Children', 'CTITreeLeafParent.parent_id');
    }
}

class CTITreeLeaf extends CTITreeLeafParent
{
    public function setTableDefinition()
    {
    	$this->hasColumn('child_name', 'string');
    }
}