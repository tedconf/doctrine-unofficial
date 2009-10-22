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
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\ORM;

use Doctrine\Common\DoctrineException;

/**
 * Configuration container for all configuration options of Doctrine.
 * It combines all configuration options from DBAL & ORM.
 *
 * @author Roman Borschel <roman@code-factory.org>
 * @since 2.0
 * @internal When adding a new configuration option just write a getter/setter
 * pair and add the option to the _attributes array with a proper default value.
 */
class Configuration extends \Doctrine\DBAL\Configuration
{    
    /**
     * Creates a new configuration that can be used for Doctrine.
     */
    public function __construct()
    {
        parent::__construct();
        
        $this->_attributes = array_merge($this->_attributes, array(
            'resultCacheImpl' => null,
            'queryCacheImpl' => null,
            'metadataCacheImpl' => null,
            'metadataDriverImpl' => null,
            'proxyDir' => null,
            'useCExtension' => false,
            'namedQueries' => array(),
            'namedNativeQueries' => array(),
            'autoGenerateProxyClasses' => true,
            'proxyNamespace' => null
        ));
        
        //TODO: Move this to client code to avoid unnecessary work when a different metadata
        // driver is used.
        $reader = new \Doctrine\Common\Annotations\AnnotationReader(new \Doctrine\Common\Cache\ArrayCache);
        $reader->setDefaultAnnotationNamespace('Doctrine\ORM\Mapping\\');
        $this->_attributes['metadataDriverImpl'] = new \Doctrine\ORM\Mapping\Driver\AnnotationDriver($reader);
    }

    /**
     * Sets the directory where Doctrine generates any necessary proxy class files.
     *
     * @param string $dir
     */
    public function setProxyDir($dir)
    {
        $this->_attributes['proxyDir'] = $dir;
    }

    /**
     * Gets the directory where Doctrine generates any necessary proxy class files.
     *
     * @return string
     */
    public function getProxyDir()
    {
        return $this->_attributes['proxyDir'];
    }
    
    /**
     * Gets a boolean flag that indicates whether proxy classes should always be regenerated
     * during each script execution.
     *
     * @return boolean
     */
    public function getAutoGenerateProxyClasses()
    {
        return $this->_attributes['autoGenerateProxyClasses'];
    }
    
    /**
     * Sets a boolean flag that indicates whether proxy classes should always be regenerated
     * during each script execution.
     *
     * @param boolean $bool
     */
    public function setAutoGenerateProxyClasses($bool)
    {
        $this->_attributes['autoGenerateProxyClasses'] = $bool;
    }
    
    public function getProxyNamespace()
    {
        return $this->_attributes['proxyNamespace'];
    }
    
    public function setProxyNamespace($ns)
    {
        $this->_attributes['proxyNamespace'] = $ns;
    }

    /**
     * Sets the cache driver implementation that is used for metadata caching.
     *
     * @param object $driverImpl
     */
    public function setMetadataDriverImpl($driverImpl)
    {
        $this->_attributes['metadataDriverImpl'] = $driverImpl;
    }

    /**
     * Gets the cache driver implementation that is used for the mapping metadata.
     *
     * @return object
     */
    public function getMetadataDriverImpl()
    {
        return $this->_attributes['metadataDriverImpl'];
    }

    /**
     * Gets the cache driver implementation that is used for query result caching.
     *
     * @return object
     */
    public function getResultCacheImpl()
    {
        return $this->_attributes['resultCacheImpl'];
    }

    /**
     * Sets the cache driver implementation that is used for query result caching.
     *
     * @param object $cacheImpl
     */
    public function setResultCacheImpl($cacheImpl)
    {
        $this->_attributes['resultCacheImpl'] = $cacheImpl;
    }

    /**
     * Gets the cache driver implementation that is used for the query cache (SQL cache).
     *
     * @return object
     */
    public function getQueryCacheImpl()
    {
        return $this->_attributes['queryCacheImpl'];
    }

    /**
     * Sets the cache driver implementation that is used for the query cache (SQL cache).
     *
     * @param object $cacheImpl
     */
    public function setQueryCacheImpl($cacheImpl)
    {
        $this->_attributes['queryCacheImpl'] = $cacheImpl;
    }

    /**
     * Gets the cache driver implementation that is used for metadata caching.
     *
     * @return object
     */
    public function getMetadataCacheImpl()
    {
        return $this->_attributes['metadataCacheImpl'];
    }

    /**
     * Sets the cache driver implementation that is used for metadata caching.
     *
     * @param object $cacheImpl
     */
    public function setMetadataCacheImpl($cacheImpl)
    {
        $this->_attributes['metadataCacheImpl'] = $cacheImpl;
    }
    
    /**
     * Gets a boolean flag that indicates whether Doctrine should make use of the
     * C extension.
     * 
     * @return boolean TRUE if Doctrine is configured to use the C extension, FALSE otherwise.
     */
    public function getUseCExtension()
    {
        return $this->_attributes['useCExtension'];
    }
    
    /**
     * Sets a boolean flag that indicates whether Doctrine should make use of the
     * C extension.
     * 
     * @param boolean $boolean Whether to make use of the C extension or not.
     */
    public function setUseCExtension($boolean)
    {
        $this->_attributes['useCExtension'] = $boolean;
    }
    
    /**
     * Adds a named DQL query to the configuration.
     * 
     * @param string $name The name of the query.
     * @param string $dql The DQL query string.
     */
    public function addNamedQuery($name, $dql)
    {
        $this->_attributes['namedQueries'][$name] = $dql;
    }
    
    /**
     * Gets a previously registered named DQL query.
     * 
     * @param string $name The name of the query.
     * @return string The DQL query.
     */
    public function getNamedQuery($name)
    {
        return $this->_attributes['namedQueries'][$name];
    }
    
    /**
     * Adds a named native query to the configuration.
     * 
     * @param string $name The name of the query.
     * @param string $sql The native SQL query string. 
     * @param ResultSetMapping $rsm The ResultSetMapping used for the results of the SQL query.
     */
    public function addNamedNativeQuery($name, $sql, Query\ResultSetMapping $rsm)
    {
        $this->_attributes['namedNativeQueries'][$name] = array($sql, $rsm);
    }
    
    /**
     * Gets the components of a previously registered named native query.
     * 
     * @param string $name The name of the query.
     * @return array A tuple with the first element being the SQL string and the second
     *          element being the ResultSetMapping.
     */
    public function getNamedNativeQuery($name)
    {
        return $this->_attributes['namedNativeQueries'][$name];
    }
    
    /**
     * Ensures that this Configuration instance contains settings that are
     * suitable for a production environment.
     * 
     * @throws DoctrineException If a configuration setting has a value that is not
     *                           suitable for a production environment.
     */
    public function ensureProductionSettings()
    {
        if ( ! $this->_attributes['queryCacheImpl']) {
            throw DoctrineException::queryCacheNotConfigured();
        }
        if ( ! $this->_attributes['metadataCacheImpl']) {
            throw DoctrineException::metadataCacheNotConfigured();
        }
        if ($this->_attributes['autoGenerateProxyClasses']) {
            throw DoctrineException::proxyClassesAlwaysRegenerating();
        }
    }
}