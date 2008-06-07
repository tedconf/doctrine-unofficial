<?php

/**
 * Relation testing (accessing) throws an Doctrine_Record_Exception
 * with message 'Unknown record property / related component
 * "payment_detail_id" on "T1072BankTransaction"'.
 * 
 * It happens if I access the relation, save the record, and access
 * the relation column (in this order).
 * 
 * UPDATE:
 * There are three addition checks for the column value type that
 * must be NULL and not an object which is not true after accessing
 * the relation.
 */
class Doctrine_Ticket_1072_TestCase extends Doctrine_UnitTestCase 
{
    public function prepareData()
    {
    }

    public function prepareTables()
    {
        $this->tables = array();
        $this->tables[] = 'T1072BankTransaction';
        $this->tables[] = 'T1072PaymentDetail';
        parent::prepareTables();
    }

    public function testTicket()
    {
        $bt = new T1072BankTransaction();
        $bt->name = 'Test Bank Transaction';
        
        // (additional check: value must be NULL)
        $this->assertEqual(gettype($bt->payment_detail_id), gettype(null));
        
        // If I access this relation...
        if ($bt->T1072PaymentDetail) {
        }
        
        // (additional check: value must still be NULL not an object)
        $this->assertEqual(gettype($bt->payment_detail_id), gettype(null));
        
        // ...save...
        $bt->save();
        
        try {
            // ...and access the relation column it will throw
            // an exception here but it shouldn't.
            if ($bt->payment_detail_id) {
            }
            
            // (additional check: value must still be NULL not an object)
            $this->assertEqual(gettype($bt->payment_detail_id), gettype(null));
            
            $this->pass();
        } catch (Doctrine_Record_Exception $e) {
            $this->fail($e->getMessage());
        }
    }
}

class T1072BankTransaction extends Doctrine_Record
{
    public function setTableDefinition()
    {
        $this->setTableName('t1072_bank_transaction');
        $this->hasColumn('payment_detail_id', 'integer', null);
        $this->hasColumn('name', 'string', 255, array('notnull' => true));
        $this->option('charset', 'utf8');
    }
    
    public function setUp()
    {
        parent::setUp();
        $this->hasOne('T1072PaymentDetail', array('local' => 'payment_detail_id',
                                                  'foreign' => 'id'));
    }
}

class T1072PaymentDetail extends Doctrine_Record
{
    public function setTableDefinition()
    {
        $this->setTableName('t1072_payment_detail');
        $this->hasColumn('name', 'string', 255, array('notnull' => true));
        $this->option('charset', 'utf8');
    }
    
    public function setUp()
    {
        parent::setUp();
        $this->hasOne('T1072BankTransaction', array('local' => 'id',
                                                    'foreign' => 'payment_detail_id'));
    }
}