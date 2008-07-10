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

#namespace Doctrine::ORM;

#use Doctrine::Common::Configuration;
#use Doctrine::Common::EventManager;
#use Doctrine::Common::NullObject;
#use Doctrine::DBAL::Connections::Connection;
#use Doctrine::ORM::Exceptions::EntityManagerException;
#use Doctrine::ORM::Internal::UnitOfWork;
#use Doctrine::ORM::Mapping::ClassMetadata;


/**
 * The EntityManager is the central access point to ORM functionality.
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.phpdoctrine.org
 * @since       2.0
 * @version     $Revision$
 * @author      Roman Borschel <roman@code-factory.org>
 * @todo package:orm
 */
class Doctrine_EntityManager
{
    /**
     * The unique name of the EntityManager. The name is used to bind entity classes
     * to certain EntityManagers.
     *
     * @var string
     */
    private $_name;
    
    /**
     * The used Configuration.
     *
     * @var Configuration
     */
    private $_config;
    
    /**
     * The database connection used by the EntityManager.
     *
     * @var Doctrine_Connection
     */
    private $_conn;
    
    /**
     * Flush modes enumeration.
     */
    private static $_flushModes = array(
            // auto: Flush occurs automatically after each operation that issues database
            // queries. No operations are queued.
            'auto',
            // commit: Flush occurs automatically at transaction commit.
            'commit',
            // manual: Flush occurs never automatically.
            'manual'
    );
    
    /**
     * The metadata factory, used to retrieve the metadata of entity classes.
     *
     * @var Doctrine_ClassMetadata_Factory
     */
    private $_metadataFactory;
    
    /**
     * The EntityPersister instances.
     *
     * @var array
     */
    private $_persisters = array();
    
    /**
     * The EntityRepository instances.
     *
     * @var array
     */
    private $_repositories = array();
    
    /**
     * The currently used flush mode. Defaults to 'commit'.
     *
     * @var string
     */
    private $_flushMode = 'commit';
    
    /**
     * The unit of work used to coordinate object-level transactions.
     *
     * @var UnitOfWork
     */
    private $_unitOfWork;
    
    /**
     * The event manager that is the central point of the event system.
     *
     * @var EventManager
     */
    private $_eventManager;
    
    /**
     * Container that is used temporarily during hydration.
     *
     * @var array
     */
    private $_tmpEntityData = array();
    
    /**
     * Creates a new EntityManager that operates on the given database connection.
     *
     * @param Doctrine_Connection $conn
     * @param string $name
     */
    public function __construct(Doctrine_Connection $conn, $name = null)
    {
        $this->_conn = $conn;
        $this->_name = $name;
        $this->_metadataFactory = new Doctrine_ClassMetadata_Factory(
                $this, new Doctrine_ClassMetadata_CodeDriver());
        $this->_unitOfWork = new Doctrine_Connection_UnitOfWork($this);
        $this->_nullObject = Doctrine_Null::$INSTANCE;
    }
    
    /**
     * Gets the database connection object used by the EntityManager.
     *
     * @return Doctrine_Connection
     */
    public function getConnection()
    {
        return $this->_conn;
    }
    
    /**
     * Gets the metadata for a class. Alias for getClassMetadata().
     *
     * @return Doctrine_Metadata
     * @todo package:orm
     */
    public function getMetadata($className)
    {
        return $this->getClassMetadata($className);
    }
    
    /**
     * Gets the transaction object used by the EntityManager to manage
     * database transactions.
     * 
     * @return Doctrine::DBAL::Transaction
     */
    public function getTransaction()
    {
        return $this->_conn->getTransaction();
    }

    /**
     * Returns the metadata for a class.
     *
     * @return Doctrine_Metadata
     */
    public function getClassMetadata($className)
    {        
        return $this->_metadataFactory->getMetadataFor($className);
    }
    
    /**
     * Sets the driver that is used to obtain metadata mapping information
     * about Entities.
     *
     * @param $driver  The driver to use.
     */
    public function setClassMetadataDriver($driver)
    {
        $this->_metadataFactory->setDriver($driver);
    }
    
    /**
     * Creates a new Doctrine_Query object that operates on this connection.
     * 
     * @param string  The DQL string.
     * @return Doctrine::ORM::Query
     * @todo package:orm
     */
    public function createQuery($dql = "")
    {
        $query = new Doctrine_Query($this);
        if ( ! empty($dql)) {
            $query->setDql($dql);
        }
        
        return $query;
    }
    
