<?php
/*
 *  $Id: UnitOfWork.php 4947 2008-09-12 13:16:05Z romanb $
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

namespace Doctrine\ORM;

use Doctrine\ORM\Internal\CommitOrderCalculator;
use Doctrine\ORM\Internal\CommitOrderNode;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exceptions\UnitOfWorkException;

/**
 * The UnitOfWork is responsible for tracking changes to objects during an
 * "object-level" transaction and for writing out changes to the database
 * in the correct order.
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.org
 * @since       2.0
 * @version     $Revision$
 * @author      Roman Borschel <roman@code-factory.org>
 */
class UnitOfWork
{
    /**
     * An Entity is in managed state when it has a primary key/identifier (and
     * therefore persistent state) and is managed by an EntityManager
     * (registered in the identity map).
     * In MANAGED state the entity is associated with an EntityManager that manages
     * the persistent state of the Entity.
     */
    const STATE_MANAGED = 1;

    /**
     * An Entity is new if it does not yet have an identifier/primary key
     * and is not (yet) managed by an EntityManager.
     */
    const STATE_NEW = 2;

    /**
     * A detached Entity is an instance with a persistent identity that is not
     * (or no longer) associated with an EntityManager (and a UnitOfWork).
     * This means its no longer in the identity map.
     */
    const STATE_DETACHED = 3;

    /**
     * A removed Entity instance is an instance with a persistent identity,
     * associated with an EntityManager, whose persistent state has been
     * deleted (or is scheduled for deletion).
     */
    const STATE_DELETED = 4;

    /**
     * The identity map that holds references to all managed entities that have
     * an identity. The entities are grouped by their class name.
     * Since all classes in a hierarchy must share the same identifier set,
     * we always take the root class name of the hierarchy.
     *
     * @var array
     */
    private $_identityMap = array();

    /**
     * Map of all identifiers. Keys are object ids.
     *
     * @var array
     */
    private $_entityIdentifiers = array();

    /**
     * Map of the original entity data of entities fetched from the database.
     * Keys are object ids. This is used for calculating changesets at commit time.
     * Note that PHPs "copy-on-write" behavior helps a lot with the potentially
     * high memory usage.
     *
     * @var array
     */
    private $_originalEntityData = array();

    /**
     * Map of data changes. Keys are object ids.
     * Filled at the beginning of a commit() of the UnitOfWork and cleaned at the end.
     *
     * @var array
     */
    private $_entityChangeSets = array();

    /**
     * The states of entities in this UnitOfWork.
     *
     * @var array
     */
    private $_entityStates = array();

    /**
     * Map of entities that are scheduled for dirty checking at commit time.
     * This is only used if automatic dirty checking is disabled.
     */
    private $_scheduledForDirtyCheck = array();

    /**
     * A list of all new entities that need to be INSERTed.
     *
     * @var array
     * @todo Index by class name.
     * @todo Rename to _inserts?
     */
    private $_newEntities = array();

    /**
     * A list of all dirty entities that need to be UPDATEd.
     *
     * @var array
     * @todo Rename to _updates?
     */
    private $_dirtyEntities = array();

    /**
     * A list of all deleted entities.
     * Removed entities are entities that are "scheduled for removal" but have
     * not yet been removed from the database.
     *
     * @var array
     * @todo Rename to _deletions?
     */
    private $_deletedEntities = array();
    
    /**
     * All collection deletions.
     *
     * @var array
     */
    private $_collectionDeletions = array();
    
    /**
     * All collection creations.
     *
     * @var array
     */
    private $_collectionCreations = array();
    
    /**
     * All collection updates.
     *
     * @var array
     */
    private $_collectionUpdates = array();

    /**
     * List of collections visited during a commit-phase of a UnitOfWork.
     * At the end of the UnitOfWork all these collections will make new snapshots
     * of their data.
     *
     * @var array
     */
    private $_visitedCollections = array();

    /**
     * The EntityManager that "owns" this UnitOfWork instance.
     *
     * @var Doctrine\ORM\EntityManager
     */
    private $_em;

    /**
     * The calculator used to calculate the order in which changes to
     * entities need to be written to the database.
     *
     * @var Doctrine\ORM\Internal\CommitOrderCalculator
     */
    private $_commitOrderCalculator;

    /**
     * The entity persister instances used to persist entity instances.
     *
     * @var array
     */
    private $_persisters = array();

    /**
     * The collection persister instances used to persist collections.
     *
     * @var array
     */
    private $_collectionPersisters = array();

    /**
     * Initializes a new UnitOfWork instance, bound to the given EntityManager.
     *
     * @param Doctrine\ORM\EntityManager $em
     */
    public function __construct(EntityManager $em)
    {
        $this->_em = $em;
        //TODO: any benefit with lazy init?
        $this->_commitOrderCalculator = new CommitOrderCalculator();
    }

