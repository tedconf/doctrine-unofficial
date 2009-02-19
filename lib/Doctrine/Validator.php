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

/**
 * This class is responsible for performing all validations on record properties
 *
 * @package     Doctrine
 * @subpackage  Validator
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.phpdoctrine.org
 * @since       1.0
 * @version     $Revision$
 * @author      Roman Borschel <roman@code-factory.org>
 * @author      Konsta Vesterinen <kvesteri@cc.hut.fi>
 */
class Doctrine_Validator extends Doctrine_Locator_Injectable
{
    /**
     * @var array $validators           an array of validator objects
     */
    private static $validators = array();

    /**
     * Get a validator instance for the passed $name
     *
     * @param  string   $name  Name of the validator or the validator class name
     * @return Doctrine_Validator_Interface $validator
     */
    public static function getValidator($name)
    {
        if ( ! isset(self::$validators[$name])) {
            $class = 'Doctrine_Validator_' . ucwords(strtolower($name));
            if (class_exists($class)) {
                self::$validators[$name] = new $class;
            } else if (class_exists($name)) {
                self::$validators[$name] = new $name;
            } else {
                throw new Doctrine_Exception("Validator named '$name' not available.");
            }

        }
        return self::$validators[$name];
    }

    /**
     * Validates a given record and saves possible errors in Doctrine_Validator::$stack
     *
     * @param  Doctrine_Record $record
     * @return void
     */
    public function validateRecord(Doctrine_Record $record)
    {
        $table = $record->getTable();

        // if record is transient all fields will be validated
        // if record is persistent only the modified fields will be validated
        $fields = $record->exists() ? $record->getModified():$record->getData();
        foreach ($fields as $fieldName => $value) {
            $table->validateField($fieldName, $value, $record);
        }
    }

    /**
     * Validates the length of a field.
     *
     * @param  string  $value         Value to validate
     * @param  string  $type          Type of field being validated
     * @param  string  $maximumLength Maximum length allowed for the column
     * @return boolean $success       True/false for whether the value passed validation
     */
    public static function validateLength($value, $type, $maximumLength)
    {
        if ($type == 'timestamp' || $type == 'integer' || $type == 'enum') {
            return true;
        } else if ($type == 'array' || $type == 'object') {
            $length = strlen(serialize($value));
        } else if ($type == 'decimal' || $type == 'float') {
            $e = explode('.', $value);
            $length = strlen($e[0]);
        } else {
            $length = self::getStringLength($value);
        }
        if ($length > $maximumLength) {
            return false;
        }
        return true;
    }

    /**
     * Get length of passed string. Will use multibyte character functions if they exist
     *
     * @param string $string 
     * @return integer $length
     */
    public static function getStringLength($string)
    {
        if (function_exists('iconv_strlen')) {
            return iconv_strlen($string);
        } else if (function_exists('mb_strlen')) {
            return mb_strlen($string);
        } else {
            return strlen($string);
        }
    }

    /**
     * Whether or not errors exist on this validator
     *
     * @return boolean True/false for whether or not this validate instance has error
     */
    public function hasErrors()
    {
        return (count($this->stack) > 0);
    }

    /**
     * Validate the type of the passed variable
     *
     * @param  mixed  $var   Variable to validate
     * @param  string $type  Type of the variable expected
     * @return boolean
     */
     public static function isValidType($var, $type)
     {
         if ($var instanceof Doctrine_Expression) {
             return true;
         } else if ($var === null) {
             return true;
         } else if (is_object($var)) {
             return $type == 'object';
         }

         switch ($type) {
             case 'float':
             case 'double':
             case 'decimal':
                 return (string)$var == strval(floatval($var));
             case 'integer':
                 return (string)$var == strval(round(floatval($var)));
             case 'string':
                 return is_string($var) || is_numeric($var);
             case 'blob':
             case 'clob':
             case 'gzip':
                 return is_string($var);
             case 'array':
                 return is_array($var);
             case 'object':
                 return is_object($var);
             case 'boolean':
                 return is_bool($var) || (is_numeric($var) && ($var == 0 || $var == 1));
             case 'timestamp':
                 $validator = self::getValidator('timestamp');
                 return $validator->validate($var);
             case 'time':
                 $validator = self::getValidator('time');
                 return $validator->validate($var);
             case 'date':
                 $validator = self::getValidator('date');
                 return $validator->validate($var);
             case 'enum':
                 return is_string($var) || is_int($var);
             default:
                 return false;
         }
     }
}