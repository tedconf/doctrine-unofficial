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
 * <http://www.phpdoctrine.org>.
 */

#namespace Doctrine::ORM::Mappings;

#use Doctrine::ORM::Entity;

/**
 * A one-to-one mapping describes a uni-directional mapping from one entity 
 * to another entity.
 *
 * @since 2.0
 * @author Roman Borschel <roman@code-factory.org>
 * @todo Rename to OneToOneMapping
 */
class Doctrine_Association_OneToOne extends Doctrine_Association
{
    /**
     * Maps the source foreign/primary key columns to the target primary/foreign key columns.
     * i.e. source.id (pk) => target.user_id (fk).
     * Reverse mapping of _targetToSourceKeyColumns.
     */
    protected $_sourceToTargetKeyColumns = array();

    /**
     * Maps the target primary/foreign key columns to the source foreign/primary key columns.
     * i.e. target.user_id (fk) => source.id (pk).
     * Reverse mapping of _sourceToTargetKeyColumns.
     */
    protected $_targetToSourceKeyColumns = array();
    
    /** Whether to delete orphaned elements (when nulled out, i.e. $foo->other = null) */
    protected $_deleteOrphans = false;
    
    /**
     * Constructor.
     * Creates a new OneToOneMapping.
     *
     * @param array $mapping  The mapping info.
     */
    public function __construct(array $mapping)
    {
        parent::__construct($mapping);
        if ($this->isOwningSide()) {
            $this->_sourceToTargetKeyColumns = $mapping['joinColumns'];
            $this->_targetToSourceKeyColumns = array_flip($this->_sourceToTargetKeyColumns);
        }
    }
    
    /**
     * Validates & completes the mapping. Mapping defaults are applied here.
     *
     * @param array $mapping  The mapping to validate & complete.
     * @return array  The validated & completed mapping.
     * @override
     */
    protected function _validateMapping(array $mapping)
    {
        $mapping = parent::_validateMapping($mapping);
        
        if ($this->isOwningSide()) {
            if ( ! isset($mapping['joinColumns'])) {
                throw Doctrine_MappingException::missingJoinColumns();
            }
        }
        
        return $mapping;
    }
    
    /**
     * Gets the source-to-target key column mapping.
     *
     * @return unknown
     */
    public function getSourceToTargetKeyColumns()
    {
        return $this->_sourceToTargetKeyColumns;
    }
    
    /**
     * Gets the target-to-source key column mapping.
     *
     * @return unknown
     */
    public function getTargetToSourceKeyColumns()
    {
        return $this->_targetToSourceKeyColumns;
    }
    
    /**
     * Lazy-loads the associated entity for a given entity.
     *
     * @param Doctrine::ORM::Entity $entity
     * @return void
     */
    public function lazyLoadFor(Doctrine_Entity $entity)
    {
        if ($entity->getClassName() != $this->_sourceClass->getClassName()) {
            //error?
        }
        
        $dql = 'SELECT t.* FROM ' . $this->_targetClass->getClassName() . ' t WHERE ';
        $params = array();
        foreach ($this->_sourceToTargetKeyFields as $sourceKeyField => $targetKeyField) {
            if ($params) {
                $dql .= " AND ";
            }
            $dql .= "t.$targetKeyField = ?";
            $params[] = $entity->_rawGetField($sourceKeyField);
        }
        
        $otherEntity = $this->_targetClass->getEntityManager()
                ->query($dql, $params)
                ->getFirst();
            
        if ( ! $otherEntity) {
            $otherEntity = Doctrine_Null::$INSTANCE;
        }
        $entity->_rawSetReference($this->_sourceFieldName, $otherEntity);
    }
    
}

?>