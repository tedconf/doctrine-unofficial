<?php

namespace Doctrine\Tests\ORM\Performance;

require_once __DIR__ . '/../../TestInit.php';

use Doctrine\Tests\Models\CMS\CmsUser;

/**
 * Description of InsertPerformanceTest
 *
 * @author robo
 */
class InsertPerformanceTest extends \Doctrine\Tests\OrmPerformanceTestCase
{
    protected function setUp() {
        $this->useModelSet('cms');
        parent::setUp();
    }

    /**
     * [romanb: 10000 objects in ~8 seconds]
     */
    public function testInsertPerformance()
    {
        $s = microtime(true);

        $conn = $this->_em->getConnection();

        $this->setMaxRunningTime(10);

        //$mem = memory_get_usage();
        //echo "Memory usage before: " . ($mem / 1024) . " KB" . PHP_EOL;

        for ($i=0; $i<10000; ++$i) {
            $user = new CmsUser;
            $user->status = 'user';
            $user->username = 'user' . $i;
            $user->name = 'Mr.Smith-' . $i;
            $this->_em->save($user);
            if (($i % 20) == 0) {
                $this->_em->flush();
                $this->_em->clear();
            }
        }

        //$memAfter = memory_get_usage();
        //echo "Memory usage after: " . ($memAfter / 1024) . " KB" . PHP_EOL;

        $e = microtime(true);

        echo ' Inserted 10000 records in ' . ($e - $s) . ' seconds' . PHP_EOL;
    }
}