    /**
     * Gets the EntityPersister for an Entity.
     * 
     * This is usually not of interest for users, mainly for internal use.
     *
     * @param string $entityName  The name of the Entity.
     * @return Doctrine::ORM::Internal::EntityPersister
     */
    public function getEntityPersister($entityName)
    {
        if ( ! isset($this->_persisters[$entityName])) {
            $class = $this->getClassMetadata($entityName);
            if ($class->getInheritanceType() == Doctrine::INHERITANCE_TYPE_JOINED) {
                $persister = new Doctrine_EntityPersister_JoinedSubclass($this, $class);
            } else {
                $persister = new Doctrine_EntityPersister_Standard($this, $class);
            }
            $this->_persisters[$entityName] = $persister;
        }
        return $this->_persisters[$entityName];
    }
    
    /**
     * Detaches an entity from the manager. It's lifecycle is no longer managed.
     *
     * @param Doctrine_Entity $entity
     * @return unknown
     */
    public function detach(Doctrine_Entity $entity)
    {
        return $this->_unitOfWork->unregisterIdentity($entity);
    }
    
    /**
     * Creates a query with the specified name.
     *
     * @todo Implementation.
     * @throws SomeException  If there is no query registered with the given name.
     */
    public function createNamedQuery($name)
    {
        //...
    }
    
    /**
     * @todo Implementation.
     */
    public function createNativeQuery($sql = "")
    {
        //...
    }
    
    /**
     * @todo Implementation.
     */
    public function createNamedNativeQuery($name)
    {
        //...
    }
    
    /**
     * @todo Implementation.
     */
    public function createCriteria()
    {
        //...
    }
    
    /**
     * Flushes all changes to objects that have been queued up to now to the database.
     *
     * @todo package:orm
     */
    public function flush()
    {
        $this->_unitOfWork->flush();
    }
    
    /**
     * Finds an Entity by its identifier.
     * This is just a convenient shortcut for getRepository()->find().
     *
     * @param string $entityName
     * @param mixed $identifier
     * @return Doctrine::ORM::Entity
     */
    public function find($entityName, $identifier)
    {
        return $this->getRepository($entityName)->find($identifier);
    }
    
    /**
     * Sets the flush mode.
     *
     * @param string $flushMode
     */
    public function setFlushMode($flushMode)
    {
        if ( ! in_array($flushMode, self::$_flushModes)) {
            throw Doctrine_EntityManager_Exception::invalidFlushMode();
        }
        $this->_flushMode = $flushMode;
    }
    
    /**
     * Gets the currently used flush mode.
     *
     * @return string
     */
    public function getFlushMode()
    {
        return $this->_flushMode;
    }
    
    /**
     * Clears the persistence context, detaching all entities.
     *
     * @return void
     * @todo package:orm
     */
    public function clear($entityName = null)
    {
        if ($entityName === null) {
            $this->_unitOfWork->detachAll();
        } else {
            //... 
        }
    }
    
    /**
     * Releases the EntityManager.
     *
     */
    public function close()
    {
        //Doctrine_EntityManagerFactory::releaseManager($this);
    }
    
    /**
     * getResultCacheDriver
     *
     * @return Doctrine_Cache_Interface
     * @todo package:orm
     */
    public function getResultCacheDriver()
    {
        if ( ! $this->getAttribute(Doctrine::ATTR_RESULT_CACHE)) {
            throw new Doctrine_Exception('Result Cache driver not initialized.');
        }
        
        return $this->getAttribute(Doctrine::ATTR_RESULT_CACHE);
    }

    /**
     * getQueryCacheDriver
     *
     * @return Doctrine_Cache_Interface
     * @todo package:orm
     */
    public function getQueryCacheDriver()
    {
        if ( ! $this->getAttribute(Doctrine::ATTR_QUERY_CACHE)) {
            throw new Doctrine_Exception('Query Cache driver not initialized.');
        }
        
        return $this->getAttribute(Doctrine::ATTR_QUERY_CACHE);
    }
    
    /**
     * Saves the given entity, persisting it's state.
     */
    public function save(Doctrine_Entity $entity)
    {
        $this->_unitOfWork->save($entity);
    }
    
