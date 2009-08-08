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

namespace Doctrine\Common\Annotations;

/**
 * A simple parser for docblock annotations.
 * 
 * @license http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link    www.doctrine-project.org
 * @since   2.0
 * @version $Revision: 3938 $
 * @author  Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author  Jonathan Wage <jonwage@gmail.com>
 * @author  Roman Borschel <roman@code-factory.org>
 */
class Parser
{
    /** 
     * Tags that are stripped prior to parsing in order to reduce parsing overhead. 
     *
     * @var array
     */
    private static $_strippedInlineTags = array(
        "{@example", "{@id", "{@internal", "{@inheritdoc",
        "{@link", "{@source", "{@toc", "{@tutorial", "*/"
    );
    
    /**
     * The lexer.
     *
     * @var Doctrine\Common\Annotations\Lexer
     */
    private $_lexer;
    
    /**
     * Flag to control if the current Annotation is nested or not.
     *
     * @var boolean
     */
    private $_isNestedAnnotation = false;
    
    /**
     * Default namespace for Annotations.
     *
     * @var string
     */
    private $_defaultAnnotationNamespace = '';
    
    /**
     * Hashmap to store namespace aliases.
     *
     * @var array
     */
    private $_namespaceAliases = array();
    
    /**
     * Constructs a new AnnotationParser.
     *
     */
    public function __construct()
    {
        $this->_lexer = new Lexer;
    }
    
    /**
     * Sets the default namespace that is assumed for an annotation that does not
     * define a namespace prefix.
     * 
     * @param $defaultNamespace
     */
    public function setDefaultAnnotationNamespace($defaultNamespace)
    {
        $this->_defaultAnnotationNamespace = $defaultNamespace;
    }
    
    /**
     * Sets an alias for an annotation namespace.
     * 
     * @param $namespace
     * @param $alias
     */
    public function setAnnotationNamespaceAlias($namespace, $alias)
    {
        $this->_namespaceAliases[$alias] = $namespace;
    }

    /**
     * Parses the given docblock string for annotations.
     * 
     * @param $docBlockString
     * @return array Array of Annotations. If no annotations are found, an empty array is returned.
     */
    public function parse($docBlockString)
    {
        // Strip out some known inline tags.
        $input = str_replace(self::$_strippedInlineTags, '', $docBlockString);
        
        // Cut of the beginning of the input until the first '@'.
        $input = substr($input, strpos($input, '@'));
        
        $this->_lexer->reset();
        $this->_lexer->setInput(trim($input, '* /'));
        $this->_lexer->moveNext();
        
        if ($this->_lexer->isNextToken('@')) {
            return $this->Annotations();
        }
        
        return array();
    }

    /**
     * Attempts to match the given token with the current lookahead token.
     * If they match, updates the lookahead token; otherwise raises a syntax error.
     *
     * @param int|string token type or value
     * @return bool True if tokens match; false otherwise.
     */
    public function match($token)
    {
        $key = (is_string($token)) ? 'value' : 'type';
        
        if ( ! ($this->_lexer->lookahead[$key] === $token)) {
            $token = (is_string($token)) ? $token : $token['value'];
            $this->syntaxError($token);
        }

        $this->_lexer->moveNext();
    }
    
    /**
     * Generates a new syntax error.
     * 
     * @param string $expected Expected string.
     * @param array $token Optional token.
     * @throws Exception
     */
    private function syntaxError($expected, $token = null)
    {
        if ($token === null) {
            $token = $this->_lexer->lookahead;
        }
        
        $message =  "Expected '{$expected}', got ";
        
        if ($this->_lexer->lookahead === null) {
            $message .= 'end of string.';
        } else {
            $message .= "'{$token['value']}'";
        }
        
        throw \Doctrine\Common\DoctrineException::syntaxError($message);
    }
    
    /**
     * Annotations ::= Annotation {[ "*" ]* [Annotation]}*
     *
     * @return array
     */
    public function Annotations()
    {
        $this->_isNestedAnnotation = false;
        
        $annotations = array();
        $annot = $this->Annotation();
        
        if ($annot !== false) {
            $annotations[get_class($annot)] = $annot;
        }
        
        while ($this->_lexer->lookahead !== null && $this->_lexer->lookahead['value'] == '@') {
            $this->_isNestedAnnotation = false;
                
            $annot = $this->Annotation();
                
            if ($annot !== false) {
                $annotations[get_class($annot)] = $annot;
            }
        }
        
        return $annotations;
    }

