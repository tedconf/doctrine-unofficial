<?php

namespace Doctrine\Tests\ORM\Functional;

use Doctrine\Tests\Models\ECommerce\ECommerceProduct;
use Doctrine\Tests\Models\ECommerce\ECommerceCategory;
use Doctrine\ORM\Mapping\AssociationMapping;
use Doctrine\ORM\Query;

require_once __DIR__ . '/../../TestInit.php';

/**
 * Tests a bidirectional many-to-many association mapping (without inheritance).
 * Owning side is ECommerceProduct, inverse side is ECommerceCategory.
 */
class ManyToManyBidirectionalAssociationTest extends AbstractManyToManyAssociationTestCase
{
    protected $_firstField = 'product_id';
    protected $_secondField = 'category_id';
    protected $_table = 'ecommerce_products_categories';
    private $firstProduct;
    private $secondProduct;
    private $firstCategory;
    private $secondCategory;

    protected function setUp()
    {
        $this->useModelSet('ecommerce');
        parent::setUp();
        $this->firstProduct = new ECommerceProduct();
        $this->firstProduct->setName("First Product");
        $this->secondProduct = new ECommerceProduct();
        $this->secondProduct->setName("Second Product");
        $this->firstCategory = new ECommerceCategory();
        $this->firstCategory->setName("Business");
        $this->secondCategory = new ECommerceCategory();
        $this->secondCategory->setName("Home");
    }

    public function testSavesAManyToManyAssociationWithCascadeSaveSet()
    {
        $this->firstProduct->addCategory($this->firstCategory);
        $this->firstProduct->addCategory($this->secondCategory);
        $this->_em->persist($this->firstProduct);
        $this->_em->flush();
        
        $this->assertForeignKeysContain($this->firstProduct->getId(),
                                   $this->firstCategory->getId());
        $this->assertForeignKeysContain($this->firstProduct->getId(),
                                   $this->secondCategory->getId());
    }

    public function testRemovesAManyToManyAssociation()
    {
        $this->firstProduct->addCategory($this->firstCategory);
        $this->firstProduct->addCategory($this->secondCategory);
        $this->_em->persist($this->firstProduct);
        $this->firstProduct->removeCategory($this->firstCategory);

        $this->_em->flush();

        $this->assertForeignKeysNotContain($this->firstProduct->getId(), $this->firstCategory->getId());
        $this->assertForeignKeysContain($this->firstProduct->getId(), $this->secondCategory->getId());
        
        $this->firstProduct->getCategories()->remove(1);
        $this->_em->flush();
        
        $this->assertForeignKeysNotContain($this->firstProduct->getId(), $this->secondCategory->getId());
    }

    public function testEagerLoadsInverseSide()
    {
        $this->_createLoadingFixture();
        list ($firstProduct, $secondProduct) = $this->_findProducts();
        $categories = $firstProduct->getCategories();
        $this->assertLoadingOfInverseSide($categories); 
        $this->assertLoadingOfInverseSide($secondProduct->getCategories());
    }

    public function testEagerLoadsOwningSide()
    {
        $this->_createLoadingFixture();
        $products = $this->_findProducts();
        $this->assertLoadingOfOwningSide($products);
    }
    
    public function testLazyLoadsCollectionOnTheInverseSide()
    {
        $this->_createLoadingFixture();

        $metadata = $this->_em->getClassMetadata('Doctrine\Tests\Models\ECommerce\ECommerceCategory');
        $metadata->getAssociationMapping('products')->fetchMode = AssociationMapping::FETCH_LAZY;

        $query = $this->_em->createQuery('SELECT c FROM Doctrine\Tests\Models\ECommerce\ECommerceCategory c order by c.id');
        $categories = $query->getResult();
        $this->assertLoadingOfInverseSide($categories);
    }

    public function testLazyLoadsCollectionOnTheOwningSide()
    {
        $this->_createLoadingFixture();

        $metadata = $this->_em->getClassMetadata('Doctrine\Tests\Models\ECommerce\ECommerceProduct');
        $metadata->getAssociationMapping('categories')->fetchMode = AssociationMapping::FETCH_LAZY;

        $query = $this->_em->createQuery('SELECT p FROM Doctrine\Tests\Models\ECommerce\ECommerceProduct p order by p.id');
        $products = $query->getResult();
        
        $this->assertEquals(2, count($products));
        $this->assertEquals(0, count($products[0]->getCategories()->unwrap()));
        $this->assertEquals(0, count($products[1]->getCategories()->unwrap()));
        
        $this->assertLoadingOfOwningSide($products);
    }

    private function _createLoadingFixture()
    {
        $this->firstProduct->addCategory($this->firstCategory);
        $this->firstProduct->addCategory($this->secondCategory);
        $this->secondProduct->addCategory($this->firstCategory);
        $this->secondProduct->addCategory($this->secondCategory);
        $this->_em->persist($this->firstProduct);
        $this->_em->persist($this->secondProduct);
        
        $this->_em->flush();
        $this->_em->clear();
    }

    protected function _findProducts()
    {
        $query = $this->_em->createQuery('SELECT p, c FROM Doctrine\Tests\Models\ECommerce\ECommerceProduct p LEFT JOIN p.categories c ORDER BY p.id, c.id');
        //$query->setHint(Query::HINT_FORCE_PARTIAL_LOAD, true);
        return $query->getResult();
    }
    
    public function assertLoadingOfOwningSide($products)
    {
        list ($firstProduct, $secondProduct) = $products;

        $firstProductCategories = $firstProduct->getCategories();
        $secondProductCategories = $secondProduct->getCategories();
        
        $this->assertEquals(2, count($firstProductCategories));
        $this->assertEquals(2, count($secondProductCategories));
        
        $this->assertTrue($firstProductCategories[0] === $secondProductCategories[0]);
        $this->assertTrue($firstProductCategories[1] === $secondProductCategories[1]);
        
        $firstCategoryProducts = $firstProductCategories[0]->getProducts();
        $secondCategoryProducts = $firstProductCategories[1]->getProducts();
        
        $this->assertEquals(2, count($firstCategoryProducts));
        $this->assertEquals(2, count($secondCategoryProducts));

        $this->assertTrue($firstCategoryProducts[0] instanceof ECommerceProduct);
        $this->assertTrue($firstCategoryProducts[1] instanceof ECommerceProduct);
        $this->assertTrue($secondCategoryProducts[0] instanceof ECommerceProduct);
        $this->assertTrue($secondCategoryProducts[1] instanceof ECommerceProduct);
        
        $this->assertCollectionEquals($firstCategoryProducts, $secondCategoryProducts);
    }

    public function assertLoadingOfInverseSide($categories)
    {
        $this->assertTrue($categories[0] instanceof ECommerceCategory);
        $this->assertTrue($categories[1] instanceof ECommerceCategory);
        
        $this->assertEquals(2, count($categories[0]->getProducts()));
        $this->assertTrue($categories[0]->getProducts()->get(0) instanceof ECommerceProduct);
        $this->assertTrue($categories[0]->getProducts()->get(1) instanceof ECommerceProduct);
        
        $this->assertEquals(2, count($categories[1]->getProducts()));
        $this->assertTrue($categories[1]->getProducts()->get(0) instanceof ECommerceProduct);
        $this->assertTrue($categories[1]->getProducts()->get(1) instanceof ECommerceProduct);
    }
}
