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

namespace Doctrine\ORM\Mapping\Driver;

use Doctrine\ORM\Mapping\ClassMetadata;

if ( ! class_exists('sfYaml', false)) {
    require_once __DIR__ . '/../../../../vendor/sfYaml/sfYaml.class.php';
    require_once __DIR__ . '/../../../../vendor/sfYaml/sfYamlDumper.class.php';
    require_once __DIR__ . '/../../../../vendor/sfYaml/sfYamlInline.class.php';
    require_once __DIR__ . '/../../../../vendor/sfYaml/sfYamlParser.class.php';
}

/**
 * The YamlDriver reads the mapping metadata from yaml schema files.
 *
 * @author Jonathan H. Wage <jonwage@gmail.com>
 * @since 2.0
 */
class YamlDriver extends AbstractFileDriver
{
    protected $_fileExtension = '.dcm.yml';

    public function loadMetadataForClass($className, ClassMetadata $metadata)
    {
        $element = $this->getElement($className);

        if ($element['type'] == 'entity') {

            // Evaluate root level properties
            if (isset($element['table'])) {
                $metadata->primaryTable['name'] = $element['table'];
            }
            if (isset($element['schema'])) {
                $metadata->primaryTable['schema'] = $element['schema'];
            }
            if (isset($element['inheritanceType'])) {
                $metadata->setInheritanceType($element['inheritanceType']);
            }

            // Evaluate fields
            if (isset($element['fields'])) {
                foreach ($element['fields'] as $name => $fieldMapping) {
                    $mapping = array(
                        'fieldName' => $name,
                        'type' => $fieldMapping['type']
                    );
                    if (isset($fieldMapping['column'])) {
                        $mapping['columnName'] = $fieldMapping['column'];
                    }
                    if (isset($fieldMapping['length'])) {
                        $mapping['length'] = $fieldMapping['length'];
                    }
                    $metadata->mapField($mapping);
                }
            }

            // Evaluate identifier settings
            foreach ($element['id'] as $name => $idElement) {
                $mapping = array(
                    'id' => true,
                    'fieldName' => $name,
                    'type' => $idElement['type']
                );
                if (isset($idElement['column'])) {
                    $mapping['columnName'] = $idElement['column'];
                }
                $metadata->mapField($mapping);

                if (isset($idElement['generator'])) {
                    $metadata->setIdGeneratorType(constant('Doctrine\ORM\Mapping\ClassMetadata::GENERATOR_TYPE_'
                            . $idElement['generator']['strategy']));
                }
            }

            // Evaluate oneToOne relationships
            if (isset($element['oneToOne'])) {
                foreach ($element['oneToOne'] as $name => $oneToOneElement) {
                    $mapping = array(
                        'fieldName' => $name,
                        'targetEntity' => $oneToOneElement['targetEntity']
                    );
                    if (isset($oneToOneElement['mappedBy'])) {
                        $mapping['mappedBy'] = $oneToOneElement['mappedBy'];
                    } else {
                        $joinColumns = array();
                        if (isset($oneToOneElement['joinColumn'])) {
                            $joinColumns[] = $this->_getJoinColumnMapping($oneToOneElement['joinColumn']);
                        } else if (isset($oneToOneElement['joinColumns'])) {
                            foreach ($oneToOneElement['joinColumns'] as $name => $joinColumnElement) {
                                if (!isset($joinColumnElement['name'])) {
                                    $joinColumnElement['name'] = $name;
                                }
                                $joinColumns[] = $this->_getJoinColumnMapping($joinColumnElement);
                            }
                        } else {
                            throw MappingException::invalidMapping($mapping['fieldName']);
                        }
                        $mapping['joinColumns'] = $joinColumns;
                    }

                    if (isset($oneToOneElement['cascade'])) {
                        $mapping['cascade'] = $this->_getCascadeMappings($oneToOneElement['cascade']);
                    }

                    $metadata->mapOneToOne($mapping);
                }
            }

            // Evaluate oneToMany relationships
            if (isset($element['oneToMany'])) {
                foreach ($element['oneToMany'] as $name => $oneToManyElement) {
                    $mapping = array(
                        'fieldName' => $name,
                        'targetEntity' => $oneToManyElement['targetEntity'],
                        'mappedBy' => $oneToManyElement['mappedBy']
                    );
                    if (isset($oneToManyElement['cascade'])) {
                        $mapping['cascade'] = $this->_getCascadeMappings($oneToManyElement['cascade']);
                    }
                    $metadata->mapOneToMany($mapping);
                }
            }

            // Evaluate manyToOne relationships
            if (isset($element['manyToOne'])) {
                foreach ($element['manyToOne'] as $name => $manyToOneElement) {
                    $mapping = array(
                        'fieldName' => $name, 
                        'targetEntity' => $manyToOneElement['targetEntity']
                    );
                    $joinColumns = array();
                    if (isset($manyToOneElement['joinColumn'])) {
                        $joinColumns[] = $this->_getJoinColumnMapping($manyToOneElement['joinColumn']);
                    } else if (isset($manyToOneElement['joinColumns'])) {
                        foreach ($manyToOneElement['joinColumns'] as $name => $joinColumnElement) {
                            if (!isset($joinColumnElement['name'])) {
                                $joinColumnElement['name'] = $name;
                            }
                            $joinColumns[] = $this->_getJoinColumnMapping($joinColumnElement);
                        }
                    } else {
                        throw MappingException::invalidMapping($mapping['fieldName']);
                    }
                    $mapping['joinColumns'] = $joinColumns;
                    if (isset($manyToOneElement['cascade'])) {
                        $mapping['cascade'] = $this->_getCascadeMappings($manyToOneElement['cascade']);
                    }
                    $metadata->mapManyToOne($mapping);
                }
            }

            // Evaluate manyToMany relationships
            if (isset($element['manyToMany'])) {
                foreach ($element['manyToMany'] as $name => $manyToManyElement) {
                    $mapping = array(
                        'fieldName' => $name,
                        'targetEntity' => $manyToManyElement['targetEntity']
                    );
                    
                    if (isset($manyToManyElement['mappedBy'])) {
                        $mapping['mappedBy'] = $manyToManyElement['mappedBy'];
                    } else if (isset($manyToManyElement['joinTable'])) {
                        $joinTableElement = $manyToManyElement['joinTable'];
                        $joinTable = array(
                            'name' => $joinTableElement['name']
                        );
                        if (isset($joinTableElement['schema'])) {
                            $joinTable['schema'] = $joinTableElement['schema'];
                        }
                        foreach ($joinTableElement['joinColumns'] as $name => $joinColumnElement) {
                            if (!isset($joinColumnElement['name'])) {
                                $joinColumnElement['name'] = $name;
                            }
                            $joinTable['joinColumns'][] = $this->_getJoinColumnMapping($joinColumnElement);
                        }
                        foreach ($joinTableElement['inverseJoinColumns'] as $name => $joinColumnElement) {
                            if (!isset($joinColumnElement['name'])) {
                                $joinColumnElement['name'] = $name;
                            }
                            $joinTable['inverseJoinColumns'][] = $this->_getJoinColumnMapping($joinColumnElement);
                        }
                        $mapping['joinTable'] = $joinTable;
                    } else {
                        throw MappingException::invalidMapping($mapping['fieldName']);
                    }
                    
                    if (isset($manyToManyElement['cascade'])) {
                        $mapping['cascade'] = $this->_getCascadeMappings($manyToManyElement['cascade']);
                    }

                    $metadata->mapManyToMany($mapping);
                }
            }

        } else if ($element['type'] == 'mapped-superclass') {
            throw MappingException::notImplemented('Mapped superclasses are not yet supported.');
        }
    }

