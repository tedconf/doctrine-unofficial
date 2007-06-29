<?php

require_once("highlight.php");
require_once('Text/Wiki.php');

class DocTool
{
    private $_wiki;
    private $_toc;
    private $_options = array('max-level'    => 1,
                              'lang'         => 'en',
                              'default-lang' => 'en',
                              'one-page'     => false,
                              'section'      => null,
                              'clean-url'    => false,
                              'base-url'     => '');
    private $_lang = array();

    public function __construct($filename)
    {
        $this->_wiki = new Text_Wiki();
        $this->_wiki->disableRule('Wikilink');
        $this->_toc = new Sensei_Doc_Toc($filename);
    }
    
    public function getOption($option)
    {
        return $this->_options[$option];
    }
    
    public function setOption($option, $value)
    {
        switch ($option) {
            case 'max-level':
                if (!is_int($value)) {
                    throw new Exception('Value must be an integer.');
                }
                break;
                
            case 'one-page':
            case 'clean-url':
                if (!is_bool($value)) {
                    throw new Exception('Value must be a boolean.');
                }
                break;
            
            case 'locale':
            case 'base-url':
                if (!is_string($value)) {
                    throw new Exception('Value must be a string.');
                }
                break;
                
            case 'section':
                if (! $value instanceof Sensei_Doc_Section) {
                    throw new Exception('Value must be an instance of Sensei_Doc_Section.');
                }
                break;
                
            default:
                throw new Exception('Unknown option.');
        }
        
        $this->_options[$option] = $value;
    }
    
    public function addLanguage(array $translations, $lang)
    {
        $this->_lang[$lang] = $translations;
    }
    
	public function translate($string)
	{
	    $language = $this->getOption('lang');
	    
	    if (array_key_exists($language, $this->_lang)
	     && array_key_exists($string, $this->_lang[language])) {
	        return $this->_lang[$language][$string];
	    } else {
	        return $string;
	    }
	}

	public function renderToc($toc = null)
	{
	    if (!$toc) {
	        $toc = $this->_toc;
	    }
	    
	    $classes = array();
	    
	    if ($toc instanceof Sensei_Doc_Toc) {
	        
	        $class = '';
	        if ($this->getOption('one-page')) {
	            $class = ' class="one-page"';
	        }
	        
	        $classes[] = 'tree';
	        
	    } else {
	        
	        $isParent = false;
	        $section = $this->getOption('section');
	        
	        if ($section !== null) {
		        $current = $section;
		        do {
		            if ($current === $toc) {
		                $isParent = true;
		                break;
		            }
		        } while (($current = $current->getParent()) !== null);
	        }
	        
	        if (! $isParent) {
	            $classes[] = 'closed';
	        }
	    }
	    
	    $classes = implode(' ', $classes);
	    
	    if ($classes === '') {
	        echo "<ul>\n";
	    } else {
	        echo "<ul class=\"$classes\">\n";
	    }
	    
	    for ($i = 0; $i < $toc->count(); $i++) {
	        $child = $toc->getChild($i);
	        
	        if ($child === $this->getOption('section')) {
	            echo '<li class="current">'; 
	        } else {
	            echo '<li>';
	        }
	
	        echo '<a href="' . $this->makeUrl($child->getPath()) . '">';
	        echo $child->getIndex() . ' ' . $child->getName() . '</a>';
	        
	        if ($child->count() > 0) {
	            echo "\n";
	            $this->renderToc($child);
	        }
	
	        echo '</li>' . "\n";
	    }
	
	    echo '</ul>' . "\n";
	    
    }

    public function makeUrl($path)
    {
        $prefix = $this->getOption('base-url');
        
        if (!$this->getOption('one-page')) {
            if ($this->getOption('clean-url')) {
                $prefix .= 'chapter/';
            } else {
                $prefix .= '?chapter=';
            }
        } 
        
        $parts = explode(':', $path);
        $firstPath = array_slice($parts, 0, $this->getOption('max-level'));
        
        $href = $prefix . implode(':', $firstPath);
        
        $anchorName = $this->makeAnchor($path);
        if (!empty($anchorName)) {
            $href .= '#' . $anchorName;
        }
        
        return $href;
    }
    
    public function makeAnchor($path)
    {
        $pathParts = explode(':', $path);
        $anchorParts = array_slice($pathParts, $this->getOption('max-level'));
        $anchorName = implode(':', $anchorParts);
        return $anchorName;
    }
    
    public function render()
    {
	    if ($this->getOption('one-page')) {
	        
	        for ($i = 0; $i < count($this->_toc); $i++) {
	            $this->renderSection($this->_toc->getChild($i));
	        }
	        
	    } else {
	        $section = $this->getOption('section');
	        
	        if (!$section) {
		        throw new Exception('Section has not been set.'); 
		    } else {
		        $this->renderSection($section);
		    }
	    }
    }
    
	protected function renderSection($section)
	{
	    $level = $section->getLevel();
	    $name = $section->getName();
	    $index = $section->getIndex();
	
	    if ($section->getLevel() == 1) {
	        echo '<div class="chapter">' . "\n";
	        echo "<h$level>Chapter $index ";
	    } else {
	        echo '<div class="section">' . "\n";
	        echo "<h$level>$index ";
	    }
	    
	    if ($section->getLevel() > $this->getOption('max-level')) {
	        echo '<a id="' . $this->makeAnchor($section->getPath()) . '">';
	        echo $name;
	        echo '</a>';
	    } else {
	        echo $name;
	    }
	    
	    echo "</h$level>\n";
	    
	    if ($level === 1 && !$this->getOption('one-page')) {
	        //$this->renderToc($this->_toc);
	    }
	    
	    echo $this->_wiki->transform($section->getText());
	    
	    for ($i = 0; $i < count($section); $i++) {
	        $this->renderSection($section->getChild($i));
	    }
	    
	    echo '</div>' . "\n";
	}
    
    public function findByPath($path)
    {
        return $this->_toc->findByPath($path);
    }
    
    public function findByIndex($index)
    {
        return $this->_toc->findByIndex($index);
    }
}