    /**
     * Commits the UnitOfWork, executing all operations that have been postponed
     * up to this point.
     */
    public function commit()
    {
        // Compute changes done since last commit
        $this->computeChangeSets();

        if (empty($this->_newEntities) &&
                empty($this->_deletedEntities) &&
                empty($this->_dirtyEntities) &&
                empty($this->_collectionUpdates)) {
            return; // Nothing to do.
        }

        // Now we need a commit order to maintain referential integrity
        $commitOrder = $this->_getCommitOrder();

        //TODO: begin transaction here?

        foreach ($commitOrder as $class) {
            $this->_executeInserts($class);
        }
        foreach ($commitOrder as $class) {
            $this->_executeUpdates($class);
        }
        
        //TODO: collection deletions (deletions of complete collections)
        //TODO: collection updates (deleteRows, updateRows, insertRows)
        foreach ($this->_collectionUpdates as $collectionToUpdate) {
            $this->getCollectionPersister($collectionToUpdate->getMapping())
                    ->update($collectionToUpdate);
        }
        //TODO: collection recreations (insertions of complete collections)

        // Entity deletions come last and need to be in reverse commit order
        for ($count = count($commitOrder), $i = $count - 1; $i >= 0; --$i) {
            $this->_executeDeletions($commitOrder[$i]);
        }

        //TODO: commit transaction here?

        // Take new snapshots from visited collections
        foreach ($this->_visitedCollections as $coll) {
            $coll->_takeSnapshot();
        }

        // Clear up
        $this->_newEntities = array();
        $this->_dirtyEntities = array();
        $this->_deletedEntities = array();
        $this->_entityChangeSets = array();
        $this->_collectionUpdates = array();
        $this->_visitedCollections = array();
    }

    /**
     * Gets the changeset for an entity.
     *
     * @return array
     */
    public function getEntityChangeSet($entity)
    {
        $oid = spl_object_hash($entity);
        if (isset($this->_entityChangeSets[$oid])) {
            return $this->_entityChangeSets[$oid];
        }
        return array();
    }

