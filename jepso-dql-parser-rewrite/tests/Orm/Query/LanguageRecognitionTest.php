<?php
class Orm_Query_LanguageRecognitionTest extends Doctrine_OrmTestCase
{
    public function assertValidDql($dql)
    {
        $query = new Doctrine_Query;
        $query->setDql($dql);

        $parser = new Doctrine_Query_Parser($query);
        $parser->parse();

        if ($parser->getSyntaxErrorCount() > 0) {
            $this->fail(implode("\n", $parser->getErrors()));
        }
    }

    public function assertInvalidDql($dql)
    {
        $query = new Doctrine_Query;
        $query->setDql($dql);

        $parser = new Doctrine_Query_Parser($query);
        $parser->parse();

        if ($parser->getSyntaxErrorCount() == 0) {
            $this->fail('No syntax errors were detected, when syntax errors were expected');
        }
    }

    public function testEmptyQueryString()
    {
        $this->assertInvalidDql('');
    }

    public function testPlainFromClauseWithoutAlias()
    {
        $this->assertValidDql('SELECT * FROM User');
    }

    public function testPlainFromClauseWithAlias()
    {
        $this->assertValidDql('SELECT u.* FROM User u');
    }

    public function testSelectSingleComponentWithAsterisk()
    {
        $this->assertValidDql('SELECT u.* FROM User u');
    }

    public function testSelectSingleComponentWithMultipleColumns()
    {
        $this->assertValidDql('SELECT u.name, u.type FROM User u');
    }

    public function testSelectMultipleComponentsWithAsterisk()
    {
        $this->assertValidDql('SELECT u.*, p.* FROM User u, u.Phonenumber p');
    }

    public function testSelectDistinctIsSupported()
    {
        $this->assertValidDql('SELECT DISTINCT u.name FROM User u');
    }

    public function testAggregateFunctionInSelect()
    {
        $this->assertValidDql('SELECT COUNT(u.id) FROM User u');
    }

    public function testAggregateFunctionWithDistinctInSelect()
    {
        $this->assertValidDql('SELECT COUNT(DISTINCT u.name) FROM User u');
    }

    public function testFunctionalExpressionsSupportedInWherePart()
    {
        $this->assertValidDql("SELECT u.name FROM User u WHERE TRIM(u.name) = 'someone'");
    }

    public function testArithmeticExpressionsSupportedInWherePart()
    {
        $this->assertValidDql('SELECT a.* FROM Account a WHERE ((a.amount + 5000) * a.amount + 3) < 10000000');
    }

    public function testInExpressionSupportedInWherePart()
    {
        $this->assertValidDql('SELECT * FROM User WHERE User.id IN (1, 2)');
    }

    public function testNotInExpressionSupportedInWherePart()
    {
        $this->assertValidDql('SELECT * FROM User WHERE User.id NOT IN (1)');
    }

    public function testExistsExpressionSupportedInWherePart()
    {
        $this->assertValidDql('SELECT * FROM User WHERE EXISTS (SELECT g.id FROM UserGroupuser g WHERE g.user_id = u.id)');
    }

    public function testNotExistsExpressionSupportedInWherePart()
    {
        $this->assertValidDql('SELECT * FROM User WHERE NOT EXISTS (SELECT g.id FROM UserGroupuser g WHERE g.user_id = u.id)');
    }

    public function testLiteralValueAsInOperatorOperandIsSupported()
    {
        $this->assertValidDql('SELECT u.id FROM User u WHERE 1 IN (1, 2)');
    }

    public function testUpdateWorksWithOneColumn()
    {
        $this->assertValidDql("UPDATE User u SET u.name = 'someone'");
    }

    public function testUpdateWorksWithMultipleColumns()
    {
        $this->assertValidDql("UPDATE User u SET u.name = 'someone', u.email_id = 5");
    }

    public function testUpdateSupportsConditions()
    {
        $this->assertValidDql("UPDATE User u SET u.name = 'someone' WHERE u.id = 5");
    }

    public function testDeleteAll()
    {
        $this->assertValidDql('DELETE FROM Entity');
    }

    public function testDeleteWithCondition()
    {
        $this->assertValidDql('DELETE FROM Entity WHERE id = 3');
    }

    public function testDeleteWithLimit()
    {
        $this->assertValidDql('DELETE FROM Entity LIMIT 20');
    }

    public function testDeleteWithLimitAndOffset()
    {
        $this->assertValidDql('DELETE FROM Entity LIMIT 10 OFFSET 20');
    }

    public function testAdditionExpression()
    {
        $this->assertValidDql('SELECT u.*, (u.id + u.id) addition FROM User u');
    }

    public function testSubtractionExpression()
    {
        $this->assertValidDql('SELECT u.*, (u.id - u.id) subtraction FROM User u');
    }

    public function testDivisionExpression()
    {
        $this->assertValidDql('SELECT u.*, (u.id/u.id) division FROM User u');
    }

    public function testMultiplicationExpression()
    {
        $this->assertValidDql('SELECT u.*, (u.id * u.id) multiplication FROM User u');
    }

    public function testNegationExpression()
    {
        $this->assertValidDql('SELECT u.*, -u.id negation FROM User u');
    }

    public function testExpressionWithPrecedingPlusSign()
    {
        $this->assertValidDql('SELECT u.*, +u.id FROM User u');
    }

    public function testAggregateFunctionInHavingClause()
    {
        $this->assertValidDql('SELECT u.name FROM User u LEFT JOIN u.Phonenumber p HAVING COUNT(p.id) > 2');
        $this->assertValidDql("SELECT u.name FROM User u LEFT JOIN u.Phonenumber p HAVING MAX(u.name) = 'zYne'");
    }

