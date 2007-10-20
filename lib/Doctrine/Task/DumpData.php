<?php
/*
 *  $Id: GenerateSql.php 2761 2007-10-07 23:42:29Z zYne $
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
 * Doctrine_Task_DumpData
 *
 * @package     Doctrine
 * @subpackage  Task
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.phpdoctrine.com
 * @since       1.0
 * @version     $Revision: 2761 $
 * @author      Jonathan H. Wage <jwage@mac.com>
 */
class Doctrine_Task_DumpData extends Doctrine_Task
{
    public $description          =   'Dump data to a yaml data fixture file.',
           $requiredArguments    =   array('data_fixtures_path' =>  'Specify path to write the yaml data fixtures file to.',
                                           'models_path'        =>  'Specify path to your Doctrine_Record definitions.'),
           $optionalArguments    =   array('individual_files'   =>  'Specify whether or not you want to dump to individual files. One file per model.');
    
    public function execute()
    {
        Doctrine::loadModels($this->getArgument('models_path'));
        
        $individualFiles = $this->getArgument('individual_files') ? true:false;
        
        $path = $this->getArgument('data_fixtures_path');
        
        if (!$individualFiles) {
            $e = explode('.', $this->getArgument('data_fixtures_path'));
        
            if (end($e) !== 'yml') {
                $path = $this->getArgument('data_fixtures_path'). DIRECTORY_SEPARATOR . 'data.yml';
            }
        }
        
        Doctrine::dumpData($path, $individualFiles);
        
        $this->dispatcher->notify(sprintf('Dumped data successfully to: %s', $path));
    }
}