    /**
     * Annotation     ::= "@" AnnotationName [ "(" [Values] ")" ]
     * AnnotationName ::= QualifiedName | SimpleName
     * QualifiedName  ::= NameSpacePart "\" {NameSpacePart "\"}* SimpleName
     * NameSpacePart  ::= identifier
     * SimpleName     ::= identifier
     *
     * @return mixed False if it is not a valid Annotation; instance of Annotation subclass otherwise. 
     */
    public function Annotation()
    {
        $values = array();
        $nameParts = array();
        
        $this->match('@');
        $this->match(Lexer::T_IDENTIFIER);
        $nameParts[] = $this->_lexer->token['value'];
        
        while ($this->_lexer->isNextToken('\\')) {
            $this->match('\\');
            $this->match(Lexer::T_IDENTIFIER);
            $nameParts[] = $this->_lexer->token['value'];
        }

        // Effectively pick the name of class (append default NS if none, grab from NS alias, etc)
        if (count($nameParts) == 1) {
            $name = $this->_defaultAnnotationNamespace . $nameParts[0];
        } else if (count($nameParts) == 2 && isset($this->_namespaceAliases[$nameParts[0]])) {
            $name = $this->_namespaceAliases[$nameParts[0]] . $nameParts[1];
        } else {
            $name = implode('\\', $nameParts);
        }

        // If it really an annotation class?
        if (
            ! $this->_isNestedAnnotation && $this->_lexer->lookahead != null && 
            ! $this->_lexer->isNextToken('(') && 
            ! $this->_lexer->isNextToken('@') || 
            ! is_subclass_of($name, 'Doctrine\Common\Annotations\Annotation')
        ) {
            $this->_lexer->skipUntil('@');
            
            return false;
        }

        // Next will be nested
        $this->_isNestedAnnotation = true;

        if ($this->_lexer->isNextToken('(')) {
            $this->match('(');
            
            if ( ! $this->_lexer->isNextToken(')')) {
                $values = $this->Values();
            }
            
            $this->match(')');
        }

        return new $name($values);
    }

    /**
     * Values ::= Value {"," Value}*
     *
     * @return array
     */
    public function Values()
    {
        $values = array();
        $values[] = $this->Value();
        
        while ($this->_lexer->isNextToken(',')) {
            $this->match(',');
            $value = $this->Value();
            
            if ( ! is_array($value)) {
                $this->syntaxError('FieldAssignment', $value);
            }
            
            $values[] = $value;
        }
        
        foreach ($values as $k => $value) {
            if (is_array($value) && is_string(key($value))) {
                $key = key($value);
                $values[$key] = $value[$key];
            } else {
                $values['value'] = $value;
            }
            
            unset($values[$k]);
        }
        
        return $values;
    }

    /**
     * Value ::= PlainValue | FieldAssignment
     *
     * @return mixed
     */
    public function Value()
    {
        $peek = $this->_lexer->glimpse();
        
        if ($peek['value'] === '=') {
            return $this->FieldAssignment();
        }
        
        return $this->PlainValue();
    }

    /**
     * PlainValue ::= integer | string | float | Array | Annotation
     *
     * @return mixed
     */
    public function PlainValue()
    {
        if ($this->_lexer->lookahead['value'] == '{') {
            return $this->Arrayx();
        }
        
        if ($this->_lexer->lookahead['value'] == '@') {
            return $this->Annotation();
        }

        switch ($this->_lexer->lookahead['type']) {
            case Lexer::T_STRING:
                $this->match(Lexer::T_STRING);
                return $this->_lexer->token['value'];
                
            case Lexer::T_INTEGER:
                $this->match(Lexer::T_INTEGER);
                return $this->_lexer->token['value'];
                
            case Lexer::T_FLOAT:
                $this->match(Lexer::T_FLOAT);
                return $this->_lexer->token['value'];
                
            case Lexer::T_TRUE:
                $this->match(Lexer::T_TRUE);
                return true;
                
            case Lexer::T_FALSE:
                $this->match(Lexer::T_FALSE);
                return false;
                
            default:
                var_dump($this->_lexer->lookahead);
                throw new \Exception("Invalid value.");
        }
    }

    /**
     * FieldAssignment ::= FieldName "=" PlainValue
     * FieldName ::= identifier
     *
     * @return array
     */
    public function FieldAssignment()
    {
        $this->match(Lexer::T_IDENTIFIER);
        $fieldName = $this->_lexer->token['value'];
        $this->match('=');
        
        return array($fieldName => $this->PlainValue());
    }

    /**
     * Array ::= "{" ArrayEntry {"," ArrayEntry}* "}"
     *
     * @return array
     */
    public function Arrayx()
    {
        $array = $values = array();
        
        $this->match('{');
        $values[] = $this->ArrayEntry();
        
        while ($this->_lexer->isNextToken(',')) {
            $this->match(',');
            $values[] = $this->ArrayEntry();
        }
        
        $this->match('}');
        
        foreach ($values as $value) {
            $key = key($value);
            
            if (is_string($key)) {
                $array[$key] = $value[$key];
            } else {
                $array[] = $value[$key];
            }
        }
        
        return $array;
    }

    /**
     * ArrayEntry ::= Value | KeyValuePair
     * KeyValuePair ::= Key "=" Value
     * Key ::= string | integer
     *
     * @return array
     */
    public function ArrayEntry()
    {
        $peek = $this->_lexer->glimpse();
        
        if ($peek['value'] == '=') {
            if ($this->_lexer->lookahead['type'] === Lexer::T_INTEGER) {
                $this->match(Lexer::T_INTEGER);
            } else {
                $this->match(Lexer::T_STRING);
            }
            
            $key = $this->_lexer->token['value'];
            $this->match('=');
            
            return array($key => $this->Value());
        }
        
        return array($this->Value());
    }
}