    /**
     * Constructs a joinColumn mapping array based on the information
     * found in the given join column element.
     * 
     * @param $joinColumnElement The array join column element
     * @return array The mapping array.
     */
    private function _getJoinColumnMapping($joinColumnElement)
    {
        $joinColumn = array(
            'name' => $joinColumnElement['name'],
            'referencedColumnName' => $joinColumnElement['referencedColumnName']
        );
        if (isset($joinColumnElement['unique'])) {
            $joinColumn['unique'] = (bool) $joinColumnElement['unique'];
        }
        if (isset($joinColumnElement['nullable'])) {
            $joinColumn['nullable'] = (bool) $joinColumnElement['nullable'];
        }
        if (isset($joinColumnElement['onDelete'])) {
            $joinColumn['onDelete'] = $joinColumnElement['onDelete'];
        }
        if (isset($joinColumnElement['onUpdate'])) {
            $joinColumn['onUpdate'] = $joinColumnElement['onUpdate'];
        }
        
        return $joinColumn;
    }
    
    /**
     * Gathers a list of cascade options found in the given cascade element.
     * 
     * @param $cascadeElement The cascade element.
     * @return array The list of cascade options.
     */
    private function _getCascadeMappings($cascadeElement)
    {
        $cascades = array();
        if (isset($cascadeElement['cascadeSave'])) {
            $cascades[] = 'save';
        }
        if (isset($cascadeElement['cascadeDelete'])) {
            $cascades[] = 'delete';
        }
        if (isset($cascadeElement['cascadeMerge'])) {
            $cascades[] = 'merge';
        }
        if (isset($cascadeElement['cascadeRefresh'])) {
            $cascades[] = 'refresh';
        }
        
        return $cascades;
    }

    /**
     * Loads a mapping file with the given name and returns a map
     * from class/entity names to their corresponding elements.
     * 
     * @param string $file The mapping file to load.
     * @return array
     */
    protected function _loadMappingFile($file)
    {
        return \sfYaml::load($file);
    }
}