    /**
     * Computes all the changes that have been done to entities and collections
     * since the last commit and stores these changes in the _entityChangeSet map
     * temporarily for access by the persisters, until the UoW commit is finished.
     *
     * @param array $entities The entities for which to compute the changesets. If this
     *          parameter is not specified, the changesets of all entities in the identity
     *          map are computed if automatic dirty checking is enabled (the default).
     *          If automatic dirty checking is disabled, only those changesets will be
     *          computed that have been scheduled through scheduleForDirtyCheck().
     */
    public function computeChangeSets(array $entities = null)
    {
        $entitySet = array();
        if ( ! is_null($entities)) {
            foreach ($entities as $entity) {
                $entitySet[get_class($entity)][] = $entity;
            }
        } else if ( ! $this->_em->getConfiguration()->getAutomaticDirtyChecking()) {
            $entitySet = $this->_scheduledForDirtyCheck;
        } else {
            $entitySet = $this->_identityMap;
        }

        foreach ($entitySet as $className => $entities) {
            $class = $this->_em->getClassMetadata($className);
            if ( ! $class->isInheritanceTypeNone() && count($entities) > 0) {
                $class = $this->_em->getClassMetadata(get_class($entities[0]));
            }
            foreach ($entities as $entity) {
                $oid = spl_object_hash($entity);
                $state = $this->getEntityState($entity);

                // Look for changes in the entity itself by comparing against the
                // original data we have.
                if ($state == self::STATE_MANAGED || $state == self::STATE_NEW) {
                    $actualData = array();
                    foreach ($class->getReflectionProperties() as $name => $refProp) {
                        if ( ! $class->isIdentifier($name) || $class->isIdentifierNatural()) {
                            $actualData[$name] = $refProp->getValue($entity);
                        }
                        
                        if ($class->isCollectionValuedAssociation($name) && ! ($actualData[$name] instanceof PersistentCollection)) {
                            // Inject PersistentCollection
                            //TODO: If $actualData[$name] is Collection then unwrap the array
                            $assoc = $class->getAssociationMapping($name);
                            $coll = new PersistentCollection($this->_em, $assoc->getTargetEntityName(),
                                    $actualData[$name] ? $actualData[$name] : array());
                            $coll->_setOwner($entity, $assoc);
                            if ( ! $coll->isEmpty()) $coll->setDirty(true);
                            $class->getReflectionProperty($name)->setValue($entity, $coll);
                            $actualData[$name] = $coll;
                        }
                    }

                    if ( ! isset($this->_originalEntityData[$oid])) {
                        // Entity is either NEW or MANAGED but not yet fully persisted
                        // (only has an id). These result in an INSERT.
                        $this->_entityChangeSets[$oid] = $actualData;
                        $this->_originalEntityData[$oid] = $actualData;
                    } else {
                        // Entity is "fully" MANAGED: it was already fully persisted before
                        // and we have a copy of the original data
                        $originalData = $this->_originalEntityData[$oid];
                        $changeSet = array();
                        $entityIsDirty = false;

                        foreach ($actualData as $propName => $actualValue) {
                            $orgValue = isset($originalData[$propName]) ? $originalData[$propName] : null;
                            if (is_object($orgValue) && $orgValue !== $actualValue) {
                                $changeSet[$propName] = array($orgValue, $actualValue);
                            } else if ($orgValue != $actualValue || (is_null($orgValue) xor is_null($actualValue))) {
                                $changeSet[$propName] = array($orgValue, $actualValue);
                            }

                            if (isset($changeSet[$propName])) {
                                if ($class->hasAssociation($propName)) {
                                    $assoc = $class->getAssociationMapping($propName);
                                    if ($assoc->isOneToOne() && $assoc->isOwningSide()) {
                                        $entityIsDirty = true;
                                    }
                                } else {
                                    $entityIsDirty = true;
                                }
                            }                            
                        }
                        if ($changeSet) {
                            if ($entityIsDirty) {
                                $this->_dirtyEntities[$oid] = $entity;
                            }
                            $this->_entityChangeSets[$oid] = $changeSet;
                            $this->_originalEntityData[$oid] = $actualData;
                        }
                    }

                    // Look for changes in associations of the entity
                    if ($state == self::STATE_MANAGED) {
                        foreach ($class->getAssociationMappings() as $assoc) {
                            $val = $actualData[$assoc->getSourceFieldName()];
                            if ( ! is_null($val)) {
                                $this->_computeAssociationChanges($assoc, $val);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Computes the changes of an association.
     *
     * @param <type> $assoc
     * @param <type> $value
     */
    private function _computeAssociationChanges($assoc, $value)
    {
        if ($assoc->isOneToOne()) {
            $value = array($value);
        }

        $targetClass = $this->_em->getClassMetadata($assoc->getTargetEntityName());
        foreach ($value as $entry) {
            $state = $this->getEntityState($entry);
            $oid = spl_object_hash($entry);
            if ($state == self::STATE_NEW) {
                // Get identifier, if possible (not post-insert)
                $idGen = $this->_em->getIdGenerator($targetClass->getClassName());
                if ( ! $idGen->isPostInsertGenerator()) {
                    $idValue = $idGen->generate($entry);
                    $this->_entityStates[$oid] = self::STATE_MANAGED;
                    if ( ! $idGen instanceof \Doctrine\ORM\Id\Assigned) {
                        $this->_entityIdentifiers[$oid] = array($idValue);
                        $targetClass->getSingleIdReflectionProperty()->setValue($entry, $idValue);
                    } else {
                        $this->_entityIdentifiers[$oid] = $idValue;
                    }
                    $this->addToIdentityMap($entry);
                }

                // NEW entities are INSERTed within the current unit of work.
                $data = array();
                foreach ($targetClass->getReflectionProperties() as $name => $refProp) {
                    $data[$name] = $refProp->getValue($entry);
                }

                $oid = spl_object_hash($entry);
                $this->_newEntities[$oid] = $entry;
                $this->_entityChangeSets[$oid] = $data;
                $this->_originalEntityData[$oid] = $data;
            } else if ($state == self::STATE_DELETED) {
                throw new DoctrineException("Deleted entity in collection detected during flush.");
            }
            // MANAGED associated entities are already taken into account
            // during changeset calculation anyway, since they are in the identity map.
        }

        if ($value instanceof PersistentCollection && $value->isDirty()) {
            if ($assoc->isOwningSide()) {
                $this->_collectionUpdates[] = $value;
            }
            $this->_visitedCollections[] = $value;
        }
    }

    /**
     * Executes all entity insertions for entities of the specified type.
     *
     * @param Doctrine\ORM\Mapping\ClassMetadata $class
     */
    private function _executeInserts($class)
    {
        //TODO: Maybe $persister->addInsert($entity) in the loop and
        // $persister->executeInserts() at the end to allow easy prepared
        // statement reuse and maybe bulk operations in the persister.
        // Same for update/delete.
        $className = $class->getClassName();
        $persister = $this->getEntityPersister($className);
        foreach ($this->_newEntities as $entity) {
            if (get_class($entity) == $className) {
                $returnVal = $persister->insert($entity);
                if ( ! is_null($returnVal)) {
                    // Persister returned a post-insert ID
                    $oid = spl_object_hash($entity);
                    $idField = $class->getSingleIdentifierFieldName();
                    $class->getReflectionProperty($idField)->setValue($entity, $returnVal);
                    $this->_entityIdentifiers[$oid] = array($returnVal);
                    $this->_entityStates[$oid] = self::STATE_MANAGED;
                    $this->_originalEntityData[$oid][$idField] = $returnVal;
                    $this->addToIdentityMap($entity);
                }
            }
        }
    }

    private function _executeCollectionUpdate($collectionToUpdate)
    {
        //...
    }

    /**
     * Executes all entity updates for entities of the specified type.
     *
     * @param Doctrine\ORM\Mapping\ClassMetadata $class
     */
    private function _executeUpdates($class)
    {
        $className = $class->getClassName();
        $persister = $this->getEntityPersister($className);
        foreach ($this->_dirtyEntities as $entity) {
            if (get_class($entity) == $className) {
                $persister->update($entity);
            }
        }
    }

    /**
     * Executes all entity deletions for entities of the specified type.
     *
     * @param Doctrine\ORM\Mapping\ClassMetadata $class
     */
    private function _executeDeletions($class)
    {
        $className = $class->getClassName();
        $persister = $this->getEntityPersister($className);
        foreach ($this->_deletedEntities as $entity) {
            if (get_class($entity) == $className) {
                $persister->delete($entity);
            }
        }
    }

    /**
     * Gets the commit order.
     *
     * @return array
     */
    private function _getCommitOrder(array $entityChangeSet = null)
    {        
        if (is_null($entityChangeSet)) {
            $entityChangeSet = array_merge(
                    $this->_newEntities,
                    $this->_dirtyEntities,
                    $this->_deletedEntities);
        }

        // TODO: We can cache computed commit orders in the metadata cache!
        // Check cache at this point here!
        
        // See if there are any new classes in the changeset, that are not in the
        // commit order graph yet (dont have a node).
        $newNodes = array();
        foreach ($entityChangeSet as $entity) {
            $className = get_class($entity);
            if ( ! $this->_commitOrderCalculator->hasNodeWithKey($className)) {
                $this->_commitOrderCalculator->addNodeWithItem(
                        $className, // index/key
                        $this->_em->getClassMetadata($className) // item
                        );
                $newNodes[] = $this->_commitOrderCalculator->getNodeForKey($className);
            }
        }

        // Calculate dependencies for new nodes
        foreach ($newNodes as $node) {
            $class = $node->getClass();
            foreach ($class->getAssociationMappings() as $assocMapping) {
                //TODO: should skip target classes that are not in the changeset.
                if ($assocMapping->isOwningSide()) {
                    $targetClass = $this->_em->getClassMetadata($assocMapping->getTargetEntityName());
                    $targetClassName = $targetClass->getClassName();
                    // if the target class does not yet have a node, create it
                    if ( ! $this->_commitOrderCalculator->hasNodeWithKey($targetClassName)) {
                        $this->_commitOrderCalculator->addNodeWithItem(
                                $targetClassName, // index/key
                                $targetClass // item
                                );
                    }
                    // add dependency
                    $otherNode = $this->_commitOrderCalculator->getNodeForKey($targetClassName);
                    $otherNode->before($node);
                }
            }
        }

        return $this->_commitOrderCalculator->getCommitOrder();
    }

    /**
     * Register a new entity.
     * 
     * @todo Rename to scheduleForInsert().
     */
    public function registerNew($entity)
    {
        $oid = spl_object_hash($entity);

        if (isset($this->_dirtyEntities[$oid])) {
            throw new Doctrine_Exception("Dirty object can't be registered as new.");
        }
        if (isset($this->_deletedEntities[$oid])) {
            throw new Doctrine_Exception("Removed object can't be registered as new.");
        }
        if (isset($this->_newEntities[$oid])) {
            throw new Doctrine_Exception("Object already registered as new. Can't register twice.");
        }

        $this->_newEntities[$oid] = $entity;
        if (isset($this->_entityIdentifiers[$oid])) {
            $this->addToIdentityMap($entity);
        }
    }

    /**
     * Checks whether an entity is registered as new on the unit of work.
     *
     * @param Doctrine\ORM\Entity $entity
     * @return boolean
     * @todo Rename to isScheduledForInsert().
     */
    public function isRegisteredNew($entity)
    {
        return isset($this->_newEntities[spl_object_hash($entity)]);
    }

    /**
     * Registers a dirty entity.
     *
     * @param Doctrine\ORM\Entity $entity
     * @todo Rename to scheduleForUpdate().
     */
    public function registerDirty($entity)
    {
        $oid = spl_object_hash($entity);
        if ( ! isset($this->_entityIdentifiers[$oid])) {
            throw new DoctrineException("Entity without identity "
                    . "can't be registered as dirty.");
        }
        if (isset($this->_deletedEntities[$oid])) {
            throw new DoctrineException("Removed object can't be registered as dirty.");
        }

        if ( ! isset($this->_dirtyEntities[$oid]) && ! isset($this->_newEntities[$oid])) {
            $this->_dirtyEntities[$oid] = $entity;
        }
    }

    /**
     * Checks whether an entity is registered as dirty in the unit of work.
     * Note: Is not very useful currently as dirty entities are only registered
     * at commit time.
     *
     * @param Doctrine_Entity $entity
     * @return boolean
     * @todo Rename to isScheduledForUpdate().
     */
    public function isRegisteredDirty($entity)
    {
        return isset($this->_dirtyEntities[spl_object_hash($entity)]);
    }

    /**
     * Registers a deleted entity.
     * 
     * @todo Rename to scheduleForDelete().
     */
    public function registerDeleted($entity)
    {
        $oid = spl_object_hash($entity);
        if ( ! $this->isInIdentityMap($entity)) {
            return;
        }

        $this->removeFromIdentityMap($entity);
        $className = get_class($entity);

        if (isset($this->_newEntities[$oid])) {
            unset($this->_newEntities[$oid]);
            return; // entity has not been persisted yet, so nothing more to do.
        }

        if (isset($this->_dirtyEntities[$oid])) {
            unset($this->_dirtyEntities[$oid]);
        }
        if ( ! isset($this->_deletedEntities[$oid])) {
            $this->_deletedEntities[$oid] = $entity;
        }
    }

    /**
     * Checks whether an entity is registered as removed/deleted with the unit
     * of work.
     *
     * @param Doctrine\ORM\Entity $entity
     * @return boolean
     * @todo Rename to isScheduledForDelete().
     */
    public function isRegisteredRemoved($entity)
    {
        return isset($this->_deletedEntities[spl_object_hash($entity)]);
    }

    /**
     * Detaches an entity from the persistence management. It's persistence will
     * no longer be managed by Doctrine.
     *
     * @param integer $oid                  object identifier
     * @return boolean                      whether ot not the operation was successful
     */
    public function detach($entity)
    {
        $oid = spl_object_hash($entity);
        $this->removeFromIdentityMap($entity);
        unset($this->_newEntities[$oid], $this->_dirtyEntities[$oid],
                $this->_deletedEntities[$oid], $this->_entityIdentifiers[$oid],
                $this->_entityStates[$oid]);
    }

    /**
     * Enter description here...
     *
     * @param Doctrine\ORM\Entity $entity
     * @return unknown
     * @todo Rename to isScheduled()
     */
    public function isEntityRegistered($entity)
    {
        $oid = spl_object_hash($entity);
        return isset($this->_newEntities[$oid]) ||
                isset($this->_dirtyEntities[$oid]) ||
                isset($this->_deletedEntities[$oid]);
    }

    /**
     * Detaches all currently managed entities.
     * Alternatively, if an entity class name is given, all entities of that type
     * (or subtypes) are detached. Don't forget that entities are registered in
     * the identity map with the name of the root entity class. So calling detachAll()
     * with a class name that is not the name of a root entity has no effect.
     *
     * @return integer   The number of detached entities.
     */
    public function detachAll($entityName = null)
    {
        //TODO: what do do with new/dirty/removed lists?
        $numDetached = 0;
        if ($entityName !== null && isset($this->_identityMap[$entityName])) {
            $numDetached = count($this->_identityMap[$entityName]);
            foreach ($this->_identityMap[$entityName] as $entity) {
                $this->detach($entity);
            }
            $this->_identityMap[$entityName] = array();
        } else {
            $numDetached = count($this->_identityMap);
            $this->_identityMap = array();
            $this->_newEntities = array();
            $this->_dirtyEntities = array();
            $this->_deletedEntities = array();
        }

        return $numDetached;
    }

    /**
     * Registers an entity in the identity map.
     * Note that entities in a hierarchy are registered with the class name of
     * the root entity.
     *
     * @param Doctrine\ORM\Entity $entity  The entity to register.
     * @return boolean  TRUE if the registration was successful, FALSE if the identity of
     *                  the entity in question is already managed.
     */
    public function addToIdentityMap($entity)
    {
        $classMetadata = $this->_em->getClassMetadata(get_class($entity));
        $idHash = $this->getIdentifierHash($this->_entityIdentifiers[spl_object_hash($entity)]);
        if ($idHash === '') {
            throw new DoctrineException("Entity with oid '" . spl_object_hash($entity)
                    . "' has no identity and therefore can't be added to the identity map.");
        }
        $className = $classMetadata->getRootClassName();
        if (isset($this->_identityMap[$className][$idHash])) {
            return false;
        }
        $this->_identityMap[$className][$idHash] = $entity;
        return true;
    }

    /**
     * Gets the state of an entity within the current unit of work.
     *
     * @param Doctrine\ORM\Entity $entity
     * @return int
     */
    public function getEntityState($entity)
    {
        $oid = spl_object_hash($entity);
        if ( ! isset($this->_entityStates[$oid])) {
            if (isset($this->_entityIdentifiers[$oid])) {
                $this->_entityStates[$oid] = self::STATE_DETACHED;
            } else {
                $this->_entityStates[$oid] = self::STATE_NEW;
            }
        }
        return $this->_entityStates[$oid];
    }

    /**
     * Removes an entity from the identity map. This effectively detaches the
     * entity from the persistence management of Doctrine.
     *
     * @param Doctrine\ORM\Entity $entity
     * @return boolean
     */
    public function removeFromIdentityMap($entity)
    {
        $oid = spl_object_hash($entity);
        $classMetadata = $this->_em->getClassMetadata(get_class($entity));
        $idHash = $this->getIdentifierHash($this->_entityIdentifiers[$oid]);
        if ($idHash === '') {
            throw new DoctrineException("Entity with oid '" . spl_object_hash($entity)
                    . "' has no identity and therefore can't be removed from the identity map.");
        }
        $className = $classMetadata->getRootClassName();
        if (isset($this->_identityMap[$className][$idHash])) {
            unset($this->_identityMap[$className][$idHash]);
            $this->_entityStates[$oid] = self::STATE_DETACHED;
            return true;
        }

        return false;
    }

    /**
     * Gets an entity in the identity map by its identifier hash.
     *
     * @param string $idHash
     * @param string $rootClassName
     * @return Doctrine\ORM\Entity
     */
    public function getByIdHash($idHash, $rootClassName)
    {
        return $this->_identityMap[$rootClassName][$idHash];
    }

    /**
     * Tries to get an entity by its identifier hash. If no entity is found for
     * the given hash, FALSE is returned.
     *
     * @param string $idHash
     * @param string $rootClassName
     * @return mixed The found entity or FALSE.
     */
    public function tryGetByIdHash($idHash, $rootClassName)
    {
        if ($this->containsIdHash($idHash, $rootClassName)) {
            return $this->getByIdHash($idHash, $rootClassName);
        }
        return false;
    }

    /**
     * Gets the identifier hash for a set of identifier values.
     * The hash is just a concatenation of the identifier values.
     * The identifiers are concatenated with a space.
     * 
     * Note that this method always returns a string. If the given array is
     * empty, an empty string is returned.
     *
     * @param array $id
     * @return string  The hash.
     */
    public function getIdentifierHash(array $id)
    {
        return implode(' ', $id);
    }

    /**
     * Checks whether an entity is registered in the identity map of the
     * UnitOfWork.
     *
     * @param object $entity
     * @return boolean
     */
    public function isInIdentityMap($entity)
    {
        $oid = spl_object_hash($entity);
        if ( ! isset($this->_entityIdentifiers[$oid])) {
            return false;
        }
        $classMetadata = $this->_em->getClassMetadata(get_class($entity));
        $idHash = $this->getIdentifierHash($this->_entityIdentifiers[$oid]);
        if ($idHash === '') {
            return false;
        }
        
        return isset($this->_identityMap
                [$classMetadata->getRootClassName()]
                [$idHash]
                );
    }

    /**
     * Checks whether an identifier hash exists in the identity map.
     *
     * @param string $idHash
     * @param string $rootClassName
     * @return boolean
     */
    public function containsIdHash($idHash, $rootClassName)
    {
        return isset($this->_identityMap[$rootClassName][$idHash]);
    }

    /**
     * Saves an entity as part of the current unit of work.
     *
     * @param object $entity The entity to save.
     */
    public function save($entity)
    {
        $insertNow = array();
        $visited = array();
        $this->_doSave($entity, $visited, $insertNow);
        if ( ! empty($insertNow)) {
            // We have no choice. This means that there are new entities
            // with a post-insert ID generation strategy.
            $this->computeChangeSets($insertNow);
            $commitOrder = $this->_getCommitOrder($insertNow);
            foreach ($commitOrder as $class) {
                $this->_executeInserts($class);
            }
            // remove them from _newEntities and _entityChangeSets
            $this->_newEntities = array_diff_key($this->_newEntities, $insertNow);
            $this->_entityChangeSets = array_diff_key($this->_entityChangeSets, $insertNow);
        }
    }

    /**
     * Saves an entity as part of the current unit of work.
     * This method is internally called during save() cascades as it tracks
     * the already visited entities to prevent infinite recursions.
     *
     * @param object $entity The entity to save.
     * @param array $visited The already visited entities.
     * @param array $insertNow The entities that must be immediately inserted because of
     *                         post-insert ID generation.
     */
    private function _doSave($entity, array &$visited, array &$insertNow)
    {
        $oid = spl_object_hash($entity);
        if (isset($visited[$oid])) {
            return; // Prevent infinite recursion
        }

        $visited[$oid] = $entity; // mark visited

        $class = $this->_em->getClassMetadata(get_class($entity));
        switch ($this->getEntityState($entity)) {
            case self::STATE_MANAGED:
                // nothing to do, except if automatic dirty checking is disabled
                if ( ! $this->_em->getConfiguration()->getAutomaticDirtyChecking()) {
                    $this->scheduleForDirtyCheck($entity);
                }
                break;
            case self::STATE_NEW:
                $idGen = $this->_em->getIdGenerator($class->getClassName());
                if ($idGen->isPostInsertGenerator()) {
                    $insertNow[$oid] = $entity;
                } else {
                    $idValue = $idGen->generate($entity);
                    $this->_entityStates[$oid] = self::STATE_MANAGED;
                    if ( ! $idGen instanceof \Doctrine\ORM\Id\Assigned) {
                        $this->_entityIdentifiers[$oid] = array($idValue);
                        $class->getSingleIdReflectionProperty()->setValue($entity, $idValue);
                    } else {
                        $this->_entityIdentifiers[$oid] = $idValue;
                    }
                }
                $this->registerNew($entity);
                break;
            case self::STATE_DETACHED:
                //exception?
                throw new Doctrine_Exception("Behavior of save() for a detached entity "
                        . "is not yet defined.");
            case self::STATE_DELETED:
                // entity becomes managed again
                if ($this->isRegisteredRemoved($entity)) {
                    //TODO: better a method for this?
                    unset($this->_deletedEntities[$oid]);
                } else {
                    //FIXME: There's more to think of here...
                    $this->registerNew($entity);
                }
                break;
            default:
                //TODO: throw UnitOfWorkException::invalidEntityState()
                throw new Doctrine_Exception("Encountered invalid entity state.");
        }
        $this->_cascadeSave($entity, $visited, $insertNow);
    }

    /**
     * Deletes an entity as part of the current unit of work.
     *
     * @param object $entity
     */
    public function delete($entity)
    {
        $visited = array();
        $this->_doDelete($entity, $visited);
    }

    /**
     * Deletes an entity as part of the current unit of work.
     * This method is internally called during delete() cascades as it tracks
     * the already visited entities to prevent infinite recursions.
     *
     * @param object $entity
     * @param array $visited
     */
    private function _doDelete($entity, array &$visited)
    {
        $oid = spl_object_hash($entity);
        if (isset($visited[$oid])) {
            return; // Prevent infinite recursion
        }

        $visited[$oid] = $entity; // mark visited

        switch ($this->getEntityState($entity)) {
            case self::STATE_NEW:
            case self::STATE_DELETED:
                // nothing to do
                break;
            case self::STATE_MANAGED:
                $this->registerDeleted($entity);
                break;
            case self::STATE_DETACHED:
                throw new DoctrineException("A detached entity can't be deleted.");
            default:
                throw new DoctrineException("Encountered invalid entity state.");
        }
        $this->_cascadeDelete($entity, $visited);
    }

    /**
     * Cascades the save operation to associated entities.
     *
     * @param object $entity
     * @param array $visited
     */
    private function _cascadeSave($entity, array &$visited, array &$insertNow)
    {
        $class = $this->_em->getClassMetadata(get_class($entity));
        foreach ($class->getAssociationMappings() as $assocMapping) {
            if ( ! $assocMapping->isCascadeSave()) {
                continue;
            }
            $relatedEntities = $class->getReflectionProperty($assocMapping->getSourceFieldName())
                    ->getValue($entity);
            if (($relatedEntities instanceof \Doctrine\Common\Collections\Collection || is_array($relatedEntities))
                    && count($relatedEntities) > 0) {
                foreach ($relatedEntities as $relatedEntity) {
                    $this->_doSave($relatedEntity, $visited, $insertNow);
                }
            } else if (is_object($relatedEntities)) {
                $this->_doSave($relatedEntities, $visited, $insertNow);
            }
        }
    }

    /**
     * Cascades the delete operation to associated entities.
     *
     * @param object $entity
     */
    private function _cascadeDelete($entity, array &$visited)
    {
        $class = $this->_em->getClassMetadata(get_class($entity));
        foreach ($class->getAssociationMappings() as $assocMapping) {
            if ( ! $assocMapping->isCascadeDelete()) {
                continue;
            }
            $relatedEntities = $class->getReflectionProperty($assocMapping->getSourceFieldName())
                    ->getValue($entity);
            if ($relatedEntities instanceof \Doctrine\Common\Collections\Collection &&
                    count($relatedEntities) > 0) {
                foreach ($relatedEntities as $relatedEntity) {
                    $this->_doDelete($relatedEntity, $visited);
                }
            } else if (is_object($relatedEntities)) {
                $this->_doDelete($relatedEntities, $visited);
            }
        }
    }

    /**
     * Gets the CommitOrderCalculator used by the UnitOfWork to order commits.
     *
     * @return Doctrine\ORM\Internal\CommitOrderCalculator
     */
    public function getCommitOrderCalculator()
    {
        return $this->_commitOrderCalculator;
    }

    /**
     * Closes the UnitOfWork.
     */
    public function close()
    {
        //...        
        $this->_commitOrderCalculator->clear();
    }
    
    public function scheduleCollectionUpdate(PersistentCollection $coll)
    {
        $this->_collectionUpdates[] = $coll;
    }
    
    public function isCollectionScheduledForUpdate(PersistentCollection $coll)
    {
        //...
    }
    
    public function scheduleCollectionDeletion(PersistentCollection $coll)
    {
        //TODO: if $coll is already scheduled for recreation ... what to do?
        // Just remove $coll from the scheduled recreations?
        $this->_collectionDeletions[] = $coll;
    }
    
    public function isCollectionScheduledForDeletion(PersistentCollection $coll)
    {
        //...
    }
    
    public function scheduleCollectionRecreation(PersistentCollection $coll)
    {
        $this->_collectionRecreations[] = $coll;
    }
    
    public function isCollectionScheduledForRecreation(PersistentCollection $coll)
    {
        //...
    }

    /**
     * Creates an entity. Used for reconstitution of entities during hydration.
     *
     * @param string $className  The name of the entity class.
     * @param array $data  The data for the entity.
     * @return object
     * @internal Performance-sensitive method.
     */
    public function createEntity($className, array $data, $query = null)
    {
        $className = $this->_inferCorrectClassName($data, $className);
        $classMetadata = $this->_em->getClassMetadata($className);

        $id = array();
        if ($classMetadata->isIdentifierComposite()) {
            $identifierFieldNames = $classMetadata->getIdentifier();
            foreach ($identifierFieldNames as $fieldName) {
                $id[] = $data[$fieldName];
            }
            $idHash = $this->getIdentifierHash($id);
        } else {
            $id = array($data[$classMetadata->getSingleIdentifierFieldName()]);
            $idHash = $id[0];
        }
        $entity = $this->tryGetByIdHash($idHash, $classMetadata->getRootClassName());
        if ($entity) {
            $oid = spl_object_hash($entity);
            $this->_mergeData($entity, $data, $classMetadata/*, $query->getHint('doctrine.refresh')*/);
            return $entity;
        } else {
            $entity = new $className;
            $oid = spl_object_hash($entity);
            $this->_mergeData($entity, $data, $classMetadata, true);
            $this->_entityIdentifiers[$oid] = $id;
            $this->addToIdentityMap($entity);
        }

        $this->_originalEntityData[$oid] = $data;

        return $entity;
    }

    /**
     * Merges the given data into the given entity, optionally overriding
     * local changes.
     *
     * @param object $entity
     * @param array $data
     * @param boolean $overrideLocalChanges
     */
    private function _mergeData($entity, array $data, $class, $overrideLocalChanges = false) {
        if ($overrideLocalChanges) {
            foreach ($data as $field => $value) {
                $class->getReflectionProperty($field)->setValue($entity, $value);
            }
        } else {
            $oid = spl_object_hash($entity);
            foreach ($data as $field => $value) {
                $currentValue = $class->getReflectionProperty($field)->getValue($entity);
                if ( ! isset($this->_originalEntityData[$oid][$field]) ||
                        $currentValue == $this->_originalEntityData[$oid][$field]) {
                    $class->getReflectionProperty($field)->setValue($entity, $value);
                }
            }
        }
    }

    /**
     * Check the dataset for a discriminator column to determine the correct
     * class to instantiate. If no discriminator column is found, the given
     * classname will be returned.
     *
     * @param array $data
     * @param string $className
     * @return string The name of the class to instantiate.
     */
    private function _inferCorrectClassName(array $data, $className)
    {
        $class = $this->_em->getClassMetadata($className);
        $discCol = $class->getDiscriminatorColumn();
        if ( ! $discCol) {
            return $className;
        }
        $discMap = $class->getDiscriminatorMap();
        if (isset($data[$discCol['name']], $discMap[$data[$discCol['name']]])) {
            return $discMap[$data[$discCol['name']]];
        } else {
            return $className;
        }
    }

    /**
     * Gets the identity map of the UnitOfWork.
     *
     * @return array
     */
    public function getIdentityMap()
    {
        return $this->_identityMap;
    }

    /**
     * Gets the original data of an entity. The original data is the data that was
     * present at the time the entity was reconstituted from the database.
     *
     * @param object $entity
     * @return array
     */
    public function getOriginalEntityData($entity)
    {
        $oid = spl_object_hash($entity);
        if (isset($this->_originalEntityData[$oid])) {
            return $this->_originalEntityData[$oid];
        }
        return array();
    }

    /**
     * INTERNAL:
     * For hydration purposes only.
     *
     * Sets a property of the original data array of an entity.
     *
     * @param string $oid
     * @param string $property
     * @param mixed $value
     */
    public function setOriginalEntityProperty($oid, $property, $value)
    {
        $this->_originalEntityData[$oid][$property] = $value;
    }

    /**
     * Gets the identifier of an entity.
     * The returned value is always an array of identifier values. If the entity
     * has a composite primary key then the identifier values are in the same
     * order as the identifier field names as returned by ClassMetadata#getIdentifierFieldNames().
     *
     * @param object $entity
     * @return array The identifier values.
     */
    public function getEntityIdentifier($entity)
    {
        return $this->_entityIdentifiers[spl_object_hash($entity)];
    }

    /**
     *
     */
    public function tryGetById($id, $rootClassName)
    {
        $idHash = $this->getIdentifierHash((array)$id);
        if (isset($this->_identityMap[$rootClassName][$idHash])) {
            return $this->_identityMap[$rootClassName][$idHash];
        }
        return false;
    }

    public function scheduleForDirtyCheck($entity)
    {
        $rootClassName = $this->_em->getClassMetadata(get_class($entity))->getRootClassName();
        $this->_scheduledForDirtyCheck[$rootClassName] = $entity;
    }

    /**
     * Calculates the size of the UnitOfWork. The size of the UnitOfWork is the
     * number of entities in the identity map.
     *
     * @return integer
     */
    public function size()
    {
        $count = 0;
        foreach ($this->_identityMap as $entitySet) $count += count($entitySet);
        return $count;
    }

    /**
     * Gets the EntityPersister for an Entity.
     *
     * This is usually not of interest for users, mainly for internal use.
     *
     * @param string $entityName  The name of the Entity.
     * @return Doctrine\ORM\Persister\AbstractEntityPersister
     */
    public function getEntityPersister($entityName)
    {
        if ( ! isset($this->_persisters[$entityName])) {
            $class = $this->_em->getClassMetadata($entityName);
            if ($class->isInheritanceTypeJoined()) {
                $persister = new \Doctrine\ORM\Persisters\JoinedSubclassPersister($this->_em, $class);
            } else {
                $persister = new \Doctrine\ORM\Persisters\StandardEntityPersister($this->_em, $class);
            }
            $this->_persisters[$entityName] = $persister;
        }
        return $this->_persisters[$entityName];
    }

    public function getCollectionPersister($association)
    {
        $type = get_class($association);
        if ( ! isset($this->_collectionPersisters[$type])) {
            if ($association instanceof \Doctrine\ORM\Mapping\OneToManyMapping) {
                $persister = new \Doctrine\ORM\Persisters\OneToManyPersister($this->_em);
            } else if ($association instanceof \Doctrine\ORM\Mapping\ManyToManyMapping) {
                $persister = new \Doctrine\ORM\Persisters\ManyToManyPersister($this->_em);
            }
            $this->_collectionPersisters[$type] = $persister;
        }
        return $this->_collectionPersisters[$type];
    }
}




