<?php
/*
 *  $Id$
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.phpdoctrine.com>.
 */

/**
 * Doctrine_Relation_OneToOne_TestCase
 *
 * @package     Doctrine
 * @author      Konsta Vesterinen <kvesteri@cc.hut.fi>
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @category    Object Relational Mapping
 * @link        www.phpdoctrine.com
 * @since       1.0
 * @version     $Revision$
 */
class Doctrine_Relation_OneToMany_TestCase extends Doctrine_UnitTestCase
{
    public function prepareData()
    { }
    public function testRelationParsing()
    {
        $table = $this->conn->getTable('Entity');

        $rel = $table->getRelation('Phonenumber');
        
        $this->assertTrue($rel instanceof Doctrine_Relation_ForeignKey);

        $rel = $table->getRelation('Email');
        
        $this->assertTrue($rel instanceof Doctrine_Relation_LocalKey);
    }
    public function testRelationParsing2()
    {
        $table = $this->conn->getTable('Phonenumber');

        $rel = $table->getRelation('Entity');
        
        $this->assertTrue($rel instanceof Doctrine_Relation_LocalKey);
    }

    public function testRelationSaving() 
    {
        $e = new Entity();
        $e->name = 'test';
        $e->save();
         
        $nr = new Phonenumber();
        $nr->phonenumber = '1234556';
        $nr->save();
        $nr->Entity = $e;
    }
}