    public function testMultipleAggregateFunctionsInHavingClause()
    {
        $this->assertValidDql("SELECT u.name FROM User u LEFT JOIN u.Phonenumber p HAVING MAX(u.name) = 'zYne'");
    }

    public function testLeftJoin()
    {
        $this->assertValidDql('SELECT * FROM User u LEFT JOIN u.UserGroup');
    }

    public function testJoin()
    {
        $this->assertValidDql('SELECT u.* FROM User u JOIN u.UserGroup');
    }

    public function testInnerJoin()
    {
        $this->assertValidDql('SELECT * FROM User u INNER JOIN u.UserGroup');
    }

    public function testMultipleLeftJoin()
    {
        $this->assertValidDql('SELECT * FROM User u LEFT JOIN u.UserGroup LEFT JOIN u.Phonenumber');
    }

    public function testMultipleInnerJoin()
    {
        $this->assertValidDql('SELECT u.name FROM User u INNER JOIN u.UserGroup INNER JOIN u.Phonenumber');
    }

    public function testMultipleInnerJoin2()
    {
        $this->assertValidDql('SELECT u.name FROM User u INNER JOIN u.UserGroup, u.Phonenumber');
    }

    public function testMixingOfJoins()
    {
        $this->assertValidDql('SELECT u.name, g.name, p.phonenumber FROM User u INNER JOIN u.UserGroup g LEFT JOIN u.Phonenumber p');
    }

    public function testMixingOfJoins2()
    {
        $this->assertValidDql('SELECT u.name, g.name, p.phonenumber FROM User u INNER JOIN u.UserGroup.Phonenumber p');
    }

    public function testOrderBySingleColumn()
    {
        $this->assertValidDql('SELECT u.name FROM User u ORDER BY u.name');
    }

    public function testOrderBySingleColumnAscending()
    {
        $this->assertValidDql('SELECT u.name FROM User u ORDER BY u.name ASC');
    }

    public function testOrderBySingleColumnDescending()
    {
        $this->assertValidDql('SELECT u.name FROM User u ORDER BY u.name DESC');
    }

    public function testOrderByMultipleColumns()
    {
        $this->assertValidDql('SELECT u.firstname, u.lastname FROM User u ORDER BY u.lastname DESC, u.firstname DESC');
    }

    public function testOrderByWithFunctionExpression()
    {
        $this->assertValidDql('SELECT u.name FROM User u ORDER BY COALESCE(u.id, u.name) DESC');
    }

    public function testSubselectInInExpression()
    {
        $this->assertValidDql("SELECT * FROM User u WHERE u.id NOT IN (SELECT u2.id FROM User u2 WHERE u2.name = 'zYne')");
    }

    public function testSubselectInSelectPart()
    {
        $this->assertValidDql("SELECT u.name, (SELECT COUNT(p.id) FROM Phonenumber p WHERE p.entity_id = u.id) pcount FROM User u WHERE u.name = 'zYne' LIMIT 1");
    }

    public function testPositionalInputParameter()
    {
        $this->assertValidDql('SELECT * FROM User u WHERE u.id = ?');
    }

    public function testNamedInputParameter()
    {
        $this->assertValidDql('SELECT * FROM User u WHERE u.id = :id');
    }

    public function testCustomJoinsAndWithKeywordSupported()
    {
        $this->assertValidDql('SELECT c.*, c2.*, d.* FROM Record_Country c INNER JOIN c.City c2 WITH c2.id = 2 WHERE c.id = 1');
    }

    public function testJoinConditionsSupported()
    {
        $this->assertValidDql("SELECT u.name, p.id FROM User u LEFT JOIN u.Phonenumber p ON p.phonenumber = '123 123'");
    }

    public function testIndexByClauseWithOneComponent()
    {
        $this->assertValidDql('SELECT * FROM Record_City c INDEX BY name');
    }

    public function testIndexBySupportsJoins()
    {
        $this->assertValidDql('SELECT * FROM Record_Country c LEFT JOIN c.City c2 INDEX BY name');
    }

    public function testIndexBySupportsJoins2()
    {
        $this->assertValidDql('SELECT * FROM User u INDEX BY name LEFT JOIN u.Phonenumber p INDEX BY phonenumber');
    }

    public function testBetweenExpressionSupported()
    {
        $this->assertValidDql("SELECT * FROM User u WHERE u.name BETWEEN 'jepso' AND 'zYne'");
    }

    public function testNotBetweenExpressionSupported()
    {
        $this->assertValidDql("SELECT * FROM User u WHERE u.name NOT BETWEEN 'jepso' AND 'zYne'");
    }

    public function testAllExpression()
    {
        $this->assertValidDql('SELECT * FROM Employee e WHERE e.salary > ALL (SELECT m.salary FROM Manager m WHERE m.department = e.department)');
    }

    public function testAnyExpression()
    {
        $this->assertValidDql('SELECT * FROM Employee e WHERE e.salary > ANY (SELECT m.salary FROM Manager m WHERE m.department = e.department)');
    }

    public function testSomeExpression()
    {
        $this->assertValidDql('SELECT * FROM Employee e WHERE e.salary > SOME (SELECT m.salary FROM Manager m WHERE m.department = e.department)');
    }

    public function testLikeExpression()
    {
        $this->assertValidDql("SELECT u.id FROM User u WHERE u.name LIKE 'z%'");
    }

    public function testNotLikeExpression()
    {
        $this->assertValidDql("SELECT u.id FROM User u WHERE u.name NOT LIKE 'z%'");
    }

    public function testLikeExpressionWithCustomEscapeCharacter()
    {
        $this->assertValidDql("SELECT u.id FROM User u WHERE u.name LIKE 'z|%' ESCAPE '|'");
    }}
