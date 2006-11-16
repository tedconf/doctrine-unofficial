<?php
require_once("UnitTestCase.php");

class Doctrine_ConfigurableTestCase extends Doctrine_UnitTestCase {
    public function prepareTables() { }
    public function prepareData() { }
    public function testSetAttribute() {
        $table = $this->connection->getTable("User");
        /**
        $this->manager->setAttribute(Doctrine::ATTR_CACHE_TTL,100);
        $this->assertEqual($this->manager->getAttribute(Doctrine::ATTR_CACHE_TTL),100);

        $this->manager->setAttribute(Doctrine::ATTR_CACHE_SIZE,1);
        $this->assertEqual($this->manager->getAttribute(Doctrine::ATTR_CACHE_SIZE),1);

        $this->manager->setAttribute(Doctrine::ATTR_CACHE_DIR,"%ROOT%".DIRECTORY_SEPARATOR."cache");
        $this->assertEqual($this->manager->getAttribute(Doctrine::ATTR_CACHE_DIR),$this->manager->getRoot().DIRECTORY_SEPARATOR."cache");
        */
        $this->manager->setAttribute(Doctrine::ATTR_FETCHMODE,Doctrine::FETCH_LAZY);
        $this->assertEqual($this->manager->getAttribute(Doctrine::ATTR_FETCHMODE),Doctrine::FETCH_LAZY);

        $this->manager->setAttribute(Doctrine::ATTR_BATCH_SIZE, 5);
        $this->assertEqual($this->manager->getAttribute(Doctrine::ATTR_BATCH_SIZE),5);

        $this->manager->setAttribute(Doctrine::ATTR_LISTENER, new Doctrine_EventListener_Debugger());
        $this->assertTrue($this->manager->getAttribute(Doctrine::ATTR_LISTENER) instanceof Doctrine_EventListener_Debugger);

        $this->manager->setAttribute(Doctrine::ATTR_LOCKMODE, Doctrine::LOCK_PESSIMISTIC);
        $this->assertEqual($this->manager->getAttribute(Doctrine::ATTR_LOCKMODE), Doctrine::LOCK_PESSIMISTIC);

        // test invalid arguments
        /**
        try {
            $this->manager->setAttribute(Doctrine::ATTR_CACHE_TTL,-12);
        } catch(Exception $e) {
            $this->assertTrue($e instanceof Exception);
        }
        try {
            $this->manager->setAttribute(Doctrine::ATTR_CACHE_SIZE,-12);
        } catch(Exception $e) {
            $this->assertTrue($e instanceof Exception);
        }
        try {
            $this->manager->setAttribute(Doctrine::ATTR_BATCH_SIZE,-12);
        } catch(Exception $e) {
            $this->assertTrue($e instanceof Exception);
        }
        */
        try {
            $this->connection->beginTransaction();
            $this->manager->setAttribute(Doctrine::ATTR_LOCKMODE, Doctrine::LOCK_OPTIMISTIC);
        } catch(Exception $e) {
            $this->assertTrue($e instanceof Exception);
            $this->connection->commit();
        }

        $e = false;
        try {
            $this->manager->setAttribute(Doctrine::ATTR_COLL_KEY, "name");
        } catch(Exception $e) {
        }
        $this->assertTrue($e instanceof Exception);

        $e = false;
        try {
            $table->setAttribute(Doctrine::ATTR_COLL_KEY, "unknown");
        } catch(Exception $e) {
        }
        $this->assertTrue($e instanceof Exception);

        $e = true;
        try {
            $table->setAttribute(Doctrine::ATTR_COLL_KEY, "name");
        } catch(Exception $e) {
        }
        $this->assertTrue($e);

        $e = false;
        try {
            $this->connection->beginTransaction();
            $this->connection->setAttribute(Doctrine::ATTR_LOCKMODE, Doctrine::LOCK_PESSIMISTIC);
        } catch(Exception $e) {
            $this->assertTrue($e instanceof Exception);
            $this->connection->commit();
        }

    }
    public function testGetAttributes() {
        $this->assertTrue(is_array($this->manager->getAttributes()));
    }
}
?>
