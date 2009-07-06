<?php

namespace Doctrine\Tests\ORM\Functional;

use Doctrine\Tests\Models\ECommerce\ECommerceCustomer;

require_once __DIR__ . '/../../TestInit.php';

/**
 * Tests a self referential one-to-one association mapping (without inheritance).
 * Relation is defined as the mentor that a customer choose. The mentor could 
 * help only one other customer, while a customer can choose only one mentor
 * for receiving support.
 * Inverse side is not present.
 */
class OneToOneSelfReferentialAssociationTest extends \Doctrine\Tests\OrmFunctionalTestCase
{
    private $customer;
    private $mentor;

    protected function setUp()
    {
        $this->useModelSet('ecommerce');
        parent::setUp();
        $this->customer = new ECommerceCustomer();
        $this->customer->setName('Anakin Skywalker');
        $this->mentor = new ECommerceCustomer();
        $this->mentor->setName('Obi-wan Kenobi');
    }

    public function testSavesAOneToOneAssociationWithCascadeSaveSet() {
        $this->customer->setMentor($this->mentor);
        $this->_em->save($this->customer);
        
        $this->assertForeignKeyIs($this->mentor->getId());
    }

    public function testRemovesOneToOneAssociation()
    {
        $this->customer->setMentor($this->mentor);
        $this->_em->save($this->customer);
        $this->customer->removeMentor();

        $this->_em->flush();

        $this->assertForeignKeyIs(null);
    }

    public function testEagerLoad()
    {
        $customer = new ECommerceCustomer;
        $customer->setName('Luke Skywalker');
        $mentor = new ECommerceCustomer;
        $mentor->setName('Obi-wan Kenobi');
        $customer->setMentor($mentor);
        
        $this->_em->save($customer);
        
        $this->_em->flush();
        $this->_em->clear();

        $query = $this->_em->createQuery('select c, m from Doctrine\Tests\Models\ECommerce\ECommerceCustomer c left join c.mentor m');
        $result = $query->getResultList();
        $customer = $result[0];
        
        $this->assertTrue($customer->getMentor() instanceof ECommerceCustomer);
        $this->assertEquals('Obi-wan Kenobi', $customer->getMentor()->getName());
    }
    
    /* TODO: not yet implemented
    public function testLazyLoad() {
        
    }*/

    public function assertForeignKeyIs($value) {
        $foreignKey = $this->_em->getConnection()->execute('SELECT mentor_id FROM ecommerce_customers WHERE id=?', array($this->customer->getId()))->fetchColumn();
        $this->assertEquals($value, $foreignKey);
    }
}