    /**
     * Removes the given entity from the persistent store.
     */
    public function delete(Doctrine_Entity $entity)
    {
        $this->_unitOfWork->delete($entity);
    }
    
    /**
     * Gets the repository for an Entity.
     *
     * @param string $entityName  The name of the Entity.
     * @return Doctrine::ORM::EntityRepository  The repository.
     */
    public function getRepository($entityName)
    {
        if (isset($this->_repositories[$entityName])) {
            return $this->_repositories[$entityName];
        }

        $metadata = $this->getClassMetadata($entityName);
        $customRepositoryClassName = $metadata->getCustomRepositoryClass();
        if ($customRepositoryClassName !== null) {
            $repository = new $customRepositoryClassName($entityName, $metadata);
        } else {
            $repository = new Doctrine_EntityRepository($entityName, $metadata);
        }
        $this->_repositories[$entityName] = $repository;

        return $repository;
    }
    
    /**
     * Creates an entity. Used for reconstitution as well as initial creation.
     *
     * @param string $className  The name of the entity class.
     * @param array $data  The data for the entity. 
     * @return Doctrine_Entity
     */
    public function createEntity($className, array $data)
    {
        $this->_tmpEntityData = $data;
        $className = $this->_inferCorrectClassName($data, $className);
        $classMetadata = $this->getClassMetadata($className);
        if ( ! empty($data)) {
            $identifierFieldNames = $classMetadata->getIdentifier();
            $isNew = false;
            foreach ($identifierFieldNames as $fieldName) {
                if ( ! isset($data[$fieldName])) {
                    // id field not found return new entity
                    $isNew = true;
                    break;
                }
                $id[] = $data[$fieldName];
            }
            
            if ($isNew) {
                $entity = new $className;
            } else {
                $idHash = $this->_unitOfWork->getIdentifierHash($id);
                if ($entity = $this->_unitOfWork->tryGetByIdHash($idHash,
                        $classMetadata->getRootClassName())) {
                    return $entity;
                } else {
                    $entity = new $className;
                    $this->_unitOfWork->registerIdentity($entity);
                }
            }
        } else {
            $entity = new $className;
        }
        
        /*if (count($data) < $classMetadata->getMappedColumnCount()) {
            $entity->_state(Doctrine_Entity::STATE_PROXY);
        } else {
            $entity->_state(Doctrine_Entity::STATE_CLEAN);
        }*/

        return $entity;
    }
    
    /**
     * INTERNAL:
     * For internal hydration purposes only.
     */
    public function _getTmpEntityData()
    {
        $data = $this->_tmpEntityData;
        $this->_tmpEntityData = array();
        return $data;
    }
    
    /**
     * Check the dataset for a discriminator column to determine the correct
     * class to instantiate. If no discriminator column is found, the given
     * classname will be returned.
     *
     * @return string The name of the class to instantiate.
     */
    private function _inferCorrectClassName(array $data, $className)
    {
        $class = $this->getClassMetadata($className);

        $discCol = $class->getInheritanceOption('discriminatorColumn');
        if ( ! $discCol) {
            return $className;
        }
        
        $discMap = $class->getInheritanceOption('discriminatorMap');
        
        if (isset($data[$discCol], $discMap[$data[$discCol]])) {
            return $discMap[$data[$discCol]];
        } else {
            return $className;
        }
    }
    
    /**
     * Gets the UnitOfWork used by the EntityManager.
     *
     * @return UnitOfWork
     */
    /*public function getUnitOfWork()
    {
        return $this->_unitOfWork;
    }*/
    
    /**
     * Gets the EventManager used by the EntityManager.
     *
     * @return EventManager
     */
    public function getEventManager()
    {
        return $this->_eventManager;
    }
    
    /**
     * Sets the EventManager used by the EntityManager.
     *
     * @param Doctrine_EventManager $eventManager
     */
    public function setEventManager(Doctrine_EventManager $eventManager)
    {
        $this->_eventManager = $eventManager;
    }
    
    /**
     * Sets the Configuration used by the EntityManager.
     *
     * @param Doctrine_Configuration $config
     */
    public function setConfiguration(Doctrine_Configuration $config)
    {
        $this->_config = $config;
    }
    
    /**
     * Gets the COnfiguration used by the EntityManager.
     *
     * @return Configuration
     */
    public function getConfiguration()
    {
        return $this->_config;
    }
    
}

?>